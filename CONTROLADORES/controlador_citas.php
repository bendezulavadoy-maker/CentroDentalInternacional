<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

error_log("🔵 === CONTROLADOR CITAS INICIADO ===");

require_once '../MODELOS/modelo_citas.php';
$modelo = new ModeloCitas();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
error_log("📋 Acción: " . $accion);

// =====================================================
// 🔍 VALIDACIONES
// =====================================================
function validarFecha($fecha) {
    if (empty($fecha)) return ['valido' => false, 'mensaje' => 'Fecha obligatoria'];
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaObj) return ['valido' => false, 'mensaje' => 'Formato de fecha inválido (use YYYY-MM-DD)'];
    return ['valido' => true];
}

function validarFechaNoAnteriorHoy($fecha) {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
    $hoy = new DateTime(); $hoy->setTime(0,0,0);
    if ($fechaObj < $hoy) return ['valido' => false, 'mensaje' => 'No se permiten fechas pasadas'];
    return ['valido' => true];
}

function validarHora($hora) {
    if (empty($hora)) return ['valido' => false, 'mensaje' => 'Hora obligatoria'];
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $hora)) {
        return ['valido' => false, 'mensaje' => 'Formato de hora inválido'];
    }
    return ['valido' => true];
}

// Campos requeridos para crear/editar (tipo_servicio es OPCIONAL)
function validarCamposObligatorios($datos) {
    $campos = [
        'id_paciente'    => 'Paciente',
        'id_doctor'      => 'Doctor',
        'fecha'          => 'Fecha',
        'hora'           => 'Hora',
        'id_estado_cita' => 'Estado',
        'id_sede_atencion' => 'Sede',
        'motivo'         => 'Motivo'
    ];
    foreach ($campos as $campo => $nombre) {
        if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
            return ['valido' => false, 'mensaje' => "$nombre es obligatorio"];
        }
    }
    return ['valido' => true];
}

