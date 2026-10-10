<?php

namespace App\Adapters\Outbound\Persistence\Repositories;

use App\Adapters\Outbound\Persistence\Eloquent\Models\UserRecord;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User\User;

final class EloquentUserRepository implements UserRepository
{
    public function findByUsername(string $username): ?User
    {
        $row = UserRecord::query()->where('username', mb_strtolower(trim($username)))->first();

        return $row === null
            ? null
            : new User((string) $row->id, (string) $row->username, (string) $row->password_hash, (string) $row->role);
    }

    public function saveUser(User $user): void
    {
        $record = UserRecord::query()->firstOrNew(['id' => $user->id]);
        $record->fill([
            'username' => $user->username,
            'password_hash' => $user->passwordHash,
            'role' => $user->role,
        ])->save();
    }
}
