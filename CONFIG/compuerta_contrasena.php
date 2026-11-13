<?php
/**
 * COMPUERTA DE CONTRASENA.
 * Quien debe cambiar su contrasena (cuenta nueva, restablecida o con la clave inicial) no puede
 * usar el sistema hasta cambiarla. Se incluye al inicio de cada controlador que exige sesion.
 * Si no hay sesion no hace nada: cada controlador sigue aplicando su propia revision.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['usuario']['debe_cambiar_contrasena'])) {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'requiere_cambio_contrasena' => true,
        'mensaje' => 'Debes cambiar tu contraseña antes de continuar.',
    ]);
    exit;
}
?>
