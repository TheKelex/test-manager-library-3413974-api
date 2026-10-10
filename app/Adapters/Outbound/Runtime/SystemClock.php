<?php

namespace App\Adapters\Outbound\Runtime;

use App\Application\Ports\Outbound\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
