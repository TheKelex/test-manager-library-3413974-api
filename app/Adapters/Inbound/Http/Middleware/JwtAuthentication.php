<?php

namespace App\Adapters\Inbound\Http\Middleware;

use App\Adapters\Outbound\Security\JwtTokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class JwtAuthentication
{
    public function __construct(private JwtTokenVerifier $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Se requiere un token válido.',
                'details' => [],
            ], 401);
        }

        try {
            $claims = $this->tokens->verify($matches[1]);
        } catch (\DomainException|\InvalidArgumentException|\UnexpectedValueException) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Se requiere un token válido.',
                'details' => [],
            ], 401);
        }

        $request->attributes->set('auth_sub', $claims['sub']);
        $request->attributes->set('auth_role', $claims['role']);

        return $next($request);
    }
}
