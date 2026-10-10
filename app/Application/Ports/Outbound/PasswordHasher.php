<?php

namespace App\Application\Ports\Outbound;

interface PasswordHasher
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;
}
