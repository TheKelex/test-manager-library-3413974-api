<?php

namespace App\Application\Ports\Outbound;

interface TokenIssuer
{
    public function issue(string $userId, string $role): string;
}
