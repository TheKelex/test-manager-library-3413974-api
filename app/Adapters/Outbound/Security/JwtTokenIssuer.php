<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Outbound\TokenIssuer;
use Firebase\JWT\JWT;
use RuntimeException;

final readonly class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(
        private string $secret,
        private int $lifetime = 28800,
    ) {
        if (strlen($secret) < 32 || $lifetime !== 28800) {
            throw new RuntimeException('JWT configuration is invalid.');
        }
    }

    public function issue(string $userId, string $role): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $this->lifetime,
        ], $this->secret, 'HS256');
    }
}
