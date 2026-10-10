<?php

namespace App\Domain\Shared;

final readonly class Isbn
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        if (! preg_match('/^\d{13}$/', $value)) {
            throw new RuleViolation('VALIDATION_ERROR', 'El ISBN debe contener exactamente 13 dígitos.');
        }

        return new self($value);
    }
}
