<?php

namespace App\Http\Middleware;

use App\Models\UsuarioToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateUsuarioToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return $this->unauthorized('Token no proporcionado.');
        }

        $tokenHash = hash('sha256', $plainToken);

        $tokenRecord = UsuarioToken::query()
            ->with('usuario')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $tokenRecord || ! $tokenRecord->usuario) {
            return $this->unauthorized('Token invalido.');
        }

        if ($tokenRecord->expires_at && $tokenRecord->expires_at->isPast()) {
            $tokenRecord->delete();

            return $this->unauthorized('Token expirado.');
        }

        $tokenRecord->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('auth_usuario', $tokenRecord->usuario);
        $request->attributes->set('auth_token_hash', $tokenHash);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
