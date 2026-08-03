<?php
// modelo_citas_doctor.php - VERSIÓN CON REGISTRO AUTOMÁTICO DE TIEMPO
require_once '../CONFIG/conexion.php';

class ModeloCitasDoctor {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    // =====================================================
    // 📋 LISTAR CITAS DEL PACIENTE PARA EL DOCTOR
    // =====================================================
    public function listarCitasPacienteDoctor($idPaciente, $idDoctor) {
        try {
            $sql = "SELECT 
                        c.id_cita,
                        c.id_paciente,
                        c.id_doctor,
                        c.fecha,
                        c.hora,
                        c.hora_inicio,
                        c.hora_fin,
                        c.motivo,
                        c.id_estado_cita,
                        ec.estado,
                        ts.nombre_servicio,
                        ts.id_tipo_servicio,
                        s.direccion_sede as sede,
                        CONCAT(u.nombre, ' ', u.apellidos) as doctor_nombre,
                        -- ✅ Verificar si ya tiene atención registrada
                        a.id_atencion,
                        a.tratamiento,
                        a.precio_unitario,
                        a.total,
                        a.a_cuenta,
                        a.resta,
                        a.tiempo_atencion,
                        ia.id_informe,
                        ia.estado AS estado_informe
                    FROM citas c
                    INNER JOIN estado_cita ec ON c.id_estado_cita = ec.id_estado_cita
                    LEFT JOIN tipo_servicio ts ON c.id_tipo_servicio = ts.id_tipo_servicio
                    LEFT JOIN sedes s ON c.id_sede_atencion = s.id_sede_atencion
                    INNER JOIN usuarios u ON c.id_doctor = u.id_usuario
                    LEFT JOIN atenciones a ON c.id_cita = a.id_cita
                    LEFT JOIN informe_atencion ia ON c.id_cita = ia.id_cita
                    WHERE c.id_paciente = :id_paciente 
                      AND c.id_doctor = :id_doctor
                    ORDER BY c.fecha DESC, c.hora DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':id_paciente' => $idPaciente,
                ':id_doctor' => $idDoctor
            ]);
            
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("✅ Citas encontradas: " . count($resultado));
            
