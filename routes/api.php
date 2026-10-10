<?php

use App\Adapters\Inbound\Http\Controllers\AuthController;
use App\Adapters\Inbound\Http\Controllers\BookController;
use App\Adapters\Inbound\Http\Controllers\CategoryController;
use App\Adapters\Inbound\Http\Controllers\LoanController;
use App\Adapters\Inbound\Http\Controllers\MemberController;
use App\Adapters\Inbound\Http\Controllers\ReportController;
use App\Adapters\Inbound\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('jwt.auth')->group(function (): void {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/books', [BookController::class, 'index']);
        Route::get('/books/{id}/cover', [BookController::class, 'cover']);
        Route::get('/books/{id}', [BookController::class, 'show']);

        Route::middleware('role:admin')->group(function (): void {
            Route::post('/users', [UserController::class, 'store']);
            Route::post('/books', [BookController::class, 'store']);
            Route::put('/books/{id}', [BookController::class, 'update']);
            Route::delete('/books/{id}', [BookController::class, 'destroy']);
            Route::put('/books/{id}/cover', [BookController::class, 'uploadCover']);
            Route::delete('/books/{id}/cover', [BookController::class, 'removeCover']);
            Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
        });

        Route::middleware('role:admin,librarian')->group(function (): void {
            Route::get('/members', [MemberController::class, 'index']);
            Route::get('/members/{id}', [MemberController::class, 'show']);
            Route::post('/members', [MemberController::class, 'store']);
            Route::get('/loans/overdue', [LoanController::class, 'overdue']);
            Route::get('/loans', [LoanController::class, 'index']);
            Route::post('/loans', [LoanController::class, 'store']);
            Route::get('/loans/{id}', [LoanController::class, 'show']);
            Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem']);
        });
    });
});
