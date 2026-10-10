<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Domain\Book\Book;

final readonly class WithdrawBook
{
    public function __construct(
        private BookRepository $books,
        private Clock $clock,
    ) {}

    public function execute(string $id): Book
    {
        $book = $this->books->findActiveById($id)
            ?? throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');

        $updated = $book->withdrawFromCatalog($this->clock->now(), false);
        $this->books->withdrawBook($updated);

        return $updated;
    }
}
