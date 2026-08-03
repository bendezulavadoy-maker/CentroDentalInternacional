<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloMiAgenda {

    private $conexion;

    public function __construct() {
        $db = new Conexion();
        $this->conexion = $db->getConexion();
    }

    public function listarCitas($id_doctor, $desde = null, $hasta = null) {
        try {
            $sql = "SELECT
                        c.id_cita, c.fecha, c.hora, c.motivo,
                        c.duracion_minutos,
                        ec.estado, ec.id_estado_cita,
                        ts.nombre_servicio,
                        ta.nombre AS tipo_atencion,
                        s.nombre_sede,
                        CONCAT(p.nombre, ' ', p.apellido) AS nombre_paciente,
                        p.dni AS dni_paciente,
                        p.telefono AS telefono_paciente,
                        p.correo AS correo_paciente,
                        p.fecha_nacimiento,
                        p.foto AS foto_paciente,
                        p.id_paciente,
                        p.observaciones
                    FROM citas c
                    INNER JOIN pacientes p      ON c.id_paciente = p.id_paciente
                    INNER JOIN estado_cita ec   ON c.id_estado_cita = ec.id_estado_cita
                    LEFT  JOIN tipo_servicio ts ON c.id_tipo_servicio = ts.id_tipo_servicio
                    LEFT  JOIN tipos_atencion ta ON c.id_tipo_atencion = ta.id_tipo_atencion
                    INNER JOIN sedes s          ON c.id_sede_atencion = s.id_sede_atencion
                    WHERE c.id_doctor = :id_doctor";

            $params = [':id_doctor' => $id_doctor];

            if ($desde && $hasta) {
                $sql .= " AND c.fecha BETWEEN :desde AND :hasta";
                $params[':desde'] = $desde;
                $params[':hasta'] = $hasta;
            }

            $sql .= " ORDER BY c.fecha ASC, c.hora ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("listarCitas: " . $e->getMessage());
            return [];
        }
    }

    public function verDetalleCita($id_cita, $id_doctor) {
        try {
            $sql = "SELECT
                        c.*, ec.estado, ec.id_estado_cita,
                        ts.nombre_servicio,
                        ta.nombre AS tipo_atencion,
                        s.nombre_sede, s.direccion_sede,
                        CONCAT(p.nombre, ' ', p.apellido) AS nombre_paciente,
                        p.dni AS dni_paciente,
                        p.telefono AS telefono_paciente,
                        p.correo AS correo_paciente,
                        p.fecha_nacimiento, p.foto AS foto_paciente,
                        p.id_paciente, p.observaciones
                    FROM citas c
                    INNER JOIN pacientes p      ON c.id_paciente = p.id_paciente
                    INNER JOIN estado_cita ec   ON c.id_estado_cita = ec.id_estado_cita
                    LEFT  JOIN tipo_servicio ts ON c.id_tipo_servicio = ts.id_tipo_servicio
                    LEFT  JOIN tipos_atencion ta ON c.id_tipo_atencion = ta.id_tipo_atencion
                    INNER JOIN sedes s          ON c.id_sede_atencion = s.id_sede_atencion
                    WHERE c.id_cita = :id AND c.id_doctor = :doc";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id_cita, ':doc' => $id_doctor]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cita) return ['success' => false, 'mensaje' => 'Cita no encontrada'];
            return $cita;

        } catch (PDOException $e) {
            error_log("verDetalleCita: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al obtener la cita'];
        }
    }

    public function cambiarEstadoCita($id_cita, $id_estado, $id_doctor) {
        try {
            $check = $this->conexion->prepare(
                "SELECT id_cita FROM citas WHERE id_cita = :id AND id_doctor = :doc"
            );
            $check->execute([':id' => $id_cita, ':doc' => $id_doctor]);
            if (!$check->fetch()) {
                return ['success' => false, 'mensaje' => 'No tienes permiso sobre esta cita'];
            }
            $stmt = $this->conexion->prepare(
                "UPDATE citas SET id_estado_cita = :estado WHERE id_cita = :id"
            );
            $stmt->execute([':estado' => $id_estado, ':id' => $id_cita]);
            return ['success' => true, 'mensaje' => 'Estado actualizado correctamente'];

        } catch (PDOException $e) {
            error_log("cambiarEstadoCita: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al actualizar el estado'];
        }
    }
}
?>