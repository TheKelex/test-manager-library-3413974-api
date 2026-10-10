<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Support\LoanPayload;
use App\Application\DTO\LoanSearch;
use App\Application\Ports\Outbound\Clock;
use App\Application\UseCases\GetLoan;
use App\Application\UseCases\LendBooks;
use App\Application\UseCases\ListOverdue;
use App\Application\UseCases\ReturnItem;
use App\Application\UseCases\SearchLoans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class LoanController
{
    public function __construct(private Clock $clock) {}

    public function index(Request $request, SearchLoans $search): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'in:open,overdue,closed'],
            'member_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $page = $search->execute(new LoanSearch(
            isset($data['from']) ? new \DateTimeImmutable($data['from'].'T00:00:00Z') : null,
            isset($data['to']) ? new \DateTimeImmutable($data['to'].'T00:00:00Z') : null,
            $data['status'] ?? null,
            $data['member_id'] ?? null,
            (int) ($data['page'] ?? 1),
            (int) ($data['size'] ?? 20),
        ));

        return response()->json([
            'items' => array_map(fn ($entry): array => [
                ...LoanPayload::from($entry->loan, $this->clock->now()),
                'member_name' => $entry->memberName,
                'status' => $entry->status,
            ], $page->items),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ]);
    }

    public function show(string $id, GetLoan $get): JsonResponse
    {
        return response()->json(LoanPayload::from($get->execute($id), $this->clock->now()));
    }

    public function store(Request $request, LendBooks $lend): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:3'],
            'items.*.book_id' => ['required', 'uuid', 'distinct'],
        ])->validate();
        $loan = $lend->execute(
            $data['member_id'],
            (string) $request->attributes->get('auth_sub'),
            array_column($data['items'], 'book_id'),
        );

        return response()->json(LoanPayload::from($loan, $this->clock->now()), 201);
    }

    public function returnItem(string $loanId, string $itemId, ReturnItem $return): JsonResponse
    {
        $loan = $return->execute($loanId, $itemId);
        foreach ($loan->items as $item) {
            if ($item->id === $itemId) {
                return response()->json([
                    'id' => $item->id,
                    'book_id' => $item->bookId,
                    'book_title' => $item->bookTitle,
                    'isbn' => $item->isbn,
                    'category_name' => $item->categoryName,
                    'due_at' => $item->dueAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                    'returned_at' => $item->returnedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                ]);
            }
        }

        throw new \LogicException('Returned loan item was not found in the updated loan.');
    }

    public function overdue(ListOverdue $overdue): JsonResponse
    {
        $items = $overdue->execute();

        return response()->json(['items' => array_map(static fn ($item): array => [
            'loan_id' => $item->loanId,
            'item_id' => $item->itemId,
            'book_title' => $item->bookTitle,
            'member_name' => $item->memberName,
            'due_at' => $item->dueAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'days_late' => $item->daysLate,
        ], $items)]);
    }
}
