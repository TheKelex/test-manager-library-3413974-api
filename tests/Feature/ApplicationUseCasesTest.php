<?php

namespace Tests\Feature;

use App\Application\DTO\BookSearch;
use App\Application\Errors\UseCaseError;
use App\Application\UseCases\CreateBook;
use App\Application\UseCases\CreateLibrarian;
use App\Application\UseCases\GetBook;
use App\Application\UseCases\LendBooks;
use App\Application\UseCases\ListOverdue;
use App\Application\UseCases\Login;
use App\Application\UseCases\RemoveCover;
use App\Application\UseCases\ReturnItem;
use App\Application\UseCases\SearchBooks;
use App\Application\UseCases\UpdateBook;
use App\Application\UseCases\UploadCover;
use App\Application\UseCases\WithdrawBook;
use App\Domain\Book\Book;
use App\Domain\Loan\Loan;
use App\Domain\Loan\LoanItem;
use App\Domain\Member\Member;
use App\Domain\Shared\Isbn;
use App\Domain\Shared\RuleViolation;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeClock;
use Tests\Fakes\FakeCoverStorage;
use Tests\Fakes\FakeIdGenerator;
use Tests\Fakes\FakeLibrary;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenIssuer;

class ApplicationUseCasesTest extends TestCase
{
    private FakeLibrary $library;

    private FakeClock $clock;

    private FakeIdGenerator $ids;

    private FakePasswordHasher $passwords;

    protected function setUp(): void
    {
        $this->library = new FakeLibrary;
        $this->clock = new FakeClock(new \DateTimeImmutable('2026-10-10T12:00:00Z'));
        $this->ids = new FakeIdGenerator;
        $this->passwords = new FakePasswordHasher;
        $this->library->members['member-1'] = new Member('member-1', 'Ana Pérez', 'DOC-1');
    }

    public function test_login_uses_same_error_for_unknown_user_and_wrong_password(): void
    {
        $login = new Login($this->library, $this->passwords, new FakeTokenIssuer);

        $this->assertUseCaseError('INVALID_CREDENTIALS', fn () => $login->execute('missing', 'wrong'));

        $this->library->users['user-1'] = new User('user-1', 'ana', $this->passwords->hash('secret'), User::ROLE_LIBRARIAN);
        $this->assertUseCaseError('INVALID_CREDENTIALS', fn () => $login->execute(' Ana ', 'wrong'));
    }

    public function test_login_returns_role_and_eight_hour_token(): void
    {
        $this->library->users['user-1'] = new User('user-1', 'ana', $this->passwords->hash('secret'), User::ROLE_LIBRARIAN);

        $result = (new Login($this->library, $this->passwords, new FakeTokenIssuer))->execute(' Ana ', 'secret');

        self::assertSame('fake-token:user-1:librarian', $result->accessToken);
        self::assertSame('librarian', $result->role);
        self::assertSame(28800, $result->expiresIn);
    }

    public function test_create_librarian_normalizes_username_hashes_password_and_rejects_admin_role(): void
    {
        $created = (new CreateLibrarian($this->library, $this->passwords, $this->ids))
            ->execute(' Ana ', 'secret', 'librarian');

        self::assertSame('ana', $created->username);
        self::assertSame('fake-hash:secret', $created->passwordHash);
        self::assertSame(['ana'], array_values(array_map(
            static fn (User $user): string => $user->username,
            $this->library->users,
        )));

        $this->assertUseCaseError(
            'VALIDATION_ERROR',
            fn () => (new CreateLibrarian($this->library, $this->passwords, $this->ids))
                ->execute('root', 'secret', 'admin'),
        );
        $this->assertUseCaseError(
            'USERNAME_TAKEN',
            fn () => (new CreateLibrarian($this->library, $this->passwords, $this->ids))
                ->execute('ANA', 'other', 'librarian'),
        );
    }

    public function test_create_book_checks_isbn_and_starts_with_total_copies_available(): void
    {
        $create = new CreateBook($this->library, $this->ids);
        $book = $create->execute('Title', 'Author', '9780307474728', 'category-1', 3);

        self::assertSame(3, $book->totalCopies);
        self::assertSame(3, $book->availableCopies);
        self::assertSame(1, $this->library->bookSaveCount);

        $this->assertUseCaseError(
            'ISBN_TAKEN',
            fn () => $create->execute('Other', 'Author', '9780307474728', 'category-1', 2),
        );
        $this->assertUseCaseError(
            'VALIDATION_ERROR',
            fn () => $create->execute('Other', 'Author', '9780307474729', 'unknown', 2),
        );
    }

