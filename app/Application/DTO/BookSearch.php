<?php

namespace App\Application\DTO;

use App\Domain\Shared\RuleViolation;

final readonly class BookSearch
{
    public function __construct(
        public ?string $query = null,
        public ?string $categoryId = null,
        public int $page = 1,
        public int $size = 20,
    ) {
        if ($page < 1 || $size < 1 || $size > 50) {
            throw new RuleViolation('VALIDATION_ERROR', 'La página debe ser positiva y el tamaño máximo es 50.');
        }
    }
}
