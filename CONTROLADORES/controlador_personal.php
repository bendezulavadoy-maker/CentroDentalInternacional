<?php
// Solo usuarios con sesion iniciada y permiso sobre el modulo Personal
require_once '../CONFIG/requerir_sesion_api.php';
requerirPermisoApi('personal');

require_once '../MODELOS/modelo_personal.php';
$modelo = new ModeloPersonal();

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

    $largo  = strlen($numero);
    $unidad = $soloNumeros ? 'dígitos' : 'caracteres';
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

function validarNombre($nombre, $campo = 'Nombre') {
    $nombre = trim($nombre);
    if (empty($nombre)) {
        return ['valido' => false, 'mensaje' => "$campo es obligatorio"];
    }
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/', $nombre)) {
        return ['valido' => false, 'mensaje' => "$campo solo puede contener letras, espacios y acentos"];
    }
    if (strlen($nombre) < 2) {
        return ['valido' => false, 'mensaje' => "$campo debe tener al menos 2 caracteres"];
    }
    return ['valido' => true];
}

function validarCorreo($correo) {
    $correo = trim($correo);
    if (empty($correo)) {
        return ['valido' => false, 'mensaje' => 'El correo es obligatorio'];
    }
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/', $correo)) {
        return ['valido' => false, 'mensaje' => 'El formato del correo no es válido'];
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['valido' => false, 'mensaje' => 'El correo electrónico no es válido'];
    }
    return ['valido' => true];
}

function validarCamposObligatorios($datos) {
    $camposRequeridos = ['nombre', 'apellidos', 'nro_documento', 'correo', 'id_rol', 'id_estado'];
    $etiquetas = [
        'nombre'    => 'Nombre',
        'apellidos' => 'Apellidos',
        'nro_documento'       => 'Número de documento',
        'correo'    => 'Correo',
        'id_rol'    => 'Rol',
        'id_estado' => 'Estado'
    ];

    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
            return ['valido' => false, 'mensaje' => $etiquetas[$campo] . ' es obligatorio'];
        }
    }
    return ['valido' => true];
}

