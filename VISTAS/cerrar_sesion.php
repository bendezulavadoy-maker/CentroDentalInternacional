<?php
session_start();
session_destroy();
header("Location: vista_inicio_sesion.php");
exit();
?>
