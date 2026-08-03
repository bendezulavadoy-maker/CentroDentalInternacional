<?php
require_once '../CONFIG/conexion.php';

class ModeloCitas {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // 🔹 Registrar nueva cita
    // =====================================================
    // Estados y transiciones de negocio
    private $estadosFinales  = [4];           // 4=Completada — nadie edita ni elimina
    private $estadosCerrados = [3, 4, 5];     // 3=Cancelada 4=Completada 5=No asistió — no editar
    private $transicionesPermitidas = [
        1 => [2, 3],    // Programada  → Confirmada, Cancelada
        2 => [4, 5, 3], // Confirmada  → Completada, No asistió, Cancelada
        3 => [1],       // Cancelada   → Programada (reversión)
        4 => [],        // Completada  → ninguno (estado final absoluto)
        5 => [1],       // No asistió  → Programada (reversión)
    ];
    private $nombresEstado = [1=>'Programada',2=>'Confirmada',3=>'Cancelada',4=>'Completada',5=>'No asistió'];

    // =====================================================
    // 🔹 Validar transición de estado
    // =====================================================
    public function validarTransicionEstado($id_cita, $nuevo_estado) {
        try {
            $stmt = $this->conexion->prepare("SELECT id_estado_cita FROM citas WHERE id_cita = :id");
            $stmt->execute([':id' => $id_cita]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cita) return ['valido' => false, 'mensaje' => 'Cita no encontrada'];

            $actual = intval($cita['id_estado_cita']);
            $nuevo  = intval($nuevo_estado);
            if ($actual === $nuevo) return ['valido' => true];

            $permitidos = $this->transicionesPermitidas[$actual] ?? [];
            if (!in_array($nuevo, $permitidos)) {
                $actualNombre = $this->nombresEstado[$actual] ?? "Estado $actual";
                if (empty($permitidos)) {
                    return ['valido' => false, 'mensaje' => "Una cita $actualNombre es estado final y no puede modificarse"];
                }
                $lista = implode(', ', array_map(fn($e) => $this->nombresEstado[$e] ?? $e, $permitidos));
                return ['valido' => false, 'mensaje' => "Desde $actualNombre solo se puede pasar a: $lista"];
            }
            return ['valido' => true];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['valido' => false, 'mensaje' => 'Error al validar estado'];
        }
    }

