<?php
require_once '../CONFIG/conexion.php';

class ModeloPersonal {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // 🔹 Validaciones de Unicidad
    // =====================================================
    
    /**
     * Verifica si un DNI ya existe en la base de datos
     * @param string $dni DNI a verificar
     * @return bool True si existe, False si no existe
     */
    public function existeDNI($dni) {
        try {
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE dni = :dni";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':dni' => $dni]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("❌ Error al verificar DNI: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un correo ya existe en la base de datos
     * @param string $correo Correo a verificar
     * @return bool True si existe, False si no existe
     */
    public function existeCorreo($correo) {
        try {
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE correo = :correo";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':correo' => $correo]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("❌ Error al verificar correo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un correo existe para otro usuario (útil en edición)
     * @param string $correo Correo a verificar
     * @param int $idUsuario ID del usuario actual (para excluirlo)
     * @return bool True si existe para otro usuario, False si no
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
            error_log("❌ Error al verificar correo: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Registrar nuevo personal
    // =====================================================
    public function registrarPersonal($nombre, $apellidos, $dni, $correo, $id_rol, $id_estado, $fecha_nacimiento = null, $foto = null) {
        try {
            // Generar código único
            $codigoBase = 'DENTINT' . substr($dni, -4);
            $codigoFinal = $codigoBase;
            $contador = 1;

            // Evitar códigos duplicados
            while (true) {
                $sqlCheck = "SELECT COUNT(*) AS existe FROM usuarios WHERE codigo_usuario = :codigo";
                $stmtCheck = $this->conexion->prepare($sqlCheck);
                $stmtCheck->execute([':codigo' => $codigoFinal]);
                $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC)['existe'];
                if ($existe == 0) break;
                $codigoFinal = $codigoBase . '-' . $contador;
                $contador++;
            }

            $fecha = date('Y-m-d H:i:s');
            $temporal = '12345';
            $hash = password_hash($temporal, PASSWORD_BCRYPT);

            $sql = "INSERT INTO usuarios 
                    (nombre, apellidos, dni, correo, contrasena, fecha_creacion, codigo_usuario, id_rol, id_estado, fecha_nacimiento, foto)
                    VALUES (:nombre, :apellidos, :dni, :correo, :contrasena, :fecha_creacion, :codigo_usuario, :id_rol, :id_estado, :fecha_nacimiento, :foto)";
            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':dni' => $dni,
                ':correo' => $correo,
                ':contrasena' => $hash,
                ':fecha_creacion' => $fecha,
                ':codigo_usuario' => $codigoFinal,
                ':id_rol' => $id_rol,
                ':id_estado' => $id_estado,
                ':fecha_nacimiento' => $fecha_nacimiento,
                ':foto' => $foto
            ]);
            
            $idNuevo = $this->conexion->lastInsertId();
            
            return [
                'id_usuario' => $idNuevo,
                'codigo' => $codigoFinal,
                'contrasena' => $temporal
            ];

        } catch (PDOException $e) {
            error_log("❌ Error al registrar personal: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Listar personal
    // =====================================================
    public function listarPersonal() {
        try {
            $sql = "SELECT u.*, r.nombre_rol 
                    FROM usuarios u 
                    INNER JOIN roles r ON u.id_rol = r.id_rol 
                    ORDER BY u.id_usuario DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al listar personal: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Ver personal por ID
    // =====================================================
    public function verPersonal($id) {
        try {
            $sql = "SELECT u.*, r.nombre_rol 
                    FROM usuarios u 
                    INNER JOIN roles r ON u.id_rol = r.id_rol 
                    WHERE u.id_usuario = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al ver personal: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Editar personal
    // =====================================================
    public function editarPersonal($id, $nombre, $apellidos, $correo, $id_rol, $id_estado, $fecha_nacimiento, $foto) {
        try {
            $sql = "UPDATE usuarios 
                    SET nombre = :nombre, 
                        apellidos = :apellidos, 
                        correo = :correo, 
                        id_rol = :id_rol, 
                        id_estado = :id_estado, 
                        fecha_nacimiento = :fecha_nacimiento, 
                        foto = :foto 
                    WHERE id_usuario = :id";
            
            $stmt = $this->conexion->prepare($sql);
            
            return $stmt->execute([
                ':nombre' => $nombre,
                ':apellidos' => $apellidos,
                ':correo' => $correo,
                ':id_rol' => $id_rol,
                ':id_estado' => $id_estado,
                ':fecha_nacimiento' => $fecha_nacimiento,
                ':foto' => $foto,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al editar personal: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Listar roles
    // =====================================================
    public function listarRoles() {
        try {
            $sql = "SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al listar roles: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Buscar personal por término
    // =====================================================
    public function buscarPersonal($termino) {
        try {
            $sql = "SELECT u.*, r.nombre_rol 
                    FROM usuarios u 
                    INNER JOIN roles r ON u.id_rol = r.id_rol 
                    WHERE u.nombre LIKE :termino 
                       OR u.apellidos LIKE :termino 
                       OR u.codigo_usuario LIKE :termino 
                       OR u.correo LIKE :termino 
                    ORDER BY u.id_usuario DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':termino' => "%$termino%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al buscar personal: " . $e->getMessage());
            return [];
        }
    }
}
?>