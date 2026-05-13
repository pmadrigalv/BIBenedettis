<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    private const SISTEMAS_AUTORIDAD_IDS = [10, 85, 94, 95, 96, 97, 98];

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $sortBy = (string) $request->query('sort_by', 'id_usuario');
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc'));
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $sortableColumns = [
            'id_usuario' => 'usuarios.id_usuario',
            'uid_usuario' => 'usuarios.uid_usuario',
            'nombres_usuario' => 'usuarios.nombres_usuario',
            'apellidos_usuario' => 'usuarios.apellidos_usuario',
            'email_usuario' => 'usuarios.email_usuario',
            'autoridad' => 'autoridades.descripcion_autoridad',
        ];

        $sortColumn = $sortableColumns[$sortBy] ?? $sortableColumns['id_usuario'];

        $query = Usuario::query()
            ->leftJoin('autoridades', 'autoridades.id_autoridad', '=', 'usuarios.id_autoridad')
            ->select([
                'usuarios.id_usuario',
                'usuarios.uid_usuario',
                'usuarios.nombres_usuario',
                'usuarios.apellidos_usuario',
                'usuarios.telefono_usuario',
                'usuarios.email_usuario',
                'usuarios.vigencia_usuario',
                'autoridades.descripcion_autoridad as autoridad',
            ]);

        if ($search !== '') {
            $query->where(function ($subquery) use ($search): void {
                $subquery
                    ->where('usuarios.id_usuario', 'like', "%{$search}%")
                    ->orWhere('usuarios.uid_usuario', 'like', "%{$search}%")
                    ->orWhere('usuarios.nombres_usuario', 'like', "%{$search}%")
                    ->orWhere('usuarios.apellidos_usuario', 'like', "%{$search}%")
                    ->orWhere('usuarios.email_usuario', 'like', "%{$search}%")
                    ->orWhere('autoridades.descripcion_autoridad', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortColumn, $sortDir);

        if ($sortColumn !== 'usuarios.id_usuario') {
            $query->orderByDesc('usuarios.id_usuario');
        }

        $usuarios = $query
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($usuarios);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uid_usuario' => ['required', 'string', 'max:80', 'unique:usuarios,uid_usuario'],
            'pwd_usuario' => ['required', 'string', 'min:6'],
            'nombres_usuario' => ['required', 'string', 'max:120'],
            'apellidos_usuario' => ['required', 'string', 'max:120'],
            'telefono_usuario' => ['nullable', 'string', 'max:25'],
            'email_usuario' => ['required', 'email', 'max:150', 'unique:usuarios,email_usuario'],
            'id_autoridad' => ['nullable', 'integer', 'exists:autoridades,id_autoridad'],
            'vigencia_usuario' => ['nullable', 'boolean'],
        ]);

        if (! array_key_exists('vigencia_usuario', $validated) || $validated['vigencia_usuario'] === null) {
            $validated['vigencia_usuario'] = true;
        }

        $usuario = Usuario::create($validated);

        return response()->json([
            'message' => 'Usuario registrado correctamente.',
            'data' => $usuario,
        ], 201);
    }

    public function show(int $usuarioId): JsonResponse
    {
        $usuario = Usuario::query()->find($usuarioId);

        if (! $usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        return response()->json($usuario);
    }

    public function update(Request $request, int $usuarioId): JsonResponse
    {
        $usuario = Usuario::query()->find($usuarioId);

        if (! $usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'uid_usuario' => ['required', 'string', 'max:80', 'unique:usuarios,uid_usuario,'.$usuario->id_usuario.',id_usuario'],
            'pwd_usuario' => ['nullable', 'string', 'min:6'],
            'nombres_usuario' => ['required', 'string', 'max:120'],
            'apellidos_usuario' => ['required', 'string', 'max:120'],
            'telefono_usuario' => ['nullable', 'string', 'max:25'],
            'email_usuario' => ['required', 'email', 'max:150', 'unique:usuarios,email_usuario,'.$usuario->id_usuario.',id_usuario'],
            'id_autoridad' => ['nullable', 'integer', 'exists:autoridades,id_autoridad'],
            'vigencia_usuario' => ['nullable', 'boolean'],
        ]);

        $usuario->uid_usuario = $validated['uid_usuario'];
        $usuario->nombres_usuario = $validated['nombres_usuario'];
        $usuario->apellidos_usuario = $validated['apellidos_usuario'];
        $usuario->telefono_usuario = $validated['telefono_usuario'] ?? null;
        $usuario->email_usuario = $validated['email_usuario'];
        $usuario->id_autoridad = $validated['id_autoridad'] ?? null;
        $usuario->vigencia_usuario = array_key_exists('vigencia_usuario', $validated)
            ? (bool) $validated['vigencia_usuario']
            : $usuario->vigencia_usuario;

        if (! empty($validated['pwd_usuario'])) {
            $usuario->pwd_usuario = $validated['pwd_usuario'];
        }

        $usuario->save();

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => $usuario,
        ]);
    }

    public function autoridades(): JsonResponse
    {
        $autoridades = DB::table('autoridades')
            ->select(['id_autoridad', 'descripcion_autoridad', 'tipo_autoridad', 'msg_autoridad'])
            ->orderBy('id_autoridad')
            ->get();

        return response()->json($autoridades);
    }

    public function getAutoridades(): JsonResponse
    {
        $autoridades = DB::table('autoridades')
            ->select(['id_autoridad', 'descripcion_autoridad'])
            ->get();

        return response()->json($autoridades);
    }

    public function solicitantes(): JsonResponse
    {
        $usuarios = Usuario::query()
            ->where('vigencia_usuario', true)
            ->orderBy('nombres_usuario')
            ->orderBy('apellidos_usuario')
            ->get([
                'id_usuario',
                'nombres_usuario',
                'apellidos_usuario',
                'uid_usuario',
                'vigencia_usuario',
            ])
            ->map(fn ($u) => [
                'id_usuario'      => $u->id_usuario,
                'uid_usuario'     => $u->uid_usuario,
                'nombre'          => trim($u->nombres_usuario.' '.$u->apellidos_usuario),
                'vigencia_usuario' => $u->vigencia_usuario,
            ])
            ->values();

        return response()->json($usuarios);
    }

    public function usuariosCatalogo(): JsonResponse
    {
        $usuarios = Usuario::query()
            ->leftJoin('autoridades', 'autoridades.id_autoridad', '=', 'usuarios.id_autoridad')
            ->orderBy('usuarios.nombres_usuario')
            ->orderBy('usuarios.apellidos_usuario')
            ->get([
                'usuarios.id_usuario',
                'usuarios.nombres_usuario',
                'usuarios.apellidos_usuario',
                'autoridades.descripcion_autoridad as autoridad',
            ])
            ->map(fn ($u) => [
                'id_usuario' => $u->id_usuario,
                'nombre' => trim($u->nombres_usuario.' '.$u->apellidos_usuario),
                'autoridad' => $u->autoridad,
            ])
            ->values();

        return response()->json($usuarios);
    }

    public function unidadesCatalogo(Request $request): JsonResponse
    {
        $unidades = $this->accessibleUnidadesQuery($request)
            ->orderBy('unidades.nombre_unidad')
            ->orderBy('unidades.id_unidad')
            ->get()
            ->map(fn ($unidad) => [
                'id_unidad' => $unidad->id_unidad,
                'nombre_unidad' => $unidad->nombre_unidad,
                'ip_unidad' => $unidad->ip_unidad,
                'status_unidad' => (int) $unidad->status_unidad,
            ])
            ->values();

        return response()->json($unidades);
    }

    public function unidades(Request $request, int $usuarioId): JsonResponse
    {
        $usuario = Usuario::query()->find($usuarioId);

        if (! $usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $unidades = $this->accessibleUnidadesQuery($request)
            ->join('unidad_usuario', 'unidad_usuario.id_unidad', '=', 'unidades.id_unidad')
            ->where('unidad_usuario.id_usuario', $usuario->id_usuario)
            ->orderBy('unidades.nombre_unidad')
            ->orderBy('unidades.id_unidad')
            ->get([
                'unidades.id_unidad',
                'unidades.nombre_unidad',
                'unidades.ip_unidad',
                'unidades.status_unidad',
            ])
            ->map(fn ($unidad) => [
                'id_unidad' => $unidad->id_unidad,
                'nombre_unidad' => $unidad->nombre_unidad,
                'ip_unidad' => $unidad->ip_unidad,
                'status_unidad' => (int) $unidad->status_unidad,
            ])
            ->values();

        return response()->json($unidades);
    }

    public function addUnidad(Request $request, int $usuarioId): JsonResponse
    {
        $usuario = Usuario::query()->find($usuarioId);

        if (! $usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'id_unidad' => ['required', 'integer', 'exists:unidades,id_unidad'],
        ]);

        $unidadAccesible = $this->accessibleUnidadesQuery($request)
            ->where('unidades.id_unidad', $validated['id_unidad'])
            ->exists();

        if (! $unidadAccesible) {
            return response()->json([
                'message' => 'No tienes acceso a la unidad seleccionada.',
            ], 403);
        }

        $usuario->unidades()->syncWithoutDetaching([$validated['id_unidad']]);

        return response()->json([
            'message' => 'Unidad relacionada al usuario correctamente.',
        ], 201);
    }

    public function removeUnidad(Request $request, int $usuarioId, int $unidadId): JsonResponse
    {
        $usuario = Usuario::query()->find($usuarioId);

        if (! $usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $unidadAccesible = $this->accessibleUnidadesQuery($request)
            ->where('unidades.id_unidad', $unidadId)
            ->exists();

        if (! $unidadAccesible) {
            return response()->json([
                'message' => 'No tienes acceso a la unidad seleccionada.',
            ], 403);
        }

        $usuario->unidades()->detach([$unidadId]);

        return response()->json([
            'message' => 'Unidad eliminada de la relacion con el usuario correctamente.',
        ]);
    }

    public function unidadesPorUsuario(int $usuarioId): JsonResponse
    {
        $hoy = now()->toDateString();

        $unidades = DB::table('unidad_usuario')
            ->join('unidades', 'unidades.id_unidad', '=', 'unidad_usuario.id_unidad')
            ->where('unidad_usuario.id_usuario', $usuarioId)
            ->where('unidades.status_unidad', 1)
            ->where(function ($query) use ($hoy): void {
                $query->whereNull('unidades.uactip_unidad')
                    ->orWhere('unidades.uactip_unidad', '<=', $hoy.' 23:59:59');
            })
            ->select([
                'unidades.id_unidad',
                'unidades.nombre_unidad',
            ])
            ->orderBy('unidades.id_unidad')
            ->get();

        return response()->json($unidades);
    }

    public function usuariosSistemas(): JsonResponse
    {
        $usuarios = Usuario::query()
            ->leftJoin('autoridades', 'autoridades.id_autoridad', '=', 'usuarios.id_autoridad')
            ->whereIn('usuarios.id_autoridad', self::SISTEMAS_AUTORIDAD_IDS)
            ->orderBy('usuarios.nombres_usuario')
            ->orderBy('usuarios.apellidos_usuario')
            ->get([
                'usuarios.id_usuario',
                'usuarios.nombres_usuario',
                'usuarios.apellidos_usuario',
                'autoridades.descripcion_autoridad as autoridad',
            ])
            ->map(fn ($usuario) => [
                'id_usuario' => $usuario->id_usuario,
                'nombre' => trim($usuario->nombres_usuario.' '.$usuario->apellidos_usuario),
                'autoridad' => $usuario->autoridad,
            ])
            ->values();

        return response()->json($usuarios);
    }

    private function accessibleUnidadesQuery(Request $request): \Illuminate\Database\Query\Builder
    {
        $authUsuario = $request->attributes->get('auth_usuario');

        $query = DB::table('unidades');

        if ($authUsuario?->id_usuario) {
            $query->whereExists(function ($subquery) use ($authUsuario): void {
                $subquery
                    ->selectRaw('1')
                    ->from('unidad_usuario')
                    ->whereColumn('unidad_usuario.id_unidad', 'unidades.id_unidad')
                    ->where('unidad_usuario.id_usuario', $authUsuario->id_usuario);
            });
        }

        return $query;
    }
}