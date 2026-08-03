<?php
require_once '../MODELOS/modelo_mi_agenda.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['id_rol'] != 2) {
    echo json_encode(['success' => false, 'mensaje' => 'Acceso denegado']);
    exit();
}

$id_doctor = $_SESSION['usuario']['id_usuario'];
$modelo    = new ModeloMiAgenda();
$accion    = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar':
        $desde = $_GET['desde'] ?? null;
        $hasta = $_GET['hasta'] ?? null;
        echo json_encode($modelo->listarCitas($id_doctor, $desde, $hasta));
        break;

    case 'ver_cita':
        $id = intval($_GET['id'] ?? 0);
        echo json_encode($modelo->verDetalleCita($id, $id_doctor));
        break;

    case 'cambiar_estado':
        $id_cita   = intval($_POST['id_cita'] ?? 0);
        $id_estado = intval($_POST['id_estado'] ?? 0);
        if (!in_array($id_estado, [4, 6])) {
            echo json_encode(['success' => false, 'mensaje' => 'Estado no permitido']);
            break;
        }
        echo json_encode($modelo->cambiarEstadoCita($id_cita, $id_estado, $id_doctor));
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>