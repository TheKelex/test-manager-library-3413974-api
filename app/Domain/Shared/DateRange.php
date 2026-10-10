<?php

namespace App\Domain\Shared;

final readonly class DateRange
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
        if ($to < $from) {
            throw new RuleViolation('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.');
        }
    }
}
