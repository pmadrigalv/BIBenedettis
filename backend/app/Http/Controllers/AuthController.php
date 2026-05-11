<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\UsuarioToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uid_usuario' => ['required', 'string', 'max:80'],
            'pwd_usuario' => ['required', 'string', 'min:1'],
        ]);

        $usuario = Usuario::query()
            ->where('uid_usuario', $data['uid_usuario'])
            ->first();

        if (! $usuario || ! Hash::check($data['pwd_usuario'], (string) $usuario->pwd_usuario)) {
            return response()->json([
                'message' => 'Credenciales invalidas.',
            ], 401);
        }

        if (! $usuario->vigencia_usuario) {
            return response()->json([
                'message' => 'El usuario no esta vigente.',
            ], 403);
        }

        $plainToken = Str::random(64);

        UsuarioToken::query()->create([
            'id_usuario' => $usuario->id_usuario,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours(12),
        ]);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Usuario|null $usuario */
        $usuario = $request->attributes->get('auth_usuario');

        if (! $usuario) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return response()->json([
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $tokenHash = (string) $request->attributes->get('auth_token_hash', '');

        if ($tokenHash !== '') {
            UsuarioToken::query()->where('token_hash', $tokenHash)->delete();
        }

        return response()->json([
            'message' => 'Sesion cerrada.',
        ]);
    }

    private function buildUserPayload(Usuario $usuario): array
    {
        $autoridad = DB::table('autoridades')
            ->where('id_autoridad', $usuario->id_autoridad)
            ->value('descripcion_autoridad');

        return [
            'id_usuario' => $usuario->id_usuario,
            'uid_usuario' => $usuario->uid_usuario,
            'nombre' => trim($usuario->nombres_usuario.' '.$usuario->apellidos_usuario),
            'id_autoridad' => $usuario->id_autoridad,
            'autoridad' => $autoridad,
            'vigencia_usuario' => $usuario->vigencia_usuario,
        ];
    }
}
