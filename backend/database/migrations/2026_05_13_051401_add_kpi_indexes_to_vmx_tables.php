<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable strict mode for this session to allow ALTER TABLE on tables
        // that contain zero-date defaults (e.g. '0000-00-00') from MySQL 5.6 backups.
        DB::statement("SET SESSION sql_mode = ''");

        $indexes = [
            'vmx_res_ventas'   => ['idx_ventas_dio_tipo_orden',            '(id_diaoperativo, tipo_venta, id_tipoorden)'],
            'vmx_diaoperativo' => ['idx_dio_id',                           '(id_diaoperativo)'],
            'vmx_producto'     => ['idx_producto_unidad_orden_adicional',   '(id_unidad, id_orden, esadicional_producto)'],
            'vmx_componente'   => ['idx_componente_receta_full',            '(id_receta, id_unidad, id_orden, id_producto)'],
        ];

        foreach ($indexes as $table => [$index, $columns]) {
            $exists = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?
            ", [$table, $index]);

            if (!$exists || $exists->cnt === 0) {
                DB::statement("ALTER TABLE {$table} ADD INDEX {$index} {$columns}");
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vmx_res_ventas DROP INDEX IF EXISTS idx_ventas_dio_tipo_orden');
        DB::statement('ALTER TABLE vmx_diaoperativo DROP INDEX IF EXISTS idx_dio_id');
        DB::statement('ALTER TABLE vmx_producto DROP INDEX IF EXISTS idx_producto_unidad_orden_adicional');
        DB::statement('ALTER TABLE vmx_componente DROP INDEX IF EXISTS idx_componente_receta_full');
    }
};
