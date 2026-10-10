<?php

namespace App\Adapters\Inbound\Http\Support;

use App\Domain\Book\Book;

final class BookPayload
{
    /** @return array<string, mixed> */
    public static function from(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn->value,
            'category' => ['id' => $book->categoryId, 'name' => $book->categoryName],
            'total_copies' => $book->totalCopies,
            'available_copies' => $book->availableCopies,
            'cover_key' => $book->coverKey,
        ];
    }
}
