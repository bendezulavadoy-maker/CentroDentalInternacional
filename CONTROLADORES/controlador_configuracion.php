<?php
require_once '../MODELOS/modelo_configuracion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Admin (1), Asistente (3) y Odontólogo (2) pueden acceder
// Odontólogo solo tiene acceso a consultas de lectura (slots, tipos_atencion, sedes)
$id_rol_actual = intval($_SESSION['usuario']['id_rol'] ?? 0);
$accionTemp    = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Odontólogo solo puede usar acciones de lectura
$accionesLecturaOdontologo = ['slots_disponibles', 'listar_tipos_atencion', 'listar_sedes', 'listar_doctores'];

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
    exit();
}

if (!in_array($id_rol_actual, [1, 2, 3])) {
    echo json_encode(['success' => false, 'mensaje' => 'Acceso denegado']);
    exit();
}

// Odontólogo solo puede usar acciones de lectura
if ($id_rol_actual === 2 && !in_array($accionTemp, $accionesLecturaOdontologo)) {
    echo json_encode(['success' => false, 'mensaje' => 'Sin permiso para esta acción']);
    exit();
}

$modelo     = new ModeloConfiguracion();
$id_usuario = intval($_SESSION['usuario']['id_usuario'] ?? 0);

// Soportar tanto form-data como JSON body
$jsonInput = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
}
$accion = $jsonInput['accion'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    // ── ROLES ──────────────────────────────────────────
    case 'listar_roles':
        echo json_encode($modelo->listarRoles());
        break;

    case 'crear_rol':
        $nombre = trim($_POST['nombre_rol'] ?? '');
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'mensaje' => 'El nombre es obligatorio']);
            break;
        }
        echo json_encode($modelo->crearRol($nombre));
        break;

    case 'editar_rol':
        $id     = intval($_POST['id_rol'] ?? 0);
        $nombre = trim($_POST['nombre_rol'] ?? '');
        if ($id <= 0 || empty($nombre)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            break;
        }
        echo json_encode($modelo->editarRol($id, $nombre));
        break;

    case 'eliminar_rol':
        $id = intval($_POST['id_rol'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID inválido']);
            break;
        }
        // No permitir eliminar los 3 roles base
        if ($id <= 3) {
            echo json_encode(['success' => false, 'mensaje' => 'No se pueden eliminar los roles base del sistema']);
            break;
        }
        echo json_encode($modelo->eliminarRol($id));
        break;

    // ── PERMISOS ───────────────────────────────────────
    case 'listar_permisos':
        echo json_encode($modelo->listarPermisosCompleto());
        break;

    case 'guardar_permisos':
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!isset($datos['permisos']) || !is_array($datos['permisos'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            break;
        }
        echo json_encode($modelo->guardarPermisos($datos['permisos']));
        break;

    // ── SEDES ──────────────────────────────────────────
    case 'listar_sedes':
        echo json_encode($modelo->listarSedes());
        break;

    case 'crear_sede':
        $nombre    = trim($_POST['nombre_sede'] ?? '');
        $direccion = trim($_POST['direccion_sede'] ?? '');
        $telefono  = trim($_POST['telefono_sede'] ?? '') ?: null;
        $activo    = intval($_POST['activo'] ?? 1);
        if (empty($nombre) || empty($direccion)) {
            echo json_encode(['success' => false, 'mensaje' => 'Nombre y dirección son obligatorios']);
            break;
        }
        echo json_encode($modelo->crearSede($nombre, $direccion, $telefono, $activo));
        break;

    case 'editar_sede':
        $id        = intval($_POST['id_sede'] ?? 0);
        $nombre    = trim($_POST['nombre_sede'] ?? '');
        $direccion = trim($_POST['direccion_sede'] ?? '');
        $telefono  = trim($_POST['telefono_sede'] ?? '') ?: null;
        $activo    = intval($_POST['activo'] ?? 1);
        if ($id <= 0 || empty($nombre) || empty($direccion)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            break;
        }
        echo json_encode($modelo->editarSede($id, $nombre, $direccion, $telefono, $activo));
        break;
    // ── SERVICIOS ──────────────────────────────────────────────────
    case 'listar_servicios':
        echo json_encode($modelo->listarServicios());
        break;

    case 'crear_servicio':
        $nombre = trim($_POST['nombre_servicio'] ?? '');
        $precio = floatval($_POST['precio_base'] ?? 0);
        $activo = intval($_POST['activo'] ?? 1);
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'mensaje' => 'El nombre es obligatorio']);
            break;
        }
        echo json_encode($modelo->crearServicio($nombre, $precio, $activo));
        break;

    case 'editar_servicio':
        $id     = intval($_POST['id_tipo_servicio'] ?? 0);
        $nombre = trim($_POST['nombre_servicio'] ?? '');
        $precio = floatval($_POST['precio_base'] ?? 0);
        $activo = intval($_POST['activo'] ?? 1);
        if ($id <= 0 || empty($nombre)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            break;
        }
        echo json_encode($modelo->editarServicio($id, $nombre, $precio, $activo));
        break;

    // ── PLANES ─────────────────────────────────────────────────────
    case 'listar_planes_config':
        echo json_encode($modelo->listarPlanesConfig());
        break;

    case 'guardar_plan':
        $datos = json_decode(file_get_contents('php://input'), true);
        if (empty($datos['nombre_plan'])) {
            echo json_encode(['success' => false, 'mensaje' => 'El nombre es obligatorio']);
            break;
        }
        echo json_encode($modelo->guardarPlan($datos));
        break;

    case 'eliminar_plan':
        $id = intval($_POST['id_plan'] ?? 0);
        echo json_encode($modelo->eliminarPlan($id));
        break;
        case 'listar_aparatologia':
        echo json_encode($modelo->listarAparatologia());
        break;

    case 'crear_aparatologia':
        $nombre = trim($_POST['nombre_aparatologia'] ?? '');
        $precio = floatval($_POST['precio_base'] ?? 0);
        $activo = intval($_POST['activo'] ?? 1);
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'mensaje' => 'El nombre es obligatorio']);
            break;
        }
        echo json_encode($modelo->crearAparatologia($nombre, $precio, $activo));
        break;

    case 'editar_aparatologia':
        $id     = intval($_POST['id_aparatologia'] ?? 0);
        $nombre = trim($_POST['nombre_aparatologia'] ?? '');
        $precio = floatval($_POST['precio_base'] ?? 0);
        $activo = intval($_POST['activo'] ?? 1);
        if ($id <= 0 || empty($nombre)) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
            break;
        }
        echo json_encode($modelo->editarAparatologia($id, $nombre, $precio, $activo));
        break;


    // ── Tipos de atención ─────────────────────────────────────────
    case 'listar_tipos_atencion':
        echo json_encode($modelo->listarTiposAtencion());
        break;

    case 'crear_tipo_atencion':
        $nombre   = trim($_POST['nombre']            ?? '');
        $duracion = intval($_POST['duracion_minutos'] ?? 30);
        $color    = trim($_POST['color']             ?? '#2a4d8f');
        if (empty($nombre)) { echo json_encode(['success'=>false,'mensaje'=>'Nombre requerido']); break; }
        echo json_encode($modelo->crearTipoAtencion($nombre, $duracion, $color));
        break;

    case 'editar_tipo_atencion':
        $id       = intval($_POST['id_tipo_atencion'] ?? 0);
        $nombre   = trim($_POST['nombre']             ?? '');
        $duracion = intval($_POST['duracion_minutos'] ?? 30);
        $color    = trim($_POST['color']              ?? '#2a4d8f');
        $activo   = intval($_POST['activo']           ?? 1);
        if ($id <= 0 || empty($nombre)) { echo json_encode(['success'=>false,'mensaje'=>'Datos inválidos']); break; }
        echo json_encode($modelo->editarTipoAtencion($id, $nombre, $duracion, $color, $activo));
        break;

    // ── Horarios de doctores ──────────────────────────────────────
    case 'listar_horarios_doctor':
        $id_doctor  = intval($_GET['id_doctor']  ?? 0);
        $fecha_ini  = $_GET['fecha_inicio'] ?? date('Y-m-d');
        $fecha_fin  = $_GET['fecha_fin']    ?? date('Y-m-d', strtotime('+13 days'));
        echo json_encode($modelo->listarHorariosDoctor($id_doctor, $fecha_ini, $fecha_fin));
        break;

    case 'guardar_horario':
        $data = $jsonInput ?? json_decode(file_get_contents('php://input'), true);
        echo json_encode($modelo->guardarHorario($data, $id_usuario));
        break;

    case 'eliminar_horario':
        $id = intval($_POST['id_horario'] ?? 0);
        echo json_encode($modelo->eliminarHorario($id));
        break;

    case 'copiar_semana':
        $data = $jsonInput ?? json_decode(file_get_contents('php://input'), true);
        echo json_encode($modelo->copiarSemana(
            intval($data['id_doctor'] ?? 0),
            $data['fecha_origen']  ?? '',
            $data['fecha_destino'] ?? '',
            $id_usuario
        ));
        break;

    // ── Bloqueos ──────────────────────────────────────────────────
    case 'listar_bloqueos':
        $id_doctor = intval($_GET['id_doctor'] ?? 0);
        $fecha_ini = $_GET['fecha_inicio'] ?? date('Y-m-d');
        $fecha_fin = $_GET['fecha_fin']    ?? date('Y-m-d', strtotime('+30 days'));
        echo json_encode($modelo->listarBloqueos($id_doctor, $fecha_ini, $fecha_fin));
        break;

    case 'crear_bloqueo':
        $data = $jsonInput ?? json_decode(file_get_contents('php://input'), true);
        echo json_encode($modelo->crearBloqueo($data, $id_usuario));
        break;

    case 'crear_bloqueo_rango':
        $data = $jsonInput ?? json_decode(file_get_contents('php://input'), true);
        echo json_encode($modelo->crearBloqueoRango($data, $id_usuario));
        break;

    case 'replicar_rango':
        $data = $jsonInput ?? json_decode(file_get_contents('php://input'), true);
        echo json_encode($modelo->replicarRango(
            intval($data['id_doctor']    ?? 0),
            $data['semana_origen']       ?? '',
            $data['fecha_inicio']        ?? '',
            $data['fecha_fin']           ?? '',
            $id_usuario
        ));
        break;

    case 'eliminar_bloqueo':
        $id = intval($_POST['id_bloqueo'] ?? 0);
        echo json_encode($modelo->eliminarBloqueo($id));
        break;

    // ── Slots disponibles (usado por módulo citas) ────────────────
    case 'slots_disponibles':
        $id_doctor  = intval($_GET['id_doctor']  ?? 0);
        $id_sede    = intval($_GET['id_sede']    ?? 0);
        $fecha      = $_GET['fecha']             ?? '';
        $duracion   = intval($_GET['duracion']   ?? 30);
        $id_cita    = intval($_GET['id_cita']    ?? 0); // para edición
        echo json_encode($modelo->slotsDisponibles($id_doctor, $id_sede, $fecha, $duracion, $id_cita));
        break;

    // ── Doctores (para selector en horarios) ─────────────────────
    case 'listar_doctores':
        echo json_encode($modelo->listarDoctores());
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>