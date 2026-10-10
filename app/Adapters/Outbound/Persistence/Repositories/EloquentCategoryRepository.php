<?php

namespace App\Adapters\Outbound\Persistence\Repositories;

use App\Adapters\Outbound\Persistence\Eloquent\Models\CategoryRecord;
use App\Application\Ports\Outbound\CategoryRepository;

final class EloquentCategoryRepository implements CategoryRepository
{
    public function allOrderedByName(): array
    {
        return CategoryRecord::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn ($category): array => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }

    public function findName(string $id): ?string
    {
        $name = CategoryRecord::query()->whereKey($id)->value('name');

        return $name === null ? null : (string) $name;
    }
}
