<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../MODELOS/modelo_historia_clinica.php';

header('Content-Type: application/json; charset=utf-8');

$modelo = new ModeloHistoriaClinica();
$idUsuario = $_SESSION['usuario']['id_usuario'] ?? null;

// Obtener la accion
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
// PROCESAMIENTO DE ACCIONES
// =====================================================

try {
    switch ($accion) {

        // Buscar pacientes
        case 'buscar_paciente':
            $termino = $_GET['termino'] ?? '';
            
            if (strlen($termino) < 2) {
                echo json_encode([]);
                exit;
            }

            $pacientes = $modelo->buscarPacientes($termino);
            echo json_encode($pacientes);
            break;

        // Cargar historia clinica completa
        case 'cargar_historia':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de paciente no proporcionado'
                ]);
                exit;
            }

            // Obtener informacion del paciente
            $paciente = $modelo->obtenerPacienteCompleto($idPaciente);
            
            if (!$paciente) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Paciente no encontrado'
                ]);
                exit;
            }

            // Obtener o crear historia clinica
            $historia = $modelo->obtenerOCrearHistoriaClinica($idPaciente);
            
            // Obtener estadisticas
            $estadisticas = $modelo->obtenerEstadisticasPaciente($idPaciente);

            // Checklist de secciones (antecedentes, examenes) para el sidebar
            $secciones = $modelo->obtenerSeccionesHistoria($historia['id_historia']);

            echo json_encode([
                'success' => true,
                'paciente' => $paciente,
                'historia' => $historia,
                'estadisticas' => $estadisticas,
                'secciones' => $secciones
            ]);
            break;

        // Cargar solo el checklist de secciones (para refrescar el sidebar sin recargar todo)
        case 'cargar_secciones':
            $idHistoria = $_GET['id_historia'] ?? 0;

            if (!$idHistoria) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de historia no proporcionado'
                ]);
                exit;
            }

            $secciones = $modelo->obtenerSeccionesHistoria($idHistoria);
            echo json_encode([
                'success' => true,
                'secciones' => $secciones
            ]);
            break;

        // Listar alergias del paciente
        case 'listar_alergias':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                echo json_encode([]);
                exit;
            }

            $alergias = $modelo->listarAlergiasPaciente($idPaciente);
            echo json_encode($alergias);
            break;

        // Buscar medicamentos
        case 'buscar_medicamentos':
            $termino = $_GET['termino'] ?? '';
            
            if (strlen($termino) < 2) {
                echo json_encode([]);
                exit;
            }

            $medicamentos = $modelo->buscarMedicamentos($termino);
            echo json_encode($medicamentos);
            break;

        // Guardar alergias del paciente
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
                    'mensaje' => 'Formato de datos invalido'
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

        // Obtener estadisticas del paciente
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

        // Guardar motivo de consulta
        case 'guardar_motivo_consulta':
            if (!isset($data['id_historia']) || !isset($data['motivo_consulta'])) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'Datos incompletos'
                ]);
                exit;
            }

            $resultado = $modelo->actualizarMotivoConsulta($data['id_historia'], $data['motivo_consulta']);

            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Motivo de consulta guardado' : 'Error al guardar el motivo de consulta'
            ]);
            break;

        // Guardar Antecedentes
        case 'guardar_antecedentes':
            if (!isset($data['id_historia'])) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de historia no proporcionado'
                ]);
                exit;
            }

            $resultado = $modelo->guardarAntecedentes(
                $data['id_historia'],
                $data['medica'] ?? '',
                $data['odontologicos'] ?? '',
                $data['familiares'] ?? '',
                $idUsuario
            );

            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Antecedentes guardados correctamente' : 'Error al guardar antecedentes'
            ]);
            break;

        // Guardar Examen Clinico General
        case 'guardar_examen_general':
            if (!isset($data['id_historia'])) {
                echo json_encode(['success' => false, 'mensaje' => 'ID de historia no proporcionado']);
                exit;
            }
            $resultado = $modelo->guardarExamenGeneral(
                $data['id_historia'],
                $data['talla_mts'] ?? '',
                $data['peso_kg'] ?? '',
                $data['temperatura'] ?? '',
                $data['saturacion'] ?? '',
                $idUsuario
            );
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Examen clinico general guardado correctamente' : 'Error al guardar el examen general'
            ]);
            break;

        case 'guardar_antecedentes_personales':
            if (!isset($data['id_historia'])) {
                echo json_encode(['success' => false, 'mensaje' => 'ID de historia no proporcionado']);
                exit;
            }
            // Medica/Odontologicos siguen viviendo en historia_antecedentes
            $modelo->guardarAntecedentes(
                $data['id_historia'],
                $data['medica'] ?? '',
                $data['odontologicos'] ?? '',
                '',
                $idUsuario
            );
            $resultado = $modelo->guardarAntecedentesPersonales($data['id_historia'], $data, $idUsuario);
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Antecedentes personales guardados correctamente' : 'Error al guardar antecedentes personales'
            ]);
            break;

        case 'guardar_antecedentes_familiares':
            if (!isset($data['id_historia'])) {
                echo json_encode(['success' => false, 'mensaje' => 'ID de historia no proporcionado']);
                exit;
            }
            $resultado = $modelo->guardarAntecedentesFamiliares($data['id_historia'], $data, $idUsuario);
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Antecedentes familiares guardados correctamente' : 'Error al guardar antecedentes familiares'
            ]);
            break;

        // Guardar Examen Extraoral
        case 'guardar_examen_extraoral':
            if (!isset($data['id_historia'])) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de historia no proporcionado'
                ]);
                exit;
            }

            $resultado = $modelo->guardarExamenExtraoral($data['id_historia'], $data, $idUsuario);

            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Examen extraoral guardado correctamente' : 'Error al guardar el examen extraoral'
            ]);
            break;

        // Guardar Examen Intraoral
        case 'guardar_examen_intraoral':
            if (!isset($data['id_historia'])) {
                echo json_encode([
                    'success' => false,
                    'mensaje' => 'ID de historia no proporcionado'
                ]);
                exit;
            }

            $resultado = $modelo->guardarExamenIntraoral($data['id_historia'], $data, $idUsuario);

            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Examen intraoral guardado correctamente' : 'Error al guardar el examen intraoral'
            ]);
            break;

        // Actualizar notas de historia clinica
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

        // Accion no valida
        default:
            echo json_encode([
                'success' => false,
                'mensaje' => 'Accion no valida: ' . $accion
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("Error en controlador_historia_clinica: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>