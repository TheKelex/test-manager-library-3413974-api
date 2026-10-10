<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return 'fake-hash:'.$password;
    }

    public function verify(string $password, string $hash): bool
    {
        return hash_equals($hash, $this->hash($password));
    }
}
