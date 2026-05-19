#!/usr/bin/php
<?php

chdir(__DIR__);

//IP AZURE https://localhost/online/
//MODIFICACION DE HOSTING DE PEDIDO EN LINEA..

require_once 'config/config_dmn.php'; //
require_once 'includes/dbcon.php'; //
require_once 'includes/comunes.php'; //
require_once 'includes/objComunes.php'; //
require_once 'includes/objEsquemacobro.php'; //
require_once 'includes/objInventario.php';
require_once 'includes/objCTD.php'; //
require_once 'includes/objUnidad.php'; //
require_once 'includes/objPrint.php'; //
require_once 'includes/print.php'; //
require_once 'config/db.php';

set_time_limit(0);
date_default_timezone_set('America/Mexico_City');
ini_set('display_errors', '0');

error_reporting(0);
$daemonLogPath = __DIR__ . '/errlog/BPdmnJusto_error.log';
ini_set('log_errors', '1');
ini_set('error_log', $daemonLogPath);

function bpLogError($message, $context = array()) {
    global $daemonLogPath;

    $entry = sprintf("[%s] %s", date('Y-m-d H:i:s'), $message);
    if (!empty($context)) {
        $entry .= ' | ' . json_encode($context);
    }

    @file_put_contents($daemonLogPath, $entry . PHP_EOL, FILE_APPEND);
}

function bpIsPearError($value) {
    if (!is_object($value)) {
        return false;
    }

    return is_a($value, 'PEAR_Error') || is_subclass_of($value, 'PEAR_Error');
}

set_exception_handler(function ($exception) {
    bpLogError('Excepcion no controlada', array(
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ));
});

set_error_handler(function ($severity, $message, $file, $line) {
    // Respect current error_reporting level in runtime.
    if (!(error_reporting() & $severity)) {
        return false;
    }

    // PHP 5.6 + PEAR DB legacy pattern: non-static methods called statically.
    if ((int) $severity === E_STRICT && strpos($message, 'should not be called statically') !== false) {
        return true;
    }

    bpLogError('Error PHP', array(
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ));

    return false;
});

