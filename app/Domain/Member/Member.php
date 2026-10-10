<?php

namespace App\Domain\Member;

use App\Domain\Shared\RuleViolation;

final readonly class Member
{
    public function __construct(
        public string $id,
        public string $fullName,
        public string $documentNumber,
    ) {
        if (trim($fullName) === '' || trim($documentNumber) === '') {
            throw new RuleViolation('VALIDATION_ERROR', 'El nombre y el documento del socio son obligatorios.');
        }
    }
}
