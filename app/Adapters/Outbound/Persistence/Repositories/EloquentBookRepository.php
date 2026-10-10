<?php

namespace App\Adapters\Outbound\Persistence\Repositories;

use App\Adapters\Outbound\Persistence\Eloquent\Models\BookRecord;
use App\Application\DTO\BookSearch;
use App\Application\DTO\PageResult;
use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book\Book;
use App\Domain\Shared\Isbn;
use App\Domain\Shared\RuleViolation;
use Illuminate\Support\Facades\DB;

final class EloquentBookRepository implements BookRepository
{
    public function findActiveById(string $id): ?Book
    {
        $row = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->where('b.id', $id)
            ->whereNull('b.deleted_at')
            ->select('b.*', 'c.name as category_name')
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findActiveByIsbn(Isbn $isbn, ?string $exceptId = null): ?Book
    {
        $query = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->where('b.isbn', $isbn->value)
            ->whereNull('b.deleted_at')
            ->select('b.*', 'c.name as category_name');
        if ($exceptId !== null) {
            $query->where('b.id', '<>', $exceptId);
        }
        $row = $query->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findCategoryName(string $categoryId): ?string
    {
        $name = DB::table('library.category')->where('id', $categoryId)->value('name');

        return $name === null ? null : (string) $name;
    }

    public function findActiveByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereIn('b.id', $ids)
            ->whereNull('b.deleted_at')
            ->select('b.*', 'c.name as category_name')
            ->get()
            ->keyBy('id');
        $books = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $books[] = $this->toDomain($rows[$id]);
            }
        }

        return $books;
    }

    public function saveBook(Book $book): void
    {
        $record = BookRecord::query()->firstOrNew(['id' => $book->id]);
        $record->fill([
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn->value,
            'category_id' => $book->categoryId,
            'total_copies' => $book->totalCopies,
            'available_copies' => $book->availableCopies,
            'cover_key' => $book->coverKey,
            'deleted_at' => $book->deletedAt,
        ])->save();
    }

    public function updateBookDetails(Book $book): void
    {
        DB::transaction(function () use ($book): void {
            $record = BookRecord::query()->whereKey($book->id)->lockForUpdate()->first();
            if ($record === null || $record->deleted_at !== null) {
                throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');
            }
            $categoryName = DB::table('library.category')->where('id', $book->categoryId)->value('name');
            if ($categoryName === null) {
                throw new UseCaseError('VALIDATION_ERROR', 'La categoría seleccionada no existe.');
            }
            $current = $this->toDomain((object) [
                ...$record->getAttributes(),
                'category_name' => (string) $categoryName,
            ]);
            try {
                $updated = $current->changeDetails(
                    $book->title,
                    $book->author,
                    $book->categoryId,
                    (string) $categoryName,
                    $book->totalCopies,
                );
            } catch (RuleViolation $exception) {
                throw new UseCaseError($exception->errorCode, $exception->getMessage());
            }
            $record->fill([
                'title' => $updated->title,
                'author' => $updated->author,
                'category_id' => $updated->categoryId,
                'total_copies' => $updated->totalCopies,
                'available_copies' => $updated->availableCopies,
            ])->save();
        }, 3);
    }

    public function updateCover(Book $book): void
    {
        $updated = BookRecord::query()
            ->whereKey($book->id)
            ->whereNull('deleted_at')
            ->update(['cover_key' => $book->coverKey]);
        if ($updated !== 1) {
            throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');
        }
    }

    public function withdrawBook(Book $book): void
    {
        DB::transaction(function () use ($book): void {
            $record = BookRecord::query()->whereKey($book->id)->lockForUpdate()->first();
            if ($record === null || $record->deleted_at !== null) {
                throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');
            }
            if ($this->hasOpenLoans($book->id)) {
                throw new UseCaseError('BOOK_HAS_OPEN_LOANS', 'No se puede dar de baja un libro con préstamos abiertos.');
            }
            $record->forceFill(['deleted_at' => $book->deletedAt])->save();
        }, 3);
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return DB::table('library.loan_item')
            ->where('book_id', $bookId)
            ->whereNull('returned_at')
            ->exists();
    }

    public function search(BookSearch $criteria): PageResult
    {
        $query = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at');
        if ($criteria->categoryId !== null) {
            $query->where('b.category_id', $criteria->categoryId);
        }
        if ($criteria->query !== null && trim($criteria->query) !== '') {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($criteria->query)).'%';
            $query->where(static function ($builder) use ($term): void {
                $builder->whereRaw('b.title ILIKE ?', [$term])->orWhereRaw('b.author ILIKE ?', [$term]);
            });
        }
        $total = (clone $query)->count('b.id');
        $rows = $query->orderBy('b.title')
            ->offset(($criteria->page - 1) * $criteria->size)
            ->limit($criteria->size)
            ->select('b.*', 'c.name as category_name')
            ->get();

        return new PageResult(
            $rows->map(fn ($row): Book => $this->toDomain($row))->all(),
            $criteria->page,
            $criteria->size,
            $total,
        );
    }

    public function toDomain(object $row): Book
    {
        return Book::reconstitute(
            (string) $row->id,
            (string) $row->title,
            (string) $row->author,
            Isbn::from((string) $row->isbn),
            (string) $row->category_id,
            (string) $row->category_name,
            (int) $row->total_copies,
            (int) $row->available_copies,
            $row->cover_key === null ? null : (string) $row->cover_key,
            $row->deleted_at === null ? null : new \DateTimeImmutable((string) $row->deleted_at),
        );
    }
}
