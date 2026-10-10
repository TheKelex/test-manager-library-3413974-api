<?php

namespace App\Application\DTO;

final readonly class OverdueLoanItem
{
    public function __construct(
        public string $loanId,
        public string $itemId,
        public string $bookTitle,
        public string $memberName,
        public \DateTimeImmutable $dueAt,
        public int $daysLate,
    ) {}
}
