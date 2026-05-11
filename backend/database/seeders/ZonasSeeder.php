<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZonasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zonas = ['Centro', 'Norte', 'Sur'];

        foreach ($zonas as $zona) {
            DB::table('zonas')->updateOrInsert(
                ['nombre_zona' => $zona],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
