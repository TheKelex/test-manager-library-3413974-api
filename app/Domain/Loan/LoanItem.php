<?php

namespace App\Domain\Loan;

use App\Domain\Shared\RuleViolation;

final readonly class LoanItem
{
    public function __construct(
        public string $id,
        public string $loanId,
        public string $bookId,
        public string $bookTitle,
        public string $isbn,
        public string $categoryName,
        public \DateTimeImmutable $dueAt,
        public ?\DateTimeImmutable $returnedAt = null,
    ) {}

    public function returnAt(\DateTimeImmutable $at): self
    {
        if ($this->returnedAt !== null) {
            throw new RuleViolation('ITEM_ALREADY_RETURNED', 'La línea de préstamo ya fue devuelta.');
        }

        return new self(
            $this->id,
            $this->loanId,
            $this->bookId,
            $this->bookTitle,
            $this->isbn,
            $this->categoryName,
            $this->dueAt,
            $at,
        );
    }
}
