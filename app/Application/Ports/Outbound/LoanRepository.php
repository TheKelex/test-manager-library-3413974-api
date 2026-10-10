<?php

namespace App\Application\Ports\Outbound;

use App\Application\DTO\LoanSearch;
use App\Application\DTO\MemberLoanSummary;
use App\Application\DTO\OverdueCandidate;
use App\Application\DTO\PageResult;
use App\Application\DTO\ReturnContext;
use App\Domain\Book\Book;
use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;

interface LoanRepository
{
    public function memberLoanSummary(string $memberId, \DateTimeImmutable $now): MemberLoanSummary;

    /** @param list<Book> $books Persist the loan, items and updated books in one transaction. */
    public function saveLendingAtomically(Loan $loan, array $books): void;

    public function findReturnContext(string $loanId, string $itemId): ?ReturnContext;

    public function findLoanById(string $loanId): ?Loan;

    public function searchLoans(LoanSearch $criteria, \DateTimeImmutable $now): PageResult;

    public function returnItemAtomically(Loan $loan, LoanItem $item, Book $book): void;

    /** @return list<OverdueCandidate> */
    public function findOverdueItems(\DateTimeImmutable $now): array;
}
