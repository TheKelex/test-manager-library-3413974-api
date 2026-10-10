<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Domain\Book\Book;

final readonly class RemoveCover
{
    public function __construct(
        private BookRepository $books,
        private CoverStorage $covers,
    ) {}

    public function execute(string $bookId): Book
    {
        $book = $this->books->findActiveById($bookId)
            ?? throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');
        $oldKey = $book->coverKey;
        $updated = $book->withCover(null);
        $this->books->saveBook($updated);

        if ($oldKey !== null) {
            $this->covers->delete($oldKey);
        }

        return $updated;
    }
}
