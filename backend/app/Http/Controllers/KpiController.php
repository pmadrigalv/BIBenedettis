<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiController extends Controller
{
    public function nativeVtaPizzas(Request $request): JsonResponse
    {
        [$inicio, $fin] = $this->resolvePeriod($request);
        $idUnidad = $this->resolveIdUnidad($request);
        $entries = $this->buildDayEntries($inicio, $fin);

        $diosActual = array_column($entries, 'dio_actual');
        $diosPrev = array_column($entries, 'dio_prev');

        $fxActual = $this->ventasPorDio($diosActual, [], $idUnidad);
        $fxPrev = $this->ventasPorDio($diosPrev, [], $idUnidad);

        $pto = collect();
        if (Schema::hasTable('presupuesto')) {
            $pto = DB::table('presupuesto')
                ->whereIn('id_diaoperativo', $diosActual)
                ->groupBy('id_diaoperativo')
                ->select('id_diaoperativo', DB::raw('SUM(total_ppo) as total_pto'))
                ->pluck('total_pto', 'id_diaoperativo');
        }

        $rows = [];
        foreach ($entries as $entry) {
            $prev = (float) ($fxPrev[$entry['dio_prev']] ?? 0);
            $actual = (float) ($fxActual[$entry['dio_actual']] ?? 0);
            $presupuesto = (float) ($pto[$entry['dio_actual']] ?? 0);

            $rows[] = [
                'dimension' => $entry['label'],
                'fx_prev' => $prev,
                'fx_actual' => $actual,
                'pct_ap' => $prev > 0 ? round((($actual - $prev) / $prev) * 100, 1) : null,
                'variacion' => $actual - $prev,
                'pto' => $presupuesto,
                'pct_aa' => $presupuesto > 0 ? round((($actual - $presupuesto) / $presupuesto) * 100, 1) : null,
                'variacion_pto' => $actual - $presupuesto,
            ];
        }

        return response()->json($this->buildTabularResponse(
            'vta-pizzas',
            'VTA PIZZAS',
            $inicio,
            $fin,
            $rows,
            [
                ['key' => 'dimension', 'label' => 'DIA', 'type' => 'text'],
                ['key' => 'fx_prev', 'label' => 'FX ' . ($inicio->year - 1), 'type' => 'currency'],
                ['key' => 'fx_actual', 'label' => 'FX ' . $inicio->year, 'type' => 'currency'],
                ['key' => 'pct_ap', 'label' => '% AP', 'type' => 'percent'],
                ['key' => 'variacion', 'label' => 'VARIACION $', 'type' => 'currency'],
                ['key' => 'pto', 'label' => 'PTO', 'type' => 'currency'],
                ['key' => 'pct_aa', 'label' => '% AA', 'type' => 'percent'],
                ['key' => 'variacion_pto', 'label' => 'VARIACION PTO', 'type' => 'currency'],
            ],
            ['fx_prev', 'fx_actual', 'variacion', 'pto', 'variacion_pto']
        ));
    }

    public function nativeVtaAdicionales(Request $request): JsonResponse
    {
        [$inicio, $fin] = $this->resolvePeriod($request);
        $idUnidad = $this->resolveIdUnidad($request);
        $entries = $this->buildDayEntries($inicio, $fin);

        $diosActual = array_column($entries, 'dio_actual');
        $diosPrev = array_column($entries, 'dio_prev');

        $adicionalesActual = $this->adicionalesPorDio($diosActual, $idUnidad);
        $adicionalesPrev = $this->adicionalesPorDio($diosPrev, $idUnidad);

        $rows = [];
        foreach ($entries as $entry) {
            $prev = (float) ($adicionalesPrev[$entry['dio_prev']] ?? 0);
            $actual = (float) ($adicionalesActual[$entry['dio_actual']] ?? 0);

            $rows[] = [
                'dimension' => $entry['label'],
                'fx_prev' => $prev,
                'fx_actual' => $actual,
                'pct_ap' => $prev > 0 ? round((($actual - $prev) / $prev) * 100, 1) : null,
                'variacion' => $actual - $prev,
            ];
        }

        return response()->json($this->buildTabularResponse(
            'vta-adicionales',
            'VTA ADICIONALES',
            $inicio,
            $fin,
            $rows,
            [
                ['key' => 'dimension', 'label' => 'DIA', 'type' => 'text'],
                ['key' => 'fx_prev', 'label' => 'FX ' . ($inicio->year - 1), 'type' => 'currency'],
                ['key' => 'fx_actual', 'label' => 'FX ' . $inicio->year, 'type' => 'currency'],
                ['key' => 'pct_ap', 'label' => '% AP', 'type' => 'percent'],
                ['key' => 'variacion', 'label' => 'VARIACION $', 'type' => 'currency'],
            ],
            ['fx_prev', 'fx_actual', 'variacion']
        ));
    }

    public function nativeVtaOrilla(Request $request): JsonResponse
    {
        [$inicio, $fin] = $this->resolvePeriod($request);
        $idUnidad = $this->resolveIdUnidad($request);

        // Convert date range to diaoperativo format (YYYYDDD)
        $dioActualInicio = $this->dateToDiaOperativo($inicio);
        $dioActualFin    = $this->dateToDiaOperativo($fin);

        // Year-ago comparison: -364 days (preserves same day of week, BASE/ convention)
        $prevInicio = $inicio->copy()->subDays(364);
        $prevFin    = $fin->copy()->subDays(364);
        $dioPrevInicio = $this->dateToDiaOperativo($prevInicio);
        $dioPrevFin    = $this->dateToDiaOperativo($prevFin);

        $recetas = [
            82  => 'ORILLA QUESO',
            590 => 'ORILLA QUESO HABANERO',
            591 => 'ORILLA QUESO CHIPOTLE',
            592 => 'ORILLA QUESO BBQ',
        ];

        $actualPorReceta = $this->orillaPorReceta($dioActualInicio, $dioActualFin, array_keys($recetas), $idUnidad);
        $prevPorReceta   = $this->orillaPorReceta($dioPrevInicio, $dioPrevFin, array_keys($recetas), $idUnidad);

        $rows = [];
        foreach ($recetas as $idReceta => $nombreReceta) {
            $prev = (float) ($prevPorReceta[$idReceta] ?? 0);
            $actual = (float) ($actualPorReceta[$idReceta] ?? 0);
            $rows[] = [
                'dimension' => $nombreReceta,
                'fx_prev' => $prev,
                'fx_actual' => $actual,
                'pct_ap' => $prev > 0 ? round((($actual - $prev) / $prev) * 100, 1) : null,
                'variacion' => $actual - $prev,
            ];
        }

        return response()->json($this->buildTabularResponse(
            'vta-orilla',
            'VTA ORILLA',
            $inicio,
            $fin,
            $rows,
            [
                ['key' => 'dimension', 'label' => 'ORILLA', 'type' => 'text'],
                ['key' => 'fx_prev', 'label' => 'FX ' . ($inicio->year - 1), 'type' => 'number'],
                ['key' => 'fx_actual', 'label' => 'FX ' . $inicio->year, 'type' => 'number'],
                ['key' => 'pct_ap', 'label' => '% AP', 'type' => 'percent'],
                ['key' => 'variacion', 'label' => 'VARIACION', 'type' => 'number'],
            ],
            ['fx_prev', 'fx_actual', 'variacion']
        ));
    }

    public function nativeRptVtas(Request $request): JsonResponse
    {
        [$inicio, $fin] = $this->resolvePeriod($request);
        $idUnidad = $this->resolveIdUnidad($request);
        $entries = $this->buildDayEntries($inicio, $fin);

        $diosActual = array_column($entries, 'dio_actual');
        $diosPrev = array_column($entries, 'dio_prev');

        $actualPorUnidad = $this->ventasPorUnidad($diosActual, $idUnidad);
        $prevPorUnidad = $this->ventasPorUnidad($diosPrev, $idUnidad);

        $ptoPorUnidad = collect();
        if (Schema::hasTable('presupuesto')) {
            $ptoPorUnidad = DB::table('presupuesto')
                ->whereIn('id_diaoperativo', $diosActual)
                ->groupBy('id_unidad')
                ->select('id_unidad', DB::raw('SUM(total_ppo) as total_pto'))
                ->pluck('total_pto', 'id_unidad');
        }

        $nombresUnidad = collect();
        if (Schema::hasTable('unidad')) {
            $nombresUnidad = DB::table('unidad')
                ->select('id_unidad', DB::raw("COALESCE(NULLIF(TRIM(nombre_unidad), ''), CONCAT('UNIDAD ', id_unidad)) AS nombre"))
                ->pluck('nombre', 'id_unidad');
        }

        $idsUnidades = array_values(array_unique(array_merge(
            array_map('intval', array_keys($actualPorUnidad->all())),
            array_map('intval', array_keys($prevPorUnidad->all())),
            array_map('intval', array_keys($ptoPorUnidad->all()))
        )));
        sort($idsUnidades);

        $rows = [];
        foreach ($idsUnidades as $idUnidad) {
            $prev = (float) ($prevPorUnidad[$idUnidad] ?? 0);
            $actual = (float) ($actualPorUnidad[$idUnidad] ?? 0);
            $pto = (float) ($ptoPorUnidad[$idUnidad] ?? 0);

            $rows[] = [
                'dimension' => (string) ($nombresUnidad[$idUnidad] ?? ('UNIDAD ' . $idUnidad)),
                'fx_prev' => $prev,
                'fx_actual' => $actual,
                'pct_ap' => $prev > 0 ? round((($actual - $prev) / $prev) * 100, 1) : null,
                'variacion' => $actual - $prev,
                'pto' => $pto,
                'pct_aa' => $pto > 0 ? round((($actual - $pto) / $pto) * 100, 1) : null,
                'variacion_pto' => $actual - $pto,
            ];
        }

        return response()->json($this->buildTabularResponse(
            'rpt-vtas',
            'RPT VTAS',
            $inicio,
            $fin,
            $rows,
            [
                ['key' => 'dimension', 'label' => 'UNIDAD', 'type' => 'text'],
                ['key' => 'fx_prev', 'label' => 'FX ' . ($inicio->year - 1), 'type' => 'currency'],
                ['key' => 'fx_actual', 'label' => 'FX ' . $inicio->year, 'type' => 'currency'],
                ['key' => 'pct_ap', 'label' => '% AP', 'type' => 'percent'],
                ['key' => 'variacion', 'label' => 'VARIACION $', 'type' => 'currency'],
                ['key' => 'pto', 'label' => 'PTO', 'type' => 'currency'],
                ['key' => 'pct_aa', 'label' => '% AA', 'type' => 'percent'],
                ['key' => 'variacion_pto', 'label' => 'VARIACION PTO', 'type' => 'currency'],
            ],
            ['fx_prev', 'fx_actual', 'variacion', 'pto', 'variacion_pto']
        ));
    }

    public function nativeRptMalasOrdenes(Request $request): JsonResponse
    {
        [$inicio, $fin] = $this->resolvePeriod($request);
        $idUnidad = $this->resolveIdUnidad($request);
        $entries = $this->buildDayEntries($inicio, $fin);

        $diosActual = array_column($entries, 'dio_actual');
        $diosPrev = array_column($entries, 'dio_prev');

        $actual = $this->malasOrdenesPorDio($diosActual, $idUnidad);
        $prev = $this->malasOrdenesPorDio($diosPrev, $idUnidad);

        $rows = [];
        foreach ($entries as $entry) {
            $actualBad = (float) ($actual[$entry['dio_actual']]['malas_ordenes'] ?? 0);
            $prevBad = (float) ($prev[$entry['dio_prev']]['malas_ordenes'] ?? 0);
            $actualTotal = (float) ($actual[$entry['dio_actual']]['total_ordenes'] ?? 0);

            $rows[] = [
                'dimension' => $entry['label'],
                'fx_prev' => $prevBad,
                'fx_actual' => $actualBad,
                'pct_ap' => $prevBad > 0 ? round((($actualBad - $prevBad) / $prevBad) * 100, 1) : null,
                'variacion' => $actualBad - $prevBad,
                'total_ordenes' => $actualTotal,
                'tasa_malas' => $actualTotal > 0 ? round(($actualBad / $actualTotal) * 100, 2) : null,
            ];
        }

        return response()->json($this->buildTabularResponse(
            'rpt-malas-ordenes',
            'RPT MALAS ORDENES',
            $inicio,
            $fin,
            $rows,
            [
                ['key' => 'dimension', 'label' => 'DIA', 'type' => 'text'],
                ['key' => 'fx_prev', 'label' => 'MALAS ' . ($inicio->year - 1), 'type' => 'number'],
                ['key' => 'fx_actual', 'label' => 'MALAS ' . $inicio->year, 'type' => 'number'],
                ['key' => 'pct_ap', 'label' => '% AP', 'type' => 'percent'],
                ['key' => 'variacion', 'label' => 'VARIACION', 'type' => 'number'],
                ['key' => 'total_ordenes', 'label' => 'ORDENES ACTUALES', 'type' => 'number'],
                ['key' => 'tasa_malas', 'label' => '% MALAS', 'type' => 'percent'],
            ],
            ['fx_prev', 'fx_actual', 'variacion', 'total_ordenes']
        ));
    }

    /**
     * Reporte: Ventas Día a Día
     *
     * Compara ventas reales (FX año actual vs FX año anterior) y presupuesto
     * para una semana de 7 días a partir de la fecha indicada.
     *
     * Query param:  fecha  (YYYY-MM-DD)  — primer día de la semana a analizar
     */
    public function vtaDiaDia(Request $request): JsonResponse
    {
        $request->validate(['fecha' => 'required|date_format:Y-m-d']);

        $inicio = Carbon::parse($request->input('fecha'))->startOfDay();
        $year   = (int) $inicio->format('Y');
        $prevY  = $year - 1;

        // Build arrays of diaoperativo for current and previous year (7 days)
        $diosActual   = [];
        $diosAnterior = [];
        $diasMeta     = [];

        for ($i = 0; $i < 7; $i++) {
            $dia        = $inicio->copy()->addDays($i);
            $dayOfYear  = (int) $dia->format('z') + 1; // format('z') is 0-based

            $dioActual   = $year  * 1000 + $dayOfYear;
            $dioAnterior = $prevY * 1000 + $dayOfYear;

            $diosActual[]   = $dioActual;
            $diosAnterior[] = $dioAnterior;

            $diasMeta[] = [
                'nombre'        => strtoupper($dia->locale('es')->isoFormat('dddd')),
                'fecha'         => $dia->format('d/m/Y'),
                'dio_actual'    => $dioActual,
                'dio_anterior'  => $dioAnterior,
            ];
        }

        // ── Sales aggregated by diaoperativo ───────────────────────────────
        $ventasActual   = $this->ventasPorDio($diosActual);
        $ventasAnterior = $this->ventasPorDio($diosAnterior);

        // ── Determine "iguales" vs "nuevas" units ─────────────────────────
        $unidadesAnterior = DB::table('vmx_res_ventas')
            ->whereIn('id_diaoperativo', $diosAnterior)
            ->distinct()
            ->pluck('id_unidad')
            ->flip();          // flip so we can use isset() for O(1) lookup

        // Sales for "iguales" units only (exist in prev year)
        $ventasActualIguales   = $this->ventasPorDio($diosActual,   $unidadesAnterior->keys()->all());
        $ventasAnteriorIguales = $this->ventasPorDio($diosAnterior, $unidadesAnterior->keys()->all());

        // Sales for "nuevas" units only
        $unidadesActual = DB::table('vmx_res_ventas')
            ->whereIn('id_diaoperativo', $diosActual)
            ->distinct()
            ->pluck('id_unidad');

        $unidadesNuevas = $unidadesActual->filter(fn ($u) => ! isset($unidadesAnterior[$u]))->values()->all();
        $ventasActualNuevas = $this->ventasPorDio($diosActual, $unidadesNuevas);

        // ── Presupuesto by diaoperativo ───────────────────────────────────
        $ptoRows = collect();

        if (Schema::hasTable('presupuesto')) {
            $ptoRows = DB::table('presupuesto')
                ->whereIn('id_diaoperativo', $diosActual)
                ->groupBy('id_diaoperativo')
                ->select('id_diaoperativo', DB::raw('SUM(total_ppo) as total_pto'))
                ->pluck('total_pto', 'id_diaoperativo');
        }

        // ── Build per-day rows ────────────────────────────────────────────
        $dias = [];

        foreach ($diasMeta as $meta) {
            $fxActual   = (float) ($ventasActualIguales[$meta['dio_actual']]   ?? 0);
            $fxAnterior = (float) ($ventasAnteriorIguales[$meta['dio_anterior']] ?? 0);
            $pto        = (float) ($ptoRows[$meta['dio_actual']] ?? 0);

            $dias[] = [
                'nombre'        => $meta['nombre'],
                'fecha'         => $meta['fecha'],
                'fx_anterior'   => $fxAnterior,
                'fx_actual'     => $fxActual,
                'pct_ap'        => $fxAnterior > 0 ? round(($fxActual - $fxAnterior) / $fxAnterior * 100, 1) : null,
                'variacion'     => $fxActual - $fxAnterior,
                'pto'           => $pto,
                'pct_aa'        => $pto > 0 ? round(($fxActual - $pto) / $pto * 100, 1) : null,
                'variacion_pto' => $fxActual - $pto,
            ];
        }

        // ── Totals ────────────────────────────────────────────────────────
        $totIgualActual   = (float) array_sum(array_column($dias, 'fx_actual'));
        $totIgualAnterior = (float) array_sum(array_column($dias, 'fx_anterior'));
        $totIgualPto      = (float) array_sum(array_column($dias, 'pto'));

        $totNueva = (float) $ventasActualNuevas->sum();

        $totActual   = $totIgualActual + $totNueva;
        $totAnterior = $totIgualAnterior;
        $totPto      = $totIgualPto;

        $totales = [
            'iguales' => [
                'fx_anterior'   => $totIgualAnterior,
                'fx_actual'     => $totIgualActual,
                'pct_ap'        => $totIgualAnterior > 0 ? round(($totIgualActual - $totIgualAnterior) / $totIgualAnterior * 100, 1) : null,
                'variacion'     => $totIgualActual - $totIgualAnterior,
                'pto'           => $totIgualPto,
                'pct_aa'        => $totIgualPto > 0 ? round(($totIgualActual - $totIgualPto) / $totIgualPto * 100, 1) : null,
                'variacion_pto' => $totIgualActual - $totIgualPto,
            ],
            'nuevas' => [
                'fx_anterior'   => null,
                'fx_actual'     => $totNueva,
                'pct_ap'        => null,
                'variacion'     => null,
                'pto'           => null,
                'pct_aa'        => null,
                'variacion_pto' => null,
            ],
            'total' => [
                'fx_anterior'   => $totAnterior,
                'fx_actual'     => $totActual,
                'pct_ap'        => $totAnterior > 0 ? round(($totActual - $totAnterior) / $totAnterior * 100, 1) : null,
                'variacion'     => $totActual - $totAnterior,
                'pto'           => $totPto,
                'pct_aa'        => $totPto > 0 ? round(($totActual - $totPto) / $totPto * 100, 1) : null,
                'variacion_pto' => $totActual - $totPto,
            ],
        ];

        // ── Build semana label ────────────────────────────────────────────
        $primerDia = Carbon::createFromFormat('d/m/Y', $diasMeta[0]['fecha'])->locale('es');
        $ultimoDia = Carbon::createFromFormat('d/m/Y', $diasMeta[6]['fecha'])->locale('es');
        $semanaLabel = sprintf(
            '%s %s a %s',
            strtoupper($diasMeta[0]['nombre']),
            $primerDia->isoFormat('D [de] MMMM [de] YYYY'),
            $ultimoDia->isoFormat('D [de] MMMM [de] YYYY')
        );

        return response()->json([
            'semana'     => $semanaLabel,
            'year'       => $year,
            'prev_year'  => $prevY,
            'dias'       => $dias,
            'totales'    => $totales,
        ]);
    }

    private function resolvePeriod(Request $request): array
    {
        $request->validate([
            'fecha' => 'sometimes|date_format:Y-m-d',
            'fecha_inicio' => 'sometimes|date_format:Y-m-d',
            'fecha_fin' => 'sometimes|date_format:Y-m-d',
        ]);

        $fecha = $request->query('fecha');
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        if (is_string($fecha) && $fecha !== '') {
            $inicio = Carbon::parse($fecha)->startOfDay();
            $fin = Carbon::parse($fecha)->startOfDay();
            return [$inicio, $fin];
        }

        if (is_string($fechaInicio) && $fechaInicio !== '' && is_string($fechaFin) && $fechaFin !== '') {
            $inicio = Carbon::parse($fechaInicio)->startOfDay();
            $fin = Carbon::parse($fechaFin)->startOfDay();

            if ($fin->lt($inicio)) {
                [$inicio, $fin] = [$fin, $inicio];
            }

            return [$inicio, $fin];
        }

        $fin = now()->startOfDay();
        $inicio = $fin->copy()->subDays(6);
        return [$inicio, $fin];
    }

    private function buildDayEntries(Carbon $inicio, Carbon $fin): array
    {
        $entries = [];
        $cursor = $inicio->copy();

        while ($cursor->lte($fin)) {
            $prev = $cursor->copy()->subYear();
            $entries[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => strtoupper($cursor->locale('es')->isoFormat('dddd')) . ' ' . $cursor->format('d/m/Y'),
                'dio_actual' => $this->dateToDiaOperativo($cursor),
                'dio_prev' => $this->dateToDiaOperativo($prev),
            ];
            $cursor->addDay();
        }

        return $entries;
    }

    private function dateToDiaOperativo(Carbon $date): int
    {
        return ((int) $date->format('Y') * 1000) + ((int) $date->format('z') + 1);
    }

    private function buildTabularResponse(
        string $report,
        string $title,
        Carbon $inicio,
        Carbon $fin,
        array $rows,
        array $columns,
        array $sumKeys
    ): array {
        $totals = [];
        foreach ($sumKeys as $key) {
            $totals[$key] = (float) array_sum(array_map(static fn ($row) => (float) ($row[$key] ?? 0), $rows));
        }

        $totals['dimension'] = 'TOTAL';

        return [
            'report' => $report,
            'title' => $title,
            'period' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
                'dias' => $inicio->diffInDays($fin) + 1,
            ],
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    private function adicionalesPorDio(array $dios, ?int $idUnidad = null): Collection
    {
        if (empty($dios)) {
            return collect();
        }

        if (!empty($this->missingTables(['vmx_diaoperativo', 'vmx_orden', 'vmx_producto']))) {
            return collect();
        }

        $q = DB::table('vmx_diaoperativo as d')
            ->join('vmx_orden as o', function ($join) {
                $join->on('o.id_unidad', '=', 'd.id_unidad')
                    ->whereRaw('o.id_orden BETWEEN d.oinicial_diaoperativo AND d.ofinal_diaoperativo');
            })
            ->join('vmx_producto as p', function ($join) {
                $join->on('p.id_unidad', '=', 'o.id_unidad')
                    ->on('p.id_orden', '=', 'o.id_orden');
            })
            ->whereIn('d.id_diaoperativo', $dios)
            ->where('p.esadicional_producto', 1);

        if ($idUnidad !== null) {
            $q->where('d.id_unidad', $idUnidad);
        }

        return $q->groupBy('d.id_diaoperativo')
            ->select('d.id_diaoperativo', DB::raw('SUM(p.precio_producto * p.cantidad_producto) as total'))
            ->pluck('total', 'd.id_diaoperativo');
    }

    private function orillaPorReceta(int $dioInicio, int $dioFin, array $idsReceta, ?int $idUnidad = null): Collection
    {
        if (empty($idsReceta)) {
            return collect();
        }

        if (!empty($this->missingTables(['vmx_diaoperativo', 'vmx_orden', 'vmx_producto', 'vmx_componente']))) {
            return collect();
        }

        if ($idUnidad !== null) {
            // Paso 1: obtener rango de órdenes de vmx_diaoperativo para la unidad y período
            $range = DB::table('vmx_diaoperativo')
                ->where('id_unidad', $idUnidad)
                ->whereBetween('id_diaoperativo', [$dioInicio, $dioFin])
                ->selectRaw('MIN(oinicial_diaoperativo) as min_orden, MAX(ofinal_diaoperativo) as max_orden')
                ->first();

            if (!$range || $range->min_orden === null) {
                return collect();
            }

            // Paso 2: contar orillas directamente con el rango de órdenes obtenido
            return DB::table('vmx_orden as o')
                ->join('vmx_producto as p', function ($join) {
                    $join->on('p.id_unidad', '=', 'o.id_unidad')
                        ->on('p.id_orden', '=', 'o.id_orden');
                })
                ->join('vmx_componente as c', function ($join) {
                    $join->on('c.id_unidad', '=', 'p.id_unidad')
                        ->on('c.id_orden', '=', 'p.id_orden')
                        ->on('c.id_producto', '=', 'p.id_producto');
                })
                ->where('o.id_unidad', $idUnidad)
                ->whereBetween('o.id_orden', [$range->min_orden, $range->max_orden])
                ->whereIn('c.id_receta', $idsReceta)
                ->groupBy('c.id_receta')
                ->select('c.id_receta', DB::raw('SUM(p.cantidad_producto) as total'))
                ->pluck('total', 'c.id_receta');
        }

        // Todas las unidades: JOIN con BETWEEN en id_diaoperativo
        return DB::table('vmx_diaoperativo as d')
            ->join('vmx_orden as o', function ($join) {
                $join->on('o.id_unidad', '=', 'd.id_unidad')
                    ->whereRaw('o.id_orden BETWEEN d.oinicial_diaoperativo AND d.ofinal_diaoperativo');
            })
            ->join('vmx_producto as p', function ($join) {
                $join->on('p.id_unidad', '=', 'o.id_unidad')
                    ->on('p.id_orden', '=', 'o.id_orden');
            })
            ->join('vmx_componente as c', function ($join) {
                $join->on('c.id_unidad', '=', 'p.id_unidad')
                    ->on('c.id_orden', '=', 'p.id_orden')
                    ->on('c.id_producto', '=', 'p.id_producto');
            })
            ->whereBetween('d.id_diaoperativo', [$dioInicio, $dioFin])
            ->whereIn('c.id_receta', $idsReceta)
            ->groupBy('c.id_receta')
            ->select('c.id_receta', DB::raw('SUM(p.cantidad_producto) as total'))
            ->pluck('total', 'c.id_receta');
    }

    private function ventasPorUnidad(array $dios, ?int $idUnidad = null): Collection
    {
        if (empty($dios)) {
            return collect();
        }

        if (!empty($this->missingTables(['vmx_res_ventas']))) {
            return collect();
        }

        $q = DB::table('vmx_res_ventas')
            ->whereIn('id_diaoperativo', $dios)
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127);

        if ($idUnidad !== null) {
            $q->where('id_unidad', $idUnidad);
        }

        return $q->groupBy('id_unidad')
            ->select('id_unidad', DB::raw('SUM(total_venta) as total'))
            ->pluck('total', 'id_unidad');
    }

    private function malasOrdenesPorDio(array $dios, ?int $idUnidad = null): Collection
    {
        if (empty($dios)) {
            return collect();
        }

        if (!empty($this->missingTables(['vmx_diaoperativo', 'vmx_orden']))) {
            return collect();
        }

        $q = DB::table('vmx_diaoperativo as d')
            ->join('vmx_orden as o', function ($join) {
                $join->on('o.id_unidad', '=', 'd.id_unidad')
                    ->whereRaw('o.id_orden BETWEEN d.oinicial_diaoperativo AND d.ofinal_diaoperativo');
            })
            ->whereIn('d.id_diaoperativo', $dios);

        if ($idUnidad !== null) {
            $q->where('d.id_unidad', $idUnidad);
        }

        return $q->groupBy('d.id_diaoperativo')
            ->select(
                'd.id_diaoperativo',
                DB::raw("COUNT(DISTINCT CONCAT(o.id_unidad, '-', o.id_orden)) as total_ordenes"),
                DB::raw("COUNT(DISTINCT CASE WHEN (o.cam_orden = 1 OR o.easc_orden = 1) THEN CONCAT(o.id_unidad, '-', o.id_orden) END) as malas_ordenes")
            )
            ->get()
            ->keyBy('id_diaoperativo');
    }

    // ── Helper ────────────────────────────────────────────────────────────

    /**
     * Returns a keyed collection [diaoperativo => total_venta].
     * If $unidades is provided, filters to only those units.
     */
    private function ventasPorDio(array $dios, array $unidades = [], ?int $idUnidad = null): \Illuminate\Support\Collection
    {
        if (empty($dios) || !empty($this->missingTables(['vmx_res_ventas']))) {
            return collect();
        }

        $q = DB::table('vmx_res_ventas')
            ->whereIn('id_diaoperativo', $dios)
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127)
            ->groupBy('id_diaoperativo')
            ->select('id_diaoperativo', DB::raw('SUM(total_venta) as total'));

        if (! empty($unidades)) {
            $q->whereIn('id_unidad', $unidades);
        }

        if ($idUnidad !== null) {
            $q->where('id_unidad', $idUnidad);
        }

        return $q->pluck('total', 'id_diaoperativo');
    }

    // ─────────────────────────────────────────────────────────────────────
    // RPT DIA — Ventas por unidad agrupadas por supervisor (BASE/ modo=dia)
    // ─────────────────────────────────────────────────────────────────────
    public function rptDia(Request $request): JsonResponse
    {
        $request->validate(['fecha' => 'sometimes|date_format:Y-m-d']);

        $fechaStr = $request->query('fecha');
        if (!is_string($fechaStr) || $fechaStr === '') {
            $fechaStr = now()->format('Y-m-d');
        }

        $fecha = Carbon::parse($fechaStr)->startOfDay();

        // Fecha comparativa: mismo día de semana del año anterior (-364 días)
        $fechaComp = $fecha->copy()->subDays(364);
        $fechaCompStr = $fechaComp->format('Y-m-d');

        $dio     = $this->dateToDiaOperativo($fecha);
        $dioComp = $this->dateToDiaOperativo($fechaComp);

        // Unidades activas con supervisor asignado, abiertas antes o en la fecha
        $unidades = DB::table('unidades')
            ->where('activa_unidad', 1)
            ->where('supervisor', '>', 0)
            ->where(function ($q) use ($fechaStr) {
                $q->whereNull('fapertura_unidad')
                  ->orWhere('fapertura_unidad', '<=', $fechaStr);
            })
            ->orderBy('supervisor')
            ->orderBy('id_unidad')
            ->get(['id_unidad', 'nombre_unidad', 'supervisor', 'fapertura_unidad']);

        if ($unidades->isEmpty()) {
            return response()->json([
                'fecha'                  => $fechaStr,
                'fecha_label'            => ucfirst($fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
                'fecha_comparativa'      => $fechaCompStr,
                'fecha_comparativa_label'=> ucfirst($fechaComp->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
                'year_actual'            => $fecha->year,
                'year_anterior'          => $fechaComp->year,
                'supervisores'           => [],
            ]);
        }

        // Nombres de supervisores
        $supervisorIds = $unidades->pluck('supervisor')->unique()->values()->all();
        $supervisores  = DB::table('usuarios')
            ->whereIn('id_usuario', $supervisorIds)
            ->get(['id_usuario', 'nombres_usuario', 'apellidos_usuario'])
            ->keyBy('id_usuario');

        $todosIds     = $unidades->pluck('id_unidad')->all();
        $idsComparat  = $unidades->filter(function ($u) use ($fechaCompStr) {
            $apertura = $u->fapertura_unidad ? substr($u->fapertura_unidad, 0, 10) : '';
            return $apertura === '' || $apertura <= $fechaCompStr;
        })->pluck('id_unidad')->all();

        // Ventas y transacciones del día actual y comparativo
        $ventasActual = $this->ventasDiaPorUnidad($dio,     $todosIds);
        $ventasComp   = empty($idsComparat) ? collect() : $this->ventasDiaPorUnidad($dioComp, $idsComparat);
        $txnActual    = $this->txnDiaPorUnidad($dio,     $todosIds);
        $txnComp      = empty($idsComparat) ? collect() : $this->txnDiaPorUnidad($dioComp, $idsComparat);

        // Presupuesto del día
        $presupuesto = collect();
        if (Schema::hasTable('presupuesto')) {
            $presupuesto = DB::table('presupuesto')
                ->where('id_diaoperativo', $dio)
                ->whereIn('id_unidad', $todosIds)
                ->groupBy('id_unidad')
                ->select('id_unidad', DB::raw('SUM(total_ppo) as total'))
                ->pluck('total', 'id_unidad');
        }

        // Agrupar por supervisor
        $porSupervisor = $unidades->groupBy('supervisor');
        $resultado     = [];

        foreach ($porSupervisor as $supId => $unidadesSup) {
            $sup    = $supervisores[(int) $supId] ?? null;
            $nombre = $sup
                ? trim(($sup->nombres_usuario ?? '') . ' ' . ($sup->apellidos_usuario ?? ''))
                : 'Supervisor ' . $supId;

            $filas = [];
            foreach ($unidadesSup->sortBy('id_unidad') as $u) {
                $idU      = (int) $u->id_unidad;
                $apertura = $u->fapertura_unidad ? substr($u->fapertura_unidad, 0, 10) : '';
                $esNueva  = !($apertura === '' || $apertura <= $fechaCompStr);

                $fxAp  = (float) ($ventasActual[$idU] ?? 0);
                $fxAc  = $esNueva ? null : (float) ($ventasComp[$idU] ?? 0);
                $txnAp = (int)   ($txnActual[$idU]   ?? 0);
                $txnAc = $esNueva ? null : (int) ($txnComp[$idU] ?? 0);
                $pto   = (float) ($presupuesto[$idU]  ?? 0);

                $var   = $fxAc !== null ? $fxAp - $fxAc : null;
                $pctAp = ($fxAc !== null && $fxAc > 0) ? round(($var / $fxAc) * 100, 1) : null;
                $varPto = $pto > 0 ? round($fxAp - $pto, 2) : 0.0;
                $pctAa  = $pto > 0 ? round(($varPto / $pto) * 100, 1) : null;

                $filas[] = [
                    'id_unidad'     => $idU,
                    'nombre_unidad' => $u->nombre_unidad,
                    'es_nueva'      => $esNueva,
                    'fx_ac'         => $fxAc,
                    'fx_ap'         => $fxAp,
                    'txn_ac'        => $txnAc,
                    'txn_ap'        => $txnAp,
                    'var'           => $var,
                    'pct_ap'        => $pctAp,
                    'presupuesto'   => $pto,
                    'variacion_pto' => $varPto,
                    'pct_aa'        => $pctAa,
                ];
            }

            $resultado[] = [
                'id_supervisor' => (int) $supId,
                'supervisor'    => $nombre !== '' ? $nombre : 'Supervisor ' . $supId,
                'unidades'      => $filas,
            ];
        }

        // === ACUMULADO POR DÍA (semana) ===
        $dayOfMonth  = (int) $fecha->format('j');
        $weekIndex   = (int) floor(($dayOfMonth - 1) / 7);
        $diaIni      = ($weekIndex * 7) + 1;
        $diaFin      = min($diaIni + 6, (int) $fecha->format('t'));
        $semanaNum   = $weekIndex + 1;
        $ym          = $fecha->format('Y-m');
        $inicioSemana = Carbon::createFromFormat('Y-m-d', $ym . '-' . sprintf('%02d', $diaIni))->startOfDay();
        $finSemana    = Carbon::createFromFormat('Y-m-d', $ym . '-' . sprintf('%02d', $diaFin))->startOfDay();

        $diasSemana = [];
        $cursor = $inicioSemana->copy();
        while ($cursor <= $finSemana) {
            $diasSemana[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        $dioActualPorFecha      = [];
        $dioCompPorFecha        = [];
        $fechaCompPorFecha      = [];
        foreach ($diasSemana as $d) {
            $c = Carbon::parse($d);
            $cComp = $c->copy()->subDays(364);
            $dioActualPorFecha[$d]  = $this->dateToDiaOperativo($c);
            $dioCompPorFecha[$d]    = $this->dateToDiaOperativo($cComp);
            $fechaCompPorFecha[$d]  = $cComp->format('Y-m-d');
        }

        $dioSemanaInicio     = $this->dateToDiaOperativo($inicioSemana);
        $dioSemanaFin        = $this->dateToDiaOperativo($finSemana);
        $dioSemanaCompInicio = $this->dateToDiaOperativo($inicioSemana->copy()->subDays(364));
        $dioSemanaCompFin    = $this->dateToDiaOperativo($finSemana->copy()->subDays(364));

        $vActualSemana   = $this->ventasRangoPorDiaUnidad($dioSemanaInicio,     $dioSemanaFin,     $todosIds);
        $vCompSemana     = $this->ventasRangoPorDiaUnidad($dioSemanaCompInicio, $dioSemanaCompFin, $todosIds);
        $txnActualSemana = $this->txnRangoPorDiaUnidad($dioSemanaInicio,     $dioSemanaFin,     $todosIds);
        $txnCompSemana   = $this->txnRangoPorDiaUnidad($dioSemanaCompInicio, $dioSemanaCompFin, $todosIds);
        $ptoSemana       = Schema::hasTable('presupuesto')
            ? $this->ptoPorDiaUnidad($dioSemanaInicio, $dioSemanaFin, $todosIds)
            : [];

        $unidadApertura = $unidades->pluck('fapertura_unidad', 'id_unidad')
            ->map(fn ($v) => $v ? substr($v, 0, 10) : '')
            ->all();

        $diasNombresEs = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
        $filasAcumulado = [];

        foreach ($diasSemana as $fechaDia) {
            $dioDia     = $dioActualPorFecha[$fechaDia];
            $dioDiaComp = $dioCompPorFecha[$fechaDia];
            $dDiaComp   = $fechaCompPorFecha[$fechaDia];

            $vActDia    = $vActualSemana[(string) $dioDia]   ?? [];
            $vCompDia   = $vCompSemana[(string) $dioDiaComp]  ?? [];
            $txnActDia  = $txnActualSemana[(string) $dioDia]  ?? [];
            $txnCompDia = $txnCompSemana[(string) $dioDiaComp] ?? [];
            $ptoDia     = $ptoSemana[(string) $dioDia]        ?? [];

            $fxAcTotal   = 0.0; $fxApIguales = 0.0; $fxApNuevas = 0.0;
            $fxApConPto  = 0.0; $ptoTotal    = 0.0;
            $txnAcTotal  = 0;   $txnApTotal  = 0;   $txnApNuevas = 0;

            foreach ($unidadApertura as $idU => $apertura) {
                $idU    = (int) $idU;
                $fxApU  = (float) ($vActDia[$idU] ?? 0);
                $ptoU   = (float) ($ptoDia[$idU] ?? 0);

                if ($ptoU > 0) {
                    $ptoTotal   += $ptoU;
                    $fxApConPto += $fxApU;
                }

                // "nueva" = opened less than 1 year before the day
                $esNuevaDia = !($apertura === '' || $apertura <= $dDiaComp);

                if ($esNuevaDia) {
                    $fxApNuevas  += $fxApU;
                    $txnApNuevas += (int) ($txnActDia[$idU] ?? 0);
                    continue;
                }

                $fxApIguales += $fxApU;
                $txnApTotal  += (int) ($txnActDia[$idU] ?? 0);

                if ($apertura === '' || $apertura <= $dDiaComp) {
                    $fxAcTotal  += (float) ($vCompDia[$idU] ?? 0);
                    $txnAcTotal += (int)   ($txnCompDia[$idU] ?? 0);
                }
            }

            $var    = round($fxApIguales - $fxAcTotal, 2);
            $pctAp  = $fxAcTotal > 0 ? round(($var / $fxAcTotal) * 100, 1) : null;
            $varPto = $ptoTotal > 0 ? round($fxApConPto - $ptoTotal, 2) : 0.0;
            $pctAa  = $ptoTotal > 0 ? round(($varPto / $ptoTotal) * 100, 1) : null;

            $cDia = Carbon::parse($fechaDia);
            $filasAcumulado[] = [
                'dia'               => $diasNombresEs[(int) $cDia->format('w')],
                'fecha_actual'      => $fechaDia,
                'fecha_comparativa' => $dDiaComp,
                'fx_ac'             => round($fxAcTotal, 2),
                'fx_ap'             => round($fxApIguales, 2),
                'fx_ap_nuevas'      => round($fxApNuevas, 2),
                'pto'               => round($ptoTotal, 2),
                'var'               => $var,
                'pct_ap'            => $pctAp,
                'variacion_pto'     => $varPto,
                'pct_aa'            => $pctAa,
                'txn_ac'            => $txnAcTotal,
                'txn_ap'            => $txnApTotal,
                'txn_ap_nuevas'     => $txnApNuevas,
            ];
        }

        $semanaLabel = 'SEMANA ' . $semanaNum . ': '
            . ucfirst($inicioSemana->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'))
            . ' a '
            . ucfirst($finSemana->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'));

        // === ACUMULADO POR SUPERVISOR (totals del día, solo unidades iguales) ===
        $acumuladoSupervisores = [];
        foreach ($resultado as $supData) {
            $fxAcSup = 0.0; $fxApSup = 0.0; $ptoSup = 0.0; $fxApConPtoSup = 0.0;
            foreach ($supData['unidades'] as $u) {
                if ($u['es_nueva']) {
                    continue;
                }
                $fxAcSup     += (float) ($u['fx_ac'] ?? 0);
                $fxApSup     += (float) $u['fx_ap'];
                $ptoSup      += (float) $u['presupuesto'];
                if ($u['presupuesto'] > 0) {
                    $fxApConPtoSup += (float) $u['fx_ap'];
                }
            }
            $varSup    = round($fxApSup - $fxAcSup, 2);
            $pctApSup  = $fxAcSup > 0 ? round(($varSup / $fxAcSup) * 100, 1) : null;
            $varPtoSup = $ptoSup > 0 ? round($fxApConPtoSup - $ptoSup, 2) : 0.0;
            $pctAaSup  = $ptoSup > 0 ? round(($varPtoSup / $ptoSup) * 100, 1) : null;

            $acumuladoSupervisores[] = [
                'supervisor'    => $supData['supervisor'],
                'fx_ac'         => round($fxAcSup, 2),
                'fx_ap'         => round($fxApSup, 2),
                'var'           => $varSup,
                'pct_ap'        => $pctApSup,
                'presupuesto'   => round($ptoSup, 2),
                'variacion_pto' => $varPtoSup,
                'pct_aa'        => $pctAaSup,
            ];
        }

        return response()->json([
            'fecha'                  => $fechaStr,
            'fecha_label'            => ucfirst($fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
            'fecha_comparativa'      => $fechaCompStr,
            'fecha_comparativa_label'=> ucfirst($fechaComp->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
            'year_actual'            => $fecha->year,
            'year_anterior'          => $fechaComp->year,
            'semana_titulo'          => $semanaLabel,
            'supervisores'           => $resultado,
            'acumulado_semana'       => $filasAcumulado,
            'acumulado_supervisores' => $acumuladoSupervisores,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RPT RANGO — Ventas por unidad/supervisor para un rango de fechas
    // ─────────────────────────────────────────────────────────────────────
    public function rptRango(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin'    => 'required|date_format:Y-m-d',
        ]);

        $fechaInicioStr = $request->input('fecha_inicio');
        $fechaFinStr    = $request->input('fecha_fin');

        $fechaInicio = Carbon::parse($fechaInicioStr)->startOfDay();
        $fechaFin    = Carbon::parse($fechaFinStr)->startOfDay();

        // Enforce order
        if ($fechaFin->lt($fechaInicio)) {
            [$fechaInicio, $fechaFin]         = [$fechaFin, $fechaInicio];
            [$fechaInicioStr, $fechaFinStr]    = [$fechaFinStr, $fechaInicioStr];
        }

        // Comparative: -364 days (preserves same day of week)
        $fechaCompInicio    = $fechaInicio->copy()->subDays(364);
        $fechaCompFin       = $fechaFin->copy()->subDays(364);
        $fechaCompInicioStr = $fechaCompInicio->format('Y-m-d');
        $fechaCompFinStr    = $fechaCompFin->format('Y-m-d');

        $dioInicio     = $this->dateToDiaOperativo($fechaInicio);
        $dioFin        = $this->dateToDiaOperativo($fechaFin);
        $dioCompInicio = $this->dateToDiaOperativo($fechaCompInicio);
        $dioCompFin    = $this->dateToDiaOperativo($fechaCompFin);

        // Active units with supervisor, opened on or before fechaFin
        $unidades = DB::table('unidades')
            ->where('activa_unidad', 1)
            ->where('supervisor', '>', 0)
            ->where(function ($q) use ($fechaFinStr) {
                $q->whereNull('fapertura_unidad')
                  ->orWhere('fapertura_unidad', '<=', $fechaFinStr);
            })
            ->orderBy('supervisor')
            ->orderBy('id_unidad')
            ->get(['id_unidad', 'nombre_unidad', 'supervisor', 'fapertura_unidad']);

        if ($unidades->isEmpty()) {
            return response()->json([
                'fecha_inicio'            => $fechaInicioStr,
                'fecha_fin'               => $fechaFinStr,
                'fecha_inicio_label'      => ucfirst($fechaInicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
                'fecha_fin_label'         => ucfirst($fechaFin->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
                'fecha_comp_inicio'       => $fechaCompInicioStr,
                'fecha_comp_fin'          => $fechaCompFinStr,
                'fecha_comp_inicio_label' => ucfirst($fechaCompInicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
                'fecha_comp_fin_label'    => ucfirst($fechaCompFin->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
                'year_actual'             => $fechaFin->year,
                'year_anterior'           => $fechaCompFin->year,
                'titulo'                  => 'RANGO: ' . $fechaInicioStr . ' a ' . $fechaFinStr,
                'supervisores'            => [],
                'acumulado_semana'        => [],
                'acumulado_supervisores'  => [],
            ]);
        }

        // Supervisor names
        $supervisorIds = $unidades->pluck('supervisor')->unique()->values()->all();
        $supervisores  = DB::table('usuarios')
            ->whereIn('id_usuario', $supervisorIds)
            ->get(['id_usuario', 'nombres_usuario', 'apellidos_usuario'])
            ->keyBy('id_usuario');

        $todosIds = $unidades->pluck('id_unidad')->all();

        // Units applicable for comparative (opened before comparative end)
        $idsComparativos = $unidades->filter(function ($u) use ($fechaCompFinStr) {
            $apertura = $u->fapertura_unidad ? substr($u->fapertura_unidad, 0, 10) : '';
            return $apertura === '' || $apertura <= $fechaCompFinStr;
        })->pluck('id_unidad')->all();

        // Totals for the whole range per unit
        $ventasActual = $this->ventasRangoPorUnidad($dioInicio, $dioFin, $todosIds);
        $ventasComp   = empty($idsComparativos) ? collect() : $this->ventasRangoPorUnidad($dioCompInicio, $dioCompFin, $idsComparativos);
        $txnActual    = $this->txnRangoPorUnidad($dioInicio, $dioFin, $todosIds);
        $txnComp      = empty($idsComparativos) ? collect() : $this->txnRangoPorUnidad($dioCompInicio, $dioCompFin, $idsComparativos);

        // Presupuesto for the range
        $presupuesto = collect();
        if (Schema::hasTable('presupuesto')) {
            $presupuesto = DB::table('presupuesto')
                ->whereBetween('id_diaoperativo', [$dioInicio, $dioFin])
                ->whereIn('id_unidad', $todosIds)
                ->groupBy('id_unidad')
                ->select('id_unidad', DB::raw('SUM(total_ppo) as total'))
                ->pluck('total', 'id_unidad');
        }

        // Build per-supervisor / per-unit data
        $porSupervisor = $unidades->groupBy('supervisor');
        $resultado     = [];

        foreach ($porSupervisor as $supId => $unidadesSup) {
            $sup    = $supervisores[(int) $supId] ?? null;
            $nombre = $sup
                ? trim(($sup->nombres_usuario ?? '') . ' ' . ($sup->apellidos_usuario ?? ''))
                : 'Supervisor ' . $supId;

            $filas = [];
            foreach ($unidadesSup->sortBy('id_unidad') as $u) {
                $idU      = (int) $u->id_unidad;
                $apertura = $u->fapertura_unidad ? substr($u->fapertura_unidad, 0, 10) : '';
                $esNueva  = !($apertura === '' || $apertura <= $fechaCompFinStr);

                $fxAp  = (float) ($ventasActual[$idU] ?? 0);
                $fxAc  = $esNueva ? null : (float) ($ventasComp[$idU] ?? 0);
                $txnAp = (int)   ($txnActual[$idU]   ?? 0);
                $txnAc = $esNueva ? null : (int) ($txnComp[$idU] ?? 0);
                $pto   = (float) ($presupuesto[$idU]  ?? 0);

                $var    = $fxAc !== null ? $fxAp - $fxAc : null;
                $pctAp  = ($fxAc !== null && $fxAc > 0) ? round(($var / $fxAc) * 100, 1) : null;
                $varPto = $pto > 0 ? round($fxAp - $pto, 2) : 0.0;
                $pctAa  = $pto > 0 ? round(($varPto / $pto) * 100, 1) : null;

                $filas[] = [
                    'id_unidad'     => $idU,
                    'nombre_unidad' => $u->nombre_unidad,
                    'es_nueva'      => $esNueva,
                    'fx_ac'         => $fxAc,
                    'fx_ap'         => $fxAp,
                    'txn_ac'        => $txnAc,
                    'txn_ap'        => $txnAp,
                    'var'           => $var,
                    'pct_ap'        => $pctAp,
                    'presupuesto'   => $pto,
                    'variacion_pto' => $varPto,
                    'pct_aa'        => $pctAa,
                ];
            }

            $resultado[] = [
                'id_supervisor' => (int) $supId,
                'supervisor'    => $nombre !== '' ? $nombre : 'Supervisor ' . $supId,
                'unidades'      => $filas,
            ];
        }

        // === ACUMULADO POR DÍA DE SEMANA (grouped by weekday across range) ===
        $vActualPorDiaUnidad = $this->ventasRangoPorDiaUnidad($dioInicio, $dioFin, $todosIds);
        $vCompPorDiaUnidad   = $this->ventasRangoPorDiaUnidad($dioCompInicio, $dioCompFin, $todosIds);
        $txnActPorDiaUnidad  = $this->txnRangoPorDiaUnidad($dioInicio, $dioFin, $todosIds);
        $txnCompPorDiaUnidad = $this->txnRangoPorDiaUnidad($dioCompInicio, $dioCompFin, $todosIds);
        $ptoPorDiaUnidad     = Schema::hasTable('presupuesto')
            ? $this->ptoPorDiaUnidad($dioInicio, $dioFin, $todosIds)
            : [];

        $unidadApertura = $unidades->pluck('fapertura_unidad', 'id_unidad')
            ->map(fn ($v) => $v ? substr($v, 0, 10) : '')
            ->all();

        $diasNombresEs = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
        $porDiaSemana  = [];

        $cursor = $fechaInicio->copy();
        while ($cursor <= $fechaFin) {
            $fechaDia     = $cursor->format('Y-m-d');
            $fechaDiaComp = $cursor->copy()->subDays(364)->format('Y-m-d');
            $dioDia       = $this->dateToDiaOperativo($cursor);
            $dioDiaComp   = $this->dateToDiaOperativo($cursor->copy()->subDays(364));
            $weekday      = (int) $cursor->format('w');

            $vActDia    = $vActualPorDiaUnidad[(string) $dioDia]    ?? [];
            $vCompDia   = $vCompPorDiaUnidad[(string) $dioDiaComp]  ?? [];
            $txnActDia  = $txnActPorDiaUnidad[(string) $dioDia]     ?? [];
            $txnCompDia = $txnCompPorDiaUnidad[(string) $dioDiaComp] ?? [];
            $ptoDia     = $ptoPorDiaUnidad[(string) $dioDia]        ?? [];

            if (!isset($porDiaSemana[$weekday])) {
                $porDiaSemana[$weekday] = [
                    'dia'           => $diasNombresEs[$weekday],
                    'fecha_actual'  => $fechaDia,
                    'fx_ac'         => 0.0,
                    'fx_ap_iguales' => 0.0,
                    'fx_ap_nuevas'  => 0.0,
                    'fx_ap_con_pto' => 0.0,
                    'pto'           => 0.0,
                    'txn_ac'        => 0,
                    'txn_ap'        => 0,
                    'txn_ap_nuevas' => 0,
                ];
            }

            foreach ($unidadApertura as $idU => $apertura) {
                $idU   = (int) $idU;
                $fxApU = (float) ($vActDia[$idU] ?? 0);
                $ptoU  = (float) ($ptoDia[$idU]  ?? 0);

                if ($ptoU > 0) {
                    $porDiaSemana[$weekday]['pto']           += $ptoU;
                    $porDiaSemana[$weekday]['fx_ap_con_pto'] += $fxApU;
                }

                $esNuevaDia = !($apertura === '' || $apertura <= $fechaDiaComp);
                if ($esNuevaDia) {
                    $porDiaSemana[$weekday]['fx_ap_nuevas']  += $fxApU;
                    $porDiaSemana[$weekday]['txn_ap_nuevas'] += (int) ($txnActDia[$idU] ?? 0);
                    continue;
                }

                $porDiaSemana[$weekday]['fx_ap_iguales'] += $fxApU;
                $porDiaSemana[$weekday]['txn_ap']        += (int) ($txnActDia[$idU] ?? 0);

                if ($apertura === '' || $apertura <= $fechaDiaComp) {
                    $porDiaSemana[$weekday]['fx_ac']  += (float) ($vCompDia[$idU]   ?? 0);
                    $porDiaSemana[$weekday]['txn_ac'] += (int)   ($txnCompDia[$idU] ?? 0);
                }
            }

            $cursor->addDay();
        }

        ksort($porDiaSemana);

        $filasAcumulado = [];
        foreach ($porDiaSemana as $filaDia) {
            $fxAc       = round($filaDia['fx_ac'], 2);
            $fxAp       = round($filaDia['fx_ap_iguales'], 2);
            $fxApNuevas = round($filaDia['fx_ap_nuevas'], 2);
            $fxApConPto = round($filaDia['fx_ap_con_pto'], 2);
            $pto        = round($filaDia['pto'], 2);
            $var        = round($fxAp - $fxAc, 2);
            $pctAp      = $fxAc > 0 ? round(($var / $fxAc) * 100, 1) : null;
            $varPto     = $pto > 0 ? round($fxApConPto - $pto, 2) : 0.0;
            $pctAa      = $pto > 0 ? round(($varPto / $pto) * 100, 1) : null;

            $filasAcumulado[] = [
                'dia'           => $filaDia['dia'],
                'fecha_actual'  => $filaDia['fecha_actual'],
                'fx_ac'         => $fxAc,
                'fx_ap'         => $fxAp,
                'fx_ap_nuevas'  => $fxApNuevas,
                'pto'           => $pto,
                'var'           => $var,
                'pct_ap'        => $pctAp,
                'variacion_pto' => $varPto,
                'pct_aa'        => $pctAa,
                'txn_ac'        => $filaDia['txn_ac'],
                'txn_ap'        => $filaDia['txn_ap'],
                'txn_ap_nuevas' => $filaDia['txn_ap_nuevas'],
            ];
        }

        // === ACUMULADO POR SUPERVISOR ===
        $acumuladoSupervisores = [];
        foreach ($resultado as $supData) {
            $fxAcSup = 0.0; $fxApSup = 0.0; $ptoSup = 0.0; $fxApConPtoSup = 0.0;
            foreach ($supData['unidades'] as $u) {
                if ($u['es_nueva']) {
                    continue;
                }
                $fxAcSup     += (float) ($u['fx_ac'] ?? 0);
                $fxApSup     += (float) $u['fx_ap'];
                $ptoSup      += (float) $u['presupuesto'];
                if ($u['presupuesto'] > 0) {
                    $fxApConPtoSup += (float) $u['fx_ap'];
                }
            }
            $varSup    = round($fxApSup - $fxAcSup, 2);
            $pctApSup  = $fxAcSup > 0 ? round(($varSup / $fxAcSup) * 100, 1) : null;
            $varPtoSup = $ptoSup > 0 ? round($fxApConPtoSup - $ptoSup, 2) : 0.0;
            $pctAaSup  = $ptoSup > 0 ? round(($varPtoSup / $ptoSup) * 100, 1) : null;

            $acumuladoSupervisores[] = [
                'supervisor'    => $supData['supervisor'],
                'fx_ac'         => round($fxAcSup, 2),
                'fx_ap'         => round($fxApSup, 2),
                'var'           => $varSup,
                'pct_ap'        => $pctApSup,
                'presupuesto'   => round($ptoSup, 2),
                'variacion_pto' => $varPtoSup,
                'pct_aa'        => $pctAaSup,
            ];
        }

        return response()->json([
            'fecha_inicio'            => $fechaInicioStr,
            'fecha_fin'               => $fechaFinStr,
            'fecha_inicio_label'      => ucfirst($fechaInicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
            'fecha_fin_label'         => ucfirst($fechaFin->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
            'fecha_comp_inicio'       => $fechaCompInicioStr,
            'fecha_comp_fin'          => $fechaCompFinStr,
            'fecha_comp_inicio_label' => ucfirst($fechaCompInicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
            'fecha_comp_fin_label'    => ucfirst($fechaCompFin->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
            'year_actual'             => $fechaFin->year,
            'year_anterior'           => $fechaCompFin->year,
            'titulo'                  => 'RANGO: ' . ucfirst($fechaInicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
                                         . ' a ' . ucfirst($fechaFin->locale('es')->isoFormat('D [de] MMMM [de] YYYY')),
            'supervisores'            => $resultado,
            'acumulado_semana'        => $filasAcumulado,
            'acumulado_supervisores'  => $acumuladoSupervisores,
        ]);
    }

    /** Ventas totales por unidad over a diaoperativo range (no grouping by day). */
    private function ventasRangoPorUnidad(int $dioStart, int $dioEnd, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }
        return DB::table('vmx_res_ventas')
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127)
            ->whereBetween('id_diaoperativo', [$dioStart, $dioEnd])
            ->whereIn('id_unidad', $ids)
            ->groupBy('id_unidad')
            ->select('id_unidad', DB::raw('SUM(total_venta) as total'))
            ->pluck('total', 'id_unidad');
    }

    /** Transaction count per unit over a diaoperativo range (no grouping by day). */
    private function txnRangoPorUnidad(int $dioStart, int $dioEnd, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }
        return DB::table('vmx_res_ventas')
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127)
            ->whereBetween('id_diaoperativo', [$dioStart, $dioEnd])
            ->whereIn('id_unidad', $ids)
            ->groupBy('id_unidad')
            ->select('id_unidad', DB::raw('SUM(numero_venta) as total'))
            ->pluck('total', 'id_unidad');
    }

    private function ventasRangoPorDiaUnidad(int $dioStart, int $dioEnd, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('vmx_res_ventas')
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127)
            ->whereBetween('id_diaoperativo', [$dioStart, $dioEnd])
            ->whereIn('id_unidad', $ids)
            ->groupBy('id_diaoperativo', 'id_unidad')
            ->select('id_diaoperativo', 'id_unidad', DB::raw('SUM(total_venta) as total'))
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->id_diaoperativo][(int) $row->id_unidad] = (float) $row->total;
        }
        return $result;
    }

    private function txnRangoPorDiaUnidad(int $dioStart, int $dioEnd, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('vmx_res_ventas')
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127)
            ->whereBetween('id_diaoperativo', [$dioStart, $dioEnd])
            ->whereIn('id_unidad', $ids)
            ->groupBy('id_diaoperativo', 'id_unidad')
            ->select('id_diaoperativo', 'id_unidad', DB::raw('SUM(numero_venta) as total'))
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->id_diaoperativo][(int) $row->id_unidad] = (int) $row->total;
        }
        return $result;
    }

    private function ptoPorDiaUnidad(int $dioStart, int $dioEnd, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('presupuesto')
            ->whereBetween('id_diaoperativo', [$dioStart, $dioEnd])
            ->whereIn('id_unidad', $ids)
            ->groupBy('id_diaoperativo', 'id_unidad')
            ->select('id_diaoperativo', 'id_unidad', DB::raw('SUM(total_ppo) as total'))
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->id_diaoperativo][(int) $row->id_unidad] = (float) $row->total;
        }
        return $result;
    }

    private function ventasDiaPorUnidad(int $dio, array $idsUnidades): Collection
    {
        if (empty($idsUnidades)) {
            return collect();
        }
        return DB::table('vmx_res_ventas')
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127)
            ->where('id_diaoperativo', $dio)
            ->whereIn('id_unidad', $idsUnidades)
            ->groupBy('id_unidad')
            ->select('id_unidad', DB::raw('SUM(total_venta) as total'))
            ->pluck('total', 'id_unidad');
    }

    private function txnDiaPorUnidad(int $dio, array $idsUnidades): Collection
    {
        if (empty($idsUnidades)) {
            return collect();
        }
        return DB::table('vmx_res_ventas')
            ->where('tipo_venta', 'D')
            ->where('id_tipoorden', 127)
            ->where('id_diaoperativo', $dio)
            ->whereIn('id_unidad', $idsUnidades)
            ->groupBy('id_unidad')
            ->select('id_unidad', DB::raw('SUM(numero_venta) as txn'))
            ->pluck('txn', 'id_unidad');
    }

    private function resolveIdUnidad(Request $request): ?int
    {
        $val = $request->query('id_unidad');
        if ($val === null || $val === '' || $val === '0') {
            return null;
        }
        return (int) $val;
    }

    private function missingTables(array $tables): array
    {
        $missing = [];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }
}
