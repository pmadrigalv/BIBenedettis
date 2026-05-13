<?php

if (php_sapi_name() !== 'cli') {
    echo "Este script solo se ejecuta por CLI.\n";
    exit(1);
}

require_once __DIR__ . '/auth/password_utils.php';

if (!isset($argv[1]) || trim($argv[1]) === '') {
    echo "Uso: php generar_hash_password.php <password> [id_usuario]\n";
    exit(1);
}

$passwordPlano = (string)$argv[1];
$idUsuario = isset($argv[2]) ? (int)$argv[2] : 0;

try {
    $hash = hashPasswordUsuario($passwordPlano);
    echo "Hash generado:\n" . $hash . "\n\n";

    if ($idUsuario > 0) {
        echo "SQL sugerido:\n";
        echo "UPDATE usuario SET password = '" . str_replace("'", "''", $hash) . "' WHERE id_usuario = " . $idUsuario . ";\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
