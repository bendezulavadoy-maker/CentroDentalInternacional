<?php
date_default_timezone_set('America/Lima');

require_once '../CONFIG/conexion.php';
require_once 'GoogleCalendarService.php';

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

// Separar id_doctor y origen del state
$partes    = explode('|', $state);
$id_doctor = intval($partes[0] ?? 0);
$origen    = $partes[1] ?? 'configuracion'; // 'configuracion' o 'agenda'

// URLs de retorno según origen
$urlBase = $origen === 'agenda'
    ? '../VISTAS/vista_panel_admin.php?modulo=mi_agenda'
    : '../VISTAS/vista_panel_admin.php?modulo=configuracion';

if ($error) {
    header("Location: {$urlBase}&gcal=cancelado");
    exit;
}
if (!$code || !$id_doctor) {
    header("Location: {$urlBase}&gcal=error");
    exit;
}

$db       = (new Conexion())->getConexion();
$gcal     = new GoogleCalendarService($db);
$resultado = $gcal->intercambiarCodigo($code, $id_doctor);

if ($resultado['ok']) {
    header("Location: {$urlBase}&gcal=conectado&doctor={$id_doctor}");

} elseif ($resultado['error'] === 'email_no_coincide') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['gcal_error'] = [
        'tipo'          => 'email_no_coincide',
        'email_google'  => $resultado['email_google'],
        'email_sistema' => $resultado['email_sistema'],
    ];
    header("Location: {$urlBase}&gcal=email_no_coincide&doctor={$id_doctor}");

} else {
    header("Location: {$urlBase}&gcal=error");
}
exit;
?>