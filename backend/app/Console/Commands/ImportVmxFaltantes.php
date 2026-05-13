<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportVmxFaltantes extends Command
{
    protected $signature   = 'app:import-vmx-faltantes';
    protected $description = 'Importa vmx_res_productos y vmx_res_excepciones desde vmxbe remoto';

    private const REMOTE_DSN  = 'mysql:host=191.101.15.179;port=3307;dbname=vmxbe;charset=utf8';
    private const REMOTE_USER = 'root';
    private const REMOTE_PASS = 'BeneB@ckend2026!';
    private const BATCH_SIZE  = 1000;

    public function handle(): int
    {
        $pdo = new \PDO(self::REMOTE_DSN, self::REMOTE_USER, self::REMOTE_PASS, [
            \PDO::ATTR_TIMEOUT            => 60,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->importVmxResProductos($pdo);
        $this->importVmxResExcepciones($pdo);

        $this->info('Importacion completada.');
        return self::SUCCESS;
    }

    // ── vmx_res_productos ────────────────────────────────────────────────

    private function importVmxResProductos(\PDO $pdo): void
    {
        $this->info('Contando vmx_res_productos remoto...');
        $total = (int) $pdo->query('SELECT COUNT(*) FROM vmx_res_productos')->fetchColumn();
        $this->info("vmx_res_productos: {$total} filas remotas");

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('vmx_res_productos')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $offset = 0;
        $bar    = $this->output->createProgressBar($total);
        $bar->start();

        $stmt = $pdo->prepare(
            'SELECT id_unidad, id_diaoperativo, id_tipoorden, id_tiporeceta, id_tamanno,
                    porcimp_producto, precio_producto, subtt_producto, total_producto,
                    impto_producto, numero_producto, rpl_res_productos, ts_resproducto, numpaq_producto
             FROM vmx_res_productos
             ORDER BY id_unidad, id_diaoperativo
             LIMIT :limit OFFSET :offset'
        );

        while (true) {
            $stmt->bindValue(':limit',  self::BATCH_SIZE, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,          \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                break;
            }

            DB::table('vmx_res_productos')->insertOrIgnore($rows);
            $offset += count($rows);
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();
        $this->info("vmx_res_productos: {$offset} filas importadas.");
    }

    // ── vmx_res_excepciones ──────────────────────────────────────────────

    private function importVmxResExcepciones(\PDO $pdo): void
    {
        $this->info('Procesando vmx_res_excepciones...');

        // Create table if it doesn't exist
        if (! Schema::hasTable('vmx_res_excepciones')) {
            $this->info('Creando tabla vmx_res_excepciones...');
            DB::statement("
                CREATE TABLE vmx_res_excepciones (
                    id_unidad           INT          NOT NULL DEFAULT 0,
                    id_diaoperativo     INT          NOT NULL DEFAULT 0,
                    id_tipoorden        TINYINT      NOT NULL DEFAULT 0,
                    id_excepcion        INT          NOT NULL DEFAULT 0,
                    porcimp_excepciones INT          NOT NULL DEFAULT 0,
                    subtt_excepciones   FLOAT        NOT NULL DEFAULT 0,
                    total_excepciones   FLOAT        NOT NULL DEFAULT 0,
                    impto_excepciones   FLOAT        NOT NULL DEFAULT 0,
                    numero_excepciones  INT          NOT NULL DEFAULT 0,
                    rpl_res_excepciones VARCHAR(1)   NOT NULL DEFAULT 'X',
                    ts_resexcepciones   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                              ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_unidad, id_diaoperativo, id_tipoorden, id_excepcion)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");
        }

        $total = (int) $pdo->query('SELECT COUNT(*) FROM vmx_res_excepciones')->fetchColumn();
        $this->info("vmx_res_excepciones: {$total} filas remotas");

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('vmx_res_excepciones')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $offset = 0;
        $bar    = $this->output->createProgressBar($total);
        $bar->start();

        $stmt = $pdo->prepare(
            'SELECT id_unidad, id_diaoperativo, id_tipoorden, id_excepcion,
                    porcimp_excepciones, subtt_excepciones, total_excepciones,
                    impto_excepciones, numero_excepciones, rpl_res_excepciones, ts_resexcepciones
             FROM vmx_res_excepciones
             LIMIT :limit OFFSET :offset'
        );

        while (true) {
            $stmt->bindValue(':limit',  self::BATCH_SIZE, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,          \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            if (empty($rows)) {
                break;
            }

            DB::table('vmx_res_excepciones')->insertOrIgnore($rows);
            $offset += count($rows);
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();
        $this->info("vmx_res_excepciones: {$offset} filas importadas.");
    }
}
