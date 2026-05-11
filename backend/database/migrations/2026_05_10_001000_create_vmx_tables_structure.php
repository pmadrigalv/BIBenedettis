<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sessionModeResult = DB::selectOne('SELECT @@SESSION.sql_mode AS mode');
        $originalSqlMode = is_object($sessionModeResult) ? (string) ($sessionModeResult->mode ?? '') : '';

        // These legacy tables use zero-date defaults and MyISAM; relax strict date mode only while creating them.
        DB::statement("SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', '')");

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `vmx_res_ventas` (
  `id_unidad` int(11) NOT NULL DEFAULT '0',
  `id_diaoperativo` int(11) NOT NULL DEFAULT '0',
  `tipo_venta` varchar(1) NOT NULL DEFAULT 'D',
  `id_tipoorden` tinyint(4) NOT NULL DEFAULT '0',
  `porcimp_venta` int(11) NOT NULL DEFAULT '0',
  `subtt_venta` float NOT NULL DEFAULT '0',
  `total_venta` float NOT NULL DEFAULT '0',
  `impto_venta` float NOT NULL DEFAULT '0',
  `numero_venta` int(11) NOT NULL DEFAULT '0',
  `regalias_venta` float NOT NULL DEFAULT '0',
  `esar_venta` float NOT NULL DEFAULT '0',
  `rpl_res_ventas` varchar(1) NOT NULL DEFAULT 'X',
  `ts_venta` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `venta_digital` float(11,2) DEFAULT '0.00',
  PRIMARY KEY (`id_unidad`,`id_diaoperativo`,`tipo_venta`,`id_tipoorden`,`porcimp_venta`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `vmx_res_productos` (
  `id_unidad` int(11) NOT NULL DEFAULT '0',
  `id_diaoperativo` int(11) NOT NULL DEFAULT '0',
  `id_tipoorden` tinyint(4) NOT NULL DEFAULT '0',
  `id_tiporeceta` int(11) NOT NULL DEFAULT '0',
  `id_tamanno` int(11) NOT NULL DEFAULT '0',
  `porcimp_producto` int(11) NOT NULL DEFAULT '0',
  `precio_producto` float NOT NULL DEFAULT '0',
  `subtt_producto` float NOT NULL DEFAULT '0',
  `total_producto` float NOT NULL DEFAULT '0',
  `impto_producto` float NOT NULL DEFAULT '0',
  `numero_producto` int(11) NOT NULL DEFAULT '0',
  `rpl_res_productos` varchar(1) NOT NULL DEFAULT 'X',
  `ts_resproducto` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `numpaq_producto` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_unidad`,`id_diaoperativo`,`id_tipoorden`,`id_tiporeceta`,`id_tamanno`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `vmx_orden` (
  `id_unidad` int(11) NOT NULL DEFAULT '0',
  `id_orden` int(11) NOT NULL,
  `consec_orden` int(11) DEFAULT NULL,
  `id_tipoorden` int(11) NOT NULL DEFAULT '0',
  `id_evac` int(11) NOT NULL DEFAULT '0',
  `id_ers` int(11) NOT NULL DEFAULT '0',
  `id_cliente` int(11) NOT NULL DEFAULT '0',
  `id_telefono` int(11) NOT NULL DEFAULT '0',
  `id_domicilio` int(11) NOT NULL DEFAULT '0',
  `mensaje_orden` varchar(40) NOT NULL,
  `timestamp_orden` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `tsprocesada_orden` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `tsempacada_orden` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `tsareparto_orden` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `tsentregada_orden` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `tscam_orden` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `tsproducida_orden` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `easc_orden` tinyint(1) NOT NULL DEFAULT '0',
  `cam_orden` tinyint(1) NOT NULL DEFAULT '0',
  `aplicocam_orden` int(11) NOT NULL DEFAULT '0',
  `ctemos_orden` varchar(50) NOT NULL,
  `pago_orden` float NOT NULL DEFAULT '0',
  `trecepcion_orden` int(11) NOT NULL DEFAULT '0',
  `tproduccion_orden` int(11) NOT NULL DEFAULT '0',
  `tespera_orden` int(11) NOT NULL DEFAULT '0',
  `treparto_orden` int(11) NOT NULL DEFAULT '0',
  `thorno_orden` int(11) NOT NULL DEFAULT '0',
  `rpl_orden` varchar(1) NOT NULL DEFAULT 'X',
  `id_servicio_digital` int(255) DEFAULT NULL,
  PRIMARY KEY (`id_unidad`,`id_orden`),
  KEY `IX_Relationship352` (`id_tipoorden`),
  KEY `IX_Relationship270` (`id_cliente`,`id_telefono`,`id_domicilio`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `vmx_producto` (
  `id_unidad` int(11) NOT NULL DEFAULT '0',
  `id_orden` int(11) NOT NULL DEFAULT '0',
  `id_producto` int(11) NOT NULL,
  `id_tamanno` int(11) NOT NULL DEFAULT '0',
  `precio_producto` float NOT NULL DEFAULT '0',
  `preciodm_producto` float NOT NULL DEFAULT '0',
  `cantidad_producto` int(11) NOT NULL DEFAULT '0',
  `esadicional_producto` tinyint(1) NOT NULL DEFAULT '0',
  `id_esquemacobro` int(11) NOT NULL DEFAULT '0',
  `impuesto_producto` float NOT NULL DEFAULT '0',
  `paquete_producto` tinyint(1) NOT NULL DEFAULT '0',
  `valido_producto` tinyint(1) DEFAULT '1',
  `validalealtad_producto` int(11) DEFAULT NULL,
  `tprocesado_producto` int(11) NOT NULL DEFAULT '0',
  `canxp_producto` int(11) NOT NULL DEFAULT '0',
  `impporc_producto` int(11) NOT NULL DEFAULT '0',
  `thorno_producto` int(11) NOT NULL DEFAULT '0',
  `canxh_producto` int(11) NOT NULL DEFAULT '0',
  `descripcion_producto` varchar(255) DEFAULT NULL,
  `esquemacobro_producto` varchar(40) DEFAULT NULL,
  `rpl_producto` varchar(1) NOT NULL DEFAULT 'X',
  `canxprod_producto` int(11) NOT NULL DEFAULT '0',
  `nhorno_producto` int(11) NOT NULL DEFAULT '0',
  `codigoval_producto` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_unidad`,`id_orden`,`id_producto`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `vmx_componente` (
  `id_unidad` int(11) NOT NULL DEFAULT '0',
  `id_componente` int(11) NOT NULL DEFAULT '0',
  `id_producto` int(11) NOT NULL DEFAULT '0',
  `id_orden` int(11) NOT NULL DEFAULT '0',
  `incexc_componente` tinyint(1) NOT NULL DEFAULT '0',
  `id_receta` int(11) NOT NULL DEFAULT '0',
  `porcion_componente` tinyint(4) NOT NULL DEFAULT '1',
  `cantidad_componente` int(11) NOT NULL DEFAULT '0',
  `prioridad_componente` int(11) NOT NULL DEFAULT '1',
  `rpl_componente` varchar(1) NOT NULL DEFAULT 'X',
  `rplnube_componente` varchar(100) NOT NULL DEFAULT 'X',
  PRIMARY KEY (`id_unidad`,`id_componente`,`id_producto`,`id_orden`),
  KEY `IX_Relationship381` (`id_receta`),
  KEY `IX_Relationship355` (`id_unidad`,`id_orden`,`id_producto`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
SQL);

        DB::statement('SET SESSION sql_mode = ?', [$originalSqlMode]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `vmx_componente`');
        DB::statement('DROP TABLE IF EXISTS `vmx_producto`');
        DB::statement('DROP TABLE IF EXISTS `vmx_orden`');
        DB::statement('DROP TABLE IF EXISTS `vmx_res_productos`');
        DB::statement('DROP TABLE IF EXISTS `vmx_res_ventas`');
    }
};
