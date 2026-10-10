<?php

namespace Tests\Unit;

use App\Application\DTO\MostBorrowedRow;
use App\Application\UseCases\GetMostBorrowedReport;
use App\Domain\Shared\DateRange;
use App\Domain\Shared\RuleViolation;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeMostBorrowedReportReader;

class MostBorrowedReportTest extends TestCase
{
    public function test_report_orders_count_then_title_and_keeps_frozen_values(): void
    {
        $reader = new FakeMostBorrowedReportReader([
            new MostBorrowedRow('b', 'Beta frozen', 'Novela', 2),
            new MostBorrowedRow('c', 'Alpha frozen', 'Historia', 2),
            new MostBorrowedRow('a', 'Current title', 'Ciencia', 1),
        ]);
        $range = new DateRange(
            new \DateTimeImmutable('2026-10-01T00:00:00Z'),
            new \DateTimeImmutable('2026-10-31T00:00:00Z'),
        );

        $rows = (new GetMostBorrowedReport($reader))->execute($range);

        self::assertSame(['c', 'b', 'a'], array_map(static fn (MostBorrowedRow $row): string => $row->bookId, $rows));
        self::assertSame('Alpha frozen', $rows[0]->bookTitle);
        self::assertSame('Historia', $rows[0]->categoryName);
    }

    public function test_inverted_report_range_is_rejected(): void
    {
        $this->expectException(RuleViolation::class);
        $this->expectExceptionMessage('La fecha final no puede ser anterior a la inicial.');

        new DateRange(
            new \DateTimeImmutable('2026-11-01T00:00:00Z'),
            new \DateTimeImmutable('2026-10-01T00:00:00Z'),
        );
    }
}
