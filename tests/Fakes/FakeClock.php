<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\Clock;

final class FakeClock implements Clock
{
    public function __construct(public \DateTimeImmutable $instant) {}

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