register_shutdown_function(function () {
    $fatal = error_get_last();
    if (!empty($fatal) && in_array($fatal['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        bpLogError('Error fatal', $fatal);
    }
});

$lockFilePath = __DIR__ . '/BPdmnJusto.lock';
$lockHandle = fopen($lockFilePath, 'c');

if ($lockHandle === false) {
    printf("BPdmnJusto.php: no se pudo abrir lock %s a las %s\n", $lockFilePath, date("d/m/Y H:i:s"));
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    printf("BPdmnJusto.php: ya se encuentra en ejecucion a las %s\n", date("d/m/Y H:i:s"));
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, (string) getmypid());

register_shutdown_function(function () use (&$lockHandle) {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
});


function proccc($campo, $tabla, $valor) {
    global $dbVentamaxx;

    $sql = sprintf("SELECT * FROM %s WHERE %s_%s = '%s'", $tabla, $campo, $tabla, $valor);
    $stmt = $dbVentamaxx->prepare($sql);
    $stmt->execute();

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return $row['id_' . $tabla];
    } else {
        $sql = sprintf("INSERT INTO %s SET %s_%s = '%s'", $tabla, $campo, $tabla, $valor);
        $stmt = $dbVentamaxx->prepare($sql);
        $stmt->execute();

        $sql = sprintf("SELECT * FROM %s WHERE %s_%s = '%s'", $tabla, $campo, $tabla, $valor);
        $stmt = $dbVentamaxx->prepare($sql);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['id_' . $tabla];
    }
}



function esadc($in_idtipo, $in_idsubtipo) {
    $sql = sprintf("select esadicional_subtiporeceta from subtiporeceta where id_tiporeceta=%d and id_subtiporeceta=%d", $in_idtipo, $in_idsubtipo);
    $rs = &$GLOBALS['db']->query($sql);
    if (bpIsPearError($rs)) {
        bpLogError('Error BD en esadc', array('sql' => $sql, 'error' => $rs->getMessage()));
        return (false);
    }

    if ($row = &$rs->fetchRow(DB_FETCHMODE_ASSOC)) {
        return ($row['esadicional_subtiporeceta']);
    }

    return (false);
}

function leebase($in_orden, $in_producto, $in_receta) {
    $sql = sprintf("select id_componente from componente as c where id_orden=%d and id_producto=%d and id_receta=%d", $in_orden, $in_producto, $in_receta);
    $rs = &$GLOBALS['db']->query($sql);
    if (bpIsPearError($rs)) {
        bpLogError('Error BD en leebase', array('sql' => $sql, 'error' => $rs->getMessage()));
        return (false);
    }

    if ($row = &$rs->fetchRow(DB_FETCHMODE_ASSOC)) {
        return ($row['id_componente']);
    }

    return (false);
}
function destinoctd($in_idcte, $in_iddom, $in_idtel) {
    $sql = sprintf("insert into destino set id_cliente=%d, id_domicilio=%d, id_telefono=%d", $in_idcte, $in_iddom, $in_idtel);

    $rs = &$GLOBALS['db']->query($sql);

    if (bpIsPearError($rs)) {
        bpLogError('Error BD en destinoctd', array('sql' => $sql, 'error' => $rs->getMessage()));
        return (false);
    }

    return (true);
}

function getDataUBE() {
    $sql = sprintf("select * from unidad");

    $rs = &$GLOBALS['db'] -> query($sql);

    if (bpIsPearError($rs)) {
        bpLogError('Error BD en getDataUBE', array('sql' => $sql, 'error' => $rs->getMessage()));
        return (false);
    }

    if ($row = &$rs->fetchRow(DB_FETCHMODE_ASSOC)) {
        return ($row);
    }

    bpLogError('No se encontraron datos en unidad para getDataUBE');
    return (false);
}

function ordenJusto() {
    set_time_limit(0);
    date_default_timezone_set('America/Mexico_City');
    ini_set('display_errors', '0');
    error_reporting(0);
    $curl = curl_init();
    header("Content-type: text/html; charset=utf8");

    $servername = "localhost";
    $username = "uservmx";
    $password = "";
    $dbname = "ventamaxx";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn -> connect_error) {
        bpLogError('Error de conexion mysqli en ordenJusto', array('error' => $conn->connect_error));
        return json_encode(array('orden' => array()));
    }

    $sqltienda = "SELECT * FROM config where id_config =917";
    $result = $conn -> query($sqltienda);
    $row = mysqli_fetch_assoc($result);

    if ($result -> num_rows > 0) {
        $idstore = $row['param0_config'];
    }

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api2.getjusto.com/api/v1/pendingOrders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 40,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_POSTFIELDS => '{
            "storesIds": ["' . $idstore . '"]
        }',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer WXgyPWKyuC2ZgfZAjPDcr2ZDJoojXx3vk7oeGdjLXJnYw7WEXAzvETkJmKw59joa',
            'Content-Type: application/json',
        ),
    ));
    $response = curl_exec($curl);

    if ($response === false) {
        bpLogError('Error CURL en ordenJusto', array('error' => curl_error($curl)));
    }

    $pedidos = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        bpLogError('Error parseando JSON de Justo', array(
            'json_error' => json_last_error_msg(),
            'response' => substr((string) $response, 0, 1000),
        ));
    }

    if (!empty($pedidos)) {
        foreach ($pedidos['data'] as $key => $value) {
            unset($orden);
            /** DATOS DE ORDEN */
            $orden['id_orden'] = $value['fullCode'];
            $orden['total'] = $value['totalPrice']-$value['websiteCoinsDiscount'];
            $orden['impuesto'] = 0;
            $orden['benepuntos'] = $value['totalDiscount'];

            /** TIPO DE ORDEN */
            if ($value['channel'] == 'web-delivery') {
                if ($value['paymentType'] == "mercadoPagoCardMX" || $value['paymentType'] == 'kushkiMX') {
                    $orden['tipo_orden'] = 18;
                } else {
                    $orden['tipo_orden'] = 10;
                }
            } else if ($value['channel'] == 'web-go') {
                if ($value['paymentType'] == "mercadoPagoCardMX" || $value['paymentType'] == 'kushkiMX') {
                    $orden['tipo_orden'] = 18;
                } else {
                    $orden['tipo_orden'] = 11;
                }
            } else if ($value['channel'] == 'app-delivery') {
                if ($value['paymentType'] == "mercadoPagoCardMX" || $value['paymentType'] == 'kushkiMX') {
                    $orden['tipo_orden'] = 17;
                } else {
                    $orden['tipo_orden'] = 13;
                }
            } else if ($value['channel'] == 'app-go') {
                if ($value['paymentType'] == "mercadoPagoCardMX" || $value['paymentType'] == 'kushkiMX') {
                    $orden['tipo_orden'] = 17;
                } else {
                    $orden['tipo_orden'] = 14;
                }
            }
            /** TIPO DE PAGO */
            if ($value['cashAmount'] == 0) {
                $orden['pago'] = $value['totalPrice'];
            } else {
                $orden['pago'] = $value['cashAmount'];
            }

            if ($value['paymentType'] == "inStore") {

                $orden['tipo_pago'] = "EFECTIVO EN TIENDA";
            } else if ($value['paymentType'] == "cash") {

                $orden['tipo_pago'] = "EFECTIVO";
            } else {
                $orden['tipo_pago'] = $value['paymentType'];
            }

            $orden['comentario'] = $value['channel'];

            /** DATOS DE CLIENTE */
            $nombre_cliente = explode(" ", $value['buyerName']);
            $orden['cliente']['nombre_cliente'] = $nombre_cliente[0];
            $orden['cliente']['apellido_cliente'] = $nombre_cliente[1];
            $orden['cliente']['email'] = $value['email'];
            $orden['cliente']['id_telefono'] = substr($value['phone'], 3, 13);
            $orden['cliente']['id_tipotelefono'] = substr($value['phone'], 3, 13);
            $orden['cliente']['nombre_calle'] = $value['address']['address'];

            if ($value['deliveryType'] == 'delivery') {
                $orden['cliente']['nombre_calle'] = $value['address']['address'];
            }
            if ($value['address']['addressSecondary'] || $value['address']['addressLine2']) {
                $orden['cliente']['referencia_domicilio'] = $value['address']['addressLine2'] . " ," . $value['address']['addressSecondary'];
            }
            $idproductos = 1;
            //print_r($value['items']);
            foreach ($value['items'] as $idproducto => $productos) {
                $tipo_producto = explode("-", $productos['product']['externalId']);

                if ($tipo_producto[0] == 'PIZZA') {

                    unset($producto);

                    // DESCRIPCION DEL PRODUCTO PIZZA
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 1;
                    $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                    $producto['id_tamano'] = $tipo_producto[1];
                    $producto['descripcion_producto'] = $tipo_producto[2] . "*";

                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = $productos['productPrice'] * $productos['amount'];

                    //ARMADO DEL COMPONENETE
                    foreach ($productos['modifiers'] as $idcomponente => $componentes) {


                        if ($componentes['externalId'] == 'TIPO-PAN') { // TIPO DE PAN

                            if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 28 && $componentes['options'][0]['externalId'] != 923) {

                                // BASE DE PIZZA
                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => "SAPREP",
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => "SAPREP",
                                );

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $precio_pizza += $componentes['options'][0]['price']* $productos['amount'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            } else {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $precio_pizza += $componentes['options'][0]['price']* $productos['amount'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SAPREP, " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'BORDE-PIZZA') {

                            if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 16) {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $precio_pizza += $componentes['options'][0]['price']* $productos['amount'];
                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            } else if ($componentes['options'][0]['externalId'] == '-16') {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => 16,
                                    'cantidad_receta' => -1,
                                    'descripcion_receta' => "AJONJOLI",
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => 16,
                                    'cantidad_receta' => -1,
                                    'descripcion_receta' => "AJONJOLI",
                                );

                                $precio_pizza += $componentes['options'][0]['price']* $productos['amount'];
                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SIN AJONJOLI, " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'EXTRA-QUESO') {

                            if ($componentes['options'][0]['externalId'] > 0) {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $precio_pizza += $componentes['options'][0]['price']* $productos['amount'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'SERVICIOS') {
                        } else if ($componentes['externalId'] == 'LEFT') { //PORCION IZQUIERDA
                            unset($componente_izquierdo);

                            $componente_izquierdo['id_porcion'] = 2;
                            $descripcion_izquierda = ", 2/2 ";
                            foreach ($componentes['countByExternalId'] as $id_receta_izquierda => $cantidad_receta_izquierda) {

                                $sql = "SELECT nombre_receta FROM receta where id_receta =" . $id_receta_izquierda;
                                $result = $conn->query($sql);
                                $row = mysqli_fetch_assoc($result);
                                if ($result->num_rows > 0) {
                                    $componente_izquierdo['recetas'][] = array(
                                        'id_receta' => $id_receta_izquierda,
                                        'cantidad_receta' => $cantidad_receta_izquierda,
                                        'descripcion_receta' => $row['nombre_receta'],
                                    );

                                    if ($cantidad_receta_izquierda > 1) {

                                        $descripcion_izquierda .= " , " . $cantidad_receta_izquierda . "x " . $row['nombre_receta'];
                                    } else {

                                        $descripcion_izquierda .= " ," . $row['nombre_receta'];
                                    }
                                }
                            }

                            if ($tipo_producto[3] == '53') {

                                $componente_izquierdo['recetas'][] = array(
                                    'id_receta' => 53,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $tipo_producto[4],
                                );

                                $componente_izquierdo['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => 'SAPREP',
                                );

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);

                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . ", CRUJI,* " . $auxiliar_desc[1];
                            }

                            $producto['descripcion_producto'] .= $descripcion_izquierda;
                            $producto['porcion'][] = $componente_izquierdo;
                        } else if ($componentes['externalId'] == 'RIGHT') { //PORCION DERECHA

                            unset($componente_derecho);

                            $componente_derecho['id_porcion'] = 1;
                            $descripcion_derecha = " 1/2 ";

                            foreach ($componentes['countByExternalId'] as $id_receta_derecha => $cantidad_receta_derecha) {

                                $sql = "SELECT nombre_receta FROM receta where id_receta =" . $id_receta_derecha;
                                $result = $conn->query($sql);
                                $row = mysqli_fetch_assoc($result);
                                if ($result->num_rows > 0) {
                                    $componente_derecho['recetas'][] = array(
                                        'id_receta' => $id_receta_derecha,
                                        'cantidad_receta' => $cantidad_receta_derecha,
                                        'descripcion_receta' => $row['nombre_receta'],
                                    );

                                    if ($cantidad_receta_derecha > 1) {

                                        $descripcion_derecha .= " , " . $cantidad_receta_derecha . "x " . $row['nombre_receta'];
                                    } else {

                                        $descripcion_derecha .= " ,  " . $row['nombre_receta'];
                                    }
                                }
                            }


                            if ($tipo_producto[3] == '53') {

                                $componente_derecho['recetas'][] = array(
                                    'id_receta' => 53,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $tipo_producto[4],
                                );

                                $componente_derecho['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => 'SAPREP',
                                );
                            }

                            $producto['descripcion_producto'] .= $adicionales . " " . $descripcion_derecha;
                            $producto['porcion'][] = $componente_derecho;
                        }


                        $producto['precio_producto'] = $precio_pizza;
                        $producto['preciodm_producto'] = $precio_pizza;

                        $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                        $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                        $producto['impporc_producto'] = 16;

                    }
                    $orden['productos'][] = $producto;
                } else if ($tipo_producto[0] == 'CALZONE') {

                    unset($producto);

                    // DESCRIPCION DEL PRODUCTO PIZZA
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 1;
                    $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                    $producto['id_tamano'] = $tipo_producto[1];
                    $producto['descripcion_producto'] = $tipo_producto[2] . "*";

                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = $productos['productPrice'] * $productos['amount'];

                    //ARMADO DEL COMPONENETE
                    foreach ($productos['modifiers'] as $idcomponente => $componentes) {


                        if ($componentes['externalId'] == 'FULL') { //PORCION DERECHA

                            unset($componente_full);

                            $componente_full['id_porcion'] = 0;
                            $componente_full['recetas'][] = array(
                                'id_receta' => 28,
                                'cantidad_receta' => 1,
                                'descripcion_receta' => 'SAPREP',
                            );
                            $descripcion_full = " ";

                            foreach ($componentes['countByExternalId'] as $id_receta_derecha => $cantidad_receta_full) {

                                $sql = "SELECT nombre_receta FROM receta where id_receta != 28 and  id_receta =" . $id_receta_derecha;
                                $result = $conn->query($sql);
                                $row = mysqli_fetch_assoc($result);
                                if ($result->num_rows > 0) {
                                    $componente_full['recetas'][] = array(
                                        'id_receta' => $id_receta_derecha,
                                        'cantidad_receta' => $cantidad_receta_full,
                                        'descripcion_receta' => $row['nombre_receta'],
                                    );

                                    if ($cantidad_receta_full > 1) {

                                        $descripcion_full .= " , " . $cantidad_receta_full . "x " . $row['nombre_receta'];
                                    } else {

                                        $descripcion_full .= " ,  " . $row['nombre_receta'];
                                    }
                                }
                            }


                            $producto['descripcion_producto'] .= $adicionales . " " . $descripcion_full;
                            $producto['porcion'][] = $componente_full;
                        }


                        $producto['precio_producto'] = $precio_pizza;
                        $producto['preciodm_producto'] = $precio_pizza;

                        $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                        $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                        $producto['impporc_producto'] = 16;

                    }
                    $orden['productos'][] = $producto;
                } else if($tipo_producto[0] == 'SALSA'){
                            unset($producto);
                            // DESCRIPCION DEL PRODUCTO PIZZA
                            $clave_entrada = explode('+', $productos['modifiers'][6]['externalId']);
                            $producto['id_producto'] = $idproductos++;
                            $producto['cantidad_producto'] = $productos['amount'];
                            $producto['id_esquemacobro'] = 917;
                            $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';

                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                                if ($componentes['externalId'] == 'SALSA') {

                                    foreach ($componentes['countByExternalId'] as $id_contenido => $contenido) {

                                        $modificadores_entrada = explode("+", $id_contenido);

                                        $sql = "SELECT descripcion_tamanno FROM tamanno where id_tamanno =" . $modificadores_entrada[0];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['id_tamano'] = $modificadores_entrada[0] . " ";
                                            $producto['descripcion_producto'] .= $row['descripcion_tamanno'];
                                            $producto['sabor'] = $modificadores_entrada[3];
                                            $precio_entrada = $modificadores_entrada[2] * $productos['amount'];
                                        }

                                        $sql = "SELECT nombre_receta FROM receta where id_receta =" . $modificadores_entrada[1];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['porcion'][0]['recetas'][] = array(
                                                'id_receta' => $modificadores_entrada[1],
                                                'cantidad_receta' => 1,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );
                                        }
                                    }
                                }
                            }

                            //PRECIO
                            $precio_pizza = $productos['unitPrice'] * $productos['amount'];
                            $producto['precio_producto'] = $precio_pizza;
                            $producto['preciodm_producto'] = $precio_pizza;

                             $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                            $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                            $orden['impuesto'] +=  $impuestopizza;
                            $producto['impporc_producto'] = 16;

                            $orden['productos'][] = $producto;

                        }else if ($tipo_producto[0] == 'BAGUETTE') {

                    unset($producto);
                    unset($componentes);

                    // DESCRIPCION DEL PRODUCTO PIZZA
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 1;
                    $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                    $producto['descripcion_producto'] = "Baguette " . $productos['product']['name'] . " ";

                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = $productos['unitPrice'] * $productos['amount'];

                    //ARMADO DEL COMPONENETE
                    foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                        if ($componentes['externalId'] == 'PAN-BAGUETTE') { // TIPO DE PAN

                            $producto['id_tamano'] =  $componentes['options'][0]['externalId'];
                            $producto['descripcion_producto'] .= $componentes['options'][0]['name'] . " ";

                            $producto['porcion'][0]['recetas'][] = array(
                                'id_receta' => 392,
                                'cantidad_receta' => 1,
                                'descripcion_receta' => "Base Baguette",
                            );
                            $producto['porcion'][0]['recetas'][] = array(
                                'id_receta' => $tipo_producto[1],
                                'cantidad_receta' => 1,
                                'descripcion_receta' => $productos['product']['name'],
                            );
                        }

                        $producto['precio_producto'] = $precio_pizza;
                        $producto['preciodm_producto'] = $precio_pizza;

                        $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                        $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                        $orden['impuesto'] += $impuestopizza;
                        $producto['impporc_producto'] = 16;
                    }

                    $orden['productos'][] = $producto;
                } else if ($tipo_producto[0] == 'ENTRADA') {

                    unset($producto);

                    // DESCRIPCION DEL PRODUCTO PIZZA
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 1;
                    $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                    $producto['descripcion_producto'] = $productos['product']['name'] . " ";

                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = $productos['unitPrice'] * $productos['amount'];

                    //ARMADO DEL COMPONENETE
                    foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                        if ($componentes['externalId'] == 'GRAMAJE') { // TIPO DE PAN
                            unset($producto);
                            // DESCRIPCION DEL PRODUCTO PIZZA
                            $clave_entrada = explode('+', $productos['modifiers'][6]['externalId']);
                            $producto['id_producto'] = $idproductos++;
                            $producto['cantidad_producto'] = $productos['amount'];
                            $producto['id_esquemacobro'] = 917;
                            $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';

                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                                if ($componentes['externalId'] == 'GRAMAJE') {

                                    foreach ($componentes['countByExternalId'] as $id_contenido => $contenido) {

                                        $modificadores_entrada = explode("+", $id_contenido);

                                        $sql = "SELECT descripcion_tamanno FROM tamanno where id_tamanno =" . $modificadores_entrada[0];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['id_tamano'] = $modificadores_entrada[0] . " ";
                                            $producto['descripcion_producto'] .= $row['descripcion_tamanno'];
                                            $precio_entrada = $modificadores_entrada[2] * $productos['amount'];
                                        }

                                        $sql = "SELECT nombre_receta FROM receta where id_receta =" . $modificadores_entrada[1];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['porcion'][0]['recetas'][] = array(
                                                'id_receta' => $modificadores_entrada[1],
                                                'cantidad_receta' => 1,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );
                                        }
                                    }
                                    if (sizeOf($modificadores_entrada) > 2) {

                                        $producto['porcion'][0]['recetas'][] = array(
                                            'id_receta' => 390,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => 'ENTRADAS',
                                        );
                                    }
                                }
                            }
                            if ($modificadores_entrada > 0) {
                                $producto['id_tamano'] = $modificadores_entrada[0];
                            } else {
                                $producto['id_tamano'] =  $componentes['options'][0]['externalId'];
                            }
                        } else if ($componentes['externalId'] == 'SABOR') {

                            if ($componentes['options'][0]['externalId'] > 0) {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => 390,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => 'ENTRADAS',
                                );

                                $producto['descripcion_producto'] .= $componentes['options'][0]['name'];
                            }
                        }

                        $producto['precio_producto'] = $precio_pizza;
                        $producto['preciodm_producto'] = $precio_pizza;

                        $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                        $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                        $orden['impuesto'] += $impuestopizza;
                        $producto['impporc_producto'] = 16;
                    }

                    $orden['productos'][] = $producto;
                } else if ($tipo_producto[0] == 'POSTRE') {

                    unset($producto);
                    unset($componentes);

                    // DESCRIPCION DEL PRODUCTO PIZZA
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 1;
                    $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                    $producto['descripcion_producto'] = "Baguette " . $productos['product']['name'] . " ";

                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = $productos['unitPrice'] * $productos['amount'];

                    //ARMADO DEL COMPONENETE
                    $producto['id_tamano'] =  $tipo_producto[3];
                    $producto['descripcion_producto'] .= $componentes['options'][0]['name'] . " ";

                    $producto['porcion'][0]['recetas'][] = array(
                        'id_receta' => 391,
                        'cantidad_receta' => 1,
                        'descripcion_receta' => "POSTRES",
                    );
                    $producto['porcion'][0]['recetas'][] = array(
                        'id_receta' => $tipo_producto[1],
                        'cantidad_receta' => 1,
                        'descripcion_receta' => $productos['product']['name'],
                    );

                    $producto['precio_producto'] = $precio_pizza;
                    $producto['preciodm_producto'] = $precio_pizza;

                    $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                    $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                    $orden['impuesto'] +=  $impuestopizza;
                    $producto['impporc_producto'] = 16;

                    $orden['productos'][] = $producto;
                } else if ($tipo_producto[0] == 'REFRESCO') {
                    unset($producto);

                    // DESCRIPCION DEL PRODUCTO PIZZA
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 1;
                    $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                    $producto['descripcion_producto'] = $productos['product']['name'] . " ";

                    $producto['id_tamano'] = $tipo_producto[1];
                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = $productos['unitPrice'] * $productos['amount'];


                    if ($tipo_producto[1] == '11') {

                        $producto['porcion'][0]['recetas'][] = array(
                            'id_receta' => 334,
                            'cantidad_receta' => 1,
                            'descripcion_receta' => 'E-PURA',
                        );

                        $producto['porcion'][0]['recetas'][] = array(
                            'id_receta' => 389,
                            'cantidad_receta' => 1,
                            'descripcion_receta' => 'BEBIDAS',
                        );

                        $producto['descripcion_producto'] .= $componentes['options'][0]['name'];

                        $producto['precio_producto'] = $precio_pizza;
                        $producto['preciodm_producto'] = $precio_pizza;

                        $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                        $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                        $orden['impuesto'] += $impuestopizza;
                        $producto['impporc_producto'] = 16;
                    } else if ($tipo_producto[1] == '230') {

                        $producto['id_producto'] = $idproductos++;
                        $producto['cantidad_producto'] = $productos['amount'];
                        $producto['id_esquemacobro'] = 1;
                        $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                        $producto['descripcion_producto'] = $productos['product']['name'] . " ";
                        //ARMADO DEL COMPONENETE
                        foreach ($productos['modifiers'] as $idcomponente => $componentes) {
                            if ($componentes['externalId'] == 'REFRESCO') {

                                if ($componentes['options'][0]['externalId'] > 0) {

                                    $producto['porcion'][0]['recetas'][] = array(
                                        'id_receta' => $componentes['options'][0]['externalId'],
                                        'cantidad_receta' => 1,
                                        'descripcion_receta' => $componentes['options'][0]['name'],
                                    );

                                    $producto['porcion'][0]['recetas'][] = array(
                                        'id_receta' => 389,
                                        'cantidad_receta' => 1,
                                        'descripcion_receta' => 'BEBIDAS',
                                    );

                                    $producto['descripcion_producto'] .= $componentes['options'][0]['name'];
                                }
                            }

                            $producto['precio_producto'] = $precio_pizza;
                            $producto['preciodm_producto'] = $precio_pizza;
                            $producto['id_tamano'] = $tipo_producto[1];

                            $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                            $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                            $orden['impuesto'] += $impuestopizza;
                            $producto['impporc_producto'] = 16;
                        }
                    } else if ($tipo_producto[1] == '9') {
                        //ARMADO DEL COMPONENETE
                        foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                            if ($componentes['externalId'] == 'REFRESCO') {

                                if ($componentes['options'][0]['externalId'] > 0) {

                                    $producto['porcion'][0]['recetas'][] = array(
                                        'id_receta' => $componentes['options'][0]['externalId'],
                                        'cantidad_receta' => 1,
                                        'descripcion_receta' => $componentes['options'][0]['name'],
                                    );

                                    $producto['porcion'][0]['recetas'][] = array(
                                        'id_receta' => 389,
                                        'cantidad_receta' => 1,
                                        'descripcion_receta' => 'BEBIDAS',
                                    );

                                    $producto['descripcion_producto'] .= $componentes['options'][0]['name'];
                                }
                            }

                            $producto['precio_producto'] = $precio_pizza;
                            $producto['preciodm_producto'] = $precio_pizza;
                            $producto['id_tamano'] = $tipo_producto[1];

                            $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                            $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                            $orden['impuesto'] += $impuestopizza;
                            $producto['impporc_producto'] = 16;
                        }
                    }
                    $orden['productos'][] = $producto;
                } else if ($tipo_producto[0] == 'COMBO') {
                    $contenido_combo = explode("+", $tipo_producto[1]);


                    foreach ($contenido_combo as $contenido) {
                        if ($contenido == 'PIZZA') {

                            unset($producto);


                            $clave_pizza = explode(".", $contenido_combo[1]);

                            $producto['id_producto'] = $idproductos++;
                            $producto['cantidad_producto'] = $productos['amount'];
                            $producto['id_esquemacobro'] = 917;
                            $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';
                            $producto['id_tamano'] = $clave_pizza[0];
                            $producto['descripcion_producto'] = $clave_pizza[1] . "*";

                            //CALCULO DE PRECIO E IMPUESTO
                            $precio_pizza = $clave_pizza[2] * $productos['amount'];

                            //ARMADO DEL COMPONENETE
                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                                echo "aaaaaaaaaaaaaaaa";
                                var_dump($componentes);
                                echo "aaaaaaaaaaaaaaaa";

                                if ($componentes['externalId'] == 'TIPO-PAN') { // TIPO DE PAN

                                    if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 28) {

                                        // BASE DE PIZZA

                                        $producto['porcion'][0]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );
                                        $producto['porcion'][1]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );
                                        $precio_pizza += $componentes['options'][0]['price'];

                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                                    } else if ($componentes['options'][0]['externalId'] < 0 && $componentes['options'][0]['externalId'] != 28) {

                                        $producto['porcion'][0]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );
                                        $producto['porcion'][1]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );
                                        $precio_pizza += $componentes['options'][0]['price'];

                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SAPREP, " . $auxiliar_desc[1];
                                    }
                                } else if ($componentes['externalId'] == 'BORDE-PIZZA') {

                                    if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 16) {

                                        $producto['porcion'][0]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );
                                        $producto['porcion'][1]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );

                                        $precio_pizza += $componentes['options'][0]['price'];
                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                                    } else if ($componentes['options'][0]['externalId'] == '-16') {

                                        $producto['porcion'][0]['recetas'][] = array(
                                            'id_receta' => 16,
                                            'cantidad_receta' => -1,
                                            'descripcion_receta' => "AJONJOLI",
                                        );
                                        $producto['porcion'][1]['recetas'][] = array(
                                            'id_receta' => 16,
                                            'cantidad_receta' => -1,
                                            'descripcion_receta' => "AJONJOLI",
                                        );

                                        $precio_pizza += $componentes['options'][0]['price'];
                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SIN AJONJOLI, " . $auxiliar_desc[1];
                                    }
                                } else if ($componentes['externalId'] == 'EXTRA-QUESO') {

                                    if ($componentes['options'][0]['externalId'] > 0) {

                                        $producto['porcion'][0]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );

                                        $producto['porcion'][1]['recetas'][] = array(
                                            'id_receta' => $componentes['options'][0]['externalId'],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );

                                        $precio_pizza += $componentes['options'][0]['price'];

                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                                    }
                                } else if ($componentes['externalId'] == 'LEFT') { //PORCION IZQUIERDA

                                    unset($componente_izquierdo);
                                    $componente_izquierdo['id_porcion'] = 2;
                                    $componente_izquierdo['recetas'][] = array(
                                        'id_receta' => 28,
                                        'cantidad_receta' => 1,
                                        'descripcion_receta' => 'SAPREP',
                                    );
                                    $descripcion_izquierda = ", 2/2 ";
                                    foreach ($componentes['countByExternalId'] as $id_receta_izquierda => $cantidad_receta_izquierda) {

                                        $sql = "SELECT nombre_receta FROM receta where id_receta != 28 and id_receta =" . $id_receta_izquierda;
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $componente_izquierdo['recetas'][] = array(
                                                'id_receta' => $id_receta_izquierda,
                                                'cantidad_receta' => $cantidad_receta_izquierda,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );

                                            if ($cantidad_receta_izquierda > 1) {

                                                $descripcion_izquierda .= " , " . $cantidad_receta_izquierda . "x " . $row['nombre_receta'];
                                            } else {

                                                $descripcion_izquierda .= " ," . $row['nombre_receta'];
                                            }
                                        }
                                    }

                                    if ($clave_pizza[3] == '53') {

                                        $componente_izquierdo['recetas'][] = array(
                                            'id_receta' => 53,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );

                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);

                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . ", CRUJI,* " . $auxiliar_desc[1];
                                    } else if ($clave_pizza[3] == '441') {

                                        $componente_izquierdo['recetas'][] = array(
                                            'id_receta' => 441,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );

                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);

                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . ", CORAZON,* " . $auxiliar_desc[1];
                                    } else  if ($clave_pizza[3] == '823') {

                                        $componente_izquierdo['recetas'][] = array(
                                            'id_receta' => 823,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );

                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);

                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . ", BALON,* " . $auxiliar_desc[1];
                                    } else  if ($clave_pizza[3] == '900') {

                                        $componente_izquierdo['recetas'][] = array(
                                            'id_receta' => 900,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );

                                        $auxiliar_desc = explode("*", $producto['descripcion_producto']);

                                        $producto['descripcion_producto'] = " ";
                                        $producto['descripcion_producto'] .= $auxiliar_desc[0] . ", Calaverita,* " . $auxiliar_desc[1];
                                    }

                                    $producto['descripcion_producto'] .= $descripcion_izquierda;
                                    $producto['porcion'][] = $componente_izquierdo;
                                } else if ($componentes['externalId'] == 'RIGHT') { //PORCION DERECHA

                                    unset($componente_derecho);

                                    $componente_derecho['id_porcion'] = 1;
                                    $componente_derecho['recetas'][] = array(
                                        'id_receta' => 28,
                                        'cantidad_receta' => 1,
                                        'descripcion_receta' => 'SAPREP',
                                    );
                                    $descripcion_derecha = " 1/2 ";

                                    foreach ($componentes['countByExternalId'] as $id_receta_derecha => $cantidad_receta_derecha) {

                                        $sql = "SELECT nombre_receta FROM receta where id_receta != 28 and  id_receta =" . $id_receta_derecha;
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $componente_derecho['recetas'][] = array(
                                                'id_receta' => $id_receta_derecha,
                                                'cantidad_receta' => $cantidad_receta_derecha,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );

                                            if ($cantidad_receta_derecha > 1) {

                                                $descripcion_derecha .= " , " . $cantidad_receta_derecha . "x " . $row['nombre_receta'];
                                            } else {

                                                $descripcion_derecha .= " ,  " . $row['nombre_receta'];
                                            }
                                        }
                                    }


                                    if ($clave_pizza[3] == '53') {

                                        $componente_derecho['recetas'][] = array(
                                            'id_receta' => 53,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );
                                    } else if ($clave_pizza[3] == '441') {

                                        $componente_derecho['recetas'][] = array(
                                            'id_receta' => 441,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );
                                    } else if ($clave_pizza[3] == '823') {

                                        $componente_derecho['recetas'][] = array(
                                            'id_receta' => 823,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );
                                    }  else  if ($clave_pizza[3] == '900') {

                                        $componente_derecho['recetas'][] = array(
                                            'id_receta' => 900,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $clave_pizza[4],
                                        );
                                    }


                                    $producto['descripcion_producto'] .= $adicionales . " " . $descripcion_derecha;
                                    $producto['porcion'][] = $componente_derecho;
                                }

                                $producto['precio_producto'] = $precio_pizza;
                                $producto['preciodm_producto'] = $precio_pizza;

                                $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                                $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                                $producto['impporc_producto'] = 16;

                            }
                            $orden['impuesto'] += $impuestopizza;
                            $orden['productos'][] = $producto;
                        } else if ($contenido == 'REFRESCO') {

                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                                if ($componentes['externalId'] == 'REFRESCO') {

                                    foreach ($componentes['countByExternalId'] as $idcontenido => $contenido) {

                                        unset($producto);
                                        $producto['id_producto'] = $idproductos++;
                                        $producto['cantidad_producto'] = ($contenido * $productos['amount']);
                                        $producto['id_esquemacobro'] = 917;
                                        $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';
                                        $producto['descripcion_producto'] = $componentes['name'] . " ";
                                        $modificadores_refresco = explode("+", $idcontenido);

                                        $producto['id_tamano'] = $modificadores_refresco[1] . " ";
                                        $precio_refresco = ($modificadores_refresco[2] * $contenido) * $productos['amount'];
                                        $sql = "SELECT nombre_receta FROM receta where id_receta =" . $modificadores_refresco[0];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {

                                            $producto['porcion'][0]['recetas'][] = array(
                                                'id_receta' => 389,
                                                'cantidad_receta' => 1,
                                                'descripcion_receta' => 'BASE BEBIDAS',
                                            );

                                            $producto['porcion'][0]['recetas'][] = array(
                                                'id_receta' => $modificadores_refresco[0],
                                                'cantidad_receta' => $contenido,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );

                                            $producto['descripcion_producto'] .= $row['nombre_receta'];
                                        }

                                        //calculo de precio
                                        $producto['precio_producto'] = $precio_refresco;
                                        $producto['preciodm_producto'] = $precio_refresco;

                                        $impuestorefresco = $precio_refresco - ($precio_refresco / 1.16);
                                        $producto['impuesto_producto'] = number_format($impuestorefresco, 2);
                                        $orden['impuesto'] += $impuestorefresco;
                                        $producto['impporc_producto'] = 16;

                                        $orden['productos'][] = $producto;
                                    }
                                }
                            }
                        } else if ($contenido == 'ENTRADA') {

                            unset($producto);
                            // DESCRIPCION DEL PRODUCTO PIZZA
                            $clave_entrada = explode('+', $productos['modifiers'][6]['externalId']);
                            $producto['id_producto'] = $idproductos++;
                            $producto['cantidad_producto'] = $productos['amount'];
                            $producto['id_esquemacobro'] = 917;
                            $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';

                            $producto['porcion'][0]['recetas'][] = array(
                                'id_receta' => 390,
                                'cantidad_receta' => 1,
                                'descripcion_receta' => 'BASE ENTRADAS',
                            );

                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                                if ($componentes['externalId'] == 'GRAMAJE') {

                                    foreach ($componentes['countByExternalId'] as $id_contenido => $contenido) {

                                        $modificadores_entrada = explode("+", $id_contenido);

                                        $sql = "SELECT descripcion_tamanno FROM tamanno where id_tamanno =" . $modificadores_entrada[0];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['id_tamano'] = $modificadores_entrada[0] . " ";
                                            $producto['descripcion_producto'] .= $row['descripcion_tamanno'];
                                            $precio_entrada = $modificadores_entrada[2] * $productos['amount'];
                                        }

                                        $sql = "SELECT nombre_receta FROM receta where id_receta =" . $modificadores_entrada[1];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['porcion'][0]['recetas'][] = array(
                                                'id_receta' => $modificadores_entrada[1],
                                                'cantidad_receta' => 1,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );
                                        }
                                    }
                                }
                            }

                            //PRECIO
                            $producto['precio_producto'] = $precio_entrada;
                            $producto['preciodm_producto'] = $precio_entrada;

                            $impuestoentrada = $precio_entrada - ($precio_entrada / 1.16);
                            $producto['impuesto_producto'] = number_format($impuestoentrada, 2);
                            $orden['impuesto'] += $impuestoentrada;
                            $producto['impporc_producto'] = 16;

                            $orden['productos'][] = $producto;
                        } else if($contenido == 'SALSA'){
                            unset($producto);
                            // DESCRIPCION DEL PRODUCTO PIZZA
                            $clave_entrada = explode('+', $productos['modifiers'][6]['externalId']);
                            $producto['id_producto'] = $idproductos++;
                            $producto['cantidad_producto'] = $productos['amount'];
                            $producto['id_esquemacobro'] = 917;
                            $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';

                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {

                                if ($componentes['externalId'] == 'SALSA') {

                                    foreach ($componentes['countByExternalId'] as $id_contenido => $contenido) {

                                        $modificadores_entrada = explode("+", $id_contenido);

                                        $sql = "SELECT descripcion_tamanno FROM tamanno where id_tamanno =" . $modificadores_entrada[0];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['id_tamano'] = $modificadores_entrada[0] . " ";
                                            $producto['descripcion_producto'] .= $row['descripcion_tamanno'];
                                            $producto['sabor'] = $modificadores_entrada[3];
                                            $precio_entrada = $modificadores_entrada[2] * $productos['amount'];
                                        }

                                        $sql = "SELECT nombre_receta FROM receta where id_receta =" . $modificadores_entrada[1];
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $producto['porcion'][0]['recetas'][] = array(
                                                'id_receta' => $modificadores_entrada[1],
                                                'cantidad_receta' => 1,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );
                                        }
                                    }
                                }
                            }

                            //PRECIO
                            $producto['precio_producto'] = $precio_entrada;
                            $producto['preciodm_producto'] = $precio_entrada;

                            $impuestoentrada = $precio_entrada - ($precio_entrada / 1.16);
                            $producto['impuesto_producto'] = number_format($impuestoentrada, 2);
                            $orden['impuesto'] += $impuestoentrada;
                            $producto['impporc_producto'] = 16;

                            $orden['productos'][] = $producto;
                        }else if ($contenido == 'POSTRE') {
                            unset($producto);
                            $contador_benetarta = 0;

                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {
                                if ($componentes['externalId'] == 'POSTRE') {
                                    if ($componentes['options'][0]['externalId'] > 1) {

                                        // Incrementa el contador si el nombre del componente es 'BENETARTA 1' o 'BENETARTA 2'
                                        if ( $componentes['name'] == 'BENETARTA 1' ) {
                                            $contador_benetarta++;

                                            if ($contador_benetarta == 1) {
                                                //break;
                                            }
                                        }

                                        $clave_postre = explode("+", $componentes['options'][0]['externalId']);

                                        // DESCRIPCION DEL PRODUCTO PIZZA
                                        $clave_entrada = explode('+', $componentes['externalId']);
                                        $producto['id_producto'] = $idproductos++;
                                        $producto['cantidad_producto'] = $productos['amount'];
                                        $producto['id_esquemacobro'] = 917;
                                        $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';
                                        $producto['id_tamano'] = $clave_postre[0];

                                        $producto['porcion'][0]['recetas'][0] = array(
                                            'id_receta' => 391,
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => 'BASE POSTRE',
                                        );

                                        $producto['porcion'][0]['recetas'][1] = array(
                                            'id_receta' => $clave_postre[1],
                                            'cantidad_receta' => 1,
                                            'descripcion_receta' => $componentes['options'][0]['name'],
                                        );

                                        $producto['descripcion_producto'] = $componentes['options'][0]['name'];

                                        $precio_postre = ($componentes['options'][0]['price'] * $productos['amount']);
                                        $producto['precio_producto'] = $precio_postre;
                                        $producto['preciodm_producto'] = $precio_postre;

                                        $impuestoentrada = $precio_postre - ($precio_postre / 1.16);
                                        $producto['impuesto_producto'] = $impuestoentrada;
                                        $orden['impuesto'] += $impuestoentrada;
                                        $producto['impporc_producto'] = 16;

                                        $orden['productos'][] = $producto;

                                        error_log('Componente: ' . print_r($componentes, true));
                                    }
                                }
                            }
                        } else if ($contenido == 'CALZONE') {
                            // DESCRIPCION DEL PRODUCTO PIZZA
                            unset($producto);
                            $producto['id_producto'] = $idproductos++;
                            $producto['cantidad_producto'] = $productos['amount'];
                            $producto['id_esquemacobro'] = 1;
                            $producto['esquemacobro_producto'] = 'COBRO GENERAL';
                            $producto['descripcion_producto'] = $tipo_producto[2] . "CALZONE";

                            $precio_calzone = 0;
                            //ARMADO DEL COMPONENETE
                            foreach ($productos['modifiers'] as $idcomponente => $componentes) {
                                $calzone = explode('>', $componentes['externalId']);
                                if ($calzone[0] == 'CALZONE') { //PORCION DERECHA

                                    $precio_calzone = $calzone[2];
                                    unset($componente_full);

                                    $componente_full['id_porcion'] = 0;
                                    $componente_full['recetas'][] = array(
                                        'id_receta' => 28,
                                        'cantidad_receta' => 1,
                                        'descripcion_receta' => 'SAPREP',
                                    );
                                    $descripcion_full = " ";

                                    foreach ($componentes['countByExternalId'] as $id_receta_derecha => $cantidad_receta_full) {

                                        $sql = "SELECT nombre_receta FROM receta where id_receta != 28 and  id_receta =" . $id_receta_derecha;
                                        $result = $conn->query($sql);
                                        $row = mysqli_fetch_assoc($result);
                                        if ($result->num_rows > 0) {
                                            $componente_full['recetas'][] = array(
                                                'id_receta' => $id_receta_derecha,
                                                'cantidad_receta' => $cantidad_receta_full,
                                                'descripcion_receta' => $row['nombre_receta'],
                                            );

                                            if ($cantidad_receta_full > 1) {

                                                $descripcion_full .= " , " . $cantidad_receta_full . "x " . $row['nombre_receta'];
                                            } else {

                                                $descripcion_full .= " ,  " . $row['nombre_receta'];
                                            }
                                        }
                                    }


                                    $producto['descripcion_producto'] .= $adicionales . " " . $descripcion_full;
                                    $producto['porcion'][] = $componente_full;
                                }

                                //CALCULO DE PRECIO E IMPUESTO
                                $precio_pizza = $precio_calzone* $productos['amount'];
                                $producto['id_tamano'] = 690;

                                $producto['precio_producto'] = $precio_pizza;
                                $producto['preciodm_producto'] = $precio_pizza;

                                $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                                $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                                $producto['impporc_producto'] = 16;

                            }
                            $orden['productos'][] = $producto;
                        }
                    }
                } elseif ($tipo_producto[0] == 'PIZZAS2X1') {

                    unset($producto);
                    unset($componenetes);

                    $contenido_2x1 = explode(".", $tipo_producto[1]);
                    // DESCRIPCION DEL PRODUCTO PIZZA 1
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 917;
                    $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';
                    $producto['id_tamano'] = $contenido_2x1[0];
                    $producto['descripcion_producto'] = $contenido_2x1[1] . "*";

                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = $contenido_2x1[2] * $productos['amount'];

                    //ARMADO DEL COMPONENETE
                    foreach ($productos['modifiers'] as $idcomponente => $componentes) {



                        if ($componentes['externalId'] == 'TIPO-PAN1') { // TIPO DE PAN

                            if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 28) {

                                // BASE DE PIZZA
                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => "SAPREP",
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => "SAPREP",
                                );

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $precio_pizza += $componentes['options'][0]['price'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            } else {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $precio_pizza += $componentes['options'][0]['price'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SAPREP, " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'BORDE-PIZZA1') {

                            if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 16) {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $precio_pizza += $componentes['options'][0]['price'];
                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            } else if ($componentes['options'][0]['externalId'] == '-16') {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => 16,
                                    'cantidad_receta' => -1,
                                    'descripcion_receta' => "AJONJOLI",
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => 16,
                                    'cantidad_receta' => -1,
                                    'descripcion_receta' => "AJONJOLI",
                                );

                                $precio_pizza += $componentes['options'][0]['price'];
                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SIN AJONJOLI, " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'EXTRA-QUESO1') {

                            if ($componentes['options'][0]['externalId'] > 0) {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $precio_pizza += $componentes['options'][0]['price'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'SERVICIOS1') {
                        } else if ($componentes['externalId'] == 'LEFT1') { //PORCION IZQUIERDA
                            unset($componente_izquierdo);

                            $componente_izquierdo['id_porcion'] = 2;
                            $descripcion_izquierda = ", 2/2 ";
                            foreach ($componentes['countByExternalId'] as $id_receta_izquierda => $cantidad_receta_izquierda) {

                                $sql = "SELECT nombre_receta FROM receta where id_receta =" . $id_receta_izquierda;
                                $result = $conn->query($sql);
                                $row = mysqli_fetch_assoc($result);
                                if ($result->num_rows > 0) {
                                    $componente_izquierdo['recetas'][] = array(
                                        'id_receta' => $id_receta_izquierda,
                                        'cantidad_receta' => $cantidad_receta_izquierda,
                                        'descripcion_receta' => $row['nombre_receta'],
                                    );

                                    if ($cantidad_receta_izquierda > 1) {

                                        $descripcion_izquierda .= " , " . $cantidad_receta_izquierda . "x " . $row['nombre_receta'];
                                    } else {

                                        $descripcion_izquierda .= " ," . $row['nombre_receta'];
                                    }
                                }
                            }
                            if ($tipo_producto[3] == '53') {

                                $componente_izquierdo['recetas'][] = array(
                                    'id_receta' => 53,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $tipo_producto[4],
                                );

                                $componente_izquierdo['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => 'SAPREP',
                                );

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);

                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . ", CRUJI,* " . $auxiliar_desc[1];
                            }

                            $producto['descripcion_producto'] .= $descripcion_izquierda;
                            $producto['porcion'][] = $componente_izquierdo;
                        } else if ($componentes['externalId'] == 'RIGHT1') { //PORCION DERECHA

                            unset($componente_derecho);

                            $componente_derecho['id_porcion'] = 1;
                            $descripcion_derecha = " 1/2 ";

                            foreach ($componentes['countByExternalId'] as $id_receta_derecha => $cantidad_receta_derecha) {

                                $sql = "SELECT nombre_receta FROM receta where id_receta =" . $id_receta_derecha;
                                $result = $conn->query($sql);
                                $row = mysqli_fetch_assoc($result);
                                if ($result->num_rows > 0) {
                                    $componente_derecho['recetas'][] = array(
                                        'id_receta' => $id_receta_derecha,
                                        'cantidad_receta' => $cantidad_receta_derecha,
                                        'descripcion_receta' => $row['nombre_receta'],
                                    );

                                    if ($cantidad_receta_derecha > 1) {

                                        $descripcion_derecha .= " , " . $cantidad_receta_derecha . "x " . $row['nombre_receta'];
                                    } else {

                                        $descripcion_derecha .= " ,  " . $row['nombre_receta'];
                                    }
                                }
                            }


                            if ($tipo_producto[3] == '53') {

                                $componente_derecho['recetas'][] = array(
                                    'id_receta' => 53,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $tipo_producto[4],
                                );

                                $componente_derecho['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => 'SAPREP',
                                );
                            }

                            $producto['descripcion_producto'] .= $adicionales . " " . $descripcion_derecha;
                            $producto['porcion'][] = $componente_derecho;
                        }

                        $producto['precio_producto'] = $precio_pizza;
                        $producto['preciodm_producto'] = $precio_pizza;

                        $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                        $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                        $orden['impuesto'] += $impuestopizza;
                        $producto['impporc_producto'] = 16;
                    }
                    $orden['productos'][] = $producto;


                    unset($producto);
                    unset($componenetes);
                    // DESCRIPCION DEL PRODUCTO PIZZA 1
                    $producto['id_producto'] = $idproductos++;
                    $producto['cantidad_producto'] = $productos['amount'];
                    $producto['id_esquemacobro'] = 917;
                    $producto['esquemacobro_producto'] = 'DESCUENTOS ONLINE';
                    $producto['id_tamano'] = $contenido_2x1[0];
                    $producto['descripcion_producto'] = $contenido_2x1[1] . "*";

                    //CALCULO DE PRECIO E IMPUESTO
                    $precio_pizza = 0 * $productos['amount'];

                    //ARMADO DEL COMPONENETE
                    foreach ($productos['modifiers'] as $idcomponente => $componentes) {


                        if ($componentes['externalId'] == 'TIPO-PAN2') { // TIPO DE PAN

                            if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 28) {

                                // BASE DE PIZZA
                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => "SAPREP",
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => "SAPREP",
                                );

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $precio_pizza += $componentes['options'][0]['price'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            } else {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $precio_pizza += $componentes['options'][0]['price'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SAPREP, " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'BORDE-PIZZA2') {

                            if ($componentes['options'][0]['externalId'] > 0 && $componentes['options'][0]['externalId'] != 16) {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $precio_pizza += $componentes['options'][0]['price'];
                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            } else if ($componentes['options'][0]['externalId'] == '-16') {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => 16,
                                    'cantidad_receta' => -1,
                                    'descripcion_receta' => "AJONJOLI",
                                );
                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => 16,
                                    'cantidad_receta' => -1,
                                    'descripcion_receta' => "AJONJOLI",
                                );

                                $precio_pizza += $componentes['options'][0]['price'];
                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* SIN AJONJOLI, " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'EXTRA-QUESO2') {

                            if ($componentes['options'][0]['externalId'] > 0) {

                                $producto['porcion'][0]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $producto['porcion'][1]['recetas'][] = array(
                                    'id_receta' => $componentes['options'][0]['externalId'],
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $componentes['options'][0]['name'],
                                );

                                $precio_pizza += $componentes['options'][0]['price'];

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);
                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . "* " . $componentes['options'][0]['name'] . ", " . $auxiliar_desc[1];
                            }
                        } else if ($componentes['externalId'] == 'SERVICIOS2') {
                        } else if ($componentes['externalId'] == 'LEFT2') { //PORCION IZQUIERDA
                            unset($componente_izquierdo);

                            $componente_izquierdo['id_porcion'] = 2;
                            $descripcion_izquierda = ", 2/2 ";
                            foreach ($componentes['countByExternalId'] as $id_receta_izquierda => $cantidad_receta_izquierda) {

                                $sql = "SELECT nombre_receta FROM receta where id_receta =" . $id_receta_izquierda;
                                $result = $conn->query($sql);
                                $row = mysqli_fetch_assoc($result);
                                if ($result->num_rows > 0) {
                                    $componente_izquierdo['recetas'][] = array(
                                        'id_receta' => $id_receta_izquierda,
                                        'cantidad_receta' => $cantidad_receta_izquierda,
                                        'descripcion_receta' => $row['nombre_receta'],
                                    );

                                    if ($cantidad_receta_izquierda > 1) {

                                        $descripcion_izquierda .= " , " . $cantidad_receta_izquierda . "x " . $row['nombre_receta'];
                                    } else {

                                        $descripcion_izquierda .= " ," . $row['nombre_receta'];
                                    }
                                }
                            }
                            if ($tipo_producto[3] == '53') {

                                $componente_izquierdo['recetas'][] = array(
                                    'id_receta' => 53,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $tipo_producto[4],
                                );

                                $componente_izquierdo['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => 'SAPREP',
                                );

                                $auxiliar_desc = explode("*", $producto['descripcion_producto']);

                                $producto['descripcion_producto'] = " ";
                                $producto['descripcion_producto'] .= $auxiliar_desc[0] . ", CRUJI,* " . $auxiliar_desc[1];
                            }

                            $producto['descripcion_producto'] .= $descripcion_izquierda;
                            $producto['porcion'][] = $componente_izquierdo;
                        } else if ($componentes['externalId'] == 'RIGHT2') { //PORCION DERECHA

                            unset($componente_derecho);

                            $componente_derecho['id_porcion'] = 1;
                            $descripcion_derecha = " 1/2 ";

                            foreach ($componentes['countByExternalId'] as $id_receta_derecha => $cantidad_receta_derecha) {

                                $sql = "SELECT nombre_receta FROM receta where id_receta =" . $id_receta_derecha;
                                $result = $conn->query($sql);
                                $row = mysqli_fetch_assoc($result);
                                if ($result->num_rows > 0) {
                                    $componente_derecho['recetas'][] = array(
                                        'id_receta' => $id_receta_derecha,
                                        'cantidad_receta' => $cantidad_receta_derecha,
                                        'descripcion_receta' => $row['nombre_receta'],
                                    );

                                    if ($cantidad_receta_derecha > 1) {

                                        $descripcion_derecha .= " , " . $cantidad_receta_derecha . "x " . $row['nombre_receta'];
                                    } else {

                                        $descripcion_derecha .= " ,  " . $row['nombre_receta'];
                                    }
                                }
                            }


                            if ($tipo_producto[3] == '53') {

                                $componente_derecho['recetas'][] = array(
                                    'id_receta' => 53,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => $tipo_producto[4],
                                );

                                $componente_derecho['recetas'][] = array(
                                    'id_receta' => 28,
                                    'cantidad_receta' => 1,
                                    'descripcion_receta' => 'SAPREP',
                                );
                            }

                            $producto['descripcion_producto'] .= $adicionales . " " . $descripcion_derecha;
                            $producto['porcion'][] = $componente_derecho;
                        }

                        $producto['precio_producto'] = $precio_pizza;
                        $producto['preciodm_producto'] = $precio_pizza;

                        $impuestopizza = $precio_pizza - ($precio_pizza / 1.16);
                        $producto['impuesto_producto'] = number_format($impuestopizza, 2);
                        $orden['impuesto'] += $impuestopizza;
                        $producto['impporc_producto'] = 16;
                    }
                    $orden['productos'][] = $producto;
                }
            }
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api2.getjusto.com/api/v1/updateOrderStatus',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 40,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => '{
                    "orderId": "' . $value['_id'] . '",
                    "orderStatus":"waiting"
                    }',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer WXgyPWKyuC2ZgfZAjPDcr2ZDJoojXx3vk7oeGdjLXJnYw7WEXAzvETkJmKw59joa',
                    'Content-Type: application/json',
                ),
            ));
            $respord = curl_exec($curl);

            if ($respord === false) {
                echo "CURL Error:" . curl_error($curl);
            }

            echo "<pre>";
            print_r($orden);
            echo "</pre>";
        }

        foreach ($orden['productos'] as $key => $value) {
            if($orden['benepuntos'] <= $value['preciodm_producto']){
                $orden['productos'][$key]['preciodm_producto'] = floatval($value['preciodm_producto'] - $orden['benepuntos']);
                $orden['productos'][$key]['impuesto_producto'] =floatval(($value['preciodm_producto'] - $orden['benepuntos'])-(  ($value['preciodm_producto'] - $orden['benepuntos'])/1.16));
                break;
            }
        }

        $ordeni['orden'][] = $orden;
        $ordenes = json_encode($ordeni);

        return $ordenes;
    }

    curl_close($curl);
}
$ube = getDataUBE();
if ($ube === false) {
    bpLogError('No se pudo obtener configuracion UBE. Se detiene ejecucion del daemon.');
    exit(1);
}

