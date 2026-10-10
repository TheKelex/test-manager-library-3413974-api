<?php

namespace App\Application\UseCases;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Domain\Book\Book;

final readonly class UploadCover
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private BookRepository $books,
        private CoverStorage $covers,
    ) {}

    public function execute(string $bookId, string $contents): Book
    {
        $book = $this->books->findActiveById($bookId)
            ?? throw new UseCaseError('NOT_FOUND', 'El libro no existe o está dado de baja.');
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw new UseCaseError('VALIDATION_ERROR', 'La portada debe pesar más de 0 y máximo 2 MB.');
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->buffer($contents);
        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw new UseCaseError('VALIDATION_ERROR', 'La portada debe ser una imagen JPEG o PNG válida.');
        }

        $key = $this->covers->put($bookId, $contents, $mimeType);
        $updated = $book->withCover($key);
        $this->books->updateCover($updated);
        if ($book->coverKey !== null) {
            $this->covers->delete($book->coverKey);
        }

        return $updated;
    }
}
