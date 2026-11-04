<?php
// Solo usuarios con sesion iniciada y acceso al modulo Pacientes
require_once '../CONFIG/permisos.php';
requerirPermisoApi('pacientes');

error_reporting(E_ALL);
ini_set('display_errors', 0);   // los errores van al log, nunca a la pantalla (rompian el JSON y mostraban rutas del servidor)
ini_set('log_errors', 1);
require_once '../MODELOS/modelo_pacientes.php';
$modelo = new ModeloPacientes();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// =====================================================
// FUNCIONES DE VALIDACION
// =====================================================

/**
 * Valida un numero de documento segun las reglas de su tipo
 * (longitud y si admite solo digitos), tomadas de la tabla tipo_documento.
 * $tipo es la fila del catalogo (o null si el tipo no existe / esta inactivo).
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

    $largo   = strlen($numero);
    $unidad  = $soloNumeros ? 'dígitos' : 'caracteres';
    if ($largo < $min || $largo > $max) {
        $texto = ($min === $max) ? "exactamente $min $unidad" : "entre $min y $max $unidad";
        return ['valido' => false, 'mensaje' => "El $abrev debe tener $texto"];
    }

    // Regla adicional solo para DNI (codigo 1): rechazar numeros repetidos
    if ($tipo['codigo'] === '1') {
        $dniInvalidos = ['00000000','11111111','22222222','33333333','44444444',
                         '55555555','66666666','77777777','88888888','99999999'];
        if (in_array($numero, $dniInvalidos)) {
            return ['valido' => false, 'mensaje' => 'El DNI ingresado no es válido'];
        }
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
    if ($correo === null || trim($correo) === '') return ['valido' => true]; // Opcional
    $correo = trim($correo);
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/', $correo))
        return ['valido' => false, 'mensaje' => 'El formato del correo no es válido'];
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL))
        return ['valido' => false, 'mensaje' => 'El correo electrónico no es válido'];
    return ['valido' => true];
}

// Acepta tanto 'sexo' como 'id_sexo', etc.
function validarCamposObligatorios($datos) {
    $camposRequeridos = [
        'nombre'            => 'Nombre',
        'apellidos'         => 'Apellidos',
        'nro_documento'               => 'Número de documento',
        'telefono'          => 'Teléfono',
        'direccion'         => 'Dirección',
    ];

    foreach ($camposRequeridos as $campo => $nombre) {
        if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
            return ['valido' => false, 'mensaje' => "$nombre es obligatorio"];
        }
    }

    // Verificar sexo (acepta 'sexo' o 'id_sexo')
    $sexo = $datos['sexo'] ?? $datos['id_sexo'] ?? '';
    if (empty($sexo)) return ['valido' => false, 'mensaje' => 'Sexo es obligatorio'];

    // Verificar estado civil (acepta 'estado_civil' o 'id_estado_civil')
    $estadoCivil = $datos['estado_civil'] ?? $datos['id_estado_civil'] ?? '';
    if (empty($estadoCivil)) return ['valido' => false, 'mensaje' => 'Estado Civil es obligatorio'];

    return ['valido' => true];
}

// Helper para obtener el valor de sexo/estado_civil/grado sin importar el nombre del campo
function obtenerCampoFlex($datos, $nombre1, $nombre2) {
    return $datos[$nombre1] ?? $datos[$nombre2] ?? null;
}

/**
 * Odontologo responsable del formulario. Solo se tiene en cuenta si el usuario puede asignarlo
 * (permiso "Asignar odontologo responsable"); si no, el valor existente se conserva.
 * $incluir indica si el campo debe guardarse. Devuelve el id o null (sin asignar).
 */
function responsableDelFormulario($modelo, &$incluir) {
    $incluir = false;
    if (!Permisos::puede('pacientes.asignar_responsable')) return null;
    if (!array_key_exists('id_doctor_responsable', $_POST)) return null;
    $incluir = true;
    $v = $_POST['id_doctor_responsable'];
    if ($v === '' || $v === null) return null;
    $id = (int)$v;
    if ($id <= 0 || !$modelo->esOdontologoActivo($id)) {
        echo json_encode(['success' => false, 'mensaje' => 'El odontólogo responsable no es válido']);
        exit;
    }
    return $id;
}

