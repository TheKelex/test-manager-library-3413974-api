<?php

namespace App\Adapters\Inbound\Http\Support;

use App\Application\Errors\UseCaseError;
use App\Domain\Shared\RuleViolation;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiErrorResponse
{
    public static function render(Throwable $exception): JsonResponse
    {
        $code = 'INTERNAL_ERROR';
        $message = 'Ocurrió un error interno.';
        $status = 500;
        $details = [];

        if ($exception instanceof UseCaseError || $exception instanceof RuleViolation) {
            $code = $exception->errorCode;
            $message = $exception->getMessage();
            $status = self::statusFor($code);
        } elseif ($exception instanceof ValidationException) {
            $code = 'VALIDATION_ERROR';
            $message = 'Los datos enviados no son válidos.';
            $status = 422;
            foreach ($exception->errors() as $field => $issues) {
                foreach ($issues as $issue) {
                    $details[] = ['field' => (string) $field, 'issue' => (string) $issue];
                }
            }
        } elseif ($exception instanceof QueryException) {
            $databaseMessage = $exception->getMessage();
            if (str_contains($databaseMessage, 'uq_book_active_isbn')) {
                $code = 'ISBN_TAKEN';
                $message = 'El ISBN ya pertenece a un libro activo.';
                $status = 409;
            } elseif (str_contains($databaseMessage, 'user_username_unique')) {
                $code = 'USERNAME_TAKEN';
                $message = 'El nombre de usuario ya está registrado.';
                $status = 409;
            } elseif (str_contains($databaseMessage, 'member_document_number_unique')) {
                $code = 'DOCUMENT_TAKEN';
                $message = 'El documento ya está registrado.';
                $status = 409;
            } elseif (str_contains($databaseMessage, 'uq_loan_item_loan_book')) {
                $code = 'DUPLICATE_BOOK_IN_LOAN';
                $message = 'No se puede repetir un libro en el mismo préstamo.';
                $status = 422;
            } elseif (str_contains($databaseMessage, 'ck_book_') || str_contains($databaseMessage, 'ck_user_role')) {
                $code = 'VALIDATION_ERROR';
                $message = 'Los datos enviados no cumplen las reglas del sistema.';
                $status = 422;
            } elseif (str_contains($databaseMessage, 'violates foreign key constraint')) {
                $code = 'NOT_FOUND';
                $message = 'Uno de los recursos relacionados no existe.';
                $status = 404;
            }
        } elseif ($exception instanceof AuthenticationException) {
            $code = 'INVALID_CREDENTIALS';
            $message = 'Se requiere un token válido.';
            $status = 401;
        } elseif ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404) {
            $code = 'NOT_FOUND';
            $message = 'El recurso no existe.';
            $status = 404;
        }

        return response()->json(compact('code', 'message', 'details'), $status);
    }

    private static function statusFor(string $code): int
    {
        return match ($code) {
            'INVALID_CREDENTIALS' => 401,
            'FORBIDDEN_ROLE' => 403,
            'NOT_FOUND' => 404,
            'USERNAME_TAKEN',
            'ISBN_TAKEN',
            'DOCUMENT_TAKEN',
            'BOOK_HAS_OPEN_LOANS',
            'BOOK_NOT_AVAILABLE',
            'MEMBER_HAS_OVERDUE',
            'ITEM_ALREADY_RETURNED' => 409,
            'MEMBER_LIMIT_EXCEEDED',
            'DUPLICATE_BOOK_IN_LOAN',
            'INVALID_DATE_RANGE',
            'VALIDATION_ERROR' => 422,
            default => 500,
        };
    }
}
