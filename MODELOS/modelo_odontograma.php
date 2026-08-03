<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloOdontograma {

    private $con;

    public function __construct() {
        $db = new Conexion();
        $this->con = $db->getConexion();
    }

    // ── Versiones ─────────────────────────────────────────────────

    public function listarVersiones($id_paciente) {
        try {
            $sql = "SELECT v.*, 
                        CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor,
                        (SELECT COUNT(*) FROM odontograma_detalle WHERE id_version = v.id_version) AS total_hallazgos
                    FROM odontograma_versiones v
                    INNER JOIN usuarios u ON v.creado_por = u.id_usuario
                    WHERE v.id_paciente = :id
                    ORDER BY v.numero_version ASC";
            $s = $this->con->prepare($sql);
            $s->execute([':id' => $id_paciente]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarVersiones: " . $e->getMessage());
            return [];
        }
    }

    public function crearVersion($id_paciente, $id_usuario, $notas = '') {
        try {
            $this->con->beginTransaction();

            $s = $this->con->prepare(
                "SELECT COALESCE(MAX(numero_version), 0) + 1 AS siguiente 
                 FROM odontograma_versiones WHERE id_paciente = :id"
            );
            $s->execute([':id' => $id_paciente]);
            $siguiente = intval($s->fetchColumn());

            $this->con->prepare(
                "UPDATE odontograma_versiones SET es_vigente = 0 WHERE id_paciente = :id"
            )->execute([':id' => $id_paciente]);

            $s = $this->con->prepare(
                "INSERT INTO odontograma_versiones 
                    (id_paciente, creado_por, descripcion, numero_version, estado, es_vigente, notas)
                 VALUES (:pac, :usr, :desc, :num, 'borrador', 1, :notas)"
            );
            $s->execute([
                ':pac'   => $id_paciente,
                ':usr'   => $id_usuario,
                ':desc'  => "Versión $siguiente",
                ':num'   => $siguiente,
                ':notas' => $notas ?: null
            ]);

            $id = $this->con->lastInsertId();
            $this->con->commit();

            return ['success' => true, 'id_version' => $id, 'numero' => $siguiente];
        } catch (PDOException $e) {
            $this->con->rollBack();
            error_log("crearVersion: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al crear versión'];
        }
    }

    public function cerrarVersion($id_version, $id_usuario) {
        try {
            $s = $this->con->prepare(
                "SELECT id_version FROM odontograma_versiones 
                 WHERE id_version = :id AND creado_por = :usr AND estado = 'borrador'"
            );
            $s->execute([':id' => $id_version, ':usr' => $id_usuario]);
            if (!$s->fetch()) {
                return ['success' => false, 'mensaje' => 'No tienes permiso para cerrar esta versión'];
            }
            $this->con->prepare(
                "UPDATE odontograma_versiones SET estado = 'cerrado', es_vigente = 1 WHERE id_version = :id"
            )->execute([':id' => $id_version]);

            return ['success' => true, 'mensaje' => 'Versión cerrada correctamente'];
        } catch (PDOException $e) {
            error_log("cerrarVersion: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al cerrar versión'];
        }
    }

    public function actualizarNotas($id_version, $notas, $id_usuario) {
        try {
            $s = $this->con->prepare(
                "UPDATE odontograma_versiones SET notas = :notas 
                 WHERE id_version = :id AND creado_por = :usr AND estado = 'borrador'"
            );
            $s->execute([':notas' => $notas, ':id' => $id_version, ':usr' => $id_usuario]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("actualizarNotas: " . $e->getMessage());
            return ['success' => false];
        }
    }

    public function eliminarVersion($id_version, $id_usuario) {
        try {
            $this->con->beginTransaction();

            $s = $this->con->prepare(
                "SELECT id_paciente FROM odontograma_versiones 
                 WHERE id_version = :id AND creado_por = :usr AND estado = 'borrador'"
            );
            $s->execute([':id' => $id_version, ':usr' => $id_usuario]);
            $version = $s->fetch(PDO::FETCH_ASSOC);

            if (!$version) {
                $this->con->rollBack();
                return ['success' => false, 'mensaje' => 'No puedes eliminar esta versión'];
            }

            $id_paciente = $version['id_paciente'];

            $this->con->prepare(
                "DELETE FROM odontograma_detalle WHERE id_version = :id"
            )->execute([':id' => $id_version]);

            $this->con->prepare(
                "DELETE FROM odontograma_versiones WHERE id_version = :id"
            )->execute([':id' => $id_version]);

            // Renumerar versiones restantes
            $s = $this->con->prepare(
                "SELECT id_version FROM odontograma_versiones 
                 WHERE id_paciente = :pac ORDER BY id_version ASC"
            );
            $s->execute([':pac' => $id_paciente]);
            $restantes = $s->fetchAll(PDO::FETCH_COLUMN);

            $upd = $this->con->prepare(
                "UPDATE odontograma_versiones SET numero_version = :num WHERE id_version = :id"
            );
            foreach ($restantes as $i => $idV) {
                $upd->execute([':num' => $i + 1, ':id' => $idV]);
            }

            // Marcar la última como vigente
            if (!empty($restantes)) {
                $ultima = end($restantes);
                $this->con->prepare(
                    "UPDATE odontograma_versiones SET es_vigente = 1 WHERE id_version = :id"
                )->execute([':id' => $ultima]);
            }

            $this->con->commit();
            return ['success' => true, 'mensaje' => 'Versión eliminada correctamente'];

        } catch (PDOException $e) {
            $this->con->rollBack();
            error_log("eliminarVersion: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al eliminar: ' . $e->getMessage()];
        }
    }

    // ── Hallazgos ─────────────────────────────────────────────────

    public function cargarHallazgos($id_version) {
        try {
            $sql = "SELECT 
                        od.cara,
                        od.color,
                        od.sigla,
                        od.observacion,
                        e.id_estado,
                        e.nombre_estado,
                        e.color AS color_estado,
                        di.id_diente,
                        di.numero_fdi,
                        di.nombre AS nombre_diente
                    FROM odontograma_detalle od
                    INNER JOIN estados_diente e  ON od.id_estado = e.id_estado
                    INNER JOIN dientes di        ON od.id_diente = di.id_diente
                    WHERE od.id_version = :id
                    ORDER BY di.numero_fdi ASC";
            $s = $this->con->prepare($sql);
            $s->execute([':id' => $id_version]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("cargarHallazgos: " . $e->getMessage());
            return [];
        }
    }

    public function guardarHallazgo($id_version, $id_diente, $id_estado, $cara, $color, $sigla, $observacion, $id_usuario) {
        try {
            // Verificar que la versión es borrador y del doctor
            $s = $this->con->prepare(
                "SELECT id_version FROM odontograma_versiones 
                 WHERE id_version = :id AND creado_por = :usr AND estado = 'borrador'"
            );
            $s->execute([':id' => $id_version, ':usr' => $id_usuario]);
            if (!$s->fetch()) {
                return ['success' => false, 'mensaje' => 'Versión no editable'];
            }

            $cara = $cara ?: 'RECUADRO';

            // INSERT ... ON DUPLICATE KEY UPDATE (clave compuesta: id_version + id_diente + cara)
            $s = $this->con->prepare(
                "INSERT INTO odontograma_detalle 
                    (id_version, id_diente, id_estado, cara, color, sigla, observacion)
                 VALUES 
                    (:v, :d, :e, :c, :col, :sig, :obs)
                 ON DUPLICATE KEY UPDATE 
                    id_estado   = VALUES(id_estado),
                    color       = VALUES(color),
                    sigla       = VALUES(sigla),
                    observacion = VALUES(observacion)"
            );
            $s->execute([
                ':v'   => $id_version,
                ':d'   => $id_diente,
                ':e'   => $id_estado,
                ':c'   => $cara,
                ':col' => $color,
                ':sig' => $sigla ?: null,
                ':obs' => $observacion ?: null,
            ]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("guardarHallazgo: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()];
        }
    }

    public function borrarHallazgo($id_version, $id_diente, $cara, $id_usuario) {
        try {
            $s = $this->con->prepare(
                "SELECT id_version FROM odontograma_versiones 
                 WHERE id_version = :id AND creado_por = :usr AND estado = 'borrador'"
            );
            $s->execute([':id' => $id_version, ':usr' => $id_usuario]);
            if (!$s->fetch()) {
                return ['success' => false, 'mensaje' => 'Versión no editable'];
            }

            $cara = $cara ?: 'RECUADRO';

            $s = $this->con->prepare(
                "DELETE FROM odontograma_detalle 
                 WHERE id_version = :v AND id_diente = :d AND cara = :c"
            );
            $s->execute([':v' => $id_version, ':d' => $id_diente, ':c' => $cara]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("borrarHallazgo: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al borrar'];
        }
    }

    // ── Catálogos ─────────────────────────────────────────────────

    public function listarEstados() {
        try {
            $s = $this->con->query("SELECT * FROM estados_diente ORDER BY id_estado ASC");
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function listarDientes() {
        try {
            $s = $this->con->query("SELECT * FROM dientes ORDER BY numero_fdi ASC");
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>