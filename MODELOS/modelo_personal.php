<?php
require_once '../CONFIG/conexion.php';

class ModeloPersonal {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // Tipos de documento de identidad
    // =====================================================
    public function listarTiposDocumento() {
        try {
            $sql = "SELECT id_tipo_documento, codigo, nombre, abreviatura,
                           longitud_min, longitud_max, solo_numeros
                    FROM tipo_documento
                    WHERE activo = 1
                    ORDER BY id_tipo_documento ASC";
            return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al listar tipos de documento: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTipoDocumento($idTipo) {
        try {
            $stmt = $this->conexion->prepare(
                "SELECT * FROM tipo_documento WHERE id_tipo_documento = :id AND activo = 1"
            );
            $stmt->execute([':id' => $idTipo]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error al obtener tipo de documento: " . $e->getMessage());
            return null;
        }
    }

    // =====================================================
    // Validaciones de unicidad
    // =====================================================

    /** Verifica si la pareja tipo + numero de documento ya existe */
    public function existeDocumento($idTipo, $numero) {
        try {
            $sql = "SELECT COUNT(*) as total FROM usuarios
                    WHERE id_tipo_documento = :tipo AND nro_documento = :numero";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':tipo' => $idTipo, ':numero' => $numero]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar documento: " . $e->getMessage());
            return false;
        }
    }

    /** Igual que existeDocumento, excluyendo al usuario que se esta editando */
    public function existeDocumentoExceptoUsuario($idTipo, $numero, $idUsuario) {
        try {
            $sql = "SELECT COUNT(*) as total FROM usuarios
                    WHERE id_tipo_documento = :tipo AND nro_documento = :numero
                      AND id_usuario != :id_usuario";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':tipo' => $idTipo,
                ':numero' => $numero,
                ':id_usuario' => $idUsuario
            ]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un correo ya existe en la base de datos
     */
    public function existeCorreo($correo) {
        try {
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE correo = :correo";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':correo' => $correo]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar correo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un correo existe para otro usuario (util en edicion)
     */
    public function existeCorreoExceptoUsuario($correo, $idUsuario) {
        try {
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE correo = :correo AND id_usuario != :id_usuario";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':correo' => $correo,
                ':id_usuario' => $idUsuario
            ]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar correo: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Codigo de usuario: DENTINT + primeros 4 caracteres del numero de documento.
    // Si ya existe, se le agrega un sufijo (-1, -2...).
    // $excluirId evita compararlo contra el propio usuario al editar.
    // =====================================================
    private function generarCodigoUsuario($numeroDocumento, $excluirId = 0) {
        $codigoBase = 'DENTINT' . substr($numeroDocumento, 0, 4);
        $codigoFinal = $codigoBase;
        $contador = 1;

        while (true) {
            $sqlCheck = "SELECT COUNT(*) AS existe FROM usuarios
                         WHERE codigo_usuario = :codigo AND id_usuario != :id";
            $stmtCheck = $this->conexion->prepare($sqlCheck);
            $stmtCheck->execute([':codigo' => $codigoFinal, ':id' => $excluirId]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC)['existe'];
            if ($existe == 0) break;
            $codigoFinal = $codigoBase . '-' . $contador;
            $contador++;
        }
        return $codigoFinal;
    }

    // =====================================================
    // Registrar nuevo personal
    // =====================================================
    public function registrarPersonal($nombre, $apellidos, $id_tipo_documento, $nro_documento, $correo, $id_rol, $id_estado, $fecha_nacimiento = null, $foto = null) {
        try {
            $codigoFinal = $this->generarCodigoUsuario($nro_documento);

            $fecha = date('Y-m-d H:i:s');
            $temporal = '12345';
            $hash = password_hash($temporal, PASSWORD_BCRYPT);

            $sql = "INSERT INTO usuarios
                    (nombre, apellidos, id_tipo_documento, nro_documento, correo, contrasena, fecha_creacion, codigo_usuario, id_rol, id_estado, fecha_nacimiento, foto)
                    VALUES (:nombre, :apellidos, :id_tipo_documento, :nro_documento, :correo, :contrasena, :fecha_creacion, :codigo_usuario, :id_rol, :id_estado, :fecha_nacimiento, :foto)";
            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':id_tipo_documento' => $id_tipo_documento,
                ':nro_documento' => $nro_documento,
                ':correo' => $correo,
                ':contrasena' => $hash,
                ':fecha_creacion' => $fecha,
                ':codigo_usuario' => $codigoFinal,
                ':id_rol' => $id_rol,
                ':id_estado' => $id_estado,
                ':fecha_nacimiento' => $fecha_nacimiento ?: null,
                ':foto' => $foto
            ]);

            $idNuevo = $this->conexion->lastInsertId();

            return [
                'id_usuario' => $idNuevo,
                'codigo' => $codigoFinal,
                'contrasena' => $temporal
            ];

        } catch (PDOException $e) {
            error_log("Error al registrar personal: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Listar personal
    // =====================================================
    public function listarPersonal() {
        try {
            $sql = "SELECT u.*, r.nombre_rol, td.abreviatura AS tipo_documento_abrev
                    FROM usuarios u
                    INNER JOIN roles r ON u.id_rol = r.id_rol
                    LEFT JOIN tipo_documento td ON u.id_tipo_documento = td.id_tipo_documento
                    ORDER BY u.id_usuario DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al listar personal: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // Ver personal por ID
    // =====================================================
    public function verPersonal($id) {
        try {
            $sql = "SELECT u.*, r.nombre_rol, td.abreviatura AS tipo_documento_abrev
                    FROM usuarios u
                    INNER JOIN roles r ON u.id_rol = r.id_rol
                    LEFT JOIN tipo_documento td ON u.id_tipo_documento = td.id_tipo_documento
                    WHERE u.id_usuario = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al ver personal: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Editar personal
    // =====================================================
    public function editarPersonal($id, $nombre, $apellidos, $id_tipo_documento, $nro_documento, $correo, $id_rol, $id_estado, $fecha_nacimiento, $foto, $actualizarCodigo = false) {
        try {
            $codigoNuevo = $actualizarCodigo ? $this->generarCodigoUsuario($nro_documento, $id) : null;

            $sql = "UPDATE usuarios
                    SET nombre = :nombre,
                        apellidos = :apellidos,
                        id_tipo_documento = :id_tipo_documento,
                        nro_documento = :nro_documento,
                        correo = :correo,
                        id_rol = :id_rol,
                        id_estado = :id_estado,
                        fecha_nacimiento = :fecha_nacimiento,
                        foto = :foto" . ($codigoNuevo ? ", codigo_usuario = :codigo_usuario" : "") . "
                    WHERE id_usuario = :id";

            $stmt = $this->conexion->prepare($sql);

            $params = [
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':id_tipo_documento' => $id_tipo_documento,
                ':nro_documento' => $nro_documento,
                ':correo' => $correo,
                ':id_rol' => $id_rol,
                ':id_estado' => $id_estado,
                ':fecha_nacimiento' => $fecha_nacimiento ?: null,
                ':foto' => $foto,
                ':id' => $id
            ];
            if ($codigoNuevo) $params[':codigo_usuario'] = $codigoNuevo;

            $exito = $stmt->execute($params);

            return $exito ? ['exito' => true, 'codigo_usuario' => $codigoNuevo] : false;
        } catch (PDOException $e) {
            error_log("Error al editar personal: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Listar roles
    // =====================================================
    public function listarRoles() {
        try {
            $sql = "SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al listar roles: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // Buscar personal por termino
    // =====================================================
    public function buscarPersonal($termino) {
        try {
            $sql = "SELECT u.*, r.nombre_rol, td.abreviatura AS tipo_documento_abrev
                    FROM usuarios u
                    INNER JOIN roles r ON u.id_rol = r.id_rol
                    LEFT JOIN tipo_documento td ON u.id_tipo_documento = td.id_tipo_documento
                    WHERE u.nombre LIKE :termino
                       OR u.apellidos LIKE :termino
                       OR u.nro_documento LIKE :termino
                       OR u.codigo_usuario LIKE :termino
                       OR u.correo LIKE :termino
                    ORDER BY u.id_usuario DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':termino' => "%$termino%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al buscar personal: " . $e->getMessage());
            return [];
        }
    }
}
?>