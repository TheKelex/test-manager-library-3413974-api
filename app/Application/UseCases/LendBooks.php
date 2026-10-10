<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\IdGenerator;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Loan\Loan;

final readonly class LendBooks
{
    public function __construct(
        private BookRepository $books,
        private LoanRepository $loans,
        private MemberRepository $members,
        private Clock $clock,
        private IdGenerator $ids,
    ) {}

    /** @param list<string> $bookIds */
    public function execute(string $memberId, string $userId, array $bookIds): Loan
    {
        if ($bookIds === []) {
            throw new UseCaseError('VALIDATION_ERROR', 'El préstamo debe incluir al menos un libro.');
        }
        if (count($bookIds) > 3) {
            throw new UseCaseError('MEMBER_LIMIT_EXCEEDED', 'Un préstamo no puede contener más de tres libros.');
        }
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new UseCaseError('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir un libro en el mismo préstamo.');
        }
        if ($this->members->findById($memberId) === null) {
            throw new UseCaseError('NOT_FOUND', 'El socio no existe.');
        }

        $now = $this->clock->now();
        $summary = $this->loans->memberLoanSummary($memberId, $now);
        if ($summary->hasOverdue) {
            throw new UseCaseError('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y no puede pedir otro préstamo.');
        }
        if ($summary->activeItems + count($bookIds) > 3) {
            throw new UseCaseError('MEMBER_LIMIT_EXCEEDED', 'El socio no puede tener más de tres libros sin devolver.');
        }

        $booksById = [];
        foreach ($this->books->findActiveByIds($bookIds) as $book) {
            $booksById[$book->id] = $book;
        }
        if (count($booksById) !== count($bookIds)) {
            throw new UseCaseError('NOT_FOUND', 'Uno o más libros no existen o están dados de baja.');
        }

        $loan = Loan::start($this->ids->newId(), $memberId, $userId, $now);
        $updatedBooks = [];
        foreach ($bookIds as $bookId) {
            $book = $booksById[$bookId];
            $updatedBooks[] = $book->withdraw();
            $loan = $loan->addBook($book, $this->ids->newId(), $book->categoryName);
        }

        $this->loans->saveLendingAtomically($loan, $updatedBooks);

        return $loan;
    }
}
