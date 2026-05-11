<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AutoridadesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $autoridades = [
            ['id_autoridad' =>  1, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'EVAC',                  'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  2, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'ERS',                   'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  3, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'PPP',                   'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  4, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'ASA',                   'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  5, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'AUREH',                 'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  6, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'CI',                    'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  7, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Franquiciatario',       'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  8, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Supervisor',            'tipo_autoridad' => 'U'],
            ['id_autoridad' =>  9, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Franquiciatario',       'tipo_autoridad' => 'C'],
            ['id_autoridad' => 10, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Sistemas',              'tipo_autoridad' => 'U'],
            ['id_autoridad' => 17, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Almacen',               'tipo_autoridad' => 'C'],
            ['id_autoridad' => 18, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Admin. Comisariato',    'tipo_autoridad' => 'C'],
            ['id_autoridad' => 19, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Dir. Comisariato',      'tipo_autoridad' => 'C'],
            ['id_autoridad' => 40, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Asesor Regional MKT',  'tipo_autoridad' => 'C'],
            ['id_autoridad' => 41, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Asesor Nacional MKT',  'tipo_autoridad' => 'C'],
            ['id_autoridad' => 49, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Director MKT',         'tipo_autoridad' => 'C'],
            ['id_autoridad' => 50, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Asesor Regional',      'tipo_autoridad' => 'C'],
            ['id_autoridad' => 58, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Gerente Regional',     'tipo_autoridad' => 'C'],
            ['id_autoridad' => 64, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Supervisor',           'tipo_autoridad' => 'C'],
            ['id_autoridad' => 65, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Gerente regional',     'tipo_autoridad' => 'C'],
            ['id_autoridad' => 67, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Auditoría Operativa',  'tipo_autoridad' => 'C'],
            ['id_autoridad' => 68, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Planeación y Control', 'tipo_autoridad' => 'C'],
            ['id_autoridad' => 70, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Socio',                'tipo_autoridad' => 'C'],
            ['id_autoridad' => 80, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Asist. de Dirección',  'tipo_autoridad' => 'C'],
            ['id_autoridad' => 84, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Director de MKT',      'tipo_autoridad' => 'C'],
            ['id_autoridad' => 85, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Director de sistemas', 'tipo_autoridad' => 'C'],
            ['id_autoridad' => 86, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Director Operaciones',  'tipo_autoridad' => 'C'],
            ['id_autoridad' => 87, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Director Adjunto',     'tipo_autoridad' => 'C'],
            ['id_autoridad' => 88, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Director General',     'tipo_autoridad' => 'C'],
            ['id_autoridad' => 89, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Presidente',           'tipo_autoridad' => 'C'],
            ['id_autoridad' => 94, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Asesor de Sistemas',   'tipo_autoridad' => 'C'],
            ['id_autoridad' => 95, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Soporte Técnico',      'tipo_autoridad' => 'C'],
            ['id_autoridad' => 96, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'Gerente Regional',     'tipo_autoridad' => 'C'],
            ['id_autoridad' => 98, 'msg_autoridad' => 'Y', 'descripcion_autoridad' => 'Desarrollo',           'tipo_autoridad' => 'C'],
            ['id_autoridad' => 99, 'msg_autoridad' => 'N', 'descripcion_autoridad' => 'root',                 'tipo_autoridad' => 'C'],
        ];

        foreach ($autoridades as $autoridad) {
            DB::table('autoridades')->updateOrInsert(
                ['id_autoridad' => $autoridad['id_autoridad']],
                array_merge($autoridad, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
