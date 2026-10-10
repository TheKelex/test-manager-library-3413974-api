<?php

namespace App\Adapters\Outbound\Runtime;

use App\Application\Ports\Outbound\Clock;

final readonly class FixedClock implements Clock
{
    public function __construct(private \DateTimeImmutable $instant) {}

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
