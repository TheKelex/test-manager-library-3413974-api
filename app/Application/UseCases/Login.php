<?php

namespace App\Application\UseCases;

use App\Application\DTO\LoginResult;
use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;

final readonly class Login
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenIssuer $tokens,
    ) {}

    public function execute(string $username, string $password): LoginResult
    {
        $user = $this->users->findByUsername(mb_strtolower(trim($username)));
        if ($user === null || ! $this->passwords->verify($password, $user->passwordHash)) {
            throw new UseCaseError('INVALID_CREDENTIALS', 'El usuario o la contraseña no son válidos.');
        }

        return new LoginResult($this->tokens->issue($user->id, $user->role), $user->role);
    }
}
