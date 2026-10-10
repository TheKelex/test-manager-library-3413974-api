<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book\Book;

final readonly class GetBook
{
    public function __construct(private BookRepository $books) {}

    public function execute(string $id): Book
    {
        return $this->books->findActiveById($id)
            ?? throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');
    }
}
