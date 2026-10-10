<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Outbound\PasswordHasher;
use RuntimeException;

final class Argon2idPasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
