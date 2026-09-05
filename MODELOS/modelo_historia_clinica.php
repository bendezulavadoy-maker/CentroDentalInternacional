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

    // =====================================================
    // 🔹 Actualizar motivo de consulta
    // =====================================================
    public function actualizarMotivoConsulta($idHistoria, $motivo) {
        try {
            $sql = "UPDATE historia_clinica SET motivo_consulta = :motivo WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':motivo' => $motivo,
                ':id_historia' => $idHistoria
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al actualizar motivo de consulta: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 Obtener TODAS las secciones de historia clínica de una vez
    //    (usado para pintar el checklist y para armar el PDF)
    // =====================================================
    public function obtenerSeccionesHistoria($idHistoria) {
        try {
            $secciones = [
                'antecedentes'      => $this->obtenerAntecedentes($idHistoria),
                'antecedentes_personales' => $this->obtenerAntecedentesPersonales($idHistoria),
                'antecedentes_familiares' => $this->obtenerAntecedentesFamiliares($idHistoria),
                'examen_general'    => $this->obtenerExamenGeneral($idHistoria),
                'examen_extraoral'  => $this->obtenerExamenExtraoral($idHistoria),
                'examen_intraoral'  => $this->obtenerExamenIntraoral($idHistoria),
            ];

            // Bandera simple por sección: true si tiene al menos un dato cargado
            foreach ($secciones as $clave => $datos) {
                $secciones[$clave]['_completo'] = $this->seccionTieneDatos($datos);
                $secciones[$clave]['modificado_por_nombre'] = $this->obtenerNombreUsuario($datos['modificado_por'] ?? null);
            }
            return $secciones;
        } catch (PDOException $e) {
            error_log("❌ Error al obtener secciones de historia: " . $e->getMessage());
            return [];
        }
    }

    // Helper: determina si una sección tiene al menos un campo con valor
    private function seccionTieneDatos($datos) {
        if (!$datos) return false;
        foreach ($datos as $campo => $valor) {
            if (in_array($campo, ['id_historia', 'fecha_actualizacion', 'modificado_por'])) continue;
            if ($valor !== null && $valor !== '') return true;
        }
        return false;
    }

    // =====================================================
    // 🔹 ANTECEDENTES — obtener / guardar
    // =====================================================
    public function obtenerAntecedentes($idHistoria) {
        try {
            $sql = "SELECT * FROM historia_antecedentes WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_historia' => $idHistoria]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("❌ Error al obtener antecedentes: " . $e->getMessage());
            return [];
        }
    }

    public function guardarAntecedentes($idHistoria, $medica, $odontologicos, $familiares, $idUsuario) {
        try {
            $sql = "INSERT INTO historia_antecedentes (id_historia, medica, odontologicos, familiares, modificado_por)
                    VALUES (:id_historia, :medica, :odontologicos, :familiares, :modificado_por)
                    ON DUPLICATE KEY UPDATE
                        medica = VALUES(medica),
                        odontologicos = VALUES(odontologicos),
                        familiares = VALUES(familiares),
                        modificado_por = VALUES(modificado_por)";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':id_historia'    => $idHistoria,
                ':medica'         => $medica,
                ':odontologicos'  => $odontologicos,
                ':familiares'     => $familiares,
                ':modificado_por' => $idUsuario
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al guardar antecedentes: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 EXAMEN CLINICO GENERAL — obtener / guardar
    // =====================================================
    public function obtenerExamenGeneral($idHistoria) {
        try {
            $sql = "SELECT * FROM historia_examen_general WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_historia' => $idHistoria]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("❌ Error al obtener examen general: " . $e->getMessage());
            return [];
        }
    }

    public function guardarExamenGeneral($idHistoria, $tallaMts, $pesoKg, $temperatura, $saturacion, $idUsuario) {
        try {
            $sql = "INSERT INTO historia_examen_general (id_historia, talla_mts, peso_kg, temperatura, saturacion, modificado_por)
                    VALUES (:id_historia, :talla_mts, :peso_kg, :temperatura, :saturacion, :modificado_por)
                    ON DUPLICATE KEY UPDATE
                        talla_mts = VALUES(talla_mts),
                        peso_kg = VALUES(peso_kg),
                        temperatura = VALUES(temperatura),
                        saturacion = VALUES(saturacion),
                        modificado_por = VALUES(modificado_por)";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':id_historia' => $idHistoria,
                ':talla_mts'   => $tallaMts !== '' ? $tallaMts : null,
                ':peso_kg'     => $pesoKg !== '' ? $pesoKg : null,
                ':temperatura' => $temperatura !== '' ? $temperatura : null,
                ':saturacion'  => $saturacion !== '' ? $saturacion : null,
                ':modificado_por' => $idUsuario
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al guardar examen general: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 ANTECEDENTES PERSONALES — obtener / guardar
    // =====================================================
    public function obtenerAntecedentesPersonales($idHistoria) {
        try {
            $sql = "SELECT * FROM historia_antecedentes_personales WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_historia' => $idHistoria]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("❌ Error al obtener antecedentes personales: " . $e->getMessage());
            return [];
        }
    }

    public function guardarAntecedentesPersonales($idHistoria, $datos, $idUsuario) {
        try {
            $sql = "INSERT INTO historia_antecedentes_personales
                        (id_historia, fuma, fuma_cantidad, fuma_frecuencia,
                         alcohol, alcohol_cantidad, alcohol_frecuencia,
                         sustancias_psicoactivas, sustancias_especifique, sustancias_frecuencia, sustancias_ultimo_consumo,
                         medicamentos_estimulantes, medicamentos_especifique, medicamentos_frecuencia, medicamentos_ultimo_consumo,
                         bruxismo, respiracion_bucal, embarazo, lactancia, trastornos_coagulacion,
                         hospitalizaciones_previas, cirugias, medicamentos_actuales, diagnostico, modificado_por)
                    VALUES
                        (:id_historia, :fuma, :fuma_cantidad, :fuma_frecuencia,
                         :alcohol, :alcohol_cantidad, :alcohol_frecuencia,
                         :sustancias_psicoactivas, :sustancias_especifique, :sustancias_frecuencia, :sustancias_ultimo_consumo,
                         :medicamentos_estimulantes, :medicamentos_especifique, :medicamentos_frecuencia, :medicamentos_ultimo_consumo,
                         :bruxismo, :respiracion_bucal, :embarazo, :lactancia, :trastornos_coagulacion,
                         :hospitalizaciones_previas, :cirugias, :medicamentos_actuales, :diagnostico, :modificado_por)
                    ON DUPLICATE KEY UPDATE
                        fuma = VALUES(fuma), fuma_cantidad = VALUES(fuma_cantidad), fuma_frecuencia = VALUES(fuma_frecuencia),
                        alcohol = VALUES(alcohol), alcohol_cantidad = VALUES(alcohol_cantidad), alcohol_frecuencia = VALUES(alcohol_frecuencia),
                        sustancias_psicoactivas = VALUES(sustancias_psicoactivas), sustancias_especifique = VALUES(sustancias_especifique),
                        sustancias_frecuencia = VALUES(sustancias_frecuencia), sustancias_ultimo_consumo = VALUES(sustancias_ultimo_consumo),
                        medicamentos_estimulantes = VALUES(medicamentos_estimulantes), medicamentos_especifique = VALUES(medicamentos_especifique),
                        medicamentos_frecuencia = VALUES(medicamentos_frecuencia), medicamentos_ultimo_consumo = VALUES(medicamentos_ultimo_consumo),
                        bruxismo = VALUES(bruxismo), respiracion_bucal = VALUES(respiracion_bucal),
                        embarazo = VALUES(embarazo), lactancia = VALUES(lactancia),
                        trastornos_coagulacion = VALUES(trastornos_coagulacion),
                        hospitalizaciones_previas = VALUES(hospitalizaciones_previas), cirugias = VALUES(cirugias),
                        medicamentos_actuales = VALUES(medicamentos_actuales), diagnostico = VALUES(diagnostico),
                        modificado_por = VALUES(modificado_por)";
            $stmt = $this->conexion->prepare($sql);
            $campos = ['fuma','fuma_cantidad','fuma_frecuencia','alcohol','alcohol_cantidad','alcohol_frecuencia',
                'sustancias_psicoactivas','sustancias_especifique','sustancias_frecuencia','sustancias_ultimo_consumo',
                'medicamentos_estimulantes','medicamentos_especifique','medicamentos_frecuencia','medicamentos_ultimo_consumo',
                'bruxismo','respiracion_bucal','embarazo','lactancia','trastornos_coagulacion',
                'hospitalizaciones_previas','cirugias','medicamentos_actuales','diagnostico'];
            $params = [':id_historia' => $idHistoria];
            foreach ($campos as $c) $params[':' . $c] = $datos[$c] ?: null;
            $params[':modificado_por'] = $idUsuario;
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("❌ Error al guardar antecedentes personales: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 ANTECEDENTES FAMILIARES — obtener / guardar
    // =====================================================
    public function obtenerAntecedentesFamiliares($idHistoria) {
        try {
            $sql = "SELECT * FROM historia_antecedentes_familiares WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_historia' => $idHistoria]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("❌ Error al obtener antecedentes familiares: " . $e->getMessage());
            return [];
        }
    }

    public function guardarAntecedentesFamiliares($idHistoria, $datos, $idUsuario) {
        try {
            $camposBool = ['hipertension_arterial','diabetes','enfermedad_cardiaca','asma','epilepsia',
                'hepatitis','vih','tuberculosis','enfermedad_renal','enfermedad_hepatica'];
            $sql = "INSERT INTO historia_antecedentes_familiares
                        (id_historia, " . implode(', ', $camposBool) . ", otros, modificado_por)
                    VALUES
                        (:id_historia, :" . implode(', :', $camposBool) . ", :otros, :modificado_por)
                    ON DUPLICATE KEY UPDATE " . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $camposBool)) . ", otros = VALUES(otros), modificado_por = VALUES(modificado_por)";
            $stmt = $this->conexion->prepare($sql);
            $params = [':id_historia' => $idHistoria, ':otros' => $datos['otros'] ?: null, ':modificado_por' => $idUsuario];
            foreach ($camposBool as $c) $params[':' . $c] = !empty($datos[$c]) ? 1 : 0;
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("❌ Error al guardar antecedentes familiares: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 EXAMEN EXTRAORAL — obtener / guardar
    // =====================================================
    public function obtenerExamenExtraoral($idHistoria) {
        try {
            $sql = "SELECT * FROM historia_examen_extraoral WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_historia' => $idHistoria]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("❌ Error al obtener examen extraoral: " . $e->getMessage());
            return [];
        }
    }

    public function guardarExamenExtraoral($idHistoria, $datos, $idUsuario) {
        try {
            // $datos es un arreglo asociativo con las claves del formulario
            $sql = "INSERT INTO historia_examen_extraoral
                        (id_historia, simetria, musculatura, perfil_antero_posterior,
                         perfil_vertical, fonacion, deglucion, deglucion_tipo,
                         respiracion, habitos, modificado_por)
                    VALUES
                        (:id_historia, :simetria, :musculatura, :perfil_antero_posterior,
                         :perfil_vertical, :fonacion, :deglucion, :deglucion_tipo,
                         :respiracion, :habitos, :modificado_por)
                    ON DUPLICATE KEY UPDATE
                        simetria = VALUES(simetria),
                        musculatura = VALUES(musculatura),
                        perfil_antero_posterior = VALUES(perfil_antero_posterior),
                        perfil_vertical = VALUES(perfil_vertical),
                        fonacion = VALUES(fonacion),
                        deglucion = VALUES(deglucion),
                        deglucion_tipo = VALUES(deglucion_tipo),
                        respiracion = VALUES(respiracion),
                        habitos = VALUES(habitos),
                        modificado_por = VALUES(modificado_por)";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':id_historia'              => $idHistoria,
                ':simetria'                 => $datos['simetria'] ?: null,
                ':musculatura'              => $datos['musculatura'] ?: null,
                ':perfil_antero_posterior'  => $datos['perfil_antero_posterior'] ?: null,
                ':perfil_vertical'          => $datos['perfil_vertical'] ?: null,
                ':fonacion'                 => $datos['fonacion'] ?: null,
                ':deglucion'                => $datos['deglucion'] ?: null,
                ':deglucion_tipo'           => $datos['deglucion_tipo'] ?: null,
                ':respiracion'              => $datos['respiracion'] ?: null,
                ':habitos'                  => $datos['habitos'] ?: null,
                ':modificado_por'           => $idUsuario
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al guardar examen extraoral: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // 🔹 EXAMEN INTRAORAL (tejidos blandos) — obtener / guardar
    // =====================================================
    public function obtenerExamenIntraoral($idHistoria) {
        try {
            $sql = "SELECT * FROM historia_examen_intraoral WHERE id_historia = :id_historia";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':id_historia' => $idHistoria]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("❌ Error al obtener examen intraoral: " . $e->getMessage());
            return [];
        }
    }
    private function obtenerNombreUsuario($idUsuario) {
        if (!$idUsuario) return null;
        $stmt = $this->conexion->prepare("SELECT nombre, apellidos FROM usuarios WHERE id_usuario = :id");
        $stmt->execute([':id' => $idUsuario]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $u ? trim($u['nombre'] . ' ' . $u['apellidos']) : null;
    }

    public function guardarExamenIntraoral($idHistoria, $datos, $idUsuario) {
        try {
            $sql = "INSERT INTO historia_examen_intraoral
                        (id_historia, labios, vestibulo, frenillos, paladar, orofaringe, lengua, piso_boca, modificado_por)
                    VALUES
                        (:id_historia, :labios, :vestibulo, :frenillos, :paladar, :orofaringe, :lengua, :piso_boca, :modificado_por)
                    ON DUPLICATE KEY UPDATE
                        labios = VALUES(labios),
                        vestibulo = VALUES(vestibulo),
                        frenillos = VALUES(frenillos),
                        paladar = VALUES(paladar),
                        orofaringe = VALUES(orofaringe),
                        lengua = VALUES(lengua),
                        piso_boca = VALUES(piso_boca),
                        modificado_por = VALUES(modificado_por)";
            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([
                ':id_historia' => $idHistoria,
                ':labios'      => $datos['labios'] ?: null,
                ':vestibulo'   => $datos['vestibulo'] ?: null,
                ':frenillos'   => $datos['frenillos'] ?: null,
                ':paladar'     => $datos['paladar'] ?: null,
                ':orofaringe'  => $datos['orofaringe'] ?: null,
                ':lengua'      => $datos['lengua'] ?: null,
                ':piso_boca'   => $datos['piso_boca'] ?: null,
                ':modificado_por' => $idUsuario
            ]);
        } catch (PDOException $e) {
            error_log("❌ Error al guardar examen intraoral: " . $e->getMessage());
            return false;
        }
    }
}
?>