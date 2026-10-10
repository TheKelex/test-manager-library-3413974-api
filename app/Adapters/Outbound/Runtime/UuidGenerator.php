<?php

namespace App\Adapters\Outbound\Runtime;

use App\Application\Ports\Outbound\IdGenerator;
use Illuminate\Support\Str;

final class UuidGenerator implements IdGenerator
{
    public function newId(): string
    {
        return (string) Str::uuid();
    }
}
