<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\TokenIssuer;

final class FakeTokenIssuer implements TokenIssuer
{
    public function issue(string $userId, string $role): string
    {
        return 'fake-token:'.$userId.':'.$role;
    }
}
