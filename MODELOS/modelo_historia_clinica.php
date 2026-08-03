<?php
require_once '../CONFIG/conexion.php';

class ModeloHistoriaClinica {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // 🔹 Buscar pacientes
    // =====================================================
    public function buscarPacientes($termino) {
        try {
            $sql = "SELECT id_paciente, nombre, apellido, dni, telefono, correo
                    FROM pacientes
                    WHERE nombre LIKE :termino 
                       OR apellido LIKE :termino 
                       OR dni LIKE :termino 
                       OR CONCAT(nombre, ' ', apellido) LIKE :termino
                    ORDER BY apellido ASC, nombre ASC
                    LIMIT 10";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':termino' => "%$termino%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al buscar pacientes: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Obtener información completa del paciente
    // =====================================================
    public function obtenerPacienteCompleto($idPaciente) {
        try {
            $sql = "SELECT p.*, 
                           s.nombre_sexo, 
                           ec.nombre_estado_civil, 
                           gi.nombre_grado_instruccion,
                           a.nombre as apoderado_nombre,
                           a.apellido as apoderado_apellido,
                           a.dni as apoderado_dni,
                           a.telefono as apoderado_telefono,
                           tf.descripcion as tipo_familiar
                    FROM pacientes p
                    LEFT JOIN sexo s ON p.id_sexo = s.id_sexo
                    LEFT JOIN estado_civil ec ON p.id_estado_civil = ec.id_estado_civil
                    LEFT JOIN grado_instruccion gi ON p.id_grado_instruccion = gi.id_grado_instruccion
                    LEFT JOIN apoderados a ON p.id_apoderado = a.id_apoderado
                    LEFT JOIN tipo_familiar tf ON a.id_tipo_familiar = tf.id_tipo_familiar
                    WHERE p.id_paciente = :id";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $idPaciente]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($paciente) {
                // Organizar datos del apoderado si existe
                if ($paciente['apoderado_nombre']) {
                    $paciente['apoderado'] = [
                        'nombre' => $paciente['apoderado_nombre'],
                        'apellido' => $paciente['apoderado_apellido'],
                        'dni' => $paciente['apoderado_dni'],
                        'telefono' => $paciente['apoderado_telefono'],
                        'tipo_familiar' => $paciente['tipo_familiar']
                    ];
                }

                // Limpiar campos innecesarios
                unset($paciente['apoderado_nombre']);
                unset($paciente['apoderado_apellido']);
                unset($paciente['apoderado_dni']);
                unset($paciente['apoderado_telefono']);
                unset($paciente['tipo_familiar']);
            }

            return $paciente;
        } catch (PDOException $e) {
            error_log("❌ Error al obtener paciente: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Verificar/crear historia clínica
    // =====================================================
    public function obtenerOCrearHistoriaClinica($idPaciente) {
        try {
            // Verificar si existe historia clínica
            $sql = "SELECT * FROM historia_clinica WHERE id_paciente = :id_paciente";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_paciente' => $idPaciente]);
            $historia = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si no existe, crear una nueva
            if (!$historia) {
                $sqlInsert = "INSERT INTO historia_clinica (id_paciente, fecha_creacion, notas) 
                              VALUES (:id_paciente, NOW(), '')";
                $stmtInsert = $this->conexion->prepare($sqlInsert);
                $stmtInsert->execute([':id_paciente' => $idPaciente]);
                
                $idHistoria = $this->conexion->lastInsertId();
                
                // Obtener la historia recién creada
                $stmt->execute([':id_paciente' => $idPaciente]);
                $historia = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return $historia;
        } catch (PDOException $e) {
            error_log("❌ Error al obtener/crear historia clínica: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Listar alergias del paciente
    // =====================================================
    public function listarAlergiasPaciente($idPaciente) {
        try {
            $sql = "SELECT pa.id_paciente_alergia, 
                           pa.id_alergia_medicamentos,
                           am.medicamento
                    FROM paciente_alergias pa
                    INNER JOIN alergias_medicamentos am ON pa.id_alergia_medicamentos = am.id_alergia_medicamentos
                    WHERE pa.id_paciente = :id_paciente
                    ORDER BY am.medicamento ASC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_paciente' => $idPaciente]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al listar alergias: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 NUEVO: Buscar medicamentos en la base de datos
    // =====================================================
    public function buscarMedicamentos($termino) {
        try {
            $sql = "SELECT id_alergia_medicamentos, medicamento
                    FROM alergias_medicamentos
                    WHERE medicamento LIKE :termino
                    ORDER BY medicamento ASC
                    LIMIT 10";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':termino' => "%$termino%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error al buscar medicamentos: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Guardar alergias del paciente
    // =====================================================
    public function guardarAlergiasPaciente($idPaciente, $medicamentos) {
        try {
            $this->conexion->beginTransaction();

            // 1. Eliminar alergias existentes del paciente
            $sqlDelete = "DELETE FROM paciente_alergias WHERE id_paciente = :id_paciente";
            $stmtDelete = $this->conexion->prepare($sqlDelete);
            $stmtDelete->execute([':id_paciente' => $idPaciente]);

            // 2. Insertar nuevas alergias
            foreach ($medicamentos as $medicamento) {
                $medicamento = trim($medicamento);
                if (empty($medicamento)) continue;

                // Verificar si el medicamento existe en la tabla alergias_medicamentos
                $sqlCheck = "SELECT id_alergia_medicamentos FROM alergias_medicamentos WHERE medicamento = :medicamento";
                $stmtCheck = $this->conexion->prepare($sqlCheck);
                $stmtCheck->execute([':medicamento' => $medicamento]);
                $alergia = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                // Si no existe, crear el medicamento
                if (!$alergia) {
                    $sqlInsertMed = "INSERT INTO alergias_medicamentos (medicamento) VALUES (:medicamento)";
                    $stmtInsertMed = $this->conexion->prepare($sqlInsertMed);
                    $stmtInsertMed->execute([':medicamento' => $medicamento]);
                    $idAlergiaMedicamento = $this->conexion->lastInsertId();
                } else {
                    $idAlergiaMedicamento = $alergia['id_alergia_medicamentos'];
                }

                // Insertar en paciente_alergias
                $sqlInsert = "INSERT INTO paciente_alergias (id_paciente, id_alergia_medicamentos) 
                              VALUES (:id_paciente, :id_alergia_medicamentos)";
                $stmtInsert = $this->conexion->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':id_paciente' => $idPaciente,
                    ':id_alergia_medicamentos' => $idAlergiaMedicamento
                ]);
            }

            $this->conexion->commit();
            return true;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al guardar alergias: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Obtener estadísticas del paciente
    // =====================================================
    public function obtenerEstadisticasPaciente($idPaciente) {
        try {
            $estadisticas = [];

            // Total de citas
            $sqlCitas = "SELECT COUNT(*) as total FROM citas WHERE id_paciente = :id_paciente";
            $stmtCitas = $this->conexion->prepare($sqlCitas);
            $stmtCitas->execute([':id_paciente' => $idPaciente]);
            $estadisticas['total_citas'] = $stmtCitas->fetch(PDO::FETCH_ASSOC)['total'];

            // Total de atenciones
            $sqlAtenciones = "SELECT COUNT(*) as total 
                              FROM atenciones a
                              INNER JOIN citas c ON a.id_cita = c.id_cita
                              WHERE c.id_paciente = :id_paciente";
            $stmtAtenciones = $this->conexion->prepare($sqlAtenciones);
            $stmtAtenciones->execute([':id_paciente' => $idPaciente]);
            $estadisticas['total_atenciones'] = $stmtAtenciones->fetch(PDO::FETCH_ASSOC)['total'];

            // Total de documentos
            $sqlDocs = "SELECT COUNT(*) as total FROM documentos_paciente WHERE id_paciente = :id_paciente";
            $stmtDocs = $this->conexion->prepare($sqlDocs);
            $stmtDocs->execute([':id_paciente' => $idPaciente]);
            $estadisticas['total_documentos'] = $stmtDocs->fetch(PDO::FETCH_ASSOC)['total'];

            // Última cita
            $sqlUltimaCita = "SELECT fecha, hora FROM citas 
                              WHERE id_paciente = :id_paciente 
                              ORDER BY fecha DESC, hora DESC 
                              LIMIT 1";
            $stmtUltimaCita = $this->conexion->prepare($sqlUltimaCita);
            $stmtUltimaCita->execute([':id_paciente' => $idPaciente]);
            $ultimaCita = $stmtUltimaCita->fetch(PDO::FETCH_ASSOC);
            $estadisticas['ultima_cita'] = $ultimaCita ?: null;

            return $estadisticas;
        } catch (PDOException $e) {
            error_log("❌ Error al obtener estadísticas: " . $e->getMessage());
            return [
                'total_citas' => 0,
                'total_atenciones' => 0,
                'total_documentos' => 0,
                'ultima_cita' => null
            ];
        }
    }

    // =====================================================
    // 🔹 Actualizar notas de historia clínica
    // =====================================================
    public function actualizarNotasHistoria($idHistoria, $notas) {
        try {
            $sql = "UPDATE historia_clinica SET notas = :notas WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':notas' => $notas,
                ':id_historia' => $idHistoria
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al actualizar notas: " . $e->getMessage());
            return false;
        }
    }
}
?>