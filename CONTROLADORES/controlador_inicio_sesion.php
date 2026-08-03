<?php
require_once '../MODELOS/modelo_inicio_sesion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo_usuario = trim($_POST['codigo_usuario']);
    $contrasena     = $_POST['contrasena'];

    $modelo  = new ModeloInicioSesion();
    $usuario = $modelo->verificarUsuario($codigo_usuario, $contrasena);

    if ($usuario) {
        if ($usuario['id_estado'] == 1) {
            // Cargar permisos del rol desde BD
            $permisos = $modelo->obtenerPermisos($usuario['id_rol']);

            $_SESSION['usuario'] = [
                'nombre'     => $usuario['nombre'],
                'id_usuario' => $usuario['id_usuario'],
                'id_rol'     => $usuario['id_rol'],
                'permisos'   => $permisos   // array de módulos permitidos
            ];

            header("Location: ../VISTAS/vista_panel_admin.php");
            exit();
        } else {
            header("Location: ../VISTAS/vista_inicio_sesion.php?error=inactivo");
            exit();
        }
    } else {
        header("Location: ../VISTAS/vista_inicio_sesion.php?error=1");
        exit();
    }
}
?>