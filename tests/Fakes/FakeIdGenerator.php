<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\IdGenerator;

final class FakeIdGenerator implements IdGenerator
{
    private int $sequence = 1;

    public function newId(): string
    {
        $id = sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d', $this->sequence++);

        return $id;
    }
}
