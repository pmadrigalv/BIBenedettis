<?php

function generarSaltBcrypt()
{
    $caracteres = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $salt = '';
    for ($i = 0; $i < 22; $i++) {
        $salt .= $caracteres[mt_rand(0, strlen($caracteres) - 1)];
    }

    return $salt;
}

function hashPasswordUsuario($password)
{
    $salt = generarSaltBcrypt();
    $hash = crypt((string)$password, '$2a$10$' . $salt);

    if (!is_string($hash) || strlen($hash) < 20) {
        throw new RuntimeException('No fue posible generar el hash de contraseña.');
    }

    return $hash;
}

function verifyPasswordUsuario($password, $hashGuardado)
{
    if (!is_string($hashGuardado) || $hashGuardado === '') {
        return false;
    }

    $rehash = crypt((string)$password, $hashGuardado);
    if (!is_string($rehash)) {
        return false;
    }

    return $rehash === $hashGuardado;
}

function hashPasswordUnidad($password)
{
    return hashPasswordUsuario($password);
}

function verifyPasswordUnidad($password, $hashGuardado)
{
    return verifyPasswordUsuario($password, $hashGuardado);
}
