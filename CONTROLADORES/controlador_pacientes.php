<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../MODELOS/modelo_pacientes.php';
$modelo = new ModeloPacientes();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// =====================================================
// 🔍 FUNCIONES DE VALIDACIÓN
// =====================================================
function validarDNI($dni) {
    if (!preg_match('/^[0-9]{8}$/', $dni)) {
        return ['valido' => false, 'mensaje' => 'El DNI debe tener exactamente 8 dígitos numéricos'];
    }
    $dniInvalidos = ['00000000','11111111','22222222','33333333','44444444',
                     '55555555','66666666','77777777','88888888','99999999'];
    if (in_array($dni, $dniInvalidos)) {
        return ['valido' => false, 'mensaje' => 'El DNI ingresado no es válido'];
    }
    return ['valido' => true];
}

function validarTelefono($telefono) {
    if (!preg_match('/^[0-9]{9}$/', $telefono)) {
        return ['valido' => false, 'mensaje' => 'El teléfono debe tener exactamente 9 dígitos numéricos'];
    }
    return ['valido' => true];
}

function validarNombre($nombre, $campo = 'Nombre') {
    $nombre = trim($nombre);
    if (empty($nombre)) return ['valido' => false, 'mensaje' => "$campo es obligatorio"];
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/', $nombre))
        return ['valido' => false, 'mensaje' => "$campo solo puede contener letras, espacios y acentos"];
    if (strlen($nombre) < 2)
        return ['valido' => false, 'mensaje' => "$campo debe tener al menos 2 caracteres"];
    return ['valido' => true];
}

function validarCorreo($correo) {
    $correo = trim($correo);
    if (empty($correo)) return ['valido' => false, 'mensaje' => 'El correo es obligatorio'];
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/', $correo))
        return ['valido' => false, 'mensaje' => 'El formato del correo no es válido'];
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL))
        return ['valido' => false, 'mensaje' => 'El correo electrónico no es válido'];
    return ['valido' => true];
}

// ✅ CORRECCIÓN: acepta tanto 'sexo' como 'id_sexo', etc.
function validarCamposObligatorios($datos) {
    $camposRequeridos = [
        'nombre'            => 'Nombre',
        'apellidos'         => 'Apellidos',
        'dni'               => 'DNI',
        'correo'            => 'Correo',
        'telefono'          => 'Teléfono',
        'direccion'         => 'Dirección',
        'ocupacion'         => 'Ocupación',
    ];

    foreach ($camposRequeridos as $campo => $nombre) {
        if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
            return ['valido' => false, 'mensaje' => "$nombre es obligatorio"];
        }
    }

    // ✅ Verificar sexo (acepta 'sexo' o 'id_sexo')
    $sexo = $datos['sexo'] ?? $datos['id_sexo'] ?? '';
    if (empty($sexo)) return ['valido' => false, 'mensaje' => 'Sexo es obligatorio'];

    // ✅ Verificar estado civil (acepta 'estado_civil' o 'id_estado_civil')
    $estadoCivil = $datos['estado_civil'] ?? $datos['id_estado_civil'] ?? '';
    if (empty($estadoCivil)) return ['valido' => false, 'mensaje' => 'Estado Civil es obligatorio'];

    // ✅ Verificar grado instrucción (acepta 'grado_instruccion' o 'id_grado_instruccion')
    $gradoInstruccion = $datos['grado_instruccion'] ?? $datos['id_grado_instruccion'] ?? '';
    if (empty($gradoInstruccion)) return ['valido' => false, 'mensaje' => 'Grado de Instrucción es obligatorio'];

    return ['valido' => true];
}

// ✅ Helper para obtener el valor de sexo/estado_civil/grado sin importar el nombre del campo
function obtenerCampoFlex($datos, $nombre1, $nombre2) {
    return $datos[$nombre1] ?? $datos[$nombre2] ?? null;
}

