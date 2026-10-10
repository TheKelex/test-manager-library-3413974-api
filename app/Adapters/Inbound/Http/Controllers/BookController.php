<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Support\BookPayload;
use App\Application\DTO\BookSearch;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\UseCases\CreateBook;
use App\Application\UseCases\GetBook;
use App\Application\UseCases\RemoveCover;
use App\Application\UseCases\SearchBooks;
use App\Application\UseCases\UpdateBook;
use App\Application\UseCases\UploadCover;
use App\Application\UseCases\WithdrawBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

final readonly class BookController
{
    public function __construct(
        private BookRepository $books,
        private CoverStorage $covers,
    ) {}

    public function index(Request $request, SearchBooks $search): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'q' => ['sometimes', 'nullable', 'string', 'max:250'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $page = $search->execute(new BookSearch(
            $data['q'] ?? null,
            $data['category_id'] ?? null,
            (int) ($data['page'] ?? 1),
            (int) ($data['size'] ?? 20),
        ));

        return response()->json([
            'items' => array_map(static fn ($book): array => BookPayload::from($book), $page->items),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ]);
    }

    public function show(string $id, GetBook $get): JsonResponse
    {
        return response()->json(BookPayload::from($get->execute($id)));
    }

    public function store(Request $request, CreateBook $create): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'isbn' => ['required', 'regex:/^\d{13}$/'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ])->validate();

        return response()->json(BookPayload::from($create->execute(
            $data['title'],
            $data['author'],
            $data['isbn'],
            $data['category_id'],
            (int) $data['total_copies'],
        )), 201);
    }

    public function update(Request $request, string $id, UpdateBook $update): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ])->validate();

        return response()->json(BookPayload::from($update->execute(
            $id,
            $data['title'],
            $data['author'],
            $data['category_id'],
            (int) $data['total_copies'],
        )));
    }

    public function destroy(string $id, WithdrawBook $withdraw): Response
    {
        $withdraw->execute($id);

        return response()->noContent();
    }

    public function uploadCover(Request $request, string $id, UploadCover $upload): JsonResponse
    {
        $data = Validator::make($request->all(), ['file' => ['required', 'file', 'max:2048']])->validate();
        $contents = file_get_contents($data['file']->getRealPath());
        if ($contents === false) {
            throw new \RuntimeException('Unable to read uploaded cover.');
        }

        return response()->json(BookPayload::from($upload->execute($id, $contents)));
    }

    public function cover(string $id): Response
    {
        $book = $this->books->findActiveById($id);
        if ($book === null || $book->coverKey === null) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El recurso no existe.',
                'details' => [],
            ], 404);
        }
        $contents = $this->covers->read($book->coverKey);
        if ($contents === null) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El recurso no existe.',
                'details' => [],
            ], 404);
        }
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw new \RuntimeException('Stored cover has an unsupported content type.');
        }

        return response($contents, 200, ['Content-Type' => $mimeType, 'Cache-Control' => 'private, no-store']);
    }

    public function removeCover(string $id, RemoveCover $remove): Response
    {
        $remove->execute($id);

        return response()->noContent();
    }
}
