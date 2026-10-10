<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book\Book;

final readonly class UpdateBook
{
    public function __construct(private BookRepository $books) {}

    public function execute(
        string $id,
        string $title,
        string $author,
        string $categoryId,
        int $totalCopies,
    ): Book {
        $book = $this->books->findActiveById($id)
            ?? throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');
        $categoryName = $this->books->findCategoryName($categoryId);
        if ($categoryName === null) {
            throw new UseCaseError('VALIDATION_ERROR', 'La categoría seleccionada no existe.');
        }

        $updated = $book->changeDetails($title, $author, $categoryId, $categoryName, $totalCopies);
        $this->books->saveBook($updated);

        return $updated;
    }
}
