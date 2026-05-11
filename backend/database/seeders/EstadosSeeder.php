<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstadosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $estados = [
            ['id_estado' => 1, 'nombre_estado' => 'Aguascalientes'],
            ['id_estado' => 2, 'nombre_estado' => 'Baja California Sur'],
            ['id_estado' => 3, 'nombre_estado' => 'Baja California'],
            ['id_estado' => 4, 'nombre_estado' => 'Campeche'],
            ['id_estado' => 5, 'nombre_estado' => 'Chihuaua'],
            ['id_estado' => 6, 'nombre_estado' => 'Colima'],
            ['id_estado' => 7, 'nombre_estado' => 'Coahuila'],
            ['id_estado' => 8, 'nombre_estado' => 'Chiapas'],
            ['id_estado' => 9, 'nombre_estado' => 'Mexico DF'],
            ['id_estado' => 10, 'nombre_estado' => 'Durango'],
            ['id_estado' => 11, 'nombre_estado' => 'Guanajuato'],
            ['id_estado' => 12, 'nombre_estado' => 'Guerrero'],
            ['id_estado' => 13, 'nombre_estado' => 'Hidalgo'],
            ['id_estado' => 14, 'nombre_estado' => 'Jalisco'],
            ['id_estado' => 15, 'nombre_estado' => 'Estado de Mexico'],
            ['id_estado' => 16, 'nombre_estado' => 'Michoacan'],
            ['id_estado' => 17, 'nombre_estado' => 'Morelos'],
            ['id_estado' => 18, 'nombre_estado' => 'Nayarit'],
            ['id_estado' => 19, 'nombre_estado' => 'Nuevo Leon'],
            ['id_estado' => 20, 'nombre_estado' => 'Oaxaca'],
            ['id_estado' => 21, 'nombre_estado' => 'Puebla'],
            ['id_estado' => 22, 'nombre_estado' => 'Queretaro'],
            ['id_estado' => 23, 'nombre_estado' => 'QuintanaRoo'],
            ['id_estado' => 24, 'nombre_estado' => 'San Lius Potosi'],
            ['id_estado' => 25, 'nombre_estado' => 'Sinaloa'],
            ['id_estado' => 26, 'nombre_estado' => 'Sonora'],
            ['id_estado' => 27, 'nombre_estado' => 'Tabasco'],
            ['id_estado' => 28, 'nombre_estado' => 'Tamaulipas'],
            ['id_estado' => 29, 'nombre_estado' => 'Tlaxcala'],
            ['id_estado' => 30, 'nombre_estado' => 'Veracruz'],
            ['id_estado' => 31, 'nombre_estado' => 'Yucatan'],
            ['id_estado' => 32, 'nombre_estado' => 'Zacatecas'],
        ];

        $rows = array_map(
            static fn (array $estado): array => [
                'id_estado' => $estado['id_estado'],
                'nombre_estado' => $estado['nombre_estado'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $estados
        );

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('estados')->truncate();
            DB::table('estados')->insert($rows);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
