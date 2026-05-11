<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            EstadosSeeder::class,
            ZonasSeeder::class,
            RegionesSeeder::class,
            TiposUnidadSeeder::class,
            AutoridadesSeeder::class,
            UsuariosSeeder::class,
            UnidadesSeeder::class,
            UnidadUsuarioSeeder::class,
        ]);
    }
}
