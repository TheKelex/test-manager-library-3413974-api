<?php

namespace App\Application\Ports\Outbound;

use App\Application\DTO\BookSearch;
use App\Application\DTO\PageResult;
use App\Domain\Book\Book;
use App\Domain\Shared\Isbn;

interface BookRepository
{
    public function findActiveById(string $id): ?Book;

    public function findActiveByIsbn(Isbn $isbn, ?string $exceptId = null): ?Book;

    public function findCategoryName(string $categoryId): ?string;

    /** @param list<string> $ids @return list<Book> */
    public function findActiveByIds(array $ids): array;

    public function saveBook(Book $book): void;

    public function hasOpenLoans(string $bookId): bool;

    public function search(BookSearch $criteria): PageResult;
}
