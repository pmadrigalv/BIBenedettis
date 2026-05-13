<?php

use App\Http\Controllers\ActualizacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $databaseStatus = 'ok';

    try {
        DB::select('select 1');
    } catch (Throwable $exception) {
        $databaseStatus = 'error';
    }

    return response()->json([
        'app' => config('app.name'),
        'laravel' => app()->version(),
        'php' => PHP_VERSION,
        'database' => [
            'connection' => config('database.default'),
            'status' => $databaseStatus,
        ],
        'timestamp' => now()->toIso8601String(),
    ], $databaseStatus === 'ok' ? 200 : 500);
});

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth.usuario')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/catalogos/estados', [UnidadController::class, 'estados']);
    Route::get('/catalogos/zonas', [UnidadController::class, 'zonas']);
    Route::get('/catalogos/regiones', [UnidadController::class, 'regiones']);
    Route::get('/catalogos/tipos-unidad', [UnidadController::class, 'tiposUnidad']);
    Route::get('/catalogos/autoridades', [UsuarioController::class, 'autoridades']);
    Route::get('/catalogos/usuarios', [UsuarioController::class, 'usuariosCatalogo']);
    Route::get('/catalogos/unidades', [UsuarioController::class, 'unidadesCatalogo']);
    Route::get('/catalogos/usuarios-sistemas', [UsuarioController::class, 'usuariosSistemas']);
    Route::get('/catalogos/solicitantes', [UsuarioController::class, 'solicitantes']);
    Route::get('/catalogos/usuarios/{usuarioId}/unidades', [UsuarioController::class, 'unidadesPorUsuario']);
    Route::get('/unidades', [UnidadController::class, 'index']);
    Route::post('/unidades', [UnidadController::class, 'store']);
    Route::get('/unidades/{unidadId}', [UnidadController::class, 'show']);
    Route::put('/unidades/{unidadId}', [UnidadController::class, 'update']);
    Route::get('/unidades/{unidadId}/usuarios', [UnidadController::class, 'usuarios']);
    Route::post('/unidades/{unidadId}/usuarios', [UnidadController::class, 'addUsuario']);
    Route::delete('/unidades/{unidadId}/usuarios/{usuarioId}', [UnidadController::class, 'removeUsuario']);
    Route::post('/unidades/{unidadId}/usuarios/{usuarioId}/alta-tienda', [UnidadController::class, 'altaUsuarioTienda']);
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::get('/usuarios/{usuarioId}', [UsuarioController::class, 'show']);
    Route::put('/usuarios/{usuarioId}', [UsuarioController::class, 'update']);
    Route::get('/usuarios/{usuarioId}/unidades', [UsuarioController::class, 'unidades']);
    Route::post('/usuarios/{usuarioId}/unidades', [UsuarioController::class, 'addUnidad']);
    Route::delete('/usuarios/{usuarioId}/unidades/{unidadId}', [UsuarioController::class, 'removeUnidad']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::put('/tickets/{ticket}/asignar-tecnico', [TicketController::class, 'assignTecnico']);
    Route::get('/actualizaciones', [ActualizacionController::class, 'index']);
    Route::post('/actualizaciones', [ActualizacionController::class, 'store']);

    // ── KPIs ─────────────────────────────────────────────────────────────
    Route::get('/kpis/vta-dia-dia', [KpiController::class, 'vtaDiaDia']);
    Route::get('/kpis/native/vta-pizzas', [KpiController::class, 'nativeVtaPizzas']);
    Route::get('/kpis/native/vta-adicionales', [KpiController::class, 'nativeVtaAdicionales']);
    Route::get('/kpis/native/vta-orilla', [KpiController::class, 'nativeVtaOrilla']);
    Route::get('/kpis/native/rpt-vtas', [KpiController::class, 'nativeRptVtas']);
    Route::get('/kpis/native/rpt-malas-ordenes', [KpiController::class, 'nativeRptMalasOrdenes']);
    Route::get('/kpis/rpt-dia', [KpiController::class, 'rptDia']);
    Route::get('/kpis/rpt-rango', [KpiController::class, 'rptRango']);
});