/**
 * Valida y normaliza los datos del apoderado (documento segun su tipo).
 * Devuelve ['valido' => bool, 'mensaje' => string, 'datos' => array]
 */
function prepararApoderado($modelo, $datos) {
    $idTipo = intval($datos['id_tipo_documento'] ?? 1);
    $tipo   = $modelo->obtenerTipoDocumento($idTipo);
    $numero = strtoupper(trim($datos['nro_documento'] ?? ''));

    $v = validarDocumento($tipo, $numero);
    if (!$v['valido']) return ['valido' => false, 'mensaje' => 'Apoderado: ' . $v['mensaje']];

    $datos['id_tipo_documento'] = $idTipo;
    $datos['nro_documento']               = $numero;
    $datos['nombre']            = mb_strtoupper(trim($datos['nombre'] ?? ''), 'UTF-8');
    $datos['apellido']          = mb_strtoupper(trim($datos['apellido'] ?? ''), 'UTF-8');
    return ['valido' => true, 'datos' => $datos];
}

/**
 * Guarda al apoderado reutilizando el que ya exista con el mismo documento
 * (la tabla exige documento unico: un mismo apoderado puede serlo de varios pacientes).
 * Devuelve el id del apoderado, o null si no se pudo guardar.
 */
function guardarApoderado($modelo, $datos, $idActual = null) {
    $existente = $modelo->buscarApoderadoPorDocumento($datos['id_tipo_documento'], $datos['nro_documento']);
    if ($existente) {
        $modelo->editarApoderado($existente['id_apoderado'], $datos);
        return $existente['id_apoderado'];
    }
    if ($idActual) {
        $modelo->editarApoderado($idActual, $datos);
        return $idActual;
    }
    return $modelo->registrarApoderado($datos);
}

// Edad en anios cumplidos (null si no hay fecha valida)
function calcularEdadPaciente($fechaNacimiento) {
    if (empty($fechaNacimiento)) return null;
    try {
        return (new DateTime($fechaNacimiento))->diff(new DateTime('today'))->y;
    } catch (Exception $e) {
        return null;
    }
}

