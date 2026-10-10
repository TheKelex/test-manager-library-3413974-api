<?php

namespace Tests\Fakes;

use App\Application\DTO\BookSearch;
use App\Application\DTO\LoanListItem;
use App\Application\DTO\LoanSearch;
use App\Application\DTO\MemberLoanSummary;
use App\Application\DTO\OverdueCandidate;
use App\Application\DTO\PageResult;
use App\Application\DTO\ReturnContext;
use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\Book\Book;
use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;
use App\Domain\Member\Member;
use App\Domain\Shared\Isbn;
use App\Domain\User\User;

final class FakeLibrary implements BookRepository, LoanRepository, MemberRepository, UserRepository
{
    /** @var array<string, Book> */
    public array $books = [];

    /** @var array<string, Loan> */
    public array $loans = [];

    /** @var array<string, Member> */
    public array $members = [];

    /** @var array<string, User> */
    public array $users = [];

    public int $bookSaveCount = 0;

    public int $lendingSaveCount = 0;

    public int $returnSaveCount = 0;

    public function findActiveById(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;

        return $book !== null && $book->deletedAt === null ? $book : null;
    }

    public function findActiveByIsbn(Isbn $isbn, ?string $exceptId = null): ?Book
    {
        foreach ($this->books as $book) {
            if ($book->isbn->value === $isbn->value && $book->deletedAt === null && $book->id !== $exceptId) {
                return $book;
            }
        }

        return null;
    }

    public function findCategoryName(string $categoryId): ?string
    {
        return in_array($categoryId, ['category-1', 'category-2'], true) ? match ($categoryId) {
            'category-1' => 'Novela',
            'category-2' => 'Ciencia',
        } : null;
    }