            return $resultado;
            
        } catch (PDOException $e) {
            error_log("❌ Error al listar citas: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // ▶️ INICIAR ATENCIÓN
    // =====================================================
    public function iniciarAtencion($idCita) {
        try {
            error_log("🔵 === INICIANDO ATENCIÓN ===");
            error_log("📋 ID Cita: " . $idCita);
            
            $this->conexion->beginTransaction();

            $sqlCheck = "SELECT id_cita, id_estado_cita, hora_inicio, hora_fin 
                         FROM citas 
                         WHERE id_cita = :id_cita";
            $stmtCheck = $this->conexion->prepare($sqlCheck);
            $stmtCheck->execute([':id_cita' => $idCita]);
            $cita = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                throw new Exception('Cita no encontrada');
            }

            if (!in_array($cita['id_estado_cita'], [1, 2, '1', '2'])) {
                throw new Exception('La cita no está en estado válido para iniciar');
            }

            if ($cita['hora_inicio']) {
                throw new Exception('La atención ya fue iniciada');
            }

            $sql = "UPDATE citas 
                    SET hora_inicio = NOW(),
                        id_estado_cita = 6
                    WHERE id_cita = :id_cita";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([':id_cita' => $idCita]);

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                return true;
            } else {
                throw new Exception('No se pudo actualizar la cita');
            }
            
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al iniciar atención: " . $e->getMessage());
            throw $e;
        }
    }

    // =====================================================
    // ⏹️ TERMINAR ATENCIÓN - CON REGISTRO AUTOMÁTICO
    // =====================================================
    public function terminarAtencion($idCita) {
        try {
            error_log("🔵 === TERMINANDO ATENCIÓN ===");
            
            $this->conexion->beginTransaction();

            // 1. Verificar estado de la cita
            $sqlCheck = "SELECT c.id_cita, c.id_estado_cita, c.id_paciente, c.id_doctor,
                               c.hora_inicio, c.hora_fin,
                               CONCAT(u.nombre, ' ', u.apellidos) as doctor_nombre
                         FROM citas c
                         INNER JOIN usuarios u ON c.id_doctor = u.id_usuario
                         WHERE c.id_cita = :id_cita";
            $stmtCheck = $this->conexion->prepare($sqlCheck);
            $stmtCheck->execute([':id_cita' => $idCita]);
            $cita = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                throw new Exception('Cita no encontrada');
            }

            if (!in_array($cita['id_estado_cita'], [6, '6'])) {
                throw new Exception('La cita no está en atención');
            }

            if (!$cita['hora_inicio']) {
                throw new Exception('La atención no ha sido iniciada');
            }

            if ($cita['hora_fin']) {
                throw new Exception('La atención ya fue terminada');
            }

            // 2. Actualizar cita con hora_fin
            $sql = "UPDATE citas 
                    SET hora_fin = NOW(),
                        id_estado_cita = 4
                    WHERE id_cita = :id_cita";
            
            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([':id_cita' => $idCita]);

            if (!$resultado || $stmt->rowCount() == 0) {
                throw new Exception('No se pudo actualizar la cita');
            }

            // 3. Calcular tiempo de atención en minutos
            $sqlTiempo = "SELECT TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin) as tiempo_minutos
                          FROM citas 
                          WHERE id_cita = :id_cita";
            $stmtTiempo = $this->conexion->prepare($sqlTiempo);
            $stmtTiempo->execute([':id_cita' => $idCita]);
            $tiempoData = $stmtTiempo->fetch(PDO::FETCH_ASSOC);
            $tiempoAtencion = $tiempoData['tiempo_minutos'] ?? 0;

            error_log("⏱️ Tiempo calculado: {$tiempoAtencion} minutos");

            // 4. Obtener id_version_odontograma del paciente
            $sqlVersion = "SELECT id_version 
                           FROM odontograma_versiones 
                           WHERE id_paciente = :id_paciente
                           ORDER BY fecha_creacion DESC 
                           LIMIT 1";
            $stmtVersion = $this->conexion->prepare($sqlVersion);
            $stmtVersion->execute([':id_paciente' => $cita['id_paciente']]);
            $version = $stmtVersion->fetch(PDO::FETCH_ASSOC);
            $idVersionOdontograma = $version['id_version'] ?? null;

            // 5. NO CREAR REGISTRO EN ATENCIONES
            // Solo guardar el tiempo calculado para usarlo después
            // El registro se creará cuando el doctor llene el formulario completo
            
            error_log("✅ Tiempo de atención calculado: {$tiempoAtencion} min (se usará al llenar el informe)");

            $this->conexion->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al terminar atención: " . $e->getMessage());
            throw $e;
        }
    }

    // =====================================================
    // 💾 GUARDAR ATENCIÓN (INSERT O UPDATE)
    // =====================================================
    public function guardarAtencion($datos) {
        try {
            error_log("🔵 === GUARDANDO ATENCIÓN ===");
            error_log("📦 Datos: " . print_r($datos, true));
            
            $this->conexion->beginTransaction();

            // Verificar que la cita existe y está completada
            $sqlCheck = "SELECT c.id_cita, c.id_estado_cita, c.hora_inicio, c.hora_fin, 
                               c.id_paciente, c.id_doctor,
                               CONCAT(u.nombre, ' ', u.apellidos) as doctor_nombre
                         FROM citas c
                         INNER JOIN usuarios u ON c.id_doctor = u.id_usuario
                         WHERE c.id_cita = :id_cita";
            $stmtCheck = $this->conexion->prepare($sqlCheck);
            $stmtCheck->execute([':id_cita' => $datos['id_cita']]);
            $cita = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                throw new Exception('Cita no encontrada');
            }

            if (!in_array($cita['id_estado_cita'], [4, '4'])) {
                throw new Exception('La cita debe estar completada');
            }

            if (!$cita['hora_fin']) {
                throw new Exception('La atención no ha sido terminada');
            }

            // ✅ VERIFICAR SI YA EXISTE UNA ATENCIÓN (Relación 1:1)
            $sqlExiste = "SELECT id_atencion, tiempo_atencion FROM atenciones WHERE id_cita = :id_cita";
            $stmtExiste = $this->conexion->prepare($sqlExiste);
            $stmtExiste->execute([':id_cita' => $datos['id_cita']]);
            $atencionExistente = $stmtExiste->fetch(PDO::FETCH_ASSOC);

            // Obtener id_version_odontograma del paciente
            $sqlVersionOdonto = "SELECT id_version 
                                 FROM odontograma_versiones 
                                 WHERE id_paciente = :id_paciente
                                 ORDER BY fecha_creacion DESC 
                                 LIMIT 1";
            $stmtVersionOdonto = $this->conexion->prepare($sqlVersionOdonto);
            $stmtVersionOdonto->execute([':id_paciente' => $cita['id_paciente']]);
            $version = $stmtVersionOdonto->fetch(PDO::FETCH_ASSOC);
            $idVersionOdontograma = $version['id_version'] ?? null;

            // Si no se proporciona tiempo, calcularlo desde la cita
            $tiempoAtencion = $datos['tiempo_atencion'] ?? 0;
            if ($tiempoAtencion == 0 && $cita['hora_inicio'] && $cita['hora_fin']) {
                $sqlTiempo = "SELECT TIMESTAMPDIFF(MINUTE, :hora_inicio, :hora_fin) as tiempo_minutos";
                $stmtTiempo = $this->conexion->prepare($sqlTiempo);
                $stmtTiempo->execute([
                    ':hora_inicio' => $cita['hora_inicio'],
                    ':hora_fin' => $cita['hora_fin']
                ]);
                $tiempoData = $stmtTiempo->fetch(PDO::FETCH_ASSOC);
                $tiempoAtencion = $tiempoData['tiempo_minutos'] ?? 0;
                error_log("⏱️ Tiempo calculado automáticamente: {$tiempoAtencion} minutos");
            }

            if ($atencionExistente) {
                // ✅ YA EXISTE - HACER UPDATE
                error_log("📝 Atención ya existe, haciendo UPDATE");
                
                $sql = "UPDATE atenciones SET
                        id_version_odontograma = :id_version_odontograma,
                        tiempo_atencion = :tiempo_atencion,
                        tratamiento = :tratamiento,
                        precio_unitario = :precio_unitario,
                        total = :total,
                        a_cuenta = :a_cuenta,
                        resta = :resta,
                        fecha_registro = NOW()
                        WHERE id_cita = :id_cita";
                
                $stmt = $this->conexion->prepare($sql);
                $resultado = $stmt->execute([
                    ':id_cita' => $datos['id_cita'],
                    ':id_version_odontograma' => $idVersionOdontograma,
                    ':tiempo_atencion' => $tiempoAtencion,
                    ':tratamiento' => $datos['tratamiento'],
                    ':precio_unitario' => $datos['precio_unitario'],
                    ':total' => $datos['total'],
                    ':a_cuenta' => $datos['a_cuenta'],
                    ':resta' => $datos['resta']
                ]);

                if ($resultado) {
                    $this->conexion->commit();
                    error_log("✅ Atención actualizada correctamente");
                    return ['success' => true, 'accion' => 'actualizar'];
                }
                
            } else {
                // ✅ NO EXISTE - HACER INSERT
                error_log("📝 Atención no existe, haciendo INSERT");
                
                $sql = "INSERT INTO atenciones 
                        (id_cita, id_version_odontograma, tiempo_atencion, tratamiento, 
                         precio_unitario, total, a_cuenta, resta, fecha_registro)
                        VALUES 
                        (:id_cita, :id_version_odontograma, :tiempo_atencion, :tratamiento, 
                         :precio_unitario, :total, :a_cuenta, :resta, NOW())";
                
                $stmt = $this->conexion->prepare($sql);
                $resultado = $stmt->execute([
                    ':id_cita' => $datos['id_cita'],
                    ':id_version_odontograma' => $idVersionOdontograma,
                    ':tiempo_atencion' => $tiempoAtencion,
                    ':tratamiento' => $datos['tratamiento'],
                    ':precio_unitario' => $datos['precio_unitario'],
                    ':total' => $datos['total'],
                    ':a_cuenta' => $datos['a_cuenta'],
                    ':resta' => $datos['resta']
                ]);

                if ($resultado) {
                    $this->conexion->commit();
                    error_log("✅ Atención guardada correctamente");
                    return ['success' => true, 'accion' => 'insertar'];
                }
            }
            
            throw new Exception('No se pudo guardar la atención');
            
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("❌ Error al guardar atención: " . $e->getMessage());
            throw $e;
        }
    }

    // =====================================================
    // 👁️ VER DETALLE DE CITA
    // =====================================================
    public function verDetalleCita($idCita) {
        try {
            $sql = "SELECT 
                        c.*,
                        ec.estado,
                        ts.nombre_servicio,
                        s.direccion_sede as sede,
                        p.nombre as paciente_nombre,
                        p.apellido as paciente_apellido,
                        p.dni as paciente_dni,
                        CONCAT(u.nombre, ' ', u.apellidos) as doctor_nombre
                    FROM citas c
                    INNER JOIN estado_cita ec ON c.id_estado_cita = ec.id_estado_cita
                    LEFT JOIN tipo_servicio ts ON c.id_tipo_servicio = ts.id_tipo_servicio
                    LEFT JOIN sedes s ON c.id_sede_atencion = s.id_sede_atencion
                    INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
                    INNER JOIN usuarios u ON c.id_doctor = u.id_usuario
                    WHERE c.id_cita = :id_cita";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_cita' => $idCita]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("❌ Error al ver detalle cita: " . $e->getMessage());
            return false;
        }
    }
}
?>