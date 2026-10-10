<?php

namespace Tests\Feature;

use App\Adapters\Outbound\Runtime\FixedClock;
use App\Application\Ports\Outbound\Clock;
use Tests\TestCase;

class DemoClockCompositionTest extends TestCase
{
    public function test_demo_clock_is_used_only_when_configured(): void
    {
        config(['library.demo_now' => '2020-01-01T12:00:00Z']);

        $clock = $this->app->make(Clock::class);

        self::assertInstanceOf(FixedClock::class, $clock);
        self::assertSame('2020-01-01T12:00:00+00:00', $clock->now()->format(DATE_ATOM));
    }
}
