<?php

require_once __DIR__ . '/controllers/VentasController.php';
require_once __DIR__ . '/config/app.php';

$connections = require __DIR__ . '/config/database.php';

if (session_id() === '') {
    session_start();
}

if (!isset($_SESSION['usuario_auth']) || !is_array($_SESSION['usuario_auth'])) {
    header('Location: ' . appUrl('/login.php'));
    exit;
}

$usuarioAuthSesion = $_SESSION['usuario_auth'];
$idUsuarioSesion = isset($usuarioAuthSesion['id_usuario']) ? (int)$usuarioAuthSesion['id_usuario'] : 0;
$nombreUsuarioSesion = trim(
    (isset($usuarioAuthSesion['nombres_usuario']) ? (string)$usuarioAuthSesion['nombres_usuario'] : '') . ' '
    . (isset($usuarioAuthSesion['apellidos_usuario']) ? (string)$usuarioAuthSesion['apellidos_usuario'] : '')
);
if ($nombreUsuarioSesion === '') {
    $nombreUsuarioSesion = isset($usuarioAuthSesion['razsoc_usuario'])
        ? (string)$usuarioAuthSesion['razsoc_usuario']
        : '';
}

$modoVistaForzado = isset($modoVistaForzado) ? (string)$modoVistaForzado : '';

$modoVistaParam = $modoVistaForzado !== '' ? $modoVistaForzado : (isset($_GET['modo']) ? $_GET['modo'] : 'dia');
$modoVista = in_array($modoVistaParam, array('dia', 'rango', 'unidad', 'orillas'), true) ? $modoVistaParam : 'dia';
$esModoUnidadDetallado = in_array($modoVista, array('unidad', 'orillas'), true);
$modoRapido = isset($_GET['modo_rapido']) && $_GET['modo_rapido'] === '1';
$fechaMaximaSeleccion = date('Y-m-d');
$fechaSeleccionada = isset($_GET['id_diaoperativo']) ? $_GET['id_diaoperativo'] : '';
$fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$idUnidadFiltro = isset($_GET['id_unidad']) ? (int)$_GET['id_unidad'] : 0;
$fechaSeleccionadaCompleta = '';
$fechaInicioCompleta = '';
$fechaFinCompleta = '';
$idDiaOperativo = '';
$idDiaOperativoInicio = '';
$idDiaOperativoFin = '';
$fechaComparativa = '';
$fechaComparativaInicio = '';
$fechaComparativaFin = '';
$fechaComparativaCompleta = '';
$fechaComparativaInicioCompleta = '';
$fechaComparativaFinCompleta = '';
$idDiaOperativoComparativo = '';
$idDiaOperativoComparativoInicio = '';
$idDiaOperativoComparativoFin = '';
$tituloReporte = 'Consulta Dia Actual vs Año Anterior';
$textoPeriodoActual = '';
$textoPeriodoComparativo = '';
$usuarios = array();
$unidadesPorSupervisor = array();
$acumuladoSemanaPorDia = array();
$acumuladoSemanaTitulo = '';
$fechaCorteAcumulado = '';
$anioPto = (int)date('Y');
$unidadesCatalogo = array();
$nombreUnidadFiltro = '';
$mostrarApartadoUnidad = false;
$categoriasTamannoUnidad = array();
$promocionesFilas = array();
$promocionesTotalFx2025 = 0;
$promocionesTotalFx2026 = 0;
$mostrarPromocionesUnidad = true;
$anioFx2026Unidad = (int)date('Y');
$error = '';

if ($modoVista === 'dia' && $fechaSeleccionada !== '') {
	try {
    validarFechaNoFutura($fechaSeleccionada, $fechaMaximaSeleccion);
        $idDiaOperativo = convertirFechaADiaOperativo($fechaSeleccionada);
        $fechaSeleccionadaCompleta = formatearFechaCompletaEs($fechaSeleccionada);
		$fechaComparativa = obtenerFechaMismoDiaSemanaAnioAnterior($fechaSeleccionada);
		$fechaComparativaCompleta = formatearFechaCompletaEs($fechaComparativa);
		$idDiaOperativoComparativo = convertirFechaADiaOperativo($fechaComparativa);
        $tituloReporte = 'Consulta Dia Actual vs Año Anterior';
        $textoPeriodoActual = $fechaSeleccionadaCompleta;
        $textoPeriodoComparativo = $fechaComparativaCompleta;
        $fechaCorteAcumulado = $fechaSeleccionada;
        $anioPto = (int)date('Y', strtotime($fechaSeleccionada));
		$controller = new VentasController($connections);
        $usuarios = $controller->consultarUsuariosSupervisoresActivos('ssql_relaciones');

        $supervisoresIds = array();
        foreach ($usuarios as $usuario) {
            $supervisorId = isset($usuario['id_usuario']) ? (int)$usuario['id_usuario'] : 0;
            if ($supervisorId > 0) {
                $supervisoresIds[] = $supervisorId;
            }
        }
        $supervisoresIds = array_values(array_unique($supervisoresIds));

        $unidadesPorSupervisorId = array();
        if (!empty($supervisoresIds)) {
            $unidadesActivas = $controller->consultarUnidadesActivasPorSupervisores(
                'ssql_relaciones',
                $supervisoresIds,
                $fechaSeleccionada
            );
            foreach ($unidadesActivas as $unidadActiva) {
                $idSupervisorUnidad = isset($unidadActiva['supervisor']) ? (int)$unidadActiva['supervisor'] : 0;
                if ($idSupervisorUnidad <= 0) {
                    continue;
                }
                if (!isset($unidadesPorSupervisorId[$idSupervisorUnidad])) {
                    $unidadesPorSupervisorId[$idSupervisorUnidad] = array();
                }
                $unidadesPorSupervisorId[$idSupervisorUnidad][] = $unidadActiva;
            }
        }

        $unidadesIds = array();
        $unidadesDetalle = array();
        $unidadesIdsComparativo = array();
        foreach ($unidadesPorSupervisorId as $listaUnidadesSupervisor) {
            foreach ($listaUnidadesSupervisor as $unidadSupervisor) {
                $idUnidad = isset($unidadSupervisor['id_unidad']) ? (int)$unidadSupervisor['id_unidad'] : 0;
                if ($idUnidad > 0) {
                    $unidadesIds[] = $idUnidad;

                    $fechaAperturaUnidad = isset($unidadSupervisor['fapertura_unidad'])
                        ? trim((string)$unidadSupervisor['fapertura_unidad'])
                        : '';
                    $fechaAperturaSoloFecha = $fechaAperturaUnidad !== '' ? substr($fechaAperturaUnidad, 0, 10) : '';
                    $unidadesDetalle[$idUnidad] = $fechaAperturaSoloFecha;
                    if (unidadAplicaEnComparativo($fechaAperturaSoloFecha, $fechaComparativa)) {
                        $unidadesIdsComparativo[] = $idUnidad;
                    }
                }
            }
        }
        $unidadesIds = array_values(array_unique($unidadesIds));
        $unidadesIdsComparativo = array_values(array_unique($unidadesIdsComparativo));

        $totalesComparativoPorUnidad = $controller->consultarTotalesPorUnidadesDia(
            'venta',
            $idDiaOperativoComparativo,
            $unidadesIdsComparativo
        );
        $totalesActualPorUnidad = $controller->consultarTotalesPorUnidadesDia(
            'venta',
            $idDiaOperativo,
            $unidadesIds
        );
        $transaccionesComparativoPorUnidad = $controller->consultarTransaccionesPorUnidadesDia(
            'venta',
            $idDiaOperativoComparativo,
            $unidadesIdsComparativo
        );
        $transaccionesActualPorUnidad = $controller->consultarTransaccionesPorUnidadesDia(
            'venta',
            $idDiaOperativo,
            $unidadesIds
        );
        $presupuestosPorUnidad = $controller->consultarPresupuestoPorUnidades(
            'ssql_relaciones',
            $unidadesIds,
            $anioPto,
            $idDiaOperativo,
            $idDiaOperativo
        );

        if (!$modoRapido) {
            $acumuladoSemana = construirAcumuladoSemanaPorDia(
                $controller,
                $unidadesDetalle,
                $fechaSeleccionada,
                $presupuestosPorUnidad
            );
            $acumuladoSemanaPorDia = isset($acumuladoSemana['filas']) ? $acumuladoSemana['filas'] : array();
            $acumuladoSemanaTitulo = isset($acumuladoSemana['titulo']) ? $acumuladoSemana['titulo'] : '';
        }

        foreach ($usuarios as $usuario) {
            $supervisorId = isset($usuario['id_usuario']) ? (int)$usuario['id_usuario'] : 0;
            $supervisorClave = isset($usuario['id_usuario']) ? (string)$usuario['id_usuario'] : '';
            $supervisorNombre = trim(
                (isset($usuario['nombres_usuario']) ? $usuario['nombres_usuario'] : '') . ' '
                . (isset($usuario['apellidos_usuario']) ? $usuario['apellidos_usuario'] : '')
            );

            if ($supervisorId <= 0) {
                continue;
            }

            $unidadesSupervisor = isset($unidadesPorSupervisorId[$supervisorId])
                ? $unidadesPorSupervisorId[$supervisorId]
                : array();
            usort($unidadesSupervisor, function ($a, $b) {
                $idA = isset($a['id_unidad']) ? (int)$a['id_unidad'] : 0;
                $idB = isset($b['id_unidad']) ? (int)$b['id_unidad'] : 0;
                if ($idA == $idB) {
                    return 0;
                }
                return ($idA < $idB) ? -1 : 1;
            });
            foreach ($unidadesSupervisor as &$unidadSupervisor) {
                $idUnidad = isset($unidadSupervisor['id_unidad']) ? (int)$unidadSupervisor['id_unidad'] : 0;
                $unidadSupervisor['fx_ac'] = ($idUnidad > 0 && isset($totalesComparativoPorUnidad[$idUnidad]))
                    ? (float)$totalesComparativoPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['fx_ap'] = ($idUnidad > 0 && isset($totalesActualPorUnidad[$idUnidad]))
                    ? (float)$totalesActualPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['txn_ac'] = ($idUnidad > 0 && isset($transaccionesComparativoPorUnidad[$idUnidad]))
                    ? (int)$transaccionesComparativoPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['txn_ap'] = ($idUnidad > 0 && isset($transaccionesActualPorUnidad[$idUnidad]))
                    ? (int)$transaccionesActualPorUnidad[$idUnidad]
                    : 0;

                $unidadSupervisor['var'] = (float)$unidadSupervisor['fx_ap'] - (float)$unidadSupervisor['fx_ac'];
                if ((float)$unidadSupervisor['fx_ac'] == 0.0) {
                    $unidadSupervisor['porcentaje_ap'] = 0;
                } else {
                    $unidadSupervisor['porcentaje_ap'] =
                        ($unidadSupervisor['var'] / (float)$unidadSupervisor['fx_ac']) * 100;
                }

                $unidadSupervisor['presupuesto'] = ($idUnidad > 0 && isset($presupuestosPorUnidad[$idUnidad]))
                    ? (float)$presupuestosPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['variacion_pto'] = $controller->calcularVariacionPto(
                    $unidadSupervisor['presupuesto'],
                    $unidadSupervisor['fx_ap']
                );
                $unidadSupervisor['porcentaje_aa'] = $controller->calcularPorcentajeAa(
                    $unidadSupervisor['presupuesto'],
                    $unidadSupervisor['fx_ap']
                );
            }
            unset($unidadSupervisor);

            $unidadesPorSupervisor[] = array(
                'supervisor' => $supervisorNombre !== '' ? $supervisorNombre : $supervisorClave,
                'unidades' => $unidadesSupervisor,
            );
        }
	} catch (Exception $e) {
		$error = $e->getMessage();
	}
}