// =====================================================
// 📋 PROCESAMIENTO DE ACCIONES
// =====================================================
switch ($accion) {

    // 🔹 Registrar nuevo paciente
    case 'registrar':
        $dni = trim($_POST['dni']);
        $validacionDNI = validarDNI($dni);
        if (!$validacionDNI['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionDNI['mensaje']]); exit;
        }
        if ($modelo->existeDNI($dni)) {
            echo json_encode(['success' => false, 'mensaje' => 'El DNI ya está registrado en el sistema']); exit;
        }
        $correo = trim($_POST['correo']);
        if ($modelo->existeCorreo($correo)) {
            echo json_encode(['success' => false, 'mensaje' => 'El correo ya está registrado en el sistema']); exit;
        }
        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]); exit;
        }

        $nombre              = trim($_POST['nombre']);
        $apellidos           = trim($_POST['apellidos']);
        $telefono            = trim($_POST['telefono']);
        $direccion           = trim($_POST['direccion']);
        $ocupacion           = trim($_POST['ocupacion']);
        $fecha_nacimiento    = $_POST['fecha_nacimiento'] ?? null;
        // ✅ CORRECCIÓN: acepta ambos nombres de campo
        $id_sexo             = obtenerCampoFlex($_POST, 'sexo', 'id_sexo');
        $id_estado_civil     = obtenerCampoFlex($_POST, 'estado_civil', 'id_estado_civil');
        $id_grado_instruccion = obtenerCampoFlex($_POST, 'grado_instruccion', 'id_grado_instruccion');
        $observaciones       = $_POST['observaciones'] ?? null;

        $validacionTelefono = validarTelefono($telefono);
        if (!$validacionTelefono['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionTelefono['mensaje']]); exit;
        }
        $validacionNombre = validarNombre($nombre, 'Nombre');
        if (!$validacionNombre['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionNombre['mensaje']]); exit;
        }
        $validacionApellidos = validarNombre($apellidos, 'Apellidos');
        if (!$validacionApellidos['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionApellidos['mensaje']]); exit;
        }
        $validacionCorreo = validarCorreo($correo);
        if (!$validacionCorreo['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionCorreo['mensaje']]); exit;
        }

        $fotoRuta = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid('paciente_') . '.' . $ext;
            $carpetaDestino = '../IMAGENES/perfiles_pacientes/';
            if (!file_exists($carpetaDestino)) mkdir($carpetaDestino, 0777, true);
            $destino = $carpetaDestino . $nombreArchivo;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $fotoRuta = 'IMAGENES/perfiles_pacientes/' . $nombreArchivo;
            }
        }

        $id_apoderado = null;
        if (isset($_POST['datos_apoderado'])) {
            $datosApoderado = json_decode($_POST['datos_apoderado'], true);
            if ($datosApoderado) {
                $id_apoderado = $modelo->registrarApoderado($datosApoderado);
            }
        }

        $datosPaciente = [
            'nombre'              => $nombre,
            'apellido'            => $apellidos,
            'dni'                 => $dni,
            'fecha_nacimiento'    => $fecha_nacimiento,
            'telefono'            => $telefono,
            'direccion'           => $direccion,
            'correo'              => $correo,
            'foto'                => $fotoRuta,
            'id_estado_civil'     => $id_estado_civil,
            'id_sexo'             => $id_sexo,
            'id_grado_instruccion'=> $id_grado_instruccion,
            'ocupacion'           => $ocupacion,
            'observaciones'       => $observaciones,
            'id_apoderado'        => $id_apoderado
        ];

        $resultado = $modelo->registrarPaciente($datosPaciente);
        if ($resultado) {
            echo json_encode(['success' => true, 'id_paciente' => $resultado['id_paciente'], 'mensaje' => 'Paciente registrado exitosamente']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al registrar el paciente']);
        }
        break;

    // 🔹 Editar paciente
    case 'editar':
        $id = intval($_POST['id_paciente'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID de paciente inválido']); exit;
        }

        $dni = trim($_POST['dni']);
        $validacionDNI = validarDNI($dni);
        if (!$validacionDNI['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionDNI['mensaje']]); exit;
        }
        if ($modelo->existeDNIExceptoPaciente($dni, $id)) {
            echo json_encode(['success' => false, 'mensaje' => 'El DNI ya está registrado por otro paciente']); exit;
        }

        $correo = trim($_POST['correo']);
        if ($modelo->existeCorreoExceptoPaciente($correo, $id)) {
            echo json_encode(['success' => false, 'mensaje' => 'El correo ya está registrado por otro paciente']); exit;
        }

        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]); exit;
        }

        $nombre               = trim($_POST['nombre']);
        $apellidos            = trim($_POST['apellidos']);
        $telefono             = trim($_POST['telefono']);
        $direccion            = trim($_POST['direccion']);
        $ocupacion            = trim($_POST['ocupacion']);
        $fecha_nacimiento     = $_POST['fecha_nacimiento'] ?? null;
        // ✅ CORRECCIÓN: acepta ambos nombres de campo
        $id_sexo              = obtenerCampoFlex($_POST, 'sexo', 'id_sexo');
        $id_estado_civil      = obtenerCampoFlex($_POST, 'estado_civil', 'id_estado_civil');
        $id_grado_instruccion = obtenerCampoFlex($_POST, 'grado_instruccion', 'id_grado_instruccion');
        $observaciones        = $_POST['observaciones'] ?? null;

        $validacionTelefono = validarTelefono($telefono);
        if (!$validacionTelefono['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionTelefono['mensaje']]); exit;
        }
        $validacionNombre = validarNombre($nombre, 'Nombre');
        if (!$validacionNombre['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionNombre['mensaje']]); exit;
        }
        $validacionApellidos = validarNombre($apellidos, 'Apellidos');
        if (!$validacionApellidos['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionApellidos['mensaje']]); exit;
        }
        $validacionCorreo = validarCorreo($correo);
        if (!$validacionCorreo['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionCorreo['mensaje']]); exit;
        }

        // Procesar foto
        $fotoRuta = $_POST['foto_actual'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid('paciente_') . '.' . $ext;
            $carpetaDestino = '../IMAGENES/perfiles_pacientes/';
            if (!file_exists($carpetaDestino)) mkdir($carpetaDestino, 0777, true);
            $destino = $carpetaDestino . $nombreArchivo;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $fotoRuta = 'IMAGENES/perfiles_pacientes/' . $nombreArchivo;
            }
        }

        // Procesar apoderado
        $id_apoderado = null;
        if (isset($_POST['datos_apoderado'])) {
            $datosApoderado = json_decode($_POST['datos_apoderado'], true);
            if ($datosApoderado) {
                if (isset($_POST['id_apoderado']) && !empty($_POST['id_apoderado'])) {
                    $modelo->editarApoderado($_POST['id_apoderado'], $datosApoderado);
                    $id_apoderado = $_POST['id_apoderado'];
                } else {
                    $id_apoderado = $modelo->registrarApoderado($datosApoderado);
                }
            }
        } elseif (isset($_POST['id_apoderado']) && !empty($_POST['id_apoderado'])) {
            $id_apoderado = $_POST['id_apoderado'];
        }

        $datosPaciente = [
            'nombre'               => $nombre,
            'apellido'             => $apellidos,
            'dni'                  => $dni,
            'fecha_nacimiento'     => $fecha_nacimiento,
            'telefono'             => $telefono,
            'direccion'            => $direccion,
            'correo'               => $correo,
            'foto'                 => $fotoRuta,
            'id_estado_civil'      => $id_estado_civil,
            'id_sexo'              => $id_sexo,
            'id_grado_instruccion' => $id_grado_instruccion,
            'ocupacion'            => $ocupacion,
            'observaciones'        => $observaciones,
            'id_apoderado'         => $id_apoderado
        ];

        $resultado = $modelo->editarPaciente($id, $datosPaciente);
        echo json_encode($resultado ?
            ['success' => true,  'mensaje' => 'Paciente actualizado exitosamente'] :
            ['success' => false, 'mensaje' => 'Error al actualizar el paciente']);
        break;

    // 🔹 Ver apoderado
    case 'ver_apoderado':
        $id = $_GET['id'] ?? 0;
        $apoderado = $modelo->verApoderado($id);
        echo json_encode($apoderado ?: ['success' => false, 'mensaje' => 'Apoderado no encontrado']);
        break;

    // 🔹 Listar pacientes
    case 'listar':
        echo json_encode($modelo->listarPacientes());
        break;

    // 🔹 Ver detalle de paciente
    case 'ver':
    $id = $_GET['id'] ?? 0;
    $paciente = $modelo->verPaciente($id);
    if ($paciente) {
        if (!empty($paciente['id_apoderado'])) {
            $apoderado = $modelo->verApoderado($paciente['id_apoderado']);
            if ($apoderado) {
                $paciente['apoderado'] = $apoderado;
            }
        }
        echo json_encode($paciente);
    } else {
        echo json_encode(['success' => false, 'mensaje' => 'Paciente no encontrado']);
    }
    break;

    case 'listar_sexo':
        echo json_encode($modelo->listarSexo());
        break;

    case 'listar_estado_civil':
        echo json_encode($modelo->listarEstadoCivil());
        break;

    case 'listar_grado_instruccion':
        echo json_encode($modelo->listarGradoInstruccion());
        break;

    case 'buscar':
        $termino = $_GET['termino'] ?? '';
        echo json_encode($modelo->buscarPaciente($termino));
        break;

    case 'listar_tipo_familiar':
        echo json_encode($modelo->listarTipoFamiliar());
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>