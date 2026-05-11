<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regiones = ['Bajio', 'Pacifico', 'Golfo', 'Norte'];

        foreach ($regiones as $region) {
            DB::table('regiones')->updateOrInsert(
                ['nombre_region' => $region],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