if ($modoVista === 'rango' && $fechaInicio !== '' && $fechaFin !== '') {
    try {
        $fechaInicioDate = DateTime::createFromFormat('Y-m-d', $fechaInicio);
        $fechaFinDate = DateTime::createFromFormat('Y-m-d', $fechaFin);
        if (
            !$fechaInicioDate || $fechaInicioDate->format('Y-m-d') !== $fechaInicio
            || !$fechaFinDate || $fechaFinDate->format('Y-m-d') !== $fechaFin
        ) {
            throw new InvalidArgumentException('Las fechas del rango no tienen formato válido.');
        }
        validarFechaNoFutura($fechaInicio, $fechaMaximaSeleccion);
        validarFechaNoFutura($fechaFin, $fechaMaximaSeleccion);
        if ($fechaInicioDate > $fechaFinDate) {
            throw new InvalidArgumentException('La fecha inicial no puede ser mayor que la fecha final.');
        }

        $idDiaOperativoInicio = convertirFechaADiaOperativo($fechaInicio);
        $idDiaOperativoFin = convertirFechaADiaOperativo($fechaFin);
        $fechaInicioCompleta = formatearFechaCompletaEs($fechaInicio);
        $fechaFinCompleta = formatearFechaCompletaEs($fechaFin);

        $fechaComparativaInicio = obtenerFechaMismoDiaSemanaAnioAnterior($fechaInicio);
        $fechaComparativaFin = obtenerFechaMismoDiaSemanaAnioAnterior($fechaFin);
        $idDiaOperativoComparativoInicio = convertirFechaADiaOperativo($fechaComparativaInicio);
        $idDiaOperativoComparativoFin = convertirFechaADiaOperativo($fechaComparativaFin);
        $fechaComparativaInicioCompleta = formatearFechaCompletaEs($fechaComparativaInicio);
        $fechaComparativaFinCompleta = formatearFechaCompletaEs($fechaComparativaFin);

        $tituloReporte = 'Consulta por Rango vs Año Anterior';
        $textoPeriodoActual = $fechaInicioCompleta . ' a ' . $fechaFinCompleta;
        $textoPeriodoComparativo = $fechaComparativaInicioCompleta . ' a ' . $fechaComparativaFinCompleta;
        $fechaCorteAcumulado = $fechaFin;
        $anioPto = (int)date('Y', strtotime($fechaFin));

        $controller = new VentasController($connections);
        $usuarios = $controller->consultarUsuariosSupervisoresActivos('ssql_relaciones');

        $supervisoresIds = array();
        foreach ($usuarios as $usuario) {
            $supervisorId = isset($usuario['id_usuario']) ? (int)$usuario['id_usuario'] : 0;
            if ($supervisorId > 0) {
                $supervisoresIds[] = $supervisorId;
            }
        }
        $supervisoresIds = array_values(array_unique($supervisoresIds));

        $unidadesPorSupervisorId = array();
        if (!empty($supervisoresIds)) {
            $unidadesActivas = $controller->consultarUnidadesActivasPorSupervisores(
                'ssql_relaciones',
                $supervisoresIds,
                $fechaFin
            );
            foreach ($unidadesActivas as $unidadActiva) {
                $idSupervisorUnidad = isset($unidadActiva['supervisor']) ? (int)$unidadActiva['supervisor'] : 0;
                if ($idSupervisorUnidad <= 0) {
                    continue;
                }
                if (!isset($unidadesPorSupervisorId[$idSupervisorUnidad])) {
                    $unidadesPorSupervisorId[$idSupervisorUnidad] = array();
                }
                $unidadesPorSupervisorId[$idSupervisorUnidad][] = $unidadActiva;
            }
        }

        $unidadesIds = array();
        $unidadesDetalle = array();
        $unidadesIdsComparativo = array();
        foreach ($unidadesPorSupervisorId as $listaUnidadesSupervisor) {
            foreach ($listaUnidadesSupervisor as $unidadSupervisor) {
                $idUnidad = isset($unidadSupervisor['id_unidad']) ? (int)$unidadSupervisor['id_unidad'] : 0;
                if ($idUnidad > 0) {
                    $unidadesIds[] = $idUnidad;

                    $fechaAperturaUnidad = isset($unidadSupervisor['fapertura_unidad'])
                        ? trim((string)$unidadSupervisor['fapertura_unidad'])
                        : '';
                    $fechaAperturaSoloFecha = $fechaAperturaUnidad !== '' ? substr($fechaAperturaUnidad, 0, 10) : '';
                    $unidadesDetalle[$idUnidad] = $fechaAperturaSoloFecha;
                    if (unidadAplicaEnComparativo($fechaAperturaSoloFecha, $fechaComparativaFin)) {
                        $unidadesIdsComparativo[] = $idUnidad;
                    }
                }
            }
        }
        $unidadesIds = array_values(array_unique($unidadesIds));
        $unidadesIdsComparativo = array_values(array_unique($unidadesIdsComparativo));

        $totalesComparativoPorUnidad = $controller->consultarTotalesPorUnidadesRango(
            'venta',
            $idDiaOperativoComparativoInicio,
            $idDiaOperativoComparativoFin,
            $unidadesIdsComparativo
        );
        $totalesActualPorUnidad = $controller->consultarTotalesPorUnidadesRango(
            'venta',
            $idDiaOperativoInicio,
            $idDiaOperativoFin,
            $unidadesIds
        );
        $transaccionesComparativoPorUnidad = $controller->consultarTransaccionesPorUnidadesRango(
            'venta',
            $idDiaOperativoComparativoInicio,
            $idDiaOperativoComparativoFin,
            $unidadesIdsComparativo
        );
        $transaccionesActualPorUnidad = $controller->consultarTransaccionesPorUnidadesRango(
            'venta',
            $idDiaOperativoInicio,
            $idDiaOperativoFin,
            $unidadesIds
        );
        $presupuestosPorUnidad = $controller->consultarPresupuestoPorUnidades(
            'ssql_relaciones',
            $unidadesIds,
            $anioPto,
            $idDiaOperativoInicio,
            $idDiaOperativoFin
        );

        if (!$modoRapido) {
            $acumuladoSemana = construirAcumuladoRangoPorDia(
                $controller,
                $unidadesDetalle,
                $fechaInicio,
                $fechaFin,
                $presupuestosPorUnidad
            );
            $acumuladoSemanaPorDia = isset($acumuladoSemana['filas']) ? $acumuladoSemana['filas'] : array();
            $acumuladoSemanaTitulo = isset($acumuladoSemana['titulo']) ? $acumuladoSemana['titulo'] : '';
        }

        foreach ($usuarios as $usuario) {
            $supervisorId = isset($usuario['id_usuario']) ? (int)$usuario['id_usuario'] : 0;
            $supervisorClave = isset($usuario['id_usuario']) ? (string)$usuario['id_usuario'] : '';
            $supervisorNombre = trim(
                (isset($usuario['nombres_usuario']) ? $usuario['nombres_usuario'] : '') . ' '
                . (isset($usuario['apellidos_usuario']) ? $usuario['apellidos_usuario'] : '')
            );

            if ($supervisorId <= 0) {
                continue;
            }

            $unidadesSupervisor = isset($unidadesPorSupervisorId[$supervisorId])
                ? $unidadesPorSupervisorId[$supervisorId]
                : array();
            usort($unidadesSupervisor, function ($a, $b) {
                $idA = isset($a['id_unidad']) ? (int)$a['id_unidad'] : 0;
                $idB = isset($b['id_unidad']) ? (int)$b['id_unidad'] : 0;
                if ($idA == $idB) {
                    return 0;
                }
                return ($idA < $idB) ? -1 : 1;
            });
            foreach ($unidadesSupervisor as &$unidadSupervisor) {
                $idUnidad = isset($unidadSupervisor['id_unidad']) ? (int)$unidadSupervisor['id_unidad'] : 0;
                $unidadSupervisor['fx_ac'] = ($idUnidad > 0 && isset($totalesComparativoPorUnidad[$idUnidad]))
                    ? (float)$totalesComparativoPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['fx_ap'] = ($idUnidad > 0 && isset($totalesActualPorUnidad[$idUnidad]))
                    ? (float)$totalesActualPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['txn_ac'] = ($idUnidad > 0 && isset($transaccionesComparativoPorUnidad[$idUnidad]))
                    ? (int)$transaccionesComparativoPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['txn_ap'] = ($idUnidad > 0 && isset($transaccionesActualPorUnidad[$idUnidad]))
                    ? (int)$transaccionesActualPorUnidad[$idUnidad]
                    : 0;

                $unidadSupervisor['var'] = (float)$unidadSupervisor['fx_ap'] - (float)$unidadSupervisor['fx_ac'];
                if ((float)$unidadSupervisor['fx_ac'] == 0.0) {
                    $unidadSupervisor['porcentaje_ap'] = 0;
                } else {
                    $unidadSupervisor['porcentaje_ap'] =
                        ($unidadSupervisor['var'] / (float)$unidadSupervisor['fx_ac']) * 100;
                }

                $unidadSupervisor['presupuesto'] = ($idUnidad > 0 && isset($presupuestosPorUnidad[$idUnidad]))
                    ? (float)$presupuestosPorUnidad[$idUnidad]
                    : 0;
                $unidadSupervisor['variacion_pto'] = $controller->calcularVariacionPto(
                    $unidadSupervisor['presupuesto'],
                    $unidadSupervisor['fx_ap']
                );
                $unidadSupervisor['porcentaje_aa'] = $controller->calcularPorcentajeAa(
                    $unidadSupervisor['presupuesto'],
                    $unidadSupervisor['fx_ap']
                );
            }
            unset($unidadSupervisor);

            $unidadesPorSupervisor[] = array(
                'supervisor' => $supervisorNombre !== '' ? $supervisorNombre : $supervisorClave,
                'unidades' => $unidadesSupervisor,
            );
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($esModoUnidadDetallado) {
    try {
        $controller = new VentasController($connections);
        $fechaReferenciaUnidad = $fechaFin !== '' ? $fechaFin : null;
        $unidadesCatalogo = $controller->consultarUnidadesActivas('ssql_relaciones', $fechaReferenciaUnidad);

        if ($fechaInicio !== '' && $fechaFin !== '' && $idUnidadFiltro > 0) {
            $fechaInicioDate = DateTime::createFromFormat('Y-m-d', $fechaInicio);
            $fechaFinDate = DateTime::createFromFormat('Y-m-d', $fechaFin);
            if (
                !$fechaInicioDate || $fechaInicioDate->format('Y-m-d') !== $fechaInicio
                || !$fechaFinDate || $fechaFinDate->format('Y-m-d') !== $fechaFin
            ) {
                throw new InvalidArgumentException('Las fechas del rango no tienen formato válido.');
            }
            validarFechaNoFutura($fechaInicio, $fechaMaximaSeleccion);
            validarFechaNoFutura($fechaFin, $fechaMaximaSeleccion);
            if ($fechaInicioDate > $fechaFinDate) {
                throw new InvalidArgumentException('La fecha inicial no puede ser mayor que la fecha final.');
            }

            foreach ($unidadesCatalogo as $unidadCatalogo) {
                $idUnidadCatalogo = isset($unidadCatalogo['id_unidad']) ? (int)$unidadCatalogo['id_unidad'] : 0;
                if ($idUnidadCatalogo === $idUnidadFiltro) {
                    $nombreUnidadFiltro = isset($unidadCatalogo['nombre_unidad'])
                        ? trim((string)$unidadCatalogo['nombre_unidad'])
                        : '';
                    if ($nombreUnidadFiltro === '') {
                        $nombreUnidadFiltro = 'Unidad #' . $idUnidadCatalogo;
                    } elseif (e($nombreUnidadFiltro) === '') {
                        $nombreUnidadFiltro = 'Unidad #' . $idUnidadCatalogo;
                    }
                    break;
                }
            }

            if ($nombreUnidadFiltro === '') {
                throw new InvalidArgumentException('La unidad seleccionada no es válida.');
            }

            $idDiaOperativoInicioUnidad = convertirFechaADiaOperativo($fechaInicio);
            $idDiaOperativoFinUnidad = convertirFechaADiaOperativo($fechaFin);
            $fechaComparativaInicioUnidad = obtenerFechaMismoDiaSemanaAnioAnterior($fechaInicio);
            $fechaComparativaFinUnidad = obtenerFechaMismoDiaSemanaAnioAnterior($fechaFin);
            $idDiaOperativoInicioUnidadComparativo = convertirFechaADiaOperativo($fechaComparativaInicioUnidad);
            $idDiaOperativoFinUnidadComparativo = convertirFechaADiaOperativo($fechaComparativaFinUnidad);
            $anioFx2026Unidad = (int)date('Y', strtotime($fechaFin));

            $tamannosVigentes = $controller->consultarTamannosVigentesPorTipo('ssql_relaciones');
            $catalogosTamannoPorTipo = array();
            $idsTamannoUnidadConsolidados = array();
            $catalogoTamannoUnidad = array();

            foreach ($tamannosVigentes as $tamannoVigente) {
                $idTamannoVigente = isset($tamannoVigente['id_tamanno']) ? (int)$tamannoVigente['id_tamanno'] : 0;
                if ($idTamannoVigente <= 0) {
                    continue;
                }

                $tipoTamannoVigente = isset($tamannoVigente['tipo']) ? trim((string)$tamannoVigente['tipo']) : '';
                if ($tipoTamannoVigente === '' || $tipoTamannoVigente === '0') {
                    continue;
                }

                $nombreTamannoVigente = isset($tamannoVigente['nombre']) ? trim((string)$tamannoVigente['nombre']) : '';
                if ($nombreTamannoVigente === '') {
                    $nombreTamannoVigente = 'TAMANNO #' . $idTamannoVigente;
                }

                if (!isset($catalogosTamannoPorTipo[$tipoTamannoVigente])) {
                    $catalogosTamannoPorTipo[$tipoTamannoVigente] = array();
                }

                $catalogosTamannoPorTipo[$tipoTamannoVigente][$idTamannoVigente] = $nombreTamannoVigente;
                $catalogoTamannoUnidad[$idTamannoVigente] = $nombreTamannoVigente;
                $idsTamannoUnidadConsolidados[] = $idTamannoVigente;
            }

            $idsTamannoUnidadConsolidados = array_values(array_unique($idsTamannoUnidadConsolidados));
            sort($idsTamannoUnidadConsolidados, SORT_NUMERIC);

            if (empty($idsTamannoUnidadConsolidados)) {
                throw new InvalidArgumentException('No se encontraron tamanos vigentes en Tablero.tamanno.');
            }

            ksort($catalogosTamannoPorTipo);

            $cantidadesTamannoUnidadActual = $controller->consultarCantidadPorTamannoRangoOperativo(
                'venta',
                $idUnidadFiltro,
                $idDiaOperativoInicioUnidad,
                $idDiaOperativoFinUnidad,
                $idsTamannoUnidadConsolidados
            );
            $cantidadesTamannoUnidadComparativo = $controller->consultarCantidadPorTamannoRangoOperativo(
                'venta',
                $idUnidadFiltro,
                $idDiaOperativoInicioUnidadComparativo,
                $idDiaOperativoFinUnidadComparativo,
                $idsTamannoUnidadConsolidados
            );

            $categoriasTamannoUnidad = array();
            $slugsCategoriasTamanno = array();

            foreach ($catalogosTamannoPorTipo as $tipoTamannoCategoria => $catalogoTamannoCategoria) {
                $filasCategoriaTamanno = array();
                $categoriaTotalFx2025 = 0;
                $categoriaTotalFx2026 = 0;

                foreach ($catalogoTamannoCategoria as $idTamannoCategoria => $nombreTamannoCategoria) {
                    $fx2025 = isset($cantidadesTamannoUnidadComparativo[$idTamannoCategoria])
                        ? (float)$cantidadesTamannoUnidadComparativo[$idTamannoCategoria]
                        : 0;
                    $fx2026 = isset($cantidadesTamannoUnidadActual[$idTamannoCategoria])
                        ? (float)$cantidadesTamannoUnidadActual[$idTamannoCategoria]
                        : 0;
                    $var = $fx2026 - $fx2025;
                    $porcentaje = $fx2025 == 0 ? 0 : (($var / $fx2025) * 100);

                    $categoriaTotalFx2025 += $fx2025;
                    $categoriaTotalFx2026 += $fx2026;

                    $filasCategoriaTamanno[] = array(
                        'id_tamanno' => (int)$idTamannoCategoria,
                        'nombre' => $nombreTamannoCategoria,
                        'fx_2025' => $fx2025,
                        'fx_2026' => $fx2026,
                        'porcentaje_ap' => $porcentaje,
                        'variacion' => $var,
                    );
                }

                $slugTipo = strtolower((string)$tipoTamannoCategoria);
                $slugTipo = preg_replace('/[^a-z0-9]+/', '-', $slugTipo);
                $slugTipo = trim((string)$slugTipo, '-');
                if ($slugTipo === '') {
                    $slugTipo = 'sin-tipo';
                }

                $slugTipoBase = $slugTipo;
                $indiceSlug = 2;
                while (isset($slugsCategoriasTamanno[$slugTipo])) {
                    $slugTipo = $slugTipoBase . '-' . $indiceSlug;
                    $indiceSlug++;
                }
                $slugsCategoriasTamanno[$slugTipo] = true;

                $categoriasTamannoUnidad[] = array(
                    'tipo' => $tipoTamannoCategoria,
                    'titulo' => $tipoTamannoCategoria . ' (orden)',
                    'slug' => $slugTipo,
                    'filas' => $filasCategoriaTamanno,
                    'total_fx_2025' => $categoriaTotalFx2025,
                    'total_fx_2026' => $categoriaTotalFx2026,
                );
            }

            if ($modoVista === 'orillas') {
                $tiposOrillaReceta = array(
                    array('id_receta' => 82, 'nombre' => 'Orilla queso', 'slug' => 'orilla-queso'),
                    array('id_receta' => 590, 'nombre' => 'Orilla queso habanero', 'slug' => 'orilla-queso-habanero'),
                    array('id_receta' => 591, 'nombre' => 'Orilla queso chipotle', 'slug' => 'orilla-queso-chipotle'),
                    array('id_receta' => 592, 'nombre' => 'Orilla queso bbq', 'slug' => 'orilla-queso-bbq'),
                );
                $idsTamannoPizza = array();

                foreach ($catalogosTamannoPorTipo as $tipoTamannoCategoria => $catalogoTamannoCategoria) {
                    if (trim((string)$tipoTamannoCategoria) !== 'PIZZA') {
                        continue;
                    }

                    foreach ($catalogoTamannoCategoria as $idTamannoPizza => $nombreTamannoPizza) {
                        $idsTamannoPizza[] = (int)$idTamannoPizza;
                    }
                }

                $idsTamannoPizza = array_values(array_unique($idsTamannoPizza));
                sort($idsTamannoPizza, SORT_NUMERIC);

                if (empty($idsTamannoPizza)) {
                    throw new InvalidArgumentException(
                        'No se encontraron tamaños con tipo pizza en Tablero.tamanno.'
                    );
                }

                $categoriasTamannoUnidad = array();
                foreach ($tiposOrillaReceta as $tipoOrillaReceta) {
                    $idRecetaOrilla = isset($tipoOrillaReceta['id_receta']) ? (int)$tipoOrillaReceta['id_receta'] : 0;
                    if ($idRecetaOrilla <= 0) {
                        continue;
                    }

                    $nombreTipoOrilla = isset($tipoOrillaReceta['nombre'])
                        ? trim((string)$tipoOrillaReceta['nombre'])
                        : ('Orilla receta #' . $idRecetaOrilla);
                    $slugTipoOrilla = isset($tipoOrillaReceta['slug'])
                        ? trim((string)$tipoOrillaReceta['slug'])
                        : ('orilla-receta-' . $idRecetaOrilla);

                    $cantidadesOrillaActual = $controller->consultarCantidadOrillaQuesoPorTamannoRangoOperativo(
                        'venta',
                        $idUnidadFiltro,
                        $idDiaOperativoInicioUnidad,
                        $idDiaOperativoFinUnidad,
                        $idsTamannoPizza,
                        array($idRecetaOrilla)
                    );
                    $cantidadesOrillaComparativo = $controller->consultarCantidadOrillaQuesoPorTamannoRangoOperativo(
                        'venta',
                        $idUnidadFiltro,
                        $idDiaOperativoInicioUnidadComparativo,
                        $idDiaOperativoFinUnidadComparativo,
                        $idsTamannoPizza,
                        array($idRecetaOrilla)
                    );

                    $filasOrilla = array();
                    $orillaTotalFx2025 = 0;
                    $orillaTotalFx2026 = 0;

                    foreach ($idsTamannoPizza as $idTamannoOrilla) {
                        $nombreTamannoOrilla = isset($catalogoTamannoUnidad[$idTamannoOrilla])
                            ? $catalogoTamannoUnidad[$idTamannoOrilla]
                            : ('TAMANNO #' . (int)$idTamannoOrilla);

                        $fx2025Orilla = isset($cantidadesOrillaComparativo[$idTamannoOrilla])
                            ? (float)$cantidadesOrillaComparativo[$idTamannoOrilla]
                            : 0;
                        $fx2026Orilla = isset($cantidadesOrillaActual[$idTamannoOrilla])
                            ? (float)$cantidadesOrillaActual[$idTamannoOrilla]
                            : 0;
                        if ($fx2025Orilla <= 0 && $fx2026Orilla <= 0) {
                            continue;
                        }

                        $varOrilla = $fx2026Orilla - $fx2025Orilla;
                        $porcentajeOrilla = $fx2025Orilla == 0 ? 0 : (($varOrilla / $fx2025Orilla) * 100);

                        $orillaTotalFx2025 += $fx2025Orilla;
                        $orillaTotalFx2026 += $fx2026Orilla;

                        $filasOrilla[] = array(
                            'id_tamanno' => (int)$idTamannoOrilla,
                            'nombre' => $nombreTamannoOrilla,
                            'fx_2025' => $fx2025Orilla,
                            'fx_2026' => $fx2026Orilla,
                            'porcentaje_ap' => $porcentajeOrilla,
                            'variacion' => $varOrilla,
                        );
                    }

                    $categoriasTamannoUnidad[] = array(
                        'tipo' => $nombreTipoOrilla,
                        'titulo' => $nombreTipoOrilla,
                        'slug' => $slugTipoOrilla,
                        'filas' => $filasOrilla,
                        'total_fx_2025' => $orillaTotalFx2025,
                        'total_fx_2026' => $orillaTotalFx2026,
                        'sin_ventas' => empty($filasOrilla),
                    );
                }
                $mostrarPromocionesUnidad = false;
                $tituloReporte = 'Consulta de Orillas por Rango y Unidad';
            } else {
                $esquemasCobroVigentes = $controller->consultarEsquemasCobroVigentes('ssql_relaciones');
                $catalogoEsquemasCobroVigentes = array();
                foreach ($esquemasCobroVigentes as $esquemaCobroVigente) {
                    $idEsquemaCobroVigente = isset($esquemaCobroVigente['id_esquemacobro'])
                        ? (int)$esquemaCobroVigente['id_esquemacobro']
                        : 0;
                    if ($idEsquemaCobroVigente <= 0) {
                        continue;
                    }

                    $nombreEsquemaCobroVigente = isset($esquemaCobroVigente['nombre'])
                        ? trim((string)$esquemaCobroVigente['nombre'])
                        : '';
                    if ($nombreEsquemaCobroVigente === '') {
                        $nombreEsquemaCobroVigente = 'ESQUEMA #' . $idEsquemaCobroVigente;
                    }

                    $catalogoEsquemasCobroVigentes[$idEsquemaCobroVigente] = $nombreEsquemaCobroVigente;
                }

                if (empty($catalogoEsquemasCobroVigentes)) {
                    throw new InvalidArgumentException(
                        'No se encontraron esquemas de cobro vigentes en Tablero.esquema_cobro.'
                    );
                }

                $idsEsquemaExcluidosPromociones = array(1, 1000, 1001, 1002, 1003, 1004, 1005);

                $idsEsquemaExcluidosPromocionesMapa = array();
                foreach ($idsEsquemaExcluidosPromociones as $idEsquemaExcluidoPromocion) {
                    $idEsquemaExcluidoPromocion = (int)$idEsquemaExcluidoPromocion;
                    if ($idEsquemaExcluidoPromocion <= 0) {
                        continue;
                    }
                    $idsEsquemaExcluidosPromocionesMapa[$idEsquemaExcluidoPromocion] = true;
                }

                $catalogoPromocionesVigentes = array();
                foreach ($catalogoEsquemasCobroVigentes as $idEsquemaCobroVigente => $nombreEsquemaCobroVigente) {
                    if (isset($idsEsquemaExcluidosPromocionesMapa[$idEsquemaCobroVigente])) {
                        continue;
                    }

                    $catalogoPromocionesVigentes[$idEsquemaCobroVigente] = $nombreEsquemaCobroVigente;
                }

                $ticketsEsquemaActual = array();
                $ticketsEsquemaComparativo = array();
                $ticketsEsquemaActual = $controller->consultarTicketsPorEsquemaCobroRangoOperativo(
                    'venta',
                    $idUnidadFiltro,
                    $idDiaOperativoInicioUnidad,
                    $idDiaOperativoFinUnidad,
                    array(),
                    $idsEsquemaExcluidosPromociones,
                    false
                );
                $ticketsEsquemaComparativo = $controller->consultarTicketsPorEsquemaCobroRangoOperativo(
                    'venta',
                    $idUnidadFiltro,
                    $idDiaOperativoInicioUnidadComparativo,
                    $idDiaOperativoFinUnidadComparativo,
                    array(),
                    $idsEsquemaExcluidosPromociones,
                    false
                );

                foreach ($catalogoPromocionesVigentes as $idEsquemaPromocion => $nombrePromocion) {
                    $filaActualPromocion = isset($ticketsEsquemaActual[$idEsquemaPromocion])
                        ? $ticketsEsquemaActual[$idEsquemaPromocion]
                        : array();
                    $filaComparativoPromocion = isset($ticketsEsquemaComparativo[$idEsquemaPromocion])
                        ? $ticketsEsquemaComparativo[$idEsquemaPromocion]
                        : array();

                    $fx2025 = isset($filaComparativoPromocion['cantidad_tickets'])
                        ? (float)$filaComparativoPromocion['cantidad_tickets']
                        : 0;
                    $fx2026 = isset($filaActualPromocion['cantidad_tickets'])
                        ? (float)$filaActualPromocion['cantidad_tickets']
                        : 0;

                    if ($fx2025 <= 0 && $fx2026 <= 0) {
                        continue;
                    }

                    $var = $fx2026 - $fx2025;
                    $porcentaje = $fx2025 == 0 ? 0 : (($var / $fx2025) * 100);

                    $promocionesTotalFx2025 += $fx2025;
                    $promocionesTotalFx2026 += $fx2026;

                    $promocionesFilas[] = array(
                        'id_esquemacobro' => (int)$idEsquemaPromocion,
                        'nombre' => $nombrePromocion,
                        'fx_2025' => $fx2025,
                        'fx_2026' => $fx2026,
                        'porcentaje_ap' => $porcentaje,
                        'variacion' => $var,
                    );
                }

                $tituloReporte = 'Consulta Rep.Ventas por Rango y Unidad';
            }
            $mostrarApartadoUnidad = true;
        } else {
            $tituloReporte = $modoVista === 'orillas'
                ? 'Consulta de Orillas por Rango y Unidad'
                : 'Consulta Rep.Ventas por Rango y Unidad';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$mostrarReporte =
    ($modoVista === 'dia' && $fechaSeleccionada !== '')
    || ($modoVista === 'rango' && $fechaInicio !== '' && $fechaFin !== '');

function convertirFechaADiaOperativo($fecha)
{
    $fechaDate = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaDate || $fechaDate->format('Y-m-d') !== $fecha) {
        throw new InvalidArgumentException('La fecha no tiene formato válido.');
    }

    $anio = $fechaDate->format('Y');
    $diaDelAnio = (int)$fechaDate->format('z') + 1;

    return $anio . str_pad($diaDelAnio, 3, '0', STR_PAD_LEFT);
}

function obtenerFechaMismoDiaSemanaAnioAnterior($fecha)
{
    $fechaDate = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaDate || $fechaDate->format('Y-m-d') !== $fecha) {
        throw new InvalidArgumentException('La fecha no tiene formato válido.');
    }

    $fechaDate->modify('-364 days');
    return $fechaDate->format('Y-m-d');
}

function validarFechaNoFutura($fecha, $fechaMaxima)
{
    if ($fecha === '') {
        return;
    }

    $fechaDate = DateTime::createFromFormat('Y-m-d', $fecha);
    $fechaMaximaDate = DateTime::createFromFormat('Y-m-d', $fechaMaxima);

    if (
        !$fechaDate || $fechaDate->format('Y-m-d') !== $fecha
        || !$fechaMaximaDate || $fechaMaximaDate->format('Y-m-d') !== $fechaMaxima
    ) {
        throw new InvalidArgumentException('La fecha no tiene formato válido.');
    }

    if ($fechaDate > $fechaMaximaDate) {
        throw new InvalidArgumentException('No se permiten fechas futuras. La última fecha seleccionable es hoy.');
    }
}

function e($value)
{
    $texto = (string)$value;

    if (
        $texto !== ''
        && function_exists('mb_check_encoding')
        && function_exists('mb_convert_encoding')
        && !mb_check_encoding($texto, 'UTF-8')
    ) {
        $textoConvertido = @mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
        if ($textoConvertido !== false) {
            $texto = $textoConvertido;
        }
    }

    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatearFechaCompletaEs($fecha)
{
    $fechaDate = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaDate || $fechaDate->format('Y-m-d') !== $fecha) {
        return '';
    }

    $dias = array('domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado');
    $meses = array(
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    );

    $diaSemana = $dias[(int)$fechaDate->format('w')];
    $diaMes = (int)$fechaDate->format('j');
    $mes = $meses[(int)$fechaDate->format('n')];
    $anio = $fechaDate->format('Y');

    return $diaSemana . ' ' . $diaMes . ' de ' . $mes . ' de ' . $anio;
}

function formatearMoneda($valor)
{
    return '$ ' . number_format((float)$valor, 2);
}

function formatearFechaCorta($fecha)
{
    $fechaDate = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaDate || $fechaDate->format('Y-m-d') !== $fecha) {
        return '';
    }

    return $fechaDate->format('d/m/Y');
}

function unidadAplicaEnComparativo($fechaAperturaUnidad, $fechaComparativa)
{
    if ($fechaAperturaUnidad === '') {
        return true;
    }

    return $fechaAperturaUnidad <= $fechaComparativa;
}

function unidadTieneAnioCumplido($fechaAperturaUnidad, $fechaReferencia)
{
    if ($fechaAperturaUnidad === '' || $fechaReferencia === '') {
        return true;
    }

    $fechaAperturaDate = DateTime::createFromFormat('Y-m-d', $fechaAperturaUnidad);
    $fechaReferenciaDate = DateTime::createFromFormat('Y-m-d', $fechaReferencia);
    if (
        !$fechaAperturaDate || $fechaAperturaDate->format('Y-m-d') !== $fechaAperturaUnidad
        || !$fechaReferenciaDate || $fechaReferenciaDate->format('Y-m-d') !== $fechaReferencia
    ) {
        return true;
    }

    $fechaMinimaConAnio = clone $fechaReferenciaDate;
    $fechaMinimaConAnio->modify('-1 year');

    return $fechaAperturaDate <= $fechaMinimaConAnio;
}

function nombreDiaSemanaMayus($fecha)
{
    $fechaDate = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaDate || $fechaDate->format('Y-m-d') !== $fecha) {
        return '';
    }

    $dias = array('DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO');
    return $dias[(int)$fechaDate->format('w')];
}

function obtenerSemanaDelMesPorFecha($fecha)
{
    $fechaDate = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaDate || $fechaDate->format('Y-m-d') !== $fecha) {
        throw new InvalidArgumentException('La fecha no tiene formato válido.');
    }

    $anio = (int)$fechaDate->format('Y');
    $mes = (int)$fechaDate->format('m');
    $diaMes = (int)$fechaDate->format('j');
    $ultimoDiaMes = (int)$fechaDate->format('t');

    $indiceSemana = (int)floor(($diaMes - 1) / 7);
    $diaInicioSemana = ($indiceSemana * 7) + 1;
    $diaFinSemana = $diaInicioSemana + 6;
    if ($diaFinSemana > $ultimoDiaMes) {
        $diaFinSemana = $ultimoDiaMes;
    }

    return array(
        'inicio' => sprintf('%04d-%02d-%02d', $anio, $mes, $diaInicioSemana),
        'fin' => sprintf('%04d-%02d-%02d', $anio, $mes, $diaFinSemana),
        'numero' => $indiceSemana + 1,
    );
}

function construirAcumuladoSemanaPorDia($controller, array $unidadesDetalle, $fechaBase, array $presupuestosPorUnidad)
{
    if ($fechaBase === '') {
        return array('titulo' => '', 'filas' => array());
    }

    $semana = obtenerSemanaDelMesPorFecha($fechaBase);
    $inicioSemana = DateTime::createFromFormat('Y-m-d', $semana['inicio']);
    $finSemana = DateTime::createFromFormat('Y-m-d', $semana['fin']);

    if (!$inicioSemana || !$finSemana) {
        return array('titulo' => '', 'filas' => array());
    }

    $filas = array();
    $fechasSemana = array();
    $fechasComparativas = array();
    $idsDiaActualPorFecha = array();
    $idsDiaComparativoPorFecha = array();

    $cursor = clone $inicioSemana;
    while ($cursor <= $finSemana) {
        $fechaActualDia = $cursor->format('Y-m-d');
        $fechaComparativaDia = obtenerFechaMismoDiaSemanaAnioAnterior($fechaActualDia);

        $fechasSemana[] = $fechaActualDia;
        $fechasComparativas[$fechaActualDia] = $fechaComparativaDia;
        $idsDiaActualPorFecha[$fechaActualDia] = (string)convertirFechaADiaOperativo($fechaActualDia);
        $idsDiaComparativoPorFecha[$fechaActualDia] = (string)convertirFechaADiaOperativo($fechaComparativaDia);

        $cursor->modify('+1 day');
    }

    $idsTodasUnidades = array();
    foreach ($unidadesDetalle as $idUnidad => $fechaAperturaUnidad) {
        $idsTodasUnidades[] = (int)$idUnidad;
    }
    $idsTodasUnidades = array_values(array_unique($idsTodasUnidades));

    $totalesActualSemanaPorDiaUnidad = $controller->consultarTotalesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo($semana['inicio']),
        convertirFechaADiaOperativo($semana['fin']),
        $idsTodasUnidades
    );
    $totalesComparativoSemanaPorDiaUnidad = $controller->consultarTotalesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($semana['inicio'])),
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($semana['fin'])),
        $idsTodasUnidades
    );
    $txnActualSemanaPorDiaUnidad = $controller->consultarTransaccionesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo($semana['inicio']),
        convertirFechaADiaOperativo($semana['fin']),
        $idsTodasUnidades
    );
    $txnComparativoSemanaPorDiaUnidad = $controller->consultarTransaccionesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($semana['inicio'])),
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($semana['fin'])),
        $idsTodasUnidades
    );
    $presupuestoPorDiaUnidad = $controller->consultarPresupuestoPorUnidadesAgrupado(
        'ssql_relaciones',
        $idsTodasUnidades,
        convertirFechaADiaOperativo($semana['inicio']),
        convertirFechaADiaOperativo($semana['fin'])
    );

    foreach ($fechasSemana as $fechaActualDia) {
        $fechaComparativaDia = isset($fechasComparativas[$fechaActualDia]) ? $fechasComparativas[$fechaActualDia] : '';
        $idDiaActual = isset($idsDiaActualPorFecha[$fechaActualDia]) ? $idsDiaActualPorFecha[$fechaActualDia] : '';
        $idDiaComparativo = isset($idsDiaComparativoPorFecha[$fechaActualDia]) ? $idsDiaComparativoPorFecha[$fechaActualDia] : '';

        $totalesActualPorUnidad = isset($totalesActualSemanaPorDiaUnidad[$idDiaActual])
            ? $totalesActualSemanaPorDiaUnidad[$idDiaActual]
            : array();
        $totalesComparativoPorUnidad = isset($totalesComparativoSemanaPorDiaUnidad[$idDiaComparativo])
            ? $totalesComparativoSemanaPorDiaUnidad[$idDiaComparativo]
            : array();
        $txnActualPorUnidad = isset($txnActualSemanaPorDiaUnidad[$idDiaActual])
            ? $txnActualSemanaPorDiaUnidad[$idDiaActual]
            : array();
        $txnComparativoPorUnidad = isset($txnComparativoSemanaPorDiaUnidad[$idDiaComparativo])
            ? $txnComparativoSemanaPorDiaUnidad[$idDiaComparativo]
            : array();

        $fxAc = 0;
        $fxApIguales = 0;
        $fxApNuevas = 0;
        $pto = 0;
        $fxApConPto = 0;
        $txnAc = 0;
        $txnAp = 0;
        $txnApNuevas = 0;

        foreach ($unidadesDetalle as $idUnidad => $fechaAperturaUnidad) {
            $idUnidad = (int)$idUnidad;
            $fxApUnidad = isset($totalesActualPorUnidad[$idUnidad]) ? (float)$totalesActualPorUnidad[$idUnidad] : 0;
            $fxAcUnidad = isset($totalesComparativoPorUnidad[$idUnidad]) ? (float)$totalesComparativoPorUnidad[$idUnidad] : 0;

            $ptoUnidad = isset($presupuestoPorDiaUnidad[$idDiaActual][$idUnidad])
                ? (float)$presupuestoPorDiaUnidad[$idDiaActual][$idUnidad]
                : 0;
            if ($ptoUnidad > 0) {
                $pto += $ptoUnidad;
                $fxApConPto += $fxApUnidad;
            }

            if (!unidadTieneAnioCumplido($fechaAperturaUnidad, $fechaActualDia)) {
                $fxApNuevas += $fxApUnidad;
                $txnApNuevas += isset($txnActualPorUnidad[$idUnidad]) ? (int)$txnActualPorUnidad[$idUnidad] : 0;
                continue;
            }

            $fxApIguales += $fxApUnidad;
            $txnAp += isset($txnActualPorUnidad[$idUnidad]) ? (int)$txnActualPorUnidad[$idUnidad] : 0;
            if (unidadAplicaEnComparativo($fechaAperturaUnidad, $fechaComparativaDia)) {
                $fxAc += $fxAcUnidad;
                $txnAc += isset($txnComparativoPorUnidad[$idUnidad]) ? (int)$txnComparativoPorUnidad[$idUnidad] : 0;
            }
        }

        $fxAp = $fxApIguales;
        $var = $fxAp - $fxAc;
        $porcentaje = $fxAc == 0 ? 0 : (($var / $fxAc) * 100);
        $variacionPto = $controller->calcularVariacionPto($pto, $fxApConPto);
        $porcentajeAa = $controller->calcularPorcentajeAa($pto, $fxApConPto);

        $filas[] = array(
            'dia' => nombreDiaSemanaMayus($fechaActualDia),
            'fecha_actual' => $fechaActualDia,
            'fecha_comparativa' => $fechaComparativaDia,
            'fx_ac' => $fxAc,
            'fx_ap' => $fxAp,
            'fx_ap_iguales' => $fxApIguales,
            'fx_ap_nuevas' => $fxApNuevas,
            'fx_ap_con_pto' => $fxApConPto,
            'pto' => $pto,
            'var' => $var,
            'porcentaje_ap' => $porcentaje,
            'variacion_pto' => $variacionPto,
            'porcentaje_aa' => $porcentajeAa,
            'txn_ac' => $txnAc,
            'txn_ap' => $txnAp,
            'txn_ap_nuevas' => $txnApNuevas,
        );
    }

    return array(
        'titulo' => 'SEMANA ' . $semana['numero'] . ': ' . formatearFechaCompletaEs($semana['inicio']) . ' a '
            . formatearFechaCompletaEs($semana['fin']),
        'filas' => $filas,
    );
}

