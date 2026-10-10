<?php

namespace App\Adapters\Outbound\Persistence\Repositories;

use App\Adapters\Outbound\Persistence\Eloquent\Models\BookRecord;
use App\Adapters\Outbound\Persistence\Eloquent\Models\LoanItemRecord;
use App\Adapters\Outbound\Persistence\Eloquent\Models\LoanRecord;
use App\Application\DTO\LoanListItem;
use App\Application\DTO\LoanSearch;
use App\Application\DTO\MemberLoanSummary;
use App\Application\DTO\OverdueCandidate;
use App\Application\DTO\PageResult;
use App\Application\DTO\ReturnContext;
use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Book\Book;
use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;
use App\Domain\Shared\Isbn;
use App\Domain\Shared\RuleViolation;
use Illuminate\Support\Facades\DB;

final class EloquentLoanRepository implements LoanRepository
{
    public function memberLoanSummary(string $memberId, \DateTimeImmutable $now): MemberLoanSummary
    {
        $items = DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('li.returned_at');

        return new MemberLoanSummary(
            (clone $items)->count(),
            (clone $items)->where('li.due_at', '<', $now)->exists(),
        );
    }

    public function saveLendingAtomically(Loan $loan, array $books): void
    {
        DB::transaction(function () use ($loan, $books): void {
            DB::table('library.member')->where('id', $loan->memberId)->lockForUpdate()->first()
                ?? throw new UseCaseError('NOT_FOUND', 'El socio no existe.');

            $now = $loan->loanedAt;
            $summary = $this->memberLoanSummary($loan->memberId, $now);
            if ($summary->hasOverdue) {
                throw new UseCaseError('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y no puede pedir otro préstamo.');
            }
            if ($summary->activeItems + count($loan->items) > 3) {
                throw new UseCaseError('MEMBER_LIMIT_EXCEEDED', 'El socio no puede tener más de tres libros sin devolver.');
            }

            $bookIds = array_map(static fn (Book $book): string => $book->id, $books);
            sort($bookIds, SORT_STRING);
            $lockedRows = BookRecord::query()
                ->whereIn('id', $bookIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($lockedRows->count() !== count($bookIds)) {
                throw new UseCaseError('NOT_FOUND', 'Uno o más libros no existen o están dados de baja.');
            }

            $itemsByBookId = [];
            foreach ($loan->items as $item) {
                $itemsByBookId[$item->bookId] = $item;
            }
            $updatedBooks = [];
            $updatedLoan = Loan::start($loan->id, $loan->memberId, $loan->loanedByUserId, $loan->loanedAt);
            foreach ($books as $requestedBook) {
                $record = $lockedRows->get($requestedBook->id);
                if ($record === null || $record->deleted_at !== null) {
                    throw new UseCaseError('NOT_FOUND', 'Uno o más libros no existen o están dados de baja.');
                }
                $categoryName = DB::table('library.category')->where('id', $record->category_id)->value('name');
                if ($categoryName === null) {
                    throw new UseCaseError('NOT_FOUND', 'La categoría del libro no existe.');
                }
                $currentBook = $this->bookFromRecord($record, (string) $categoryName);
                try {
                    $updatedBooks[] = $currentBook->withdraw();
                } catch (RuleViolation $exception) {
                    throw new UseCaseError($exception->errorCode, $exception->getMessage());
                }
                $item = $itemsByBookId[$requestedBook->id] ?? throw new \LogicException('Loan item missing for book.');
                $updatedLoan = $updatedLoan->addBook($currentBook, $item->id, (string) $categoryName);
            }

            $loanRecord = new LoanRecord;
            $loanRecord->fill([
                'id' => $updatedLoan->id,
                'member_id' => $updatedLoan->memberId,
                'loaned_by_user_id' => $updatedLoan->loanedByUserId,
                'loaned_at' => $updatedLoan->loanedAt,
            ])->save();
            foreach ($updatedLoan->items as $item) {
                $itemRecord = new LoanItemRecord;
                $itemRecord->fill([
                    'id' => $item->id,
                    'loan_id' => $item->loanId,
                    'book_id' => $item->bookId,
                    'book_title' => $item->bookTitle,
                    'isbn' => $item->isbn,
                    'category_name' => $item->categoryName,
                    'due_at' => $item->dueAt,
                    'returned_at' => null,
                ])->save();
            }
            foreach ($updatedBooks as $book) {
                (new EloquentBookRepository)->saveBook($book);
            }
        }, 3);
    }

    public function findReturnContext(string $loanId, string $itemId): ?ReturnContext
    {
        $itemRow = DB::table('library.loan_item')->where('loan_id', $loanId)->where('id', $itemId)->first();
        if ($itemRow === null) {
            return null;
        }
        $loan = $this->loadLoan($loanId);
        $bookRow = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->where('b.id', $itemRow->book_id)
            ->select('b.*', 'c.name as category_name')
            ->first();

        return $loan === null || $bookRow === null
            ? null
            : new ReturnContext(
                $loan,
                $this->itemFromRecord($itemRow),
                (new EloquentBookRepository)->toDomain($bookRow),
            );
    }

    public function findLoanById(string $loanId): ?Loan
    {
        return $this->loadLoan($loanId);
    }

    public function searchLoans(LoanSearch $criteria, \DateTimeImmutable $now): PageResult
    {
        $query = DB::table('library.loan as l')
            ->join('library.member as m', 'm.id', '=', 'l.member_id');
        if ($criteria->from !== null) {
            $query->where('l.loaned_at', '>=', $criteria->from->setTime(0, 0));
        }
        if ($criteria->to !== null) {
            $query->where('l.loaned_at', '<', $criteria->to->setTime(0, 0)->modify('+1 day'));
        }
        if ($criteria->memberId !== null) {
            $query->where('l.member_id', $criteria->memberId);
        }
        $activeItems = static fn ($builder) => $builder->selectRaw('1')
            ->from('library.loan_item as li')
            ->whereColumn('li.loan_id', 'l.id')
            ->whereNull('li.returned_at');
        $overdueItems = static fn ($builder) => $activeItems($builder)->where('li.due_at', '<', $now);
        if ($criteria->status === 'closed') {
            $query->whereNotExists($activeItems);
        } elseif ($criteria->status === 'overdue') {
            $query->whereExists($overdueItems);
        } elseif ($criteria->status === 'open') {
            $query->whereExists($activeItems)->whereNotExists($overdueItems);
        }
        $total = (clone $query)->count('l.id');
        $rows = $query->orderByDesc('l.loaned_at')
            ->orderBy('l.id')
            ->offset(($criteria->page - 1) * $criteria->size)
            ->limit($criteria->size)
            ->get(['l.id', 'm.full_name']);
        $items = [];
        foreach ($rows as $row) {
            $loan = $this->loadLoan((string) $row->id);
            if ($loan !== null) {
                $items[] = new LoanListItem($loan, (string) $row->full_name, $loan->statusAt($now));
            }
        }

        return new PageResult($items, $criteria->page, $criteria->size, $total);
    }

    public function returnItemAtomically(Loan $loan, LoanItem $item, Book $book): void
    {
        DB::transaction(function () use ($loan, $item): void {
            $itemRow = DB::table('library.loan_item')
                ->where('loan_id', $loan->id)
                ->where('id', $item->id)
                ->lockForUpdate()
                ->first();
            if ($itemRow === null) {
                throw new UseCaseError('NOT_FOUND', 'La línea de préstamo no existe.');
            }
            if ($itemRow->returned_at !== null) {
                throw new UseCaseError('ITEM_ALREADY_RETURNED', 'La línea de préstamo ya fue devuelta.');
            }

            $bookRow = BookRecord::query()->whereKey($itemRow->book_id)->lockForUpdate()->first();
            if ($bookRow === null) {
                throw new UseCaseError('NOT_FOUND', 'El libro no existe.');
            }
            $categoryName = DB::table('library.category')->where('id', $bookRow->category_id)->value('name');
            if ($categoryName === null) {
                throw new UseCaseError('NOT_FOUND', 'La categoría del libro no existe.');
            }
            $currentBook = $this->bookFromRecord($bookRow, (string) $categoryName);
            try {
                $updatedBook = $currentBook->restock();
            } catch (RuleViolation $exception) {
                throw new UseCaseError($exception->errorCode, $exception->getMessage());
            }

            DB::table('library.loan_item')->where('id', $item->id)->update(['returned_at' => $item->returnedAt]);
            (new EloquentBookRepository)->saveBook($updatedBook);
        }, 3);
    }

    public function findOverdueItems(\DateTimeImmutable $now): array
    {
        return DB::table('library.loan_item as li')
            ->join('library.loan as l', 'l.id', '=', 'li.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('li.returned_at')
            ->where('li.due_at', '<', $now)
            ->orderBy('li.due_at')
            ->get(['l.id as loan_id', 'li.id as item_id', 'li.book_title', 'm.full_name', 'li.due_at'])
            ->map(static fn ($row): OverdueCandidate => new OverdueCandidate(
                (string) $row->loan_id,
                (string) $row->item_id,
                (string) $row->book_title,
                (string) $row->full_name,
                new \DateTimeImmutable((string) $row->due_at),
            ))
            ->all();
    }

    private function loadLoan(string $loanId): ?Loan
    {
        $row = LoanRecord::query()->find($loanId);
        if ($row === null) {
            return null;
        }
        $items = LoanItemRecord::query()->where('loan_id', $loanId)->orderBy('id')->get()
            ->map(fn ($item): LoanItem => $this->itemFromRecord($item))
            ->all();

        return Loan::reconstitute(
            (string) $row->id,
            (string) $row->member_id,
            (string) $row->loaned_by_user_id,
            new \DateTimeImmutable((string) $row->loaned_at),
            $items,
        );
    }

    private function itemFromRecord(object $record): LoanItem
    {
        return new LoanItem(
            (string) $record->id,
            (string) $record->loan_id,
            (string) $record->book_id,
            (string) $record->book_title,
            (string) $record->isbn,
            (string) $record->category_name,
            new \DateTimeImmutable((string) $record->due_at),
            $record->returned_at === null ? null : new \DateTimeImmutable((string) $record->returned_at),
        );
    }

    private function bookFromRecord(object $record, string $categoryName): Book
    {
        return Book::reconstitute(
            (string) $record->id,
            (string) $record->title,
            (string) $record->author,
            Isbn::from((string) $record->isbn),
            (string) $record->category_id,
            $categoryName,
            (int) $record->total_copies,
            (int) $record->available_copies,
            $record->cover_key === null ? null : (string) $record->cover_key,
            $record->deleted_at === null ? null : new \DateTimeImmutable((string) $record->deleted_at),
        );
    }
}
