<?php

namespace Tests\Unit;

use App\Domain\Shared\DateRange;
use App\Domain\Shared\Isbn;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\RuleViolation;
use PHPUnit\Framework\TestCase;

class DomainValuesTest extends TestCase
{
    public function test_isbn_accepts_exactly_thirteen_digits(): void
    {
        self::assertSame('9780307474728', Isbn::from('9780307474728')->value);
    }

    public function test_isbn_rejects_non_thirteen_digit_values(): void
    {
        foreach (['978030747472', '97803074747289', '97803074747A8', ' 9780307474728'] as $isbn) {
            try {
                Isbn::from($isbn);
                self::fail('Invalid ISBN was accepted.');
            } catch (RuleViolation $error) {
                self::assertSame('VALIDATION_ERROR', $error->errorCode);
            }
        }
    }

    public function test_quantity_must_be_positive(): void
    {
        self::assertSame(1, Quantity::positive(1)->value);

        $this->expectException(RuleViolation::class);
        Quantity::positive(0);
    }

    public function test_date_range_allows_equal_dates_and_rejects_inverted_range(): void
    {
        $date = new \DateTimeImmutable('2026-10-10T00:00:00Z');
        self::assertSame($date, (new DateRange($date, $date))->from);

        try {
            new DateRange($date->modify('+1 day'), $date);
            self::fail('An inverted date range was accepted.');
        } catch (RuleViolation $error) {
            self::assertSame('INVALID_DATE_RANGE', $error->errorCode);
        }
    }
}
