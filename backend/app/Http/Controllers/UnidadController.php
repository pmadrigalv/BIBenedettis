<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
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
}
