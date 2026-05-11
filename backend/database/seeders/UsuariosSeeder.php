<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('database/data/USUARIO.sql');

        if (! file_exists($sqlFile)) {
            $sqlFile = base_path('../DATA/USUARIO');
        }

        if (! file_exists($sqlFile)) {
            throw new \RuntimeException("No se encontro el archivo de datos: {$sqlFile}");
        }

        $sql = (string) file_get_contents($sqlFile);

        if (trim($sql) === '') {
            return;
        }

        $validAutoridades = DB::table('autoridades')->pluck('id_autoridad')->map(fn ($id) => (int) $id)->all();
        $validAutoridades = array_flip($validAutoridades);

        preg_match_all('/\((.*?)\)(?:,|;)/s', $sql, $matches);

        $rowsById = [];

        foreach ($matches[1] as $tuple) {
            $values = str_getcsv($tuple, ',', "'", '\\');

            if (count($values) !== 8) {
                continue;
            }

            $idUsuario = (int) trim((string) $values[0]);
            $uidUsuario = trim((string) $values[1]);
            $nombresUsuario = trim((string) $values[2]);
            $apellidosUsuario = trim((string) $values[3]);
            $telefonoUsuario = $this->nullableString($values[4]);
            $emailUsuario = $this->nullableEmail($values[5]);
            $idAutoridadRaw = (int) trim((string) $values[6]);
            $vigenciaRaw = trim((string) $values[7]);

            if ($idUsuario <= 0 || $uidUsuario === '' || $nombresUsuario === '' || $apellidosUsuario === '') {
                continue;
            }

            $idAutoridad = $validAutoridades[$idAutoridadRaw] ?? false ? $idAutoridadRaw : null;

            $rowsById[$idUsuario] = [
                'id_usuario' => $idUsuario,
                'uid_usuario' => $uidUsuario,
                'pwd_usuario' => Hash::make('123456'),
                'nombres_usuario' => $nombresUsuario,
                'apellidos_usuario' => $apellidosUsuario,
                'telefono_usuario' => $telefonoUsuario,
                'email_usuario' => $emailUsuario,
                'id_autoridad' => $idAutoridad,
                'vigencia_usuario' => $this->normalizeVigencia($vigenciaRaw),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rowsById === []) {
            return;
        }

        $rows = array_values($rowsById);
        $rows = $this->normalizeUniqueFields($rows);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('usuarios')->truncate();
            DB::table('usuarios')->insert($rows);

            $this->applyUpdateFile($validAutoridades);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @param array<int, bool> $validAutoridades
     */
    private function applyUpdateFile(array $validAutoridades): void
    {
        $updateFile = base_path('database/data/update_usuario.sql');

        if (! file_exists($updateFile)) {
            $updateFile = base_path('../DATA/update  usuario');
        }

        if (! file_exists($updateFile)) {
            return;
        }

        $sql = (string) file_get_contents($updateFile);

        if (trim($sql) === '') {
            return;
        }

        preg_match_all('/\(\s*(\d+)\s*,\s*(\d+)\s*,\s*([^\)]+?)\s*\)/', $sql, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $idUsuario = (int) $match[1];
            $idAutoridadRaw = (int) $match[2];
            $vigenciaRaw = trim((string) $match[3]);

            if ($idUsuario <= 0) {
                continue;
            }

            $idAutoridad = isset($validAutoridades[$idAutoridadRaw]) ? $idAutoridadRaw : null;

            DB::table('usuarios')
                ->where('id_usuario', $idUsuario)
                ->update([
                    'id_autoridad' => $idAutoridad,
                    'vigencia_usuario' => $this->normalizeVigencia($vigenciaRaw),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUniqueFields(array $rows): array
    {
        $seenUid = [];
        $seenEmail = [];

        foreach ($rows as &$row) {
            $uidKey = strtolower((string) $row['uid_usuario']);

            if (isset($seenUid[$uidKey])) {
                $row['uid_usuario'] = $row['uid_usuario'].'_'.$row['id_usuario'];
                $uidKey = strtolower((string) $row['uid_usuario']);
            }

            $seenUid[$uidKey] = true;

            $email = $row['email_usuario'];

            if ($email === null) {
                $email = strtolower((string) $row['uid_usuario']).'.'.$row['id_usuario'].'@placeholder.local';
                $row['email_usuario'] = $email;
            }

            $emailKey = strtolower((string) $email);
            if (isset($seenEmail[$emailKey])) {
                $email = strtolower((string) $row['uid_usuario']).'.'.$row['id_usuario'].'@placeholder.local';
                $row['email_usuario'] = $email;
                $emailKey = strtolower($email);
            }

            $seenEmail[$emailKey] = true;
        }

        unset($row);

        return $rows;
    }

    /**
     * @param mixed $value
     */
    private function nullableString($value): ?string
    {
        $text = trim((string) $value);

        if ($text === '' || strtoupper($text) === 'NULL') {
            return null;
        }

        return $text;
    }

    /**
     * @param mixed $value
     */
    private function nullableEmail($value): ?string
    {
        $email = $this->nullableString($value);

        if ($email === null) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function normalizeVigencia(string $value): int
    {
        if ($value === '' || strtoupper($value) === 'NULL') {
            return 1;
        }

        if ($value === '0') {
            return 0;
        }

        if ($value === '1') {
            return 1;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value >= now()->toDateString() ? 1 : 0;
        }

        return 1;
    }
}