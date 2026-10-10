<?php

namespace App\Adapters\Outbound\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

final readonly class JwtTokenVerifier
{
    public function __construct(private string $secret) {}

    /** @return array{sub: string, role: string} */
    public function verify(string $token): array
    {
        $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
        if (! isset($claims->sub, $claims->role)
            || ! is_string($claims->sub)
            || ! in_array($claims->role, ['admin', 'librarian'], true)) {
            throw new UnexpectedValueException('Invalid token claims.');
        }

        return ['sub' => $claims->sub, 'role' => $claims->role];
    }
}
