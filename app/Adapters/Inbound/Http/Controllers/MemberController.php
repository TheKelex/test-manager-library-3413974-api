<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\Errors\UseCaseError;
use App\Application\Ports\Outbound\MemberRepository;
use App\Application\UseCases\CreateMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class MemberController
{
    public function __construct(private MemberRepository $members) {}

    public function index(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $page = $this->members->searchMembers(
            $data['q'] ?? null,
            (int) ($data['page'] ?? 1),
            (int) ($data['size'] ?? 20),
        );

        return response()->json([
            'items' => array_map(fn ($member): array => [
                'id' => $member->id,
                'full_name' => $member->fullName,
                'document_number' => $member->documentNumber,
                'active_loans' => $this->members->activeLoanCount($member->id),
            ], $page->items),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $member = $this->members->findById($id)
            ?? throw new UseCaseError('NOT_FOUND', 'El socio no existe.');

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->fullName,
            'document_number' => $member->documentNumber,
            'active_loans' => $this->members->activeLoanCount($member->id),
        ]);
    }

    public function store(Request $request, CreateMember $create): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:200'],
            'document_number' => ['required', 'string', 'max:30'],
        ])->validate();
        $member = $create->execute($data['full_name'], $data['document_number']);

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->fullName,
            'document_number' => $member->documentNumber,
            'active_loans' => 0,
        ], 201);
    }
}
