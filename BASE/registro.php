<?php

require_once __DIR__ . '/controllers/VentasController.php';
require_once __DIR__ . '/auth/password_utils.php';
require_once __DIR__ . '/config/app.php';

$connections = require __DIR__ . '/config/database.php';

if (session_id() === '') {
    session_start();
}

$mensaje = '';
$error = '';
$uidUsuario = '';
$nombres = '';
$apellidos = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uidUsuario = isset($_POST['uid_usuario']) ? trim((string)$_POST['uid_usuario']) : '';
    $nombres = isset($_POST['nombres_usuario']) ? trim((string)$_POST['nombres_usuario']) : '';
    $apellidos = isset($_POST['apellidos_usuario']) ? trim((string)$_POST['apellidos_usuario']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $passwordConfirmacion = isset($_POST['password_confirmacion']) ? (string)$_POST['password_confirmacion'] : '';

    if ($uidUsuario === '' || $password === '' || $passwordConfirmacion === '') {
        $error = 'Debes capturar usuario y contraseña.';
    } elseif ($password !== $passwordConfirmacion) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $controller = new VentasController($connections);
            $hash = hashPasswordUsuario($password);
            $resultado = $controller->registrarUsuarioLogin(
                'ssql_relaciones',
                $uidUsuario,
                $nombres,
                $apellidos,
                $hash
            );

            if (isset($resultado['nuevo']) && $resultado['nuevo']) {
                $mensaje = 'Usuario registrado correctamente. Ya puedes iniciar sesión.';
            } else {
                $mensaje = 'Contraseña registrada/actualizada correctamente. Ya puedes iniciar sesión.';
            }
        } catch (Exception $e) {
            $error = 'No se pudo registrar el usuario: ' . $e->getMessage();
        }
    }
}

function e($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Registro de usuario</title>
    <style>
    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        background: #f3f6f8;
        color: #1b1b1b;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .registro-box {
        background: #ffffff;
        border: 1px solid #d9d9d9;
        border-radius: 8px;
        padding: 20px;
        width: 390px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    }

    h1 {
        margin: 0 0 16px;
        font-size: 22px;
    }

    .row {
        margin-bottom: 12px;
    }

    label {
        display: block;
        margin-bottom: 4px;
        font-weight: bold;
    }

    input {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 10px;
        font-size: 14px;
    }

    button {
        width: 100%;
        padding: 10px;
        border: 0;
        border-radius: 5px;
        color: #fff;
        font-weight: bold;
        background: #067845;
        cursor: pointer;
    }

    .error {
        margin-bottom: 12px;
        padding: 10px;
        border-radius: 5px;
        background: #ffe9e9;
        color: #9f1f1f;
        border: 1px solid #f4bdbd;
    }

    .ok {
        margin-bottom: 12px;
        padding: 10px;
        border-radius: 5px;
        background: #e8f9ef;
        color: #1f7a3b;
        border: 1px solid #b8ebc8;
    }

    .links {
        margin-top: 10px;
        font-size: 13px;
    }

    .links a {
        color: #046d3f;
        text-decoration: none;
        font-weight: bold;
    }
    </style>
</head>

<body>
    <div class="registro-box">
        <h1>Registro de Usuario</h1>

        <?php if ($error !== ''): ?>
        <div class="error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje !== ''): ?>
        <div class="ok"><?php echo e($mensaje); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="row">
                <label for="uid_usuario">Nombre de usuario</label>
                <input type="text" name="uid_usuario" id="uid_usuario" value="<?php echo e($uidUsuario); ?>" required>
            </div>
            <div class="row">
                <label for="nombres_usuario">Nombres (opcional)</label>
                <input type="text" name="nombres_usuario" id="nombres_usuario" value="<?php echo e($nombres); ?>">
            </div>
            <div class="row">
                <label for="apellidos_usuario">Apellidos (opcional)</label>
                <input type="text" name="apellidos_usuario" id="apellidos_usuario" value="<?php echo e($apellidos); ?>">
            </div>
            <div class="row">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="row">
                <label for="password_confirmacion">Confirmar contraseña</label>
                <input type="password" name="password_confirmacion" id="password_confirmacion" required>
            </div>
            <button type="submit">Registrar</button>
        </form>

        <div class="links">
            <a href="<?php echo e(appUrl('/login.php')); ?>">Ir a iniciar sesión</a>
        </div>
    </div>
</body>

</html>