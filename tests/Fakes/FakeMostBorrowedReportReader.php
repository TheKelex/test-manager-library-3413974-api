<?php

namespace Tests\Fakes;

use App\Application\DTO\MostBorrowedRow;
use App\Application\Ports\Outbound\MostBorrowedReportReader;
use App\Domain\Shared\DateRange;

final class FakeMostBorrowedReportReader implements MostBorrowedReportReader
{
    /** @param list<MostBorrowedRow> $rows */
    public function __construct(private array $rows = []) {}

    public function mostBorrowed(DateRange $range): array
    {
        return $this->rows;
    }
}
