<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloPacientes {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // 🔹 Validaciones de Unicidad
    // =====================================================
    
    /**
     * Verifica si un DNI ya existe en la base de datos de pacientes
     * @param string $dni DNI a verificar
     * @return bool True si existe, False si no existe
     */
    public function existeDNI($dni) {
        try {
            $sql = "SELECT COUNT(*) as total FROM pacientes WHERE dni = :dni";
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
     * Verifica si un correo ya existe en la base de datos de pacientes
     * @param string $correo Correo a verificar
     * @return bool True si existe, False si no existe
     */
    public function existeCorreo($correo) {
        try {
            $sql = "SELECT COUNT(*) as total FROM pacientes WHERE correo = :correo";
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
     * Verifica si un correo existe para otro paciente (útil en edición)
     * @param string $correo Correo a verificar
     * @param int $idPaciente ID del paciente actual (para excluirlo)
     * @return bool True si existe para otro paciente, False si no
     */
    public function existeCorreoExceptoPaciente($correo, $idPaciente) {
        try {
            $sql = "SELECT COUNT(*) as total FROM pacientes WHERE correo = :correo AND id_paciente != :id_paciente";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':correo' => $correo,
                ':id_paciente' => $idPaciente
            ]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("❌ Error al verificar correo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un DNI existe para otro paciente (útil en edición)
     * @param string $dni DNI a verificar
     * @param int $idPaciente ID del paciente actual (para excluirlo)
     * @return bool True si existe para otro paciente, False si no
     */
    public function existeDNIExceptoPaciente($dni, $idPaciente) {
        try {
            $sql = "SELECT COUNT(*) as total FROM pacientes WHERE dni = :dni AND id_paciente != :id_paciente";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':dni' => $dni,
                ':id_paciente' => $idPaciente
            ]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("❌ Error al verificar DNI: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Registrar nuevo paciente
    // =====================================================
    public function registrarPaciente($datos) {
        try {
            $fechaRegistro = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO pacientes
                    (nombre, apellido, dni, fecha_nacimiento, telefono, direccion, correo, 
                     fecha_registro, foto, id_estado_civil, id_sexo, id_grado_instruccion, 
                     ocupacion, observaciones, id_apoderado)
                    VALUES (:nombre, :apellido, :dni, :fecha_nacimiento, :telefono, :direccion, 
                            :correo, :fecha_registro, :foto, :id_estado_civil, :id_sexo, 
                            :id_grado_instruccion, :ocupacion, :observaciones, :id_apoderado)";
            
            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':dni' => $datos['dni'],
                ':fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                ':telefono' => $datos['telefono'],
                ':direccion' => $datos['direccion'],
                ':correo' => $datos['correo'],
                ':fecha_registro' => $fechaRegistro,
                ':foto' => $datos['foto'] ?? null,
                ':id_estado_civil' => $datos['id_estado_civil'],
                ':id_sexo' => $datos['id_sexo'],
                ':id_grado_instruccion' => $datos['id_grado_instruccion'],
                ':ocupacion' => $datos['ocupacion'],
                ':observaciones' => $datos['observaciones'] ?? null,
                ':id_apoderado' => $datos['id_apoderado'] ?? null
            ]);
            
            $idNuevo = $this->conexion->lastInsertId();
            
            return [
                'success' => true,
                'id_paciente' => $idNuevo
            ];

        } catch (PDOException $e) {
            error_log("❌ Error al registrar paciente: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Listar pacientes
    // =====================================================
    public function listarPacientes() {
        try {
            $sql = "SELECT p.*, 
                           s.nombre_sexo, 
                           ec.nombre_estado_civil, 
                           gi.nombre_grado_instruccion
                    FROM pacientes p
                    LEFT JOIN sexo s ON p.id_sexo = s.id_sexo
                    LEFT JOIN estado_civil ec ON p.id_estado_civil = ec.id_estado_civil
                    LEFT JOIN grado_instruccion gi ON p.id_grado_instruccion = gi.id_grado_instruccion
                    ORDER BY p.id_paciente DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al listar pacientes: " . $e->getMessage());
            return [];
        }
    }
// =====================================================
// 🔹 Listar tipos de familiar
// =====================================================
public function listarTipoFamiliar() {
    try {
        $sql = "SELECT id_tipo_familiar, descripcion 
                FROM tipo_familiar 
                ORDER BY descripcion ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Error al listar tipos de familiar: " . $e->getMessage());
        return [];
    }
}
    // =====================================================
    // 🔹 Ver paciente por ID
    // =====================================================
    public function verPaciente($id) {
        try {
            $sql = "SELECT p.*, 
                           s.nombre_sexo, 
                           ec.nombre_estado_civil, 
                           gi.nombre_grado_instruccion
                    FROM pacientes p
                    LEFT JOIN sexo s ON p.id_sexo = s.id_sexo
                    LEFT JOIN estado_civil ec ON p.id_estado_civil = ec.id_estado_civil
                    LEFT JOIN grado_instruccion gi ON p.id_grado_instruccion = gi.id_grado_instruccion
                    WHERE p.id_paciente = :id";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al ver paciente: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Editar paciente
    // =====================================================
    public function editarPaciente($id, $datos) {
        try {
            $sql = "UPDATE pacientes 
                    SET nombre = :nombre, 
                        apellido = :apellido, 
                        dni = :dni,
                        fecha_nacimiento = :fecha_nacimiento,
                        telefono = :telefono,
                        direccion = :direccion,
                        correo = :correo, 
                        foto = :foto,
                        id_estado_civil = :id_estado_civil,
                        id_sexo = :id_sexo,
                        id_grado_instruccion = :id_grado_instruccion,
                        ocupacion = :ocupacion,
                        observaciones = :observaciones,
                        id_apoderado = :id_apoderado
                    WHERE id_paciente = :id";
            
            $stmt = $this->conexion->prepare($sql);
            
            return $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':dni' => $datos['dni'],
                ':fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                ':telefono' => $datos['telefono'],
                ':direccion' => $datos['direccion'],
                ':correo' => $datos['correo'],
                ':foto' => $datos['foto'],
                ':id_estado_civil' => $datos['id_estado_civil'],
                ':id_sexo' => $datos['id_sexo'],
                ':id_grado_instruccion' => $datos['id_grado_instruccion'],
                ':ocupacion' => $datos['ocupacion'],
                ':observaciones' => $datos['observaciones'] ?? null,
                ':id_apoderado' => $datos['id_apoderado'] ?? null,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al editar paciente: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Listar estado civil
    // =====================================================
    public function listarEstadoCivil() {
        try {
            $sql = "SELECT id_estado_civil, nombre_estado_civil 
                    FROM estado_civil 
                    ORDER BY nombre_estado_civil ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al listar estado civil: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Listar sexo
    // =====================================================
    public function listarSexo() {
        try {
            $sql = "SELECT id_sexo, nombre_sexo 
                    FROM sexo 
                    ORDER BY nombre_sexo ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al listar sexo: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Listar grado de instrucción
    // =====================================================
    public function listarGradoInstruccion() {
        try {
            $sql = "SELECT id_grado_instruccion, nombre_grado_instruccion 
                    FROM grado_instruccion 
                    ORDER BY nombre_grado_instruccion ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al listar grado de instrucción: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Registrar apoderado
    // =====================================================
    public function registrarApoderado($datos) {
        try {
            $sql = "INSERT INTO apoderados (nombre, apellido, dni, id_tipo_familiar, telefono)
                    VALUES (:nombre, :apellido, :dni, :id_tipo_familiar, :telefono)";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':dni' => $datos['dni'],
                ':id_tipo_familiar' => $datos['id_tipo_familiar'],
                ':telefono' => $datos['telefono']
            ]);
            
            return $this->conexion->lastInsertId();
        } catch (PDOException $e) {
            error_log("❌ Error al registrar apoderado: " . $e->getMessage());
            return null;
        }
    }

    // =====================================================
    // 🔹 Ver apoderado por ID
    // =====================================================
    public function verApoderado($id) {
        try {
            $sql = "SELECT a.*, tf.descripcion as tipo_familiar_descripcion 
                    FROM apoderados a
                    LEFT JOIN tipo_familiar tf ON a.id_tipo_familiar = tf.id_tipo_familiar
                    WHERE a.id_apoderado = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al ver apoderado: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Editar apoderado
    // =====================================================
    public function editarApoderado($id, $datos) {
        try {
            $sql = "UPDATE apoderados 
                    SET nombre = :nombre,
                        apellido = :apellido,
                        dni = :dni,
                        id_tipo_familiar = :id_tipo_familiar,
                        telefono = :telefono
                    WHERE id_apoderado = :id";
            
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':dni' => $datos['dni'],
                ':id_tipo_familiar' => $datos['id_tipo_familiar'],
                ':telefono' => $datos['telefono'],
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al editar apoderado: " . $e->getMessage());
            return false;
        }
    }
    // =====================================================
    // 🔹 Buscar paciente por término
    // =====================================================
    public function buscarPaciente($termino) {
        try {
            $sql = "SELECT p.*, 
                           s.nombre_sexo, 
                           ec.nombre_estado_civil, 
                           gi.nombre_grado_instruccion
                    FROM pacientes p
                    LEFT JOIN sexo s ON p.id_sexo = s.id_sexo
                    LEFT JOIN estado_civil ec ON p.id_estado_civil = ec.id_estado_civil
                    LEFT JOIN grado_instruccion gi ON p.id_grado_instruccion = gi.id_grado_instruccion
                    WHERE p.nombre LIKE :termino 
                       OR p.apellido LIKE :termino 
                       OR p.dni LIKE :termino 
                       OR p.correo LIKE :termino
                       OR p.telefono LIKE :termino
                    ORDER BY p.id_paciente DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':termino' => "%$termino%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al buscar paciente: " . $e->getMessage());
            return [];
        }
    }
}
?>