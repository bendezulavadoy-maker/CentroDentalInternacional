<?php
require_once '../CONFIG/conexion.php';

class ModeloPortal {
    private $db;

    public function __construct() {
        $this->db = (new Conexion())->getConexion();
    }

    // ══ AUTENTICACIÓN ════════════════════════════════════════════

    public function verificarDni($dni) {
        $stmt = $this->db->prepare(
            "SELECT id_paciente, nombre, apellido, contrasena_portal
             FROM pacientes WHERE dni = :dni LIMIT 1"
        );
        $stmt->execute([':dni' => $dni]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['existe' => false];
        return [
            'existe'        => true,
            'nombre'        => $row['nombre'],
            'id_paciente'   => $row['id_paciente'],
            'tiene_password'=> !empty($row['contrasena_portal']),
        ];
    }

    public function login($dni, $password) {
        $stmt = $this->db->prepare(
            "SELECT id_paciente, nombre, apellido, contrasena_portal
             FROM pacientes WHERE dni = :dni LIMIT 1"
        );
        $stmt->execute([':dni' => $dni]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($password, $row['contrasena_portal'])) {
            return ['success' => false, 'mensaje' => 'DNI o contraseña incorrectos'];
        }
        // Iniciar sesión del portal
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['portal'] = [
            'id_paciente' => $row['id_paciente'],
            'nombre'      => $row['nombre'],
            'apellido'    => $row['apellido'],
        ];
        return ['success' => true];
    }

    public function crearPassword($dni, $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            "UPDATE pacientes SET contrasena_portal = :hash WHERE dni = :dni"
        );
        $stmt->execute([':hash' => $hash, ':dni' => $dni]);
        if ($stmt->rowCount() === 0) return ['success' => false, 'mensaje' => 'DNI no encontrado'];
        // Loguear automáticamente
        $p = $this->db->prepare("SELECT id_paciente, nombre, apellido FROM pacientes WHERE dni=:d");
        $p->execute([':d' => $dni]);
        $row = $p->fetch(PDO::FETCH_ASSOC);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['portal'] = [
            'id_paciente' => $row['id_paciente'],
            'nombre'      => $row['nombre'],
            'apellido'    => $row['apellido'],
        ];
        return ['success' => true];
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        unset($_SESSION['portal']);
        session_destroy();
    }

    // ══ REGISTRO NUEVO PACIENTE ══════════════════════════════════

    public function registrarPaciente($datos, $fotoPath = null) {
        try {
            $this->db->beginTransaction();

            // Verificar DNI no duplicado
            $chk = $this->db->prepare("SELECT id_paciente FROM pacientes WHERE dni=:d");
            $chk->execute([':d' => $datos['dni']]);
            if ($chk->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'mensaje' => 'Este DNI ya está registrado'];
            }

            // Insertar apoderado si viene
            $id_apoderado = null;
            if (!empty($datos['apo_nombre'])) {
                $apo = $this->db->prepare(
                    "INSERT INTO apoderados (nombre, apellido, dni, telefono, id_tipo_familiar)
                     VALUES (:n, :a, :d, :t, :p)"
                );
                $apo->execute([
                    ':n' => $datos['apo_nombre'],
                    ':a' => $datos['apo_apellido'],
                    ':d' => $datos['apo_dni'],
                    ':t' => $datos['apo_telefono'],
                    ':p' => $datos['apo_parentesco'],
                ]);
                $id_apoderado = $this->db->lastInsertId();
            }

            $hash = password_hash($datos['password'], PASSWORD_BCRYPT);

            $sql = "INSERT INTO pacientes
                    (nombre, apellido, dni, fecha_nacimiento, telefono, correo,
                     direccion, ocupacion, id_sexo, id_estado_civil, id_grado_instruccion,
                     id_apoderado, foto, contrasena_portal, fecha_registro)
                    VALUES
                    (:nombre, :apellido, :dni, :fecha_nac, :telefono, :correo,
                     :direccion, :ocupacion, :id_sexo, :id_estado_civil, :id_grado,
                     :id_apoderado, :foto, :hash, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre'           => $datos['nombre'],
                ':apellido'         => $datos['apellido'],
                ':dni'              => $datos['dni'],
                ':fecha_nac'        => $datos['fecha_nac'],
                ':telefono'         => $datos['telefono'],
                ':correo'           => $datos['correo'],
                ':direccion'        => $datos['direccion'],
                ':ocupacion'        => $datos['ocupacion'],
                ':id_sexo'          => $datos['id_sexo'] ?: null,
                ':id_estado_civil'  => $datos['id_estado_civil'] ?: null,
                ':id_grado'         => $datos['id_grado'] ?: null,
                ':id_apoderado'     => $id_apoderado,
                ':foto'             => $fotoPath,
                ':hash'             => $hash,
            ]);
            $id_paciente = $this->db->lastInsertId();

