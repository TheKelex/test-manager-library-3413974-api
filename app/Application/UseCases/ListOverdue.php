<?php

namespace App\Application\UseCases;

use App\Application\DTO\OverdueLoanItem;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;

final readonly class ListOverdue
{
    public function __construct(
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    /** @return list<OverdueLoanItem> */
    public function execute(): array
    {
        $now = $this->clock->now();
        $utc = new \DateTimeZone('UTC');
        $items = [];

        foreach ($this->loans->findOverdueItems($now) as $candidate) {
            if ($candidate->dueAt >= $now) {
                continue;
            }
            $dueDate = $candidate->dueAt->setTimezone($utc)->setTime(0, 0);
            $today = $now->setTimezone($utc)->setTime(0, 0);
            $daysLate = (int) $dueDate->diff($today)->format('%a');
            $items[] = new OverdueLoanItem(
                $candidate->loanId,
                $candidate->itemId,
                $candidate->bookTitle,
                $candidate->memberName,
                $candidate->dueAt,
                $daysLate,
            );
        }

        usort($items, static fn (OverdueLoanItem $left, OverdueLoanItem $right): int => $right->daysLate <=> $left->daysLate
            ?: $left->dueAt <=> $right->dueAt
        );

        return $items;
    }
}
