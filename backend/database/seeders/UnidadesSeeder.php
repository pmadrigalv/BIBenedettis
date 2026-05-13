<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnidadesSeeder extends Seeder
{
    public function run(): void
    {
        $pdo = new \PDO(
            'mysql:host=191.101.15.179;port=3307;dbname=Tablero;charset=utf8',
            'root',
            'BeneB@ckend2026!',
            [
                \PDO::ATTR_TIMEOUT           => 25,
                \PDO::ATTR_ERRMODE           => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $stmt = $pdo->query(
            'SELECT id_unidad, nombre_unidad, fapertura_unidad, supervisor, activa_unidad FROM unidad ORDER BY id_unidad ASC'
        );
        $rows = $stmt->fetchAll();

        $this->command->info('Importando ' . count($rows) . ' unidades desde Tablero...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('unidades')->truncate();

            $batch = [];
            foreach ($rows as $row) {
                $fapertura = null;
                if (!empty($row['fapertura_unidad'])) {
                    $fapertura = substr((string) $row['fapertura_unidad'], 0, 10);
                }

                $batch[] = [
                    'id_unidad'        => (int) $row['id_unidad'],
                    'nombre_unidad'    => (string) $row['nombre_unidad'],
                    'fapertura_unidad' => $fapertura,
                    'supervisor'       => (int) $row['supervisor'],
                    'activa_unidad'    => isset($row['activa_unidad']) ? (int) $row['activa_unidad'] : null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }

            foreach (array_chunk($batch, 50) as $chunk) {
                DB::table('unidades')->insert($chunk);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->command->info('Listo: ' . count($rows) . ' unidades importadas.');
    }
}