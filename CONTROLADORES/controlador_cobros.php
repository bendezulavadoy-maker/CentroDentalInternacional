<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../CONFIG/verificar_sesion.php';
require_once '../MODELOS/modelo_cobros.php';

header('Content-Type: application/json');


// Solo asistente (id_rol = 2) y admin (id_rol = 1) pueden acceder
$id_rol = $_SESSION['usuario']['id_rol'] ?? 0;
if (!in_array($id_rol, [1, 2])) {
    echo json_encode(['success' => false, 'mensaje' => 'Sin permiso']);
    exit;
}

$modelo       = new ModeloCobros();
$id_usuario   = $_SESSION['usuario']['id_usuario'] ?? 0;
$accion       = $_GET['accion'] ?? $_POST['accion'] ?? '';

switch ($accion) {

    case 'listar_buzon':
        $resultado = $modelo->listarBuzon();
        if ($resultado === false) {
            echo json_encode(['error' => 'Query failed - check error_log']);
        } else {
            echo json_encode($resultado);
        }
        break;

    case 'detalle_cobro':
        $id_paciente = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->detalleCobro($id_paciente));
        break;

    case 'resumen_saldos':
        $id_paciente = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->resumenSaldos($id_paciente));
        break;

    case 'registrar_pagos':
        $data = json_decode(file_get_contents('php://input'), true);
        $id_paciente = intval($data['id_paciente'] ?? 0);
        $pagos       = $data['pagos'] ?? [];
        if (!$id_paciente || empty($pagos)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos']);
            break;
        }
        echo json_encode($modelo->registrarPagos($pagos, $id_paciente, $id_usuario));
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}