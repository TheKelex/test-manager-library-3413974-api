<?php

namespace App\Application\Ports\Outbound;

use App\Domain\User\User;

interface UserRepository
{
    public function findByUsername(string $username): ?User;

    public function saveUser(User $user): void;
}
