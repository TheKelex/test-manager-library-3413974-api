<?php

namespace App\Application\DTO;

final readonly class MemberLoanSummary
{
    public function __construct(
        public int $activeItems,
        public bool $hasOverdue,
    ) {}
}
