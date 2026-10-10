<?php

namespace App\Domain\Shared;

final readonly class Quantity
{
    private function __construct(public int $value) {}

    public static function positive(int $value): self
    {
        if ($value < 1) {
            throw new RuleViolation('VALIDATION_ERROR', 'La cantidad debe ser mayor que cero.');
        }

        return new self($value);
    }
}
