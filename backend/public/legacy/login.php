<?php

require_once __DIR__ . '/controllers/VentasController.php';
require_once __DIR__ . '/auth/password_utils.php';
require_once __DIR__ . '/config/app.php';

$connections = require __DIR__ . '/config/database.php';

if (session_id() === '') {
    session_start();
}

if (isset($_SESSION['usuario_auth']) && is_array($_SESSION['usuario_auth'])) {
    header('Location: ' . appUrl('/reportes/dia.php'));
    exit;
}

$error = '';
$usuarioLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioLogin = isset($_POST['usuario_login']) ? trim((string)$_POST['usuario_login']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($usuarioLogin === '' || $password === '') {
        $error = 'Debes capturar usuario y contraseña.';
    } else {
        try {
            $controller = new VentasController($connections);
            $usuario = $controller->consultarUsuarioPorUidLogin('ssql_relaciones', $usuarioLogin);

            if (!$usuario) {
                $error = 'Credenciales inválidas.';
            } else {
                $vigencia = isset($usuario['vigencia_usuario']) ? (int)$usuario['vigencia_usuario'] : 0;
                $hashGuardado = isset($usuario['password']) ? (string)$usuario['password'] : '';
                if ($vigencia !== 1) {
                    $error = 'El usuario no está activo.';
                } elseif ($hashGuardado === '') {
                    $error = 'El usuario no tiene contraseña configurada.';
                } elseif (!verifyPasswordUsuario($password, $hashGuardado)) {
                    $error = 'Credenciales inválidas.';
                } else {
                    $_SESSION['usuario_auth'] = array(
                        'id_usuario' => (int)$usuario['id_usuario'],
                        'uid_usuario' => isset($usuario['uid_usuario']) ? (string)$usuario['uid_usuario'] : '',
                        'nombres_usuario' => isset($usuario['nombres_usuario']) ? (string)$usuario['nombres_usuario'] : '',
                        'apellidos_usuario' => isset($usuario['apellidos_usuario']) ? (string)$usuario['apellidos_usuario'] : '',
                        'razsoc_usuario' => isset($usuario['razsoc_usuario']) ? (string)$usuario['razsoc_usuario'] : '',
                    );

                    header('Location: ' . appUrl('/reportes/dia.php'));
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = 'No se pudo iniciar sesión: ' . $e->getMessage();
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
    <title>Acceso reportes</title>
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

    .login-box {
        background: #ffffff;
        border: 1px solid #d9d9d9;
        border-radius: 8px;
        padding: 20px;
        width: 360px;
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

    .hint {
        margin-top: 10px;
        font-size: 12px;
        color: #666;
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
    <div class="login-box">
        <h1>Acceso a Reportes</h1>
        <?php if ($error !== ''): ?>
        <div class="error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="row">
                <label for="usuario_login">Nombre de usuario</label>
                <input type="text" name="usuario_login" id="usuario_login" value="<?php echo e($usuarioLogin); ?>"
                    required>
            </div>
            <div class="row">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
        <p class="hint">Usa la contraseña cifrada guardada en Tablero.usuario.password.</p>
        <div class="links">
            <a href="<?php echo e(appUrl('/registro.php')); ?>">Registrar usuario</a>
        </div>
    </div>
</body>

</html>