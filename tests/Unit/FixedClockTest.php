<?php

namespace Tests\Unit;

use App\Adapters\Outbound\Runtime\FixedClock;
use PHPUnit\Framework\TestCase;

class FixedClockTest extends TestCase
{
    public function test_fixed_clock_returns_the_configured_instant_in_utc(): void
    {
        $clock = new FixedClock(new \DateTimeImmutable('2020-01-01T12:00:00-05:00'));

        self::assertSame(
            '2020-01-01T17:00:00+00:00',
            $clock->now()->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
        );
    }
}
