<?php

namespace App\Application\Ports\Outbound;

use App\Application\DTO\MostBorrowedRow;
use App\Domain\Shared\DateRange;

interface MostBorrowedReportReader
{
    /** @return list<MostBorrowedRow> */
    public function mostBorrowed(DateRange $range): array;
}
