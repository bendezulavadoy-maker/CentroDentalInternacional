<?php
session_start();
session_unset();
session_destroy();
header("Location: ../VISTAS/vista_inicio_sesion.php");
exit();
?>
