<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    private const SISTEMAS_AUTORIDAD_IDS = [10, 85, 94, 95, 96, 97, 98];

    public function index(Request $request): JsonResponse
    {
        $authUsuario = $request->attributes->get('auth_usuario');
        $q       = $request->query('q', '');
        $perPage = (int) $request->query('per_page', 15);
        $sortBy  = $request->query('sort_by', 'id');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $autoridadDescripcion = null;
        if ($authUsuario?->id_autoridad) {
            $autoridadDescripcion = DB::table('autoridades')
                ->where('id_autoridad', $authUsuario->id_autoridad)
                ->value('descripcion_autoridad');
        }

        $isSistemas = in_array((int) ($authUsuario?->id_autoridad ?? 0), self::SISTEMAS_AUTORIDAD_IDS, true);

        $allowed = ['id', 'titulo', 'estado', 'prioridad', 'fecha', 'created_at'];
        if (! in_array($sortBy, $allowed)) {
            $sortBy = 'id';
        }

        $query = Ticket::query()
            ->leftJoin('usuarios', 'usuarios.id_usuario', '=', 'tickets.usuario_id')
            ->leftJoin('usuarios as tecnicos', 'tecnicos.id_usuario', '=', 'tickets.tecnico_id')
            ->leftJoin('unidades', 'unidades.id_unidad', '=', 'tickets.unidad_id')
            ->select([
                'tickets.id',
                'tickets.titulo',
                'tickets.descripcion',
                'tickets.estado',
                'tickets.prioridad',
                'tickets.usuario_id',
                'tickets.tecnico_id',
                'tickets.unidad_id',
                'tickets.fecha',
                'tickets.imagenes',
                'tickets.archivo_adjunto',
                'tickets.created_at',
                'tickets.updated_at',
                'usuarios.nombres_usuario',
                'usuarios.apellidos_usuario',
                'tecnicos.nombres_usuario as tecnico_nombres_usuario',
                'tecnicos.apellidos_usuario as tecnico_apellidos_usuario',
                'unidades.nombre_unidad',
            ])
            ->when($authUsuario?->id_usuario && ! $isSistemas, fn($qb) => $qb->where('tickets.usuario_id', $authUsuario->id_usuario))
            ->when($q, fn($qb) => $qb->where(function ($subquery) use ($q): void {
                $subquery->where('tickets.titulo', 'like', "%{$q}%")
                    ->orWhere('tickets.descripcion', 'like', "%{$q}%")
                    ->orWhere('usuarios.nombres_usuario', 'like', "%{$q}%")
                    ->orWhere('usuarios.apellidos_usuario', 'like', "%{$q}%");
            }))
            ->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function ($ticket) {
            $imagenes = is_array($ticket->imagenes) ? $ticket->imagenes : (json_decode((string) $ticket->imagenes, true) ?: []);

            return [
                'id' => $ticket->id,
                'titulo' => $ticket->titulo,
                'descripcion' => $ticket->descripcion,
                'estado' => $ticket->estado,
                'prioridad' => $ticket->prioridad,
                'usuario_id' => $ticket->usuario_id,
                'usuario_nombre' => trim(($ticket->nombres_usuario ?? '').' '.($ticket->apellidos_usuario ?? '')) ?: null,
                'tecnico_id' => $ticket->tecnico_id,
                'tecnico_nombre' => trim(($ticket->tecnico_nombres_usuario ?? '').' '.($ticket->tecnico_apellidos_usuario ?? '')) ?: null,
                'unidad_id' => $ticket->unidad_id,
                'unidad_nombre' => $ticket->nombre_unidad,
                'fecha' => $ticket->fecha,
                'imagenes' => $imagenes,
                'imagenes_urls' => collect($imagenes)
                    ->map(fn ($ruta) => Storage::disk('public')->url($ruta))
                    ->values(),
                'archivo_adjunto' => $ticket->archivo_adjunto,
                'archivo_url' => $ticket->archivo_adjunto
                    ? Storage::disk('public')->url($ticket->archivo_adjunto)
                    : null,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ];
        })->values();

        return response()->json([
            'data'         => $items,
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $authUsuario = $request->attributes->get('auth_usuario');
        $data = $request->validate([
            'titulo'      => 'required|string|max:255',
            'fecha'       => 'required|date',
            'descripcion' => 'nullable|string',
            'estado'      => 'nullable|in:abierto,en_proceso,resuelto,cerrado',
            'prioridad'   => 'nullable|in:baja,media,alta,urgente',
            'usuario_id'  => 'nullable|integer|exists:usuarios,id_usuario',
            'unidad_id'   => 'nullable|integer|exists:unidades,id_unidad',
            'imagenes'    => 'nullable|array',
            'imagenes.*'  => 'file|image|max:5120',
            'archivo'     => 'nullable|file|max:10240',
        ]);

        $autoridadDescripcion = null;
        if ($authUsuario?->id_autoridad) {
            $autoridadDescripcion = DB::table('autoridades')
                ->where('id_autoridad', $authUsuario->id_autoridad)
                ->value('descripcion_autoridad');
        }

        $isSistemas = in_array((int) ($authUsuario?->id_autoridad ?? 0), self::SISTEMAS_AUTORIDAD_IDS, true);
        $isRootOrGerenteSistemas = $this->isRootOrGerenteSistemas($autoridadDescripcion);

        if (! $isSistemas) {
            if (! $authUsuario?->id_usuario) {
                return response()->json([
                    'message' => 'No autenticado.',
                ], 401);
            }

            $data['usuario_id'] = (int) $authUsuario->id_usuario;
        }

        if ($isSistemas && empty($data['usuario_id'])) {
            return response()->json([
                'message' => 'Debe seleccionar un solicitante.',
                'errors'  => ['usuario_id' => ['El solicitante es obligatorio.']],
            ], 422);
        }

        $solicitanteAutoridadId = DB::table('usuarios')
            ->where('usuarios.id_usuario', $data['usuario_id'])
            ->value('usuarios.id_autoridad');

        $solicitanteEsSistemas = in_array((int) ($solicitanteAutoridadId ?? 0), self::SISTEMAS_AUTORIDAD_IDS, true);
        if ($solicitanteEsSistemas && ! $isRootOrGerenteSistemas) {
            return response()->json([
                'message' => 'Solo Root o Gerente de Sistemas puede asignar tickets a usuarios de Sistemas.',
                'errors'  => ['usuario_id' => ['No tienes permiso para asignar a un usuario de Sistemas.']],
            ], 422);
        }

        // Verificar que el solicitante esté vigente
        $solicitante = Usuario::find($data['usuario_id']);
        if ($solicitante && ! $solicitante->vigencia_usuario) {
            return response()->json([
                'message' => 'El solicitante seleccionado no está vigente.',
                'errors'  => ['usuario_id' => ['El usuario no tiene vigencia activa.']],
            ], 422);
        }

        if (! empty($data['unidad_id'])) {
            $unidadAsignada = DB::table('unidad_usuario')
                ->join('unidades', 'unidades.id_unidad', '=', 'unidad_usuario.id_unidad')
                ->where('unidad_usuario.id_usuario', $data['usuario_id'])
                ->where('unidad_usuario.id_unidad', $data['unidad_id'])
                ->where('unidades.status_unidad', 1)
                ->exists();

            if (! $unidadAsignada) {
                return response()->json([
                    'message' => 'La unidad seleccionada no pertenece al usuario o no está activa.',
                    'errors' => ['unidad_id' => ['La unidad no es válida para este solicitante.']],
                ], 422);
            }
        }

        $imagenes = [];
        if ($request->hasFile('imagenes')) {
            foreach ($request->file('imagenes') as $imagen) {
                $imagenes[] = $imagen->store('tickets/imagenes', 'public');
            }
        }

        $archivoAdjunto = null;
        if ($request->hasFile('archivo')) {
            $archivoAdjunto = $request->file('archivo')->store('tickets/archivos', 'public');
        }

        $data['imagenes'] = $imagenes;
        $data['archivo_adjunto'] = $archivoAdjunto;

        $ticket = Ticket::create($data);

        $ticket->load(['usuario', 'unidad']);

        return response()->json([
            'id' => $ticket->id,
            'titulo' => $ticket->titulo,
            'descripcion' => $ticket->descripcion,
            'estado' => $ticket->estado,
            'prioridad' => $ticket->prioridad,
            'usuario_id' => $ticket->usuario_id,
            'usuario_nombre' => $ticket->usuario
                ? trim($ticket->usuario->nombres_usuario.' '.$ticket->usuario->apellidos_usuario)
                : null,
            'tecnico_id' => $ticket->tecnico_id,
            'tecnico_nombre' => $ticket->tecnico
                ? trim($ticket->tecnico->nombres_usuario.' '.$ticket->tecnico->apellidos_usuario)
                : null,
            'unidad_id' => $ticket->unidad_id,
            'unidad_nombre' => $ticket->unidad?->nombre_unidad,
            'fecha' => $ticket->fecha,
            'imagenes' => $ticket->imagenes ?? [],
            'imagenes_urls' => collect($ticket->imagenes ?? [])->map(fn ($ruta) => Storage::disk('public')->url($ruta))->values(),
            'archivo_adjunto' => $ticket->archivo_adjunto,
            'archivo_url' => $ticket->archivo_adjunto ? Storage::disk('public')->url($ticket->archivo_adjunto) : null,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ], 201);
    }

    public function assignTecnico(Request $request, Ticket $ticket): JsonResponse
    {
        $authUsuario = $request->attributes->get('auth_usuario');
        $autoridadDescripcion = $this->getAuthAuthorityDescription($authUsuario?->id_autoridad);

        $isSistemas = in_array((int) ($authUsuario?->id_autoridad ?? 0), self::SISTEMAS_AUTORIDAD_IDS, true);
        $canManageAllTickets = $isSistemas || $this->isRootOrGerenteSistemas($autoridadDescripcion);
        $isOwnTicket = (int) ($ticket->usuario_id ?? 0) === (int) ($authUsuario?->id_usuario ?? 0);

        $canAssignTecnico = $canManageAllTickets || $isOwnTicket;

        if (! $canAssignTecnico) {
            return response()->json([
                'message' => 'No tienes permiso para asignar técnico.',
            ], 403);
        }

        $data = $request->validate([
            'tecnico_id' => 'required|integer|exists:usuarios,id_usuario',
        ]);

        $tecnicoAutoridadId = DB::table('usuarios')
            ->where('usuarios.id_usuario', $data['tecnico_id'])
            ->value('usuarios.id_autoridad');

        if (! in_array((int) ($tecnicoAutoridadId ?? 0), self::SISTEMAS_AUTORIDAD_IDS, true)) {
            return response()->json([
                'message' => 'El usuario seleccionado no pertenece a Sistemas.',
                'errors' => ['tecnico_id' => ['Debes seleccionar un usuario con autoridad Sistemas.']],
            ], 422);
        }

        $tecnico = Usuario::find($data['tecnico_id']);
        if ($tecnico && ! $tecnico->vigencia_usuario) {
            return response()->json([
                'message' => 'El técnico seleccionado no está vigente.',
                'errors' => ['tecnico_id' => ['El técnico no tiene vigencia activa.']],
            ], 422);
        }

        $ticket->tecnico_id = (int) $data['tecnico_id'];
        $ticket->save();
        $ticket->load(['usuario', 'unidad', 'tecnico']);

        return response()->json([
            'id' => $ticket->id,
            'titulo' => $ticket->titulo,
            'descripcion' => $ticket->descripcion,
            'estado' => $ticket->estado,
            'prioridad' => $ticket->prioridad,
            'usuario_id' => $ticket->usuario_id,
            'usuario_nombre' => $ticket->usuario
                ? trim($ticket->usuario->nombres_usuario.' '.$ticket->usuario->apellidos_usuario)
                : null,
            'tecnico_id' => $ticket->tecnico_id,
            'tecnico_nombre' => $ticket->tecnico
                ? trim($ticket->tecnico->nombres_usuario.' '.$ticket->tecnico->apellidos_usuario)
                : null,
            'unidad_id' => $ticket->unidad_id,
            'unidad_nombre' => $ticket->unidad?->nombre_unidad,
            'fecha' => $ticket->fecha,
            'imagenes' => $ticket->imagenes ?? [],
            'imagenes_urls' => collect($ticket->imagenes ?? [])->map(fn ($ruta) => Storage::disk('public')->url($ruta))->values(),
            'archivo_adjunto' => $ticket->archivo_adjunto,
            'archivo_url' => $ticket->archivo_adjunto ? Storage::disk('public')->url($ticket->archivo_adjunto) : null,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ]);
    }

    private function getAuthAuthorityDescription(?int $idAutoridad): ?string
    {
        if (! $idAutoridad) {
            return null;
        }

        return DB::table('autoridades')
            ->where('id_autoridad', $idAutoridad)
            ->value('descripcion_autoridad');
    }

    private function isRootOrGerenteSistemas(?string $autoridadDescripcion): bool
    {
        $role = strtolower(trim((string) $autoridadDescripcion));

        if ($role === 'root') {
            return true;
        }

        return str_contains($role, 'gerente') && str_contains($role, 'sistemas');
    }

}
