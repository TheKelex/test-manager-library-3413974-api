<?php

namespace Tests\Feature;

use App\Adapters\Outbound\Persistence\Repositories\EloquentBookRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentCategoryRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentLoanRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentMemberRepository;
use App\Adapters\Outbound\Persistence\Repositories\EloquentMostBorrowedReportReader;
use App\Adapters\Outbound\Persistence\Repositories\EloquentUserRepository;
use App\Application\Errors\UseCaseError;
use App\Domain\Book\Book;
use App\Domain\Loan\Loan;
use App\Domain\Shared\DateRange;
use App\Domain\Shared\Isbn;
use App\Domain\User\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersistenceAdaptersTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORY = '11111111-1111-4111-8111-111111111111';

    private const USER = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000001';

    private const MEMBER = 'bbbbbbbb-bbbb-4bbb-8bbb-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        DB::table('library.user')->insert([
            'id' => self::USER,
            'username' => 'admin',
            'password_hash' => 'test-hash',
            'role' => 'admin',
        ]);
        DB::table('library.member')->insert([
            'id' => self::MEMBER,
            'full_name' => 'Ana Pérez',
            'document_number' => 'DOC-1',
        ]);
    }

    public function test_catalog_member_and_user_repositories_map_persisted_rows(): void
    {
        $book = $this->book('cccccccc-cccc-4ccc-8ccc-000000000001', '9780307474728', 'Frozen title');
        $books = new EloquentBookRepository;
        $books->saveBook($book);

        self::assertSame('Frozen title', $books->findActiveById($book->id)?->title);
        self::assertSame($book->id, $books->findActiveByIsbn($book->isbn)?->id);
        self::assertSame('Novela', (new EloquentCategoryRepository)->findName(self::CATEGORY));
        self::assertSame('Ana Pérez', (new EloquentMemberRepository)->findById(self::MEMBER)?->fullName);

        $users = new EloquentUserRepository;
        $users->saveUser(new User('dddddddd-dddd-4ddd-8ddd-000000000001', 'Luz', 'hash', 'librarian'));
        self::assertSame('luz', $users->findByUsername(' LUZ ')?->username);
    }

    public function test_most_borrowed_report_uses_inclusive_dates_and_frozen_catalog_values(): void
    {
        $book = $this->book('cccccccc-cccc-4ccc-8ccc-000000000001', '9780307474728', 'Title at loan');
        (new EloquentBookRepository)->saveBook($book);
        $this->insertLoan('eeeeeeee-eeee-4eee-8eee-000000000001', '2026-10-01T00:00:00Z', $book->id, 'Title at loan');
        $this->insertLoan('eeeeeeee-eeee-4eee-8eee-000000000002', '2026-10-31T23:59:59Z', $book->id, 'Title at loan');
        $this->insertLoan('eeeeeeee-eeee-4eee-8eee-000000000003', '2026-11-01T00:00:00Z', $book->id, 'Outside range');
        DB::table('library.book')->where('id', $book->id)->update(['title' => 'Renamed later']);

        $rows = (new EloquentMostBorrowedReportReader)->mostBorrowed(new DateRange(
            new \DateTimeImmutable('2026-10-01T00:00:00Z'),
            new \DateTimeImmutable('2026-10-31T00:00:00Z'),
        ));

        self::assertCount(1, $rows);
        self::assertSame('Title at loan', $rows[0]->bookTitle);
        self::assertSame(2, $rows[0]->timesBorrowed);
    }

    public function test_atomic_lending_blocks_overselling_and_returning_twice(): void
    {
        $book = $this->book('cccccccc-cccc-4ccc-8ccc-000000000001', '9780307474728', 'One copy', 1);
        (new EloquentBookRepository)->saveBook($book);
        $loans = new EloquentLoanRepository;
        $now = new \DateTimeImmutable('2026-10-10T12:00:00Z');
        $loan = Loan::start('eeeeeeee-eeee-4eee-8eee-000000000001', self::MEMBER, self::USER, $now)
            ->addBook($book, 'ffffffff-ffff-4fff-8fff-000000000001', 'Novela');

        $loans->saveLendingAtomically($loan, [$book->withdraw()]);

        self::assertSame(0, (new EloquentBookRepository)->findActiveById($book->id)?->availableCopies);
        $secondLoan = Loan::start('eeeeeeee-eeee-4eee-8eee-000000000002', self::MEMBER, self::USER, $now)
            ->addBook($book, 'ffffffff-ffff-4fff-8fff-000000000002', 'Novela');
        try {
            $loans->saveLendingAtomically($secondLoan, [$book->withdraw()]);
            self::fail('The last copy was lent more than once.');
        } catch (UseCaseError $error) {
            self::assertSame('BOOK_NOT_AVAILABLE', $error->errorCode);
        }

        $context = $loans->findReturnContext($loan->id, $loan->items[0]->id);
        self::assertNotNull($context);
        $returnedLoan = $context->loan->returnItem($context->item->id, $now);
        $returnedItem = $returnedLoan->items[0];
        $loans->returnItemAtomically($returnedLoan, $returnedItem, $context->book->restock());
        self::assertSame(1, (new EloquentBookRepository)->findActiveById($book->id)?->availableCopies);

        try {
            $loans->returnItemAtomically($returnedLoan, $returnedItem, $context->book->restock());
            self::fail('A returned item was returned a second time.');
        } catch (UseCaseError $error) {
            self::assertSame('ITEM_ALREADY_RETURNED', $error->errorCode);
        }
    }

    private function book(string $id, string $isbn, string $title, int $total = 2): Book
    {
        return Book::create($id, $title, 'Author', Isbn::from($isbn), self::CATEGORY, 'Novela', $total);
    }

    private function insertLoan(string $id, string $loanedAt, string $bookId, string $title): void
    {
        DB::table('library.loan')->insert([
            'id' => $id,
            'member_id' => self::MEMBER,
            'loaned_by_user_id' => self::USER,
            'loaned_at' => $loanedAt,
        ]);
        DB::table('library.loan_item')->insert([
            'id' => 'ffffffff-ffff-4fff-8fff-'.substr($id, -12),
            'loan_id' => $id,
            'book_id' => $bookId,
            'book_title' => $title,
            'isbn' => '9780307474728',
            'category_name' => 'Novela',
            'due_at' => '2026-10-15T00:00:00Z',
            'returned_at' => null,
        ]);
    }
}