            $this->db->commit();

            // Iniciar sesión del portal
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['portal'] = [
                'id_paciente' => $id_paciente,
                'nombre'      => $datos['nombre'],
                'apellido'    => $datos['apellido'],
            ];
            return ['success' => true, 'id_paciente' => $id_paciente];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("registrarPaciente: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al registrar. Intenta nuevamente.'];
        }
    }

    // ══ CATÁLOGOS ════════════════════════════════════════════════

    public function catalogos() {
        $tablas = [
            'sexo'         => ['SELECT id_sexo, nombre_sexo FROM sexo', []],
            'estado_civil' => ['SELECT id_estado_civil, nombre_estado_civil FROM estado_civil', []],
            'grado'        => ['SELECT id_grado_instruccion, nombre_grado_instruccion FROM grado_instruccion', []],
            'parentesco'   => ['SELECT id_tipo_familiar, descripcion FROM tipo_familiar', []],
        ];
        $result = [];
        foreach ($tablas as $key => [$sql, $params]) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result;
    }

    // ══ DATOS DEL PACIENTE LOGUEADO ══════════════════════════════

    public function getPaciente($id) {
        $stmt = $this->db->prepare(
            "SELECT p.*, s.nombre_sexo, ec.nombre_estado_civil,
                    gi.nombre_grado_instruccion, a.nombre AS apo_nombre,
                    a.apellido AS apo_apellido, a.dni AS apo_dni,
                    a.telefono AS apo_telefono, tf.descripcion AS apo_parentesco
             FROM pacientes p
             LEFT JOIN sexo s               ON p.id_sexo = s.id_sexo
             LEFT JOIN estado_civil ec      ON p.id_estado_civil = ec.id_estado_civil
             LEFT JOIN grado_instruccion gi ON p.id_grado_instruccion = gi.id_grado_instruccion
             LEFT JOIN apoderados a         ON p.id_apoderado = a.id_apoderado
             LEFT JOIN tipo_familiar tf     ON a.id_tipo_familiar = tf.id_tipo_familiar
             WHERE p.id_paciente = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ══ CITAS DEL PACIENTE ═══════════════════════════════════════

    public function getCitasPaciente($id_paciente) {
        $stmt = $this->db->prepare(
            "SELECT c.id_cita, c.fecha, c.hora, c.motivo, c.duracion_minutos,
                    c.id_doctor, c.id_sede_atencion,
                    CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor,
                    s.nombre_sede, s.direccion_sede,
                    ta.nombre AS tipo_atencion,
                    ec.estado, ec.id_estado_cita
             FROM citas c
             INNER JOIN usuarios u     ON c.id_doctor = u.id_usuario
             INNER JOIN estado_cita ec ON c.id_estado_cita = ec.id_estado_cita
             LEFT  JOIN sedes s        ON c.id_sede_atencion = s.id_sede_atencion
             LEFT  JOIN tipos_atencion ta ON c.id_tipo_atencion = ta.id_tipo_atencion
             WHERE c.id_paciente = :id
             ORDER BY c.fecha DESC, c.id_cita DESC"
        );
        $stmt->execute([':id' => $id_paciente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══ OBTENER DATOS CITA PARA WHATSAPP ════════════════════════

    public function getCitaParaWA($id_cita, $id_paciente) {
        $stmt = $this->db->prepare(
            "SELECT c.fecha, c.hora,
                    CONCAT(p.nombre,' ',p.apellido) AS nombre_paciente,
                    p.telefono AS telefono_paciente,
                    CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor,
                    s.nombre_sede
             FROM citas c
             INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
             INNER JOIN usuarios u  ON c.id_doctor   = u.id_usuario
             LEFT  JOIN sedes s     ON c.id_sede_atencion = s.id_sede_atencion
             WHERE c.id_cita = :id AND c.id_paciente = :pac"
        );
        $stmt->execute([':id' => $id_cita, ':pac' => $id_paciente]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ══ CANCELAR CITA ════════════════════════════════════════════

    public function cancelarCita($id_cita, $id_paciente) {
        // Verificar que la cita es del paciente y está en estado cancelable
        $stmt = $this->db->prepare(
            "SELECT id_estado_cita, fecha FROM citas
             WHERE id_cita=:id AND id_paciente=:pac"
        );
        $stmt->execute([':id' => $id_cita, ':pac' => $id_paciente]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cita) return ['success' => false, 'mensaje' => 'Cita no encontrada'];
        if (!in_array($cita['id_estado_cita'], [1,2]))
            return ['success' => false, 'mensaje' => 'Esta cita no puede cancelarse'];
        if ($cita['fecha'] < date('Y-m-d'))
            return ['success' => false, 'mensaje' => 'No puedes cancelar una cita pasada'];

        $upd = $this->db->prepare("UPDATE citas SET id_estado_cita=3 WHERE id_cita=:id");
        $upd->execute([':id' => $id_cita]);
        return ['success' => true, 'mensaje' => 'Cita cancelada correctamente'];
    }

    // ══ DISPONIBILIDAD PARA NUEVA CITA / REPROGRAMAR ═════════════

    public function getDoctores() {
        $stmt = $this->db->query(
            "SELECT id_usuario, nombre, apellidos FROM usuarios WHERE id_rol=2 AND id_estado=1 ORDER BY nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSedes() {
        $stmt = $this->db->query(
            "SELECT id_sede_atencion, nombre_sede, direccion_sede FROM sedes WHERE activo=1 ORDER BY nombre_sede"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTiposAtencion() {
        $stmt = $this->db->query(
            "SELECT id_tipo_atencion, nombre, duracion_minutos FROM tipos_atencion WHERE activo=1 ORDER BY nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══ AGENDAR NUEVA CITA ═══════════════════════════════════════

    public function agendarCita($datos) {
        try {
            // Validar que la fecha no sea pasada
            if ($datos['fecha'] < date('Y-m-d'))
                return ['success' => false, 'mensaje' => 'No puedes agendar en una fecha pasada'];

            // Verificar slot disponible usando slotsDisponibles del modelo de configuración
            $slotsModel = new ModeloConfiguracion();
            $slots = $slotsModel->slotsDisponibles(
                $datos['id_doctor'], $datos['id_sede'], $datos['fecha'],
                $datos['duracion'], 0
            );
            if (!$slots['disponible'] || !in_array($datos['hora'], $slots['slots']))
                return ['success' => false, 'mensaje' => 'Ese horario ya no está disponible. Por favor elige otro.'];

            $sql = "INSERT INTO citas
                    (id_paciente, id_doctor, fecha, hora, id_estado_cita,
                     id_tipo_atencion, duracion_minutos, id_sede_atencion, motivo)
                    VALUES (:pac, :doc, :fecha, :hora, 1, :ta, :dur, :sede, :motivo)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':pac'   => $datos['id_paciente'],
                ':doc'   => $datos['id_doctor'],
                ':fecha' => $datos['fecha'],
                ':hora'  => $datos['hora'] . ':00',
                ':ta'    => $datos['id_tipo_atencion'] ?: null,
                ':dur'   => $datos['duracion'],
                ':sede'  => $datos['id_sede'],
                ':motivo'=> $datos['motivo'],
            ]);
            return ['success' => true, 'id_cita' => $this->db->lastInsertId()];

        } catch (PDOException $e) {
            error_log("agendarCita portal: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al agendar. Intenta nuevamente.'];
        }
    }

    // ══ REPROGRAMAR CITA ═════════════════════════════════════════

    public function reprogramarCita($id_cita, $id_paciente, $nuevaFecha, $nuevaHora, $duracion, $id_sede, $id_doctor) {
        // Verificar que la cita pertenece al paciente y es reprogramable
        $stmt = $this->db->prepare(
            "SELECT id_estado_cita FROM citas WHERE id_cita=:id AND id_paciente=:pac"
        );
        $stmt->execute([':id' => $id_cita, ':pac' => $id_paciente]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cita) return ['success' => false, 'mensaje' => 'Cita no encontrada'];
        if (!in_array($cita['id_estado_cita'], [1, 2]))
            return ['success' => false, 'mensaje' => 'Esta cita no puede reprogramarse'];

        // Verificar slot
        require_once '../MODELOS/modelo_configuracion.php';
        $slotsModel = new ModeloConfiguracion();
        $slots = $slotsModel->slotsDisponibles($id_doctor, $id_sede, $nuevaFecha, $duracion, $id_cita);
        if (!$slots['disponible'] || !in_array($nuevaHora, $slots['slots']))
            return ['success' => false, 'mensaje' => 'Ese horario ya no está disponible'];

        $upd = $this->db->prepare(
            "UPDATE citas SET fecha=:f, hora=:h, reprogramada_de=id_cita WHERE id_cita=:id"
        );
        $upd->execute([':f' => $nuevaFecha, ':h' => $nuevaHora . ':00', ':id' => $id_cita]);
        return ['success' => true];
    }
}
?>