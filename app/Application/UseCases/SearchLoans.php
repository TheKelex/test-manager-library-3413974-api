<?php

namespace App\Application\UseCases;

use App\Application\DTO\LoanSearch;
use App\Application\DTO\PageResult;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;

final readonly class SearchLoans
{
    public function __construct(
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    public function execute(LoanSearch $criteria): PageResult
    {
        return $this->loans->searchLoans($criteria, $this->clock->now());
    }
}
