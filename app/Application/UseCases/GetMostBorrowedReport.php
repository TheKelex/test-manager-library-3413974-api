<?php

namespace App\Application\UseCases;

use App\Application\DTO\MostBorrowedRow;
use App\Application\Ports\Outbound\MostBorrowedReportReader;
use App\Domain\Shared\DateRange;

final readonly class GetMostBorrowedReport
{
    public function __construct(private MostBorrowedReportReader $reports) {}

    /** @return list<MostBorrowedRow> */
    public function execute(DateRange $range): array
    {
        $rows = $this->reports->mostBorrowed($range);
        usort($rows, static fn (MostBorrowedRow $left, MostBorrowedRow $right): int => $right->timesBorrowed <=> $left->timesBorrowed
            ?: $left->bookTitle <=> $right->bookTitle
            ?: $left->bookId <=> $right->bookId
        );

        return $rows;
    }
}
