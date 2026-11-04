<?php
// Cobros. Acceso: sesion + modulo "Cobros" (Configuracion > Permisos por Rol). Antes el acceso estaba fijo a los
// roles 1 y 2, numeracion que ya no coincide (el 2 es Odontologo y la Asistente, rol 3, quedaba fuera).
// Acciones finas: cobros.registrar, cobros.anular, cobros.registrar_retroactivo.
require_once '../CONFIG/permisos.php';
requerirPermisoApi('cobros');
require_once '../CONFIG/bitacora.php';
require_once '../MODELOS/modelo_cobros.php';

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$modelo     = new ModeloCobros();
$id_usuario = (int)($_SESSION['usuario']['id_usuario'] ?? 0);
$accion     = $_GET['accion'] ?? $_POST['accion'] ?? '';

switch ($accion) {

    // Lo que la pantalla necesita para dibujarse segun quien la usa
    case 'contexto':
        echo json_encode([
            'success'  => true,
            'metodos'  => $modelo->metodosPago(true),
            'permisos' => [
                'registrar'    => Permisos::puede('cobros.registrar'),
                'anular'       => Permisos::puede('cobros.anular'),
                'retroactivo'  => Permisos::puede('cobros.registrar_retroactivo'),
            ],
        ]);
        break;

    case 'metodos_pago':
        echo json_encode(['success' => true, 'metodos' => $modelo->metodosPago(true)]);
        break;

    case 'listar_buzon':
        $resultado = $modelo->listarBuzon();
        echo json_encode($resultado === false ? ['error' => 'Query failed - check error_log'] : $resultado);
        break;

    case 'detalle_cobro':
        $id_paciente = intval($_GET['id_paciente'] ?? 0);
        $detalle = $modelo->detalleCobro($id_paciente);
        if (is_array($detalle)) {
            $detalle['saldo_a_favor']      = $modelo->saldoAFavor($id_paciente);
            $detalle['cuotas_pendientes']  = $modelo->cuotasPendientes($id_paciente);
        }
        echo json_encode($detalle);
        break;

    case 'resumen_saldos':
        echo json_encode($modelo->resumenSaldos(intval($_GET['id_paciente'] ?? 0)));
        break;

    case 'historial_pagos':
        $id_paciente = intval($_GET['id_paciente'] ?? 0);
        if ($id_paciente <= 0) { echo json_encode(['success' => false, 'mensaje' => 'Paciente no válido']); break; }
        echo json_encode($modelo->historialPagos($id_paciente));
        break;

    case 'registrar_pagos':
        Permisos::requerir('cobros.registrar');
        $data = json_decode(file_get_contents('php://input'), true);
        $id_paciente = intval($data['id_paciente'] ?? 0);
        $pagos       = $data['pagos'] ?? [];
        $anticipo    = $data['anticipo'] ?? 0;
        if ($id_paciente <= 0 || (empty($pagos) && !(is_numeric($anticipo) && $anticipo > 0))) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos']);
            break;
        }
        $fecha = trim((string)($data['fecha_pago'] ?? ''));
        if ($fecha !== '' && $fecha < date('Y-m-d')) {
            Permisos::requerir('cobros.registrar_retroactivo');          // pagos con fecha pasada: permiso aparte
        }
        $r = $modelo->registrarPagos($pagos, $id_paciente, $id_usuario, [
            'id_metodo_pago'  => $data['id_metodo_pago']  ?? 0,
            'nro_comprobante' => $data['nro_comprobante'] ?? '',
            'fecha_pago'      => $fecha,
            'usar_saldo'      => !empty($data['usar_saldo']),
            'anticipo'        => $anticipo,
        ]);
        if (!empty($r['success'])) {
            registrarBitacora('cobro.registrar', 'operacion', $r['id_operacion'], $id_paciente,
                ['aplicado' => $r['aplicado'], 'anticipo' => $r['anticipo'], 'metodo' => $r['metodo'], 'origen' => $r['origen']]);
        }
        echo json_encode($r);
        break;

    case 'anular_pago':
        Permisos::requerir('cobros.anular');
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $r = $modelo->anularOperacion(intval($data['id_operacion'] ?? 0), $data['motivo'] ?? '', $id_usuario);
        if (!empty($r['success'])) {
            registrarBitacora('cobro.anular', 'operacion', intval($data['id_operacion']), $r['id_paciente'],
                ['monto' => $r['monto'], 'lineas' => $r['lineas'], 'motivo' => trim((string)$data['motivo'])]);
        }
        echo json_encode($r);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
