<?php

class VentasController
{
    private $connections;
    private $pdoCache;
    private $presupuestoColumnasCache;
    private $tablaColumnasCache;
    private $rangoOrdenOperativoCache;
    private $tamannosVigentesCache;
    private $esquemasCobroVigentesCache;

    public function __construct(array $connections)
    {
        $this->connections = $connections;
        $this->pdoCache = array();
        $this->presupuestoColumnasCache = array();
        $this->tablaColumnasCache = array();
        $this->rangoOrdenOperativoCache = array();
        $this->tamannosVigentesCache = array();
        $this->esquemasCobroVigentesCache = array();
    }

    private function createPdo($connectionKey)
    {
        if (isset($this->pdoCache[$connectionKey]) && $this->pdoCache[$connectionKey] instanceof PDO) {
            return $this->pdoCache[$connectionKey];
        }

        if (!isset($this->connections[$connectionKey])) {
            throw new InvalidArgumentException('Conexión no válida.');
        }

        $config = $this->connections[$connectionKey];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $connectTimeout = isset($config['connect_timeout']) ? (int)$config['connect_timeout'] : 10;
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $connectTimeout,
        );

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            $this->pdoCache[$connectionKey] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'No se pudo conectar a ' . $connectionKey . ' en ' . $connectTimeout . 's. ' . $e->getMessage()
            );
        }
    }

    public function consultarUsuariosSupervisoresActivos($connectionKey)
    {
        $pdo = $this->createPdo($connectionKey);

        $sql = 'SELECT id_usuario, nombres_usuario, apellidos_usuario, razsoc_usuario, vigencia_usuario '
            . 'FROM usuario '
            . 'WHERE razsoc_usuario = :razsoc_usuario '
            . 'AND vigencia_usuario = :vigencia_usuario';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':razsoc_usuario', 'SUPERVISOR');
        $stmt->bindValue(':vigencia_usuario', 1, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function consultarUnidadesPorSupervisor($connectionKey, $idUsuario, $fechaReferencia = null)
    {
        $pdo = $this->createPdo($connectionKey);

        $sql = 'SELECT id_unidad, ' . $this->obtenerExpresionNombreUnidadSql() . ' AS nombre_unidad, '
            . 'fapertura_unidad, supervisor, activa_unidad '
            . 'FROM unidad '
            . 'WHERE supervisor = :id_usuario '
            . 'AND activa_unidad = :activa_unidad ';

        if ($fechaReferencia !== null && $fechaReferencia !== '') {
            $sql .= 'AND (fapertura_unidad IS NULL OR DATE(fapertura_unidad) <= :fecha_referencia) ';
        }

        $sql .= 'ORDER BY id_unidad ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_usuario', (int)$idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':activa_unidad', 1, PDO::PARAM_INT);
        if ($fechaReferencia !== null && $fechaReferencia !== '') {
            $stmt->bindValue(':fecha_referencia', $fechaReferencia);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function consultarUnidadesActivasPorSupervisores($connectionKey, array $idsUsuarios, $fechaReferencia = null)
    {
        if (empty($idsUsuarios)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $placeholders = array();
        foreach ($idsUsuarios as $index => $idUsuario) {
            $placeholders[] = ':id_usuario_' . $index;
        }

        $sql = 'SELECT id_unidad, ' . $this->obtenerExpresionNombreUnidadSql() . ' AS nombre_unidad, '
            . 'fapertura_unidad, supervisor, activa_unidad '
            . 'FROM unidad '
            . 'WHERE activa_unidad = :activa_unidad '
            . 'AND supervisor IN (' . implode(', ', $placeholders) . ') ';

        if ($fechaReferencia !== null && $fechaReferencia !== '') {
            $sql .= 'AND (fapertura_unidad IS NULL OR DATE(fapertura_unidad) <= :fecha_referencia) ';
        }

        $sql .= 'ORDER BY supervisor ASC, id_unidad ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':activa_unidad', 1, PDO::PARAM_INT);
        foreach ($idsUsuarios as $index => $idUsuario) {
            $stmt->bindValue(':id_usuario_' . $index, (int)$idUsuario, PDO::PARAM_INT);
        }
        if ($fechaReferencia !== null && $fechaReferencia !== '') {
            $stmt->bindValue(':fecha_referencia', $fechaReferencia);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function consultarUnidadesActivas($connectionKey, $fechaReferencia = null)
    {
        $pdo = $this->createPdo($connectionKey);

        $sql = 'SELECT id_unidad, ' . $this->obtenerExpresionNombreUnidadSql() . ' AS nombre_unidad, '
            . 'fapertura_unidad, activa_unidad '
            . 'FROM unidad '
            . 'WHERE activa_unidad = :activa_unidad ';

        if ($fechaReferencia !== null && $fechaReferencia !== '') {
            $sql .= 'AND (fapertura_unidad IS NULL OR DATE(fapertura_unidad) <= :fecha_referencia) ';
        }

        $sql .= 'ORDER BY id_unidad ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':activa_unidad', 1, PDO::PARAM_INT);
        if ($fechaReferencia !== null && $fechaReferencia !== '') {
            $stmt->bindValue(':fecha_referencia', $fechaReferencia);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function consultarUsuarioParaLogin($connectionKey, $idUsuario)
    {
        $pdo = $this->createPdo($connectionKey);

        $sql = 'SELECT id_usuario, nombres_usuario, apellidos_usuario, razsoc_usuario, vigencia_usuario, password '
            . 'FROM usuario '
            . 'WHERE id_usuario = :id_usuario '
            . 'LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_usuario', (int)$idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public function consultarUsuarioPorUidLogin($connectionKey, $uidUsuario)
    {
        $pdo = $this->createPdo($connectionKey);

        $uidUsuario = trim((string)$uidUsuario);
        if ($uidUsuario === '') {
            return null;
        }

        $sql = 'SELECT id_usuario, uid_usuario, nombres_usuario, apellidos_usuario, razsoc_usuario, vigencia_usuario, password '
            . 'FROM usuario '
            . 'WHERE LOWER(TRIM(uid_usuario)) = LOWER(:uid_usuario) '
            . 'LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':uid_usuario', $uidUsuario);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public function registrarUsuarioLogin($connectionKey, $uidUsuario, $nombres, $apellidos, $passwordHash)
    {
        $pdo = $this->createPdo($connectionKey);

        $uidUsuario = trim((string)$uidUsuario);
        $nombres = trim((string)$nombres);
        $apellidos = trim((string)$apellidos);
        $passwordHash = (string)$passwordHash;

        if ($uidUsuario === '') {
            throw new InvalidArgumentException('El usuario de acceso no es válido.');
        }
        if ($passwordHash === '') {
            throw new InvalidArgumentException('La contraseña no es válida.');
        }

        $usuarioActual = $this->consultarUsuarioPorUidLogin($connectionKey, $uidUsuario);

        if ($usuarioActual) {
            throw new InvalidArgumentException('El usuario ya existe.');
        }

        $sqlMax = 'SELECT IFNULL(MAX(id_usuario), 0) AS max_id FROM usuario';
        $stmtMax = $pdo->query($sqlMax);
        $rowMax = $stmtMax ? $stmtMax->fetch() : null;
        $idUsuarioNuevo = $rowMax && isset($rowMax['max_id']) ? ((int)$rowMax['max_id'] + 1) : 1;

        $sqlInsert = 'INSERT INTO usuario '
            . '(id_usuario, uid_usuario, nombres_usuario, apellidos_usuario, razsoc_usuario, vigencia_usuario, password) '
            . 'VALUES '
            . '(:id_usuario, :uid_usuario, :nombres_usuario, :apellidos_usuario, :razsoc_usuario, :vigencia_usuario, :password)';
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindValue(':id_usuario', $idUsuarioNuevo, PDO::PARAM_INT);
        $stmtInsert->bindValue(':uid_usuario', $uidUsuario);
        $stmtInsert->bindValue(':nombres_usuario', $nombres);
        $stmtInsert->bindValue(':apellidos_usuario', $apellidos);
        $stmtInsert->bindValue(':razsoc_usuario', 'USUARIO');
        $stmtInsert->bindValue(':vigencia_usuario', 1, PDO::PARAM_INT);
        $stmtInsert->bindValue(':password', $passwordHash);
        $stmtInsert->execute();

        return array(
            'id_usuario' => $idUsuarioNuevo,
            'nuevo' => true,
        );
    }

    public function consultarTotalesPorUnidadesDia($connectionKey, $idDiaOperativo, array $idsUnidades)
    {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT id_unidad, IFNULL(SUM(total_venta), 0) AS total_venta '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo = :id_diaoperativo '
            . 'AND id_unidad IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY id_unidad';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo', $idDiaOperativo);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        $stmt->execute();

        $totales = array();
        foreach ($stmt->fetchAll() as $row) {
            $totales[(int)$row['id_unidad']] = (float)$row['total_venta'];
        }

        return $totales;
    }

    public function consultarTotalesPorUnidadesRango($connectionKey, $idDiaOperativoInicio, $idDiaOperativoFin, array $idsUnidades)
    {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT id_unidad, IFNULL(SUM(total_venta), 0) AS total_venta '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo BETWEEN :id_diaoperativo_inicio AND :id_diaoperativo_fin '
            . 'AND id_unidad IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY id_unidad';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo_inicio', $idDiaOperativoInicio);
        $stmt->bindValue(':id_diaoperativo_fin', $idDiaOperativoFin);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        $stmt->execute();

        $totales = array();
        foreach ($stmt->fetchAll() as $row) {
            $totales[(int)$row['id_unidad']] = (float)$row['total_venta'];
        }

        return $totales;
    }

    public function consultarTotalesPorUnidadesEntreDiasAgrupado(
        $connectionKey,
        $idDiaOperativoInicio,
        $idDiaOperativoFin,
        array $idsUnidades
    ) {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT id_diaoperativo, id_unidad, IFNULL(SUM(total_venta), 0) AS total_venta '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo BETWEEN :id_diaoperativo_inicio AND :id_diaoperativo_fin '
            . 'AND id_unidad IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY id_diaoperativo, id_unidad';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo_inicio', $idDiaOperativoInicio);
        $stmt->bindValue(':id_diaoperativo_fin', $idDiaOperativoFin);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        $stmt->execute();

        $totales = array();
        foreach ($stmt->fetchAll() as $row) {
            $idDia = isset($row['id_diaoperativo']) ? (string)$row['id_diaoperativo'] : '';
            $idUnidad = isset($row['id_unidad']) ? (int)$row['id_unidad'] : 0;
            if ($idDia === '' || $idUnidad <= 0) {
                continue;
            }

            if (!isset($totales[$idDia])) {
                $totales[$idDia] = array();
            }
            $totales[$idDia][$idUnidad] = (float)$row['total_venta'];
        }

        return $totales;
    }

    public function consultarFxApPorUnidad($connectionKey, $idDiaOperativo, $idUnidad)
    {
        $pdo = $this->createPdo($connectionKey);

        $sql = 'SELECT IFNULL(SUM(total_venta), 0) AS total_venta '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo = :id_diaoperativo '
            . 'AND id_unidad = :id_unidad';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo', $idDiaOperativo);
        $stmt->bindValue(':id_unidad', (int)$idUnidad, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? (float)$row['total_venta'] : 0;
    }

    public function consultarFxApPorUnidadRango($connectionKey, $idDiaOperativoInicio, $idDiaOperativoFin, $idUnidad)
    {
        $pdo = $this->createPdo($connectionKey);

        $sql = 'SELECT IFNULL(SUM(total_venta), 0) AS total_venta '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo BETWEEN :id_diaoperativo_inicio AND :id_diaoperativo_fin '
            . 'AND id_unidad = :id_unidad';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo_inicio', $idDiaOperativoInicio);
        $stmt->bindValue(':id_diaoperativo_fin', $idDiaOperativoFin);
        $stmt->bindValue(':id_unidad', (int)$idUnidad, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? (float)$row['total_venta'] : 0;
    }

    public function consultarTransaccionesPorUnidadesDia($connectionKey, $idDiaOperativo, array $idsUnidades)
    {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT id_unidad, IFNULL(SUM(numero_venta), 0) AS num_transacciones '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo = :id_diaoperativo '
            . 'AND id_unidad IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY id_unidad';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo', $idDiaOperativo);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        $stmt->execute();

        $totales = array();
        foreach ($stmt->fetchAll() as $row) {
            $totales[(int)$row['id_unidad']] = (int)$row['num_transacciones'];
        }

        return $totales;
    }

    public function consultarTransaccionesPorUnidadesRango($connectionKey, $idDiaOperativoInicio, $idDiaOperativoFin, array $idsUnidades)
    {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT id_unidad, IFNULL(SUM(numero_venta), 0) AS num_transacciones '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo BETWEEN :id_diaoperativo_inicio AND :id_diaoperativo_fin '
            . 'AND id_unidad IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY id_unidad';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo_inicio', $idDiaOperativoInicio);
        $stmt->bindValue(':id_diaoperativo_fin', $idDiaOperativoFin);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        $stmt->execute();

        $totales = array();
        foreach ($stmt->fetchAll() as $row) {
            $totales[(int)$row['id_unidad']] = (int)$row['num_transacciones'];
        }

        return $totales;
    }

    public function consultarTransaccionesPorUnidadesEntreDiasAgrupado(
        $connectionKey,
        $idDiaOperativoInicio,
        $idDiaOperativoFin,
        array $idsUnidades
    ) {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT id_diaoperativo, id_unidad, IFNULL(SUM(numero_venta), 0) AS num_transacciones '
            . 'FROM vmx_res_ventas '
            . 'WHERE tipo_venta = :tipo_venta '
            . 'AND id_tipoorden = :id_tipoorden '
            . 'AND id_diaoperativo BETWEEN :id_diaoperativo_inicio AND :id_diaoperativo_fin '
            . 'AND id_unidad IN (' . implode(', ', $placeholders) . ') '
            . 'GROUP BY id_diaoperativo, id_unidad';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_venta', 'D');
        $stmt->bindValue(':id_tipoorden', 127, PDO::PARAM_INT);
        $stmt->bindValue(':id_diaoperativo_inicio', $idDiaOperativoInicio);
        $stmt->bindValue(':id_diaoperativo_fin', $idDiaOperativoFin);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        $stmt->execute();

        $totales = array();
        foreach ($stmt->fetchAll() as $row) {
            $idDia = isset($row['id_diaoperativo']) ? (string)$row['id_diaoperativo'] : '';
            $idUnidad = isset($row['id_unidad']) ? (int)$row['id_unidad'] : 0;
            if ($idDia === '' || $idUnidad <= 0) {
                continue;
            }

            if (!isset($totales[$idDia])) {
                $totales[$idDia] = array();
            }
            $totales[$idDia][$idUnidad] = (int)$row['num_transacciones'];
        }

        return $totales;
    }

    public function consultarPresupuestoPorUnidades(
        $connectionKey,
        array $idsUnidades,
        $anio,
        $idDiaOperativoInicio = null,
        $idDiaOperativoFin = null
    )
    {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        if (!isset($this->presupuestoColumnasCache[$connectionKey])) {
            $columnasStmt = $pdo->query('SHOW COLUMNS FROM presupuesto');
            $columnas = $columnasStmt ? $columnasStmt->fetchAll(PDO::FETCH_ASSOC) : array();
            if (empty($columnas)) {
                $this->presupuestoColumnasCache[$connectionKey] = array(
                    'id_unidad' => '',
                    'monto' => '',
                    'anio' => '',
                );
            } else {
                $nombresColumnas = array();
                foreach ($columnas as $columna) {
                    if (!isset($columna['Field'])) {
                        continue;
                    }
                    $nombresColumnas[] = strtolower((string)$columna['Field']);
                }

                $this->presupuestoColumnasCache[$connectionKey] = array(
                    'id_unidad' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('id_unidad', 'idunidad', 'unidad_id')
                    ),
                    'monto' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('presupuesto', 'monto', 'pto', 'presupuesto_anual', 'importe', 'valor', 'total_ppo')
                    ),
                    'anio' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('anio', 'anio_presupuesto', 'year', 'ejercicio')
                    ),
                    'id_diaoperativo' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('id_diaoperativo', 'iddiaoperativo', 'dia_operativo')
                    ),
                );
            }
        }

        $columnasPresupuesto = $this->presupuestoColumnasCache[$connectionKey];
        $columnaIdUnidad = isset($columnasPresupuesto['id_unidad']) ? $columnasPresupuesto['id_unidad'] : '';
        $columnaMonto = isset($columnasPresupuesto['monto']) ? $columnasPresupuesto['monto'] : '';
        $columnaAnio = isset($columnasPresupuesto['anio']) ? $columnasPresupuesto['anio'] : '';
        $columnaDiaOperativo =
            isset($columnasPresupuesto['id_diaoperativo']) ? $columnasPresupuesto['id_diaoperativo'] : '';

        if ($columnaIdUnidad === '' || $columnaMonto === '') {
            return array();
        }

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT ' . $columnaIdUnidad . ' AS id_unidad, '
            . 'IFNULL(SUM(' . $columnaMonto . '), 0) AS presupuesto '
            . 'FROM presupuesto '
            . 'WHERE ' . $columnaIdUnidad . ' IN (' . implode(', ', $placeholders) . ') ';

        if ($columnaDiaOperativo !== '' && $idDiaOperativoInicio !== null && $idDiaOperativoInicio !== '') {
            if ($idDiaOperativoFin !== null && $idDiaOperativoFin !== '' && (string)$idDiaOperativoFin !== (string)$idDiaOperativoInicio) {
                $sql .= 'AND ' . $columnaDiaOperativo . ' BETWEEN :id_diaoperativo_inicio AND :id_diaoperativo_fin ';
            } else {
                $sql .= 'AND ' . $columnaDiaOperativo . ' = :id_diaoperativo_inicio ';
            }
        } elseif ($columnaAnio !== '' && $anio !== null && $anio !== '') {
            $sql .= 'AND ' . $columnaAnio . ' = :anio ';
        }

        $sql .= 'GROUP BY ' . $columnaIdUnidad;

        $stmt = $pdo->prepare($sql);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        if ($columnaDiaOperativo !== '' && $idDiaOperativoInicio !== null && $idDiaOperativoInicio !== '') {
            $stmt->bindValue(':id_diaoperativo_inicio', (string)$idDiaOperativoInicio);
            if ($idDiaOperativoFin !== null && $idDiaOperativoFin !== '' && (string)$idDiaOperativoFin !== (string)$idDiaOperativoInicio) {
                $stmt->bindValue(':id_diaoperativo_fin', (string)$idDiaOperativoFin);
            }
        } elseif ($columnaAnio !== '' && $anio !== null && $anio !== '') {
            $stmt->bindValue(':anio', (int)$anio, PDO::PARAM_INT);
        }
        $stmt->execute();

        $presupuestos = array();
        foreach ($stmt->fetchAll() as $row) {
            $presupuestos[(int)$row['id_unidad']] = (float)$row['presupuesto'];
        }

        return $presupuestos;
    }

    public function consultarPresupuestoPorUnidadesAgrupado(
        $connectionKey,
        array $idsUnidades,
        $idDiaOperativoInicio,
        $idDiaOperativoFin
    ) {
        if (empty($idsUnidades)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        if (!isset($this->presupuestoColumnasCache[$connectionKey])) {
            $columnasStmt = $pdo->query('SHOW COLUMNS FROM presupuesto');
            $columnas = $columnasStmt ? $columnasStmt->fetchAll(PDO::FETCH_ASSOC) : array();
            if (empty($columnas)) {
                $this->presupuestoColumnasCache[$connectionKey] = array(
                    'id_unidad' => '',
                    'monto' => '',
                    'anio' => '',
                    'id_diaoperativo' => '',
                );
            } else {
                $nombresColumnas = array();
                foreach ($columnas as $columna) {
                    if (!isset($columna['Field'])) {
                        continue;
                    }
                    $nombresColumnas[] = strtolower((string)$columna['Field']);
                }

                $this->presupuestoColumnasCache[$connectionKey] = array(
                    'id_unidad' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('id_unidad', 'idunidad', 'unidad_id')
                    ),
                    'monto' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('presupuesto', 'monto', 'pto', 'presupuesto_anual', 'importe', 'valor', 'total_ppo')
                    ),
                    'anio' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('anio', 'anio_presupuesto', 'year', 'ejercicio')
                    ),
                    'id_diaoperativo' => $this->resolverNombreColumna(
                        $nombresColumnas,
                        array('id_diaoperativo', 'iddiaoperativo', 'dia_operativo')
                    ),
                );
            }
        }

        $columnasPresupuesto = $this->presupuestoColumnasCache[$connectionKey];
        $columnaIdUnidad = isset($columnasPresupuesto['id_unidad']) ? $columnasPresupuesto['id_unidad'] : '';
        $columnaMonto = isset($columnasPresupuesto['monto']) ? $columnasPresupuesto['monto'] : '';
        $columnaDiaOperativo =
            isset($columnasPresupuesto['id_diaoperativo']) ? $columnasPresupuesto['id_diaoperativo'] : '';

        if ($columnaIdUnidad === '' || $columnaMonto === '' || $columnaDiaOperativo === '') {
            return array();
        }

        $placeholders = array();
        foreach ($idsUnidades as $index => $idUnidad) {
            $placeholders[] = ':id_unidad_' . $index;
        }

        $sql = 'SELECT ' . $columnaDiaOperativo . ' AS id_diaoperativo, '
            . $columnaIdUnidad . ' AS id_unidad, '
            . 'IFNULL(SUM(' . $columnaMonto . '), 0) AS presupuesto '
            . 'FROM presupuesto '
            . 'WHERE ' . $columnaIdUnidad . ' IN (' . implode(', ', $placeholders) . ') '
            . 'AND ' . $columnaDiaOperativo . ' BETWEEN :id_diaoperativo_inicio AND :id_diaoperativo_fin '
            . 'GROUP BY ' . $columnaDiaOperativo . ', ' . $columnaIdUnidad;

        $stmt = $pdo->prepare($sql);
        foreach ($idsUnidades as $index => $idUnidad) {
            $stmt->bindValue(':id_unidad_' . $index, (int)$idUnidad, PDO::PARAM_INT);
        }
        $stmt->bindValue(':id_diaoperativo_inicio', (string)$idDiaOperativoInicio);
        $stmt->bindValue(':id_diaoperativo_fin', (string)$idDiaOperativoFin);
        $stmt->execute();

        $presupuestos = array();
        foreach ($stmt->fetchAll() as $row) {
            $idDia = isset($row['id_diaoperativo']) ? (string)$row['id_diaoperativo'] : '';
            $idUnidad = isset($row['id_unidad']) ? (int)$row['id_unidad'] : 0;
            if ($idDia === '' || $idUnidad <= 0) {
                continue;
            }
            if (!isset($presupuestos[$idDia])) {
                $presupuestos[$idDia] = array();
            }
            $presupuestos[$idDia][$idUnidad] = (float)$row['presupuesto'];
        }

        return $presupuestos;
    }

    public function calcularVariacionPto($presupuesto, $fxAp)
    {
        $presupuesto = (float)$presupuesto;
        if ($presupuesto <= 0) {
            return 0;
        }

        return (float)$fxAp - $presupuesto;
    }

    public function calcularPorcentajeAa($presupuesto, $fxAp)
    {
        $presupuesto = (float)$presupuesto;
        if ($presupuesto <= 0) {
            return 0;
        }

        return (((float)$fxAp - $presupuesto) / $presupuesto) * 100;
    }

    public function consultarTamannosVigentesPorTipo($connectionKey)
    {
        if (isset($this->tamannosVigentesCache[$connectionKey])) {
            return $this->tamannosVigentesCache[$connectionKey];
        }

        $pdo = $this->createPdo($connectionKey);
        $columnasTamanno = $this->obtenerColumnasTabla($pdo, 'tamanno');
        if (empty($columnasTamanno)) {
            $this->tamannosVigentesCache[$connectionKey] = array();
            return array();
        }

        $columnaIdTamanno = $this->resolverNombreColumna(
            $columnasTamanno,
            array('id_tamanno', 'id_tamano', 'tamanno', 'id')
        );
        $columnaDescripcionTamanno = $this->resolverNombreColumna(
            $columnasTamanno,
            array('descripcion_tamanno', 'descripcion', 'nombre_tamanno', 'nombre')
        );
        $columnaAbreviaturaTamanno = $this->resolverNombreColumna(
            $columnasTamanno,
            array('abreviatura_tamanno', 'abreviatura', 'abrev')
        );
        $columnaTipoTamanno = $this->resolverNombreColumna(
            $columnasTamanno,
            array('tipo', 'tipo_tamanno', 'categoria', 'grupo')
        );
        $columnaVigenciaTamanno = $this->resolverNombreColumna(
            $columnasTamanno,
            array('vigencia_tamanno', 'vigencia', 'activo', 'estatus')
        );

        if ($columnaIdTamanno === '' || $columnaTipoTamanno === '' || $columnaVigenciaTamanno === '') {
            $this->tamannosVigentesCache[$connectionKey] = array();
            return array();
        }

        $expresionNombreTamanno = 'CONCAT(\'TAMANNO #\', t.' . $columnaIdTamanno . ')';
        if ($columnaDescripcionTamanno !== '' && $columnaAbreviaturaTamanno !== '') {
            $expresionNombreTamanno = 'COALESCE('
                . 'NULLIF(TRIM(t.' . $columnaDescripcionTamanno . '), \'\'), '
                . 'NULLIF(TRIM(t.' . $columnaAbreviaturaTamanno . '), \'\'), '
                . 'CONCAT(\'TAMANNO #\', t.' . $columnaIdTamanno . '))';
        } elseif ($columnaDescripcionTamanno !== '') {
            $expresionNombreTamanno = 'COALESCE(NULLIF(TRIM(t.' . $columnaDescripcionTamanno
                . '), \'\'), CONCAT(\'TAMANNO #\', t.' . $columnaIdTamanno . '))';
        } elseif ($columnaAbreviaturaTamanno !== '') {
            $expresionNombreTamanno = 'COALESCE(NULLIF(TRIM(t.' . $columnaAbreviaturaTamanno
                . '), \'\'), CONCAT(\'TAMANNO #\', t.' . $columnaIdTamanno . '))';
        }

        $sql = 'SELECT t.' . $columnaIdTamanno . ' AS id_tamanno, '
            . $expresionNombreTamanno . ' AS nombre_tamanno, '
            . 't.' . $columnaTipoTamanno . ' AS tipo_tamanno '
            . 'FROM tamanno t '
            . 'WHERE t.' . $columnaVigenciaTamanno . ' = :vigencia_tamanno '
            . 'ORDER BY t.' . $columnaTipoTamanno . ' ASC, nombre_tamanno ASC, t.' . $columnaIdTamanno . ' ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':vigencia_tamanno', 1, PDO::PARAM_INT);
        $stmt->execute();

        $tamannos = array();
        foreach ($stmt->fetchAll() as $row) {
            $idTamanno = isset($row['id_tamanno']) ? (int)$row['id_tamanno'] : 0;
            if ($idTamanno <= 0) {
                continue;
            }

            $nombreTamanno = isset($row['nombre_tamanno']) ? trim((string)$row['nombre_tamanno']) : '';
            if ($nombreTamanno === '') {
                $nombreTamanno = 'TAMANNO #' . $idTamanno;
            }

            $tipoTamanno = isset($row['tipo_tamanno']) ? trim((string)$row['tipo_tamanno']) : '';

            $tamannos[] = array(
                'id_tamanno' => $idTamanno,
                'nombre' => $nombreTamanno,
                'tipo' => $tipoTamanno,
            );
        }

        $this->tamannosVigentesCache[$connectionKey] = $tamannos;

        return $tamannos;
    }

    public function consultarEsquemasCobroVigentes($connectionKey)
    {
        if (isset($this->esquemasCobroVigentesCache[$connectionKey])) {
            return $this->esquemasCobroVigentesCache[$connectionKey];
        }

        $pdo = $this->createPdo($connectionKey);
        $tablaEsquemaCobro = 'esquema_cobro';
        $columnasEsquemaCobro = $this->obtenerColumnasTabla($pdo, $tablaEsquemaCobro);
        if (empty($columnasEsquemaCobro)) {
            $tablaEsquemaCobro = 'esquemacobro';
            $columnasEsquemaCobro = $this->obtenerColumnasTabla($pdo, $tablaEsquemaCobro);
        }
        if (empty($columnasEsquemaCobro)) {
            $this->esquemasCobroVigentesCache[$connectionKey] = array();
            return array();
        }

        $columnaIdEsquemaCobro = $this->resolverNombreColumna(
            $columnasEsquemaCobro,
            array('id_esquemacobro', 'id_esquema_cobro', 'idesquemacobro', 'id')
        );
        $columnaNombreEsquemaCobro = $this->resolverNombreColumna(
            $columnasEsquemaCobro,
            array(
                'nombre_esquema_cobro',
                'nombre_esquemacobro',
                'descripcion_esquema_cobro',
                'descripcion_esquemacobro',
                'nombre',
                'descripcion',
                'desc_esquema_cobro',
                'desc_esquemacobro'
            )
        );
        $columnaVigenciaEsquemaCobro = $this->resolverNombreColumna(
            $columnasEsquemaCobro,
            array('vigencia_esquema_cobro', 'vigencia_esquemacobro', 'vigencia', 'activo', 'estatus')
        );

        if ($columnaIdEsquemaCobro === '' || $columnaVigenciaEsquemaCobro === '') {
            $this->esquemasCobroVigentesCache[$connectionKey] = array();
            return array();
        }

        $expresionNombreEsquemaCobro = 'CONCAT(\'ESQUEMA #\', e.' . $columnaIdEsquemaCobro . ')';
        if ($columnaNombreEsquemaCobro !== '') {
            $expresionNombreEsquemaCobro = 'COALESCE(NULLIF(TRIM(e.' . $columnaNombreEsquemaCobro
                . '), \'\'), CONCAT(\'ESQUEMA #\', e.' . $columnaIdEsquemaCobro . '))';
        }

        $sql = 'SELECT e.' . $columnaIdEsquemaCobro . ' AS id_esquemacobro, '
            . $expresionNombreEsquemaCobro . ' AS nombre_esquema '
            . 'FROM ' . $tablaEsquemaCobro . ' e '
            . 'WHERE e.' . $columnaVigenciaEsquemaCobro . ' = :vigencia_esquemacobro '
            . 'ORDER BY nombre_esquema ASC, e.' . $columnaIdEsquemaCobro . ' ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':vigencia_esquemacobro', 1, PDO::PARAM_INT);
        $stmt->execute();

        $esquemasCobro = array();
        foreach ($stmt->fetchAll() as $row) {
            $idEsquemaCobro = isset($row['id_esquemacobro']) ? (int)$row['id_esquemacobro'] : 0;
            if ($idEsquemaCobro <= 0) {
                continue;
            }

            $nombreEsquemaCobro = isset($row['nombre_esquema']) ? trim((string)$row['nombre_esquema']) : '';
            if ($nombreEsquemaCobro === '') {
                $nombreEsquemaCobro = 'ESQUEMA #' . $idEsquemaCobro;
            }

            $esquemasCobro[] = array(
                'id_esquemacobro' => $idEsquemaCobro,
                'nombre' => $nombreEsquemaCobro,
            );
        }

        $this->esquemasCobroVigentesCache[$connectionKey] = $esquemasCobro;

        return $esquemasCobro;
    }

    public function consultarNombresEsquemaCobroPorIds($connectionKey, array $idsEsquemaCobro)
    {
        $idsEsquemaLimpios = array();
        foreach ($idsEsquemaCobro as $idEsquemaCobro) {
            $idEsquemaCobro = (int)$idEsquemaCobro;
            if ($idEsquemaCobro > 0) {
                $idsEsquemaLimpios[] = $idEsquemaCobro;
            }
        }
        $idsEsquemaLimpios = array_values(array_unique($idsEsquemaLimpios));

        if (empty($idsEsquemaLimpios)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);
        $tablaEsquemaCobro = 'esquema_cobro';
        $columnasEsquemaCobro = $this->obtenerColumnasTabla($pdo, $tablaEsquemaCobro);
        if (empty($columnasEsquemaCobro)) {
            $tablaEsquemaCobro = 'esquemacobro';
            $columnasEsquemaCobro = $this->obtenerColumnasTabla($pdo, $tablaEsquemaCobro);
        }
        if (empty($columnasEsquemaCobro)) {
            return array();
        }

        $columnaIdEsquemaCobro = $this->resolverNombreColumna(
            $columnasEsquemaCobro,
            array('id_esquemacobro', 'id_esquema_cobro', 'idesquemacobro', 'id')
        );
        if ($columnaIdEsquemaCobro === '') {
            return array();
        }

        $columnaNombreEsquemaCobro = $this->resolverNombreColumna(
            $columnasEsquemaCobro,
            array(
                'nombre_esquema_cobro',
                'nombre_esquemacobro',
                'descripcion_esquema_cobro',
                'descripcion_esquemacobro',
                'nombre',
                'descripcion',
                'desc_esquema_cobro',
                'desc_esquemacobro'
            )
        );

        $expresionNombreEsquemaCobro = 'CONCAT(\'ESQUEMA #\', e.' . $columnaIdEsquemaCobro . ')';
        if ($columnaNombreEsquemaCobro !== '') {
            $expresionNombreEsquemaCobro = 'COALESCE(NULLIF(TRIM(e.' . $columnaNombreEsquemaCobro
                . '), \'\'), CONCAT(\'ESQUEMA #\', e.' . $columnaIdEsquemaCobro . '))';
        }

        $placeholders = array();
        foreach ($idsEsquemaLimpios as $indice => $idEsquemaCobro) {
            $placeholders[] = ':id_esquema_' . $indice;
        }

        $sql = 'SELECT e.' . $columnaIdEsquemaCobro . ' AS id_esquemacobro, '
            . $expresionNombreEsquemaCobro . ' AS nombre_esquema '
            . 'FROM ' . $tablaEsquemaCobro . ' e '
            . 'WHERE e.' . $columnaIdEsquemaCobro . ' IN (' . implode(', ', $placeholders) . ') '
            . 'ORDER BY e.' . $columnaIdEsquemaCobro . ' ASC';

        $stmt = $pdo->prepare($sql);
        foreach ($idsEsquemaLimpios as $indice => $idEsquemaCobro) {
            $stmt->bindValue(':id_esquema_' . $indice, $idEsquemaCobro, PDO::PARAM_INT);
        }
        $stmt->execute();

        $nombres = array();
        foreach ($stmt->fetchAll() as $row) {
            $idEsquemaCobro = isset($row['id_esquemacobro']) ? (int)$row['id_esquemacobro'] : 0;
            if ($idEsquemaCobro <= 0) {
                continue;
            }

            $nombreEsquemaCobro = isset($row['nombre_esquema']) ? trim((string)$row['nombre_esquema']) : '';
            if ($nombreEsquemaCobro === '') {
                $nombreEsquemaCobro = 'ESQUEMA #' . $idEsquemaCobro;
            }

            $nombres[$idEsquemaCobro] = $nombreEsquemaCobro;
        }

        return $nombres;
    }

    public function consultarCantidadPorTamannoRangoOperativo(
        $connectionKey,
        $idUnidad,
        $idDiaOperativoInicio,
        $idDiaOperativoFin,
        array $idsTamanno
    ) {
        $idUnidad = (int)$idUnidad;
        if ($idUnidad <= 0 || empty($idsTamanno)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $rangoOrdenes = $this->obtenerRangoOrdenesOperativo(
            $pdo,
            $connectionKey,
            $idUnidad,
            $idDiaOperativoInicio,
            $idDiaOperativoFin
        );
        $ordenInicial = isset($rangoOrdenes['orden_inicial']) ? (int)$rangoOrdenes['orden_inicial'] : 0;
        $ordenFinal = isset($rangoOrdenes['orden_final']) ? (int)$rangoOrdenes['orden_final'] : 0;
        if ($ordenInicial <= 0 || $ordenFinal <= 0 || $ordenFinal < $ordenInicial) {
            return array();
        }

        $placeholders = array();
        foreach ($idsTamanno as $index => $idTamanno) {
            $placeholders[] = ':id_tamanno_' . $index;
        }

        $columnasOrden = $this->obtenerColumnasTabla($pdo, 'vmx_orden');
        $columnasProducto = $this->obtenerColumnasTabla($pdo, 'vmx_producto');
        if (empty($columnasOrden) || empty($columnasProducto)) {
            return array();
        }

        $columnaTamannoProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_tamanno', 'id_tamano', 'tamanno', 'tamano')
        );
        $columnaCantidadProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('cantidad', 'cantidad_producto', 'cant_producto', 'cant')
        );
        $columnaEsquemaCobroProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_esquemacobro', 'id_esquema_cobro', 'esquemacobro')
        );

        if ($columnaTamannoProducto === '' || $columnaCantidadProducto === '') {
            return array();
        }

        $sql = 'SELECT p.' . $columnaTamannoProducto . ' AS id_tamanno, '
            . 'IFNULL(SUM(p.' . $columnaCantidadProducto . '), 0) AS cantidad '
            . 'FROM vmx_orden o '
            . 'INNER JOIN vmx_producto p ON p.id_unidad = o.id_unidad AND p.id_orden = o.id_orden '
            . 'WHERE o.id_unidad = :id_unidad '
            . 'AND o.id_orden BETWEEN :orden_inicial AND :orden_final '
            . 'AND o.consec_orden > 0 '
            . 'AND p.id_unidad = :id_unidad_producto '
            . 'AND p.id_orden BETWEEN :orden_inicial_producto AND :orden_final_producto '
            . 'AND p.' . $columnaTamannoProducto . ' IN (' . implode(', ', $placeholders) . ') ';

        if ($columnaEsquemaCobroProducto !== '') {
            $sql .= 'AND (p.' . $columnaEsquemaCobroProducto . ' < 1000 OR p.' . $columnaEsquemaCobroProducto . ' > 1005 OR p.' . $columnaEsquemaCobroProducto . ' IS NULL) ';
        }

        $sql .= 'GROUP BY p.' . $columnaTamannoProducto;

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_unidad', $idUnidad, PDO::PARAM_INT);
        $stmt->bindValue(':orden_inicial', $ordenInicial, PDO::PARAM_INT);
        $stmt->bindValue(':orden_final', $ordenFinal, PDO::PARAM_INT);
        $stmt->bindValue(':id_unidad_producto', $idUnidad, PDO::PARAM_INT);
        $stmt->bindValue(':orden_inicial_producto', $ordenInicial, PDO::PARAM_INT);
        $stmt->bindValue(':orden_final_producto', $ordenFinal, PDO::PARAM_INT);
        foreach ($idsTamanno as $index => $idTamanno) {
            $stmt->bindValue(':id_tamanno_' . $index, (int)$idTamanno, PDO::PARAM_INT);
        }
        $stmt->execute();

        $cantidades = array();
        foreach ($stmt->fetchAll() as $row) {
            $idTamanno = isset($row['id_tamanno']) ? (int)$row['id_tamanno'] : 0;
            if ($idTamanno <= 0) {
                continue;
            }
            $cantidades[$idTamanno] = (float)$row['cantidad'];
        }

        return $cantidades;
    }

    public function consultarCantidadOrillaQuesoPorTamannoRangoOperativo(
        $connectionKey,
        $idUnidad,
        $idDiaOperativoInicio,
        $idDiaOperativoFin,
        array $idsTamanno,
        array $idsRecetaOrillaQueso
    ) {
        $idUnidad = (int)$idUnidad;
        if ($idUnidad <= 0 || empty($idsTamanno) || empty($idsRecetaOrillaQueso)) {
            return array();
        }

        $idsRecetaLimpios = array();
        foreach ($idsRecetaOrillaQueso as $idRecetaOrillaQueso) {
            $idRecetaOrillaQueso = (int)$idRecetaOrillaQueso;
            if ($idRecetaOrillaQueso > 0) {
                $idsRecetaLimpios[] = $idRecetaOrillaQueso;
            }
        }
        $idsRecetaLimpios = array_values(array_unique($idsRecetaLimpios));
        if (empty($idsRecetaLimpios)) {
            return array();
        }

        $pdo = $this->createPdo($connectionKey);

        $rangoOrdenes = $this->obtenerRangoOrdenesOperativo(
            $pdo,
            $connectionKey,
            $idUnidad,
            $idDiaOperativoInicio,
            $idDiaOperativoFin
        );
        $ordenInicial = isset($rangoOrdenes['orden_inicial']) ? (int)$rangoOrdenes['orden_inicial'] : 0;
        $ordenFinal = isset($rangoOrdenes['orden_final']) ? (int)$rangoOrdenes['orden_final'] : 0;
        if ($ordenInicial <= 0 || $ordenFinal <= 0 || $ordenFinal < $ordenInicial) {
            return array();
        }

        $columnasProducto = $this->obtenerColumnasTabla($pdo, 'vmx_producto');
        if (empty($columnasProducto)) {
            return array();
        }

        $tablaComponente = '';
        $columnasComponente = array();
        $tablasComponenteCandidatas = array('vmx_componente', 'componente');
        foreach ($tablasComponenteCandidatas as $tablaComponenteCandidata) {
            $columnasComponenteCandidata = $this->obtenerColumnasTabla($pdo, $tablaComponenteCandidata);
            if (empty($columnasComponenteCandidata)) {
                continue;
            }

            if (
                $this->resolverNombreColumna($columnasComponenteCandidata, array('id_receta', 'idreceta', 'receta'))
                === ''
            ) {
                continue;
            }

            $tablaComponente = $tablaComponenteCandidata;
            $columnasComponente = $columnasComponenteCandidata;
            break;
        }

        if ($tablaComponente === '' || empty($columnasComponente)) {
            return array();
        }

        $columnaTamannoProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_tamanno', 'id_tamano', 'tamanno', 'tamano')
        );
        $columnaCantidadProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('cantidad', 'cantidad_producto', 'cant_producto', 'cant')
        );
        $columnaEsquemaCobroProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_esquemacobro', 'id_esquema_cobro', 'esquemacobro')
        );
        $columnaIdUnidadProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_unidad', 'idunidad', 'unidad_id')
        );
        $columnaIdOrdenProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_orden', 'idorden', 'orden_id')
        );

        $columnaIdUnidadComponente = $this->resolverNombreColumna(
            $columnasComponente,
            array('id_unidad', 'idunidad', 'unidad_id')
        );
        $columnaIdOrdenComponente = $this->resolverNombreColumna(
            $columnasComponente,
            array('id_orden', 'idorden', 'orden_id')
        );
        $columnaIdRecetaComponente = $this->resolverNombreColumna(
            $columnasComponente,
            array('id_receta', 'idreceta', 'receta')
        );

        if (
            $columnaTamannoProducto === '' || $columnaCantidadProducto === ''
            || $columnaIdUnidadProducto === '' || $columnaIdOrdenProducto === ''
            || $columnaIdUnidadComponente === '' || $columnaIdOrdenComponente === '' || $columnaIdRecetaComponente === ''
        ) {
            return array();
        }

        $columnaIdProductoProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_producto', 'idproducto', 'producto_id', 'consec_producto', 'consecutivo_producto', 'renglon')
        );
        $columnaIdProductoComponente = $this->resolverNombreColumna(
            $columnasComponente,
            array('id_producto', 'idproducto', 'producto_id', 'consec_producto', 'consecutivo_producto', 'renglon')
        );

        $placeholdersTamanno = array();
        foreach ($idsTamanno as $index => $idTamanno) {
            $placeholdersTamanno[] = ':id_tamanno_' . $index;
        }

        $placeholdersReceta = array();
        foreach ($idsRecetaLimpios as $index => $idReceta) {
            $placeholdersReceta[] = ':id_receta_' . $index;
        }

        $sql = 'SELECT p.' . $columnaTamannoProducto . ' AS id_tamanno, '
            . 'IFNULL(SUM(p.' . $columnaCantidadProducto . '), 0) AS cantidad '
            . 'FROM vmx_orden o '
            . 'INNER JOIN vmx_producto p ON p.id_unidad = o.id_unidad AND p.id_orden = o.id_orden '
            . 'WHERE o.id_unidad = :id_unidad '
            . 'AND o.id_orden BETWEEN :orden_inicial AND :orden_final '
            . 'AND o.consec_orden > 0 '
            . 'AND p.id_unidad = :id_unidad_producto '
            . 'AND p.id_orden BETWEEN :orden_inicial_producto AND :orden_final_producto '
            . 'AND p.' . $columnaTamannoProducto . ' IN (' . implode(', ', $placeholdersTamanno) . ') ';

        if ($columnaEsquemaCobroProducto !== '') {
            $sql .= 'AND (p.' . $columnaEsquemaCobroProducto . ' < 1000 OR p.' . $columnaEsquemaCobroProducto . ' > 1005 OR p.' . $columnaEsquemaCobroProducto . ' IS NULL) ';
        }

        $sql .= 'AND EXISTS ('
            . 'SELECT 1 FROM ' . $tablaComponente . ' c '
            . 'WHERE c.' . $columnaIdUnidadComponente . ' = p.' . $columnaIdUnidadProducto . ' '
            . 'AND c.' . $columnaIdOrdenComponente . ' = p.' . $columnaIdOrdenProducto . ' ';

        if ($columnaIdProductoProducto !== '' && $columnaIdProductoComponente !== '') {
            $sql .= 'AND c.' . $columnaIdProductoComponente . ' = p.' . $columnaIdProductoProducto . ' ';
        }

        $sql .= 'AND c.' . $columnaIdRecetaComponente . ' IN (' . implode(', ', $placeholdersReceta) . ') '
            . ') '
            . 'GROUP BY p.' . $columnaTamannoProducto;

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_unidad', $idUnidad, PDO::PARAM_INT);
        $stmt->bindValue(':orden_inicial', $ordenInicial, PDO::PARAM_INT);
        $stmt->bindValue(':orden_final', $ordenFinal, PDO::PARAM_INT);
        $stmt->bindValue(':id_unidad_producto', $idUnidad, PDO::PARAM_INT);
        $stmt->bindValue(':orden_inicial_producto', $ordenInicial, PDO::PARAM_INT);
        $stmt->bindValue(':orden_final_producto', $ordenFinal, PDO::PARAM_INT);

        foreach ($idsTamanno as $index => $idTamanno) {
            $stmt->bindValue(':id_tamanno_' . $index, (int)$idTamanno, PDO::PARAM_INT);
        }

        foreach ($idsRecetaLimpios as $index => $idReceta) {
            $stmt->bindValue(':id_receta_' . $index, $idReceta, PDO::PARAM_INT);
        }

        $stmt->execute();

        $cantidades = array();
        foreach ($stmt->fetchAll() as $row) {
            $idTamanno = isset($row['id_tamanno']) ? (int)$row['id_tamanno'] : 0;
            if ($idTamanno <= 0) {
                continue;
            }
            $cantidades[$idTamanno] = (float)$row['cantidad'];
        }

        return $cantidades;
    }

    public function consultarTicketsPorEsquemaCobroRangoOperativo(
        $connectionKey,
        $idUnidad,
        $idDiaOperativoInicio,
        $idDiaOperativoFin,
        array $idsEsquemaIncluidos,
        array $idsEsquemaExcluidos,
        $incluirNombreEsquema = true
    ) {
        $idUnidad = (int)$idUnidad;
        if ($idUnidad <= 0) {
            return array();
        }

        $incluirNombreEsquema = (bool)$incluirNombreEsquema;

        $pdo = $this->createPdo($connectionKey);

        $rangoOrdenes = $this->obtenerRangoOrdenesOperativo(
            $pdo,
            $connectionKey,
            $idUnidad,
            $idDiaOperativoInicio,
            $idDiaOperativoFin
        );
        $ordenInicial = isset($rangoOrdenes['orden_inicial']) ? (int)$rangoOrdenes['orden_inicial'] : 0;
        $ordenFinal = isset($rangoOrdenes['orden_final']) ? (int)$rangoOrdenes['orden_final'] : 0;
        if ($ordenInicial <= 0 || $ordenFinal <= 0 || $ordenFinal < $ordenInicial) {
            return array();
        }

        $columnasOrden = $this->obtenerColumnasTabla($pdo, 'vmx_orden');
        $columnasProducto = $this->obtenerColumnasTabla($pdo, 'vmx_producto');
        if (empty($columnasOrden) || empty($columnasProducto)) {
            return array();
        }

        $columnaEsquemaCobroProducto = $this->resolverNombreColumna(
            $columnasProducto,
            array('id_esquemacobro', 'id_esquema_cobro', 'esquemacobro')
        );
        if ($columnaEsquemaCobroProducto === '') {
            return array();
        }

        $expresionNombreEsquema = 'CONCAT(\'ESQUEMA #\', p.' . $columnaEsquemaCobroProducto . ')';
        $joinEsquemaCobro = '';
        if ($incluirNombreEsquema) {
            $tablaEsquemaCobroVenta = 'vmx_esquema_cobro';
            $columnasEsquemaCobro = $this->obtenerColumnasTabla($pdo, $tablaEsquemaCobroVenta);
            if (empty($columnasEsquemaCobro)) {
                $tablaEsquemaCobroVenta = 'vmx_esquemacobro';
                $columnasEsquemaCobro = $this->obtenerColumnasTabla($pdo, $tablaEsquemaCobroVenta);
            }

            $columnaIdEsquemaCobro = '';
            $columnaNombreEsquemaCobro = '';
            if (!empty($columnasEsquemaCobro)) {
                $columnaIdEsquemaCobro = $this->resolverNombreColumna(
                    $columnasEsquemaCobro,
                    array('id_esquemacobro', 'id_esquema_cobro', 'idesquemacobro', 'id')
                );
                $columnaNombreEsquemaCobro = $this->resolverNombreColumna(
                    $columnasEsquemaCobro,
                    array(
                        'nombre_esquema_cobro',
                        'nombre_esquemacobro',
                        'descripcion_esquema_cobro',
                        'descripcion_esquemacobro',
                        'nombre',
                        'descripcion',
                        'desc_esquema_cobro',
                        'desc_esquemacobro'
                    )
                );
            }

            if ($columnaIdEsquemaCobro !== '') {
                $joinEsquemaCobro = 'LEFT JOIN ' . $tablaEsquemaCobroVenta . ' e ON e.' . $columnaIdEsquemaCobro
                    . ' = p.' . $columnaEsquemaCobroProducto . ' ';
                if ($columnaNombreEsquemaCobro !== '') {
                    $expresionNombreEsquema = 'COALESCE(NULLIF(TRIM(e.' . $columnaNombreEsquemaCobro
                        . '), \'\'), CONCAT(\'ESQUEMA #\', p.' . $columnaEsquemaCobroProducto . '))';
                }
            }
        }

        $sql = 'SELECT p.' . $columnaEsquemaCobroProducto . ' AS id_esquemacobro, '
            . 'COUNT(DISTINCT o.id_orden) AS cantidad_tickets ';

        if ($incluirNombreEsquema) {
            $sql .= ', MAX(' . $expresionNombreEsquema . ') AS nombre_esquema ';
        }

        $sql .= 'FROM vmx_orden o '
            . 'INNER JOIN vmx_producto p ON p.id_unidad = o.id_unidad AND p.id_orden = o.id_orden '
            . $joinEsquemaCobro
            . 'WHERE o.id_unidad = :id_unidad '
            . 'AND o.id_orden BETWEEN :orden_inicial AND :orden_final '
            . 'AND o.consec_orden > 0 '
            . 'AND p.id_unidad = :id_unidad_producto '
            . 'AND p.id_orden BETWEEN :orden_inicial_producto AND :orden_final_producto ';

        $stmt = null;
        $idsEsquemaExcluidosLimpios = array(1000, 1001, 1002, 1003, 1004, 1005);
        foreach ($idsEsquemaExcluidos as $idEsquemaExcluido) {
            $idEsquemaExcluido = (int)$idEsquemaExcluido;
            if ($idEsquemaExcluido > 0) {
                $idsEsquemaExcluidosLimpios[] = $idEsquemaExcluido;
            }
        }
        $idsEsquemaExcluidosLimpios = array_values(array_unique($idsEsquemaExcluidosLimpios));

        $idsEsquemaIncluidosLimpios = array();
        foreach ($idsEsquemaIncluidos as $idEsquemaIncluido) {
            $idEsquemaIncluido = (int)$idEsquemaIncluido;
            if ($idEsquemaIncluido > 0 && !in_array($idEsquemaIncluido, $idsEsquemaExcluidosLimpios, true)) {
                $idsEsquemaIncluidosLimpios[] = $idEsquemaIncluido;
            }
        }
        $idsEsquemaIncluidosLimpios = array_values(array_unique($idsEsquemaIncluidosLimpios));

        if (!empty($idsEsquemaIncluidosLimpios)) {
            $placeholdersIncluidos = array();
            foreach ($idsEsquemaIncluidosLimpios as $indice => $idEsquemaIncluido) {
                $placeholdersIncluidos[] = ':id_esquema_in_' . $indice;
            }
            $sql .= 'AND p.' . $columnaEsquemaCobroProducto . ' IN (' . implode(', ', $placeholdersIncluidos) . ') ';
        } else {
            $sql .= 'AND p.' . $columnaEsquemaCobroProducto . ' IS NOT NULL '
                . 'AND p.' . $columnaEsquemaCobroProducto . ' > 0 ';

            if (!empty($idsEsquemaExcluidosLimpios)) {
                $placeholdersExcluidos = array();
                foreach ($idsEsquemaExcluidosLimpios as $indice => $idEsquemaExcluido) {
                    $placeholdersExcluidos[] = ':id_esquema_out_' . $indice;
                }
                $sql .= 'AND p.' . $columnaEsquemaCobroProducto . ' NOT IN (' . implode(', ', $placeholdersExcluidos) . ') ';
            }
        }

        $sql .= 'GROUP BY p.' . $columnaEsquemaCobroProducto . ' '
            . 'ORDER BY cantidad_tickets DESC, p.' . $columnaEsquemaCobroProducto . ' ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_unidad', $idUnidad, PDO::PARAM_INT);
        $stmt->bindValue(':orden_inicial', $ordenInicial, PDO::PARAM_INT);
        $stmt->bindValue(':orden_final', $ordenFinal, PDO::PARAM_INT);
        $stmt->bindValue(':id_unidad_producto', $idUnidad, PDO::PARAM_INT);
        $stmt->bindValue(':orden_inicial_producto', $ordenInicial, PDO::PARAM_INT);
        $stmt->bindValue(':orden_final_producto', $ordenFinal, PDO::PARAM_INT);

        if (!empty($idsEsquemaIncluidosLimpios)) {
            foreach ($idsEsquemaIncluidosLimpios as $indice => $idEsquemaIncluido) {
                $stmt->bindValue(':id_esquema_in_' . $indice, $idEsquemaIncluido, PDO::PARAM_INT);
            }
        } else {
            foreach ($idsEsquemaExcluidosLimpios as $indice => $idEsquemaExcluido) {
                $stmt->bindValue(':id_esquema_out_' . $indice, $idEsquemaExcluido, PDO::PARAM_INT);
            }
        }

        $stmt->execute();

        $resultados = array();
        foreach ($stmt->fetchAll() as $row) {
            $idEsquemaCobro = isset($row['id_esquemacobro']) ? (int)$row['id_esquemacobro'] : 0;
            if ($idEsquemaCobro <= 0) {
                continue;
            }

            $nombreEsquema = '';
            if ($incluirNombreEsquema && isset($row['nombre_esquema'])) {
                $nombreEsquema = trim((string)$row['nombre_esquema']);
            }
            if ($nombreEsquema === '') {
                $nombreEsquema = 'ESQUEMA #' . $idEsquemaCobro;
            }

            $resultados[$idEsquemaCobro] = array(
                'id_esquemacobro' => $idEsquemaCobro,
                'nombre' => $nombreEsquema,
                'cantidad_tickets' => isset($row['cantidad_tickets']) ? (float)$row['cantidad_tickets'] : 0,
            );
        }

        return $resultados;
    }

    private function obtenerRangoOrdenesOperativo(
        PDO $pdo,
        $connectionKey,
        $idUnidad,
        $idDiaOperativoInicio,
        $idDiaOperativoFin
    ) {
        $idUnidad = (int)$idUnidad;
        if ($idUnidad <= 0) {
            return array(
                'orden_inicial' => 0,
                'orden_final' => 0,
            );
        }

        $cacheKey = (string)$connectionKey
            . '|' . $idUnidad
            . '|' . (string)$idDiaOperativoInicio
            . '|' . (string)$idDiaOperativoFin;

        if (isset($this->rangoOrdenOperativoCache[$cacheKey])) {
            return $this->rangoOrdenOperativoCache[$cacheKey];
        }

        $sqlRango = 'SELECT '
            . '(SELECT oinicial_diaoperativo FROM vmx_diaoperativo '
            . 'WHERE id_unidad = :id_unidad_ini AND id_diaoperativo = :id_diaoperativo_inicio LIMIT 1) AS oinicial, '
            . '(SELECT ofinal_diaoperativo FROM vmx_diaoperativo '
            . 'WHERE id_unidad = :id_unidad_fin AND id_diaoperativo = :id_diaoperativo_fin LIMIT 1) AS ofinal';

        $stmtRango = $pdo->prepare($sqlRango);
        $stmtRango->bindValue(':id_unidad_ini', $idUnidad, PDO::PARAM_INT);
        $stmtRango->bindValue(':id_unidad_fin', $idUnidad, PDO::PARAM_INT);
        $stmtRango->bindValue(':id_diaoperativo_inicio', (string)$idDiaOperativoInicio);
        $stmtRango->bindValue(':id_diaoperativo_fin', (string)$idDiaOperativoFin);
        $stmtRango->execute();
        $rango = $stmtRango->fetch();

        $resultado = array(
            'orden_inicial' => ($rango && isset($rango['oinicial'])) ? (int)$rango['oinicial'] : 0,
            'orden_final' => ($rango && isset($rango['ofinal'])) ? (int)$rango['ofinal'] : 0,
        );

        $this->rangoOrdenOperativoCache[$cacheKey] = $resultado;

        return $resultado;
    }

    private function obtenerColumnasTabla(PDO $pdo, $tabla)
    {
        $tabla = strtolower((string)$tabla);
        $cacheKey = spl_object_hash($pdo) . '|' . $tabla;

        if (isset($this->tablaColumnasCache[$cacheKey])) {
            return $this->tablaColumnasCache[$cacheKey];
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM ' . $tabla);
        } catch (Exception $e) {
            $this->tablaColumnasCache[$cacheKey] = array();
            return array();
        }

        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
        $columnas = array();
        foreach ($rows as $row) {
            if (!isset($row['Field'])) {
                continue;
            }
            $columnas[] = strtolower((string)$row['Field']);
        }

        $this->tablaColumnasCache[$cacheKey] = $columnas;

        return $columnas;
    }

    private function resolverNombreColumna(array $columnasDisponibles, array $candidatas)
    {
        foreach ($candidatas as $candidata) {
            if (in_array($candidata, $columnasDisponibles, true)) {
                return $candidata;
            }
        }

        return '';
    }

    private function obtenerExpresionNombreUnidadSql()
    {
        return 'CASE '
            . 'WHEN nombre_unidad IS NULL '
            . 'OR TRIM(REPLACE(REPLACE(REPLACE(REPLACE(nombre_unidad, CHAR(160), \'\'), CHAR(9), \'\'), CHAR(10), \'\'), CHAR(13), \'\')) = \'\' '
            . 'THEN CONCAT(\'Unidad #\', id_unidad) '
            . 'ELSE nombre_unidad END';
    }
}