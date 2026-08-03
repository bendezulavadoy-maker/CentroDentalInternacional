<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Rutas absolutas para evitar conflictos desde subcarpeta portal/
$base = __DIR__ . '/..';
require_once $base . '/CONFIG/conexion.php';
require_once $base . '/MODELOS/modelo_configuracion.php';
require_once __DIR__ . '/modelo_portal.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$modelo = new ModeloPortal();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── Acciones públicas (sin sesión) ────────────────────────────
$accionesPublicas = ['verificar_dni','login','crear_password','registrar_paciente','catalogos','slots_portal'];

if (!in_array($accion, $accionesPublicas)) {
    if (empty($_SESSION['portal']['id_paciente'])) {
        echo json_encode(['success' => false, 'mensaje' => 'Sesión expirada', 'redirect' => 'index.php']);
        exit;
    }
}

$idPaciente = $_SESSION['portal']['id_paciente'] ?? 0;

switch ($accion) {

    case 'verificar_dni':
        $dni = trim($_POST['dni'] ?? '');
        if (!preg_match('/^\d{8}$/', $dni)) {
            echo json_encode(['success' => false, 'mensaje' => 'DNI inválido']);
            exit;
        }
        echo json_encode($modelo->verificarDni($dni));
        break;

    case 'login':
        $dni  = trim($_POST['dni'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (!$dni || !$pass) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos']);
            exit;
        }
        echo json_encode($modelo->login($dni, $pass));
        break;

    case 'crear_password':
        $dni  = trim($_POST['dni'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (!$dni || strlen($pass) < 6) {
            echo json_encode(['success' => false, 'mensaje' => 'Contraseña muy corta (mínimo 6 caracteres)']);
            exit;
        }
        echo json_encode($modelo->crearPassword($dni, $pass));
        break;

    case 'registrar_paciente':
        $campos = ['dni','nombre','apellido','fecha_nac','id_sexo','id_estado_civil',
                   'id_grado','ocupacion','telefono','correo','direccion','password'];
        foreach ($campos as $c) {
            if (empty($_POST[$c])) {
                echo json_encode(['success' => false, 'mensaje' => "El campo $c es obligatorio"]);
                exit;
            }
        }
        if (!preg_match('/^\d{8}$/', $_POST['dni'])) {
            echo json_encode(['success' => false, 'mensaje' => 'DNI inválido']);
            exit;
        }
        if (!preg_match('/^\d{9}$/', $_POST['telefono'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Teléfono inválido — debe tener 9 dígitos']);
            exit;
        }
        if (!filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'mensaje' => 'Correo electrónico inválido']);
            exit;
        }
        if (strlen($_POST['password']) < 6) {
            echo json_encode(['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres']);
            exit;
        }

        // Procesar foto
        $fotoPath = null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'])) {
                echo json_encode(['success' => false, 'mensaje' => 'Solo se permiten fotos JPG o PNG']);
                exit;
            }
            if ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                echo json_encode(['success' => false, 'mensaje' => 'La foto no puede superar 2MB']);
                exit;
            }
            $nombre  = 'paciente_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destino = $base . '/IMAGENES/perfiles_pacientes/' . $nombre;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $fotoPath = 'IMAGENES/perfiles_pacientes/' . $nombre;
            }
        }

        $campos_datos = ['dni','nombre','apellido','fecha_nac','id_sexo','id_estado_civil',
                         'id_grado','ocupacion','telefono','correo','direccion','password',
                         'apo_nombre','apo_apellido','apo_dni','apo_telefono','apo_parentesco'];
        $datos = [];
        foreach ($campos_datos as $c) {
            $datos[$c] = isset($_POST[$c]) ? trim($_POST[$c]) : '';
        }
        echo json_encode($modelo->registrarPaciente($datos, $fotoPath));
        break;

    case 'catalogos':
        echo json_encode($modelo->catalogos());
        break;

    case 'get_paciente':
        echo json_encode($modelo->getPaciente($idPaciente));
        break;

    case 'mis_citas':
        echo json_encode($modelo->getCitasPaciente($idPaciente));
        break;

    case 'cancelar_cita':
        $id_cita = intval($_POST['id_cita'] ?? 0);
        if (!$id_cita) { echo json_encode(['success'=>false,'mensaje'=>'ID inválido']); exit; }

        // Obtener datos antes de cancelar (para el WhatsApp)
        $citaAntes = $modelo->getCitaParaWA($id_cita, $idPaciente);

        $resultado = $modelo->cancelarCita($id_cita, $idPaciente);

        if ($resultado['success'] && $citaAntes) {
            try {
                require_once '../SERVICIOS/WhatsAppService.php';
                $wa = new WhatsAppService();
                $wa->citaCancelada($citaAntes);
                $wa->avisoCancelacionRecepcionista($citaAntes);
            } catch (Exception $e) {
                error_log("WhatsApp cancelar portal: " . $e->getMessage());
            }
        }
        echo json_encode($resultado);
        break;

    case 'slots_portal':
        $id_doctor = intval($_GET['id_doctor'] ?? 0);
        $id_sede   = intval($_GET['id_sede']   ?? 0);
        $fecha     = $_GET['fecha']             ?? '';
        $duracion  = intval($_GET['duracion']   ?? 30);
        if (!$id_doctor || !$id_sede || !$fecha) {
            echo json_encode(['disponible'=>false,'slots'=>[],'mensaje'=>'Parámetros incompletos']);
            exit;
        }
        $mc = new ModeloConfiguracion();
        echo json_encode($mc->slotsDisponibles($id_doctor, $id_sede, $fecha, $duracion, 0));
        break;

    case 'doctores':
        echo json_encode($modelo->getDoctores());
        break;

    case 'sedes':
        echo json_encode($modelo->getSedes());
        break;

    case 'tipos_atencion':
        echo json_encode($modelo->getTiposAtencion());
        break;

    case 'agendar_cita':
        $req = ['id_doctor','id_sede','fecha','hora','duracion','motivo'];
        foreach ($req as $c) {
            if (empty($_POST[$c])) {
                echo json_encode(['success'=>false,'mensaje'=>"$c es obligatorio"]); exit;
            }
        }
        $datos = [
            'id_paciente'      => $idPaciente,
            'id_doctor'        => intval($_POST['id_doctor']),
            'id_sede'          => intval($_POST['id_sede']),
            'fecha'            => $_POST['fecha'],
            'hora'             => $_POST['hora'],
            'duracion'         => intval($_POST['duracion']),
            'id_tipo_atencion' => intval($_POST['id_tipo_atencion'] ?? 0) ?: null,
            'motivo'           => trim($_POST['motivo']),
        ];
        echo json_encode($modelo->agendarCita($datos));
        break;

    case 'reprogramar_cita':
        $id_cita  = intval($_POST['id_cita']    ?? 0);
        $fecha    = $_POST['nueva_fecha']        ?? '';
        $hora     = $_POST['nueva_hora']         ?? '';
        $duracion = intval($_POST['duracion']    ?? 30);
        $id_sede  = intval($_POST['id_sede']     ?? 0);
        $id_doc   = intval($_POST['id_doctor']   ?? 0);
        if (!$id_cita || !$fecha || !$hora || !$id_doc || !$id_sede) {
            echo json_encode(['success'=>false,'mensaje'=>'Datos incompletos']); exit;
        }

        // Obtener datos antes de reprogramar
        $citaAntes = $modelo->getCitaParaWA($id_cita, $idPaciente);

        $resultado = $modelo->reprogramarCita($id_cita, $idPaciente, $fecha, $hora, $duracion, $id_sede, $id_doc);

        if ($resultado['success'] && $citaAntes) {
            try {
                require_once '../SERVICIOS/WhatsAppService.php';
                $wa = new WhatsAppService();
                $wa->citaReprogramada($citaAntes, $fecha, $hora);
                $wa->avisoReprogramacionRecepcionista($citaAntes, $fecha, $hora);
            } catch (Exception $e) {
                error_log("WhatsApp reprogramar portal: " . $e->getMessage());
            }
        }
        echo json_encode($resultado);
        break;

    case 'logout':
        $modelo->logout();
        echo json_encode(['success'=>true]);
        break;

    default:
        echo json_encode(['success'=>false,'mensaje'=>"Acción no válida: $accion"]);
}
?>