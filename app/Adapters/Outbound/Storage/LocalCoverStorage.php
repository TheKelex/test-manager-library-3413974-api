<?php

namespace App\Adapters\Outbound\Storage;

use App\Application\Ports\Outbound\CoverStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class LocalCoverStorage implements CoverStorage
{
    public function put(string $bookId, string $contents, string $mimeType): string
    {
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new \InvalidArgumentException('Unsupported cover MIME type.'),
        };
        $key = 'covers/'.Str::uuid().'.'.$extension;
        Storage::disk('covers')->put($key, $contents);

        return $key;
    }

    public function delete(string $key): void
    {
        Storage::disk('covers')->delete($key);
    }

    public function read(string $key): ?string
    {
        $disk = Storage::disk('covers');

        return $disk->exists($key) ? $disk->get($key) : null;
    }
}
