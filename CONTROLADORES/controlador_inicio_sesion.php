<?php
require_once '../MODELOS/modelo_inicio_sesion.php';
require_once '../CONFIG/limite_intentos.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo_usuario = trim($_POST['codigo_usuario'] ?? '');
    $contrasena     = $_POST['contrasena'] ?? '';

    // Freno contra adivinar contrasenas: 5 fallos por cuenta (desde la misma conexion) o 30 por conexion, en 15 minutos
    $ip          = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    $claveCuenta = 'login:' . strtolower($codigo_usuario) . '|' . $ip;
    $claveIp     = 'login-ip:' . $ip;
    if (LimiteIntentos::bloqueado($claveCuenta, 5, 15) || LimiteIntentos::bloqueado($claveIp, 30, 15)) {
        header("Location: ../VISTAS/vista_inicio_sesion.php?error=bloqueado");
        exit();
    }

    $modelo  = new ModeloInicioSesion();
    $usuario = $modelo->verificarUsuario($codigo_usuario, $contrasena);

    if ($usuario) {
        if ($usuario['id_estado'] == 1) {
            LimiteIntentos::limpiar($claveCuenta);

            // Cargar permisos del rol desde BD
            $permisos = $modelo->obtenerPermisos($usuario['id_rol']);

            session_regenerate_id(true);   // sesion nueva al entrar (evita reutilizar una sesion ajena)
            $debeCambiar = (int)($usuario['debe_cambiar_contrasena'] ?? 0);

            $_SESSION['usuario'] = [
                'nombre'     => $usuario['nombre'],
                'id_usuario' => $usuario['id_usuario'],
                'id_rol'     => $usuario['id_rol'],
                'permisos'   => $permisos,   // array de módulos permitidos
                'debe_cambiar_contrasena' => $debeCambiar
            ];

            header($debeCambiar
                ? "Location: ../VISTAS/vista_cambiar_contrasena.php?obligatorio=1"
                : "Location: ../VISTAS/vista_panel_admin.php");
            exit();
        } else {
            header("Location: ../VISTAS/vista_inicio_sesion.php?error=inactivo");
            exit();
        }
    } else {
        LimiteIntentos::registrar($claveCuenta);
        LimiteIntentos::registrar($claveIp);
        header("Location: ../VISTAS/vista_inicio_sesion.php?error=1");
        exit();
    }
}
?>