function construirAcumuladoRangoPorDia(
    $controller,
    array $unidadesDetalle,
    $fechaInicio,
    $fechaFin,
    array $presupuestosPorUnidad
) {
    if ($fechaInicio === '' || $fechaFin === '') {
        return array('titulo' => '', 'filas' => array());
    }

    $inicioDate = DateTime::createFromFormat('Y-m-d', $fechaInicio);
    $finDate = DateTime::createFromFormat('Y-m-d', $fechaFin);
    if (
        !$inicioDate || $inicioDate->format('Y-m-d') !== $fechaInicio
        || !$finDate || $finDate->format('Y-m-d') !== $fechaFin
        || $inicioDate > $finDate
    ) {
        return array('titulo' => '', 'filas' => array());
    }

    $idsTodasUnidades = array();
    foreach ($unidadesDetalle as $idUnidad => $fechaAperturaUnidad) {
        $idsTodasUnidades[] = (int)$idUnidad;
    }
    $idsTodasUnidades = array_values(array_unique($idsTodasUnidades));

    $totalesActualRangoPorDiaUnidad = $controller->consultarTotalesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo($fechaInicio),
        convertirFechaADiaOperativo($fechaFin),
        $idsTodasUnidades
    );
    $totalesComparativoRangoPorDiaUnidad = $controller->consultarTotalesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($fechaInicio)),
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($fechaFin)),
        $idsTodasUnidades
    );
    $txnActualRangoPorDiaUnidad = $controller->consultarTransaccionesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo($fechaInicio),
        convertirFechaADiaOperativo($fechaFin),
        $idsTodasUnidades
    );
    $txnComparativoRangoPorDiaUnidad = $controller->consultarTransaccionesPorUnidadesEntreDiasAgrupado(
        'venta',
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($fechaInicio)),
        convertirFechaADiaOperativo(obtenerFechaMismoDiaSemanaAnioAnterior($fechaFin)),
        $idsTodasUnidades
    );
    $presupuestoPorDiaUnidad = $controller->consultarPresupuestoPorUnidadesAgrupado(
        'ssql_relaciones',
        $idsTodasUnidades,
        convertirFechaADiaOperativo($fechaInicio),
        convertirFechaADiaOperativo($fechaFin)
    );

    $acumuladoPorDiaSemana = array();
    $cursor = clone $inicioDate;
    while ($cursor <= $finDate) {
        $fechaActualDia = $cursor->format('Y-m-d');
        $fechaComparativaDia = obtenerFechaMismoDiaSemanaAnioAnterior($fechaActualDia);
        $idDiaActual = (string)convertirFechaADiaOperativo($fechaActualDia);
        $idDiaComparativo = (string)convertirFechaADiaOperativo($fechaComparativaDia);
        $indiceDiaSemana = (int)$cursor->format('w');

        if (!isset($acumuladoPorDiaSemana[$indiceDiaSemana])) {
            $acumuladoPorDiaSemana[$indiceDiaSemana] = array(
                'dia' => nombreDiaSemanaMayus($fechaActualDia),
                'fecha_actual' => '',
                'fx_ac' => 0,
                'fx_ap_iguales' => 0,
                'fx_ap_nuevas' => 0,
                'fx_ap_con_pto' => 0,
                'pto' => 0,
                'txn_ac' => 0,
                'txn_ap' => 0,
                'txn_ap_nuevas' => 0,
            );
        }

        $totalesActualPorUnidad = isset($totalesActualRangoPorDiaUnidad[$idDiaActual])
            ? $totalesActualRangoPorDiaUnidad[$idDiaActual]
            : array();
        $totalesComparativoPorUnidad = isset($totalesComparativoRangoPorDiaUnidad[$idDiaComparativo])
            ? $totalesComparativoRangoPorDiaUnidad[$idDiaComparativo]
            : array();
        $txnActualPorUnidad = isset($txnActualRangoPorDiaUnidad[$idDiaActual])
            ? $txnActualRangoPorDiaUnidad[$idDiaActual]
            : array();
        $txnComparativoPorUnidad = isset($txnComparativoRangoPorDiaUnidad[$idDiaComparativo])
            ? $txnComparativoRangoPorDiaUnidad[$idDiaComparativo]
            : array();

        $fxAcDia = 0;
        $fxApIgualesDia = 0;
        $fxApNuevasDia = 0;
        $ptoDia = 0;
        $fxApConPtoDia = 0;
        $txnAcDia = 0;
        $txnApDia = 0;
        $txnApNuevasDia = 0;

        foreach ($unidadesDetalle as $idUnidad => $fechaAperturaUnidad) {
            $idUnidad = (int)$idUnidad;
            $fxApUnidad = isset($totalesActualPorUnidad[$idUnidad]) ? (float)$totalesActualPorUnidad[$idUnidad] : 0;
            $fxAcUnidad = isset($totalesComparativoPorUnidad[$idUnidad]) ? (float)$totalesComparativoPorUnidad[$idUnidad] : 0;

            $ptoUnidad = isset($presupuestoPorDiaUnidad[$idDiaActual][$idUnidad])
                ? (float)$presupuestoPorDiaUnidad[$idDiaActual][$idUnidad]
                : 0;
            if ($ptoUnidad > 0) {
                $ptoDia += $ptoUnidad;
                $fxApConPtoDia += $fxApUnidad;
            }

            if (!unidadTieneAnioCumplido($fechaAperturaUnidad, $fechaActualDia)) {
                $fxApNuevasDia += $fxApUnidad;
                $txnApNuevasDia += isset($txnActualPorUnidad[$idUnidad]) ? (int)$txnActualPorUnidad[$idUnidad] : 0;
                continue;
            }

            $fxApIgualesDia += $fxApUnidad;
            $txnApDia += isset($txnActualPorUnidad[$idUnidad]) ? (int)$txnActualPorUnidad[$idUnidad] : 0;
            if (unidadAplicaEnComparativo($fechaAperturaUnidad, $fechaComparativaDia)) {
                $fxAcDia += $fxAcUnidad;
                $txnAcDia += isset($txnComparativoPorUnidad[$idUnidad]) ? (int)$txnComparativoPorUnidad[$idUnidad] : 0;
            }
        }

        if ($acumuladoPorDiaSemana[$indiceDiaSemana]['fecha_actual'] === '') {
            $acumuladoPorDiaSemana[$indiceDiaSemana]['fecha_actual'] = $fechaActualDia;
        }
        $acumuladoPorDiaSemana[$indiceDiaSemana]['fx_ac'] += $fxAcDia;
        $acumuladoPorDiaSemana[$indiceDiaSemana]['fx_ap_iguales'] += $fxApIgualesDia;
        $acumuladoPorDiaSemana[$indiceDiaSemana]['fx_ap_nuevas'] += $fxApNuevasDia;
        $acumuladoPorDiaSemana[$indiceDiaSemana]['fx_ap_con_pto'] += $fxApConPtoDia;
        $acumuladoPorDiaSemana[$indiceDiaSemana]['pto'] += $ptoDia;
        $acumuladoPorDiaSemana[$indiceDiaSemana]['txn_ac'] += $txnAcDia;
        $acumuladoPorDiaSemana[$indiceDiaSemana]['txn_ap'] += $txnApDia;
        $acumuladoPorDiaSemana[$indiceDiaSemana]['txn_ap_nuevas'] += $txnApNuevasDia;

        $cursor->modify('+1 day');
    }

    ksort($acumuladoPorDiaSemana);

    $filas = array();
    foreach ($acumuladoPorDiaSemana as $filaDia) {
        $fxAc = isset($filaDia['fx_ac']) ? (float)$filaDia['fx_ac'] : 0;
        $fxApIguales = isset($filaDia['fx_ap_iguales']) ? (float)$filaDia['fx_ap_iguales'] : 0;
        $fxApNuevas = isset($filaDia['fx_ap_nuevas']) ? (float)$filaDia['fx_ap_nuevas'] : 0;
        $fxApConPto = isset($filaDia['fx_ap_con_pto']) ? (float)$filaDia['fx_ap_con_pto'] : 0;
        $pto = isset($filaDia['pto']) ? (float)$filaDia['pto'] : 0;
        $fxAp = $fxApIguales;
        $var = $fxAp - $fxAc;
        $porcentaje = $fxAc == 0 ? 0 : (($var / $fxAc) * 100);
        $variacionPto = $controller->calcularVariacionPto($pto, $fxApConPto);
        $porcentajeAa = $controller->calcularPorcentajeAa($pto, $fxApConPto);

        $filas[] = array(
            'dia' => isset($filaDia['dia']) ? $filaDia['dia'] : '',
            'fecha_actual' => isset($filaDia['fecha_actual']) ? $filaDia['fecha_actual'] : '',
            'fecha_comparativa' => '',
            'fx_ac' => $fxAc,
            'fx_ap' => $fxAp,
            'fx_ap_iguales' => $fxApIguales,
            'fx_ap_nuevas' => $fxApNuevas,
            'fx_ap_con_pto' => $fxApConPto,
            'pto' => $pto,
            'var' => $var,
            'porcentaje_ap' => $porcentaje,
            'variacion_pto' => $variacionPto,
            'porcentaje_aa' => $porcentajeAa,
            'txn_ac' => isset($filaDia['txn_ac']) ? (int)$filaDia['txn_ac'] : 0,
            'txn_ap' => isset($filaDia['txn_ap']) ? (int)$filaDia['txn_ap'] : 0,
            'txn_ap_nuevas' => isset($filaDia['txn_ap_nuevas']) ? (int)$filaDia['txn_ap_nuevas'] : 0,
        );
    }

    return array(
        'titulo' => 'RANGO: ' . formatearFechaCompletaEs($fechaInicio) . ' a ' . formatearFechaCompletaEs($fechaFin),
        'filas' => $filas,
    );
}

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Consulta Ventas</title>
    <style>
    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        background: #f3f6f8;
        color: #1b1b1b;
    }

    .top-header {
        background: linear-gradient(180deg, #067845 0%, #046d3f 100%);
        border-bottom: 3px solid #66ff99;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.18);
    }

    .top-header-inner {
        max-width: 1200px;
        margin: 0 auto;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .top-nav {
        display: inline-flex;
        gap: 8px;
    }

    .top-nav-link {
        color: #ffffff;
        text-decoration: none;
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 5px;
        padding: 7px 12px;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.6px;
    }

    .top-nav-link.activo {
        background: #ffffff;
        color: #046d3f;
        border-color: #ffffff;
    }

    .logo-link {
        text-decoration: none;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
    }

    .logo-mark {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        margin-right: 10px;
        border: 2px solid rgba(255, 255, 255, 0.9);
        background:
            linear-gradient(135deg, #0c7f4a 0%, #0c7f4a 40%, #ffffff 40%, #ffffff 52%, #d63535 52%, #d63535 100%);
    }

    .logo-text {
        line-height: 1.05;
    }

    .logo-title {
        font-size: 30px;
        font-weight: bold;
        letter-spacing: 0.3px;
        font-style: italic;
    }

    .logo-sub {
        font-size: 10px;
        letter-spacing: 2px;
        color: #ffe082;
        text-transform: uppercase;
        margin-top: 1px;
    }

    .main-content {
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 20px 20px;
    }

    h1 {
        margin-top: 8px;
    }

    .panel-consulta {
        background: #ffffff;
        border: 1px solid #d9d9d9;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        gap: 20px;
    }

    .panel-form {
        min-width: 270px;
    }

    .panel-form-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .panel-form-boton {
        margin-top: 12px;
    }

    .panel-info {
        flex: 1;
        text-align: right;
    }

    .panel-info p {
        margin: 0 0 6px;
    }

    .panel-info p:last-child {
        margin-bottom: 0;
    }

    input[type="date"],
    button {
        font-size: 14px;
        padding: 7px 10px;
    }

    button {
        background: #067845;
        border: 0;
        color: #ffffff;
        border-radius: 5px;
        cursor: pointer;
    }

    button:disabled {
        opacity: 0.7;
        cursor: default;
    }

    table {
        background: #ffffff;
        border-collapse: collapse;
    }

    th {
        background: #edf4ef;
    }

    .valor-positivo {
        color: #0a8f08;
        font-weight: bold;
    }

    .valor-negativo {
        color: #d12020;
        font-weight: bold;
    }

    .alineado-derecha {
        text-align: right;
    }

    .fila-total td {
        font-weight: bold;
        background: #0b6f41;
        color: #ffffff;
    }

    .tabla-resumen-supervisores {
        margin-bottom: 20px;
        width: 100%;
    }

    .tabla-resumen-supervisores thead th {
        background: #dff2df;
    }

    .tabla-resumen-supervisores .encabezado-titulo {
        background: #0b6f41;
        color: #ffffff;
    }

    .tabla-resumen-supervisores .fila-total td {
        background: #0b6f41;
        color: #ffffff;
    }

    .tabla-acumulado-semana {
        margin-bottom: 20px;
        width: 100%;
    }

    .tabla-acumulado-semana thead th {
        background: #dff2df;
    }

    .tabla-acumulado-semana .encabezado-titulo {
        background: #0b6f41;
        color: #ffffff;
    }

    .dia-semana-fecha {
        font-size: 12px;
        color: #4f4f4f;
        margin-top: 2px;
    }

    .unidad-nueva td {
        background: #c084fc;
        color: #000000;
        font-weight: bold;
    }

    .grafica-unidad {
        background: #ffffff;
        border: 1px solid #d9d9d9;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 20px;
    }

    .grafica-unidad-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    .grafica-unidad-titulo {
        margin: 0;
        color: #103f2a;
        font-size: 15px;
        letter-spacing: 0.2px;
    }

    .bloque-unidad-categoria {
        margin-bottom: 20px;
    }

    .bloque-unidad-categoria .grafica-unidad {
        margin-bottom: 10px;
    }

    .grafica-unidad-scroll {
        overflow-x: auto;
        margin-top: 10px;
        padding-bottom: 5px;
    }

    .grafica-unidad-canvas {
        display: block;
        height: 360px;
        min-width: 100%;
    }

    .grafica-unidad-leyenda {
        margin-top: 10px;
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        color: #2d2d2d;
        font-size: 12px;
    }

    .grafica-unidad-leyenda-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .grafica-unidad-leyenda-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
        display: inline-block;
    }

    @media (max-width: 980px) {
        .top-header-inner {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .top-nav {
            flex-wrap: wrap;
        }

        .panel-consulta {
            flex-direction: column;
            gap: 12px;
        }

        .panel-info {
            text-align: left;
        }

        .bloque-unidad-categoria {
            margin-bottom: 14px;
        }

        .grafica-unidad-canvas {
            height: 320px;
        }
    }
    </style>
</head>

<body>
    <header class="top-header">
        <div class="top-header-inner">
            <a href="#" class="logo-link">
                <span class="logo-mark"></span>
                <span class="logo-text">
                    <span class="logo-title">Benedetti's</span>
                    <span class="logo-sub">Pizza</span>
                </span>
            </a>
            <nav class="top-nav">
                <a href="<?php echo e(appUrl('/reportes/dia.php')); ?>"
                    class="top-nav-link <?php echo $modoVista === 'dia' ? 'activo' : ''; ?>">Dia</a>
                <a href="<?php echo e(appUrl('/reportes/rango.php')); ?>"
                    class="top-nav-link <?php echo $modoVista === 'rango' ? 'activo' : ''; ?>">Rango</a>
                <a href="<?php echo e(appUrl('/reportes/unidad.php')); ?>"
                    class="top-nav-link <?php echo $modoVista === 'unidad' ? 'activo' : ''; ?>">Rep.Ventas</a>
                <a href="<?php echo e(appUrl('/reportes/orillas.php')); ?>"
                    class="top-nav-link <?php echo $modoVista === 'orillas' ? 'activo' : ''; ?>">Orillas</a>
                <span class="top-nav-link">Usuario: <?php echo e($nombreUsuarioSesion); ?>
                    (<?php echo e($idUsuarioSesion); ?>)</span>
                <a href="<?php echo e(appUrl('/logout.php')); ?>" class="top-nav-link">Salir</a>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <h1><?php echo e($tituloReporte); ?></h1>

        <div class="panel-consulta">
            <div class="panel-form">
                <form method="get" action="" id="consulta-form">
                    <input type="hidden" name="modo" value="<?php echo e($modoVista); ?>">
                    <?php if ($modoVista === 'dia'): ?>
                    <div class="panel-form-row">
                        <label for="id_diaoperativo">Fecha:</label>
                        <input type="date" id="id_diaoperativo" name="id_diaoperativo"
                            value="<?php echo e($fechaSeleccionada); ?>" max="<?php echo e($fechaMaximaSeleccion); ?>"
                            required>
                    </div>
                    <?php elseif ($modoVista === 'rango'): ?>
                    <div class="panel-form-row">
                        <label for="fecha_inicio">Inicio:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo e($fechaInicio); ?>"
                            max="<?php echo e($fechaMaximaSeleccion); ?>" required>
                    </div>
                    <div class="panel-form-row" style="margin-top:8px;">
                        <label for="fecha_fin">Fin:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo e($fechaFin); ?>"
                            max="<?php echo e($fechaMaximaSeleccion); ?>" required>
                    </div>
                    <?php elseif ($esModoUnidadDetallado): ?>
                    <div class="panel-form-row">
                        <label for="fecha_inicio">Inicio:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo e($fechaInicio); ?>"
                            max="<?php echo e($fechaMaximaSeleccion); ?>" required>
                    </div>
                    <div class="panel-form-row" style="margin-top:8px;">
                        <label for="fecha_fin">Fin:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo e($fechaFin); ?>"
                            max="<?php echo e($fechaMaximaSeleccion); ?>" required>
                    </div>
                    <div class="panel-form-row" style="margin-top:8px;">
                        <label for="id_unidad">
                            <?php echo $modoVista === 'orillas' ? 'Orillas por unidad:' : 'Rep.Ventas por unidad:'; ?>
                        </label>
                        <select id="id_unidad" name="id_unidad" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($unidadesCatalogo as $unidadCatalogo): ?>
                            <?php $idUnidadOption = isset($unidadCatalogo['id_unidad']) ? (int)$unidadCatalogo['id_unidad'] : 0; ?>
                            <?php
                                $nombreUnidadOption = isset($unidadCatalogo['nombre_unidad'])
                                    ? trim((string)$unidadCatalogo['nombre_unidad'])
                                    : '';
                                if ($nombreUnidadOption === '') {
                                    $nombreUnidadOption = $idUnidadOption > 0
                                        ? ('Unidad #' . $idUnidadOption)
                                        : 'Unidad sin nombre';
                                }
                                $textoUnidadOption = $nombreUnidadOption . ' (' . $idUnidadOption . ')';
                                $textoUnidadOptionEscapado = e($textoUnidadOption);
                                if ($textoUnidadOptionEscapado === '') {
                                    $textoUnidadOptionEscapado = ($idUnidadOption > 0
                                        ? ('Unidad #' . $idUnidadOption)
                                        : 'Unidad sin nombre') . ' (' . $idUnidadOption . ')';
                                }
                            ?>
                            <option value="<?php echo e($idUnidadOption); ?>"
                                <?php echo $idUnidadOption === $idUnidadFiltro ? 'selected' : ''; ?>>
                                <?php echo $textoUnidadOptionEscapado; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if (!$esModoUnidadDetallado): ?>
                    <div class="panel-form-row" style="margin-top:8px;">
                        <label for="modo_rapido">Modo rápido:</label>
                        <input type="checkbox" id="modo_rapido" name="modo_rapido" value="1"
                            <?php echo $modoRapido ? 'checked' : ''; ?>>
                    </div>
                    <?php endif; ?>
                    <div class="panel-form-boton">
                        <button type="submit" id="btn-consultar">Enviar</button>
                    </div>
                </form>
            </div>

            <div class="panel-info">
                <?php if ($modoVista === 'dia' && $fechaSeleccionada !== '' && $error === ''): ?>
                <p>Día operativo calculado: <?php echo e($idDiaOperativo); ?></p>
                <p>Fecha comparable año anterior (mismo día de semana): <?php echo e($fechaComparativa); ?></p>
                <p>Día operativo año anterior comparable: <?php echo e($idDiaOperativoComparativo); ?></p>
                <?php endif; ?>
                <?php if ($modoVista === 'rango' && $fechaInicio !== '' && $fechaFin !== '' && $error === ''): ?>
                <p>Día operativo inicial: <?php echo e($idDiaOperativoInicio); ?></p>
                <p>Día operativo final: <?php echo e($idDiaOperativoFin); ?></p>
                <p>Rango comparable año anterior: <?php echo e($fechaComparativaInicio); ?> a
                    <?php echo e($fechaComparativaFin); ?></p>
                <?php endif; ?>
                <?php if ($esModoUnidadDetallado && $mostrarApartadoUnidad && $error === ''): ?>
                <p>Selección: <?php echo e($nombreUnidadFiltro); ?> (<?php echo e($idUnidadFiltro); ?>)</p>
                <p>Rango seleccionado: <?php echo e($fechaInicio); ?> a <?php echo e($fechaFin); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($esModoUnidadDetallado && $mostrarApartadoUnidad && $error === ''): ?>
        <?php
        $graficasTamannoUnidad = array();
        foreach ($categoriasTamannoUnidad as $categoriaTamannoUnidad) {
            $filasGraficaCategoriaTamanno = array();
            foreach ($categoriaTamannoUnidad['filas'] as $filaCategoriaTamanno) {
                $filasGraficaCategoriaTamanno[] = array(
                    'nombre' => isset($filaCategoriaTamanno['nombre']) ? (string)$filaCategoriaTamanno['nombre'] : '',
                    'fx_2025' => isset($filaCategoriaTamanno['fx_2025']) ? (float)$filaCategoriaTamanno['fx_2025'] : 0,
                    'fx_2026' => isset($filaCategoriaTamanno['fx_2026']) ? (float)$filaCategoriaTamanno['fx_2026'] : 0,
                );
            }

            $jsonGraficaCategoriaTamanno = json_encode($filasGraficaCategoriaTamanno);
            if ($jsonGraficaCategoriaTamanno === false) {
                $jsonGraficaCategoriaTamanno = '[]';
            }

            $idBaseGraficaTamanno = 'grafica-tipo-' . (isset($categoriaTamannoUnidad['slug'])
                ? (string)$categoriaTamannoUnidad['slug']
                : 'sin-tipo');

            $graficasTamannoUnidad[] = array(
                'id_base' => $idBaseGraficaTamanno,
                'tipo' => isset($categoriaTamannoUnidad['tipo']) ? (string)$categoriaTamannoUnidad['tipo'] : 'SIN TIPO',
                'titulo' => isset($categoriaTamannoUnidad['titulo'])
                    ? (string)$categoriaTamannoUnidad['titulo']
                    : 'SIN TIPO (orden)',
                'filas' => isset($categoriaTamannoUnidad['filas']) ? $categoriaTamannoUnidad['filas'] : array(),
                'total_fx_2025' => isset($categoriaTamannoUnidad['total_fx_2025'])
                    ? (float)$categoriaTamannoUnidad['total_fx_2025']
                    : 0,
                'total_fx_2026' => isset($categoriaTamannoUnidad['total_fx_2026'])
                    ? (float)$categoriaTamannoUnidad['total_fx_2026']
                    : 0,
                'json_data' => $jsonGraficaCategoriaTamanno,
            );
        }

        $filasGraficaPromociones = array();
        foreach ($promocionesFilas as $filaPromocion) {
            $filasGraficaPromociones[] = array(
                'nombre' => isset($filaPromocion['nombre']) ? (string)$filaPromocion['nombre'] : '',
                'fx_2025' => isset($filaPromocion['fx_2025']) ? (float)$filaPromocion['fx_2025'] : 0,
                'fx_2026' => isset($filaPromocion['fx_2026']) ? (float)$filaPromocion['fx_2026'] : 0,
            );
        }

        $jsonGraficaPromociones = json_encode($filasGraficaPromociones);
        if ($jsonGraficaPromociones === false) {
            $jsonGraficaPromociones = '[]';
        }
        ?>
        <?php foreach ($graficasTamannoUnidad as $graficaTamannoUnidad): ?>
        <div class="bloque-unidad-categoria">
            <?php if (!empty($graficaTamannoUnidad['sin_ventas'])): ?>
            <table border="1" cellpadding="6" cellspacing="0" class="tabla-resumen-supervisores">
                <thead>
                    <tr>
                        <th class="encabezado-titulo" style="text-align:left;">
                            <?php echo e(strtoupper($graficaTamannoUnidad['tipo'])); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Aun no se han registrado ventas para esta orilla.</td>
                    </tr>
                </tbody>
            </table>
            <?php else: ?>
            <section class="grafica-unidad" id="<?php echo e($graficaTamannoUnidad['id_base']); ?>-panel">
                <div class="grafica-unidad-header">
                    <h2 class="grafica-unidad-titulo"><?php echo e($graficaTamannoUnidad['titulo']); ?></h2>
                </div>
                <div class="grafica-unidad-scroll" id="<?php echo e($graficaTamannoUnidad['id_base']); ?>-scroll">
                    <canvas id="<?php echo e($graficaTamannoUnidad['id_base']); ?>-canvas" class="grafica-unidad-canvas"
                        role="img"
                        aria-label="Comparativo de <?php echo e(strtolower($graficaTamannoUnidad['tipo'])); ?>"></canvas>
                </div>
                <div class="grafica-unidad-leyenda">
                    <span class="grafica-unidad-leyenda-item">
                        <span class="grafica-unidad-leyenda-color" style="background:#e4d01e;"></span>
                        <span>FX <?php echo e($anioFx2026Unidad - 1); ?></span>
                    </span>
                    <span class="grafica-unidad-leyenda-item">
                        <span class="grafica-unidad-leyenda-color" style="background:#ff8e2b;"></span>
                        <span>FX <?php echo e($anioFx2026Unidad); ?></span>
                    </span>
                </div>
                <script type="application/json" id="<?php echo e($graficaTamannoUnidad['id_base']); ?>-data"
                    data-grafica-unidad="1" data-grafica-id="<?php echo e($graficaTamannoUnidad['id_base']); ?>">
                <?php echo $graficaTamannoUnidad['json_data']; ?>
                </script>
            </section>

            <table border="1" cellpadding="6" cellspacing="0" class="tabla-resumen-supervisores">
                <thead>
                    <tr>
                        <th colspan="5" class="encabezado-titulo" style="text-align:left;">
                            <?php echo e(strtoupper($graficaTamannoUnidad['tipo'])); ?>
                        </th>
                    </tr>
                    <tr>
                        <th></th>
                        <th>FX <?php echo e($anioFx2026Unidad - 1); ?></th>
                        <th>FX <?php echo e($anioFx2026Unidad); ?></th>
                        <th>% AP</th>
                        <th>VARIACIÓN $</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($graficaTamannoUnidad['filas'] as $filaTamannoUnidad): ?>
                    <?php
                    $tamannoUnidadPorcentaje = isset($filaTamannoUnidad['porcentaje_ap']) ? (float)$filaTamannoUnidad['porcentaje_ap'] : 0;
                    $tamannoUnidadVariacion = isset($filaTamannoUnidad['variacion']) ? (float)$filaTamannoUnidad['variacion'] : 0;
                    $claseTamannoUnidadPorcentaje = $tamannoUnidadPorcentaje > 0 ? 'valor-positivo' : ($tamannoUnidadPorcentaje < 0 ? 'valor-negativo' : '');
                    $claseTamannoUnidadVariacion = $tamannoUnidadVariacion > 0 ? 'valor-positivo' : ($tamannoUnidadVariacion < 0 ? 'valor-negativo' : '');
                    ?>
                    <tr>
                        <td><?php echo e(isset($filaTamannoUnidad['nombre']) ? $filaTamannoUnidad['nombre'] : ''); ?>
                        </td>
                        <td class="alineado-derecha">
                            <?php echo e(number_format((float)$filaTamannoUnidad['fx_2025'], 1)); ?>
                        </td>
                        <td class="alineado-derecha">
                            <?php echo e(number_format((float)$filaTamannoUnidad['fx_2026'], 1)); ?>
                        </td>
                        <td class="alineado-derecha <?php echo e($claseTamannoUnidadPorcentaje); ?>">
                            <?php echo e(number_format($tamannoUnidadPorcentaje, 1)); ?></td>
                        <td class="alineado-derecha <?php echo e($claseTamannoUnidadVariacion); ?>">
                            <?php echo e(number_format($tamannoUnidadVariacion, 1)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php
                    $tamannoUnidadTotalVar = $graficaTamannoUnidad['total_fx_2026'] - $graficaTamannoUnidad['total_fx_2025'];
                    $tamannoUnidadTotalPorcentaje = $graficaTamannoUnidad['total_fx_2025'] == 0
                        ? 0
                        : (($tamannoUnidadTotalVar / $graficaTamannoUnidad['total_fx_2025']) * 100);
                    $claseTamannoUnidadTotalPorcentaje = $tamannoUnidadTotalPorcentaje > 0 ? 'valor-positivo' : ($tamannoUnidadTotalPorcentaje < 0 ? 'valor-negativo' : '');
                    $claseTamannoUnidadTotalVar = $tamannoUnidadTotalVar > 0 ? 'valor-positivo' : ($tamannoUnidadTotalVar < 0 ? 'valor-negativo' : '');
                    ?>
                    <tr class="fila-total">
                        <td>TOTAL</td>
                        <td class="alineado-derecha">
                            <?php echo e(number_format((float)$graficaTamannoUnidad['total_fx_2025'], 1)); ?></td>
                        <td class="alineado-derecha">
                            <?php echo e(number_format((float)$graficaTamannoUnidad['total_fx_2026'], 1)); ?></td>
                        <td class="alineado-derecha <?php echo e($claseTamannoUnidadTotalPorcentaje); ?>">
                            <?php echo e(number_format($tamannoUnidadTotalPorcentaje, 1)); ?></td>
                        <td class="alineado-derecha <?php echo e($claseTamannoUnidadTotalVar); ?>">
                            <?php echo e(number_format($tamannoUnidadTotalVar, 1)); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($mostrarPromocionesUnidad): ?>
        <div class="bloque-unidad-categoria">
            <section class="grafica-unidad" id="grafica-promociones-panel">
                <div class="grafica-unidad-header">
                    <h2 class="grafica-unidad-titulo">Promociones (tickets)</h2>
                </div>
                <div class="grafica-unidad-scroll" id="grafica-promociones-scroll">
                    <canvas id="grafica-promociones-canvas" class="grafica-unidad-canvas" role="img"
                        aria-label="Comparativo de tickets por promociones"></canvas>
                </div>
                <div class="grafica-unidad-leyenda">
                    <span class="grafica-unidad-leyenda-item">
                        <span class="grafica-unidad-leyenda-color" style="background:#e4d01e;"></span>
                        <span>FX <?php echo e($anioFx2026Unidad - 1); ?></span>
                    </span>
                    <span class="grafica-unidad-leyenda-item">
                        <span class="grafica-unidad-leyenda-color" style="background:#ff8e2b;"></span>
                        <span>FX <?php echo e($anioFx2026Unidad); ?></span>
                    </span>
                </div>
                <script type="application/json" id="grafica-promociones-data" data-grafica-unidad="1"
                    data-grafica-id="grafica-promociones">
                <?php echo $jsonGraficaPromociones; ?>
                </script>
            </section>

            <table border="1" cellpadding="6" cellspacing="0" class="tabla-resumen-supervisores">
                <thead>
                    <tr>
                        <th colspan="5" class="encabezado-titulo" style="text-align:left;">PROMOCIONES</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th>FX <?php echo e($anioFx2026Unidad - 1); ?></th>
                        <th>FX <?php echo e($anioFx2026Unidad); ?></th>
                        <th>% AP</th>
                        <th>VARIACIÓN $</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promocionesFilas as $filaPromocion): ?>
                    <?php
                    $promocionPorcentaje = isset($filaPromocion['porcentaje_ap']) ? (float)$filaPromocion['porcentaje_ap'] : 0;
                    $promocionVariacion = isset($filaPromocion['variacion']) ? (float)$filaPromocion['variacion'] : 0;
                    $clasePromocionPorcentaje = $promocionPorcentaje > 0 ? 'valor-positivo' : ($promocionPorcentaje < 0 ? 'valor-negativo' : '');
                    $clasePromocionVariacion = $promocionVariacion > 0 ? 'valor-positivo' : ($promocionVariacion < 0 ? 'valor-negativo' : '');
                    ?>
                    <tr>
                        <td><?php echo e(isset($filaPromocion['nombre']) ? $filaPromocion['nombre'] : ''); ?></td>
                        <td class="alineado-derecha">
                            <?php echo e(number_format((float)$filaPromocion['fx_2025'], 1)); ?>
                        </td>
                        <td class="alineado-derecha">
                            <?php echo e(number_format((float)$filaPromocion['fx_2026'], 1)); ?>
                        </td>
                        <td class="alineado-derecha <?php echo e($clasePromocionPorcentaje); ?>">
                            <?php echo e(number_format($promocionPorcentaje, 1)); ?>
                        </td>
                        <td class="alineado-derecha <?php echo e($clasePromocionVariacion); ?>">
                            <?php echo e(number_format($promocionVariacion, 1)); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php
                    $promocionesTotalVar = $promocionesTotalFx2026 - $promocionesTotalFx2025;
                    $promocionesTotalPorcentaje = $promocionesTotalFx2025 == 0 ? 0 : (($promocionesTotalVar / $promocionesTotalFx2025) * 100);
                    $clasePromocionesTotalPorcentaje = $promocionesTotalPorcentaje > 0 ? 'valor-positivo' : ($promocionesTotalPorcentaje < 0 ? 'valor-negativo' : '');
                    $clasePromocionesTotalVar = $promocionesTotalVar > 0 ? 'valor-positivo' : ($promocionesTotalVar < 0 ? 'valor-negativo' : '');
                    ?>
                    <tr class="fila-total">
                        <td>TOTAL</td>
                        <td class="alineado-derecha"><?php echo e(number_format($promocionesTotalFx2025, 1)); ?></td>
                        <td class="alineado-derecha"><?php echo e(number_format($promocionesTotalFx2026, 1)); ?></td>
                        <td class="alineado-derecha <?php echo e($clasePromocionesTotalPorcentaje); ?>">
                            <?php echo e(number_format($promocionesTotalPorcentaje, 1)); ?>
                        </td>
                        <td class="alineado-derecha <?php echo e($clasePromocionesTotalVar); ?>">
                            <?php echo e(number_format($promocionesTotalVar, 1)); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <p id="estado-consulta" style="display:none;">Consultando... primero ssql_relaciones y después venta (puede
            tardar unos segundos).</p>

        <?php if ($error !== ''): ?>
        <p><?php echo e($error); ?></p>
        <?php endif; ?>

        <?php if ($mostrarReporte && $error === ''): ?>
        <?php if (!$modoRapido): ?>
        <table border="1" cellpadding="6" cellspacing="0" class="tabla-acumulado-semana">
            <thead>
                <tr>
                    <th colspan="8" class="encabezado-titulo">ACUMULADO POR DÍA -
                        <?php echo e($acumuladoSemanaTitulo); ?></th>
                </tr>
                <tr>
                    <th>DÍA</th>
                    <th>FX <?php echo e($anioPto - 1); ?></th>
                    <th>FX <?php echo e($anioPto); ?></th>
                    <th>% AP</th>
                    <th>VARIACIÓN $</th>
                    <th>PTO <?php echo e($anioPto); ?></th>
                    <th>% AA</th>
                    <th>VARIACIÓN PTO</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $acumuladoSemanaTotalIgualesFxAc = 0;
                $acumuladoSemanaTotalIgualesFxAp = 0;
                $acumuladoSemanaTotalNuevasFxAp = 0;
                $acumuladoSemanaTotalFxAp = 0;
                $acumuladoSemanaTotalIgualesFxApConPto = 0;
                $acumuladoSemanaTotalPto = 0;
                $acumuladoSemanaTotalTxnAc = 0;
                $acumuladoSemanaTotalTxnAp = 0;
                ?>
                <?php foreach ($acumuladoSemanaPorDia as $filaSemana): ?>
                <?php
                $filaFxAc = isset($filaSemana['fx_ac']) ? (float)$filaSemana['fx_ac'] : 0;
                $filaFxAp = isset($filaSemana['fx_ap']) ? (float)$filaSemana['fx_ap'] : 0;
                $filaFxApIguales = isset($filaSemana['fx_ap_iguales']) ? (float)$filaSemana['fx_ap_iguales'] : 0;
                $filaFxApNuevas = isset($filaSemana['fx_ap_nuevas']) ? (float)$filaSemana['fx_ap_nuevas'] : 0;
                $filaFxApConPto = isset($filaSemana['fx_ap_con_pto']) ? (float)$filaSemana['fx_ap_con_pto'] : 0;
                $filaPto = isset($filaSemana['pto']) ? (float)$filaSemana['pto'] : 0;
                $filaVar = isset($filaSemana['var']) ? (float)$filaSemana['var'] : 0;
                $filaPorcentaje = isset($filaSemana['porcentaje_ap']) ? (float)$filaSemana['porcentaje_ap'] : 0;
                $filaVariacionPto = isset($filaSemana['variacion_pto']) ? (float)$filaSemana['variacion_pto'] : 0;
                $filaPorcentajeAa = isset($filaSemana['porcentaje_aa']) ? (float)$filaSemana['porcentaje_aa'] : 0;
                $filaTxnAc = isset($filaSemana['txn_ac']) ? (int)$filaSemana['txn_ac'] : 0;
                $filaTxnAp = isset($filaSemana['txn_ap']) ? (int)$filaSemana['txn_ap'] : 0;
                $claseFilaPorcentaje = $filaPorcentaje > 0 ? 'valor-positivo' : ($filaPorcentaje < 0 ? 'valor-negativo' : '');
                $claseFilaVar = $filaVar > 0 ? 'valor-positivo' : ($filaVar < 0 ? 'valor-negativo' : '');
                $claseFilaPorcentajeAa = $filaPorcentajeAa > 0 ? 'valor-positivo' : ($filaPorcentajeAa < 0 ? 'valor-negativo' : '');
                $claseFilaVariacionPto = $filaVariacionPto > 0 ? 'valor-positivo' : ($filaVariacionPto < 0 ? 'valor-negativo' : '');

                $acumuladoSemanaTotalIgualesFxAc += $filaFxAc;
                $acumuladoSemanaTotalIgualesFxAp += $filaFxApIguales;
                $acumuladoSemanaTotalNuevasFxAp += $filaFxApNuevas;
                $acumuladoSemanaTotalFxAp += $filaFxAp;
                $acumuladoSemanaTotalIgualesFxApConPto += $filaFxApConPto;
                $acumuladoSemanaTotalPto += $filaPto;
                $acumuladoSemanaTotalTxnAc += $filaTxnAc;
                $acumuladoSemanaTotalTxnAp += $filaTxnAp;
                ?>
                <tr>
                    <td>
                        <div><?php echo e(isset($filaSemana['dia']) ? $filaSemana['dia'] : ''); ?></div>
                        <div class="dia-semana-fecha">
                            <?php echo e(formatearFechaCorta(isset($filaSemana['fecha_actual']) ? $filaSemana['fecha_actual'] : '')); ?>
                        </div>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($filaFxAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($filaFxAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseFilaPorcentaje); ?>">
                        <?php echo e(number_format($filaPorcentaje, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseFilaVar); ?>">
                        <?php echo e(formatearMoneda($filaVar)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($filaPto)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseFilaPorcentajeAa); ?>">
                        <?php echo e(number_format($filaPorcentajeAa, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseFilaVariacionPto); ?>">
                        <?php echo e(formatearMoneda($filaVariacionPto)); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php
                $acumuladoSemanaTotalFxAp = $acumuladoSemanaTotalIgualesFxAp + $acumuladoSemanaTotalNuevasFxAp;
                $acumuladoSemanaTotalIgualesVar = $acumuladoSemanaTotalIgualesFxAp - $acumuladoSemanaTotalIgualesFxAc;
                $acumuladoSemanaTotalIgualesPorcentaje =
                    $acumuladoSemanaTotalIgualesFxAc == 0
                        ? 0
                        : (($acumuladoSemanaTotalIgualesVar / $acumuladoSemanaTotalIgualesFxAc) * 100);
                $claseAcumuladoIgualesPorcentaje =
                    $acumuladoSemanaTotalIgualesPorcentaje > 0
                        ? 'valor-positivo'
                        : ($acumuladoSemanaTotalIgualesPorcentaje < 0 ? 'valor-negativo' : '');
                $claseAcumuladoIgualesVar =
                    $acumuladoSemanaTotalIgualesVar > 0 ? 'valor-positivo' : ($acumuladoSemanaTotalIgualesVar < 0 ? 'valor-negativo' : '');
                $acumuladoSemanaTotalIgualesVarPto =
                    $controller->calcularVariacionPto($acumuladoSemanaTotalPto, $acumuladoSemanaTotalIgualesFxApConPto);
                $acumuladoSemanaTotalIgualesPorcentajeAa =
                    $controller->calcularPorcentajeAa($acumuladoSemanaTotalPto, $acumuladoSemanaTotalIgualesFxApConPto);
                $claseAcumuladoIgualesPorcentajeAa =
                    $acumuladoSemanaTotalIgualesPorcentajeAa > 0
                        ? 'valor-positivo'
                        : ($acumuladoSemanaTotalIgualesPorcentajeAa < 0 ? 'valor-negativo' : '');
                $claseAcumuladoIgualesVarPto =
                    $acumuladoSemanaTotalIgualesVarPto > 0
                        ? 'valor-positivo'
                        : ($acumuladoSemanaTotalIgualesVarPto < 0 ? 'valor-negativo' : '');

                $acumuladoSemanaTotalVar = $acumuladoSemanaTotalFxAp - $acumuladoSemanaTotalIgualesFxAc;
                $acumuladoSemanaTotalPorcentaje =
                    $acumuladoSemanaTotalIgualesFxAc == 0 ? 0 : (($acumuladoSemanaTotalVar / $acumuladoSemanaTotalIgualesFxAc) * 100);
                $claseAcumuladoTotalPorcentaje =
                    $acumuladoSemanaTotalPorcentaje > 0 ? 'valor-positivo' : ($acumuladoSemanaTotalPorcentaje < 0 ? 'valor-negativo' : '');
                $claseAcumuladoTotalVar =
                    $acumuladoSemanaTotalVar > 0 ? 'valor-positivo' : ($acumuladoSemanaTotalVar < 0 ? 'valor-negativo' : '');
                $acumuladoSemanaTotalVarPto =
                    $controller->calcularVariacionPto($acumuladoSemanaTotalPto, $acumuladoSemanaTotalIgualesFxApConPto);
                $acumuladoSemanaTotalPorcentajeAa =
                    $controller->calcularPorcentajeAa($acumuladoSemanaTotalPto, $acumuladoSemanaTotalIgualesFxApConPto);
                $claseAcumuladoTotalPorcentajeAa =
                    $acumuladoSemanaTotalPorcentajeAa > 0 ? 'valor-positivo' : ($acumuladoSemanaTotalPorcentajeAa < 0 ? 'valor-negativo' : '');
                $claseAcumuladoTotalVarPto =
                    $acumuladoSemanaTotalVarPto > 0 ? 'valor-positivo' : ($acumuladoSemanaTotalVarPto < 0 ? 'valor-negativo' : '');
                ?>
                <tr class="fila-total">
                    <td>TOTAL IGUALES</td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($acumuladoSemanaTotalIgualesFxAc)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($acumuladoSemanaTotalIgualesFxAp)); ?>
                    </td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoIgualesPorcentaje); ?>">
                        <?php echo e(number_format($acumuladoSemanaTotalIgualesPorcentaje, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoIgualesVar); ?>">
                        <?php echo e(formatearMoneda($acumuladoSemanaTotalIgualesVar)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($acumuladoSemanaTotalPto)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoIgualesPorcentajeAa); ?>">
                        <?php echo e(number_format($acumuladoSemanaTotalIgualesPorcentajeAa, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoIgualesVarPto); ?>">
                        <?php echo e(formatearMoneda($acumuladoSemanaTotalIgualesVarPto)); ?>
                    </td>
                </tr>
                <tr class="unidad-nueva">
                    <td>TOTAL NUEVAS</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($acumuladoSemanaTotalNuevasFxAp)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
                <tr class="fila-total">
                    <td>TOTAL</td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($acumuladoSemanaTotalIgualesFxAc)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($acumuladoSemanaTotalFxAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoTotalPorcentaje); ?>">
                        <?php echo e(number_format($acumuladoSemanaTotalPorcentaje, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoTotalVar); ?>">
                        <?php echo e(formatearMoneda($acumuladoSemanaTotalVar)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($acumuladoSemanaTotalPto)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoTotalPorcentajeAa); ?>">
                        <?php echo e(number_format($acumuladoSemanaTotalPorcentajeAa, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseAcumuladoTotalVarPto); ?>">
                        <?php echo e(formatearMoneda($acumuladoSemanaTotalVarPto)); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="1" cellpadding="6" cellspacing="0" class="tabla-acumulado-semana">
            <thead>
                <tr>
                    <th colspan="8" class="encabezado-titulo">TRANSACCIONES POR DÍA -
                        <?php echo e($acumuladoSemanaTitulo); ?></th>
                </tr>
                <tr>
                    <th>DÍA</th>
                    <th>TX <?php echo e($anioPto - 1); ?></th>
                    <th>TX <?php echo e($anioPto); ?></th>
                    <th>% AP</th>
                    <th>VARIACIÓN</th>
                    <th>PTO <?php echo e($anioPto); ?></th>
                    <th>% AA</th>
                    <th>VARIACIÓN PTO</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $acumuladoSemanaTxnApNuevas = 0;
                ?>
                <?php foreach ($acumuladoSemanaPorDia as $filaSemana): ?>
                <?php
                $txAcFila = isset($filaSemana['txn_ac']) ? (int)$filaSemana['txn_ac'] : 0;
                $txApFila = isset($filaSemana['txn_ap']) ? (int)$filaSemana['txn_ap'] : 0;
                $txApNuevasFila = isset($filaSemana['txn_ap_nuevas']) ? (int)$filaSemana['txn_ap_nuevas'] : 0;
                $txVarFila = $txApFila - $txAcFila;
                $txPctFila = $txAcFila == 0 ? 0 : (($txVarFila / $txAcFila) * 100);
                $claseTxPctFila = $txPctFila > 0 ? 'valor-positivo' : ($txPctFila < 0 ? 'valor-negativo' : '');
                $claseTxVarFila = $txVarFila > 0 ? 'valor-positivo' : ($txVarFila < 0 ? 'valor-negativo' : '');
                $acumuladoSemanaTxnApNuevas += $txApNuevasFila;
                ?>
                <tr>
                    <td>
                        <div><?php echo e(isset($filaSemana['dia']) ? $filaSemana['dia'] : ''); ?></div>
                        <div class="dia-semana-fecha">
                            <?php echo e(formatearFechaCorta(isset($filaSemana['fecha_actual']) ? $filaSemana['fecha_actual'] : '')); ?>
                        </div>
                    </td>
                    <td class="alineado-derecha"><?php echo e(number_format($txAcFila)); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($txApFila)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTxPctFila); ?>">
                        <?php echo e(number_format($txPctFila, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTxVarFila); ?>">
                        <?php echo e(number_format($txVarFila)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
                <?php endforeach; ?>
                <?php
                $txVarTotalIguales = $acumuladoSemanaTotalTxnAp - $acumuladoSemanaTotalTxnAc;
                $txPctTotalIguales = $acumuladoSemanaTotalTxnAc == 0 ? 0 : (($txVarTotalIguales / $acumuladoSemanaTotalTxnAc) * 100);
                $claseTxPctTotalIguales = $txPctTotalIguales > 0 ? 'valor-positivo' : ($txPctTotalIguales < 0 ? 'valor-negativo' : '');
                $claseTxVarTotalIguales = $txVarTotalIguales > 0 ? 'valor-positivo' : ($txVarTotalIguales < 0 ? 'valor-negativo' : '');
                $txTotalFinal = $acumuladoSemanaTotalTxnAp + $acumuladoSemanaTxnApNuevas;
                $txVarTotal = $txTotalFinal - $acumuladoSemanaTotalTxnAc;
                $txPctTotal = $acumuladoSemanaTotalTxnAc == 0 ? 0 : (($txVarTotal / $acumuladoSemanaTotalTxnAc) * 100);
                $claseTxPctTotal = $txPctTotal > 0 ? 'valor-positivo' : ($txPctTotal < 0 ? 'valor-negativo' : '');
                $claseTxVarTotal = $txVarTotal > 0 ? 'valor-positivo' : ($txVarTotal < 0 ? 'valor-negativo' : '');
                ?>
                <tr class="fila-total">
                    <td>TOTAL IGUALES</td>
                    <td class="alineado-derecha"><?php echo e(number_format($acumuladoSemanaTotalTxnAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($acumuladoSemanaTotalTxnAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTxPctTotalIguales); ?>">
                        <?php echo e(number_format($txPctTotalIguales, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTxVarTotalIguales); ?>">
                        <?php echo e(number_format($txVarTotalIguales)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
                <tr class="unidad-nueva">
                    <td>TOTAL NUEVAS</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha"><?php echo e(number_format($acumuladoSemanaTxnApNuevas)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
                <tr class="fila-total">
                    <td>TOTAL</td>
                    <td class="alineado-derecha"><?php echo e(number_format($acumuladoSemanaTotalTxnAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($txTotalFinal)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTxPctTotal); ?>">
                        <?php echo e(number_format($txPctTotal, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTxVarTotal); ?>">
                        <?php echo e(number_format($txVarTotal)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <table border="1" cellpadding="6" cellspacing="0" class="tabla-resumen-supervisores">
            <thead>
                <tr>
                    <th colspan="8" class="encabezado-titulo">ACUMULADO POR SUPERVISOR</th>
                </tr>
                <tr>
                    <th>SUPERVISOR</th>
                    <th>FX <?php echo e($anioPto - 1); ?></th>
                    <th>FX <?php echo e($anioPto); ?></th>
                    <th>% AP</th>
                    <th>VARIACIÓN $</th>
                    <th>PTO <?php echo e($anioPto); ?></th>
                    <th>% AA</th>
                    <th>VARIACIÓN PTO</th>
                </tr>
                <tr>
                    <th></th>
                    <th><?php echo e($textoPeriodoComparativo); ?></th>
                    <th><?php echo e($textoPeriodoActual); ?></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $resumenTotalFxAc = 0;
                $resumenTotalFxAp = 0;
                $resumenTotalPto = 0;
                $resumenTotalFxApConPto = 0;
                $resumenTotalTxnAc = 0;
                $resumenTotalTxnAp = 0;
                ?>
                <?php foreach ($unidadesPorSupervisor as $bloqueSupervisorResumen): ?>
                <?php
                $resumenSupervisorFxAc = 0;
                $resumenSupervisorFxAp = 0;
                $resumenSupervisorPto = 0;
                $resumenSupervisorFxApConPto = 0;
                $resumenSupervisorTxnAc = 0;
                $resumenSupervisorTxnAp = 0;
                foreach ($bloqueSupervisorResumen['unidades'] as $unidadResumen) {
                    $fechaAperturaUnidadResumen = isset($unidadResumen['fapertura_unidad'])
                        ? trim((string)$unidadResumen['fapertura_unidad'])
                        : '';
                    $fechaAperturaUnidadResumen =
                        $fechaAperturaUnidadResumen !== '' ? substr($fechaAperturaUnidadResumen, 0, 10) : '';
                    if (!unidadTieneAnioCumplido($fechaAperturaUnidadResumen, $fechaCorteAcumulado)) {
                        continue;
                    }

                    $fxApUnidadResumen = isset($unidadResumen['fx_ap']) ? (float)$unidadResumen['fx_ap'] : 0;
                    $ptoUnidadResumen = isset($unidadResumen['presupuesto']) ? (float)$unidadResumen['presupuesto'] : 0;

                    $resumenSupervisorFxAc += isset($unidadResumen['fx_ac']) ? (float)$unidadResumen['fx_ac'] : 0;
                    $resumenSupervisorFxAp += $fxApUnidadResumen;
                    $resumenSupervisorPto += $ptoUnidadResumen;
                    $resumenSupervisorTxnAc += isset($unidadResumen['txn_ac']) ? (int)$unidadResumen['txn_ac'] : 0;
                    $resumenSupervisorTxnAp += isset($unidadResumen['txn_ap']) ? (int)$unidadResumen['txn_ap'] : 0;
                    if ($ptoUnidadResumen > 0) {
                        $resumenSupervisorFxApConPto += $fxApUnidadResumen;
                    }
                }
                $resumenSupervisorVar = $resumenSupervisorFxAp - $resumenSupervisorFxAc;
                $resumenSupervisorPorcentaje = $resumenSupervisorFxAc == 0 ? 0 : (($resumenSupervisorVar / $resumenSupervisorFxAc) * 100);
                $resumenSupervisorVarPto =
                    $controller->calcularVariacionPto($resumenSupervisorPto, $resumenSupervisorFxApConPto);
                $resumenSupervisorPorcentajeAa =
                    $controller->calcularPorcentajeAa($resumenSupervisorPto, $resumenSupervisorFxApConPto);
                $claseResumenPorcentaje =
                    $resumenSupervisorPorcentaje > 0 ? 'valor-positivo' : ($resumenSupervisorPorcentaje < 0 ? 'valor-negativo' : '');
                $claseResumenVar =
                    $resumenSupervisorVar > 0 ? 'valor-positivo' : ($resumenSupervisorVar < 0 ? 'valor-negativo' : '');
                $claseResumenPorcentajeAa =
                    $resumenSupervisorPorcentajeAa > 0 ? 'valor-positivo' : ($resumenSupervisorPorcentajeAa < 0 ? 'valor-negativo' : '');
                $claseResumenVarPto =
                    $resumenSupervisorVarPto > 0 ? 'valor-positivo' : ($resumenSupervisorVarPto < 0 ? 'valor-negativo' : '');

                $resumenTotalFxAc += $resumenSupervisorFxAc;
                $resumenTotalFxAp += $resumenSupervisorFxAp;
                $resumenTotalPto += $resumenSupervisorPto;
                $resumenTotalFxApConPto += $resumenSupervisorFxApConPto;
                $resumenTotalTxnAc += $resumenSupervisorTxnAc;
                $resumenTotalTxnAp += $resumenSupervisorTxnAp;
                ?>
                <tr>
                    <td><?php echo e($bloqueSupervisorResumen['supervisor']); ?></td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($resumenSupervisorFxAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($resumenSupervisorFxAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseResumenPorcentaje); ?>">
                        <?php echo e(number_format($resumenSupervisorPorcentaje, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseResumenVar); ?>">
                        <?php echo e(formatearMoneda($resumenSupervisorVar)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($resumenSupervisorPto)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseResumenPorcentajeAa); ?>">
                        <?php echo e(number_format($resumenSupervisorPorcentajeAa, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseResumenVarPto); ?>">
                        <?php echo e(formatearMoneda($resumenSupervisorVarPto)); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php
                $resumenTotalVar = $resumenTotalFxAp - $resumenTotalFxAc;
                $resumenTotalPorcentaje = $resumenTotalFxAc == 0 ? 0 : (($resumenTotalVar / $resumenTotalFxAc) * 100);
                $resumenTotalVarPto = $controller->calcularVariacionPto($resumenTotalPto, $resumenTotalFxApConPto);
                $resumenTotalPorcentajeAa =
                    $controller->calcularPorcentajeAa($resumenTotalPto, $resumenTotalFxApConPto);
                $claseResumenTotalPorcentaje =
                    $resumenTotalPorcentaje > 0 ? 'valor-positivo' : ($resumenTotalPorcentaje < 0 ? 'valor-negativo' : '');
                $claseResumenTotalVar =
                    $resumenTotalVar > 0 ? 'valor-positivo' : ($resumenTotalVar < 0 ? 'valor-negativo' : '');
                $claseResumenTotalPorcentajeAa =
                    $resumenTotalPorcentajeAa > 0 ? 'valor-positivo' : ($resumenTotalPorcentajeAa < 0 ? 'valor-negativo' : '');
                $claseResumenTotalVarPto =
                    $resumenTotalVarPto > 0 ? 'valor-positivo' : ($resumenTotalVarPto < 0 ? 'valor-negativo' : '');
                ?>
                <tr class="fila-total">
                    <td>TOTAL</td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($resumenTotalFxAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($resumenTotalFxAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseResumenTotalPorcentaje); ?>">
                        <?php echo e(number_format($resumenTotalPorcentaje, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseResumenTotalVar); ?>">
                        <?php echo e(formatearMoneda($resumenTotalVar)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($resumenTotalPto)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseResumenTotalPorcentajeAa); ?>">
                        <?php echo e(number_format($resumenTotalPorcentajeAa, 1)); ?>%
                    </td>
                    <td class="alineado-derecha <?php echo e($claseResumenTotalVarPto); ?>">
                        <?php echo e(formatearMoneda($resumenTotalVarPto)); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="1" cellpadding="6" cellspacing="0" class="tabla-resumen-supervisores">
            <thead>
                <tr>
                    <th colspan="8" class="encabezado-titulo">TRANSACCIONES POR SUPERVISOR</th>
                </tr>
                <tr>
                    <th>SUPERVISOR</th>
                    <th>TX <?php echo e($anioPto - 1); ?></th>
                    <th>TX <?php echo e($anioPto); ?></th>
                    <th>% AP</th>
                    <th>VARIACIÓN</th>
                    <th>PTO <?php echo e($anioPto); ?></th>
                    <th>% AA</th>
                    <th>VARIACIÓN PTO</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $txTotalResumenAc = 0;
                $txTotalResumenAp = 0;
                ?>
                <?php foreach ($unidadesPorSupervisor as $bloqueSupervisorTx): ?>
                <?php
                $txSupAc = 0;
                $txSupAp = 0;
                foreach ($bloqueSupervisorTx['unidades'] as $unidadTx) {
                    $fechaAperturaTxSup = isset($unidadTx['fapertura_unidad'])
                        ? trim((string)$unidadTx['fapertura_unidad'])
                        : '';
                    $fechaAperturaTxSup = $fechaAperturaTxSup !== '' ? substr($fechaAperturaTxSup, 0, 10) : '';
                    if (!unidadTieneAnioCumplido($fechaAperturaTxSup, $fechaCorteAcumulado)) {
                        continue;
                    }
                    $txSupAc += isset($unidadTx['txn_ac']) ? (int)$unidadTx['txn_ac'] : 0;
                    $txSupAp += isset($unidadTx['txn_ap']) ? (int)$unidadTx['txn_ap'] : 0;
                }
                $txSupVar = $txSupAp - $txSupAc;
                $txSupPct = $txSupAc == 0 ? 0 : (($txSupVar / $txSupAc) * 100);
                $claseTxSupPct = $txSupPct > 0 ? 'valor-positivo' : ($txSupPct < 0 ? 'valor-negativo' : '');
                $claseTxSupVar = $txSupVar > 0 ? 'valor-positivo' : ($txSupVar < 0 ? 'valor-negativo' : '');
                $txTotalResumenAc += $txSupAc;
                $txTotalResumenAp += $txSupAp;
                ?>
                <tr>
                    <td><?php echo e($bloqueSupervisorTx['supervisor']); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($txSupAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($txSupAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTxSupPct); ?>">
                        <?php echo e(number_format($txSupPct, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTxSupVar); ?>">
                        <?php echo e(number_format($txSupVar)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
                <?php endforeach; ?>
                <?php
                $txVarTotalResumen = $txTotalResumenAp - $txTotalResumenAc;
                $txPctTotalResumen = $txTotalResumenAc == 0 ? 0 : (($txVarTotalResumen / $txTotalResumenAc) * 100);
                $claseTxPctTotalResumen = $txPctTotalResumen > 0 ? 'valor-positivo' : ($txPctTotalResumen < 0 ? 'valor-negativo' : '');
                $claseTxVarTotalResumen = $txVarTotalResumen > 0 ? 'valor-positivo' : ($txVarTotalResumen < 0 ? 'valor-negativo' : '');
                ?>
                <tr class="fila-total">
                    <td>TOTAL</td>
                    <td class="alineado-derecha"><?php echo e(number_format($txTotalResumenAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($txTotalResumenAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTxPctTotalResumen); ?>">
                        <?php echo e(number_format($txPctTotalResumen, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTxVarTotalResumen); ?>">
                        <?php echo e(number_format($txVarTotalResumen)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
            </tbody>
        </table>
        <?php foreach ($unidadesPorSupervisor as $bloqueSupervisor): ?>
        <table border="1" cellpadding="6" cellspacing="0" style="margin-bottom: 20px; width: 100%;">
            <thead>
                <tr>
                    <th colspan="8"><?php echo e($bloqueSupervisor['supervisor']); ?></th>
                </tr>
                <tr>
                    <th>NOMBRE UNIDAD</th>
                    <th>FX <?php echo e($anioPto - 1); ?></th>
                    <th>FX <?php echo e($anioPto); ?></th>
                    <th>%AP</th>
                    <th>$VAR</th>
                    <th>PTO <?php echo e($anioPto); ?></th>
                    <th>%AA</th>
                    <th>VARIACIÓN PTO</th>
                </tr>
                <tr>
                    <th></th>
                    <th><?php echo e($textoPeriodoComparativo); ?></th>
                    <th><?php echo e($textoPeriodoActual); ?></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalFxAc = 0;
                $totalFxAp = 0;
                $totalPto = 0;
                $totalFxApConPto = 0;
                $totalTxnAc = 0;
                $totalTxnAp = 0;
            ?>
                <?php if (empty($bloqueSupervisor['unidades'])): ?>
                <tr>
                    <td colspan="10">Sin unidades asignadas.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($bloqueSupervisor['unidades'] as $unidad): ?>
                <?php
                $porcentajeAp = isset($unidad['porcentaje_ap']) ? (float)$unidad['porcentaje_ap'] : 0;
                $varMonto = isset($unidad['var']) ? (float)$unidad['var'] : 0;
                $ptoUnidad = isset($unidad['presupuesto']) ? (float)$unidad['presupuesto'] : 0;
                $porcentajeAa = isset($unidad['porcentaje_aa']) ? (float)$unidad['porcentaje_aa'] : 0;
                $variacionPto = isset($unidad['variacion_pto']) ? (float)$unidad['variacion_pto'] : 0;
                $clasePorcentaje = $porcentajeAp > 0 ? 'valor-positivo' : ($porcentajeAp < 0 ? 'valor-negativo' : '');
                $claseVar = $varMonto > 0 ? 'valor-positivo' : ($varMonto < 0 ? 'valor-negativo' : '');
                $clasePorcentajeAa = $porcentajeAa > 0 ? 'valor-positivo' : ($porcentajeAa < 0 ? 'valor-negativo' : '');
                $claseVariacionPto = $variacionPto > 0 ? 'valor-positivo' : ($variacionPto < 0 ? 'valor-negativo' : '');

                $fechaAperturaUnidad = isset($unidad['fapertura_unidad'])
                    ? trim((string)$unidad['fapertura_unidad'])
                    : '';
                $fechaAperturaUnidad = $fechaAperturaUnidad !== '' ? substr($fechaAperturaUnidad, 0, 10) : '';
                $esUnidadNueva = !unidadTieneAnioCumplido($fechaAperturaUnidad, $fechaCorteAcumulado);
                $claseFilaUnidad = $esUnidadNueva ? 'unidad-nueva' : '';

                $fxAcUnidad = isset($unidad['fx_ac']) ? (float)$unidad['fx_ac'] : 0;
                $fxApUnidad = isset($unidad['fx_ap']) ? (float)$unidad['fx_ap'] : 0;
                $txnAcUnidad = isset($unidad['txn_ac']) ? (int)$unidad['txn_ac'] : 0;
                $txnApUnidad = isset($unidad['txn_ap']) ? (int)$unidad['txn_ap'] : 0;
                $totalFxAc += $fxAcUnidad;
                $totalFxAp += $fxApUnidad;
                $totalPto += $ptoUnidad;
                $totalTxnAc += $txnAcUnidad;
                $totalTxnAp += $txnApUnidad;
                if ($ptoUnidad > 0) {
                    $totalFxApConPto += $fxApUnidad;
                }

                $idUnidadNombre = isset($unidad['id_unidad']) ? (int)$unidad['id_unidad'] : 0;
                $nombreUnidad = isset($unidad['nombre_unidad']) ? trim((string)$unidad['nombre_unidad']) : '';
                if ($nombreUnidad === '') {
                    $nombreUnidad = $idUnidadNombre > 0 ? ('Unidad #' . $idUnidadNombre) : 'Unidad sin nombre';
                }
                $nombreUnidadEscapado = e($nombreUnidad);
                if ($nombreUnidadEscapado === '') {
                    $nombreUnidadEscapado = $idUnidadNombre > 0 ? ('Unidad #' . $idUnidadNombre) : 'Unidad sin nombre';
                }
            ?>
                <tr class="<?php echo e($claseFilaUnidad); ?>">
                    <td><?php echo $nombreUnidadEscapado; ?></td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($fxAcUnidad)); ?></td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($fxApUnidad)); ?></td>
                    <td class="alineado-derecha <?php echo e($clasePorcentaje); ?>">
                        <?php echo e(number_format($porcentajeAp, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseVar); ?>">
                        <?php echo e(formatearMoneda($varMonto)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($ptoUnidad)); ?></td>
                    <td class="alineado-derecha <?php echo e($clasePorcentajeAa); ?>">
                        <?php echo e(number_format($porcentajeAa, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseVariacionPto); ?>">
                        <?php echo e(formatearMoneda($variacionPto)); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php
                $totalVar = $totalFxAp - $totalFxAc;
                $totalPorcentajeAp = $totalFxAc == 0 ? 0 : (($totalVar / $totalFxAc) * 100);
                $totalVarPto = $controller->calcularVariacionPto($totalPto, $totalFxApConPto);
                $totalPorcentajeAa = $controller->calcularPorcentajeAa($totalPto, $totalFxApConPto);
                $claseTotalPorcentaje = $totalPorcentajeAp > 0 ? 'valor-positivo' : ($totalPorcentajeAp < 0 ? 'valor-negativo' : '');
                $claseTotalVar = $totalVar > 0 ? 'valor-positivo' : ($totalVar < 0 ? 'valor-negativo' : '');
                $claseTotalPorcentajeAa = $totalPorcentajeAa > 0 ? 'valor-positivo' : ($totalPorcentajeAa < 0 ? 'valor-negativo' : '');
                $claseTotalVarPto = $totalVarPto > 0 ? 'valor-positivo' : ($totalVarPto < 0 ? 'valor-negativo' : '');
            ?>
                <tr class="fila-total">
                    <td>TOTAL</td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($totalFxAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($totalFxAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTotalPorcentaje); ?>">
                        <?php echo e(number_format($totalPorcentajeAp, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTotalVar); ?>">
                        <?php echo e(formatearMoneda($totalVar)); ?>
                    </td>
                    <td class="alineado-derecha"><?php echo e(formatearMoneda($totalPto)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTotalPorcentajeAa); ?>">
                        <?php echo e(number_format($totalPorcentajeAa, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTotalVarPto); ?>">
                        <?php echo e(formatearMoneda($totalVarPto)); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="1" cellpadding="6" cellspacing="0" style="margin-bottom: 20px; width: 100%;">
            <thead>
                <tr>
                    <th colspan="8"><?php echo e($bloqueSupervisor['supervisor']); ?> - TRANSACCIONES</th>
                </tr>
                <tr>
                    <th>NOMBRE UNIDAD</th>
                    <th>TX <?php echo e($anioPto - 1); ?></th>
                    <th>TX <?php echo e($anioPto); ?></th>
                    <th>% AP</th>
                    <th>VARIACIÓN</th>
                    <th>PTO <?php echo e($anioPto); ?></th>
                    <th>% AA</th>
                    <th>VARIACIÓN PTO</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalTxnDetAc = 0;
                $totalTxnDetAp = 0;
                ?>
                <?php foreach ($bloqueSupervisor['unidades'] as $unidadTxDet): ?>
                <?php
                $txDetAc = isset($unidadTxDet['txn_ac']) ? (int)$unidadTxDet['txn_ac'] : 0;
                $txDetAp = isset($unidadTxDet['txn_ap']) ? (int)$unidadTxDet['txn_ap'] : 0;
                $txDetVar = $txDetAp - $txDetAc;
                $txDetPct = $txDetAc == 0 ? 0 : (($txDetVar / $txDetAc) * 100);
                $claseTxDetPct = $txDetPct > 0 ? 'valor-positivo' : ($txDetPct < 0 ? 'valor-negativo' : '');
                $claseTxDetVar = $txDetVar > 0 ? 'valor-positivo' : ($txDetVar < 0 ? 'valor-negativo' : '');
                $fechaAperturaTxDet = isset($unidadTxDet['fapertura_unidad'])
                    ? trim((string)$unidadTxDet['fapertura_unidad'])
                    : '';
                $fechaAperturaTxDet = $fechaAperturaTxDet !== '' ? substr($fechaAperturaTxDet, 0, 10) : '';
                $esUnidadNuevaTx = !unidadTieneAnioCumplido($fechaAperturaTxDet, $fechaCorteAcumulado);
                $claseFilaUnidadTx = $esUnidadNuevaTx ? 'unidad-nueva' : '';
                if (!$esUnidadNuevaTx) {
                    $totalTxnDetAc += $txDetAc;
                    $totalTxnDetAp += $txDetAp;
                }
                ?>
                <tr class="<?php echo e($claseFilaUnidadTx); ?>">
                    <td><?php
                        $nombreTxDet = isset($unidadTxDet['nombre_unidad']) ? trim((string)$unidadTxDet['nombre_unidad']) : '';
                        $idTxDet = isset($unidadTxDet['id_unidad']) ? (int)$unidadTxDet['id_unidad'] : 0;
                        echo e($nombreTxDet !== '' ? $nombreTxDet : ($idTxDet > 0 ? 'Unidad #' . $idTxDet : 'Unidad sin nombre'));
                    ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($txDetAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($txDetAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTxDetPct); ?>">
                        <?php echo e(number_format($txDetPct, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTxDetVar); ?>">
                        <?php echo e(number_format($txDetVar)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
                <?php endforeach; ?>
                <?php
                $txDetVarTotal = $totalTxnDetAp - $totalTxnDetAc;
                $txDetPctTotal = $totalTxnDetAc == 0 ? 0 : (($txDetVarTotal / $totalTxnDetAc) * 100);
                $claseTxDetPctTotal = $txDetPctTotal > 0 ? 'valor-positivo' : ($txDetPctTotal < 0 ? 'valor-negativo' : '');
                $claseTxDetVarTotal = $txDetVarTotal > 0 ? 'valor-positivo' : ($txDetVarTotal < 0 ? 'valor-negativo' : '');
                ?>
                <tr class="fila-total">
                    <td>TOTAL</td>
                    <td class="alineado-derecha"><?php echo e(number_format($totalTxnDetAc)); ?></td>
                    <td class="alineado-derecha"><?php echo e(number_format($totalTxnDetAp)); ?></td>
                    <td class="alineado-derecha <?php echo e($claseTxDetPctTotal); ?>">
                        <?php echo e(number_format($txDetPctTotal, 1)); ?>%</td>
                    <td class="alineado-derecha <?php echo e($claseTxDetVarTotal); ?>">
                        <?php echo e(number_format($txDetVarTotal)); ?></td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                    <td class="alineado-derecha">-</td>
                </tr>
            </tbody>
        </table>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>

<script>
(function() {
    var formularioConsulta = document.getElementById('consulta-form');
    if (formularioConsulta) {
        formularioConsulta.addEventListener('submit', function() {
            var estadoConsulta = document.getElementById('estado-consulta');
            var botonConsultar = document.getElementById('btn-consultar');
            if (estadoConsulta) {
                estadoConsulta.style.display = 'block';
            }
            if (botonConsultar) {
                botonConsultar.disabled = true;
            }
        });
    }

    function obtenerDatosDesdeNodo(idNodo) {
        var nodo = document.getElementById(idNodo);
        if (!nodo) {
            return [];
        }

        try {
            var datos = JSON.parse(nodo.textContent || nodo.innerText || '[]');
            return Array.isArray(datos) ? datos : [];
        } catch (errorParseo) {
            return [];
        }
    }

    function redondearUno(valor) {
        return Math.round((Number(valor) || 0) * 10) / 10;
    }

    function formatearValor(valor) {
        var numero = redondearUno(valor);
        return numero.toFixed(1);
    }

    function normalizarDatosGrafica(datosCrudos) {
        var filtrados = [];
        var i;
        for (i = 0; i < datosCrudos.length; i++) {
            var fila = datosCrudos[i];
            if (!fila || typeof fila !== 'object') {
                continue;
            }

            filtrados.push({
                nombre: (fila.nombre || '').toString(),
                fx2025: Number(fila.fx_2025) || 0,
                fx2026: Number(fila.fx_2026) || 0
            });
        }
        return filtrados;
    }

    function dibujarGraficaUnidad(canvas, contenedor, datosCrudos) {
        if (!canvas || !contenedor) {
            return;
        }

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var datos = normalizarDatosGrafica(datosCrudos);
        var anchoVisible = contenedor.clientWidth || 540;
        var alturaLienzo = 360;
        var izquierda = 56;
        var derecha = 24;
        var arriba = 24;
        var fuenteEtiquetas = '11px Arial, Helvetica, sans-serif';
        var abajo = 90;
        var anchoGrupo = 62;
        var espacioGrupo = 26;
        var anchoBarras = 14;
        var espacioBarras = 7;
        var maxAnchoCanvas = 28000;

        if (datos.length > 0) {
            var anchoSlotActual = anchoGrupo + espacioGrupo;
            var anchoSlotMaximo = Math.floor((maxAnchoCanvas - izquierda - derecha) / datos.length);
            if (anchoSlotMaximo > 0 && anchoSlotMaximo < anchoSlotActual) {
                var anchoSlotNuevo = Math.max(8, anchoSlotMaximo);
                anchoGrupo = Math.max(5, Math.floor(anchoSlotNuevo * 0.65));
                espacioGrupo = Math.max(1, anchoSlotNuevo - anchoGrupo);
                espacioBarras = Math.max(1, Math.floor(anchoGrupo * 0.15));
                anchoBarras = Math.max(2, Math.floor((anchoGrupo - espacioBarras) / 2));
            }
        }

        if (datos.length > 0) {
            ctx.font = fuenteEtiquetas;
            var maxAnchoEtiqueta = 0;
            var indiceEtiqueta;
            for (indiceEtiqueta = 0; indiceEtiqueta < datos.length; indiceEtiqueta++) {
                var textoEtiqueta = (datos[indiceEtiqueta].nombre || '').toString();
                var anchoEtiqueta = ctx.measureText(textoEtiqueta).width;
                if (anchoEtiqueta > maxAnchoEtiqueta) {
                    maxAnchoEtiqueta = anchoEtiqueta;
                }
            }

            abajo = Math.max(90, Math.min(260, Math.ceil(maxAnchoEtiqueta + 18)));
        }

        var anchoNecesario = izquierda + derecha;
        if (datos.length > 0) {
            anchoNecesario += (datos.length * anchoGrupo) + ((datos.length - 1) * espacioGrupo);
        } else {
            anchoNecesario += 200;
        }

        var anchoFinal = Math.max(anchoVisible, anchoNecesario);
        if (anchoFinal > maxAnchoCanvas) {
            anchoFinal = maxAnchoCanvas;
        }
        var dpr = window.devicePixelRatio || 1;

        canvas.style.width = anchoFinal + 'px';
        canvas.style.height = alturaLienzo + 'px';
        canvas.width = Math.round(anchoFinal * dpr);
        canvas.height = Math.round(alturaLienzo * dpr);

        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(dpr, dpr);
        ctx.clearRect(0, 0, anchoFinal, alturaLienzo);

        if (datos.length === 0) {
            ctx.fillStyle = '#4f4f4f';
            ctx.font = 'bold 14px Arial, Helvetica, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('No hay datos para mostrar en la gráfica.', anchoFinal / 2, alturaLienzo / 2);
            return;
        }

        var minimo = 0;
        var maximo = 0;
        var idx;
        for (idx = 0; idx < datos.length; idx++) {
            var fila = datos[idx];
            if (fila.fx2025 < minimo) {
                minimo = fila.fx2025;
            }
            if (fila.fx2026 < minimo) {
                minimo = fila.fx2026;
            }

            if (fila.fx2025 > maximo) {
                maximo = fila.fx2025;
            }
            if (fila.fx2026 > maximo) {
                maximo = fila.fx2026;
            }
        }

        if (maximo <= 0) {
            maximo = 1;
        }
        if (minimo >= 0) {
            minimo = Math.min(0, minimo);
        }

        var rango = maximo - minimo;
        if (rango === 0) {
            rango = 1;
        }

        var altoGrafica = alturaLienzo - arriba - abajo;

        function obtenerY(valor) {
            return arriba + ((maximo - valor) / rango) * altoGrafica;
        }

        var yCero = obtenerY(0);
        var divisiones = 5;
        ctx.font = '11px Arial, Helvetica, sans-serif';
        ctx.textAlign = 'right';

        for (idx = 0; idx <= divisiones; idx++) {
            var valorLinea = maximo - (idx * (rango / divisiones));
            var yLinea = arriba + (idx * (altoGrafica / divisiones));
            ctx.beginPath();
            ctx.strokeStyle = '#d6dfd8';
            ctx.lineWidth = 1;
            ctx.moveTo(izquierda, yLinea);
            ctx.lineTo(anchoFinal - derecha, yLinea);
            ctx.stroke();

            ctx.fillStyle = '#4f5b54';
            ctx.fillText(formatearValor(valorLinea), izquierda - 8, yLinea + 3);
        }

        if (yCero >= arriba && yCero <= (alturaLienzo - abajo)) {
            ctx.beginPath();
            ctx.strokeStyle = '#4a4a4a';
            ctx.lineWidth = 1.2;
            ctx.moveTo(izquierda, yCero);
            ctx.lineTo(anchoFinal - derecha, yCero);
            ctx.stroke();
        }

        var colores = {
            fx2025: '#e4d01e',
            fx2026: '#ff8e2b'
        };

        ctx.textAlign = 'center';
        for (idx = 0; idx < datos.length; idx++) {
            var punto = datos[idx];
            var xCentro = izquierda + (idx * (anchoGrupo + espacioGrupo)) + (anchoGrupo / 2);
            var valores = [punto.fx2025, punto.fx2026];
            var anchoBloqueBarras = (anchoBarras * valores.length) + (espacioBarras * (valores.length - 1));
            var inicioBarras = xCentro - (anchoBloqueBarras / 2);

            var indiceBarra;
            for (indiceBarra = 0; indiceBarra < valores.length; indiceBarra++) {
                var valorBarra = valores[indiceBarra];
                var xBarra = inicioBarras + (indiceBarra * (anchoBarras + espacioBarras));
                var yValor = obtenerY(valorBarra);
                var yBarra = Math.min(yCero, yValor);
                var altoBarra = Math.abs(yValor - yCero);

                if (altoBarra < 1) {
                    altoBarra = 1;
                }

                var colorBarra = indiceBarra === 0 ? colores.fx2025 : colores.fx2026;

                ctx.fillStyle = colorBarra;
                ctx.fillRect(xBarra, yBarra, anchoBarras, altoBarra);

                ctx.fillStyle = '#2b2b2b';
                ctx.font = 'bold 10px Arial, Helvetica, sans-serif';
                var yTexto = valorBarra >= 0 ? (yBarra - 5) : (yBarra + altoBarra + 11);
                ctx.fillText(formatearValor(valorBarra), xBarra + (anchoBarras / 2), yTexto);
            }

            ctx.fillStyle = '#3b3b3b';
            ctx.font = fuenteEtiquetas;
            ctx.save();
            ctx.translate(xCentro, alturaLienzo - 8);
            ctx.rotate(-Math.PI / 2);
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText((punto.nombre || '').toString(), 0, 0);
            ctx.restore();
        }
    }

    var graficasUnidad = [];
    var nodosDatosGraficasUnidad = document.querySelectorAll('script[data-grafica-unidad="1"][data-grafica-id]');
    var indiceGraficaUnidad;
    for (indiceGraficaUnidad = 0; indiceGraficaUnidad < nodosDatosGraficasUnidad.length; indiceGraficaUnidad++) {
        var nodoDatosGraficaUnidad = nodosDatosGraficasUnidad[indiceGraficaUnidad];
        var idBaseGraficaUnidad = (nodoDatosGraficaUnidad.getAttribute('data-grafica-id') || '').toString();
        if (idBaseGraficaUnidad === '') {
            continue;
        }

        graficasUnidad.push({
            canvas: document.getElementById(idBaseGraficaUnidad + '-canvas'),
            contenedor: document.getElementById(idBaseGraficaUnidad + '-scroll'),
            datos: obtenerDatosDesdeNodo(nodoDatosGraficaUnidad.id)
        });
    }

    function redibujarGraficasUnidad() {
        var indiceRedibujo;
        for (indiceRedibujo = 0; indiceRedibujo < graficasUnidad.length; indiceRedibujo++) {
            var graficaUnidad = graficasUnidad[indiceRedibujo];
            dibujarGraficaUnidad(graficaUnidad.canvas, graficaUnidad.contenedor, graficaUnidad.datos);
        }
    }

    redibujarGraficasUnidad();

    var temporizadorResize = null;
    window.addEventListener('resize', function() {
        if (temporizadorResize) {
            clearTimeout(temporizadorResize);
        }
        temporizadorResize = setTimeout(function() {
            redibujarGraficasUnidad();
        }, 120);
    });
})();
</script>

</html>