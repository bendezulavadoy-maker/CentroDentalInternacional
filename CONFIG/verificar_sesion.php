<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario']['id_usuario'])) {
    header("Location: ../VISTAS/vista_inicio_sesion.php?error=2");
    exit();
}

// Quien debe cambiar su contrasena no entra al sistema hasta hacerlo
if (!empty($_SESSION['usuario']['debe_cambiar_contrasena'])) {
    header("Location: ../VISTAS/vista_cambiar_contrasena.php?obligatorio=1");
    exit();
}

if (isset($rolRequerido) && $_SESSION['usuario']['id_rol'] != $rolRequerido) {
    header("Location: ../VISTAS/sin_permiso.php");
    exit();
}
?>