<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloInforme {

    private $con;

    public function __construct() {
        $this->con = (new Conexion())->getConexion();
    }

    // ── Obtener datos completos de una cita para el informe ───────
    public function obtenerDatosCita($id_cita) {
        try {
            $s = $this->con->prepare(
                "SELECT
                    c.id_cita, c.fecha, c.hora, c.hora_inicio, c.hora_fin,
                    c.id_paciente, c.id_doctor,
                    ec.estado, ec.id_estado_cita,
                    ts.nombre_servicio,
                    s.nombre_sede,
                    CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor,
                    CONCAT(p.nombre,' ',p.apellido) AS nombre_paciente,
                    ia.id_informe, ia.estado AS estado_informe,
                    ia.motivo_consulta, ia.diagnostico,
                    ia.tratamiento_realizado, ia.materiales, ia.observaciones,
                    ia.id_plan, ia.id_paciente_plan, ia.fecha_envio,
                    pt.nombre_plan,
                    pp.tipo AS plan_tipo,
                    pp.costo_acordado AS plan_costo_acordado,
                    pp.cuota_inicial AS plan_cuota_inicial,
                    pp.en_cuotas AS plan_en_cuotas,
                    pp.sesiones_pago_est AS plan_sesiones_est,
                    pp.costo_estimado_sesion AS plan_costo_sesion,
                    pp.notas AS plan_notas,
                    pp.creado_en_cita AS plan_creado_en_cita,
                    TIMESTAMPDIFF(MINUTE, c.hora_inicio, c.hora_fin) AS tiempo_atencion
                FROM citas c
                INNER JOIN estado_cita ec     ON c.id_estado_cita = ec.id_estado_cita
                LEFT  JOIN tipo_servicio ts    ON c.id_tipo_servicio = ts.id_tipo_servicio
                LEFT  JOIN sedes s             ON c.id_sede_atencion = s.id_sede_atencion
                INNER JOIN usuarios u          ON c.id_doctor = u.id_usuario
                INNER JOIN pacientes p         ON c.id_paciente = p.id_paciente
                LEFT  JOIN informe_atencion ia ON c.id_cita = ia.id_cita
                LEFT  JOIN planes_tratamiento pt ON ia.id_plan = pt.id_plan
                LEFT  JOIN paciente_planes pp  ON ia.id_paciente_plan = pp.id_paciente_plan
                WHERE c.id_cita = :id"
            );
            $s->execute([':id' => $id_cita]);
            return $s->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("obtenerDatosCita: " . $e->getMessage());
            return null;
        }
    }

    // ── Guardar informe ───────────────────────────────────────────
    public function guardarInforme($datos, $id_doctor) {
        try {
            $this->con->beginTransaction();

            $id_cita = intval($datos['id_cita']);
            $estado  = $datos['estado'] ?? 'borrador';

            // Verificar que la cita pertenece al doctor
            $chk = $this->con->prepare(
                "SELECT id_cita, id_paciente FROM citas 
                 WHERE id_cita = :id AND id_doctor = :doc"
            );
            $chk->execute([':id' => $id_cita, ':doc' => $id_doctor]);
            $cita = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$cita) {
                $this->con->rollBack();
                return ['success' => false, 'mensaje' => 'No tienes permiso sobre esta cita'];
            }

            $id_paciente      = $cita['id_paciente'];
            $id_plan          = !empty($datos['id_plan']) ? intval($datos['id_plan']) : null;
            $id_paciente_plan = !empty($datos['id_paciente_plan']) ? intval($datos['id_paciente_plan']) : null;

            // Si se asocia plan existente → registrar sesión y guardar aparatología
            if ($id_paciente_plan && empty($datos['nuevo_plan'])) {
                $this->registrarSesionPlan($id_paciente_plan, $id_cita);

                // Guardar aparatología de esta sesión
                if (!empty($datos['aparatologia_sesion'])) {
                    $this->con->prepare(
                        "DELETE FROM paciente_plan_aparatologia
                         WHERE id_paciente_plan = :pp AND id_cita = :cita"
                    )->execute([':pp' => $id_paciente_plan, ':cita' => $id_cita]);

                    $ins = $this->con->prepare(
                        "INSERT INTO paciente_plan_aparatologia
                            (id_paciente_plan, id_cita, id_aparatologia,
                             descripcion, precio_base_ref, precio_acordado, incluida_en_costo, cantidad)
                         VALUES (:pp, :cita, :ap, :desc, :ref, :acord, :incl, :cant)"
                    );
                    foreach ($datos['aparatologia_sesion'] as $ap) {
                        $ins->execute([
                            ':pp'   => $id_paciente_plan,
                            ':cita' => $id_cita,
                            ':ap'   => intval($ap['id_aparatologia']),
                            ':desc' => $ap['nombre'],
                            ':ref'  => floatval($ap['precio_base_ref']),
                            ':acord'=> floatval($ap['precio_acordado']),
                            ':incl' => 0,
                            ':cant' => intval($ap['cantidad'] ?? 1),
                        ]);
                    }
                }
            }

            // Manejar plan nuevo
            if (!empty($datos['nuevo_plan'])) {
                $np = $datos['nuevo_plan'];

                // Verificar si ya existe plan para esta cita
                $chkPlan = $this->con->prepare(
                    "SELECT id_paciente_plan FROM paciente_planes WHERE creado_en_cita = :cita"
                );
                $chkPlan->execute([':cita' => $id_cita]);
                $planExistente = $chkPlan->fetchColumn();

                if ($planExistente) {
                    // Actualizar plan
                    $id_paciente_plan = $planExistente;
                    $this->con->prepare(
                        "UPDATE paciente_planes SET
                            tipo = :tipo, costo_acordado = :costo,
                            en_cuotas = :cuotas, sesiones_pago_est = :ses_est,
                            costo_estimado_sesion = :costo_ses,
                            cuota_inicial = :cuota_ini, notas = :notas
                         WHERE id_paciente_plan = :id"
                    )->execute([
                        ':tipo'      => $np['tipo'] ?? 'por_sesion',
                        ':costo'     => isset($np['costo_acordado']) ? floatval($np['costo_acordado']) : null,
                        ':cuotas'    => intval($np['en_cuotas'] ?? 0),
                        ':ses_est'   => isset($np['sesiones_pago_est']) ? intval($np['sesiones_pago_est']) : null,
                        ':costo_ses' => isset($np['costo_estimado_sesion']) ? floatval($np['costo_estimado_sesion']) : null,
                        ':cuota_ini' => floatval($np['cuota_inicial'] ?? 0),
                        ':notas'     => $np['notas'] ?: null,
                        ':id'        => $id_paciente_plan
                    ]);
                } else {
                    // Crear plan nuevo
                    $this->con->prepare(
                        "INSERT INTO paciente_planes
                            (id_plan, id_paciente, id_doctor, tipo, costo_acordado,
                             en_cuotas, sesiones_pago_est, costo_estimado_sesion,
                             cuota_inicial, notas, creado_en_cita, fecha_inicio, estado)
                         VALUES
                            (:plan, :pac, :doc, :tipo, :costo,
                             :cuotas, :ses_est, :costo_ses,
                             :cuota_ini, :notas, :cita, CURDATE(), 'activo')"
                    )->execute([
                        ':plan'      => intval($np['id_plan_catalogo']),
                        ':pac'       => $id_paciente,
                        ':doc'       => $id_doctor,
                        ':tipo'      => $np['tipo'] ?? 'por_sesion',
                        ':costo'     => isset($np['costo_acordado']) ? floatval($np['costo_acordado']) : null,
                        ':cuotas'    => intval($np['en_cuotas'] ?? 0),
                        ':ses_est'   => isset($np['sesiones_pago_est']) ? intval($np['sesiones_pago_est']) : null,
                        ':costo_ses' => isset($np['costo_estimado_sesion']) ? floatval($np['costo_estimado_sesion']) : null,
                        ':cuota_ini' => floatval($np['cuota_inicial'] ?? 0),
                        ':notas'     => $np['notas'] ?: null,
                        ':cita'      => $id_cita,
                    ]);
                    $id_paciente_plan = $this->con->lastInsertId();
                    // Registrar sesión 1 al crear el plan
                    $this->registrarSesionPlan($id_paciente_plan, $id_cita);
                }

                // Guardar aparatología — siempre borrar y reinsertar
                if (!empty($np['aparatologia'])) {
                    $this->con->prepare(
                        "DELETE FROM paciente_plan_aparatologia
                         WHERE id_paciente_plan = :pp AND id_cita = :cita"
                    )->execute([':pp' => $id_paciente_plan, ':cita' => $id_cita]);

                    $ins = $this->con->prepare(
                        "INSERT INTO paciente_plan_aparatologia
                            (id_paciente_plan, id_cita, id_aparatologia,
                             descripcion, precio_base_ref, precio_acordado, incluida_en_costo, cantidad)
                         VALUES (:pp, :cita, :ap, :desc, :ref, :acord, :incl, :cant)"
                    );
                    foreach ($np['aparatologia'] as $ap) {
                        $ins->execute([
                            ':pp'   => $id_paciente_plan,
                            ':cita' => $id_cita,
                            ':ap'   => intval($ap['id_aparatologia']),
                            ':desc' => $ap['nombre'],
                            ':ref'  => floatval($ap['precio_base_ref']),
                            ':acord'=> floatval($ap['precio_acordado']),
                            ':incl' => intval($ap['incluida_en_costo']),
                            ':cant' => intval($ap['cantidad'] ?? 1),
                        ]);
                    }
                }

                $id_plan = intval($np['id_plan_catalogo']);
            }

            // Verificar si ya existe informe
            $existe = $this->con->prepare(
                "SELECT id_informe, estado FROM informe_atencion WHERE id_cita = :id"
            );
            $existe->execute([':id' => $id_cita]);
            $informe = $existe->fetch(PDO::FETCH_ASSOC);

            // Bloquear si está enviado y tiene pagos
            if ($informe && $informe['estado'] === 'enviado') {
                $pagos = $this->con->prepare("SELECT COUNT(*) FROM pagos WHERE id_cita = :id");
                $pagos->execute([':id' => $id_cita]);
                if ($pagos->fetchColumn() > 0) {
                    $this->con->rollBack();
                    return ['success' => false, 'mensaje' => 'No puedes editar un informe con pagos registrados'];
                }
            }

            $fechaEnvio = ($estado === 'enviado') ? date('Y-m-d H:i:s') : null;

            if ($informe) {
                $this->con->prepare(
                    "UPDATE informe_atencion SET
                        motivo_consulta = :motivo, diagnostico = :diag,
                        tratamiento_realizado = :trat, materiales = :mat,
                        observaciones = :obs, id_plan = :plan,
                        id_paciente_plan = :pp, estado = :estado,
                        fecha_envio = COALESCE(fecha_envio, :fecha_envio)
                     WHERE id_informe = :id"
                )->execute([
                    ':motivo'      => $datos['motivo_consulta'] ?? null,
                    ':diag'        => $datos['diagnostico'] ?? null,
                    ':trat'        => $datos['tratamiento_realizado'] ?? null,
                    ':mat'         => $datos['materiales'] ?? null,
                    ':obs'         => $datos['observaciones'] ?? null,
                    ':plan'        => $id_plan,
                    ':pp'          => $id_paciente_plan,
                    ':estado'      => $estado,
                    ':fecha_envio' => $fechaEnvio,
                    ':id'          => $informe['id_informe']
                ]);
                $id_informe = $informe['id_informe'];
            } else {
                $this->con->prepare(
                    "INSERT INTO informe_atencion
                        (id_cita, id_doctor, motivo_consulta, diagnostico,
                         tratamiento_realizado, materiales, observaciones,
                         id_plan, id_paciente_plan, estado, fecha_envio)
                     VALUES
                        (:cita, :doc, :motivo, :diag, :trat, :mat, :obs,
                         :plan, :pp, :estado, :fecha_envio)"
                )->execute([
                    ':cita'        => $id_cita,
                    ':doc'         => $id_doctor,
                    ':motivo'      => $datos['motivo_consulta'] ?? null,
                    ':diag'        => $datos['diagnostico'] ?? null,
                    ':trat'        => $datos['tratamiento_realizado'] ?? null,
                    ':mat'         => $datos['materiales'] ?? null,
                    ':obs'         => $datos['observaciones'] ?? null,
                    ':plan'        => $id_plan,
                    ':pp'          => $id_paciente_plan,
                    ':estado'      => $estado,
                    ':fecha_envio' => $fechaEnvio,
                ]);
                $id_informe = $this->con->lastInsertId();
            }

            // Guardar servicios
            $this->con->prepare("DELETE FROM cita_servicios WHERE id_cita = :id")
                ->execute([':id' => $id_cita]);

            if (!empty($datos['servicios'])) {
                $ins = $this->con->prepare(
                    "INSERT INTO cita_servicios
                        (id_cita, id_tipo_servicio, cantidad, precio_unitario, subtotal)
                     VALUES (:cita, :serv, :cant, :precio, :sub)"
                );
                foreach ($datos['servicios'] as $srv) {
                    $sub = floatval($srv['cantidad']) * floatval($srv['precio_unitario']);
                    $ins->execute([
                        ':cita'   => $id_cita,
                        ':serv'   => intval($srv['id_tipo_servicio']),
                        ':cant'   => intval($srv['cantidad']),
                        ':precio' => floatval($srv['precio_unitario']),
                        ':sub'    => $sub,
                    ]);
                }
            }

            $this->con->commit();
            return ['success' => true, 'id_informe' => $id_informe];

        } catch (PDOException $e) {
            $this->con->rollBack();
            error_log("guardarInforme: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al guardar: ' . $e->getMessage()];
        }
    }

    // ── Enviar a cobrar ───────────────────────────────────────────
    public function enviarACobrar($id_informe, $id_doctor) {
        try {
            $s = $this->con->prepare(
                "SELECT ia.id_informe, ia.id_cita, ia.estado
                  FROM informe_atencion ia
                  WHERE ia.id_informe = :id AND ia.id_doctor = :doc"
            );
            $s->execute([':id' => $id_informe, ':doc' => $id_doctor]);
            $informe = $s->fetch(PDO::FETCH_ASSOC);

            if (!$informe) {
                return ['success' => false, 'mensaje' => 'Informe no encontrado'];
            }

            if ($informe['estado'] === 'enviado') {
                $pagos = $this->con->prepare("SELECT COUNT(*) FROM pagos WHERE id_cita = :id");
                $pagos->execute([':id' => $informe['id_cita']]);
                if ($pagos->fetchColumn() > 0) {
                    return ['success' => false, 'mensaje' => 'No puedes modificar un informe con pagos registrados'];
                }
            }

            $this->con->prepare(
                "UPDATE informe_atencion SET estado = 'enviado', fecha_envio = COALESCE(fecha_envio, NOW())
                 WHERE id_informe = :id"
            )->execute([':id' => $id_informe]);
            return ['success' => true, 'mensaje' => 'Informe enviado a cobrar'];
        } catch (PDOException $e) {
            error_log("enviarACobrar: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al enviar'];
        }
    }

    // ── Verificar pagos ───────────────────────────────────────────
    public function tienePagos($id_cita) {
        try {
            $s = $this->con->prepare("SELECT COUNT(*) FROM pagos WHERE id_cita = :id");
            $s->execute([':id' => $id_cita]);
            return $s->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── Listar servicios de una cita ──────────────────────────────
    public function listarServiciosCita($id_cita) {
        try {
            $s = $this->con->prepare(
                "SELECT cs.*, ts.nombre_servicio
                 FROM cita_servicios cs
                 INNER JOIN tipo_servicio ts ON cs.id_tipo_servicio = ts.id_tipo_servicio
                 WHERE cs.id_cita = :id"
            );
            $s->execute([':id' => $id_cita]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Listar aparatología de un plan ────────────────────────────
    public function listarAparatologiaPlan($id_paciente_plan) {
        try {
            $s = $this->con->prepare(
                "SELECT pa.id, pa.id_aparatologia, pa.descripcion,
                        pa.precio_base_ref, pa.precio_acordado, pa.incluida_en_costo,
                        pa.id_cita, pa.cantidad
                 FROM paciente_plan_aparatologia pa
                 WHERE pa.id_paciente_plan = :id
                 ORDER BY pa.fecha_registro ASC"
            );
            $s->execute([':id' => $id_paciente_plan]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Planes activos del paciente ───────────────────────────────
    public function listarPlanesActivos($id_paciente) {
        try {
            $s = $this->con->prepare(
                "SELECT pp.id_paciente_plan, pp.tipo, pp.costo_acordado,
                        pp.cuota_inicial, pp.sesiones_pago_est, pp.en_cuotas,
                        pp.costo_estimado_sesion, pp.notas, pp.estado,
                        pp.creado_en_cita,
                        pt.nombre_plan, pt.id_plan,
                        CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor
                 FROM paciente_planes pp
                 INNER JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                 INNER JOIN usuarios u ON pp.id_doctor = u.id_usuario
                 WHERE pp.id_paciente = :id AND pp.estado = 'activo'
                 ORDER BY pp.fecha_creacion DESC"
            );
            $s->execute([':id' => $id_paciente]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Catálogos ─────────────────────────────────────────────────
    public function listarServicios() {
        try {
            return $this->con->query(
                "SELECT id_tipo_servicio, nombre_servicio, precio_base
                 FROM tipo_servicio WHERE activo = 1 ORDER BY nombre_servicio ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }

    public function listarPlanesCatalogo() {
        try {
            return $this->con->query(
                "SELECT id_plan, nombre_plan, costo_referencial AS costo_base
                 FROM planes_tratamiento WHERE activo = 1 ORDER BY nombre_plan ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }

    public function listarAparatologia() {
        try {
            return $this->con->query(
                "SELECT id_aparatologia, nombre, precio_base
                 FROM aparatologia WHERE activo = 1 ORDER BY nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }
    public function obtenerResumenPlan($id_paciente_plan, $id_cita) {
        try {
            // Datos del plan
            $sPlan = $this->con->prepare(
                "SELECT pp.*, pt.nombre_plan,
                        CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor
                 FROM paciente_planes pp
                 INNER JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                 INNER JOIN usuarios u ON pp.id_doctor = u.id_usuario
                 WHERE pp.id_paciente_plan = :id"
            );
            $sPlan->execute([':id' => $id_paciente_plan]);
            $plan = $sPlan->fetch(PDO::FETCH_ASSOC);
            if (!$plan) return null;

            // Aparatología de todas las sesiones
            $sAp = $this->con->prepare(
                "SELECT pa.*, c.fecha AS fecha_cita, ps.numero_sesion
                 FROM paciente_plan_aparatologia pa
                 INNER JOIN citas c ON pa.id_cita = c.id_cita
                 LEFT JOIN plan_sesiones ps
                    ON ps.id_paciente_plan = pa.id_paciente_plan
                   AND ps.id_cita = pa.id_cita
                 WHERE pa.id_paciente_plan = :id
                 ORDER BY pa.id_cita ASC, pa.fecha_registro ASC"
            );
            $sAp->execute([':id' => $id_paciente_plan]);
            $aparatologia = $sAp->fetchAll(PDO::FETCH_ASSOC);

            // Sesiones con pagos
            $sSes = $this->con->prepare(
                "SELECT ps.*, c.fecha AS fecha_cita,
                        COALESCE((
                            SELECT SUM(p.monto)
                            FROM pagos p
                            WHERE p.id_cita = ps.id_cita
                            AND p.tipo_pago = 'sesion'
                        ), 0) AS pagado_sesion
                 FROM plan_sesiones ps
                 INNER JOIN citas c ON ps.id_cita = c.id_cita
                 WHERE ps.id_paciente_plan = :id
                 ORDER BY ps.numero_sesion ASC"
            );
            $sSes->execute([':id' => $id_paciente_plan]);
            $sesiones = $sSes->fetchAll(PDO::FETCH_ASSOC);

            // Pagos por concepto
            $sPagos = $this->con->prepare(
                "SELECT tipo_pago, COALESCE(SUM(monto), 0) AS total_pagado
                 FROM pagos WHERE id_paciente_plan = :id
                 GROUP BY tipo_pago"
            );
            $sPagos->execute([':id' => $id_paciente_plan]);
            $pagos = [];
            foreach ($sPagos->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $pagos[$p['tipo_pago']] = floatval($p['total_pagado']);
            }

            // Número de sesión actual
            $sAct = $this->con->prepare(
                "SELECT numero_sesion FROM plan_sesiones
                 WHERE id_paciente_plan = :pp AND id_cita = :cita"
            );
            $sAct->execute([':pp' => $id_paciente_plan, ':cita' => $id_cita]);
            $sesionActual = $sAct->fetchColumn();

            if (!$sesionActual) {
                $sMax = $this->con->prepare(
                    "SELECT COALESCE(MAX(numero_sesion), 0) + 1
                     FROM plan_sesiones WHERE id_paciente_plan = :pp"
                );
                $sMax->execute([':pp' => $id_paciente_plan]);
                $sesionActual = intval($sMax->fetchColumn());
            }

            return [
                'plan'           => $plan,
                'aparatologia'   => $aparatologia,
                'sesiones'       => $sesiones,
                'pagos'          => $pagos,
                'sesion_actual'  => $sesionActual,
                'id_cita_actual' => $id_cita
            ];

        } catch (PDOException $e) {
            error_log("obtenerResumenPlan: " . $e->getMessage());
            return null;
        }
    }


    private function registrarSesionPlan($id_paciente_plan, $id_cita) {
        try {
            // Obtener datos del plan
            $sPlan = $this->con->prepare(
                "SELECT tipo, en_cuotas, costo_acordado, sesiones_pago_est, costo_estimado_sesion
                 FROM paciente_planes WHERE id_paciente_plan = :id"
            );
            $sPlan->execute([':id' => $id_paciente_plan]);
            $p = $sPlan->fetch(PDO::FETCH_ASSOC);
            if (!$p) return;

            // Calcular costo correcto por sesión según valores actuales del plan
            if ($p['tipo'] === 'costo_total' && !$p['en_cuotas']) {
                $costoSesion = 0; // pago único sin cuotas
            } elseif ($p['tipo'] === 'costo_total' && $p['en_cuotas']) {
                // Recalcular desde costo_acordado / sesiones_pago_est (fuente de verdad)
                $ses = intval($p['sesiones_pago_est'] ?? 1);
                $costoSesion = $ses > 0
                    ? round(floatval($p['costo_acordado']) / $ses, 2)
                    : 0;
            } else {
                // Por sesión: usar costo_estimado_sesion
                $costoSesion = floatval($p['costo_estimado_sesion'] ?? 0);
            }

            // Verificar si ya existe sesión para esta cita
            $chk = $this->con->prepare(
                "SELECT id_sesion FROM plan_sesiones
                 WHERE id_paciente_plan = :pp AND id_cita = :cita"
            );
            $chk->execute([':pp' => $id_paciente_plan, ':cita' => $id_cita]);
            $existente = $chk->fetch();

            if ($existente) {
                // Actualizar costo por si cambió el plan
                $this->con->prepare(
                    "UPDATE plan_sesiones SET costo_sesion = :costo
                     WHERE id_paciente_plan = :pp AND id_cita = :cita"
                )->execute([
                    ':costo' => $costoSesion,
                    ':pp'    => $id_paciente_plan,
                    ':cita'  => $id_cita,
                ]);
            } else {
                // Número de sesión = MAX + 1
                $sMax = $this->con->prepare(
                    "SELECT COALESCE(MAX(numero_sesion), 0) + 1
                     FROM plan_sesiones WHERE id_paciente_plan = :pp"
                );
                $sMax->execute([':pp' => $id_paciente_plan]);
                $numero = intval($sMax->fetchColumn());

                $this->con->prepare(
                    "INSERT INTO plan_sesiones
                        (id_paciente_plan, id_cita, numero_sesion, costo_sesion, total_pagado)
                     VALUES (:pp, :cita, :num, :costo, 0)"
                )->execute([
                    ':pp'    => $id_paciente_plan,
                    ':cita'  => $id_cita,
                    ':num'   => $numero,
                    ':costo' => $costoSesion,
                ]);
            }

        } catch (PDOException $e) {
            error_log("registrarSesionPlan: " . $e->getMessage());
        }
    }


}
?>