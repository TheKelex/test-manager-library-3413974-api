<?php

namespace App\Application\UseCases;

use App\Application\DTO\BookSearch;
use App\Application\DTO\PageResult;
use App\Application\Ports\Outbound\BookRepository;

final readonly class SearchBooks
{
    public function __construct(private BookRepository $books) {}

    public function execute(BookSearch $criteria): PageResult
    {
        return $this->books->search($criteria);
    }
}
