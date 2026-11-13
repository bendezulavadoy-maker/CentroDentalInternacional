<?php
// Ficha del plan de tratamiento. Ver: cualquier usuario con sesion (igual que el resto de Atenciones).
// Editar: permisos "planes.editar" (notas, estado, fecha) y "planes.editar_costos" (dinero). Todo cambio queda en la bitacora.
require_once '../CONFIG/permisos.php';
require_once '../CONFIG/bitacora.php';
require_once '../MODELOS/modelo_planes.php';
if (session_status() === PHP_SESSION_NONE) session_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
    exit;
}

$modelo = new ModeloPlanes();
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

switch ($accion) {

    case 'listar':
        $idPac = intval($_GET['id_paciente'] ?? 0);
        echo json_encode(['success' => true, 'planes' => $modelo->listar($idPac, ($_GET['todos'] ?? '') === '1'),
                          'permisos' => ['editar' => Permisos::puede('planes.editar', $idPac), 'editar_costos' => Permisos::puede('planes.editar_costos')]]);
        break;

    case 'detalle':
        $d = $modelo->detalle(intval($_GET['id_paciente_plan'] ?? 0));
        echo json_encode($d ? ['success' => true, 'plan' => $d] : ['success' => false, 'mensaje' => 'El plan no existe']);
        break;

    case 'detalle_cita':
        $d = $modelo->detalleCita(intval($_GET['id_cita'] ?? 0));
        echo json_encode($d ? ['success' => true] + $d : ['success' => false, 'mensaje' => 'La cita no existe']);
        break;

    case 'editar':
        // { id_paciente_plan, cambios: {campo: valor}, motivo, recalcular }
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos) || !isset($datos['cambios']) || !is_array($datos['cambios']) || !$datos['cambios']) { echo json_encode(['success' => false, 'mensaje' => 'No hay cambios para guardar']); break; }
        try { $prep = $modelo->prepararEdicion(intval($datos['id_paciente_plan'] ?? 0), $datos['cambios']); }
        catch (DomainException $e) { echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]); break; }
        $idPac = (int)$prep['plan']['id_paciente'];
        if ($prep['generales'])   Permisos::requerir('planes.editar', $idPac);       // solo exige el permiso de lo que REALMENTE cambia
        if ($prep['financieros']) Permisos::requerir('planes.editar_costos');
        $r = $modelo->aplicarEdicion($prep, $datos['motivo'] ?? '', !isset($datos['recalcular']) || !empty($datos['recalcular']));
        if (!empty($r['success'])) {
            if ($r['cambios'] > 0) registrarBitacora('plan.editar', 'plan', (int)$prep['plan']['id_paciente_plan'], $idPac,
                ['cambios' => $prep['diff'], 'motivo' => trim((string)($datos['motivo'] ?? '')), 'sesiones_recalculadas' => $r['sesiones_recalculadas'] ?? 0]);
            $r['mensaje'] = $r['cambios'] === 0 ? 'No había cambios que guardar' : 'Plan actualizado correctamente';
        }
        echo json_encode($r);
        break;

    case 'previsualizar_recalculo':
        Permisos::requerir('planes.editar_costos');
        $pre = $modelo->previsualizarRecalculo(intval($_GET['id_paciente_plan'] ?? 0));
        echo json_encode($pre ? ['success' => true] + $pre : ['success' => false, 'mensaje' => 'El plan no existe']);
        break;

    case 'aplicar_recalculo':
        Permisos::requerir('planes.editar_costos');
        $datos = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $r = $modelo->aplicarRecalculo(intval($datos['id_paciente_plan'] ?? 0));
        if (!empty($r['success'])) {
            registrarBitacora('plan.recalcular_costos', 'plan', intval($datos['id_paciente_plan']), $r['id_paciente'], ['corregidas' => $r['corregidas']]);
            $n = count($r['corregidas']); $r['mensaje'] = $n ? ($n === 1 ? 'Se corrigió el costo de 1 sesión' : "Se corrigió el costo de $n sesiones") : 'No había nada que corregir';
        }
        echo json_encode($r);
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
