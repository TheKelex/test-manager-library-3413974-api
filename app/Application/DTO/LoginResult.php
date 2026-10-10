<?php

namespace App\Application\DTO;

final readonly class LoginResult
{
    public function __construct(
        public string $accessToken,
        public string $role,
        public int $expiresIn = 28800,
    ) {}
}
