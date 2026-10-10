<?php

namespace App\Adapters\Inbound\Http\Support;

use App\Domain\Loan\Loan;

final class LoanPayload
{
    /** @return array<string, mixed> */
    public static function from(Loan $loan, \DateTimeImmutable $now): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_at' => $loan->loanedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'status' => $loan->statusAt($now),
            'items' => array_map(static fn ($item): array => [
                'id' => $item->id,
                'book_id' => $item->bookId,
                'book_title' => $item->bookTitle,
                'isbn' => $item->isbn,
                'category_name' => $item->categoryName,
                'due_at' => $item->dueAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                'returned_at' => $item->returnedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            ], $loan->items),
        ];
    }
}
