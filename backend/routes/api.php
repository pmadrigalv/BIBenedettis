<?php

use App\Http\Controllers\ActualizacionController;
use App\Http\Controllers\AuthController;
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
    Route::get('/catalogos/usuarios-sistemas', [UsuarioController::class, 'usuariosSistemas']);
    Route::get('/catalogos/solicitantes', [UsuarioController::class, 'solicitantes']);
    Route::get('/catalogos/usuarios/{usuarioId}/unidades', [UsuarioController::class, 'unidadesPorUsuario']);
    Route::get('/unidades', [UnidadController::class, 'index']);
    Route::post('/unidades', [UnidadController::class, 'store']);
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::put('/tickets/{ticket}/asignar-tecnico', [TicketController::class, 'assignTecnico']);
    Route::get('/actualizaciones', [ActualizacionController::class, 'index']);
    Route::post('/actualizaciones', [ActualizacionController::class, 'store']);
});