    // =====================================================
    // 🔹 Citas vencidas (fecha pasada, aún abiertas)
    // =====================================================
    public function listarCitasVencidas() {
        try {
            $sql = "SELECT c.id_cita, c.fecha, c.hora, c.motivo,
                           CONCAT(p.nombre,' ',p.apellido) AS nombre_paciente,
                           p.dni AS dni_paciente,
                           CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor,
                           ec.estado, s.nombre_sede
                    FROM citas c
                    INNER JOIN pacientes p    ON c.id_paciente      = p.id_paciente
                    INNER JOIN usuarios u     ON c.id_doctor        = u.id_usuario
                    INNER JOIN estado_cita ec ON c.id_estado_cita   = ec.id_estado_cita
                    LEFT  JOIN sedes s        ON c.id_sede_atencion = s.id_sede_atencion
                    WHERE c.fecha < CURDATE()
                      AND c.id_estado_cita IN (1, 2)
                    ORDER BY c.fecha DESC, c.id_cita DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Cerrar cita vencida (solo No asistió o Cancelada)
    // =====================================================
    public function cerrarCitaVencida($id_cita, $nuevo_estado) {
        try {
            if (!in_array(intval($nuevo_estado), [3, 5])) {
                return ['success' => false, 'mensaje' => 'Solo se puede cerrar como Cancelada o No asistio'];
            }
            $stmt = $this->conexion->prepare("SELECT id_estado_cita, fecha FROM citas WHERE id_cita = :id");
            $stmt->execute([':id' => $id_cita]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cita) return ['success' => false, 'mensaje' => 'Cita no encontrada'];
            if (!in_array(intval($cita['id_estado_cita']), [1, 2]))
                return ['success' => false, 'mensaje' => 'La cita ya esta cerrada'];
            if ($cita['fecha'] >= date('Y-m-d'))
                return ['success' => false, 'mensaje' => 'La cita aun no ha vencido'];

            $upd = $this->conexion->prepare("UPDATE citas SET id_estado_cita = :estado WHERE id_cita = :id");
            $upd->execute([':estado' => intval($nuevo_estado), ':id' => $id_cita]);
            return ['success' => true, 'mensaje' => 'Estado actualizado'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'mensaje' => 'Error de base de datos'];
        }
    }

    public function registrarCita($datos) {
        try {
            $camposRequeridos = ['id_paciente', 'id_doctor', 'fecha', 'hora', 'id_estado_cita', 'id_sede_atencion', 'motivo'];
            foreach ($camposRequeridos as $campo) {
                if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
                    return ['success' => false, 'mensaje' => "$campo es obligatorio"];
                }
            }

            // Solo Programada (1) o Confirmada (2) al crear
            if (!in_array(intval($datos['id_estado_cita']), [1, 2])) {
                return ['success' => false, 'mensaje' => 'Al crear una cita el estado solo puede ser Programada o Confirmada'];
            }
            
            $sql = "INSERT INTO citas
                    (id_paciente, id_doctor, fecha, hora,
                     id_estado_cita, id_tipo_servicio, id_tipo_atencion,
                     duracion_minutos, id_sede_atencion, id_paciente_plan, motivo)
                    VALUES (:id_paciente, :id_doctor, :fecha, :hora,
                            :id_estado_cita, :id_tipo_servicio, :id_tipo_atencion,
                            :duracion_minutos, :id_sede_atencion, :id_paciente_plan, :motivo)";
            
            $stmt = $this->conexion->prepare($sql);

            $resultado = $stmt->execute([
                ':id_paciente'      => $datos['id_paciente'],
                ':id_doctor'        => $datos['id_doctor'],
                ':fecha'            => $datos['fecha'],
                ':hora'             => $datos['hora'],
                ':id_estado_cita'   => $datos['id_estado_cita'],
                ':id_tipo_servicio' => !empty($datos['id_tipo_servicio']) ? intval($datos['id_tipo_servicio']) : null,
                ':id_tipo_atencion' => !empty($datos['id_tipo_atencion']) ? intval($datos['id_tipo_atencion']) : null,
                ':duracion_minutos' => intval($datos['duracion_minutos'] ?? 30),
                ':id_sede_atencion' => $datos['id_sede_atencion'],
                ':id_paciente_plan' => !empty($datos['id_paciente_plan']) ? intval($datos['id_paciente_plan']) : null,
                ':motivo'           => $datos['motivo']
            ]);
            
            if ($resultado) {
                $idNuevo = $this->conexion->lastInsertId();
                error_log("✅ Cita registrada ID: $idNuevo");
                
                if (isset($datos['alergias_medicamentos']) && !empty($datos['alergias_medicamentos'])) {
                    $alergias_filtradas = array_filter($datos['alergias_medicamentos'], function($val) {
                        return !empty(trim($val));
                    });
                    if (!empty($alergias_filtradas)) {
                        $this->guardarAlergiasPaciente($datos['id_paciente'], $alergias_filtradas);
                    }
                }
                
                // Log de auditoría
                $this->registrarLogCita($idNuevo, 'CREAR', null, $datos);
                
                return [
                    'success' => true,
                    'id_cita' => $idNuevo
                ];
            }
            
            error_log("❌ No se pudo insertar");
            return ['success' => false, 'mensaje' => 'Error al insertar en la base de datos'];

        } catch (PDOException $e) {
            error_log("❌ Error: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error de base de datos'];
        }
    }

    // =====================================================
    // 🔹 Editar cita — CORREGIDO: guarda todos los campos
    // =====================================================
    public function editarCita($id, $datos) {
        try {
            // Obtener estado actual
            $citaAnterior = $this->verCita($id);
            if (!$citaAnterior) return ['success' => false, 'mensaje' => 'Cita no encontrada'];

            $estadoActual = intval($citaAnterior['id_estado_cita']);

            // Bloquear edicion de citas completadas (estado final absoluto)
            if (in_array($estadoActual, $this->estadosFinales)) {
                $nombre = $this->nombresEstado[$estadoActual] ?? "Estado $estadoActual";
                return ['success' => false, 'mensaje' => "No se puede editar una cita $nombre"];
            }

            // Si cambio de estado, validar transicion
            $nuevoEstado = intval($datos['id_estado_cita'] ?? $estadoActual);
            if ($nuevoEstado !== $estadoActual) {
                $validacion = $this->validarTransicionEstado($id, $nuevoEstado);
                if (!$validacion['valido']) {
                    return ['success' => false, 'mensaje' => $validacion['mensaje']];
                }
            }

            // Si la cita esta cancelada o no asistio, solo permitir cambio de estado (reversion)
            // no cambiar otros datos
            if (in_array($estadoActual, [3, 5]) && $nuevoEstado !== $estadoActual) {
                $upd = $this->conexion->prepare("UPDATE citas SET id_estado_cita = :estado WHERE id_cita = :id");
                $upd->execute([':estado' => $nuevoEstado, ':id' => $id]);
                $this->registrarLogCita($id, 'REVERTIR_ESTADO', $citaAnterior, $datos);
                return ['success' => true, 'reversion' => true];
            }
            
            $sql = "UPDATE citas 
                    SET id_paciente      = :id_paciente,
                        id_doctor        = :id_doctor,
                        fecha            = :fecha,
                        hora             = :hora,
                        id_estado_cita   = :id_estado_cita,
                        id_tipo_servicio = :id_tipo_servicio,
                        id_tipo_atencion = :id_tipo_atencion,
                        duracion_minutos = :duracion_minutos,
                        id_sede_atencion = :id_sede_atencion,
                        id_paciente_plan = :id_paciente_plan,
                        motivo           = :motivo
                    WHERE id_cita = :id";
            
            $stmt = $this->conexion->prepare($sql);
            
            $resultado = $stmt->execute([
                ':id_paciente'      => $datos['id_paciente'],
                ':id_doctor'        => $datos['id_doctor'],
                ':fecha'            => $datos['fecha'],
                ':hora'             => $datos['hora'],
                ':id_estado_cita'   => $datos['id_estado_cita'],
                ':id_tipo_servicio' => !empty($datos['id_tipo_servicio']) ? intval($datos['id_tipo_servicio']) : null,
                ':id_tipo_atencion' => !empty($datos['id_tipo_atencion']) ? intval($datos['id_tipo_atencion']) : null,
                ':duracion_minutos' => intval($datos['duracion_minutos'] ?? 30),
                ':id_sede_atencion' => $datos['id_sede_atencion'],
                ':id_paciente_plan' => !empty($datos['id_paciente_plan']) ? intval($datos['id_paciente_plan']) : null,
                ':motivo'           => $datos['motivo'],
                ':id'               => $id
            ]);
            
            if ($resultado) {
                error_log("✅ Cita actualizada ID: $id");
                
                if (isset($datos['alergias_medicamentos'])) {
                    $alergias_filtradas = array_filter($datos['alergias_medicamentos'], function($val) {
                        return !empty(trim($val));
                    });
                    if (!empty($alergias_filtradas)) {
                        $this->guardarAlergiasPaciente($datos['id_paciente'], $alergias_filtradas);
                    }
                }

                // Log de auditoría
                $this->registrarLogCita($id, 'EDITAR', $citaAnterior, $datos);
            }
            
            return $resultado;
            
        } catch (PDOException $e) {
            error_log("❌ Error: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Eliminar cita
    // =====================================================
    public function eliminarCita($id) {
        try {
            // Verificar estado actual
            $stmtEst = $this->conexion->prepare("SELECT id_estado_cita FROM citas WHERE id_cita = :id");
            $stmtEst->execute([':id' => $id]);
            $cita = $stmtEst->fetch(PDO::FETCH_ASSOC);
            if (!$cita) return ['success' => false, 'mensaje' => 'Cita no encontrada'];

            // Completada no se puede eliminar por nadie
            if (in_array(intval($cita['id_estado_cita']), $this->estadosFinales)) {
                $nombre = $this->nombresEstado[$cita['id_estado_cita']] ?? '';
                return ['success' => false, 'mensaje' => "Una cita $nombre no puede eliminarse, solo visualizarse"];
            }

            // Verificar atenciones registradas
            $sqlCheck = "SELECT COUNT(*) FROM atenciones WHERE id_cita = :id";
            $stmtCheck = $this->conexion->prepare($sqlCheck);
            $stmtCheck->execute([':id' => $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                return ['success' => false, 'mensaje' => 'No se puede eliminar: la cita tiene atenciones registradas'];
            }

            $sql = "DELETE FROM citas WHERE id_cita = :id";
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([':id' => $id]);
            return $resultado 
                ? ['success' => true, 'mensaje' => 'Cita eliminada'] 
                : ['success' => false, 'mensaje' => 'Error al eliminar'];
        } catch (PDOException $e) {
            error_log("❌ Error: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error de base de datos'];
        }
    }

    // =====================================================
    // 🔹 Listar citas
    // =====================================================
    public function listarCitas() {
        try {
            $sql = "SELECT 
                        c.id_cita,
                        c.fecha,
                        c.hora,
                        c.id_paciente,
                        c.id_doctor,
                        c.id_estado_cita,
                        c.id_tipo_servicio,
                        c.id_tipo_atencion,
                        c.duracion_minutos,
                        c.id_sede_atencion,
                        c.id_paciente_plan,
                        c.motivo,
                        p.nombre AS paciente_nombre,
                        p.apellido AS paciente_apellido,
                        p.dni AS paciente_dni,
                        u.nombre AS doctor_nombre,
                        u.apellidos AS doctor_apellidos,
                        ec.estado,
                        ts.nombre_servicio,
                        ta.nombre AS tipo_atencion,
                        s.nombre_sede AS sede
                    FROM citas c
                    INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
                    INNER JOIN usuarios u  ON c.id_doctor = u.id_usuario
                    LEFT JOIN estado_cita ec      ON c.id_estado_cita = ec.id_estado_cita
                    LEFT JOIN tipo_servicio ts     ON c.id_tipo_servicio = ts.id_tipo_servicio
                    LEFT JOIN tipos_atencion ta    ON c.id_tipo_atencion = ta.id_tipo_atencion
                    LEFT JOIN sedes s              ON c.id_sede_atencion = s.id_sede_atencion
                    ORDER BY c.fecha DESC, c.id_cita DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔹 Ver cita por ID
    // =====================================================
    public function verCita($id) {
        try {
            $sql = "SELECT 
                        c.*,
                        p.nombre AS paciente_nombre,
                        p.apellido AS paciente_apellido,
                        p.dni AS paciente_dni,
                        p.telefono AS telefono_paciente,
                        u.nombre AS doctor_nombre,
                        u.apellidos AS doctor_apellidos,
                        ec.estado,
                        ts.nombre_servicio,
                        ta.nombre AS tipo_atencion,
                        ta.color AS tipo_atencion_color,
                        s.nombre_sede AS sede,
                        s.direccion_sede,
                        pp.id_paciente_plan,
                        pt.nombre_plan AS nombre_plan
                    FROM citas c
                    INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
                    INNER JOIN usuarios u ON c.id_doctor = u.id_usuario
                    LEFT JOIN estado_cita ec ON c.id_estado_cita = ec.id_estado_cita
                    LEFT JOIN tipo_servicio ts ON c.id_tipo_servicio = ts.id_tipo_servicio
                    LEFT JOIN tipos_atencion ta ON c.id_tipo_atencion = ta.id_tipo_atencion
                    LEFT JOIN sedes s ON c.id_sede_atencion = s.id_sede_atencion
                    LEFT JOIN paciente_planes pp ON c.id_paciente_plan = pp.id_paciente_plan
                    LEFT JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                    WHERE c.id_cita = :id";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            $cita = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cita) {
                $cita['alergias_medicamentos'] = $this->obtenerAlergiasPaciente($cita['id_paciente']);
            }
            
            return $cita;
        } catch (PDOException $e) {
            error_log("❌ Error: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Validar disponibilidad de slot (doble validación servidor)
    // =====================================================
    public function validarSlotDisponible($id_doctor, $id_sede, $fecha, $hora, $duracion_minutos, $excluir_cita = 0) {
        try {
            $ts_inicio = strtotime("$fecha $hora");
            $ts_fin    = $ts_inicio + ($duracion_minutos * 60);

            // Validar que el slot no sea en el pasado (margen 30 min)
            if ($fecha === date('Y-m-d') && $ts_inicio < time() + (30 * 60)) {
                return ['valido' => false, 'mensaje' => 'No se puede agendar en un horario que ya pasó o es muy próximo'];
            }

            // 1. Verificar horario del doctor ese día
            $sHor = $this->conexion->prepare(
                "SELECT hora_inicio, hora_fin FROM horario_doctor
                 WHERE id_doctor=:doc AND id_sede=:sede AND fecha=:f AND activo=1
                 LIMIT 1"
            );
            $sHor->execute([':doc'=>$id_doctor, ':sede'=>$id_sede, ':f'=>$fecha]);
            $horario = $sHor->fetch(PDO::FETCH_ASSOC);
            if (!$horario) {
                return ['valido' => false, 'mensaje' => 'El doctor no tiene horario configurado para ese día en esa sede'];
            }
            $h_ini_ts = strtotime("$fecha " . $horario['hora_inicio']);
            $h_fin_ts = strtotime("$fecha " . $horario['hora_fin']);
            if ($ts_inicio < $h_ini_ts || $ts_fin > $h_fin_ts) {
                return ['valido' => false, 'mensaje' => 'La hora está fuera del horario del doctor'];
            }

            // 2. Verificar bloqueos_doctor (bloqueos puntuales por fecha)
            $sBloq = $this->conexion->prepare(
                "SELECT hora_inicio, hora_fin FROM bloqueos_doctor
                 WHERE id_doctor=:doc AND fecha=:f"
            );
            $sBloq->execute([':doc'=>$id_doctor, ':f'=>$fecha]);
            foreach ($sBloq->fetchAll(PDO::FETCH_ASSOC) as $b) {
                if ($b['hora_inicio'] === null) {
                    return ['valido' => false, 'mensaje' => 'El doctor tiene bloqueado ese día completo'];
                }
                $b_ini = strtotime("$fecha " . $b['hora_inicio']);
                $b_fin = strtotime("$fecha " . $b['hora_fin']);
                if ($ts_inicio < $b_fin && $ts_fin > $b_ini) {
                    return ['valido' => false, 'mensaje' => 'El horario coincide con un bloqueo del doctor'];
                }
            }

            // 3. Verificar bloqueos_agenda (rangos de bloqueo por vacaciones/feriados)
            $sAgenda = $this->conexion->prepare(
                "SELECT hora_inicio, hora_fin FROM bloqueos_agenda
                 WHERE id_doctor=:doc 
                   AND :fecha BETWEEN fecha_inicio AND fecha_fin
                   AND (id_sede_atencion IS NULL OR id_sede_atencion=:sede)"
            );
            $sAgenda->execute([':doc'=>$id_doctor, ':fecha'=>$fecha, ':sede'=>$id_sede]);
            foreach ($sAgenda->fetchAll(PDO::FETCH_ASSOC) as $ba) {
                if ($ba['hora_inicio'] === null) {
                    return ['valido' => false, 'mensaje' => 'El doctor tiene bloqueada esa fecha (vacaciones/feriado)'];
                }
                $ba_ini = strtotime("$fecha " . $ba['hora_inicio']);
                $ba_fin = strtotime("$fecha " . $ba['hora_fin']);
                if ($ts_inicio < $ba_fin && $ts_fin > $ba_ini) {
                    return ['valido' => false, 'mensaje' => 'El horario coincide con un bloqueo de agenda'];
                }
            }

            // 4. Verificar citas existentes ese día/doctor (evitar solapamiento)
            $sCitas = $this->conexion->prepare(
                "SELECT hora, duracion_minutos FROM citas
                 WHERE id_doctor=:doc AND fecha=:f
                   AND id_cita != :excl
                   AND id_estado_cita NOT IN (3,4,5)
                 ORDER BY hora ASC"
            );
            $sCitas->execute([':doc'=>$id_doctor, ':f'=>$fecha, ':excl'=>$excluir_cita]);
            foreach ($sCitas->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $c_ini = strtotime("$fecha " . $c['hora']);
                $c_fin = $c_ini + (intval($c['duracion_minutos']) * 60);
                if ($ts_inicio < $c_fin && $ts_fin > $c_ini) {
                    return ['valido' => false, 'mensaje' => 'Ese horario ya está ocupado por otra cita'];
                }
            }

            return ['valido' => true];

        } catch (PDOException $e) {
            error_log("❌ validarSlot: " . $e->getMessage());
            return ['valido' => false, 'mensaje' => 'Error al validar disponibilidad'];
        }
    }

    // =====================================================
    // 💊 GUARDAR ALERGIAS
    // =====================================================
    private function guardarAlergiasPaciente($id_paciente, $alergias) {
        try {
            if (is_string($alergias)) {
                $alergias = explode(',', $alergias);
            }
            $alergias = array_filter($alergias, function($val) {
                return !empty(trim($val));
            });
            if (empty($alergias)) return true;
            
            $sqlInsert = "INSERT IGNORE INTO paciente_alergias (id_paciente, id_alergia_medicamentos) 
                          VALUES (:id_paciente, :id_alergia)";
            $stmt = $this->conexion->prepare($sqlInsert);
            foreach ($alergias as $id_alergia) {
                $id_alergia = trim($id_alergia);
                if (empty($id_alergia) || !is_numeric($id_alergia)) continue;
                $stmt->execute([':id_paciente' => $id_paciente, ':id_alergia' => $id_alergia]);
            }
            return true;
        } catch (PDOException $e) {
            error_log("❌ Error: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 💊 OBTENER ALERGIAS
    // =====================================================
    public function obtenerAlergiasPaciente($id_paciente) {
        try {
            $sql = "SELECT 
                        pa.id_paciente_alergia,
                        pa.id_alergia_medicamentos,
                        am.medicamento
                    FROM paciente_alergias pa
                    INNER JOIN alergias_medicamentos am 
                        ON pa.id_alergia_medicamentos = am.id_alergia_medicamentos
                    WHERE pa.id_paciente = :id_paciente
                    ORDER BY am.medicamento ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_paciente' => $id_paciente]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // 🔍 BÚSQUEDAS Y CATÁLOGOS
    // =====================================================
    public function buscarPacientes($termino) {
        try {
            $sql = "SELECT id_paciente, nombre, apellido, dni
                    FROM pacientes
                    WHERE nombre LIKE :termino 
                       OR apellido LIKE :termino 
                       OR dni LIKE :termino
                    ORDER BY nombre, apellido
                    LIMIT 10";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':termino' => "%$termino%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function buscarDoctores($termino) {
        try {
            $sql = "SELECT id_usuario, nombre, apellidos, dni
                    FROM usuarios
                    WHERE id_rol = 2
                      AND (nombre LIKE :termino 
                           OR apellidos LIKE :termino 
                           OR dni LIKE :termino)
                    ORDER BY nombre, apellidos
                    LIMIT 10";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':termino' => "%$termino%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function listarEstadosCita() {
        try {
            $sql = "SELECT id_estado_cita, estado FROM estado_cita ORDER BY id_estado_cita ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function listarTiposServicio() {
        try {
            $sql = "SELECT id_tipo_servicio, nombre_servicio FROM tipo_servicio WHERE activo=1 ORDER BY nombre_servicio ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function listarSedes() {
        try {
            $sql = "SELECT id_sede_atencion, nombre_sede, direccion_sede FROM sedes WHERE activo=1 ORDER BY nombre_sede ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function listarAlergiasMedicamentos() {
        try {
            $sql = "SELECT id_alergia_medicamentos, medicamento FROM alergias_medicamentos ORDER BY medicamento ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // =====================================================
    // 📋 LOG DE AUDITORÍA (no crítico, falla silenciosamente)
    // =====================================================
    private function registrarLogCita($id_cita, $accion, $datos_anteriores, $datos_nuevos) {
        try {
            $tablaExiste = $this->conexion->query("SHOW TABLES LIKE 'log_citas'")->rowCount() > 0;
            if (!$tablaExiste) return;
            $sql = "INSERT INTO log_citas (id_cita, accion, datos_anteriores, datos_nuevos, fecha_log)
                    VALUES (:id, :accion, :ant, :nue, NOW())";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':id'     => $id_cita,
                ':accion' => $accion,
                ':ant'    => $datos_anteriores ? json_encode($datos_anteriores) : null,
                ':nue'    => json_encode($datos_nuevos)
            ]);
        } catch (Exception $e) {
            // Log silencioso — no interrumpe el flujo
        }
    }
}
?>