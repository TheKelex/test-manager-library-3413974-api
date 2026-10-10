<?php

namespace Tests\Unit;

use App\Domain\Book\Book;
use App\Domain\Shared\Isbn;
use App\Domain\Shared\RuleViolation;
use PHPUnit\Framework\TestCase;

class BookDomainTest extends TestCase
{
    public function test_new_book_starts_with_all_copies_available(): void
    {
        $book = $this->book(total: 4);

        self::assertSame(4, $book->availableCopies);
        self::assertNull($book->coverKey);
        self::assertNull($book->deletedAt);
    }

    public function test_withdraw_and_restock_preserve_available_range(): void
    {
        $book = $this->book(total: 1)->withdraw();

        self::assertSame(0, $book->availableCopies);
        self::assertSame(1, $book->restock()->availableCopies);

        $this->assertRule('BOOK_NOT_AVAILABLE', fn () => $book->withdraw());
        $this->assertRule('VALIDATION_ERROR', fn () => $this->book(total: 1)->restock());
    }

    public function test_changing_total_preserves_number_of_borrowed_copies(): void
    {
        $book = $this->book(total: 4)->withdraw()->withdraw();

        $updated = $book->changeDetails(' New title ', ' New author ', 'category-2', 'Ciencia', 5);
        self::assertSame('New title', $updated->title);
        self::assertSame(5, $updated->totalCopies);
        self::assertSame(3, $updated->availableCopies);
        self::assertSame('Ciencia', $updated->categoryName);

        $reduced = $updated->changeDetails('New title', 'New author', 'category-2', 'Ciencia', 2);
        self::assertSame(0, $reduced->availableCopies);
    }

    public function test_total_cannot_be_reduced_below_copies_on_loan(): void
    {
        $book = $this->book(total: 3)->withdraw()->withdraw();

        $this->assertRule(
            'VALIDATION_ERROR',
            fn () => $book->changeDetails('Title', 'Author', 'category-1', 'Novela', 1),
        );
    }

    public function test_logical_withdrawal_is_rejected_with_open_loans(): void
    {
        $book = $this->book();
        $at = new \DateTimeImmutable('2026-10-10T12:00:00Z');

        $this->assertRule('BOOK_HAS_OPEN_LOANS', fn () => $book->withdrawFromCatalog($at, true));
        self::assertSame($at, $book->withdrawFromCatalog($at, false)->deletedAt);
    }

    private function book(int $total = 3): Book
    {
        return Book::create(
            'book-1',
            ' Title ',
            ' Author ',
            Isbn::from('9780307474728'),
            'category-1',
            'Novela',
            $total,
        );
    }

    private function assertRule(string $code, callable $action): void
    {
        try {
            $action();
            self::fail('Expected domain rule violation was not thrown.');
        } catch (RuleViolation $error) {
            self::assertSame($code, $error->errorCode);
        }
    }
}
