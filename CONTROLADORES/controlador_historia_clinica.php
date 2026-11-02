<?php
// Historia clinica. Solo con sesion iniciada y acceso al modulo; cada guardado ademas exige el permiso
// "Editar la historia clinica" (Configuracion -> Permisos por Rol), que se comprueba aqui en el servidor.
require_once '../CONFIG/permisos.php';
requerirPermisoApi('historia_clinica');
require_once '../MODELOS/modelo_historia_clinica.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$modelo    = new ModeloHistoriaClinica();
$idUsuario = (int)($_SESSION['usuario']['id_usuario'] ?? 0);

// Entrada: cuerpo JSON, formulario o parametros de la URL
$input = file_get_contents('php://input');
$data  = json_decode($input, true);
if (!is_array($data)) $data = [];
$accion = $data['accion'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';

function responder(array $payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function entero($valor) {
    return (is_numeric($valor) && (int)$valor > 0) ? (int)$valor : 0;
}

/**
 * Para guardar algo en una historia: el paciente se deduce de la historia (no se confia en el que
 * mande el navegador) y se exige el permiso de edicion sobre ESE paciente. Devuelve el id del paciente.
 */
function exigirEdicionDeHistoria($modelo, $idHistoria) {
    $idHistoria = entero($idHistoria);
    if (!$idHistoria) responder(['success' => false, 'mensaje' => 'ID de historia no proporcionado']);
    $idPaciente = $modelo->idPacienteDeHistoria($idHistoria);
    if (!$idPaciente) responder(['success' => false, 'mensaje' => 'La historia clínica no existe']);
    Permisos::requerir('historia_clinica.editar', $idPaciente);
    return $idPaciente;
}

function anotar($seccion, $idHistoria, $idPaciente) {
    // Solo queda constancia de QUIEN guardo QUE seccion y CUANDO; el contenido clinico no se copia a la bitacora
    registrarBitacora('historia.guardar_' . $seccion, 'historia', $idHistoria, $idPaciente);
}

try {
    switch ($accion) {

        // =====================================================
        // Lectura
        // =====================================================
        case 'buscar_paciente':
            $termino = $_GET['termino'] ?? '';
            if (mb_strlen($termino, 'UTF-8') < 2) responder([]);
            responder($modelo->buscarPacientes($termino));

        case 'cargar_historia':
            $idPaciente = entero($_GET['id_paciente'] ?? 0);
            if (!$idPaciente) responder(['success' => false, 'mensaje' => 'ID de paciente no proporcionado']);

            $paciente = $modelo->obtenerPacienteCompleto($idPaciente);
            if (!$paciente) responder(['success' => false, 'mensaje' => 'Paciente no encontrado']);

            $historia = $modelo->obtenerOCrearHistoriaClinica($idPaciente);
            registrarBitacora('historia.ver', 'historia', (int)$historia['id_historia'], $idPaciente);

            responder([
                'success'      => true,
                'paciente'     => $paciente,
                'historia'     => $historia,
                'estadisticas' => $modelo->obtenerEstadisticasPaciente($idPaciente),
                'secciones'    => $modelo->obtenerSeccionesHistoria($historia['id_historia']),
                // La pantalla se pone en solo lectura si no puede editar (el servidor lo vuelve a comprobar al guardar)
                'permisos'     => ['editar' => Permisos::puede('historia_clinica.editar', $idPaciente)],
            ]);

        case 'cargar_secciones':
            $idHistoria = entero($_GET['id_historia'] ?? 0);
            if (!$idHistoria) responder(['success' => false, 'mensaje' => 'ID de historia no proporcionado']);
            responder(['success' => true, 'secciones' => $modelo->obtenerSeccionesHistoria($idHistoria)]);

        case 'listar_alergias':
            $idPaciente = entero($_GET['id_paciente'] ?? 0);
            if (!$idPaciente) responder([]);
            responder($modelo->listarAlergiasPaciente($idPaciente));

        case 'buscar_medicamentos':
            $termino = $_GET['termino'] ?? '';
            if (mb_strlen($termino, 'UTF-8') < 2) responder([]);
            responder($modelo->buscarMedicamentos($termino));

        case 'obtener_estadisticas':
            $idPaciente = entero($_GET['id_paciente'] ?? 0);
            if (!$idPaciente) responder(['success' => false, 'mensaje' => 'ID de paciente no proporcionado']);
            responder(['success' => true, 'estadisticas' => $modelo->obtenerEstadisticasPaciente($idPaciente)]);

        // =====================================================
        // Escritura (todas exigen el permiso de edicion)
        // =====================================================
        case 'guardar_alergias':
            $idPaciente = entero($data['id_paciente'] ?? 0);
            if (!$idPaciente || !isset($data['alergias'])) responder(['success' => false, 'mensaje' => 'Datos incompletos']);
            if (!is_array($data['alergias'])) responder(['success' => false, 'mensaje' => 'Formato de datos invalido']);
            Permisos::requerir('historia_clinica.editar', $idPaciente);

            // Los medicamentos nuevos pasan al catalogo que ve todo el personal: solo letras, numeros y signos comunes
            foreach ($data['alergias'] as $med) {
                $med = trim((string)$med);
                if ($med === '') continue;
                if (!preg_match('/^[\p{L}\p{N} .,\-\/()+%]{2,100}$/u', $med)) {
                    responder(['success' => false, 'mensaje' => 'El nombre del medicamento contiene caracteres no permitidos o es muy largo (use letras, números y . , - / ( ) + %)']);
                }
            }

            $ok = $modelo->guardarAlergiasPaciente($idPaciente, $data['alergias']);
            if ($ok) registrarBitacora('historia.guardar_alergias', 'paciente', $idPaciente, $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Alergias actualizadas correctamente' : 'Error al actualizar las alergias']);

        case 'guardar_motivo_consulta':
            if (!isset($data['motivo_consulta'])) responder(['success' => false, 'mensaje' => 'Datos incompletos']);
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            $ok = $modelo->actualizarMotivoConsulta((int)$data['id_historia'], $data['motivo_consulta']);
            if ($ok) anotar('motivo_consulta', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Motivo de consulta guardado' : 'Error al guardar el motivo de consulta']);

        case 'guardar_antecedentes':
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            $ok = $modelo->guardarAntecedentes((int)$data['id_historia'], $data['medica'] ?? '', $data['odontologicos'] ?? '', $data['familiares'] ?? '', $idUsuario);
            if ($ok) anotar('antecedentes', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Antecedentes guardados correctamente' : 'Error al guardar antecedentes']);

        case 'guardar_examen_general':
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            $ok = $modelo->guardarExamenGeneral((int)$data['id_historia'], $data['talla_mts'] ?? '', $data['peso_kg'] ?? '', $data['temperatura'] ?? '', $data['saturacion'] ?? '', $idUsuario);
            if ($ok) anotar('examen_general', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Examen clínico general guardado correctamente' : 'Error al guardar el examen general']);

        case 'guardar_antecedentes_personales':
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            // Medica/Odontologicos siguen viviendo en historia_antecedentes
            $modelo->guardarAntecedentes((int)$data['id_historia'], $data['medica'] ?? '', $data['odontologicos'] ?? '', '', $idUsuario);
            $ok = $modelo->guardarAntecedentesPersonales((int)$data['id_historia'], $data, $idUsuario);
            if ($ok) anotar('antecedentes_personales', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Antecedentes personales guardados correctamente' : 'Error al guardar antecedentes personales']);

        case 'guardar_antecedentes_familiares':
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            $ok = $modelo->guardarAntecedentesFamiliares((int)$data['id_historia'], $data, $idUsuario);
            if ($ok) anotar('antecedentes_familiares', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Antecedentes familiares guardados correctamente' : 'Error al guardar antecedentes familiares']);

        case 'guardar_examen_extraoral':
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            $ok = $modelo->guardarExamenExtraoral((int)$data['id_historia'], $data, $idUsuario);
            if ($ok) anotar('examen_extraoral', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Examen extraoral guardado correctamente' : 'Error al guardar el examen extraoral']);

        case 'guardar_examen_intraoral':
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            $ok = $modelo->guardarExamenIntraoral((int)$data['id_historia'], $data, $idUsuario);
            if ($ok) anotar('examen_intraoral', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Examen intraoral guardado correctamente' : 'Error al guardar el examen intraoral']);

        case 'actualizar_notas':
            if (!isset($data['notas'])) responder(['success' => false, 'mensaje' => 'Datos incompletos']);
            $idPaciente = exigirEdicionDeHistoria($modelo, $data['id_historia'] ?? 0);
            $ok = $modelo->actualizarNotasHistoria((int)$data['id_historia'], $data['notas']);
            if ($ok) anotar('notas', (int)$data['id_historia'], $idPaciente);
            responder(['success' => (bool)$ok, 'mensaje' => $ok ? 'Notas actualizadas correctamente' : 'Error al actualizar las notas']);

        default:
            responder(['success' => false, 'mensaje' => 'Acción no válida']);
    }

} catch (Throwable $e) {
    error_log('controlador_historia_clinica: ' . $e->getMessage());
    http_response_code(500);
    responder(['success' => false, 'mensaje' => 'Ocurrió un error inesperado. Intenta nuevamente.']);
}
