<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposUnidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = ['Express', 'Precocido'];

        foreach ($tipos as $tipo) {
            DB::table('tipos_unidad')->updateOrInsert(
                ['nombre_tipounidad' => $tipo],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
