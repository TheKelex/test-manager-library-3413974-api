<?php

namespace App\Application\Ports\Outbound;

use App\Domain\Member\Member;

interface MemberRepository
{
    public function findById(string $id): ?Member;
}
