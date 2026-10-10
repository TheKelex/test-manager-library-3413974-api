<?php

namespace Tests\Fakes;

use App\Application\Ports\Outbound\CoverStorage;

final class FakeCoverStorage implements CoverStorage
{
    /** @var array<string, string> */
    public array $files = [];

    public function put(string $bookId, string $contents, string $mimeType): string
    {
        $key = 'covers/'.$bookId.'.'.($mimeType === 'image/png' ? 'png' : 'jpg');
        $this->files[$key] = $contents;

        return $key;
    }

    public function delete(string $key): void
    {
        unset($this->files[$key]);
    }
}
