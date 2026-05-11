<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnidadUsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../DATA/usuariounidad');

        if (! file_exists($sqlFile)) {
            $sqlFile = base_path('database/data/usuariounidad.sql');
        }

        if (! file_exists($sqlFile)) {
            throw new \RuntimeException("No se encontro el archivo de datos: {$sqlFile}");
        }

        $sql = (string) file_get_contents($sqlFile);

        if (trim($sql) === '') {
            return;
        }

        preg_match_all('/\(\s*(\d+)\s*,\s*(\d+)\s*\)/', $sql, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return;
        }

        $now = now();
        $rowsByKey = [];

        foreach ($matches as $match) {
            $idUnidad = (int) $match[1];
            $idUsuario = (int) $match[2];

            $key = $idUnidad.'-'.$idUsuario;
            $rowsByKey[$key] = [
                'id_unidad' => $idUnidad,
                'id_usuario' => $idUsuario,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('unidad_usuario')->truncate();

            if ($rowsByKey !== []) {
                DB::table('unidad_usuario')->insert(array_values($rowsByKey));
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}