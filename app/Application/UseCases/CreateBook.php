<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\IdGenerator;
use App\Domain\Book\Book;
use App\Domain\Shared\Isbn;

final readonly class CreateBook
{
    public function __construct(
        private BookRepository $books,
        private IdGenerator $ids,
    ) {}

    public function execute(
        string $title,
        string $author,
        string $isbn,
        string $categoryId,
        int $totalCopies,
    ): Book {
        $value = Isbn::from($isbn);
        if ($this->books->findActiveByIsbn($value) !== null) {
            throw new UseCaseError('ISBN_TAKEN', 'El ISBN ya pertenece a un libro activo.');
        }
        $categoryName = $this->books->findCategoryName($categoryId);
        if ($categoryName === null) {
            throw new UseCaseError('VALIDATION_ERROR', 'La categoría seleccionada no existe.');
        }

        $book = Book::create($this->ids->newId(), $title, $author, $value, $categoryId, $categoryName, $totalCopies);
        $this->books->saveBook($book);

        return $book;
    }
}
