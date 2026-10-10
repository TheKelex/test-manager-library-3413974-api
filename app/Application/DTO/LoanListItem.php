<?php

namespace App\Application\DTO;

use App\Domain\Loan\Loan;

final readonly class LoanListItem
{
    public function __construct(
        public Loan $loan,
        public string $memberName,
        public string $status,
    ) {}
}