    public function test_search_books_returns_paged_result_and_get_book_hides_missing_or_withdrawn_book(): void
    {
        $first = $this->book('book-1', '9780307474728', 'Alpha title', 'Author');
        $second = $this->book('book-2', '9780307474729', 'Beta title', 'Writer');
        $this->library->books[$first->id] = $first;
        $this->library->books[$second->id] = $second;

        $page = (new SearchBooks($this->library))->execute(new BookSearch(page: 1, size: 1));
        self::assertSame(2, $page->total);
        self::assertSame(1, $page->size);
        self::assertSame('Alpha title', $page->items[0]->title);
        self::assertSame($first, (new GetBook($this->library))->execute('book-1'));
        $this->assertUseCaseError('NOT_FOUND', fn () => (new GetBook($this->library))->execute('absent'));
    }

    public function test_update_book_adjusts_availability_and_withdrawal_is_logical(): void
    {
        $book = $this->book('book-1', '9780307474728')->withdraw();
        $this->library->books[$book->id] = $book;

        $updated = (new UpdateBook($this->library))->execute('book-1', 'Updated', 'Author', 'category-2', 4);
        self::assertSame(3, $updated->availableCopies);
        self::assertSame('Ciencia', $updated->categoryName);

        $withdrawn = (new WithdrawBook($this->library, $this->clock))->execute('book-1');
        self::assertSame($this->clock->instant, $withdrawn->deletedAt);
        self::assertNotNull($this->library->books['book-1']);
    }

    public function test_book_cannot_be_withdrawn_while_it_has_open_loans(): void
    {
        $book = $this->book('book-1', '9780307474728');
        $loan = Loan::start('loan-1', 'member-1', 'user-1', $this->clock->instant)
            ->addBook($book, 'item-1', 'Novela');
        $this->library->books[$book->id] = $book;
        $this->library->loans[$loan->id] = $loan;

        $this->assertUseCaseError('BOOK_HAS_OPEN_LOANS', fn () => (new WithdrawBook($this->library, $this->clock))->execute('book-1'));
        self::assertSame(0, $this->library->bookSaveCount);
    }

    public function test_upload_cover_validates_real_image_type_and_two_megabyte_limit(): void
    {
        $book = $this->book('book-1', '9780307474728');
        $this->library->books[$book->id] = $book;
        $covers = new FakeCoverStorage;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aXcAAAAASUVORK5CYII=', true);
        self::assertIsString($png);

        $uploaded = (new UploadCover($this->library, $covers))->execute('book-1', $png);
        self::assertSame('covers/book-1.png', $uploaded->coverKey);
        self::assertArrayHasKey($uploaded->coverKey, $covers->files);

        $this->assertUseCaseError('VALIDATION_ERROR', fn () => (new UploadCover($this->library, $covers))
            ->execute('book-1', str_repeat('x', 2 * 1024 * 1024 + 1)));
        $this->assertUseCaseError('VALIDATION_ERROR', fn () => (new UploadCover($this->library, $covers))
            ->execute('book-1', 'not an image'));
    }

    public function test_removing_cover_clears_its_reference_and_deletes_binary(): void
    {
        $book = $this->book('book-1', '9780307474728')->withCover('opaque/key.png');
        $this->library->books[$book->id] = $book;
        $covers = new FakeCoverStorage;
        $covers->files['opaque/key.png'] = 'image';

        $removed = (new RemoveCover($this->library, $covers))->execute('book-1');

        self::assertNull($removed->coverKey);
        self::assertArrayNotHasKey('opaque/key.png', $covers->files);
    }

    public function test_lending_checks_business_rules_before_one_atomic_save(): void
    {
        $first = $this->book('book-1', '9780307474728');
        $second = $this->book('book-2', '9780307474729');
        $this->library->books[$first->id] = $first;
        $this->library->books[$second->id] = $second;
        $lend = new LendBooks($this->library, $this->library, $this->library, $this->clock, $this->ids);

        $loan = $lend->execute('member-1', 'user-1', ['book-1', 'book-2']);

        self::assertCount(2, $loan->items);
        self::assertEquals($this->clock->instant->modify('+14 days'), $loan->items[0]->dueAt);
        self::assertSame('Title book-1', $loan->items[0]->bookTitle);
        self::assertSame('Novela', $loan->items[0]->categoryName);
        self::assertSame(1, $this->library->books['book-1']->availableCopies);
        self::assertSame(1, $this->library->books['book-2']->availableCopies);
        self::assertSame(1, $this->library->lendingSaveCount);

        $this->assertUseCaseError('DUPLICATE_BOOK_IN_LOAN', fn () => $lend->execute('member-1', 'user-1', ['book-1', 'book-1']));
        $this->assertUseCaseError('VALIDATION_ERROR', fn () => $lend->execute('member-1', 'user-1', []));
        self::assertSame(1, $this->library->lendingSaveCount);
    }

