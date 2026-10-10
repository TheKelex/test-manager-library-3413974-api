<?php

namespace App\Domain\Loan;

use App\Domain\Book\Book;
use App\Domain\Shared\RuleViolation;

final readonly class Loan
{
    /**
     * @param  list<LoanItem>  $items
     */
    private function __construct(
        public string $id,
        public string $memberId,
        public string $loanedByUserId,
        public \DateTimeImmutable $loanedAt,
        public array $items,
    ) {
        if (count($items) > 3) {
            throw new RuleViolation('MEMBER_LIMIT_EXCEEDED', 'Un préstamo no puede contener más de tres libros.');
        }
    }

    public static function start(
        string $id,
        string $memberId,
        string $loanedByUserId,
        \DateTimeImmutable $loanedAt,
    ): self {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, []);
    }

    /**
     * @param  list<LoanItem>  $items
     */
    public static function reconstitute(
        string $id,
        string $memberId,
        string $loanedByUserId,
        \DateTimeImmutable $loanedAt,
        array $items,
    ): self {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, array_values($items));
    }

    public function addBook(Book $book, string $itemId, string $categoryName): self
    {
        if (count($this->items) >= 3) {
            throw new RuleViolation('MEMBER_LIMIT_EXCEEDED', 'Un préstamo no puede contener más de tres libros.');
        }
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new RuleViolation('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir un libro en el mismo préstamo.');
            }
        }

        $dueAt = $this->loanedAt->modify('+14 days');
        $item = new LoanItem(
            $itemId,
            $this->id,
            $book->id,
            $book->title,
            $book->isbn->value,
            $categoryName,
            $dueAt,
        );

        return new self($this->id, $this->memberId, $this->loanedByUserId, $this->loanedAt, [...$this->items, $item]);
    }

    public function returnItem(string $itemId, \DateTimeImmutable $at): self
    {
        $found = false;
        $items = [];
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                $items[] = $item->returnAt($at);
                $found = true;
            } else {
                $items[] = $item;
            }
        }
        if (! $found) {
            throw new RuleViolation('NOT_FOUND', 'La línea de préstamo no existe.');
        }

        return new self($this->id, $this->memberId, $this->loanedByUserId, $this->loanedAt, $items);
    }

    public function statusAt(\DateTimeImmutable $now): string
    {
        if ($this->items !== [] && count(array_filter(
            $this->items,
            static fn (LoanItem $item): bool => $item->returnedAt !== null,
        )) === count($this->items)) {
            return 'closed';
        }
        foreach ($this->items as $item) {
            if ($item->returnedAt === null && $item->dueAt < $now) {
                return 'overdue';
            }
        }

        return 'open';
    }

    public function activeItemsCount(): int
    {
        return count(array_filter(
            $this->items,
            static fn (LoanItem $item): bool => $item->returnedAt === null,
        ));
    }
}
