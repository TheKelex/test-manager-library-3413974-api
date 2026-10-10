<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan\Loan;

final readonly class GetLoan
{
    public function __construct(private LoanRepository $loans) {}

    public function execute(string $loanId): Loan
    {
        return $this->loans->findLoanById($loanId)
            ?? throw new UseCaseError('NOT_FOUND', 'El préstamo no existe.');
    }
}
