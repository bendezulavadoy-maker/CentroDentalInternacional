<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario']['id_usuario'])) {
    header("Location: ../VISTAS/vista_inicio_sesion.php?error=2");
    exit();
}

if (isset($rolRequerido) && $_SESSION['usuario']['id_rol'] != $rolRequerido) {
    header("Location: ../VISTAS/sin_permiso.php");
    exit();
}
?>