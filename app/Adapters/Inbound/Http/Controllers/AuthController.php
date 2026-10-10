<?php

namespace App\Adapters\Inbound\Http\Controllers;

use App\Application\UseCases\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AuthController
{
    public function login(Request $request, Login $login): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:512'],
        ])->validate();
        $result = $login->execute($data['username'], $data['password']);

        return response()->json([
            'access_token' => $result->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $result->expiresIn,
            'role' => $result->role,
        ]);
    }
}
