<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan\Loan;

final readonly class ReturnItem
{
    public function __construct(
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    public function execute(string $loanId, string $itemId): Loan
    {
        $context = $this->loans->findReturnContext($loanId, $itemId)
            ?? throw new UseCaseError('NOT_FOUND', 'El préstamo o la línea no existe.');
        $updatedLoan = $context->loan->returnItem($itemId, $this->clock->now());
        $returnedItem = null;
        foreach ($updatedLoan->items as $item) {
            if ($item->id === $itemId) {
                $returnedItem = $item;
                break;
            }
        }
        if ($returnedItem === null) {
            throw new UseCaseError('NOT_FOUND', 'La línea de préstamo no existe.');
        }

        $updatedBook = $context->book->restock();
        $this->loans->returnItemAtomically($updatedLoan, $returnedItem, $updatedBook);

        return $updatedLoan;
    }
}