// =====================================================
// PROCESAMIENTO DE ACCIONES
// =====================================================
switch ($accion) {

    // Registrar nuevo paciente
    case 'registrar':
        $idTipoDoc = intval($_POST['id_tipo_documento'] ?? 1);
        $tipoDoc   = $modelo->obtenerTipoDocumento($idTipoDoc);
        $nro_documento       = strtoupper(trim($_POST['nro_documento'] ?? ''));

        $validacionDoc = validarDocumento($tipoDoc, $nro_documento);
        if (!$validacionDoc['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionDoc['mensaje']]); exit;
        }
        if ($modelo->existeDocumento($idTipoDoc, $nro_documento)) {
            echo json_encode(['success' => false, 'mensaje' => "El {$tipoDoc['abreviatura']} ya está registrado en el sistema"]); exit;
        }
        $correo = trim($_POST['correo'] ?? '');
        $correo = $correo === '' ? null : $correo;
        if (!empty($correo) && $modelo->existeCorreo($correo)) {
            echo json_encode(['success' => false, 'mensaje' => 'El correo ya está registrado en el sistema']); exit;
        }
        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]); exit;
        }

        $responsable         = responsableDelFormulario($modelo, $incluirResponsable);
        $nombre              = mb_strtoupper(trim($_POST['nombre']), 'UTF-8');
        $apellidos           = mb_strtoupper(trim($_POST['apellidos']), 'UTF-8');
        $telefono            = trim($_POST['telefono']);
        $direccion           = trim($_POST['direccion'] ?? '');
        $ocupacion           = trim($_POST['ocupacion'] ?? '');
        $fecha_nacimiento    = $_POST['fecha_nacimiento'] ?? null;
        // Acepta ambos nombres de campo
        $id_sexo             = obtenerCampoFlex($_POST, 'sexo', 'id_sexo');
        $id_estado_civil     = obtenerCampoFlex($_POST, 'estado_civil', 'id_estado_civil');
        $id_grado_instruccion = obtenerCampoFlex($_POST, 'grado_instruccion', 'id_grado_instruccion');
        $id_grado_instruccion = ($id_grado_instruccion === '' || $id_grado_instruccion === null) ? null : $id_grado_instruccion;
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

        // Validar al apoderado ANTES de guardar nada
        $datosApoderado = null;
        if (isset($_POST['datos_apoderado'])) {
            $datosApoderado = json_decode($_POST['datos_apoderado'], true);
            if ($datosApoderado) {
                $prep = prepararApoderado($modelo, $datosApoderado);
                if (!$prep['valido']) {
                    echo json_encode(['success' => false, 'mensaje' => $prep['mensaje']]); exit;
                }
                $datosApoderado = $prep['datos'];
            }
        }

        // Menores de edad: apoderado obligatorio. En adultos es opcional.
        $edad = calcularEdadPaciente($fecha_nacimiento);
        if ($edad !== null && $edad < 18 && empty($datosApoderado)) {
            echo json_encode(['success' => false, 'mensaje' => 'Debe registrar un apoderado para pacientes menores de edad']); exit;
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
        if ($datosApoderado) {
            $id_apoderado = guardarApoderado($modelo, $datosApoderado);
        }

        $datosPaciente = [
            'nombre'              => $nombre,
            'apellido'            => $apellidos,
            'id_tipo_documento'   => $idTipoDoc,
            'nro_documento'                 => $nro_documento,
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
            'id_apoderado'        => $id_apoderado,
            'id_doctor_responsable' => $responsable
        ];

        $resultado = $modelo->registrarPaciente($datosPaciente);
        if ($resultado) {
            if ($responsable) {
                registrarBitacora('paciente.asignar_responsable', 'paciente', (int)$resultado['id_paciente'], (int)$resultado['id_paciente'], ['antes' => null, 'despues' => $responsable]);
            }
            echo json_encode(['success' => true, 'id_paciente' => $resultado['id_paciente'], 'mensaje' => 'Paciente registrado exitosamente']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al registrar el paciente']);
        }
        break;

    // Editar paciente
    case 'editar':
        $id = intval($_POST['id_paciente'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID de paciente inválido']); exit;
        }

        $idTipoDoc = intval($_POST['id_tipo_documento'] ?? 1);
        $tipoDoc   = $modelo->obtenerTipoDocumento($idTipoDoc);
        $nro_documento       = strtoupper(trim($_POST['nro_documento'] ?? ''));

        $validacionDoc = validarDocumento($tipoDoc, $nro_documento);
        if (!$validacionDoc['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionDoc['mensaje']]); exit;
        }
        if ($modelo->existeDocumentoExceptoPaciente($idTipoDoc, $nro_documento, $id)) {
            echo json_encode(['success' => false, 'mensaje' => "El {$tipoDoc['abreviatura']} ya está registrado por otro paciente"]); exit;
        }

        $correo = trim($_POST['correo'] ?? '');
        $correo = $correo === '' ? null : $correo;
        if (!empty($correo) && $modelo->existeCorreoExceptoPaciente($correo, $id)) {
            echo json_encode(['success' => false, 'mensaje' => 'El correo ya está registrado por otro paciente']); exit;
        }

        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]); exit;
        }

        $responsable          = responsableDelFormulario($modelo, $incluirResponsable);
        $nombre               = mb_strtoupper(trim($_POST['nombre']), 'UTF-8');
        $apellidos            = mb_strtoupper(trim($_POST['apellidos']), 'UTF-8');
        $telefono             = trim($_POST['telefono']);
        $direccion            = trim($_POST['direccion'] ?? '');
        $ocupacion            = trim($_POST['ocupacion'] ?? '');
        $fecha_nacimiento     = $_POST['fecha_nacimiento'] ?? null;
        // Acepta ambos nombres de campo
        $id_sexo              = obtenerCampoFlex($_POST, 'sexo', 'id_sexo');
        $id_estado_civil      = obtenerCampoFlex($_POST, 'estado_civil', 'id_estado_civil');
        $id_grado_instruccion = obtenerCampoFlex($_POST, 'grado_instruccion', 'id_grado_instruccion');
        $id_grado_instruccion = ($id_grado_instruccion === '' || $id_grado_instruccion === null) ? null : $id_grado_instruccion;
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

        // Validar al apoderado ANTES de guardar nada
        $datosApoderado = null;
        if (isset($_POST['datos_apoderado'])) {
            $datosApoderado = json_decode($_POST['datos_apoderado'], true);
            if ($datosApoderado) {
                $prep = prepararApoderado($modelo, $datosApoderado);
                if (!$prep['valido']) {
                    echo json_encode(['success' => false, 'mensaje' => $prep['mensaje']]); exit;
                }
                $datosApoderado = $prep['datos'];
            }
        }

        // Menores de edad: apoderado obligatorio. En adultos es opcional.
        $edad = calcularEdadPaciente($fecha_nacimiento);
        $tieneApoderado = !empty($datosApoderado) || !empty($_POST['id_apoderado']);
        if ($edad !== null && $edad < 18 && !$tieneApoderado) {
            echo json_encode(['success' => false, 'mensaje' => 'Debe registrar un apoderado para pacientes menores de edad']); exit;
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
        // Sin datos de apoderado y sin id => el paciente queda sin apoderado
        // (asi se "quita" en adultos desde el formulario)
        $id_apoderado = null;
        if ($datosApoderado) {
            $idActual = !empty($_POST['id_apoderado']) ? intval($_POST['id_apoderado']) : null;
            $id_apoderado = guardarApoderado($modelo, $datosApoderado, $idActual);
        } elseif (!empty($_POST['id_apoderado'])) {
            $id_apoderado = $_POST['id_apoderado'];
        }

        $datosPaciente = [
            'nombre'               => $nombre,
            'apellido'             => $apellidos,
            'id_tipo_documento'    => $idTipoDoc,
            'nro_documento'                  => $nro_documento,
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
        $anterior = $modelo->verPaciente($id);
        if ($incluirResponsable) $datosPaciente['id_doctor_responsable'] = $responsable;

        $resultado = $modelo->editarPaciente($id, $datosPaciente);
        if ($resultado && $incluirResponsable && (int)($anterior['id_doctor_responsable'] ?? 0) !== (int)$responsable) {
            registrarBitacora('paciente.asignar_responsable', 'paciente', $id, $id,
                ['antes' => $anterior['id_doctor_responsable'] ?? null, 'despues' => $responsable]);
        }
        echo json_encode($resultado ?
            ['success' => true,  'mensaje' => 'Paciente actualizado exitosamente'] :
            ['success' => false, 'mensaje' => 'Error al actualizar el paciente']);
        break;

    // Ver apoderado
    case 'ver_apoderado':
        $id = $_GET['id'] ?? 0;
        $apoderado = $modelo->verApoderado($id);
        echo json_encode($apoderado ?: ['success' => false, 'mensaje' => 'Apoderado no encontrado']);
        break;

    // Listar pacientes
    case 'listar':
        echo json_encode($modelo->listarPacientes());
        break;

    // Ver detalle de paciente
    case 'ver':
        $id = $_GET['id'] ?? 0;
        $paciente = $modelo->verPaciente($id);
        if ($paciente) {
            $paciente['permisos'] = [
                'asignar_responsable' => Permisos::puede('pacientes.asignar_responsable'),
                'restablecer_portal'  => Permisos::puede('pacientes.restablecer_portal', (int)$id),
            ];
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

    // Restablecer el acceso al portal del paciente (borra su contrasena del portal)
    case 'restablecer_acceso_portal':
        // Accion que modifica datos: solo por POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']); exit;
        }
        $id = intval($_POST['id_paciente'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID de paciente inválido']); exit;
        }
        Permisos::requerir('pacientes.restablecer_portal', $id);
        $resultado = $modelo->restablecerAccesoPortal($id);
        if (!empty($resultado['success'])) {
            // Queda registrado en el log de PHP quien lo hizo
            registrarBitacora('portal.restablecer_acceso', 'paciente', $id, $id, ['tenia_acceso' => !empty($resultado['tenia_acceso'])]);
        }
        echo json_encode($resultado);
        break;

    case 'listar_doctores_responsables':
        echo json_encode([
            'success'       => true,
            'doctores'      => $modelo->listarDoctoresResponsables(),
            'puede_asignar' => Permisos::puede('pacientes.asignar_responsable')
        ]);
        break;

    case 'listar_tipos_documento':
        echo json_encode($modelo->listarTiposDocumento());
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