<?php

namespace App\Http\Controllers;

use App\Models\Actualizacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActualizacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q       = $request->query('q', '');
        $perPage = (int) $request->query('per_page', 15);
        $sortBy  = $request->query('sort_by', 'id');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowed = ['id', 'titulo', 'version', 'fecha_publicacion', 'created_at'];
        if (! in_array($sortBy, $allowed)) {
            $sortBy = 'id';
        }

        $query = Actualizacion::query()
            ->when($q, fn($qb) => $qb->where('titulo', 'like', "%{$q}%")
                ->orWhere('descripcion', 'like', "%{$q}%"))
            ->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data'         => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'            => 'required|string|max:255',
            'descripcion'       => 'nullable|string',
            'version'           => 'nullable|string|max:50',
            'fecha_publicacion' => 'nullable|date',
        ]);

        $actualizacion = Actualizacion::create($data);

        return response()->json($actualizacion, 201);
    }
}
