<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columnType = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'usuarios')
            ->where('column_name', 'vigencia_usuario')
            ->value('data_type');

        if ($columnType === 'tinyint') {
            return;
        }

        DB::statement("ALTER TABLE usuarios ADD COLUMN vigencia_usuario_tmp TINYINT(1) NOT NULL DEFAULT 1 AFTER vigencia_usuario");

        DB::statement(<<<'SQL'
            UPDATE usuarios
            SET vigencia_usuario_tmp = CASE
                WHEN vigencia_usuario IS NULL THEN 1
                ELSE 1
            END
        SQL);

        DB::statement("ALTER TABLE usuarios DROP COLUMN vigencia_usuario");
        DB::statement("ALTER TABLE usuarios CHANGE COLUMN vigencia_usuario_tmp vigencia_usuario TINYINT(1) NOT NULL DEFAULT 1");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columnType = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'usuarios')
            ->where('column_name', 'vigencia_usuario')
            ->value('data_type');

        if ($columnType === 'date') {
            return;
        }

        DB::statement("ALTER TABLE usuarios ADD COLUMN vigencia_usuario_tmp DATE NULL AFTER vigencia_usuario");

        DB::statement(<<<'SQL'
            UPDATE usuarios
            SET vigencia_usuario_tmp = CASE
                WHEN vigencia_usuario = 1 THEN CURRENT_DATE()
                ELSE NULL
            END
        SQL);

        DB::statement("ALTER TABLE usuarios DROP COLUMN vigencia_usuario");
        DB::statement("ALTER TABLE usuarios CHANGE COLUMN vigencia_usuario_tmp vigencia_usuario DATE NULL");
    }
};