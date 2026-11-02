<?php
/**
 * Guardia para los endpoints JSON (CONTROLADORES).
 *
 * Uso al inicio de cada controlador:
 *   require_once '../CONFIG/requerir_sesion_api.php';   // exige sesion iniciada
 *   requerirPermisoApi('personal');                      // ademas exige el permiso del modulo
 *
 * Si no hay sesion responde 401; si falta el permiso, 403. En ambos casos
 * corta la ejecucion antes de tocar la base de datos.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function responderErrorApi($codigo, $mensaje) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'mensaje' => $mensaje]);
    exit;
}

if (!isset($_SESSION['usuario']['id_usuario'])) {
    responderErrorApi(401, 'Sesión no válida. Inicia sesión nuevamente.');
}

/**
 * Exige que el rol del usuario tenga acceso al modulo (tabla permisos_rol).
 * $modulo: 'personal', 'pacientes', 'citas', 'cobros', 'historia_clinica', ...
 */
function requerirPermisoApi($modulo) {
    $permisos = $_SESSION['usuario']['permisos'] ?? [];
    if (!in_array($modulo, $permisos, true)) {
        responderErrorApi(403, 'No tienes permiso para realizar esta acción.');
    }
}
?>