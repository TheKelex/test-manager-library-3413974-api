<?php

namespace Tests\Unit;

use App\Domain\Book\Book;
use App\Domain\Loan\Loan;
use App\Domain\Shared\Isbn;
use App\Domain\Shared\RuleViolation;
use PHPUnit\Framework\TestCase;

class LoanDomainTest extends TestCase
{
    private \DateTimeImmutable $loanedAt;

    protected function setUp(): void
    {
        $this->loanedAt = new \DateTimeImmutable('2026-10-10T12:00:00Z');
    }

    public function test_add_book_freezes_catalog_values_and_sets_due_date_to_fourteen_days(): void
    {
        $loan = $this->loan()->addBook($this->book(), 'item-1', 'Novela');
        $item = $loan->items[0];

        self::assertSame('Cien años de soledad', $item->bookTitle);
        self::assertSame('9780307474728', $item->isbn);
        self::assertSame('Novela', $item->categoryName);
        self::assertEquals($this->loanedAt->modify('+14 days'), $item->dueAt);
        self::assertSame('open', $loan->statusAt($this->loanedAt));
    }

    public function test_loan_status_changes_from_open_to_overdue_to_closed(): void
    {
        $loan = $this->loan()->addBook($this->book(), 'item-1', 'Novela');

        self::assertSame('open', $loan->statusAt($this->loanedAt->modify('+14 days')));
        self::assertSame('overdue', $loan->statusAt($this->loanedAt->modify('+14 days +1 second')));

        $returned = $loan->returnItem('item-1', $this->loanedAt->modify('+15 days'));
        self::assertSame('closed', $returned->statusAt($this->loanedAt->modify('+16 days')));
        self::assertSame(0, $returned->activeItemsCount());
    }

    public function test_loan_rejects_duplicate_books_and_more_than_three_items(): void
    {
        $loan = $this->loan()->addBook($this->book(), 'item-1', 'Novela');
        $this->assertRule('DUPLICATE_BOOK_IN_LOAN', fn () => $loan->addBook($this->book(), 'item-2', 'Novela'));

        $loan = $loan
            ->addBook($this->book('book-2', '9780307474729'), 'item-2', 'Novela')
            ->addBook($this->book('book-3', '9780307474730'), 'item-3', 'Novela');
        $this->assertRule('MEMBER_LIMIT_EXCEEDED', fn () => $loan->addBook(
            $this->book('book-4', '9780307474731'),
            'item-4',
            'Novela',
        ));
    }

    public function test_item_cannot_be_returned_twice(): void
    {
        $loan = $this->loan()->addBook($this->book(), 'item-1', 'Novela');
        $returned = $loan->returnItem('item-1', $this->loanedAt->modify('+2 days'));

        $this->assertRule(
            'ITEM_ALREADY_RETURNED',
            fn () => $returned->returnItem('item-1', $this->loanedAt->modify('+3 days')),
        );
    }

    private function loan(): Loan
    {
        return Loan::start('loan-1', 'member-1', 'user-1', $this->loanedAt);
    }

    private function book(string $id = 'book-1', string $isbn = '9780307474728'): Book
    {
        return Book::create($id, 'Cien años de soledad', 'Gabriel García Márquez', Isbn::from($isbn), 'category-1', 'Novela', 2);
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
