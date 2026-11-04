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

/**
 * Valida un numero de documento segun las reglas de su tipo
 * (longitud y si admite solo digitos), tomadas de la tabla tipo_documento.
 */
function validarDocumento($tipo, $numero) {
    if (!$tipo) return ['valido' => false, 'mensaje' => 'El tipo de documento no es válido'];

    $abrev       = $tipo['abreviatura'];
    $min         = (int)$tipo['longitud_min'];
    $max         = (int)$tipo['longitud_max'];
    $soloNumeros = (int)$tipo['solo_numeros'] === 1;
    $numero      = trim($numero);

    if ($numero === '') return ['valido' => false, 'mensaje' => "El número de $abrev es obligatorio"];

    if ($soloNumeros && !preg_match('/^[0-9]+$/', $numero))
        return ['valido' => false, 'mensaje' => "El $abrev solo admite dígitos"];
    if (!$soloNumeros && !preg_match('/^[A-Za-z0-9]+$/', $numero))
        return ['valido' => false, 'mensaje' => "El $abrev solo admite letras y números"];

    $largo  = strlen($numero);
    $unidad = $soloNumeros ? 'dígitos' : 'caracteres';
    if ($largo < $min || $largo > $max) {
        $texto = ($min === $max) ? "exactamente $min $unidad" : "entre $min y $max $unidad";
        return ['valido' => false, 'mensaje' => "El $abrev debe tener $texto"];
    }

    if ($tipo['codigo'] === '1') { // DNI: rechazar numeros repetidos
        if (preg_match('/^(\d)\1{7}$/', $numero)) {
            return ['valido' => false, 'mensaje' => 'El DNI ingresado no es válido'];
        }
    }
    return ['valido' => true];
}

/**
 * Nombres y apellidos: solo letras, espacios y tildes (2 a 60 caracteres).
 * Devuelve el texto del error, o '' si es valido.
 */
function validarNombrePortal($valor, $campo) {
    $valor = trim($valor);
    $largo = mb_strlen($valor, 'UTF-8');
    if ($largo < 2 || $largo > 60 || !preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü ]+$/u', $valor)) {
        return "$campo solo puede contener letras y espacios (entre 2 y 60 caracteres)";
    }
    return '';
}

/**
 * Texto libre (direccion, ocupacion, motivo): se rechazan "<", ">" y caracteres de control,
 * que son los que permiten colar codigo HTML. El formulario es publico: lo que se guarda aqui
 * lo termina viendo el personal de la clinica en sus pantallas.
 */
function validarTextoLibre($valor, $campo, $min, $max) {
    $valor = trim($valor);
    $largo = mb_strlen($valor, 'UTF-8');
    if ($largo < $min || $largo > $max) {
        return "$campo debe tener entre $min y $max caracteres";
    }
    if (preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/', $valor)) {
        return "$campo contiene caracteres no permitidos (< >)";
    }
    return '';
}

function responderBloqueado() {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.'
    ]);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$modelo = new ModeloPortal();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── Acciones públicas (sin sesión) ────────────────────────────
$accionesPublicas = ['verificar_nro_documento','login','crear_password','registrar_paciente','catalogos','slots_portal'];

if (!in_array($accion, $accionesPublicas)) {
    if (empty($_SESSION['portal']['id_paciente'])) {
        echo json_encode(['success' => false, 'mensaje' => 'Sesión expirada', 'redirect' => 'index.php']);
        exit;
    }
}

$idPaciente = $_SESSION['portal']['id_paciente'] ?? 0;

