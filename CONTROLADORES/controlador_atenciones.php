<?php
require_once '../MODELOS/modelo_atenciones.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
    exit;
}

$id_usuario = intval($_SESSION['usuario']['id_usuario']);
$id_rol     = intval($_SESSION['usuario']['id_rol']);
$modelo     = new ModeloAtenciones();
$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar_citas':
        $id_pac = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->listarCitasPaciente($id_pac));
        break;

    case 'obtener_saldo':
        $id_pac = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->obtenerSaldo($id_pac));
        break;

    case 'listar_planes_activos':
        $id_pac = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->listarPlanesActivos($id_pac));
        break;

    case 'listar_servicios_cita':
        $id_cita = intval($_GET['id_cita'] ?? 0);
        echo json_encode($modelo->listarServiciosCita($id_cita));
        break;

    case 'guardar_informe':
        if ($id_rol !== 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos']);
            break;
        }
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!$datos) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            break;
        }
        echo json_encode($modelo->guardarInforme($datos, $id_usuario));
        break;
    case 'registrar_pago':
        if ($id_rol !== 1 && $id_rol !== 3) {
            echo json_encode(['success' => false, 'mensaje' => 'Sin permiso para registrar pagos']);
            break;
        }
        $id_pac  = intval($_POST['id_paciente'] ?? 0);
        $id_cita = intval($_POST['id_cita'] ?? 0) ?: null;
        $monto   = floatval($_POST['monto'] ?? 0);
        $obs     = trim($_POST['observacion'] ?? '');
        if ($monto <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'El monto debe ser mayor a 0']);
            break;
        }
        echo json_encode($modelo->registrarPago($id_pac, $id_cita, $monto, $obs, $id_usuario));
        break;

    case 'listar_servicios':
        echo json_encode($modelo->listarServicios());
        break;

    case 'listar_planes':
        echo json_encode($modelo->listarPlanes());
        break;
    case 'enviar_a_cobrar':
        if ($id_rol !== 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos']);
            break;
        }
        $id_informe = intval($_POST['id_informe'] ?? 0);
        echo json_encode($modelo->enviarACobrar($id_informe, $id_usuario));
        break;

    case 'verificar_pagos':
        $id_cita = intval($_GET['id_cita'] ?? 0);
        echo json_encode(['tiene_pagos' => $modelo->citaTienePagos($id_cita)]);
        break;


    case 'cobros_pendientes':
        if ($id_rol !== 1 && $id_rol !== 3) {
            echo json_encode(['success' => false, 'mensaje' => 'Sin permiso']);
            break;
        }
        echo json_encode($modelo->listarCobrosPendientes());
        break;
    case 'listar_aparatologia':
        echo json_encode($modelo->listarAparatologia());
        break;
    case 'listar_aparatologia_plan':
        $id_pp = intval($_GET['id_paciente_plan'] ?? 0);
        echo json_encode($modelo->listarAparatologiaPlan($id_pp));
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
}
