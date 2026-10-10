<?php

namespace App\Application\Ports\Outbound;

/** @phpstan-type Category array{id: string, name: string} */
interface CategoryRepository
{
    /** @return list<array{id: string, name: string}> */
    public function allOrderedByName(): array;

    public function findName(string $id): ?string;
}
