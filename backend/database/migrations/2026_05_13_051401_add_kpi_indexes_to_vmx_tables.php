<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // vmx_res_ventas (5.6M rows)
        // Queries filter on id_diaoperativo + tipo_venta + id_tipoorden
        // but PK starts with id_unidad -> full scan without this index
        DB::statement('ALTER TABLE vmx_res_ventas
            ADD INDEX idx_ventas_dio_tipo_orden (id_diaoperativo, tipo_venta, id_tipoorden)');

        // vmx_diaoperativo (352K rows)
        // Queries filter on id_diaoperativo; PK starts with id_unidad
        DB::statement('ALTER TABLE vmx_diaoperativo
            ADD INDEX idx_dio_id (id_diaoperativo)');

        // vmx_producto (6.5M rows)
        // adicionalesPorDio joins (id_unidad, id_orden) and filters esadicional_producto=1
        DB::statement('ALTER TABLE vmx_producto
            ADD INDEX idx_producto_unidad_orden_adicional (id_unidad, id_orden, esadicional_producto)');

        // vmx_componente (13.3M rows)
        // orillaPorReceta filters on id_receta + joins (id_unidad, id_orden, id_producto)
        DB::statement('ALTER TABLE vmx_componente
            ADD INDEX idx_componente_receta_full (id_receta, id_unidad, id_orden, id_producto)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vmx_res_ventas DROP INDEX IF EXISTS idx_ventas_dio_tipo_orden');
        DB::statement('ALTER TABLE vmx_diaoperativo DROP INDEX IF EXISTS idx_dio_id');
        DB::statement('ALTER TABLE vmx_producto DROP INDEX IF EXISTS idx_producto_unidad_orden_adicional');
        DB::statement('ALTER TABLE vmx_componente DROP INDEX IF EXISTS idx_componente_receta_full');
    }
};
