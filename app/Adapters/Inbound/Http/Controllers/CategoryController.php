<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\Ports\Outbound\CategoryRepository;
use Illuminate\Http\JsonResponse;

final readonly class CategoryController
{
    public function __construct(private CategoryRepository $categories) {}

    public function index(): JsonResponse
    {
        return response()->json(['items' => $this->categories->allOrderedByName()]);
    }
}
