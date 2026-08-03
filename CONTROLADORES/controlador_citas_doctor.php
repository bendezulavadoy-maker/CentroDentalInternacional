<?php
// controlador_citas_doctor.php - VERSIÓN CORREGIDA CON RESPUESTA ACCIÓN
require_once '../MODELOS/modelo_citas_doctor.php';

header('Content-Type: application/json; charset=utf-8');

$modelo = new ModeloCitasDoctor();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

error_log("🔵 === CONTROLADOR CITAS DOCTOR ===");
error_log("📋 Acción: " . $accion);

try {
    switch ($accion) {

        // =====================================================
        // 📋 LISTAR CITAS
        // =====================================================
        case 'listar_citas':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            $idDoctor = $_GET['id_doctor'] ?? 0;

            if (!$idPaciente || !$idDoctor) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Parámetros incompletos'
                ]);
                exit;
            }

            $citas = $modelo->listarCitasPacienteDoctor($idPaciente, $idDoctor);
            echo json_encode($citas);
            break;

        // =====================================================
        // ▶️ INICIAR ATENCIÓN
        // =====================================================
        case 'iniciar_atencion':
            $idCita = $_POST['id_cita'] ?? 0;

            if (!$idCita) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de cita no proporcionado'
                ]);
                exit;
            }

            try {
                $resultado = $modelo->iniciarAtencion($idCita);
                
                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Atención iniciada correctamente'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => $e->getMessage()
                ]);
            }
            break;

        // =====================================================
        // ⏹️ TERMINAR ATENCIÓN
        // =====================================================
        case 'terminar_atencion':
            $idCita = $_POST['id_cita'] ?? 0;

            if (!$idCita) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de cita no proporcionado'
                ]);
                exit;
            }

            try {
                $resultado = $modelo->terminarAtencion($idCita);
                
                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Atención terminada correctamente'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => $e->getMessage()
                ]);
            }
            break;

        // =====================================================
        // 💾 GUARDAR ATENCIÓN
        // =====================================================
        case 'guardar_atencion':
            if (!isset($_POST['id_cita']) || 
                !isset($_POST['id_paciente']) || 
                !isset($_POST['tratamiento']) || 
                !isset($_POST['precio_unitario'])) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Campos obligatorios incompletos'
                ]);
                exit;
            }

            $datos = [
                'id_cita' => $_POST['id_cita'],
                'tiempo_atencion' => $_POST['tiempo_atencion'] ?? 0,
                'tratamiento' => trim($_POST['tratamiento']),
                'precio_unitario' => floatval($_POST['precio_unitario']),
                'total' => floatval($_POST['total'] ?? $_POST['precio_unitario']),
                'a_cuenta' => floatval($_POST['a_cuenta'] ?? 0),
                'resta' => floatval($_POST['resta'] ?? 0)
            ];

            try {
                // ✅ El modelo ahora retorna ['success' => true, 'accion' => 'insertar|actualizar']
                $resultado = $modelo->guardarAtencion($datos);
                
                echo json_encode([
                    'success' => $resultado['success'],
                    'accion' => $resultado['accion'], // 'insertar' o 'actualizar'
                    'mensaje' => $resultado['accion'] === 'actualizar' 
                        ? 'Atención actualizada correctamente' 
                        : 'Atención registrada correctamente'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => $e->getMessage()
                ]);
            }
            break;

        // =====================================================
        // 👁️ VER DETALLE CITA
        // =====================================================
        case 'ver_detalle':
            $idCita = $_GET['id_cita'] ?? 0;

            if (!$idCita) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de cita no proporcionado'
                ]);
                exit;
            }

            $cita = $modelo->verDetalleCita($idCita);

            if ($cita) {
                echo json_encode($cita);
            } else {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Cita no encontrada'
                ]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'mensaje' => 'Acción no válida: ' . $accion
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("❌ Error general en controlador: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>