// =====================================================
// 📋 PROCESAMIENTO
// =====================================================
switch ($accion) {

    // 🆕 Registrar
    case 'registrar':
        error_log("🆕 === REGISTRAR ===");
        
        $validacion = validarCamposObligatorios($_POST);
        if (!$validacion['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacion['mensaje']]);
            exit;
        }

        $validacionFecha = validarFecha($_POST['fecha']);
        if (!$validacionFecha['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionFecha['mensaje']]);
            exit;
        }
        
        $validacionFechaHoy = validarFechaNoAnteriorHoy($_POST['fecha']);
        if (!$validacionFechaHoy['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionFechaHoy['mensaje']]);
            exit;
        }

        $validacionHora = validarHora($_POST['hora']);
        if (!$validacionHora['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionHora['mensaje']]);
            exit;
        }

        // Validar disponibilidad del slot en el servidor (segunda línea de defensa)
        $duracion = intval($_POST['duracion_minutos'] ?? 30);
        $validSlot = $modelo->validarSlotDisponible(
            intval($_POST['id_doctor']),
            intval($_POST['id_sede_atencion']),
            $_POST['fecha'],
            $_POST['hora'],
            $duracion,
            0
        );
        if (!$validSlot['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validSlot['mensaje']]);
            exit;
        }

        $alergias = $_POST['alergias_medicamentos'] ?? [];
        if (is_string($alergias) && !empty($alergias)) {
            $alergias = explode(',', $alergias);
        }

        $datos = [
            'id_paciente'      => intval($_POST['id_paciente']),
            'id_doctor'        => intval($_POST['id_doctor']),
            'fecha'            => $_POST['fecha'],
            'hora'             => $_POST['hora'],
            'id_estado_cita'   => intval($_POST['id_estado_cita']),
            'id_tipo_servicio' => !empty($_POST['id_tipo_servicio']) ? intval($_POST['id_tipo_servicio']) : null,
            'id_tipo_atencion' => !empty($_POST['id_tipo_atencion']) ? intval($_POST['id_tipo_atencion']) : null,
            'duracion_minutos' => $duracion,
            'id_sede_atencion' => intval($_POST['id_sede_atencion']),
            'id_paciente_plan' => !empty($_POST['id_paciente_plan']) ? intval($_POST['id_paciente_plan']) : null,
            'motivo'           => trim($_POST['motivo']),
            'alergias_medicamentos' => $alergias
        ];

        $resultado = $modelo->registrarCita($datos);
        
        if ($resultado && isset($resultado['success']) && $resultado['success']) {
            $cita = $modelo->verCita($resultado['id_cita']);

            // Google Calendar — crear evento
            try {
                require_once '../SERVICIOS/GoogleCalendarService.php';
                $gcal = new GoogleCalendarService((new Conexion())->getConexion());
                if ($cita && $gcal->doctorConectado(intval($datos['id_doctor']))) {
                    $datosCal = [
                        'fecha'            => $cita['fecha'],
                        'hora'             => $cita['hora'],
                        'duracion_minutos' => $cita['duracion_minutos'] ?? 30,
                        'nombre_paciente'  => trim(($cita['paciente_nombre'] ?? '') . ' ' . ($cita['paciente_apellido'] ?? '')),
                        'tipo_atencion'    => $cita['tipo_atencion'] ?? '',
                        'nombre_sede'      => $cita['sede'] ?? '',
                        'motivo'           => $cita['motivo'] ?? '',
                    ];
                    $eventId = $gcal->crearEvento(intval($datos['id_doctor']), $datosCal);
                    if ($eventId) {
                        // Guardar el google_event_id en la cita
                        $stmtUpd = (new Conexion())->getConexion()->prepare(
                            "UPDATE citas SET google_event_id = :eid WHERE id_cita = :id"
                        );
                        $stmtUpd->execute([':eid' => $eventId, ':id' => $resultado['id_cita']]);
                    }
                }
            } catch (Exception $e) {
                error_log("Google Calendar crearEvento error: " . $e->getMessage());
            }

            // WhatsApp — confirmación
            try {
                require_once '../SERVICIOS/WhatsAppService.php';
                if ($cita) {
                    $wa = new WhatsAppService();
                    $datosCita = [
                        'fecha'            => $cita['fecha'],
                        'hora'             => $cita['hora'],
                        'nombre_paciente'  => trim(($cita['paciente_nombre'] ?? '') . ' ' . ($cita['paciente_apellido'] ?? '')),
                        'nombre_doctor'    => trim(($cita['doctor_nombre'] ?? '') . ' ' . ($cita['doctor_apellidos'] ?? '')),
                        'nombre_sede'      => $cita['sede'] ?? '',
                        'telefono_paciente'=> $cita['telefono_paciente'] ?? '',
                    ];
                    if (!empty($datosCita['telefono_paciente'])) {
                        $wa->citaConfirmada($datosCita);
                    }
                }
            } catch (Exception $e) {
                error_log("WhatsApp citaConfirmada error: " . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'id_cita' => $resultado['id_cita'],
                'mensaje' => 'Cita registrada correctamente'
            ]);
        } else {
            $msg = isset($resultado['mensaje']) ? $resultado['mensaje'] : 'Error al registrar la cita';
            echo json_encode(['success' => false, 'mensaje' => $msg]);
        }
        break;

    // 🔄 Editar
    case 'editar':
        error_log("🔄 === EDITAR ===");
        
        $id_cita = intval($_POST['id_cita'] ?? 0);
        if (!$id_cita) {
            echo json_encode(['success' => false, 'mensaje' => 'ID de cita inválido']);
            exit;
        }

        $validacion = validarCamposObligatorios($_POST);
        if (!$validacion['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacion['mensaje']]);
            exit;
        }

        $validacionFecha = validarFecha($_POST['fecha']);
        if (!$validacionFecha['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionFecha['mensaje']]);
            exit;
        }

        $validacionHora = validarHora($_POST['hora']);
        if (!$validacionHora['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionHora['mensaje']]);
            exit;
        }

        $duracion = intval($_POST['duracion_minutos'] ?? 30);

        // Solo validar slot si la fecha/hora cambiaron (no aplica a reversiones de estado)
        $nuevoEstado = intval($_POST['id_estado_cita'] ?? 0);
        $esReversionEstado = in_array($nuevoEstado, [1]) && isset($_POST['solo_estado']);
        if (!$esReversionEstado) {
            $validSlot = $modelo->validarSlotDisponible(
                intval($_POST['id_doctor']),
                intval($_POST['id_sede_atencion']),
                $_POST['fecha'],
                $_POST['hora'],
                $duracion,
                $id_cita
            );
            if (!$validSlot['valido']) {
                echo json_encode(['success' => false, 'mensaje' => $validSlot['mensaje']]);
                exit;
            }
        }

        $alergias = $_POST['alergias_medicamentos'] ?? [];
        if (is_string($alergias) && !empty($alergias)) {
            $alergias = explode(',', $alergias);
        }

        $datos = [
            'id_paciente'      => intval($_POST['id_paciente']),
            'id_doctor'        => intval($_POST['id_doctor']),
            'fecha'            => $_POST['fecha'],
            'hora'             => $_POST['hora'],
            'id_estado_cita'   => $nuevoEstado,
            'id_tipo_servicio' => !empty($_POST['id_tipo_servicio']) ? intval($_POST['id_tipo_servicio']) : null,
            'id_tipo_atencion' => !empty($_POST['id_tipo_atencion']) ? intval($_POST['id_tipo_atencion']) : null,
            'duracion_minutos' => $duracion,
            'id_sede_atencion' => intval($_POST['id_sede_atencion']),
            'id_paciente_plan' => !empty($_POST['id_paciente_plan']) ? intval($_POST['id_paciente_plan']) : null,
            'motivo'           => trim($_POST['motivo']),
            'alergias_medicamentos' => $alergias
        ];

        $resultado = $modelo->editarCita($id_cita, $datos);

        $exito = is_array($resultado) ? ($resultado['success'] ?? false) : (bool)$resultado;

        if ($exito) {
            // Google Calendar — actualizar evento
            try {
                require_once '../SERVICIOS/GoogleCalendarService.php';
                $cita = $modelo->verCita($id_cita);
                if ($cita && $cita['google_event_id'] ?? null) {
                    $gcal = new GoogleCalendarService((new Conexion())->getConexion());
                    if ($gcal->doctorConectado(intval($datos['id_doctor']))) {
                        $datosCal = [
                            'fecha'            => $cita['fecha'],
                            'hora'             => $cita['hora'],
                            'duracion_minutos' => $cita['duracion_minutos'] ?? 30,
                            'nombre_paciente'  => trim(($cita['paciente_nombre'] ?? '') . ' ' . ($cita['paciente_apellido'] ?? '')),
                            'tipo_atencion'    => $cita['tipo_atencion'] ?? '',
                            'nombre_sede'      => $cita['sede'] ?? '',
                            'motivo'           => $cita['motivo'] ?? '',
                        ];
                        // Si la cita se canceló, eliminar el evento
                        if (intval($datos['id_estado_cita']) === 3) {
                            $gcal->eliminarEvento(intval($datos['id_doctor']), $cita['google_event_id']);
                        } else {
                            $gcal->actualizarEvento(intval($datos['id_doctor']), $cita['google_event_id'], $datosCal);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Google Calendar actualizarEvento error: " . $e->getMessage());
            }
        }

        if (is_array($resultado)) {
            echo json_encode($resultado['success']
                ? ['success' => true, 'mensaje' => 'Cita actualizada correctamente']
                : $resultado
            );
        } elseif ($resultado) {
            echo json_encode(['success' => true, 'mensaje' => 'Cita actualizada correctamente']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al actualizar la cita']);
        }
        break;

    // 🗑️ Eliminar
    case 'eliminar':
        $id = intval($_POST['id_cita'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'mensaje' => 'ID inválido']);
            exit;
        }
        // Obtener google_event_id e id_doctor antes de eliminar
        try {
            $stmtGcal = (new Conexion())->getConexion()->prepare(
                "SELECT google_event_id, id_doctor FROM citas WHERE id_cita = :id"
            );
            $stmtGcal->execute([':id' => $id]);
            $citaGcal = $stmtGcal->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $citaGcal = null; }

        $resultado = $modelo->eliminarCita($id);

        // Google Calendar — eliminar evento si existía
        if (($resultado['success'] ?? false) && $citaGcal && !empty($citaGcal['google_event_id'])) {
            try {
                require_once '../SERVICIOS/GoogleCalendarService.php';
                $gcal = new GoogleCalendarService((new Conexion())->getConexion());
                $gcal->eliminarEvento(intval($citaGcal['id_doctor']), $citaGcal['google_event_id']);
            } catch (Exception $e) {
                error_log("Google Calendar eliminarEvento error: " . $e->getMessage());
            }
        }

        echo json_encode($resultado);
        break;

    // 📋 Listar
    case 'listar':
        echo json_encode($modelo->listarCitas());
        break;

    // 👁️ Ver
    case 'ver':
        $id = intval($_GET['id'] ?? 0);
        $cita = $modelo->verCita($id);
        if ($cita) {
            echo json_encode($cita);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Cita no encontrada']);
        }
        break;

    // 💊 Alergias paciente
    case 'obtener_alergias_paciente':
        $id = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->obtenerAlergiasPaciente($id));
        break;

    // 🔍 Autocompletar
    case 'autocompletar_paciente':
        $termino = trim($_GET['termino'] ?? '');
        if (strlen($termino) < 2) { echo json_encode([]); exit; }
        echo json_encode($modelo->buscarPacientes($termino));
        break;

    case 'autocompletar_doctor':
        $termino = trim($_GET['termino'] ?? '');
        if (strlen($termino) < 2) { echo json_encode([]); exit; }
        echo json_encode($modelo->buscarDoctores($termino));
        break;

    // 📋 Catálogos
    case 'listar_estados_cita':
        echo json_encode($modelo->listarEstadosCita());
        break;

    case 'listar_tipos_servicio':
        echo json_encode($modelo->listarTiposServicio());
        break;

    case 'listar_sedes':
        echo json_encode($modelo->listarSedes());
        break;

    case 'listar_alergias_medicamentos':
        echo json_encode($modelo->listarAlergiasMedicamentos());
        break;

    // 📋 Citas vencidas (banner de alerta)
    case 'listar_vencidas':
        echo json_encode($modelo->listarCitasVencidas());
        break;

    // ✅ Cerrar cita vencida (No asistió o Cancelada)
    case 'cerrar_vencida':
        $id_cita     = intval($_POST['id_cita'] ?? 0);
        $nuevo_estado = intval($_POST['id_estado_cita'] ?? 0);
        if (!$id_cita || !$nuevo_estado) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos']);
            exit;
        }
        echo json_encode($modelo->cerrarCitaVencida($id_cita, $nuevo_estado));
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida: ' . htmlspecialchars($accion)]);
        break;
}

error_log("🔵 === CONTROLADOR FINALIZADO ===");
?>