    public function test_lending_rejects_unavailable_missing_overdue_and_over_limit_without_partial_write(): void
    {
        $unavailable = $this->book('book-1', '9780307474728', total: 1)->withdraw();
        $book = $this->book('book-2', '9780307474729');
        $this->library->books[$unavailable->id] = $unavailable;
        $this->library->books[$book->id] = $book;
        $lend = new LendBooks($this->library, $this->library, $this->library, $this->clock, $this->ids);

        $this->assertUseCaseError('BOOK_NOT_AVAILABLE', fn () => $lend->execute('member-1', 'user-1', ['book-1', 'book-2']));
        self::assertSame(0, $this->library->lendingSaveCount);
        self::assertSame(2, $this->library->books['book-2']->availableCopies);
        $this->assertUseCaseError('NOT_FOUND', fn () => $lend->execute('member-1', 'user-1', ['missing']));

        $dueLoan = Loan::start('loan-due', 'member-1', 'user-1', $this->clock->instant->modify('-20 days'))
            ->addBook($book, 'item-due', 'Novela');
        $this->library->loans[$dueLoan->id] = $dueLoan;
        $this->assertUseCaseError('MEMBER_HAS_OVERDUE', fn () => $lend->execute('member-1', 'user-1', ['book-2']));

        $activeItems = [];
        for ($index = 1; $index <= 2; $index++) {
            $activeItems[] = new LoanItem(
                'active-'.$index,
                'active-loan',
                'other-'.$index,
                'Other',
                '978030747470'.$index,
                'Novela',
                $this->clock->instant->modify('+1 day'),
            );
        }
        $activeLoan = Loan::reconstitute('active-loan', 'member-1', 'user-1', $this->clock->instant, $activeItems);
        $this->library->loans = ['active-loan' => $activeLoan];
        $this->assertUseCaseError('MEMBER_LIMIT_EXCEEDED', fn () => $lend->execute('member-1', 'user-1', ['book-2', 'book-1']));
        self::assertSame(0, $this->library->lendingSaveCount);
    }

    public function test_return_replenishes_once_and_rejects_second_return(): void
    {
        $book = $this->book('book-1', '9780307474728')->withdraw();
        $loan = Loan::start('loan-1', 'member-1', 'user-1', $this->clock->instant)
            ->addBook($this->book('book-1', '9780307474728'), 'item-1', 'Novela');
        $this->library->books[$book->id] = $book;
        $this->library->loans[$loan->id] = $loan;
        $return = new ReturnItem($this->library, $this->clock);

        $returned = $return->execute('loan-1', 'item-1');

        self::assertSame($this->clock->instant, $returned->items[0]->returnedAt);
        self::assertSame(2, $this->library->books['book-1']->availableCopies);
        self::assertSame(1, $this->library->returnSaveCount);
        $this->assertUseCaseError('ITEM_ALREADY_RETURNED', fn () => $return->execute('loan-1', 'item-1'));
        self::assertSame(2, $this->library->books['book-1']->availableCopies);
        self::assertSame(1, $this->library->returnSaveCount);
    }

    public function test_overdue_list_uses_strict_deadline_and_sorts_most_late_first(): void
    {
        $this->library->members['member-1'] = new Member('member-1', 'Ana Pérez', 'DOC-1');
        $items = [
            new LoanItem('old', 'loan-1', 'book-old', 'Old', '9780307474701', 'Novela', $this->clock->instant->modify('-8 days')),
            new LoanItem('new', 'loan-1', 'book-new', 'New', '9780307474702', 'Novela', $this->clock->instant->modify('-2 days')),
            new LoanItem('due-now', 'loan-1', 'book-now', 'Now', '9780307474703', 'Novela', $this->clock->instant),
        ];
        $this->library->loans['loan-1'] = Loan::reconstitute('loan-1', 'member-1', 'user-1', $this->clock->instant, $items);

        $overdue = (new ListOverdue($this->library, $this->clock))->execute();

        self::assertSame(['old', 'new'], array_map(static fn ($item): string => $item->itemId, $overdue));
        self::assertSame([8, 2], array_map(static fn ($item): int => $item->daysLate, $overdue));
        self::assertSame('Ana Pérez', $overdue[0]->memberName);
    }

    private function book(
        string $id,
        string $isbn,
        string $title = '',
        string $author = 'Author',
        int $total = 2,
    ): Book {
        return Book::create(
            $id,
            $title === '' ? 'Title '.$id : $title,
            $author,
            Isbn::from($isbn),
            'category-1',
            'Novela',
            $total,
        );
    }

    private function assertUseCaseError(string $code, callable $action): void
    {
        try {
            $action();
            self::fail('Expected use case error was not thrown.');
        } catch (UseCaseError $error) {
            self::assertSame($code, $error->errorCode);
        } catch (RuleViolation $error) {
            self::assertSame($code, $error->errorCode);
        }
    }
}
