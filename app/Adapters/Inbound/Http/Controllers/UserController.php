<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\UseCases\CreateLibrarian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class UserController
{
    public function store(Request $request, CreateLibrarian $create): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:1', 'max:512'],
            'role' => ['required', 'in:librarian'],
        ])->validate();
        $user = $create->execute($data['username'], $data['password'], $data['role']);

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ], 201);
    }
}
