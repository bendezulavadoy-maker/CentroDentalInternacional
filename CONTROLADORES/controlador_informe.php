<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
require_once '../MODELOS/modelo_informe.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
    exit;
}

$id_usuario = intval($_SESSION['usuario']['id_usuario']);
$id_rol     = intval($_SESSION['usuario']['id_rol']);
$modelo     = new ModeloInforme();
$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    // ── Obtener datos de la cita ──────────────────────────────────
    case 'obtener_cita':
        $id_cita = intval($_GET['id_cita'] ?? 0);
        $datos   = $modelo->obtenerDatosCita($id_cita);
        if (!$datos) {
            echo json_encode(['success' => false, 'mensaje' => 'Cita no encontrada']);
        } else {
            echo json_encode(['success' => true, 'cita' => $datos]);
        }
        break;

    // ── Guardar informe ───────────────────────────────────────────
    case 'guardar_informe':
        if ($id_rol !== 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos pueden registrar informes']);
            break;
        }
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!$datos) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            break;
        }
        echo json_encode($modelo->guardarInforme($datos, $id_usuario));
        break;

    // ── Enviar a cobrar ───────────────────────────────────────────
    case 'enviar_a_cobrar':
        if ($id_rol !== 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos']);
            break;
        }
        $id_informe = intval($_POST['id_informe'] ?? 0);
        echo json_encode($modelo->enviarACobrar($id_informe, $id_usuario));
        break;

    // ── Verificar pagos ───────────────────────────────────────────
    case 'verificar_pagos':
        $id_cita = intval($_GET['id_cita'] ?? 0);
        echo json_encode(['tiene_pagos' => $modelo->tienePagos($id_cita)]);
        break;

    // ── Servicios de la cita ──────────────────────────────────────
    case 'listar_servicios_cita':
        $id_cita = intval($_GET['id_cita'] ?? 0);
        echo json_encode($modelo->listarServiciosCita($id_cita));
        break;

    // ── Aparatología del plan ─────────────────────────────────────
    case 'listar_aparatologia_plan':
        $id_pp = intval($_GET['id_paciente_plan'] ?? 0);
        echo json_encode($modelo->listarAparatologiaPlan($id_pp));
        break;

    // ── Planes activos del paciente ───────────────────────────────
    case 'listar_planes_activos':
        $id_pac = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->listarPlanesActivos($id_pac));
        break;

    // ── Resumen de plan existente (para edición) ──────────────────
    case 'obtener_resumen_plan':
        $id_pp   = intval($_GET['id_paciente_plan'] ?? 0);
        $id_cita = intval($_GET['id_cita'] ?? 0);
        $resumen = $modelo->obtenerResumenPlan($id_pp, $id_cita);
        if (!$resumen) {
            echo json_encode(['success' => false, 'mensaje' => 'Plan no encontrado']);
        } else {
            echo json_encode(array_merge(['success' => true], $resumen));
        }
        break;

    // ── Catálogos ─────────────────────────────────────────────────
    case 'listar_servicios':
        echo json_encode($modelo->listarServicios());
        break;

    case 'listar_planes':
        echo json_encode($modelo->listarPlanesCatalogo());
        break;

    case 'listar_aparatologia':
        echo json_encode($modelo->listarAparatologia());
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
}