$cfgube = leeConfig('ube');
// print_r($ube);

while (1) {

    // aqui va el script para obtener las ordenes
    $num = 0;
    $contentPage = '';
    $ordene = ordenJusto();

    $orden = json_decode($ordene, true);
    $num = count($orden);
    $cont = 0;
    if ($num > 0 and $orden['orden'][0] != NULL) {
        foreach ($orden as $nord => $ords) {
            $cont++;
            echo "\n=>Orden:>" . $cont;
            echo "\n";
            foreach ($ords as $numord => $ordR) {

                print_r($ordR);
                $cadfile = "\n============================================================";
                $cadfile .= "\n inicio ejecutando - " . date("d/m/Y H:i:s");
                $cadfile .= "\n------------------------------------------------------------";
                $usrR = $ordR['id_usuario'];
                $total = 0;
                $ordupd = new orden;
                $ordupd->nuevaOrden(88889);

                //if ($contentPage == 1) {
                echo "\n>> actualizado..";
                //continuar con el demonio...
                $in_ctemos = $ordR['cliente']['etiqueta'] . ' ' . $ordR['cliente']['nombre_cliente'] . ' ' . $ordR['cliente']['apellido_cliente'];
                $ordupd->ctemos = $in_ctemos;
                $cte = new cliente;
                $cte->nuevoCliente();
                $cte->nombre = utf8_decode($ordR['cliente']['nombre_cliente']);
                $cte->apellidos = utf8_decode($ordR['cliente']['apellido_cliente']);
                $cte->comentario = utf8_decode($ordR['cliente']['comment']);
                $cte->email = $ordR['cliente']['email'];
                $cte->ultimaorden = date("Y-m-d");
                $cte->primeraorden = $cte->ultimaorden;
                $cte->frecuencia++;
                $cte->rpl = 'I';
                $cte->rplnube = 'I';
                $cte->escribeCliente();
                $ordupd->idCliente = $cte->id;

                $tel = new telefono;
                if ($tel->leeTelefono(0, $ordR['cliente']['id_telefono']) == false) {
                    $tel->nuevoTelefono($ordR['cliente']['id_telefono']);
                }
                $tel->leeLada();
                $tel->escribeTelefono();
                $ordupd->idTelefono = $tel->id;

                $cadfile .= "\n" . $ordR['cliente']['id_telefono'] . " - " . $in_ctemos;
                $cadfile .= sprintf("\nidOrdenWEB= %d, ------- %d de %d", $ordR['id_orden'], $numord, $nords);
                $dom = new domicilio;
                $dom->nuevoDomicilio();
                $dom->numeroExt = $ordR['cliente']['numext_domicilio'];
                $dom->numeroInt = $ordR['cliente']['numint_domicilio'];
                $dom->referencia = sprintf("%s, %s", $ordR['cliente']['referencia_domicilio'], $ordR['cliente']['comment']);

                $dom->idCalle = proccc('nombre', 'calle', utf8_decode($ordR['cliente']['nombre_calle']));
                $dom->idColonia = proccc('nombre', 'colonia', utf8_decode($ordR['cliente']['id_colonia']));
                $dom->idCodigopostal = proccc('numero', 'codigopostal', $ordR['cliente']['id_codigopostal']);

                $dom->escribeDomicilio();
                $ordupd->idDomicilio = $dom->id;

                destinoctd($cte->id, $dom->id, $tel->id);

                $ordupd->web = "@";
                $ordupd->escribeOrden();
                $dom->__destruct();
                $tel->__destruct();
                $cte->__destruct();

                foreach ($ordR['productos'] as $idpro => $proR) {
                    $proP = $proR['id_producto'];
                    $pro = new producto;
                    $pro->nuevoProducto($ordupd->id);
                    $pro->idTamanno = $proR['id_tamano'];
                    $pro->cantidad = $proR['cantidad_producto'];
                    $pro->descripcion = utf8_decode($proR['descripcion_producto']);
                    //    printf("\n\t idpro= %d %s ------- idtam= %d ----- cant= %d ", $idpro, $proR['desc_producto'], $proR['id_tamano'], $proR['cantidad']);
                    //    print_r($proR['porcion']['recetas']);
                    //    echo "\n".$proR->porcion->recetas[0]->descripcion_receta;
                    foreach ($proR['porcion'] as $porcion) {
                        print_r("fffffffffffffffffffffffffff" . $porcion -> recetas);
                        //        echo "\nProd\nporidpor=".$porcion['id_porcion']." cant=".$porcion['recetas'][0]['cantidad_receta'];
                        foreach ($porcion['recetas'] as $numrec => $receta) {
                            //            echo "receta".$numrec."\n";
                            //            print_r($receta);
                            $comp = new componente;
                            $comp->nuevoComponente($pro->idProducto, $pro->idOrden);
                            $comp->idReceta = $receta['id_receta'];
                            $comp->cantidad = $receta['cantidad_receta'];
                            $comp->idPorcion = $porcion['id_porcion'];
                            $comp->rpl = 'I';
                            $comp->rplnube = 'I';
                            $rec = new receta;
                            $rec->leeReceta($comp->idReceta);
                            if (!$pro->idTamanno) {
                                $rec->leeTamannos();
                                foreach ($rec->tamannos as $pro->idTamanno) {
                                    break;
                                }
                            }
                            $comp->incexc = $rec->excluye;
                            $comp->prioridad = $rec->prioridad;
                            $rec->__destruct();
                            $comp->escribeComponente();
                            //                    printf("\n\t\tcant %d ---- %s ----- id: %d ", $xtra->cantidad, $ingP->desc_ingrediente, $ingP->id_ingrediente);
                            $comp->__destruct();
                        }
                    } // porciones
                    $tam = new tamanno;
                    $tam->leeTamanno($pro->idTamanno);
                    $tam->subtiporeceta();
                    $pro->idTiporeceta = $tam->idTiporeceta;
                    $pro->esAdicional = esadc($tam->idTiporeceta, $tam->idSubtiporeceta);
                    $pro->idEsquemacobro = $tam->idEsquemacobro;
                    $tam->__destruct();

                    $tip = new tiporeceta;
                    $tip->leeTiporeceta($pro->idTiporeceta);
                    //    $pro->esAdicional= $tip->esadicional;
                    if ($pro->esAdicional < 2) {
                        $pro->canxp = $proR['cantidad_producto'];
                        $pro->canxh = $proR['cantidad_producto'];
                    }

                    $pro->escribeProducto();

                    $tip->__destruct();
                    $pro->leeProducto($pro->idOrden, $pro->idProducto);
                    $cadfile .= "\n" . $pro->descripcion . "\t\t" . $proR['preciodm_producto'];
                    $total += $proR['preciodm_producto'];
                    $pro->precio = $proR['precio_producto'];
                    $pro->comment = $proR['comment_prodducto'];
                    $pro->precioDm = $proR['preciodm_producto'];
                    $pro->idEsquemacobro = $proR['id_esquemacobro'];
                    $pro->desEsquemacobro = $proR['esquemacobro_producto'];
                    $pro->impuesto = $proR['impuesto_producto'];
                    $pro->impPorc = $proR['impporc_producto'];
                    $pro->descripcion .= $proR['sabor'];
                    $pro->escribeProducto();
                    $pro->__destruct();
                }
                $dia = new diaoperativo;
                $dia->leeDOPabierto();
                if (!$dia->consec) {
                    $dia->leeConsec();
                }

                $dia->consec++;
                $dia->rpl = 'I';
                $dia->rplnube = 'I';
                $dia->escribe();
                $ordupd->consec = $dia->consec;
                $dia->__destruct();
                $ordupd->tsprocesada = date("YmdHis");
                $ordupd->trecepcion = time() - strtotime($ordupd->timestamp);
                $ordupd->rpl = 'I';
                $ordupd->rplnube = 'I';
                $pwdfact = 0;
                if ($factura || $cfgube[7]['param1']) {
                    $ordupd->fpwdord();
                }

                if (intval($ordR['tipo_orden'])) {
                    $ordupd->idTipoorden = $ordR['tipo_orden'];
                } else {
                    $ordupd->idTipoorden = 13;
                }

                $cadena = '';
                if (strlen($ordRd['delivery_time'])) {
                    $cadena = sprintf("\nentregar: %s", $ordR['delivery_time']);
                }

                $ordupd->mensaje = utf8_decode($ordR['comentario']) . $cadena;
                $cadfile .= $ordupd->mensaje;
                $ordupd->pago = $ordR['pago'];
                $ordupd->tpago = $ordR['tipo_pago'];
                if (!strlen($ordupd->tpago)) {
                    $ordupd->tpago = "EFECTIVO";
                }

                $ordupd->precioDm = $ordR['total'];
                $ordupd->folio = leeFolio();
                $ordupd->benepuntos = $ordR['benepuntos'];
                $ordupd->escribeOrden();
                // print_r($ordupd);
                imprimeOrden($ordupd->id, $in_ctemos, $cfgube[7]['param2']); // includes/print.php
                // aqui va la actualizacion de la orden web
                //cambiar update...
                // ****************************************
                $cadfile .= "\n ordWeb#" . $ordR['id_orden'] . ", ordLocal# " . $ordid;
                $cadfile .= "\n Total= " . $ordR['total'] . "\n-------------------------------------------------------------------------\n";
                $cadfile .= "\n\nResponse:\n" . $response . "\n\nResultstatus;\n" . $resultStatus;
                $ordupd->__destruct();
                /* FIN DE LA ESTRUCTURA DE LA ORDEN.. */
            }
        }
        $contentPage = '';
        echo $cadfile;
        echo "\n 40s..." . date("Y/m/d H:i:s") . "\n";
    } else {
        echo "</br>NO HAY ORDENES DISPONIBLES</br>";
    }
    //si trae orden..

    sleep(40);
}

// echo "\n terminando ejecucion\n\n";
