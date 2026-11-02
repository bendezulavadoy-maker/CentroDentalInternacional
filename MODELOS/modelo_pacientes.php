<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloPacientes {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // El hash de la contrasena del portal nunca debe llegar al navegador:
    // se reemplaza por un indicador si/no.
    // =====================================================
    private function prepararFila($fila) {
        if (!$fila) return $fila;
        $fila['tiene_acceso_portal'] = !empty($fila['contrasena_portal']);
        unset($fila['contrasena_portal']);
        return $fila;
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

    /** Verifica si la pareja tipo + numero de documento ya existe en pacientes */
    public function existeDocumento($idTipo, $numero) {
        try {
            $sql = "SELECT COUNT(*) as total FROM pacientes
                    WHERE id_tipo_documento = :tipo AND dni = :numero";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':tipo' => $idTipo, ':numero' => $numero]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar documento: " . $e->getMessage());
            return false;
        }
    }

    /** Igual que existeDocumento, excluyendo al paciente que se esta editando */
    public function existeDocumentoExceptoPaciente($idTipo, $numero, $idPaciente) {
        try {
            $sql = "SELECT COUNT(*) as total FROM pacientes
                    WHERE id_tipo_documento = :tipo AND dni = :numero
                      AND id_paciente != :id_paciente";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':tipo' => $idTipo,
                ':numero' => $numero,
                ':id_paciente' => $idPaciente
            ]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un correo ya existe en la base de datos de pacientes
     */
    public function existeCorreo($correo) {
        try {
            $sql = "SELECT COUNT(*) as total FROM pacientes WHERE correo = :correo";
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
     * Verifica si un correo existe para otro paciente (util en edicion)
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
            error_log("Error al verificar correo: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Odontologo responsable
    // =====================================================
    public function listarDoctoresResponsables() {
        try {
            $stmt = $this->conexion->query(
                "SELECT id_usuario, nombre, apellidos FROM usuarios WHERE id_rol = 2 AND id_estado = 1 ORDER BY nombre, apellidos"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al listar odontologos: " . $e->getMessage());
            return [];
        }
    }

    public function esOdontologoActivo($idUsuario) {
        try {
            $stmt = $this->conexion->prepare("SELECT 1 FROM usuarios WHERE id_usuario = :id AND id_rol = 2 AND id_estado = 1");
            $stmt->execute([':id' => (int)$idUsuario]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    // =====================================================
    // Registrar nuevo paciente
    // =====================================================
    public function registrarPaciente($datos) {
        try {
            $fechaRegistro = date('Y-m-d H:i:s');

            $sql = "INSERT INTO pacientes
                    (nombre, apellido, id_tipo_documento, dni, fecha_nacimiento, telefono, direccion, correo,
                     fecha_registro, foto, id_estado_civil, id_sexo, id_grado_instruccion,
                     ocupacion, observaciones, id_apoderado, id_doctor_responsable)
                    VALUES (:nombre, :apellido, :id_tipo_documento, :dni, :fecha_nacimiento, :telefono, :direccion,
                            :correo, :fecha_registro, :foto, :id_estado_civil, :id_sexo,
                            :id_grado_instruccion, :ocupacion, :observaciones, :id_apoderado, :id_doctor_responsable)";

            $stmt = $this->conexion->prepare($sql);

            $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':id_tipo_documento' => $datos['id_tipo_documento'],
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
                ':id_apoderado' => $datos['id_apoderado'] ?? null,
                ':id_doctor_responsable' => $datos['id_doctor_responsable'] ?? null
            ]);

            $idNuevo = $this->conexion->lastInsertId();

            return [
                'success' => true,
                'id_paciente' => $idNuevo
            ];

        } catch (PDOException $e) {
            error_log("Error al registrar paciente: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Listar pacientes
    // =====================================================
    public function listarPacientes() {
        try {
            $sql = "SELECT p.*,
                           td.abreviatura AS tipo_documento_abrev,
                           CONCAT(ur.nombre, ' ', ur.apellidos) AS doctor_responsable_nombre,
                           s.nombre_sexo,
                           ec.nombre_estado_civil,
                           gi.nombre_grado_instruccion
                    FROM pacientes p
                    LEFT JOIN tipo_documento td ON p.id_tipo_documento = td.id_tipo_documento
                    LEFT JOIN usuarios ur ON p.id_doctor_responsable = ur.id_usuario
                    LEFT JOIN sexo s ON p.id_sexo = s.id_sexo
                    LEFT JOIN estado_civil ec ON p.id_estado_civil = ec.id_estado_civil
                    LEFT JOIN grado_instruccion gi ON p.id_grado_instruccion = gi.id_grado_instruccion
                    ORDER BY p.id_paciente DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return array_map([$this, 'prepararFila'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log("Error al listar pacientes: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // Listar tipos de familiar
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
            error_log("Error al listar tipos de familiar: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // Ver paciente por ID
    // =====================================================
    public function verPaciente($id) {
        try {
            $sql = "SELECT p.*,
                           td.abreviatura AS tipo_documento_abrev,
                           CONCAT(ur.nombre, ' ', ur.apellidos) AS doctor_responsable_nombre,
                           s.nombre_sexo,
                           ec.nombre_estado_civil,
                           gi.nombre_grado_instruccion
                    FROM pacientes p
                    LEFT JOIN tipo_documento td ON p.id_tipo_documento = td.id_tipo_documento
                    LEFT JOIN usuarios ur ON p.id_doctor_responsable = ur.id_usuario
                    LEFT JOIN sexo s ON p.id_sexo = s.id_sexo
                    LEFT JOIN estado_civil ec ON p.id_estado_civil = ec.id_estado_civil
                    LEFT JOIN grado_instruccion gi ON p.id_grado_instruccion = gi.id_grado_instruccion
                    WHERE p.id_paciente = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $this->prepararFila($stmt->fetch(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log("Error al ver paciente: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Editar paciente
    // =====================================================
    public function editarPaciente($id, $datos) {
        try {
            $sql = "UPDATE pacientes
                    SET nombre = :nombre,
                        apellido = :apellido,
                        id_tipo_documento = :id_tipo_documento,
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
                        id_apoderado = :id_apoderado" . (array_key_exists('id_doctor_responsable', $datos) ? ",
                        id_doctor_responsable = :id_doctor_responsable" : "") . "
                    WHERE id_paciente = :id";

            $stmt = $this->conexion->prepare($sql);

            $params = [
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':id_tipo_documento' => $datos['id_tipo_documento'],
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
            ];
            if (array_key_exists('id_doctor_responsable', $datos)) {
                $params[':id_doctor_responsable'] = $datos['id_doctor_responsable'];   // solo si el usuario puede asignarlo
            }
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error al editar paciente: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Listar estado civil
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
            error_log("Error al listar estado civil: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // Listar sexo
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
            error_log("Error al listar sexo: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // Listar grado de instruccion
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
            error_log("Error al listar grado de instruccion: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // Registrar apoderado
    // =====================================================
    public function registrarApoderado($datos) {
        try {
            $sql = "INSERT INTO apoderados (nombre, apellido, id_tipo_documento, dni, id_tipo_familiar, telefono)
                    VALUES (:nombre, :apellido, :id_tipo_documento, :dni, :id_tipo_familiar, :telefono)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':id_tipo_documento' => $datos['id_tipo_documento'] ?? 1,
                ':dni' => $datos['dni'],
                ':id_tipo_familiar' => $datos['id_tipo_familiar'],
                ':telefono' => $datos['telefono']
            ]);

            return $this->conexion->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error al registrar apoderado: " . $e->getMessage());
            return null;
        }
    }

    // =====================================================
    // Buscar apoderado por tipo + numero de documento
    // =====================================================
    public function buscarApoderadoPorDocumento($idTipo, $numero) {
        try {
            $stmt = $this->conexion->prepare(
                "SELECT id_apoderado FROM apoderados WHERE id_tipo_documento = :tipo AND dni = :numero LIMIT 1"
            );
            $stmt->execute([':tipo' => $idTipo, ':numero' => $numero]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error al buscar apoderado: " . $e->getMessage());
            return null;
        }
    }

    // =====================================================
    // Ver apoderado por ID
    // =====================================================
    public function verApoderado($id) {
        try {
            $sql = "SELECT a.*, tf.descripcion as tipo_familiar_descripcion,
                           td.abreviatura AS tipo_documento_abrev
                    FROM apoderados a
                    LEFT JOIN tipo_familiar tf ON a.id_tipo_familiar = tf.id_tipo_familiar
                    LEFT JOIN tipo_documento td ON a.id_tipo_documento = td.id_tipo_documento
                    WHERE a.id_apoderado = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al ver apoderado: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Editar apoderado
    // =====================================================
    public function editarApoderado($id, $datos) {
        try {
            $sql = "UPDATE apoderados
                    SET nombre = :nombre,
                        apellido = :apellido,
                        id_tipo_documento = :id_tipo_documento,
                        dni = :dni,
                        id_tipo_familiar = :id_tipo_familiar,
                        telefono = :telefono
                    WHERE id_apoderado = :id";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':id_tipo_documento' => $datos['id_tipo_documento'] ?? 1,
                ':dni' => $datos['dni'],
                ':id_tipo_familiar' => $datos['id_tipo_familiar'],
                ':telefono' => $datos['telefono'],
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Error al editar apoderado: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // Restablecer el acceso al portal del paciente
    // Borra su contrasena del portal: para volver a entrar tendra que crear una
    // nueva con "Crea tu acceso", confirmando su documento y su telefono.
    // Tambien limpia los bloqueos por intentos fallidos de ese documento.
    // =====================================================
    public function restablecerAccesoPortal($idPaciente) {
        try {
            $sel = $this->conexion->prepare(
                "SELECT id_tipo_documento, dni, contrasena_portal FROM pacientes WHERE id_paciente = :id"
            );
            $sel->execute([':id' => $idPaciente]);
            $fila = $sel->fetch(PDO::FETCH_ASSOC);
            if (!$fila) return ['success' => false, 'mensaje' => 'Paciente no encontrado'];

            $teniaAcceso = !empty($fila['contrasena_portal']);

            $upd = $this->conexion->prepare("UPDATE pacientes SET contrasena_portal = NULL WHERE id_paciente = :id");
            $upd->execute([':id' => $idPaciente]);

            // Si la tabla de intentos del portal aun no existe, no es un error
            try {
                $clave = 'doc|' . $fila['id_tipo_documento'] . '|' . $fila['dni'];
                $this->conexion->prepare("DELETE FROM portal_intentos WHERE clave = :c")->execute([':c' => $clave]);
            } catch (PDOException $e) {
                error_log("restablecerAccesoPortal (intentos): " . $e->getMessage());
            }

            return ['success' => true, 'tenia_acceso' => $teniaAcceso];
        } catch (PDOException $e) {
            error_log("Error al restablecer acceso al portal: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo restablecer el acceso'];
        }
    }

    // =====================================================
    // Buscar paciente por termino
    // =====================================================
    public function buscarPaciente($termino) {
        try {
            $sql = "SELECT p.*,
                           td.abreviatura AS tipo_documento_abrev,
                           CONCAT(ur.nombre, ' ', ur.apellidos) AS doctor_responsable_nombre,
                           s.nombre_sexo,
                           ec.nombre_estado_civil,
                           gi.nombre_grado_instruccion
                    FROM pacientes p
                    LEFT JOIN tipo_documento td ON p.id_tipo_documento = td.id_tipo_documento
                    LEFT JOIN usuarios ur ON p.id_doctor_responsable = ur.id_usuario
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
            return array_map([$this, 'prepararFila'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log("Error al buscar paciente: " . $e->getMessage());
            return [];
        }
    }
}
?>