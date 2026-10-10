<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\IdGenerator;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User\User;

final readonly class CreateLibrarian
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private IdGenerator $ids,
    ) {}

    public function execute(string $username, string $password, string $requestedRole): User
    {
        if ($requestedRole !== User::ROLE_LIBRARIAN) {
            throw new UseCaseError('VALIDATION_ERROR', 'Solo se permite crear usuarios con rol bibliotecario.');
        }

        $normalized = mb_strtolower(trim($username));
        if ($this->users->findByUsername($normalized) !== null) {
            throw new UseCaseError('USERNAME_TAKEN', 'El nombre de usuario ya está registrado.');
        }

        $user = new User(
            $this->ids->newId(),
            $normalized,
            $this->passwords->hash($password),
            User::ROLE_LIBRARIAN,
        );
        $this->users->saveUser($user);

        return $user;
    }
}
