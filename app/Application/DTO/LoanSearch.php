<?php

namespace App\Application\DTO;

use App\Domain\Shared\RuleViolation;

final readonly class LoanSearch
{
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $status = null,
        public ?string $memberId = null,
        public int $page = 1,
        public int $size = 20,
    ) {
        if ($page < 1 || $size < 1 || $size > 50) {
            throw new RuleViolation('VALIDATION_ERROR', 'La página debe ser positiva y el tamaño máximo es 50.');
        }
        if ($from !== null && $to !== null && $to < $from) {
            throw new RuleViolation('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.');
        }
        if ($status !== null && ! in_array($status, ['open', 'overdue', 'closed'], true)) {
            throw new RuleViolation('VALIDATION_ERROR', 'El estado del préstamo no es válido.');
        }
    }
}
