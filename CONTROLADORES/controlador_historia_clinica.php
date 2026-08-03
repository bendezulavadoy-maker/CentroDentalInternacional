<?php
require_once '../MODELOS/modelo_historia_clinica.php';

header('Content-Type: application/json; charset=utf-8');

$modelo = new ModeloHistoriaClinica();

// Obtener la acción
$accion = '';

// Verificar si es POST con JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data && isset($data['accion'])) {
    $accion = $data['accion'];
} else {
    $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
}

// =====================================================
// 📋 PROCESAMIENTO DE ACCIONES
// =====================================================

try {
    switch ($accion) {

        // 🔹 Buscar pacientes
        case 'buscar_paciente':
            $termino = $_GET['termino'] ?? '';
            
            if (strlen($termino) < 2) {
                echo json_encode([]);
                exit;
            }

            $pacientes = $modelo->buscarPacientes($termino);
            echo json_encode($pacientes);
            break;

        // 🔹 Cargar historia clínica completa
        case 'cargar_historia':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de paciente no proporcionado'
                ]);
                exit;
            }

            // Obtener información del paciente
            $paciente = $modelo->obtenerPacienteCompleto($idPaciente);
            
            if (!$paciente) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Paciente no encontrado'
                ]);
                exit;
            }

            // Obtener o crear historia clínica
            $historia = $modelo->obtenerOCrearHistoriaClinica($idPaciente);
            
            // Obtener estadísticas
            $estadisticas = $modelo->obtenerEstadisticasPaciente($idPaciente);

            echo json_encode([
                'success' => true,
                'paciente' => $paciente,
                'historia' => $historia,
                'estadisticas' => $estadisticas
            ]);
            break;

        // 🔹 Listar alergias del paciente
        case 'listar_alergias':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                echo json_encode([]);
                exit;
            }

            $alergias = $modelo->listarAlergiasPaciente($idPaciente);
            echo json_encode($alergias);
            break;

        // 🔹 NUEVO: Buscar medicamentos
        case 'buscar_medicamentos':
            $termino = $_GET['termino'] ?? '';
            
            if (strlen($termino) < 2) {
                echo json_encode([]);
                exit;
            }

            $medicamentos = $modelo->buscarMedicamentos($termino);
            echo json_encode($medicamentos);
            break;

        // 🔹 Guardar alergias del paciente
        case 'guardar_alergias':
            if (!isset($data['id_paciente']) || !isset($data['alergias'])) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Datos incompletos'
                ]);
                exit;
            }

            $idPaciente = $data['id_paciente'];
            $medicamentos = $data['alergias'];

            // Validar que medicamentos sea un array
            if (!is_array($medicamentos)) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Formato de datos inválido'
                ]);
                exit;
            }

            $resultado = $modelo->guardarAlergiasPaciente($idPaciente, $medicamentos);
            
            if ($resultado) {
                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Alergias actualizadas correctamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Error al actualizar las alergias'
                ]);
            }
            break;

        // 🔹 Obtener estadísticas del paciente
        case 'obtener_estadisticas':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de paciente no proporcionado'
                ]);
                exit;
            }

            $estadisticas = $modelo->obtenerEstadisticasPaciente($idPaciente);
            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
            break;

        // 🔹 Actualizar notas de historia clínica
        case 'actualizar_notas':
            if (!isset($data['id_historia']) || !isset($data['notas'])) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Datos incompletos'
                ]);
                exit;
            }

            $idHistoria = $data['id_historia'];
            $notas = $data['notas'];

            $resultado = $modelo->actualizarNotasHistoria($idHistoria, $notas);
            
            if ($resultado) {
                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Notas actualizadas correctamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Error al actualizar las notas'
                ]);
            }
            break;

        // 🔹 Acción no válida
        default:
            echo json_encode([
                'success' => false,
                'mensaje' => 'Acción no válida: ' . $accion
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("❌ Error en controlador_historia_clinica: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>