switch ($accion) {

    case 'verificar_nro_documento':
        // Limite de consultas por IP (evita recorrer documentos para ver quien es paciente)
        if ($modelo->estaBloqueado("verif|$ip", 40)) responderBloqueado();
        $modelo->registrarIntento("verif|$ip");

        $idTipo = intval($_POST['id_tipo_documento'] ?? 1);
        $tipo   = $modelo->obtenerTipoDocumento($idTipo);
        $numero = strtoupper(trim($_POST['nro_documento'] ?? ''));

        $v = validarDocumento($tipo, $numero);
        if (!$v['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $v['mensaje']]);
            exit;
        }
        echo json_encode($modelo->verificarDocumento($idTipo, $numero));
        break;

    case 'login':
        $idTipo = intval($_POST['id_tipo_documento'] ?? 1);
        $numero = strtoupper(trim($_POST['nro_documento'] ?? ''));
        $pass   = $_POST['password'] ?? '';
        if (!$numero || !$pass) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos incompletos']);
            exit;
        }
        $claveDoc = "doc|$idTipo|$numero";
        if ($modelo->estaBloqueado($claveDoc, 5) || $modelo->estaBloqueado("ip|$ip", 20)) responderBloqueado();

        $resultado = $modelo->login($idTipo, $numero, $pass);
        if (!empty($resultado['success'])) {
            $modelo->limpiarIntentos($claveDoc);
        } else {
            $modelo->registrarIntento($claveDoc);
            $modelo->registrarIntento("ip|$ip");
        }
        echo json_encode($resultado);
        break;

    case 'crear_password':
        $idTipo   = intval($_POST['id_tipo_documento'] ?? 1);
        $numero   = strtoupper(trim($_POST['nro_documento'] ?? ''));
        $telefono = trim($_POST['telefono'] ?? '');
        $pass     = $_POST['password'] ?? '';
        if (!$numero || strlen($pass) < 6) {
            echo json_encode(['success' => false, 'mensaje' => 'Contraseña muy corta (mínimo 6 caracteres)']);
            exit;
        }
        if (!preg_match('/^\d{9}$/', $telefono)) {
            echo json_encode(['success' => false, 'mensaje' => 'Ingresa el teléfono de 9 dígitos registrado en la clínica']);
            exit;
        }
        $claveDoc = "doc|$idTipo|$numero";
        if ($modelo->estaBloqueado($claveDoc, 5) || $modelo->estaBloqueado("ip|$ip", 20)) responderBloqueado();

        $resultado = $modelo->crearPassword($idTipo, $numero, $telefono, $pass);
        if (!empty($resultado['success'])) {
            $modelo->limpiarIntentos($claveDoc);
        } else {
            $modelo->registrarIntento($claveDoc);
            $modelo->registrarIntento("ip|$ip");
        }
        echo json_encode($resultado);
        break;

    case 'registrar_paciente':
        // Limite de altas por IP (el formulario es publico)
        if ($modelo->estaBloqueado("reg|$ip", 10, 60)) {
            echo json_encode(['success' => false, 'mensaje' => 'Se alcanzó el límite de registros. Inténtalo más tarde o comunícate con la clínica.']);
            exit;
        }

        $campos = ['nro_documento','nombre','apellido','fecha_nac','id_sexo','id_estado_civil',
                   'id_grado','ocupacion','telefono','correo','direccion','password'];
        foreach ($campos as $c) {
            if (empty($_POST[$c])) {
                echo json_encode(['success' => false, 'mensaje' => "El campo $c es obligatorio"]);
                exit;
            }
        }

        $idTipo = intval($_POST['id_tipo_documento'] ?? 1);
        $numero = strtoupper(trim($_POST['nro_documento']));
        $v = validarDocumento($modelo->obtenerTipoDocumento($idTipo), $numero);
        if (!$v['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $v['mensaje']]);
            exit;
        }
        if (!preg_match('/^\d{9}$/', $_POST['telefono'])) {
            echo json_encode(['success' => false, 'mensaje' => 'Teléfono inválido — debe tener 9 dígitos']);
            exit;
        }
        if (!filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL) || strlen($_POST['correo']) > 150) {
            echo json_encode(['success' => false, 'mensaje' => 'Correo electrónico inválido']);
            exit;
        }
        if (strlen($_POST['password']) < 6 || strlen($_POST['password']) > 72) {
            echo json_encode(['success' => false, 'mensaje' => 'La contraseña debe tener entre 6 y 72 caracteres']);
            exit;
        }

        // Nombres, ocupacion y direccion (texto que luego ve el personal)
        $errorCampo = validarNombrePortal($_POST['nombre'], 'El nombre')
                   ?: validarNombrePortal($_POST['apellido'], 'El apellido')
                   ?: validarTextoLibre($_POST['ocupacion'], 'La ocupación', 2, 100)
                   ?: validarTextoLibre($_POST['direccion'], 'La dirección', 5, 255);
        if ($errorCampo) {
            echo json_encode(['success' => false, 'mensaje' => $errorCampo]);
            exit;
        }

        // Fecha de nacimiento: formato valido, no futura
        $fn = DateTime::createFromFormat('Y-m-d', $_POST['fecha_nac']);
        if (!$fn || $fn->format('Y-m-d') !== $_POST['fecha_nac']
            || $fn > new DateTime('today') || (int)$fn->format('Y') < 1900) {
            echo json_encode(['success' => false, 'mensaje' => 'Fecha de nacimiento inválida']);
            exit;
        }

        // Apoderado (si viene): validar su documento segun su tipo
        $apoIdTipo = intval($_POST['apo_id_tipo_documento'] ?? 1);
        $apoNumero = strtoupper(trim($_POST['apo_nro_documento'] ?? ''));
        if (!empty($_POST['apo_nombre'])) {
            $errorApo = validarNombrePortal($_POST['apo_nombre'], 'El nombre del apoderado')
                     ?: validarNombrePortal($_POST['apo_apellido'] ?? '', 'El apellido del apoderado');
            if ($errorApo) {
                echo json_encode(['success' => false, 'mensaje' => $errorApo]);
                exit;
            }
            if (!preg_match('/^\d{9}$/', $_POST['apo_telefono'] ?? '')) {
                echo json_encode(['success' => false, 'mensaje' => 'Teléfono del apoderado inválido — debe tener 9 dígitos']);
                exit;
            }
            $vApo = validarDocumento($modelo->obtenerTipoDocumento($apoIdTipo), $apoNumero);
            if (!$vApo['valido']) {
                echo json_encode(['success' => false, 'mensaje' => 'Apoderado: ' . $vApo['mensaje']]);
                exit;
            }
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
            // Ademas de la extension, comprobar que el contenido sea realmente una imagen
            $info = @getimagesize($_FILES['foto']['tmp_name']);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                echo json_encode(['success' => false, 'mensaje' => 'El archivo no es una imagen válida']);
                exit;
            }
            $nombre  = 'paciente_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destino = $base . '/IMAGENES/perfiles_pacientes/' . $nombre;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $fotoPath = 'IMAGENES/perfiles_pacientes/' . $nombre;
            }
        }

        $campos_datos = ['nro_documento','nombre','apellido','fecha_nac','id_sexo','id_estado_civil',
                         'id_grado','ocupacion','telefono','correo','direccion','password',
                         'apo_nombre','apo_apellido','apo_nro_documento','apo_telefono','apo_parentesco'];
        $datos = [];
        foreach ($campos_datos as $c) {
            $datos[$c] = isset($_POST[$c]) ? trim($_POST[$c]) : '';
        }
        // Documento normalizado y nombres siempre en mayusculas
        $datos['id_tipo_documento']     = $idTipo;
        $datos['nro_documento']                   = $numero;
        $datos['apo_id_tipo_documento'] = $apoIdTipo;
        $datos['apo_nro_documento']               = $apoNumero;
        foreach (['nombre','apellido','apo_nombre','apo_apellido'] as $c) {
            $datos[$c] = mb_strtoupper($datos[$c], 'UTF-8');
        }

        $resultado = $modelo->registrarPaciente($datos, $fotoPath);
        if (!empty($resultado['success'])) $modelo->registrarIntento("reg|$ip");
        echo json_encode($resultado);
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
        $errorMotivo = validarTextoLibre($_POST['motivo'], 'El motivo', 3, 500);
        if ($errorMotivo) {
            echo json_encode(['success' => false, 'mensaje' => $errorMotivo]);
            exit;
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