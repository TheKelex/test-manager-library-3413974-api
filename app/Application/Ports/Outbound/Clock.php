<?php

namespace App\Application\Ports\Outbound;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
