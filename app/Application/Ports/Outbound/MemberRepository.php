<?php

namespace App\Application\Ports\Outbound;

use App\Application\DTO\PageResult;
use App\Domain\Member\Member;

interface MemberRepository
{
    public function findById(string $id): ?Member;

    public function findByDocumentNumber(string $documentNumber): ?Member;

    public function saveMember(Member $member): void;

    public function searchMembers(?string $query, int $page, int $size): PageResult;

    public function activeLoanCount(string $memberId): int;
}
