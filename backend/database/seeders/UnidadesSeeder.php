<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnidadesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('database/data/UNIDAD.sql');

        if (! file_exists($sqlFile)) {
            $sqlFile = base_path('../DATA/UNIDAD.sql');
        }

        if (! file_exists($sqlFile)) {
            throw new \RuntimeException("No se encontro el archivo de datos: {$sqlFile}");
        }

        $sql = (string) file_get_contents($sqlFile);

        if ($sql === '') {
            return;
        }

        // Ajustes para el esquema actual de Laravel.
        $sql = str_replace('INSERT INTO unidad', 'INSERT INTO unidades', $sql);
        $sql = str_replace("'0000-00-00 00:00:00'", 'NULL', $sql);
        $sql = str_replace(",'C',", ',1,', $sql);
        $sql = str_replace(",'A',", ',1,', $sql);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('unidades')->truncate();
            DB::unprepared($sql);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}