// =====================================================
// PROCESAMIENTO DE ACCIONES
// =====================================================
switch ($accion) {

    // Registrar nuevo personal
    case 'registrar':
        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]);
            exit;
        }

        // Nombres siempre en mayusculas (con tildes y n correctas)
        $nombre    = mb_strtoupper(trim($_POST['nombre']), 'UTF-8');
        $apellidos = mb_strtoupper(trim($_POST['apellidos']), 'UTF-8');
        $correo    = trim($_POST['correo']);
        $id_rol    = $_POST['id_rol'];
        $id_estado = $_POST['id_estado'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

        // Documento: tipo + numero
        $idTipoDoc = intval($_POST['id_tipo_documento'] ?? 1);
        $tipoDoc   = $modelo->obtenerTipoDocumento($idTipoDoc);
        $nro_documento       = strtoupper(trim($_POST['nro_documento']));

        $validacionDoc = validarDocumento($tipoDoc, $nro_documento);
        if (!$validacionDoc['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionDoc['mensaje']]);
            exit;
        }
        if ($modelo->existeDocumento($idTipoDoc, $nro_documento)) {
            echo json_encode(['success' => false, 'mensaje' => "El {$tipoDoc['abreviatura']} ya está registrado en el sistema"]);
            exit;
        }

        $validacionNombre = validarNombre($nombre, 'Nombre');
        if (!$validacionNombre['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionNombre['mensaje']]);
            exit;
        }

        $validacionApellidos = validarNombre($apellidos, 'Apellidos');
        if (!$validacionApellidos['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionApellidos['mensaje']]);
            exit;
        }

        $validacionCorreo = validarCorreo($correo);
        if (!$validacionCorreo['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionCorreo['mensaje']]);
            exit;
        }

        if ($modelo->existeCorreo($correo)) {
            echo json_encode(['success' => false, 'mensaje' => 'El correo ya está registrado en el sistema']);
            exit;
        }

        // Procesar foto si existe
        $fotoRuta = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid('perfil_') . '.' . $ext;
            $carpetaDestino = '../IMAGENES/perfiles_personal/';
            if (!file_exists($carpetaDestino)) mkdir($carpetaDestino, 0777, true);
            $destino = $carpetaDestino . $nombreArchivo;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $fotoRuta = 'IMAGENES/perfiles_personal/' . $nombreArchivo;
            }
        }

        $resultado = $modelo->registrarPersonal($nombre, $apellidos, $idTipoDoc, $nro_documento, $correo, $id_rol, $id_estado, $fecha_nacimiento, $fotoRuta);

        if ($resultado) {
            echo json_encode([
                'success' => true,
                'id_usuario' => $resultado['id_usuario'],
                'codigo' => $resultado['codigo'],
                'contrasena' => $resultado['contrasena']
            ]);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al registrar el personal']);
        }
        break;

    // Listar personal
    case 'listar':
        echo json_encode($modelo->listarPersonal());
        break;

    // Ver detalle
    case 'ver':
        $id = $_GET['id'] ?? 0;
        echo json_encode($modelo->verPersonal($id));
        break;

    // Editar personal
    case 'editar':
        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]);
            exit;
        }

        $id        = intval($_POST['id_usuario'] ?? 0);
        $nombre    = mb_strtoupper(trim($_POST['nombre']), 'UTF-8');
        $apellidos = mb_strtoupper(trim($_POST['apellidos']), 'UTF-8');
        $correo    = trim($_POST['correo']);
        $id_rol    = $_POST['id_rol'];
        $id_estado = $_POST['id_estado'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
        $actualizarCodigo = ($_POST['actualizar_codigo'] ?? '0') === '1';

        if ($id <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID de usuario inválido']);
            exit;
        }

        // Documento: tipo + numero
        $idTipoDoc = intval($_POST['id_tipo_documento'] ?? 1);
        $tipoDoc   = $modelo->obtenerTipoDocumento($idTipoDoc);
        $nro_documento       = strtoupper(trim($_POST['nro_documento'] ?? ''));

        $validacionDoc = validarDocumento($tipoDoc, $nro_documento);
        if (!$validacionDoc['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionDoc['mensaje']]);
            exit;
        }
        // Antes no se comprobaba: un documento repetido terminaba en un error generico
        if ($modelo->existeDocumentoExceptoUsuario($idTipoDoc, $nro_documento, $id)) {
            echo json_encode(['success' => false, 'mensaje' => "El {$tipoDoc['abreviatura']} ya está registrado por otro usuario"]);
            exit;
        }

        $validacionNombre = validarNombre($nombre, 'Nombre');
        if (!$validacionNombre['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionNombre['mensaje']]);
            exit;
        }

        $validacionApellidos = validarNombre($apellidos, 'Apellidos');
        if (!$validacionApellidos['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionApellidos['mensaje']]);
            exit;
        }

        $validacionCorreo = validarCorreo($correo);
        if (!$validacionCorreo['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionCorreo['mensaje']]);
            exit;
        }

        if ($modelo->existeCorreoExceptoUsuario($correo, $id)) {
            echo json_encode(['success' => false, 'mensaje' => 'El correo ya está registrado por otro usuario']);
            exit;
        }

        // Procesar foto
        $fotoRuta = $_POST['foto_actual'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid('perfil_') . '.' . $ext;
            $carpetaDestino = '../IMAGENES/perfiles_personal/';
            if (!file_exists($carpetaDestino)) mkdir($carpetaDestino, 0777, true);
            $destino = $carpetaDestino . $nombreArchivo;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $fotoRuta = 'IMAGENES/perfiles_personal/' . $nombreArchivo;
            }
        }

        $resultado = $modelo->editarPersonal($id, $nombre, $apellidos, $idTipoDoc, $nro_documento, $correo, $id_rol, $id_estado, $fecha_nacimiento, $fotoRuta, $actualizarCodigo);

        if ($resultado) {
            echo json_encode([
                'success' => true,
                'codigo_usuario' => $resultado['codigo_usuario'] ?? null
            ]);
        } else {
            echo json_encode(['success' => false, 'mensaje' => 'Error al actualizar']);
        }
        break;

    case 'listar_tipos_documento':
        echo json_encode($modelo->listarTiposDocumento());
        break;

    // Listar roles
    case 'listar_roles':
        echo json_encode($modelo->listarRoles());
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>