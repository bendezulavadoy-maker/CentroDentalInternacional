<?php
session_start();
require_once '../MODELOS/modelo_documentos.php';

header('Content-Type: application/json; charset=utf-8');

$modelo = new ModeloDocumentos();

// Obtener ID del usuario de la sesión
$idUsuario = $_SESSION['usuario']['id_usuario'] ?? null;

if (!$idUsuario) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Usuario no autenticado'
    ]);
    exit;
}

// Obtener acción
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$accion = $data['accion'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';

try {
    switch ($accion) {

        // =====================================================
        // 📁 GESTIÓN DE CARPETAS
        // =====================================================

        case 'inicializar_estructura':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                throw new Exception('ID de paciente no proporcionado');
            }

            // Verificar si ya existe estructura
            if ($modelo->existeEstructura($idPaciente)) {
                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Estructura ya existe'
                ]);
                exit;
            }

            // Crear estructura inicial
            $resultado = $modelo->crearEstructuraInicial($idPaciente, $idUsuario);
            
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Estructura creada correctamente' : 'Error al crear estructura'
            ]);
            break;

        case 'obtener_estructura':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                throw new Exception('ID de paciente no proporcionado');
            }

            // Verificar/crear estructura si no existe
            if (!$modelo->existeEstructura($idPaciente)) {
                $modelo->crearEstructuraInicial($idPaciente, $idUsuario);
            }

            $estructura = $modelo->obtenerEstructuraCarpetas($idPaciente);
            
            echo json_encode([
                'success' => true,
                'estructura' => $estructura
            ]);
            break;

        case 'obtener_carpetas_raiz':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                throw new Exception('ID de paciente no proporcionado');
            }

            $carpetas = $modelo->obtenerCarpetasRaiz($idPaciente);
            
            echo json_encode([
                'success' => true,
                'carpetas' => $carpetas
            ]);
            break;

        case 'obtener_subcarpetas':
            $idCarpeta = $_GET['id_carpeta'] ?? 0;
            
            if (!$idCarpeta) {
                throw new Exception('ID de carpeta no proporcionado');
            }

            $subcarpetas = $modelo->obtenerSubcarpetas($idCarpeta);
            
            echo json_encode([
                'success' => true,
                'subcarpetas' => $subcarpetas
            ]);
            break;

        case 'obtener_carpeta':
            $idCarpeta = $_GET['id_carpeta'] ?? 0;
            
            if (!$idCarpeta) {
                throw new Exception('ID de carpeta no proporcionado');
            }

            $carpeta = $modelo->obtenerCarpeta($idCarpeta);
            
            echo json_encode([
                'success' => true,
                'carpeta' => $carpeta
            ]);
            break;

        case 'crear_carpeta':
            if (!isset($data['nombre_carpeta']) || !isset($data['id_paciente'])) {
                throw new Exception('Datos incompletos');
            }

            $datos = [
                'nombre_carpeta' => $data['nombre_carpeta'],
                'id_carpeta_padre' => $data['id_carpeta_padre'] ?? null,
                'id_paciente' => $data['id_paciente'],
                'icono' => $data['icono'] ?? '📁',
                'color' => $data['color'] ?? '#3498db',
                'orden' => $data['orden'] ?? 0,
                'creado_por' => $idUsuario
            ];

            $idCarpeta = $modelo->crearCarpeta($datos);
            
            if ($idCarpeta) {
                $carpeta = $modelo->obtenerCarpeta($idCarpeta);
                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Carpeta creada correctamente',
                    'carpeta' => $carpeta
                ]);
            } else {
                throw new Exception('Error al crear carpeta');
            }
            break;

        case 'editar_carpeta':
            if (!isset($data['id_carpeta']) || !isset($data['nombre_carpeta'])) {
                throw new Exception('Datos incompletos');
            }

            $idCarpeta = $data['id_carpeta'];
            $datos = [
                'nombre_carpeta' => $data['nombre_carpeta'],
                'icono' => $data['icono'] ?? '📁',
                'color' => $data['color'] ?? '#3498db',
                'orden' => $data['orden'] ?? 0
            ];

            $resultado = $modelo->editarCarpeta($idCarpeta, $datos, $idUsuario);
            
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Carpeta editada correctamente' : 'Error al editar carpeta'
            ]);
            break;

        case 'eliminar_carpeta':
            if (!isset($data['id_carpeta'])) {
                throw new Exception('ID de carpeta no proporcionado');
            }

            $resultado = $modelo->eliminarCarpeta($data['id_carpeta'], $idUsuario);
            
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Carpeta eliminada correctamente' : 'Error al eliminar carpeta'
            ]);
            break;

        case 'mover_carpeta':
            if (!isset($data['id_carpeta']) || !array_key_exists('id_carpeta_destino', $data)) {
                throw new Exception('Datos incompletos');
            }

            $resultado = $modelo->moverCarpeta(
                $data['id_carpeta'],
                $data['id_carpeta_destino'],
                $idUsuario
            );
            
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Carpeta movida correctamente' : 'Error al mover carpeta'
            ]);
            break;

        case 'copiar_carpeta':
            if (!isset($data['id_carpeta']) || !isset($data['id_paciente'])) {
                throw new Exception('Datos incompletos');
            }

            $idNueva = $modelo->copiarCarpeta(
                $data['id_carpeta'],
                $data['id_carpeta_destino'] ?? null,
                $data['id_paciente'],
                $idUsuario
            );

            echo json_encode([
                'success' => (bool)$idNueva,
                'mensaje' => $idNueva ? 'Carpeta copiada correctamente' : 'Error al copiar carpeta',
                'id_carpeta' => $idNueva
            ]);
            break;

        // =====================================================
        // 📄 GESTIÓN DE DOCUMENTOS
        // =====================================================

        case 'obtener_documentos_carpeta':
            $idCarpeta = $_GET['id_carpeta'] ?? 0;
            
            if (!$idCarpeta) {
                throw new Exception('ID de carpeta no proporcionado');
            }

            $documentos = $modelo->obtenerDocumentosCarpeta($idCarpeta);
            
            echo json_encode([
                'success' => true,
                'documentos' => $documentos
            ]);
            break;

        case 'obtener_documentos_paciente':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            $limit = $_GET['limit'] ?? null;
            
            if (!$idPaciente) {
                throw new Exception('ID de paciente no proporcionado');
            }

            $documentos = $modelo->obtenerDocumentosPaciente($idPaciente, $limit);
            
            echo json_encode([
                'success' => true,
                'documentos' => $documentos
            ]);
            break;

        case 'subir_documento':
            // Manejo de archivos subidos
            if (!isset($_FILES['archivo']) || !isset($_POST['id_paciente'])) {
                throw new Exception('Datos incompletos');
            }

            $archivo = $_FILES['archivo'];
            $idPaciente = $_POST['id_paciente'];
            $idCarpeta = $_POST['id_carpeta'] ?? null;
            $titulo = $_POST['titulo'] ?? $archivo['name'];
            $descripcion = $_POST['descripcion'] ?? null;

            // Validar archivo
            if ($archivo['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al subir el archivo');
            }

            // Validar tamaño (máx. 10MB)
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($archivo['size'] > $maxSize) {
                throw new Exception('El archivo es demasiado grande. Máximo 10MB');
            }

            // Extensiones permitidas
            $extensionesPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $extensionesPermitidas)) {
                throw new Exception('Tipo de archivo no permitido');
            }

            // Crear directorio si no existe
            $directorioBase = '../ARCHIVOS/documentos_pacientes/' . $idPaciente . '/';
            if (!file_exists($directorioBase)) {
                mkdir($directorioBase, 0755, true);
            }

            // Generar nombre único
            $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
            $rutaDestino = $directorioBase . $nombreArchivo;

            // Mover archivo
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                throw new Exception('Error al guardar el archivo');
            }

            // Guardar en base de datos
            $datos = [
                'id_paciente' => $idPaciente,
                'id_carpeta' => $idCarpeta,
                'titulo' => $titulo,
                'archivo' => 'ARCHIVOS/documentos_pacientes/' . $idPaciente . '/' . $nombreArchivo,
                'tipo' => $archivo['type'],
                'extension' => $extension,
                'tamano_archivo' => $archivo['size'],
                'descripcion' => $descripcion,
                'subido_por' => $idUsuario
            ];

            $idDocumento = $modelo->subirDocumento($datos);
            
            if ($idDocumento) {
                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Documento subido correctamente',
                    'id_documento' => $idDocumento
                ]);
            } else {
                // Eliminar archivo si falla la BD
                unlink($rutaDestino);
                throw new Exception('Error al guardar el documento en la base de datos');
            }
            break;

        case 'editar_documento':
            if (!isset($data['id_documento']) || !isset($data['titulo'])) {
                throw new Exception('Datos incompletos');
            }

            $datos = [
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null
            ];

            $resultado = $modelo->editarDocumento($data['id_documento'], $datos, $idUsuario);
            
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Documento editado correctamente' : 'Error al editar documento'
            ]);
            break;

        case 'mover_documento':
            if (!isset($data['id_documento']) || !array_key_exists('id_carpeta_destino', $data)) {
                throw new Exception('Datos incompletos');
            }

            $resultado = $modelo->moverDocumento(
                $data['id_documento'],
                $data['id_carpeta_destino'],
                $idUsuario
            );
            
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Documento movido correctamente' : 'Error al mover documento'
            ]);
            break;

        case 'copiar_documento':
            if (!isset($data['id_documento'])) {
                throw new Exception('ID de documento no proporcionado');
            }

            $idNuevo = $modelo->copiarDocumento(
                $data['id_documento'],
                $data['id_carpeta_destino'] ?? null,
                $idUsuario
            );

            echo json_encode([
                'success' => (bool)$idNuevo,
                'mensaje' => $idNuevo ? 'Documento copiado correctamente' : 'Error al copiar documento',
                'id_documento' => $idNuevo
            ]);
            break;

        case 'eliminar_documento':
            if (!isset($data['id_documento'])) {
                throw new Exception('ID de documento no proporcionado');
            }

            $resultado = $modelo->eliminarDocumento($data['id_documento'], $idUsuario);
            
            echo json_encode([
                'success' => $resultado,
                'mensaje' => $resultado ? 'Documento eliminado correctamente' : 'Error al eliminar documento'
            ]);
            break;

        case 'registrar_descarga':
            if (!isset($data['id_documento'])) {
                throw new Exception('ID de documento no proporcionado');
            }

            $modelo->registrarDescarga($data['id_documento'], $idUsuario);
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Descarga registrada'
            ]);
            break;

        // =====================================================
        // 📊 ESTADÍSTICAS
        // =====================================================

        case 'obtener_estadisticas':
            $idPaciente = $_GET['id_paciente'] ?? 0;
            
            if (!$idPaciente) {
                throw new Exception('ID de paciente no proporcionado');
            }

            $estadisticas = $modelo->obtenerEstadisticas($idPaciente);
            
            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
            break;

        // =====================================================
        // 📝 HISTORIAL
        // =====================================================

        case 'obtener_historial_carpeta':
            $idCarpeta = $_GET['id_carpeta'] ?? 0;
            $limit = $_GET['limit'] ?? 10;
            
            if (!$idCarpeta) {
                throw new Exception('ID de carpeta no proporcionado');
            }

            $historial = $modelo->obtenerHistorialCarpeta($idCarpeta, $limit);
            
            echo json_encode([
                'success' => true,
                'historial' => $historial
            ]);
            break;

        case 'obtener_historial_documento':
            $idDocumento = $_GET['id_documento'] ?? 0;
            $limit = $_GET['limit'] ?? 10;
            
            if (!$idDocumento) {
                throw new Exception('ID de documento no proporcionado');
            }

            $historial = $modelo->obtenerHistorialDocumento($idDocumento, $limit);
            
            echo json_encode([
                'success' => true,
                'historial' => $historial
            ]);
            break;

        default:
            throw new Exception('Acción no válida: ' . $accion);
    }

} catch (Exception $e) {
    error_log("❌ Error en controlador_documentos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>