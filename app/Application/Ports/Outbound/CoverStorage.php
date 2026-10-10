<?php

namespace App\Application\Ports\Outbound;

interface CoverStorage
{
    public function put(string $bookId, string $contents, string $mimeType): string;

    public function delete(string $key): void;
}
