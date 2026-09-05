<?php
require_once '../CONFIG/conexion.php';

class ModeloDocumentos {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // 📁 GESTIÓN DE CARPETAS
    // =====================================================

    /**
     * Crear estructura inicial de carpetas para un paciente
     */
    public function crearEstructuraInicial($idPaciente, $idUsuario) {
        try {
            $sql = "CALL crear_estructura_carpetas_inicial(:id_paciente, :id_usuario)";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':id_paciente' => $idPaciente,
                ':id_usuario' => $idUsuario
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al crear estructura inicial: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si existe estructura de carpetas
     */
    public function existeEstructura($idPaciente) {
        try {
            $sql = "SELECT COUNT(*) as total 
                    FROM carpetas_documentos 
                    WHERE id_paciente = :id_paciente AND activo = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_paciente' => $idPaciente]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("❌ Error al verificar estructura: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estructura completa de carpetas (árbol jerárquico)
     */
    public function obtenerEstructuraCarpetas($idPaciente, $idCarpetaPadre = null) {
        try {
            $sql = "SELECT * FROM vista_carpetas_completa 
                    WHERE id_paciente = :id_paciente ";
            
            if ($idCarpetaPadre === null) {
                $sql .= "AND id_carpeta_padre IS NULL ";
            } else {
                $sql .= "AND id_carpeta_padre = :id_carpeta_padre ";
            }
            
            $sql .= "ORDER BY orden ASC, nombre_carpeta ASC";
            
            $stmt = $this->conexion->prepare($sql);
            $params = [':id_paciente' => $idPaciente];
            
            if ($idCarpetaPadre !== null) {
                $params[':id_carpeta_padre'] = $idCarpetaPadre;
            }
            
            $stmt->execute($params);
            $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener subcarpetas recursivamente
            foreach ($carpetas as &$carpeta) {
                $carpeta['subcarpetas'] = $this->obtenerEstructuraCarpetas($idPaciente, $carpeta['id_carpeta']);
            }
            
            return $carpetas;
        } catch (PDOException $e) {
            error_log("❌ Error al obtener estructura: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener carpetas raíz (nivel superior)
     */
    public function obtenerCarpetasRaiz($idPaciente) {
        try {
            $sql = "SELECT * FROM vista_carpetas_completa 
                    WHERE id_paciente = :id_paciente 
                    AND id_carpeta_padre IS NULL
                    ORDER BY orden ASC, nombre_carpeta ASC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_paciente' => $idPaciente]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener carpetas raíz: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener subcarpetas de una carpeta
     */
    public function obtenerSubcarpetas($idCarpeta) {
        try {
            $sql = "SELECT * FROM vista_carpetas_completa 
                    WHERE id_carpeta_padre = :id_carpeta
                    ORDER BY orden ASC, nombre_carpeta ASC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_carpeta' => $idCarpeta]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener subcarpetas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener información de una carpeta específica
     */
    public function obtenerCarpeta($idCarpeta) {
        try {
            $sql = "SELECT * FROM vista_carpetas_completa WHERE id_carpeta = :id_carpeta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_carpeta' => $idCarpeta]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener carpeta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear nueva carpeta
     */
    public function crearCarpeta($datos) {
        try {
            $this->conexion->beginTransaction();

            $sql = "INSERT INTO carpetas_documentos 
                    (nombre_carpeta, id_carpeta_padre, id_paciente, es_sistema, 
                     orden, icono, color, creado_por) 
                    VALUES 
                    (:nombre, :id_padre, :id_paciente, :es_sistema, 
                     :orden, :icono, :color, :creado_por)";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':nombre' => $datos['nombre_carpeta'],
                ':id_padre' => $datos['id_carpeta_padre'] ?? null,
                ':id_paciente' => $datos['id_paciente'],
                ':es_sistema' => $datos['es_sistema'] ?? 0,
                ':orden' => $datos['orden'] ?? 0,
                ':icono' => $datos['icono'] ?? '📁',
                ':color' => $datos['color'] ?? '#3498db',
                ':creado_por' => $datos['creado_por']
            ]);

            $idCarpeta = $this->conexion->lastInsertId();

            // Registrar en historial
            $this->registrarHistorialCarpeta($idCarpeta, 'crear', $datos['creado_por'], [
                'nombre' => $datos['nombre_carpeta']
            ]);

            $this->conexion->commit();
            return $idCarpeta;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al crear carpeta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Editar carpeta
     */
    public function editarCarpeta($idCarpeta, $datos, $idUsuario) {
        try {
            $this->conexion->beginTransaction();

            // Obtener datos anteriores para historial
            $carpetaAnterior = $this->obtenerCarpeta($idCarpeta);

            $sql = "UPDATE carpetas_documentos 
                    SET nombre_carpeta = :nombre,
                        icono = :icono,
                        color = :color,
                        orden = :orden,
                        editado_por = :editado_por
                    WHERE id_carpeta = :id_carpeta";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([
                ':nombre' => $datos['nombre_carpeta'],
                ':icono' => $datos['icono'] ?? '📁',
                ':color' => $datos['color'] ?? '#3498db',
                ':orden' => $datos['orden'] ?? 0,
                ':editado_por' => $idUsuario,
                ':id_carpeta' => $idCarpeta
            ]);

            // Registrar cambios
            $this->registrarHistorialCarpeta($idCarpeta, 'editar', $idUsuario, [
                'anterior' => $carpetaAnterior,
                'nuevo' => $datos
            ]);

            $this->conexion->commit();
            return $resultado;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al editar carpeta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar carpeta (soft delete)
     */
    public function eliminarCarpeta($idCarpeta, $idUsuario) {
        try {
            $this->conexion->beginTransaction();

            // Verificar si es carpeta del sistema
            $carpeta = $this->obtenerCarpeta($idCarpeta);
            if ($carpeta && $carpeta['es_sistema']) {
                throw new Exception("No se puede eliminar una carpeta del sistema");
            }

            $sql = "UPDATE carpetas_documentos 
                    SET activo = 0, editado_por = :editado_por
                    WHERE id_carpeta = :id_carpeta";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([
                ':editado_por' => $idUsuario,
                ':id_carpeta' => $idCarpeta
            ]);

            $this->registrarHistorialCarpeta($idCarpeta, 'eliminar', $idUsuario, [
                'carpeta' => $carpeta
            ]);

            $this->conexion->commit();
            return $resultado;
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al eliminar carpeta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mover carpeta a otra ubicación
     */
    public function moverCarpeta($idCarpeta, $idCarpetaDestino, $idUsuario) {
        try {
            $this->conexion->beginTransaction();

            $carpetaAnterior = $this->obtenerCarpeta($idCarpeta);

            $sql = "UPDATE carpetas_documentos 
                    SET id_carpeta_padre = :id_destino,
                        editado_por = :editado_por
                    WHERE id_carpeta = :id_carpeta";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([
                ':id_destino' => $idCarpetaDestino,
                ':editado_por' => $idUsuario,
                ':id_carpeta' => $idCarpeta
            ]);

            $this->registrarHistorialCarpeta($idCarpeta, 'mover', $idUsuario, [
                'carpeta_anterior' => $carpetaAnterior['nombre_carpeta_padre'] ?? 'Raíz',
                'carpeta_nueva' => $idCarpetaDestino
            ]);

            $this->conexion->commit();
            return $resultado;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al mover carpeta: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 📄 GESTIÓN DE DOCUMENTOS
    // =====================================================

    /**
     * Obtener documentos de una carpeta
     */
    public function obtenerDocumentosCarpeta($idCarpeta) {
        try {
            $sql = "SELECT * FROM vista_documentos_completa 
                    WHERE id_carpeta = :id_carpeta
                    ORDER BY fecha_subida DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_carpeta' => $idCarpeta]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener documentos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todos los documentos de un paciente
     */
    public function obtenerDocumentosPaciente($idPaciente, $limit = null) {
        try {
            $sql = "SELECT * FROM vista_documentos_completa 
                    WHERE id_paciente = :id_paciente
                    ORDER BY fecha_subida DESC";
            
            if ($limit) {
                $sql .= " LIMIT :limit";
            }
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_paciente', $idPaciente, PDO::PARAM_INT);
            
            if ($limit) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener documentos del paciente: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Subir nuevo documento
     */
    public function subirDocumento($datos) {
        try {
            $this->conexion->beginTransaction();

            $sql = "INSERT INTO documentos_paciente 
                    (id_paciente, id_carpeta, titulo, archivo, tipo, 
                     extension, tamano_archivo, descripcion, subido_por) 
                    VALUES 
                    (:id_paciente, :id_carpeta, :titulo, :archivo, :tipo,
                     :extension, :tamano, :descripcion, :subido_por)";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':id_paciente' => $datos['id_paciente'],
                ':id_carpeta' => $datos['id_carpeta'] ?? null,
                ':titulo' => $datos['titulo'],
                ':archivo' => $datos['archivo'],
                ':tipo' => $datos['tipo'],
                ':extension' => $datos['extension'],
                ':tamano' => $datos['tamano_archivo'],
                ':descripcion' => $datos['descripcion'] ?? null,
                ':subido_por' => $datos['subido_por']
            ]);

            $idDocumento = $this->conexion->lastInsertId();

            $this->registrarHistorialDocumento($idDocumento, 'subir', $datos['subido_por'], [
                'titulo' => $datos['titulo'],
                'carpeta' => $datos['id_carpeta']
            ]);

            $this->conexion->commit();
            return $idDocumento;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al subir documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copiar un documento (duplica el archivo físico + el registro) a otra carpeta
     */
    public function copiarDocumento($idDocumento, $idCarpetaDestino, $idUsuario) {
        try {
            $original = $this->obtenerDocumento($idDocumento);
            if (!$original) throw new Exception('Documento original no encontrado');

            // Duplicar el archivo físico en disco
            $rutaOriginal = '../' . $original['archivo'];
            $extension = $original['extension'];
            $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
            $directorio = dirname($rutaOriginal) . '/';
            $rutaNueva = $directorio . $nombreArchivo;

            if (!file_exists($rutaOriginal) || !copy($rutaOriginal, $rutaNueva)) {
                throw new Exception('No se pudo duplicar el archivo físico');
            }

            $rutaRelativaNueva = dirname($original['archivo']) . '/' . $nombreArchivo;

            $this->conexion->beginTransaction();

            $sql = "INSERT INTO documentos_paciente 
                    (id_paciente, id_carpeta, titulo, archivo, tipo, 
                     extension, tamano_archivo, descripcion, subido_por) 
                    VALUES 
                    (:id_paciente, :id_carpeta, :titulo, :archivo, :tipo,
                     :extension, :tamano, :descripcion, :subido_por)";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':id_paciente' => $original['id_paciente'],
                ':id_carpeta' => $idCarpetaDestino,
                ':titulo' => $original['titulo'] . ' (copia)',
                ':archivo' => $rutaRelativaNueva,
                ':tipo' => $original['tipo'],
                ':extension' => $original['extension'],
                ':tamano' => $original['tamano_archivo'],
                ':descripcion' => $original['descripcion'],
                ':subido_por' => $idUsuario
            ]);

            $idNuevo = $this->conexion->lastInsertId();

            $this->registrarHistorialDocumento($idNuevo, 'subir', $idUsuario, [
                'titulo' => $original['titulo'] . ' (copia)',
                'copiado_de' => $idDocumento
            ]);

            $this->conexion->commit();
            return $idNuevo;
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            error_log("❌ Error al copiar documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copiar una carpeta completa (con subcarpetas y documentos) a otra ubicación
     */
    public function copiarCarpeta($idCarpeta, $idCarpetaDestinoPadre, $idPaciente, $idUsuario) {
        try {
            $original = $this->obtenerCarpeta($idCarpeta);
            if (!$original) return false;

            // 1. Crear la carpeta nueva (nunca marcada como del sistema)
            $idCarpetaNueva = $this->crearCarpeta([
                'nombre_carpeta' => $original['nombre_carpeta'] . ' (copia)',
                'id_carpeta_padre' => $idCarpetaDestinoPadre,
                'id_paciente' => $idPaciente,
                'es_sistema' => 0,
                'orden' => $original['orden'],
                'icono' => $original['icono'],
                'color' => $original['color'],
                'creado_por' => $idUsuario
            ]);
            if (!$idCarpetaNueva) throw new Exception('No se pudo crear la carpeta destino');

            // 2. Copiar los documentos de esta carpeta
            $documentos = $this->obtenerDocumentosCarpeta($idCarpeta);
            foreach ($documentos as $doc) {
                $this->copiarDocumento($doc['id_documento'], $idCarpetaNueva, $idUsuario);
            }

            // 3. Copiar recursivamente las subcarpetas
            $subcarpetas = $this->obtenerSubcarpetas($idCarpeta);
            foreach ($subcarpetas as $sub) {
                $this->copiarCarpeta($sub['id_carpeta'], $idCarpetaNueva, $idPaciente, $idUsuario);
            }

            return $idCarpetaNueva;
        } catch (Exception $e) {
            error_log("❌ Error al copiar carpeta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener un documento por su ID (todas las columnas)
     */
    public function obtenerDocumento($idDocumento) {
        try {
            $sql = "SELECT * FROM vista_documentos_completa WHERE id_documento = :id_documento";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_documento' => $idDocumento]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Editar información del documento
     */
    public function editarDocumento($idDocumento, $datos, $idUsuario) {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE documentos_paciente 
                    SET titulo = :titulo,
                        descripcion = :descripcion,
                        editado_por = :editado_por
                    WHERE id_documento = :id_documento";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([
                ':titulo' => $datos['titulo'],
                ':descripcion' => $datos['descripcion'] ?? null,
                ':editado_por' => $idUsuario,
                ':id_documento' => $idDocumento
            ]);

            $this->registrarHistorialDocumento($idDocumento, 'editar', $idUsuario, $datos);

            $this->conexion->commit();
            return $resultado;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al editar documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mover documento a otra carpeta
     */
    public function moverDocumento($idDocumento, $idCarpetaDestino, $idUsuario) {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE documentos_paciente 
                    SET id_carpeta = :id_carpeta,
                        editado_por = :editado_por
                    WHERE id_documento = :id_documento";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([
                ':id_carpeta' => $idCarpetaDestino,
                ':editado_por' => $idUsuario,
                ':id_documento' => $idDocumento
            ]);

            $this->registrarHistorialDocumento($idDocumento, 'mover', $idUsuario, [
                'carpeta_destino' => $idCarpetaDestino
            ]);

            $this->conexion->commit();
            return $resultado;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al mover documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar documento (soft delete)
     */
    public function eliminarDocumento($idDocumento, $idUsuario) {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE documentos_paciente 
                    SET activo = 0, editado_por = :editado_por
                    WHERE id_documento = :id_documento";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([
                ':editado_por' => $idUsuario,
                ':id_documento' => $idDocumento
            ]);

            $this->registrarHistorialDocumento($idDocumento, 'eliminar', $idUsuario, []);

            $this->conexion->commit();
            return $resultado;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al eliminar documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar descarga de documento
     */
    public function registrarDescarga($idDocumento, $idUsuario) {
        try {
            return $this->registrarHistorialDocumento($idDocumento, 'descargar', $idUsuario, [
                'fecha_descarga' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("❌ Error al registrar descarga: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 📊 ESTADÍSTICAS Y REPORTES
    // =====================================================

    /**
     * Obtener estadísticas de documentos del paciente
     */
    public function obtenerEstadisticas($idPaciente) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_documentos,
                        COUNT(DISTINCT id_carpeta) as total_carpetas_usadas,
                        SUM(tamano_archivo) as tamano_total,
                        MAX(fecha_subida) as ultimo_documento
                    FROM documentos_paciente
                    WHERE id_paciente = :id_paciente AND activo = 1";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_paciente' => $idPaciente]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener estadísticas: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 📝 HISTORIAL Y AUDITORÍA
    // =====================================================

    /**
     * Registrar cambio en carpeta
     */
    private function registrarHistorialCarpeta($idCarpeta, $accion, $idUsuario, $detalles) {
        try {
            $sql = "INSERT INTO historial_carpetas 
                    (id_carpeta, accion, id_usuario, detalles) 
                    VALUES (:id_carpeta, :accion, :id_usuario, :detalles)";
            
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':id_carpeta' => $idCarpeta,
                ':accion' => $accion,
                ':id_usuario' => $idUsuario,
                ':detalles' => json_encode($detalles, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al registrar historial de carpeta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar cambio en documento
     */
    private function registrarHistorialDocumento($idDocumento, $accion, $idUsuario, $detalles) {
        try {
            $sql = "INSERT INTO historial_documentos 
                    (id_documento, accion, id_usuario, detalles) 
                    VALUES (:id_documento, :accion, :id_usuario, :detalles)";
            
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':id_documento' => $idDocumento,
                ':accion' => $accion,
                ':id_usuario' => $idUsuario,
                ':detalles' => json_encode($detalles, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al registrar historial de documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener historial de una carpeta
     */
    public function obtenerHistorialCarpeta($idCarpeta, $limit = 10) {
        try {
            $sql = "SELECT h.*, CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre
                    FROM historial_carpetas h
                    INNER JOIN usuarios u ON h.id_usuario = u.id_usuario
                    WHERE h.id_carpeta = :id_carpeta
                    ORDER BY h.fecha_accion DESC
                    LIMIT :limit";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_carpeta', $idCarpeta, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener historial: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de un documento
     */
    public function obtenerHistorialDocumento($idDocumento, $limit = 10) {
        try {
            $sql = "SELECT h.*, CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre
                    FROM historial_documentos h
                    INNER JOIN usuarios u ON h.id_usuario = u.id_usuario
                    WHERE h.id_documento = :id_documento
                    ORDER BY h.fecha_accion DESC
                    LIMIT :limit";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_documento', $idDocumento, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al obtener historial: " . $e->getMessage());
            return [];
        }
    }
}
?>