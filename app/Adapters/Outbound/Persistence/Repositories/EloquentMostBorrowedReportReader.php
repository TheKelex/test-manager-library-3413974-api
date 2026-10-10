<?php

namespace App\Adapters\Outbound\Persistence\Repositories;

use App\Application\DTO\MostBorrowedRow;
use App\Application\Ports\Outbound\MostBorrowedReportReader;
use App\Domain\Shared\DateRange;
use Illuminate\Support\Facades\DB;

final class EloquentMostBorrowedReportReader implements MostBorrowedReportReader
{
    public function mostBorrowed(DateRange $range): array
    {
        $utc = new \DateTimeZone('UTC');
        $from = $range->from->setTimezone($utc)->setTime(0, 0);
        $until = $range->to->setTimezone($utc)->setTime(0, 0)->modify('+1 day');

        return DB::table('library.loan as l')
            ->join('library.loan_item as li', 'li.loan_id', '=', 'l.id')
            ->where('l.loaned_at', '>=', $from)
            ->where('l.loaned_at', '<', $until)
            ->groupBy('li.book_id', 'li.book_title', 'li.category_name')
            ->selectRaw('li.book_id, li.book_title, li.category_name, COUNT(*) AS times_borrowed')
            ->orderByDesc('times_borrowed')
            ->orderBy('li.book_title')
            ->get()
            ->map(static fn ($row): MostBorrowedRow => new MostBorrowedRow(
                (string) $row->book_id,
                (string) $row->book_title,
                (string) $row->category_name,
                (int) $row->times_borrowed,
            ))
            ->all();
    }
}
