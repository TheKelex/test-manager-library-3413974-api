<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\UseCases\GetMostBorrowedReport;
use App\Domain\Shared\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ReportController
{
    public function mostBorrowed(Request $request, GetMostBorrowedReport $report): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ])->validate();
        $range = new DateRange(
            new \DateTimeImmutable($data['from'].'T00:00:00Z'),
            new \DateTimeImmutable($data['to'].'T00:00:00Z'),
        );
        $rows = $report->execute($range);

        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'rows' => array_map(static fn ($row): array => [
                'book_id' => $row->bookId,
                'book_title' => $row->bookTitle,
                'category_name' => $row->categoryName,
                'times_borrowed' => $row->timesBorrowed,
            ], $rows),
        ]);
    }
}
