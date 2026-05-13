<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnidadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $authUsuario = $request->attributes->get('auth_usuario');
        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $sortBy = (string) $request->query('sort_by', 'id_unidad');
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc'));
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $sortableColumns = [
            'id_unidad' => 'unidades.id_unidad',
            'nombre_unidad' => 'unidades.nombre_unidad',
            'estado' => 'estados.nombre_estado',
            'uactip_unidad' => 'unidades.uactip_unidad',
            'ip_unidad' => 'unidades.ip_unidad',
        ];

        $sortColumn = $sortableColumns[$sortBy] ?? $sortableColumns['id_unidad'];

        $query = Unidad::query()
            ->leftJoin('estados', 'estados.id_estado', '=', 'unidades.id_estado')
            ->select([
                'unidades.id_unidad',
                'unidades.nombre_unidad',
                'unidades.uactip_unidad',
                'unidades.ip_unidad',
                'estados.nombre_estado as estado',
            ]);

        if ($authUsuario?->id_usuario) {
            $query->whereExists(function ($subquery) use ($authUsuario): void {
                $subquery
                    ->selectRaw('1')
                    ->from('unidad_usuario')
                    ->whereColumn('unidad_usuario.id_unidad', 'unidades.id_unidad')
                    ->where('unidad_usuario.id_usuario', $authUsuario->id_usuario);
            });
        }

        if ($search !== '') {
            $query->where(function ($subquery) use ($search): void {
                $subquery
                    ->where('unidades.id_unidad', 'like', "%{$search}%")
                    ->orWhere('unidades.nombre_unidad', 'like', "%{$search}%")
                    ->orWhere('estados.nombre_estado', 'like', "%{$search}%")
                    ->orWhere('unidades.uactip_unidad', 'like', "%{$search}%")
                    ->orWhere('unidades.ip_unidad', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortColumn, $sortDir);

        if ($sortColumn !== 'unidades.id_unidad') {
            $query->orderByDesc('unidades.id_unidad');
        }

        $unidades = $query
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($unidades);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre_unidad' => ['required', 'string', 'max:150'],
            'id_estado' => ['required', 'integer', 'exists:estados,id_estado'],
            'ciudad' => ['required', 'string', 'max:255'],
            'id_zona' => ['required', 'integer', 'exists:zonas,id_zona'],
            'id_region' => ['required', 'integer', 'exists:regiones,id_region'],
            'fapertura_unidad' => ['required', 'date'],
            'telefono_unidad' => ['required', 'string', 'max:25'],
            'id_tipounidad' => ['required', 'integer', 'exists:tipos_unidad,id_tipounidad'],
            'status_unidad' => ['required', 'integer', 'between:0,1'],
            'alcancepedido_unidad' => ['required', 'integer', 'min:0'],
            'clave_unidad' => ['required', 'string', 'max:80'],
            'ip_unidad' => ['nullable', 'ip'],
            'uactip_unidad' => ['nullable', 'date'],
        ]);

        $unidad = Unidad::create([
            'nombre_unidad' => $validated['nombre_unidad'],
            'id_estado' => $validated['id_estado'],
            'ciudad' => $validated['ciudad'],
            'id_zona' => $validated['id_zona'],
            'id_region' => $validated['id_region'],
            'fapertura_unidad' => $validated['fapertura_unidad'],
            'telefono_unidad' => $validated['telefono_unidad'],
            'id_tipounidad' => $validated['id_tipounidad'],
            'status_unidad' => $validated['status_unidad'],
            'alcancepedido_unidad' => $validated['alcancepedido_unidad'],
            'clave_unidad' => $validated['clave_unidad'],
            'uactip_unidad' => $validated['uactip_unidad'] ?? null,
            'ip_unidad' => $validated['ip_unidad'] ?? null,
        ]);

        return response()->json([
            'message' => 'Unidad registrada correctamente.',
            'data' => $unidad,
        ], 201);
    }

    public function show(Request $request, int $unidadId): JsonResponse
    {
        $unidad = $this->resolveUnidadForRequest($request, $unidadId);

        if (! $unidad) {
            return response()->json([
                'message' => 'Unidad no encontrada o sin acceso.',
            ], 404);
        }

        return response()->json($unidad);
    }

    public function update(Request $request, int $unidadId): JsonResponse
    {
        $unidad = $this->resolveUnidadForRequest($request, $unidadId);

        if (! $unidad) {
            return response()->json([
                'message' => 'Unidad no encontrada o sin acceso.',
            ], 404);
        }

        $validated = $request->validate([
            'nombre_unidad' => ['required', 'string', 'max:150'],
            'id_estado' => ['required', 'integer', 'exists:estados,id_estado'],
            'ciudad' => ['required', 'string', 'max:255'],
            'id_zona' => ['required', 'integer', 'exists:zonas,id_zona'],
            'id_region' => ['required', 'integer', 'exists:regiones,id_region'],
            'fapertura_unidad' => ['required', 'date'],
            'telefono_unidad' => ['required', 'string', 'max:25'],
            'id_tipounidad' => ['required', 'integer', 'exists:tipos_unidad,id_tipounidad'],
            'status_unidad' => ['required', 'integer', 'between:0,1'],
            'alcancepedido_unidad' => ['required', 'integer', 'min:0'],
            'clave_unidad' => ['required', 'string', 'max:80'],
            'ip_unidad' => ['nullable', 'ip'],
            'uactip_unidad' => ['nullable', 'date'],
        ]);

        $unidad->fill([
            'nombre_unidad' => $validated['nombre_unidad'],
            'id_estado' => $validated['id_estado'],
            'ciudad' => $validated['ciudad'],
            'id_zona' => $validated['id_zona'],
            'id_region' => $validated['id_region'],
            'fapertura_unidad' => $validated['fapertura_unidad'],
            'telefono_unidad' => $validated['telefono_unidad'],
            'id_tipounidad' => $validated['id_tipounidad'],
            'status_unidad' => $validated['status_unidad'],
            'alcancepedido_unidad' => $validated['alcancepedido_unidad'],
            'clave_unidad' => $validated['clave_unidad'],
            'uactip_unidad' => $validated['uactip_unidad'] ?? null,
            'ip_unidad' => $validated['ip_unidad'] ?? null,
        ]);
        $unidad->save();

        return response()->json([
            'message' => 'Unidad actualizada correctamente.',
            'data' => $unidad,
        ]);
    }

    public function usuarios(Request $request, int $unidadId): JsonResponse
    {
        $unidad = $this->resolveUnidadForRequest($request, $unidadId);

        if (! $unidad) {
            return response()->json([
                'message' => 'Unidad no encontrada o sin acceso.',
            ], 404);
        }

        $usuarios = DB::table('unidad_usuario')
            ->join('usuarios', 'usuarios.id_usuario', '=', 'unidad_usuario.id_usuario')
            ->leftJoin('autoridades', 'autoridades.id_autoridad', '=', 'usuarios.id_autoridad')
            ->where('unidad_usuario.id_unidad', $unidad->id_unidad)
            ->orderBy('usuarios.nombres_usuario')
            ->orderBy('usuarios.apellidos_usuario')
            ->get([
                'usuarios.id_usuario',
                'usuarios.uid_usuario',
                'usuarios.nombres_usuario',
                'usuarios.apellidos_usuario',
                'autoridades.descripcion_autoridad as autoridad',
            ])
            ->map(fn ($usuario) => [
                'id_usuario' => $usuario->id_usuario,
                'uid_usuario' => $usuario->uid_usuario,
                'nombre' => trim($usuario->nombres_usuario.' '.$usuario->apellidos_usuario),
                'autoridad' => $usuario->autoridad,
            ])
            ->values();

        return response()->json($usuarios);
    }

    public function addUsuario(Request $request, int $unidadId): JsonResponse
    {
        $unidad = $this->resolveUnidadForRequest($request, $unidadId);

        if (! $unidad) {
            return response()->json([
                'message' => 'Unidad no encontrada o sin acceso.',
            ], 404);
        }

        $validated = $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuarios,id_usuario'],
        ]);

        $usuario = Usuario::query()->find($validated['id_usuario']);

        if (! $usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $unidad->usuarios()->syncWithoutDetaching([$usuario->id_usuario]);

        return response()->json([
            'message' => 'Usuario relacionado a la unidad correctamente.',
        ], 201);
    }

    public function removeUsuario(Request $request, int $unidadId, int $usuarioId): JsonResponse
    {
        $unidad = $this->resolveUnidadForRequest($request, $unidadId);

        if (! $unidad) {
            return response()->json([
                'message' => 'Unidad no encontrada o sin acceso.',
            ], 404);
        }

        $unidad->usuarios()->detach([$usuarioId]);

        return response()->json([
            'message' => 'Usuario eliminado de la unidad correctamente.',
        ]);
    }

    public function altaUsuarioTienda(Request $request, int $unidadId, int $usuarioId): JsonResponse
    {
        $unidad = $this->resolveUnidadForRequest($request, $unidadId);

        if (! $unidad) {
            return response()->json([
                'message' => 'Unidad no encontrada o sin acceso.',
            ], 404);
        }

        $usuario = Usuario::query()->find($usuarioId);

        if (! $usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $host = trim((string) ($unidad->ip_unidad ?? ''));

        if ($host === '') {
            return response()->json([
                'message' => 'La unidad no tiene IP configurada para alta en tienda.',
            ], 422);
        }

        $mysqlConfig = config('database.connections.mysql');
        $connectionName = 'mysql_unidad_tienda_runtime';
        $databaseName = (string) (env('UNIDAD_STORE_DB_DATABASE') ?: ($mysqlConfig['database'] ?? 'app'));

        config([
            "database.connections.{$connectionName}" => array_merge($mysqlConfig, [
                'host' => $host,
                'database' => $databaseName,
            ]),
        ]);

        DB::purge($connectionName);
        $tiendaDb = DB::connection($connectionName);

        try {
            if (! $tiendaDb->getSchemaBuilder()->hasTable('usuario')) {
                return response()->json([
                    'message' => 'La base de datos de la unidad no tiene la tabla usuario.',
                ], 422);
            }

            $tiendaDb->table('usuario')->updateOrInsert(
                ['id_usuario' => $usuario->id_usuario],
                [
                    'uid_usuario' => $usuario->uid_usuario,
                    'nombres_usuario' => $usuario->nombres_usuario,
                    'apellidos_usuario' => $usuario->apellidos_usuario,
                    'telefono_usuario' => $usuario->telefono_usuario,
                    'email_usuario' => $usuario->email_usuario,
                    'id_autoridad' => $usuario->id_autoridad,
                    'vigencia_usuario' => $usuario->vigencia_usuario ? 1 : 0,
                ]
            );

            return response()->json([
                'message' => 'Usuario dado de alta en la tienda correctamente.',
                'data' => [
                    'host' => $host,
                    'database' => $databaseName,
                    'tabla' => 'usuario',
                    'id_usuario' => $usuario->id_usuario,
                ],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'No se pudo ejecutar el alta en tienda: '.$exception->getMessage(),
            ], 500);
        } finally {
            DB::disconnect($connectionName);
        }
    }

    public function estados(): JsonResponse
    {
        $estados = DB::table('estados')
            ->select(['id_estado', 'nombre_estado'])
            ->orderBy('id_estado')
            ->get();

        return response()->json($estados);
    }

    public function zonas(): JsonResponse
    {
        $zonas = DB::table('zonas')
            ->select(['id_zona', 'nombre_zona'])
            ->orderBy('id_zona')
            ->get();

        return response()->json($zonas);
    }

    public function regiones(): JsonResponse
    {
        $regiones = DB::table('regiones')
            ->select(['id_region', 'nombre_region'])
            ->orderBy('id_region')
            ->get();

        return response()->json($regiones);
    }

    public function tiposUnidad(): JsonResponse
    {
        $tiposUnidad = DB::table('tipos_unidad')
            ->select(['id_tipounidad', 'nombre_tipounidad'])
            ->orderBy('id_tipounidad')
            ->get();

        return response()->json($tiposUnidad);
    }

    private function resolveUnidadForRequest(Request $request, int $unidadId): ?Unidad
    {
        $authUsuario = $request->attributes->get('auth_usuario');

        $query = Unidad::query()->where('id_unidad', $unidadId);

        if ($authUsuario?->id_usuario) {
            $query->whereExists(function ($subquery) use ($authUsuario): void {
                $subquery
                    ->selectRaw('1')
                    ->from('unidad_usuario')
                    ->whereColumn('unidad_usuario.id_unidad', 'unidades.id_unidad')
                    ->where('unidad_usuario.id_usuario', $authUsuario->id_usuario);
            });
        }

        return $query->first();
    }
}
