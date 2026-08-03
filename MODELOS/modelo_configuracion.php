<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloConfiguracion {

    private $conexion;

    // Módulos disponibles en el sistema
    private $modulosDisponibles = [
        'pacientes'        => 'Pacientes',
        'personal'         => 'Personal',
        'citas'            => 'Citas',
        'mi_agenda'        => 'Mi Agenda',
        'historia_clinica' => 'Historias Clínicas',
        'cobros'           => 'Cobros',
        'configuracion'    => 'Configuración',
    ];

    public function __construct() {
        $db = new Conexion();
        $this->conexion = $db->getConexion();
    }

    // ── ROLES ──────────────────────────────────────────────────────

    public function listarRoles() {
        try {
            $stmt = $this->conexion->query("SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarRoles: " . $e->getMessage());
            return [];
        }
    }

    public function crearRol($nombre) {
        try {
            // Verificar que no exista
            $check = $this->conexion->prepare("SELECT id_rol FROM roles WHERE nombre_rol = :nombre");
            $check->execute([':nombre' => $nombre]);
            if ($check->fetch()) {
                return ['success' => false, 'mensaje' => 'Ya existe un rol con ese nombre'];
            }

            $stmt = $this->conexion->prepare("INSERT INTO roles (nombre_rol) VALUES (:nombre)");
            $stmt->execute([':nombre' => $nombre]);
            $id = $this->conexion->lastInsertId();
            return ['success' => true, 'id_rol' => $id, 'mensaje' => 'Rol creado correctamente'];
        } catch (PDOException $e) {
            error_log("crearRol: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al crear el rol'];
        }
    }

    public function editarRol($id, $nombre) {
        try {
            // Verificar duplicado excluyendo el actual
            $check = $this->conexion->prepare("SELECT id_rol FROM roles WHERE nombre_rol = :nombre AND id_rol != :id");
            $check->execute([':nombre' => $nombre, ':id' => $id]);
            if ($check->fetch()) {
                return ['success' => false, 'mensaje' => 'Ya existe un rol con ese nombre'];
            }

            $stmt = $this->conexion->prepare("UPDATE roles SET nombre_rol = :nombre WHERE id_rol = :id");
            $stmt->execute([':nombre' => $nombre, ':id' => $id]);
            return ['success' => true, 'mensaje' => 'Rol actualizado correctamente'];
        } catch (PDOException $e) {
            error_log("editarRol: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al editar el rol'];
        }
    }

    public function eliminarRol($id) {
        try {
            // Verificar si tiene usuarios asignados
            $check = $this->conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE id_rol = :id");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) {
                return ['success' => false, 'mensaje' => 'No se puede eliminar: hay usuarios con este rol'];
            }

            $stmt = $this->conexion->prepare("DELETE FROM roles WHERE id_rol = :id");
            $stmt->execute([':id' => $id]);
            return ['success' => true, 'mensaje' => 'Rol eliminado correctamente'];
        } catch (PDOException $e) {
            error_log("eliminarRol: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al eliminar el rol'];
        }
    }

    // ── PERMISOS ───────────────────────────────────────────────────

    public function listarPermisosCompleto() {
        try {
            $roles = $this->listarRoles();

            // Traer permisos actuales de todos los roles
            $stmt     = $this->conexion->query("SELECT id_rol, modulo FROM permisos_rol");
            $actuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Indexar por rol
            $mapa = [];
            foreach ($actuales as $p) {
                $mapa[$p['id_rol']][] = $p['modulo'];
            }

            return [
                'roles'   => $roles,
                'modulos' => $this->modulosDisponibles,
                'mapa'    => $mapa
            ];
        } catch (PDOException $e) {
            error_log("listarPermisosCompleto: " . $e->getMessage());
            return ['roles' => [], 'modulos' => [], 'mapa' => []];
        }
    }

    public function guardarPermisos($permisos) {
        // $permisos = [ ['id_rol' => 1, 'modulo' => 'pacientes'], ... ]
        try {
            $this->conexion->beginTransaction();

            // Borrar todos los permisos actuales y reinsertar
            $this->conexion->exec("DELETE FROM permisos_rol");

            if (!empty($permisos)) {
                $stmt = $this->conexion->prepare(
                    "INSERT INTO permisos_rol (id_rol, modulo) VALUES (:id_rol, :modulo)"
                );
                foreach ($permisos as $p) {
                    if (!empty($p['id_rol']) && !empty($p['modulo'])) {
                        $stmt->execute([':id_rol' => $p['id_rol'], ':modulo' => $p['modulo']]);
                    }
                }
            }

            $this->conexion->commit();
            return ['success' => true, 'mensaje' => 'Permisos guardados correctamente'];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("guardarPermisos: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al guardar permisos'];
        }
    }

    // ── SEDES ──────────────────────────────────────────────────────

    public function listarSedes() {
        try {
            $stmt = $this->conexion->query(
                "SELECT id_sede_atencion, nombre_sede, direccion_sede, telefono_sede, activo FROM sedes ORDER BY id_sede_atencion ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarSedes: " . $e->getMessage());
            return [];
        }
    }

    public function crearSede($nombre, $direccion, $telefono, $activo) {
        try {
            $stmt = $this->conexion->prepare(
                "INSERT INTO sedes (nombre_sede, direccion_sede, telefono_sede, activo) VALUES (:nombre, :dir, :tel, :activo)"
            );
            $stmt->execute([':nombre' => $nombre, ':dir' => $direccion, ':tel' => $telefono, ':activo' => $activo]);
            return ['success' => true, 'mensaje' => 'Sede creada correctamente'];
        } catch (PDOException $e) {
            error_log("crearSede: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al crear la sede'];
        }
    }

    public function editarSede($id, $nombre, $direccion, $telefono, $activo) {
        try {
            $stmt = $this->conexion->prepare(
                "UPDATE sedes SET nombre_sede=:nombre, direccion_sede=:dir, telefono_sede=:tel, activo=:activo WHERE id_sede_atencion=:id"
            );
            $stmt->execute([':nombre' => $nombre, ':dir' => $direccion, ':tel' => $telefono, ':activo' => $activo, ':id' => $id]);
            return ['success' => true, 'mensaje' => 'Sede actualizada correctamente'];
        } catch (PDOException $e) {
            error_log("editarSede: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al actualizar la sede'];
        }
    }
    // ── SERVICIOS ──────────────────────────────────────────────────

    public function listarServicios() {
        try {
            $s = $this->conexion->query(
                "SELECT id_tipo_servicio, nombre_servicio, precio_base, activo
                 FROM tipo_servicio ORDER BY nombre_servicio ASC"
            );
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarServicios: " . $e->getMessage());
            return [];
        }
    }

    public function crearServicio($nombre, $precio, $activo) {
        try {
            $chk = $this->conexion->prepare(
                "SELECT id_tipo_servicio FROM tipo_servicio WHERE nombre_servicio = :n"
            );
            $chk->execute([':n' => $nombre]);
            if ($chk->fetch()) {
                return ['success' => false, 'mensaje' => 'Ya existe un servicio con ese nombre'];
            }
            $s = $this->conexion->prepare(
                "INSERT INTO tipo_servicio (nombre_servicio, precio_base, activo)
                 VALUES (:n, :p, :a)"
            );
            $s->execute([':n' => $nombre, ':p' => $precio, ':a' => $activo]);
            return ['success' => true, 'mensaje' => 'Servicio creado correctamente'];
        } catch (PDOException $e) {
            error_log("crearServicio: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al crear servicio'];
        }
    }

    public function editarServicio($id, $nombre, $precio, $activo) {
        try {
            $s = $this->conexion->prepare(
                "UPDATE tipo_servicio SET nombre_servicio = :n, precio_base = :p, activo = :a
                 WHERE id_tipo_servicio = :id"
            );
            $s->execute([':n' => $nombre, ':p' => $precio, ':a' => $activo, ':id' => $id]);
            return ['success' => true, 'mensaje' => 'Servicio actualizado correctamente'];
        } catch (PDOException $e) {
            error_log("editarServicio: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al actualizar servicio'];
        }
    }

    // ── PLANES ─────────────────────────────────────────────────────

    public function listarPlanesConfig() {
        try {
            $planes = $this->conexion->query(
                "SELECT * FROM planes_tratamiento ORDER BY nombre_plan ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($planes as &$p) {
                $s = $this->conexion->prepare(
                    "SELECT * FROM plan_pasos WHERE id_plan = :id ORDER BY numero_paso ASC"
                );
                $s->execute([':id' => $p['id_plan']]);
                $p['pasos'] = $s->fetchAll(PDO::FETCH_ASSOC);
            }
            return $planes;
        } catch (PDOException $e) {
            error_log("listarPlanesConfig: " . $e->getMessage());
            return [];
        }
    }

    public function guardarPlan($datos) {
        try {
            $id     = intval($datos['id_plan'] ?? 0);
            $activo = intval($datos['activo'] ?? 1);
    
            if ($id > 0) {
                $s = $this->conexion->prepare(
                    "UPDATE planes_tratamiento SET
                        nombre_plan = :nombre, descripcion = :desc,
                        costo_referencial = :cref, activo = :activo
                     WHERE id_plan = :id"
                );
                $s->execute([
                    ':nombre' => $datos['nombre_plan'],
                    ':desc'   => $datos['descripcion'] ?: null,
                    ':cref'   => $datos['costo_referencial'] ?: null,
                    ':activo' => $activo,
                    ':id'     => $id
                ]);
            } else {
                $s = $this->conexion->prepare(
                    "INSERT INTO planes_tratamiento
                        (nombre_plan, descripcion, costo_referencial, activo)
                     VALUES (:nombre, :desc, :cref, :activo)"
                );
                $s->execute([
                    ':nombre' => $datos['nombre_plan'],
                    ':desc'   => $datos['descripcion'] ?: null,
                    ':cref'   => $datos['costo_referencial'] ?: null,
                    ':activo' => $activo,
                ]);
            }
    
            return ['success' => true, 'mensaje' => 'Plan guardado correctamente'];
    
        } catch (PDOException $e) {
            error_log("guardarPlan: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al guardar plan'];
        }
    }

    public function eliminarPlan($id) {
        try {
            // Verificar que no tiene instancias activas
            $chk = $this->conexion->prepare(
                "SELECT COUNT(*) FROM paciente_planes WHERE id_plan = :id"
            );
            $chk->execute([':id' => $id]);
            if ($chk->fetchColumn() > 0) {
                return ['success' => false, 'mensaje' => 'No se puede eliminar: hay pacientes con este plan'];
            }
            $this->conexion->prepare(
                "DELETE FROM planes_tratamiento WHERE id_plan = :id"
            )->execute([':id' => $id]);
            return ['success' => true, 'mensaje' => 'Plan eliminado correctamente'];
        } catch (PDOException $e) {
            error_log("eliminarPlan: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al eliminar plan'];
        }
    }
    public function listarAparatologia() {
        try {
            $s = $this->conexion->query(
                "SELECT id_aparatologia, nombre, precio_base, activo
                 FROM aparatologia ORDER BY nombre ASC"
            );
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarAparatologia: " . $e->getMessage());
            return [];
        }
    }

    public function crearAparatologia($nombre, $precio, $activo) {
        try {
            $chk = $this->conexion->prepare(
                "SELECT id_aparatologia FROM aparatologia WHERE nombre = :n"
            );
            $chk->execute([':n' => $nombre]);
            if ($chk->fetch()) {
                return ['success' => false, 'mensaje' => 'Ya existe un elemento con ese nombre'];
            }
            $s = $this->conexion->prepare(
                "INSERT INTO aparatologia (nombre, precio_base, activo)
                 VALUES (:n, :p, :a)"
            );
            $s->execute([':n' => $nombre, ':p' => $precio, ':a' => $activo]);
            return ['success' => true, 'mensaje' => 'Aparatología creada correctamente'];
        } catch (PDOException $e) {
            error_log("crearAparatologia: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al crear'];
        }
    }

    public function editarAparatologia($id, $nombre, $precio, $activo) {
        try {
            $s = $this->conexion->prepare(
                "UPDATE aparatologia SET nombre = :n, precio_base = :p, activo = :a
                 WHERE id_aparatologia = :id"
            );
            $s->execute([':n' => $nombre, ':p' => $precio, ':a' => $activo, ':id' => $id]);
            return ['success' => true, 'mensaje' => 'Aparatología actualizada correctamente'];
        } catch (PDOException $e) {
            error_log("editarAparatologia: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al actualizar'];
        }
    }
    // ══════════════════════════════════════════════════════════════
    // TIPOS DE ATENCIÓN
    // ══════════════════════════════════════════════════════════════

    public function listarTiposAtencion() {
        try {
            $s = $this->conexion->query("SELECT * FROM tipos_atencion ORDER BY nombre ASC");
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log($e->getMessage()); return []; }
    }

    public function crearTipoAtencion($nombre, $duracion, $color) {
        try {
            $this->conexion->prepare(
                "INSERT INTO tipos_atencion (nombre, duracion_minutos, color) VALUES (:n,:d,:c)"
            )->execute([':n'=>$nombre, ':d'=>$duracion, ':c'=>$color]);
            return ['success'=>true];
        } catch (PDOException $e) { error_log($e->getMessage()); return ['success'=>false,'mensaje'=>'Error al crear']; }
    }

    public function editarTipoAtencion($id, $nombre, $duracion, $color, $activo) {
        try {
            $this->conexion->prepare(
                "UPDATE tipos_atencion SET nombre=:n, duracion_minutos=:d, color=:c, activo=:a WHERE id_tipo_atencion=:id"
            )->execute([':n'=>$nombre, ':d'=>$duracion, ':c'=>$color, ':a'=>$activo, ':id'=>$id]);
            return ['success'=>true];
        } catch (PDOException $e) { error_log($e->getMessage()); return ['success'=>false,'mensaje'=>'Error al editar']; }
    }

    // ══════════════════════════════════════════════════════════════
    // HORARIOS DE DOCTORES
    // ══════════════════════════════════════════════════════════════

    public function listarDoctores() {
        try {
            $s = $this->conexion->query(
                "SELECT u.id_usuario, u.dni, CONCAT(u.nombre,' ',u.apellidos) AS nombre_completo
                 FROM usuarios u
                 INNER JOIN roles r ON u.id_rol = r.id_rol
                 WHERE r.nombre_rol = 'Odontólogo' AND u.id_rol IS NOT NULL
                 ORDER BY u.nombre ASC"
            );
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log($e->getMessage()); return []; }
    }

    public function listarHorariosDoctor($id_doctor, $fecha_ini, $fecha_fin) {
        try {
            $s = $this->conexion->prepare(
                "SELECT hd.*, s.nombre_sede
                 FROM horario_doctor hd
                 INNER JOIN sedes s ON hd.id_sede = s.id_sede_atencion
                 WHERE hd.id_doctor = :doc
                   AND hd.fecha BETWEEN :fi AND :ff
                   AND hd.activo = 1
                 ORDER BY hd.fecha ASC, hd.hora_inicio ASC"
            );
            $s->execute([':doc'=>$id_doctor, ':fi'=>$fecha_ini, ':ff'=>$fecha_fin]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log($e->getMessage()); return []; }
    }

    public function guardarHorario($data, $creado_por) {
        try {
            $id        = intval($data['id_horario']  ?? 0);
            $id_doctor = intval($data['id_doctor']   ?? 0);
            $id_sede   = intval($data['id_sede']     ?? 0);
            $fecha     = $data['fecha']              ?? '';
            $h_ini     = $data['hora_inicio']        ?? '';
            $h_fin     = $data['hora_fin']           ?? '';

            // ── Validaciones básicas ──────────────────────────────
            if (!$id_doctor || !$id_sede || !$fecha || !$h_ini || !$h_fin) {
                return ['success'=>false, 'mensaje'=>'Todos los campos son obligatorios'];
            }

            // Hora fin debe ser mayor a hora inicio
            if ($h_fin <= $h_ini) {
                return ['success'=>false, 'mensaje'=>'La hora fin debe ser mayor a la hora inicio'];
            }

            // Mínimo 30 minutos de duración
            $ini_ts = strtotime($fecha . ' ' . $h_ini);
            $fin_ts = strtotime($fecha . ' ' . $h_fin);
            if (($fin_ts - $ini_ts) < 1800) {
                return ['success'=>false, 'mensaje'=>'El horario debe tener al menos 30 minutos de duración'];
            }

            // No puede ser en el pasado (solo advertencia, permitir si es hoy)
            $fecha_ts = strtotime($fecha);
            if ($fecha_ts < strtotime(date('Y-m-d')) - 86400) {
                return ['success'=>false, 'mensaje'=>'No se puede registrar horario en fechas pasadas'];
            }

            // ── Validar solapamiento con otros horarios del mismo doctor ──
            // Un doctor no puede estar en dos lugares al mismo tiempo
            $sqlSolap = "SELECT hd.id_horario, s.nombre_sede,
                                hd.hora_inicio, hd.hora_fin
                         FROM horario_doctor hd
                         INNER JOIN sedes s ON hd.id_sede = s.id_sede_atencion
                         WHERE hd.id_doctor = :doc
                           AND hd.fecha = :fecha
                           AND hd.activo = 1
                           AND hd.id_horario != :excl
                           AND :h_ini < hd.hora_fin
                           AND :h_fin > hd.hora_inicio";

            $sChk = $this->conexion->prepare($sqlSolap);
            $sChk->execute([
                ':doc'   => $id_doctor,
                ':fecha' => $fecha,
                ':excl'  => $id > 0 ? $id : 0,
                ':h_ini' => $h_ini,
                ':h_fin' => $h_fin,
            ]);
            $conflicto = $sChk->fetch(PDO::FETCH_ASSOC);

            if ($conflicto) {
                $hIni = substr($conflicto['hora_inicio'], 0, 5);
                $hFin = substr($conflicto['hora_fin'],    0, 5);
                return [
                    'success' => false,
                    'mensaje' => "Horario solapado con turno en {$conflicto['nombre_sede']} ({$hIni} – {$hFin}). Un doctor no puede estar en dos sedes al mismo tiempo."
                ];
            }

            // ── Validar que no haya citas programadas fuera del nuevo horario ──
            if ($id > 0) {
                $sqlCitas = "SELECT COUNT(*) FROM citas
                             WHERE id_doctor = :doc AND fecha = :fecha
                               AND id_estado_cita NOT IN (4,5)
                               AND (hora < :h_ini OR ADDTIME(hora, SEC_TO_TIME(duracion_minutos*60)) > :h_fin)";
                $sCitas = $this->conexion->prepare($sqlCitas);
                $sCitas->execute([':doc'=>$id_doctor, ':fecha'=>$fecha, ':h_ini'=>$h_ini, ':h_fin'=>$h_fin]);
                if ($sCitas->fetchColumn() > 0) {
                    return ['success'=>false, 'mensaje'=>'Existen citas programadas fuera del nuevo horario. Ajusta las citas antes de modificar el horario.'];
                }
            }

            // ── Guardar ───────────────────────────────────────────
            if ($id > 0) {
                $this->conexion->prepare(
                    "UPDATE horario_doctor
                     SET id_sede=:s, fecha=:f, hora_inicio=:hi, hora_fin=:hf
                     WHERE id_horario=:id AND id_doctor=:doc"
                )->execute([':s'=>$id_sede, ':f'=>$fecha, ':hi'=>$h_ini, ':hf'=>$h_fin, ':id'=>$id, ':doc'=>$id_doctor]);
            } else {
                $this->conexion->prepare(
                    "INSERT INTO horario_doctor (id_doctor,id_sede,fecha,hora_inicio,hora_fin)
                     VALUES (:doc,:s,:f,:hi,:hf)"
                )->execute([':doc'=>$id_doctor, ':s'=>$id_sede, ':f'=>$fecha, ':hi'=>$h_ini, ':hf'=>$h_fin]);
            }
            return ['success'=>true];

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) return ['success'=>false,'mensaje'=>'Error de duplicado — contacta al administrador'];
            error_log($e->getMessage());
            return ['success'=>false,'mensaje'=>'Error al guardar horario'];
        }
    }

    public function eliminarHorario($id) {
        try {
            $this->conexion->prepare("UPDATE horario_doctor SET activo=0 WHERE id_horario=:id")
                      ->execute([':id'=>$id]);
            return ['success'=>true];
        } catch (PDOException $e) { error_log($e->getMessage()); return ['success'=>false]; }
    }

    public function copiarSemana($id_doctor, $fecha_origen, $fecha_destino, $creado_por) {
        try {
            // Obtener lunes de cada semana
            $lunes_origen  = date('Y-m-d', strtotime('monday this week', strtotime($fecha_origen)));
            $lunes_destino = date('Y-m-d', strtotime('monday this week', strtotime($fecha_destino)));
            $diff_days     = (strtotime($lunes_destino) - strtotime($lunes_origen)) / 86400;

            if ($diff_days == 0) return ['success'=>false, 'mensaje'=>'Las semanas son iguales'];

            $s = $this->conexion->prepare(
                "SELECT * FROM horario_doctor
                 WHERE id_doctor=:doc AND activo=1
                   AND fecha BETWEEN :fi AND :ff"
            );
            $s->execute([
                ':doc' => $id_doctor,
                ':fi'  => $lunes_origen,
                ':ff'  => date('Y-m-d', strtotime($lunes_origen . ' +6 days'))
            ]);
            $horarios = $s->fetchAll(PDO::FETCH_ASSOC);

            if (empty($horarios)) return ['success'=>false, 'mensaje'=>'No hay horarios en la semana origen'];

            $ins = $this->conexion->prepare(
                "INSERT IGNORE INTO horario_doctor (id_doctor,id_sede,fecha,hora_inicio,hora_fin)
                 VALUES (:doc,:s,:f,:hi,:hf)"
            );
            $copiados = 0;
            foreach ($horarios as $h) {
                $nueva_fecha = date('Y-m-d', strtotime($h['fecha'] . " +{$diff_days} days"));
                $ins->execute([
                    ':doc' => $id_doctor,
                    ':s'   => $h['id_sede'],
                    ':f'   => $nueva_fecha,
                    ':hi'  => $h['hora_inicio'],
                    ':hf'  => $h['hora_fin'],
                ]);
                $copiados++;
            }
            return ['success'=>true, 'copiados'=>$copiados];
        } catch (PDOException $e) { error_log($e->getMessage()); return ['success'=>false,'mensaje'=>'Error al copiar']; }
    }

    // ══════════════════════════════════════════════════════════════
    // BLOQUEOS
    // ══════════════════════════════════════════════════════════════

    public function listarBloqueos($id_doctor, $fecha_ini, $fecha_fin) {
        try {
            $s = $this->conexion->prepare(
                "SELECT * FROM bloqueos_doctor
                 WHERE id_doctor=:doc AND fecha BETWEEN :fi AND :ff
                 ORDER BY fecha ASC, hora_inicio ASC"
            );
            $s->execute([':doc'=>$id_doctor, ':fi'=>$fecha_ini, ':ff'=>$fecha_fin]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log($e->getMessage()); return []; }
    }

    public function crearBloqueo($data, $creado_por) {
        try {
            $id_doctor = intval($data['id_doctor'] ?? 0);
            $fecha     = $data['fecha']            ?? '';
            $h_ini     = $data['hora_inicio']      ?: null;
            $h_fin     = $data['hora_fin']         ?: null;
            $motivo    = trim($data['motivo']      ?? '');

            if (!$id_doctor || !$fecha) return ['success'=>false,'mensaje'=>'Datos incompletos'];

            // Si es bloqueo parcial, validar horas
            if ($h_ini && $h_fin) {
                if ($h_fin <= $h_ini) {
                    return ['success'=>false,'mensaje'=>'La hora fin debe ser mayor a la hora inicio'];
                }
            }

            // Verificar citas en el período a bloquear
            $sqlCitas = $h_ini
                ? "SELECT COUNT(*) FROM citas
                   WHERE id_doctor=:doc AND fecha=:f
                     AND id_estado_cita NOT IN (4,5)
                     AND hora < :h_fin
                     AND ADDTIME(hora, SEC_TO_TIME(duracion_minutos*60)) > :h_ini"
                : "SELECT COUNT(*) FROM citas
                   WHERE id_doctor=:doc AND fecha=:f
                     AND id_estado_cita NOT IN (4,5)";

            $sCitas = $this->conexion->prepare($sqlCitas);
            $params = [':doc'=>$id_doctor, ':f'=>$fecha];
            if ($h_ini) { $params[':h_ini'] = $h_ini; $params[':h_fin'] = $h_fin; }
            $sCitas->execute($params);
            $nCitas = $sCitas->fetchColumn();

            if ($nCitas > 0) {
                return ['success'=>false,'mensaje'=>"Hay {$nCitas} cita(s) programada(s) en ese período. Cancela o reprograma las citas antes de bloquear."];
            }

            $this->conexion->prepare(
                "INSERT INTO bloqueos_doctor (id_doctor,fecha,hora_inicio,hora_fin,motivo,creado_por)
                 VALUES (:doc,:f,:hi,:hf,:mot,:cp)"
            )->execute([':doc'=>$id_doctor,':f'=>$fecha,':hi'=>$h_ini,':hf'=>$h_fin,':mot'=>$motivo,':cp'=>$creado_por]);
            return ['success'=>true];
        } catch (PDOException $e) { error_log($e->getMessage()); return ['success'=>false,'mensaje'=>'Error al crear bloqueo']; }
    }

    public function eliminarBloqueo($id) {
        try {
            $this->conexion->prepare("DELETE FROM bloqueos_doctor WHERE id_bloqueo=:id")->execute([':id'=>$id]);
            return ['success'=>true];
        } catch (PDOException $e) { error_log($e->getMessage()); return ['success'=>false]; }
    }

    // ══════════════════════════════════════════════════════════════
    // SLOTS DISPONIBLES
    // ══════════════════════════════════════════════════════════════

    public function slotsDisponibles($id_doctor, $id_sede, $fecha, $duracion_min, $excluir_cita = 0) {
        try {
            // 1. Obtener horario del doctor en esa sede y fecha
            $sHor = $this->conexion->prepare(
                "SELECT hora_inicio, hora_fin FROM horario_doctor
                 WHERE id_doctor=:doc AND id_sede=:sede AND fecha=:f AND activo=1"
            );
            $sHor->execute([':doc'=>$id_doctor, ':sede'=>$id_sede, ':f'=>$fecha]);
            $horario = $sHor->fetch(PDO::FETCH_ASSOC);

            if (!$horario) return ['disponible'=>false, 'slots'=>[], 'mensaje'=>'El doctor no tiene horario en esa sede ese día'];

            // 2. Bloqueos del día
            $sBloq = $this->conexion->prepare(
                "SELECT hora_inicio, hora_fin FROM bloqueos_doctor
                 WHERE id_doctor=:doc AND fecha=:f"
            );
            $sBloq->execute([':doc'=>$id_doctor, ':f'=>$fecha]);
            $bloqueos = $sBloq->fetchAll(PDO::FETCH_ASSOC);

            // 3. Citas existentes ese día (ocupan slots)
            $sCitas = $this->conexion->prepare(
                "SELECT hora, duracion_minutos FROM citas
                 WHERE id_doctor=:doc AND fecha=:f
                   AND id_cita != :excl
                   AND id_estado_cita NOT IN (4,5)
                 ORDER BY hora ASC"
            );
            $sCitas->execute([':doc'=>$id_doctor, ':f'=>$fecha, ':excl'=>$excluir_cita]);
            $citas_dia = $sCitas->fetchAll(PDO::FETCH_ASSOC);

            // 4. Generar slots de 30 min dentro del horario
            $slots    = [];
            $ini_ts   = strtotime($fecha . ' ' . $horario['hora_inicio']);
            $fin_ts   = strtotime($fecha . ' ' . $horario['hora_fin']);
            $slots_necesarios = ceil($duracion_min / 30);

            for ($ts = $ini_ts; $ts + ($duracion_min * 60) <= $fin_ts; $ts += 1800) {
                $hora_slot = date('H:i', $ts);
                $ts_fin_slot = $ts + ($duracion_min * 60);

                // Verificar contra bloqueos
                $bloqueado = false;
                foreach ($bloqueos as $b) {
                    if ($b['hora_inicio'] === null) { $bloqueado = true; break; }
                    $b_ini = strtotime($fecha . ' ' . $b['hora_inicio']);
                    $b_fin = strtotime($fecha . ' ' . $b['hora_fin']);
                    if ($ts < $b_fin && $ts_fin_slot > $b_ini) { $bloqueado = true; break; }
                }
                if ($bloqueado) continue;

                // Verificar contra citas existentes
                $ocupado = false;
                foreach ($citas_dia as $c) {
                    $c_ini = strtotime($fecha . ' ' . $c['hora']);
                    $c_fin = $c_ini + (intval($c['duracion_minutos']) * 60);
                    if ($ts < $c_fin && $ts_fin_slot > $c_ini) { $ocupado = true; break; }
                }
                if ($ocupado) continue;

                $slots[] = $hora_slot;
            }

            return ['disponible'=>true, 'slots'=>$slots, 'horario'=>$horario];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['disponible'=>false, 'slots'=>[], 'mensaje'=>'Error al calcular slots'];
        }
    }
    public function crearBloqueoRango($data, $creado_por) {
        try {
            $id_doctor  = intval($data['id_doctor']   ?? 0);
            $fecha_ini  = $data['fecha_inicio']       ?? '';
            $fecha_fin  = $data['fecha_fin']          ?? '';
            $h_ini      = $data['hora_inicio']        ?: null;
            $h_fin      = $data['hora_fin']           ?: null;
            $motivo     = trim($data['motivo']        ?? '');

            if (!$id_doctor || !$fecha_ini || !$fecha_fin) {
                return ['success'=>false, 'mensaje'=>'Datos incompletos'];
            }
            if ($fecha_fin < $fecha_ini) {
                return ['success'=>false, 'mensaje'=>'La fecha fin debe ser mayor o igual a la fecha inicio'];
            }
            if ($h_ini && $h_fin && $h_fin <= $h_ini) {
                return ['success'=>false, 'mensaje'=>'La hora fin debe ser mayor a la hora inicio'];
            }

            // Verificar citas en el rango completo
            $sqlCitas = $h_ini
                ? "SELECT COUNT(*) FROM citas
                   WHERE id_doctor=:doc AND fecha BETWEEN :fi AND :ff
                     AND id_estado_cita NOT IN (4,5)
                     AND hora < :h_fin
                     AND ADDTIME(hora, SEC_TO_TIME(duracion_minutos*60)) > :h_ini"
                : "SELECT COUNT(*) FROM citas
                   WHERE id_doctor=:doc AND fecha BETWEEN :fi AND :ff
                     AND id_estado_cita NOT IN (4,5)";

            $sCitas = $this->conexion->prepare($sqlCitas);
            $params = [':doc'=>$id_doctor, ':fi'=>$fecha_ini, ':ff'=>$fecha_fin];
            if ($h_ini) { $params[':h_ini'] = $h_ini; $params[':h_fin'] = $h_fin; }
            $sCitas->execute($params);
            $nCitas = $sCitas->fetchColumn();
            if ($nCitas > 0) {
                return ['success'=>false, 'mensaje'=>"Hay {$nCitas} cita(s) programada(s) en ese período."];
            }

            // Insertar un bloqueo por cada día del rango
            $ins = $this->conexion->prepare(
                "INSERT INTO bloqueos_doctor (id_doctor,fecha,hora_inicio,hora_fin,motivo,creado_por)
                 VALUES (:doc,:f,:hi,:hf,:mot,:cp)"
            );

            $dias = 0;
            $current = strtotime($fecha_ini);
            $end     = strtotime($fecha_fin);
            while ($current <= $end) {
                $fecha = date('Y-m-d', $current);
                $ins->execute([':doc'=>$id_doctor,':f'=>$fecha,':hi'=>$h_ini,':hf'=>$h_fin,':mot'=>$motivo,':cp'=>$creado_por]);
                $current = strtotime('+1 day', $current);
                $dias++;
            }

            return ['success'=>true, 'dias'=>$dias];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success'=>false, 'mensaje'=>'Error al crear bloqueo'];
        }
    }

    public function replicarRango($id_doctor, $semana_origen, $fecha_inicio, $fecha_fin, $creado_por) {
        try {
            // Obtener lunes de la semana origen
            $d = new DateTime($semana_origen);
            $d->modify('monday this week');
            $lunes_origen = $d->format('Y-m-d');
            $domingo_origen = (clone $d)->modify('+6 days')->format('Y-m-d');

            // Obtener horarios de la semana origen
            $s = $this->conexion->prepare(
                "SELECT * FROM horario_doctor
                 WHERE id_doctor=:doc AND fecha BETWEEN :fi AND :ff AND activo=1"
            );
            $s->execute([':doc'=>$id_doctor, ':fi'=>$lunes_origen, ':ff'=>$domingo_origen]);
            $horarios_origen = $s->fetchAll(PDO::FETCH_ASSOC);

            if (empty($horarios_origen)) {
                return ['success'=>false, 'mensaje'=>'No hay horarios en la semana origen para replicar'];
            }

            $ins = $this->conexion->prepare(
                "INSERT IGNORE INTO horario_doctor (id_doctor,id_sede,fecha,hora_inicio,hora_fin)
                 VALUES (:doc,:s,:f,:hi,:hf)"
            );

            $copiados = 0;
            $semanas  = 0;

            // Iterar semana por semana en el rango destino
            $current_lunes = new DateTime($fecha_inicio);
            $current_lunes->modify('monday this week');
            $fin_dt = new DateTime($fecha_fin);

            while ($current_lunes <= $fin_dt) {
                $lunes_dest = $current_lunes->format('Y-m-d');

                // Saltar si es la misma semana origen
                if ($lunes_dest === $lunes_origen) {
                    $current_lunes->modify('+7 days');
                    continue;
                }

                foreach ($horarios_origen as $h) {
                    // Calcular día equivalente en la semana destino
                    $dia_origen = new DateTime($h['fecha']);
                    $dia_origen->modify('monday this week');
                    $diff_dias = (new DateTime($h['fecha']))->diff($dia_origen)->days;
                    $nueva_fecha = (clone $current_lunes)->modify("+{$diff_dias} days")->format('Y-m-d');

                    // Solo si la fecha cae dentro del rango
                    if ($nueva_fecha >= $fecha_inicio && $nueva_fecha <= $fecha_fin) {
                        $ins->execute([
                            ':doc' => $id_doctor,
                            ':s'   => $h['id_sede'],
                            ':f'   => $nueva_fecha,
                            ':hi'  => $h['hora_inicio'],
                            ':hf'  => $h['hora_fin'],
                        ]);
                        $copiados++;
                    }
                }
                $semanas++;
                $current_lunes->modify('+7 days');
            }

            return ['success'=>true, 'semanas'=>$semanas, 'copiados'=>$copiados];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success'=>false, 'mensaje'=>'Error al replicar horario'];
        }
    }

}