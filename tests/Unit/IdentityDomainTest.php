<?php

namespace Tests\Unit;

use App\Domain\Member\Member;
use App\Domain\Shared\RuleViolation;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

class IdentityDomainTest extends TestCase
{
    public function test_user_username_is_trimmed_and_lowercased_and_roles_are_restricted(): void
    {
        $user = new User('user-1', ' Ana ', 'hash', User::ROLE_LIBRARIAN);

        self::assertSame('ana', $user->username);
        self::assertSame(User::ROLE_LIBRARIAN, $user->role);
        self::assertSame('hash', $user->passwordHash);

        $this->expectException(RuleViolation::class);
        new User('user-2', 'owner', 'hash', 'visitor');
    }

    public function test_member_requires_name_and_document(): void
    {
        self::assertSame('Ana Pérez', (new Member('member-1', 'Ana Pérez', 'DOC-1'))->fullName);

        $this->expectException(RuleViolation::class);
        new Member('member-2', '  ', 'DOC-2');
    }
}
