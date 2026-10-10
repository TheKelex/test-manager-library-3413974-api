<?php

namespace App\Domain\Book;

use App\Domain\Shared\Isbn;
use App\Domain\Shared\RuleViolation;

final readonly class Book
{
    private function __construct(
        public string $id,
        public string $title,
        public string $author,
        public Isbn $isbn,
        public string $categoryId,
        public string $categoryName,
        public int $totalCopies,
        public int $availableCopies,
        public ?string $coverKey,
        public ?\DateTimeImmutable $deletedAt,
    ) {
        if ($title === '' || $author === '') {
            throw new RuleViolation('VALIDATION_ERROR', 'El título y el autor son obligatorios.');
        }

        if ($totalCopies < 1 || $availableCopies < 0 || $availableCopies > $totalCopies) {
            throw new RuleViolation('VALIDATION_ERROR', 'La disponibilidad debe estar entre cero y el total de ejemplares.');
        }

        if ($coverKey === '') {
            throw new RuleViolation('VALIDATION_ERROR', 'La clave de portada debe ser nula o no vacía.');
        }
    }

    public static function create(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        string $categoryName,
        int $totalCopies,
    ): self {
        return new self(
            $id,
            trim($title),
            trim($author),
            $isbn,
            $categoryId,
            $categoryName,
            $totalCopies,
            $totalCopies,
            null,
            null,
        );
    }

    public function withdraw(): self
    {
        if ($this->deletedAt !== null || $this->availableCopies === 0) {
            throw new RuleViolation('BOOK_NOT_AVAILABLE', 'No quedan ejemplares disponibles de este libro.');
        }

        return $this->withAvailability($this->availableCopies - 1);
    }

    public function restock(): self
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new RuleViolation('VALIDATION_ERROR', 'La disponibilidad no puede superar el total de ejemplares.');
        }

        return $this->withAvailability($this->availableCopies + 1);
    }

    public function changeDetails(
        string $title,
        string $author,
        string $categoryId,
        string $categoryName,
        int $totalCopies,
    ): self {
        if ($this->deletedAt !== null) {
            throw new RuleViolation('NOT_FOUND', 'El libro no existe o está dado de baja.');
        }
        if ($totalCopies < 1) {
            throw new RuleViolation('VALIDATION_ERROR', 'El total de ejemplares debe ser mayor que cero.');
        }

        $available = $this->availableCopies + ($totalCopies - $this->totalCopies);
        if ($available < 0) {
            throw new RuleViolation('VALIDATION_ERROR', 'No se puede reducir el total por debajo de los ejemplares prestados.');
        }

        return new self(
            $this->id,
            trim($title),
            trim($author),
            $this->isbn,
            $categoryId,
            $categoryName,
            $totalCopies,
            $available,
            $this->coverKey,
            $this->deletedAt,
        );
    }

    public function withCover(?string $coverKey): self
    {
        if ($this->deletedAt !== null) {
            throw new RuleViolation('NOT_FOUND', 'El libro no existe o está dado de baja.');
        }
        if ($coverKey === '') {
            throw new RuleViolation('VALIDATION_ERROR', 'La clave de portada debe ser nula o no vacía.');
        }

        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->categoryName,
            $this->totalCopies,
            $this->availableCopies,
            $coverKey,
            $this->deletedAt,
        );
    }

    public function withdrawFromCatalog(\DateTimeImmutable $at, bool $hasOpenLoans): self
    {
        if ($this->deletedAt !== null) {
            throw new RuleViolation('NOT_FOUND', 'El libro no existe o está dado de baja.');
        }
        if ($hasOpenLoans) {
            throw new RuleViolation('BOOK_HAS_OPEN_LOANS', 'No se puede dar de baja un libro con préstamos abiertos.');
        }

        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->categoryName,
            $this->totalCopies,
            $this->availableCopies,
            $this->coverKey,
            $at,
        );
    }

    private function withAvailability(int $available): self
    {
        return new self(
            $this->id,
            $this->title,
            $this->author,
            $this->isbn,
            $this->categoryId,
            $this->categoryName,
            $this->totalCopies,
            $available,
            $this->coverKey,
            $this->deletedAt,
        );
    }
}
