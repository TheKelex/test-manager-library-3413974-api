<?php

namespace App\Domain\User;

use App\Domain\Shared\RuleViolation;

final readonly class User
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_LIBRARIAN = 'librarian';

    public string $username;

    public function __construct(
        public string $id,
        string $username,
        public string $passwordHash,
        public string $role,
    ) {
        $normalized = mb_strtolower(trim($username));
        if ($normalized === '' || $passwordHash === '') {
            throw new RuleViolation('VALIDATION_ERROR', 'El usuario y su hash de contraseña son obligatorios.');
        }
        if (! in_array($role, [self::ROLE_ADMIN, self::ROLE_LIBRARIAN], true)) {
            throw new RuleViolation('VALIDATION_ERROR', 'El rol de usuario no es válido.');
        }

        $this->username = $normalized;
    }
}
