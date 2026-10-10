<?php

namespace App\Application\Ports\Outbound;

interface IdGenerator
{
    public function newId(): string;
}
