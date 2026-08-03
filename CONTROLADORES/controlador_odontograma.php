<?php
require_once '../MODELOS/modelo_odontograma.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
    exit;
}

$id_usuario = $_SESSION['usuario']['id_usuario'];
$id_rol     = $_SESSION['usuario']['id_rol'];
$modelo     = new ModeloOdontograma();
$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar_versiones':
        $id_pac = intval($_GET['id_paciente'] ?? 0);
        echo json_encode($modelo->listarVersiones($id_pac));
        break;

    case 'crear_version':
        if ($id_rol != 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos pueden crear versiones']);
            break;
        }
        $id_pac = intval($_POST['id_paciente'] ?? 0);
        if ($id_pac === 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID de paciente inválido']);
            break;
        }
        $notas = trim($_POST['notas'] ?? '');
        echo json_encode($modelo->crearVersion($id_pac, $id_usuario, $notas));
        break;

    case 'cerrar_version':
        if ($id_rol != 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos']);
            break;
        }
        $id_ver = intval($_POST['id_version'] ?? 0);
        echo json_encode($modelo->cerrarVersion($id_ver, $id_usuario));
        break;
    case 'eliminar_version':
        if ($id_rol != 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos']);
            break;
        }
        $id_ver = intval($_POST['id_version'] ?? 0);
        try {
            $pdo = (new Conexion())->getConexion();
            $chk = $pdo->prepare("SELECT id_version, creado_por, estado FROM odontograma_versiones WHERE id_version = :id");
            $chk->execute([':id' => $id_ver]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            error_log("Versión en BD: " . json_encode($row));
            error_log("ID usuario sesión: $id_usuario");
        } catch(Exception $e) {}
        
        echo json_encode($modelo->eliminarVersion($id_ver, $id_usuario));
        break;

    case 'actualizar_notas':
        if ($id_rol != 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos']);
            break;
        }
        $id_ver = intval($_POST['id_version'] ?? 0);
        $notas  = trim($_POST['notas'] ?? '');
        echo json_encode($modelo->actualizarNotas($id_ver, $notas, $id_usuario));
        break;

    case 'cargar_hallazgos':
        $id_ver = intval($_GET['id_version'] ?? 0);
        echo json_encode($modelo->cargarHallazgos($id_ver));
        break;

        case 'guardar_hallazgo':
        if ($id_rol != 2) { 
            echo json_encode(['success'=>false,'mensaje'=>'Solo odontólogos']); 
            break; 
        }
        $result = $modelo->guardarHallazgo(
            intval($_POST['id_version']  ?? 0),
            intval($_POST['id_diente']   ?? 0),
            intval($_POST['id_estado']   ?? 0),
            $_POST['cara']        ?? 'RECUADRO',
            $_POST['color']       ?? 'azul',
            $_POST['sigla']       ?? null,
            $_POST['observacion'] ?? null,
            $id_usuario
        );
        // Debug temporal
        error_log("guardarHallazgo POST: " . json_encode($_POST));
        error_log("guardarHallazgo result: " . json_encode($result));
        echo json_encode($result);
        break;

    case 'borrar_hallazgo':
        if ($id_rol != 2) {
            echo json_encode(['success' => false, 'mensaje' => 'Solo odontólogos']);
            break;
        }
        echo json_encode($modelo->borrarHallazgo(
            intval($_POST['id_version'] ?? 0),
            intval($_POST['id_diente']  ?? 0),
            $_POST['cara'] ?? null,
            $id_usuario
        ));
        break;

    case 'listar_estados':
        echo json_encode($modelo->listarEstados());
        break;

    case 'listar_dientes':
        echo json_encode($modelo->listarDientes());
        break;

    default:
        echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
}