    public function findActiveByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            $book = $this->findActiveById($id);
            if ($book !== null) {
                $found[] = $book;
            }
        }

        return $found;
    }

    public function saveBook(Book $book): void
    {
        $this->books[$book->id] = $book;
        $this->bookSaveCount++;
    }

    public function updateBookDetails(Book $book): void
    {
        $this->books[$book->id] = $book;
        $this->bookSaveCount++;
    }

    public function updateCover(Book $book): void
    {
        $this->books[$book->id] = $book;
        $this->bookSaveCount++;
    }

    public function withdrawBook(Book $book): void
    {
        if ($this->hasOpenLoans($book->id)) {
            throw new UseCaseError(
                'BOOK_HAS_OPEN_LOANS',
                'No se puede dar de baja un libro con préstamos abiertos.',
            );
        }
        $this->books[$book->id] = $book;
        $this->bookSaveCount++;
    }

    public function hasOpenLoans(string $bookId): bool
    {
        foreach ($this->loans as $loan) {
            foreach ($loan->items as $item) {
                if ($item->bookId === $bookId && $item->returnedAt === null) {
                    return true;
                }
            }
        }

        return false;
    }

    public function search(BookSearch $criteria): PageResult
    {
        $books = array_values(array_filter($this->books, static function (Book $book) use ($criteria): bool {
            if ($book->deletedAt !== null) {
                return false;
            }
            if ($criteria->categoryId !== null && $book->categoryId !== $criteria->categoryId) {
                return false;
            }
            if ($criteria->query !== null && $criteria->query !== '') {
                $query = mb_strtolower($criteria->query);
                if (! str_contains(mb_strtolower($book->title), $query)
                    && ! str_contains(mb_strtolower($book->author), $query)) {
                    return false;
                }
            }

            return true;
        }));
        usort($books, static fn (Book $a, Book $b): int => $a->title <=> $b->title);
        $total = count($books);
        $items = array_slice($books, ($criteria->page - 1) * $criteria->size, $criteria->size);

        return new PageResult($items, $criteria->page, $criteria->size, $total);
    }

    public function memberLoanSummary(string $memberId, \DateTimeImmutable $now): MemberLoanSummary
    {
        $active = 0;
        $overdue = false;
        foreach ($this->loans as $loan) {
            if ($loan->memberId !== $memberId) {
                continue;
            }
            foreach ($loan->items as $item) {
                if ($item->returnedAt === null) {
                    $active++;
                    $overdue = $overdue || $item->dueAt < $now;
                }
            }
        }

        return new MemberLoanSummary($active, $overdue);
    }

    public function saveLendingAtomically(Loan $loan, array $books): void
    {
        $this->lendingSaveCount++;
        foreach ($books as $book) {
            $this->books[$book->id] = $book;
        }
        $this->loans[$loan->id] = $loan;
    }

    public function findReturnContext(string $loanId, string $itemId): ?ReturnContext
    {
        $loan = $this->loans[$loanId] ?? null;
        if ($loan === null) {
            return null;
        }
        foreach ($loan->items as $item) {
            if ($item->id === $itemId) {
                $book = $this->books[$item->bookId] ?? null;

                return $book === null ? null : new ReturnContext($loan, $item, $book);
            }
        }

        return null;
    }

    public function findLoanById(string $loanId): ?Loan
    {
        return $this->loans[$loanId] ?? null;
    }

    public function searchLoans(LoanSearch $criteria, \DateTimeImmutable $now): PageResult
    {
        $items = [];
        foreach ($this->loans as $loan) {
            if ($criteria->memberId !== null && $loan->memberId !== $criteria->memberId) {
                continue;
            }
            if ($criteria->from !== null && $loan->loanedAt < $criteria->from->setTime(0, 0)) {
                continue;
            }
            if ($criteria->to !== null && $loan->loanedAt >= $criteria->to->setTime(0, 0)->modify('+1 day')) {
                continue;
            }
            $status = $loan->statusAt($now);
            if ($criteria->status !== null && $criteria->status !== $status) {
                continue;
            }
            $member = $this->members[$loan->memberId] ?? null;
            if ($member !== null) {
                $items[] = new LoanListItem($loan, $member->fullName, $status);
            }
        }
        usort($items, static fn (LoanListItem $a, LoanListItem $b): int => $b->loan->loanedAt <=> $a->loan->loanedAt);
        $total = count($items);

        return new PageResult(array_slice($items, ($criteria->page - 1) * $criteria->size, $criteria->size), $criteria->page, $criteria->size, $total);
    }

    public function returnItemAtomically(Loan $loan, LoanItem $item, Book $book): void
    {
        $this->returnSaveCount++;
        $this->loans[$loan->id] = $loan;
        $this->books[$book->id] = $book;
    }

    public function findOverdueItems(\DateTimeImmutable $now): array
    {
        $candidates = [];
        foreach ($this->loans as $loan) {
            $member = $this->members[$loan->memberId] ?? null;
            if ($member === null) {
                continue;
            }
            foreach ($loan->items as $item) {
                if ($item->returnedAt === null && $item->dueAt < $now) {
                    $candidates[] = new OverdueCandidate(
                        $loan->id,
                        $item->id,
                        $item->bookTitle,
                        $member->fullName,
                        $item->dueAt,
                    );
                }
            }
        }

        return $candidates;
    }

    public function findById(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function findByDocumentNumber(string $documentNumber): ?Member
    {
        foreach ($this->members as $member) {
            if ($member->documentNumber === $documentNumber) {
                return $member;
            }
        }

        return null;
    }

    public function saveMember(Member $member): void
    {
        $this->members[$member->id] = $member;
    }

    public function searchMembers(?string $query, int $page, int $size): PageResult
    {
        $members = array_values(array_filter($this->members, static fn (Member $member): bool => $query === null
            || str_contains(mb_strtolower($member->fullName), mb_strtolower($query))
            || str_contains(mb_strtolower($member->documentNumber), mb_strtolower($query))));
        usort($members, static fn (Member $a, Member $b): int => $a->fullName <=> $b->fullName);
        $total = count($members);

        return new PageResult(array_slice($members, ($page - 1) * $size, $size), $page, $size, $total);
    }

    public function activeLoanCount(string $memberId): int
    {
        $count = 0;
        foreach ($this->loans as $loan) {
            if ($loan->memberId === $memberId) {
                $count += $loan->activeItemsCount();
            }
        }

        return $count;
    }

    public function findByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->username === mb_strtolower(trim($username))) {
                return $user;
            }
        }

        return null;
    }

    public function saveUser(User $record): void
    {
        $this->users[$record->id] = $record;
    }
}
