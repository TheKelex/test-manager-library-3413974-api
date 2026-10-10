<?php

namespace App\Application\UseCases;

use App\Application\Ports\Outbound\IdGenerator;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User\User;
use RuntimeException;

final readonly class EnsureInitialAdmin
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private IdGenerator $ids,
    ) {}

    public function execute(string $username, string $password): User
    {
        $username = mb_strtolower(trim($username));
        if ($username === '' || $password === '') {
            throw new RuntimeException('ADMIN_USERNAME and ADMIN_PASSWORD must be configured.');
        }
        $existing = $this->users->findByUsername($username);
        if ($existing !== null) {
            if ($existing->role !== User::ROLE_ADMIN) {
                throw new RuntimeException('Configured initial administrator username belongs to another role.');
            }

            return $existing;
        }

        $admin = new User($this->ids->newId(), $username, $this->passwords->hash($password), User::ROLE_ADMIN);
        $this->users->saveUser($admin);

        return $admin;
    }
}
