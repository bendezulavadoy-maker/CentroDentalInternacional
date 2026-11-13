<?php
// Cambiar la propia contrasena. Exige sesion, pero NO pasa por la compuerta: es justo el lugar donde se cambia.
require_once __DIR__ . '/../CONFIG/requerir_sesion_api.php';
require_once __DIR__ . '/../CONFIG/contrasenas.php';
require_once __DIR__ . '/../CONFIG/limite_intentos.php';
require_once __DIR__ . '/../CONFIG/bitacora.php';
require_once __DIR__ . '/../MODELOS/modelo_contrasena.php';

header('Content-Type: application/json; charset=utf-8');

$idUsuario = (int)$_SESSION['usuario']['id_usuario'];
$accion    = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    // Datos para la pantalla (si el cambio es obligatorio y las reglas)
    case 'estado':
        echo json_encode([
            'success'     => true,
            'obligatorio' => !empty($_SESSION['usuario']['debe_cambiar_contrasena']),
            'minimo'      => Contrasenas::MINIMO,
        ]);
        break;

    case 'cambiar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
            break;
        }

        // Quien adivina la clave actual desde una sesion abierta tambien se frena
        $claveLimite = 'cambio:' . $idUsuario;
        if (LimiteIntentos::bloqueado($claveLimite, 5, 15)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'mensaje' => 'Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.']);
            break;
        }

        $actual    = (string)($_POST['actual']    ?? '');
        $nueva     = (string)($_POST['nueva']     ?? '');
        $confirmar = (string)($_POST['confirmar'] ?? '');

        $modelo  = new ModeloContrasena();
        $usuario = $modelo->obtenerUsuario($idUsuario);
        if (!$usuario) {
            echo json_encode(['success' => false, 'mensaje' => 'No se encontró tu cuenta.']);
            break;
        }

        if ($actual === '' || !password_verify($actual, $usuario['contrasena'])) {
            LimiteIntentos::registrar($claveLimite);
            echo json_encode(['success' => false, 'mensaje' => 'La contraseña actual no es correcta.']);
            break;
        }
        if ($nueva !== $confirmar) {
            echo json_encode(['success' => false, 'mensaje' => 'La confirmación no coincide con la contraseña nueva.']);
            break;
        }
        $errores = Contrasenas::validarNueva($nueva, $actual, (string)$usuario['codigo_usuario'], (string)$usuario['nro_documento']);
        if ($errores) {
            echo json_encode(['success' => false, 'mensaje' => $errores[0], 'errores' => $errores]);
            break;
        }

        $eraObligatorio = !empty($_SESSION['usuario']['debe_cambiar_contrasena']);
        if (!$modelo->guardarCambio($idUsuario, Contrasenas::cifrar($nueva))) {
            echo json_encode(['success' => false, 'mensaje' => 'No se pudo guardar la contraseña. Inténtalo de nuevo.']);
            break;
        }

        LimiteIntentos::limpiar($claveLimite);
        $_SESSION['usuario']['debe_cambiar_contrasena'] = 0;
        session_regenerate_id(true);                       // nueva sesion tras un cambio de credenciales
        registrarBitacora('usuario.cambiar_contrasena', 'usuario', $idUsuario, null, ['obligatorio' => $eraObligatorio]);

        echo json_encode(['success' => true, 'mensaje' => 'Tu contraseña se cambió correctamente.', 'redirigir' => '../VISTAS/vista_panel_admin.php']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
}
?>
