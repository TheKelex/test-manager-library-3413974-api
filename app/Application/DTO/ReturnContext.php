<?php

namespace App\Application\DTO;

use App\Domain\Book\Book;
use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;

final readonly class ReturnContext
{
    public function __construct(
        public Loan $loan,
        public LoanItem $item,
        public Book $book,
    ) {}
}
