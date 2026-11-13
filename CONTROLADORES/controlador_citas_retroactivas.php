<?php
// Citas de fechas pasadas (historial). Acceso: sesion + modulo Citas + el permiso "Registrar citas de fechas pasadas"
// (por defecto solo el Administrador; se puede dar a una persona concreta en Configuracion > Permisos por usuario).
// Si incluye pagos, ademas exige los permisos de Cobros (registrar y registrar con fecha pasada).
require_once '../CONFIG/permisos.php';
requerirPermisoApi('citas');
require_once '../CONFIG/bitacora.php';
require_once '../MODELOS/modelo_citas_retroactivas.php';
require_once '../MODELOS/modelo_cobros.php';

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');

Permisos::requerir('citas.registrar_retroactiva');

$modelo     = new ModeloCitasRetroactivas();
$id_usuario = (int)($_SESSION['usuario']['id_usuario'] ?? 0);
$accion     = $_GET['accion'] ?? $_POST['accion'] ?? '';

switch ($accion) {

    case 'contexto':
        echo json_encode(['success' => true, 'catalogos' => $modelo->catalogos(),
                          'metodos' => (new ModeloCobros())->metodosPago(true),
                          'permisos_pagos' => ['registrar' => Permisos::puede('cobros.registrar'), 'retroactivo' => Permisos::puede('cobros.registrar_retroactivo')],
                          'hoy' => date('Y-m-d')]);
        break;

    case 'buscar_paciente':
        echo json_encode(['success' => true, 'pacientes' => $modelo->buscarPacientes($_GET['termino'] ?? '')]);
        break;

    case 'planes_paciente':
        echo json_encode(['success' => true, 'planes' => $modelo->planesDelPaciente(intval($_GET['id_paciente'] ?? 0))]);
        break;

    case 'listar_recientes':
        echo json_encode(['success' => true, 'citas' => $modelo->listarRecientes(15)]);
        break;

    case 'registrar':
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos)) { echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']); break; }
        if (!empty($datos['pagos'])) {                                   // registrar dinero exige los permisos de Cobros
            Permisos::requerir('cobros.registrar');
            Permisos::requerir('cobros.registrar_retroactivo');
        }
        $r = $modelo->registrar($datos, $id_usuario);
        if (!empty($r['success'])) {
            registrarBitacora('cita.retroactiva', 'cita', $r['id_cita'], intval($datos['id_paciente'] ?? 0),
                ['fecha' => $datos['fecha'] ?? '', 'estado' => intval($datos['id_estado_cita'] ?? 0), 'servicios' => $r['servicios'],
                 'total' => $r['total'], 'pagado' => $r['pagado'], 'informe' => $r['informe'], 'plan' => $r['plan']['id_paciente_plan'] ?? null]);
        }
        echo json_encode($r);
        break;

    case 'eliminar':
        $datos = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = intval($datos['id_cita'] ?? 0);
        $r = $id > 0 ? $modelo->eliminar($id) : ['success' => false, 'mensaje' => 'Cita no válida'];
        if (!empty($r['success'])) registrarBitacora('cita.retroactiva_eliminar', 'cita', $id, null, []);
        echo json_encode($r);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
