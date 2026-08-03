<?php
require_once '../MODELOS/modelo_personal.php';
$modelo = new ModeloPersonal();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// =====================================================
// 🔍 FUNCIONES DE VALIDACIÓN
// =====================================================
function validarDNI($dni) {
    // Debe tener exactamente 8 dígitos
    if (!preg_match('/^[0-9]{8}$/', $dni)) {
        return ['valido' => false, 'mensaje' => 'El DNI debe tener exactamente 8 dígitos numéricos'];
    }
    
    // No permitir valores inválidos como 00000000
    $dniInvalidos = ['00000000', '11111111', '22222222', '33333333', '44444444', 
                     '55555555', '66666666', '77777777', '88888888', '99999999'];
    if (in_array($dni, $dniInvalidos)) {
        return ['valido' => false, 'mensaje' => 'El DNI ingresado no es válido'];
    }
    
    return ['valido' => true];
}

function validarNombre($nombre, $campo = 'Nombre') {
    // Verificar que no esté vacío
    $nombre = trim($nombre);
    if (empty($nombre)) {
        return ['valido' => false, 'mensaje' => "$campo es obligatorio"];
    }
    
    // Solo letras, espacios, acentos y ñ
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/', $nombre)) {
        return ['valido' => false, 'mensaje' => "$campo solo puede contener letras, espacios y acentos"];
    }
    
    // Mínimo 2 caracteres
    if (strlen($nombre) < 2) {
        return ['valido' => false, 'mensaje' => "$campo debe tener al menos 2 caracteres"];
    }
    
    return ['valido' => true];
}

function validarCorreo($correo) {
    // Verificar que no esté vacío
    $correo = trim($correo);
    if (empty($correo)) {
        return ['valido' => false, 'mensaje' => 'El correo es obligatorio'];
    }
    
    // Validar formato con regex
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/', $correo)) {
        return ['valido' => false, 'mensaje' => 'El formato del correo no es válido'];
    }
    
    // Validación adicional con filter_var
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['valido' => false, 'mensaje' => 'El correo electrónico no es válido'];
    }
    
    return ['valido' => true];
}

function validarCamposObligatorios($datos) {
    $camposRequeridos = ['nombre', 'apellidos', 'dni', 'correo', 'id_rol', 'id_estado'];
    
    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
            $nombreCampo = [
                'nombre' => 'Nombre',
                'apellidos' => 'Apellidos',
                'dni' => 'DNI',
                'correo' => 'Correo',
                'id_rol' => 'Rol',
                'id_estado' => 'Estado'
            ][$campo];
            return ['valido' => false, 'mensaje' => "$nombreCampo es obligatorio"];
        }
    }
    
    return ['valido' => true];
}

// =====================================================
// 📋 PROCESAMIENTO DE ACCIONES
// =====================================================
switch ($accion) {

    // 🔹 Registrar nuevo personal
    case 'registrar':
        // Validar campos obligatorios
        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]);
            exit;
        }

        $nombre = trim($_POST['nombre']);
        $apellidos = trim($_POST['apellidos']);
        $dni = trim($_POST['dni']);
        $correo = trim($_POST['correo']);
        $id_rol = $_POST['id_rol'];
        $id_estado = $_POST['id_estado'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

        // Validar DNI
        $validacionDNI = validarDNI($dni);
        if (!$validacionDNI['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionDNI['mensaje']]);
            exit;
        }

        // Validar que DNI sea único
        if ($modelo->existeDNI($dni)) {
            echo json_encode(['success' => false, 'mensaje' => 'El DNI ya está registrado en el sistema']);
            exit;
        }

        // Validar nombre
        $validacionNombre = validarNombre($nombre, 'Nombre');
        if (!$validacionNombre['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionNombre['mensaje']]);
            exit;
        }

        // Validar apellidos
        $validacionApellidos = validarNombre($apellidos, 'Apellidos');
        if (!$validacionApellidos['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionApellidos['mensaje']]);
            exit;
        }

        // Validar correo
        $validacionCorreo = validarCorreo($correo);
        if (!$validacionCorreo['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionCorreo['mensaje']]);
            exit;
        }

        // Validar que correo sea único
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

        // Registrar personal
        $resultado = $modelo->registrarPersonal($nombre, $apellidos, $dni, $correo, $id_rol, $id_estado, $fecha_nacimiento, $fotoRuta);
        
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

    // 🔹 Listar personal
    case 'listar':
        echo json_encode($modelo->listarPersonal());
        break;

    // 🔹 Ver detalle
    case 'ver':
        $id = $_GET['id'] ?? 0;
        echo json_encode($modelo->verPersonal($id));
        break;

    // 🔹 Editar personal
    case 'editar':
        // Validar campos obligatorios
        $validacionObligatorios = validarCamposObligatorios($_POST);
        if (!$validacionObligatorios['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionObligatorios['mensaje']]);
            exit;
        }

        $id = $_POST['id_usuario'];
        $nombre = trim($_POST['nombre']);
        $apellidos = trim($_POST['apellidos']);
        $correo = trim($_POST['correo']);
        $id_rol = $_POST['id_rol'];
        $id_estado = $_POST['id_estado'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
        
        // Validar nombre
        $validacionNombre = validarNombre($nombre, 'Nombre');
        if (!$validacionNombre['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionNombre['mensaje']]);
            exit;
        }

        // Validar apellidos
        $validacionApellidos = validarNombre($apellidos, 'Apellidos');
        if (!$validacionApellidos['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionApellidos['mensaje']]);
            exit;
        }

        // Validar correo
        $validacionCorreo = validarCorreo($correo);
        if (!$validacionCorreo['valido']) {
            echo json_encode(['success' => false, 'mensaje' => $validacionCorreo['mensaje']]);
            exit;
        }

        // Validar que correo sea único (excepto para el mismo usuario)
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

        // Actualizar personal
        $resultado = $modelo->editarPersonal($id, $nombre, $apellidos, $correo, $id_rol, $id_estado, $fecha_nacimiento, $fotoRuta);
        
        echo json_encode($resultado ? ['success' => true] : ['success' => false, 'mensaje' => 'Error al actualizar']);
        break;

    // 🔹 Listar roles
    case 'listar_roles':
        echo json_encode($modelo->listarRoles());
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
        break;
}
?>