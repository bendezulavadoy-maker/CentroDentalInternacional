<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloAtenciones
{

    private $con;

    public function __construct()
    {
        $this->con = (new Conexion())->getConexion();
    }

    // ── Listar citas del paciente ─────────────────────────────────
    public function listarCitasPaciente($id_paciente)
    {
        try {
            $sql = "SELECT
                        c.id_cita, c.fecha, c.hora, c.motivo,
                        ec.estado, ec.id_estado_cita,
                        ts.id_tipo_servicio, ts.nombre_servicio,
                        s.nombre_sede,
                        CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor,
                        u.id_usuario AS id_doctor,
                        ia.id_informe, ia.estado AS estado_informe,
                        ia.diagnostico, ia.tratamiento_realizado,
                        ia.motivo_consulta, ia.materiales, ia.observaciones,
                        ia.id_plan, ia.id_paciente_plan,
                        pt.nombre_plan,
                        pp.tipo AS plan_tipo,
                        pp.costo_acordado AS plan_costo_acordado,
                        pp.cuota_inicial AS plan_cuota_inicial,
                        pp.en_cuotas AS plan_en_cuotas,
                        pp.sesiones_pago_est AS plan_sesiones_est,
                        pp.costo_estimado_sesion AS plan_costo_sesion,
                        pp.notas AS plan_notas,
                        COALESCE((
                            SELECT SUM(cs.subtotal)
                            FROM cita_servicios cs
                            WHERE cs.id_cita = c.id_cita
                        ), 0) AS total_servicios,
                        COALESCE((
                            SELECT SUM(p.monto)
                            FROM pagos p
                            WHERE p.id_cita = c.id_cita
                        ), 0) AS total_pagado
                    FROM citas c
                    INNER JOIN estado_cita ec    ON c.id_estado_cita = ec.id_estado_cita
                    LEFT  JOIN tipo_servicio ts   ON c.id_tipo_servicio = ts.id_tipo_servicio
                    LEFT  JOIN sedes s            ON c.id_sede_atencion = s.id_sede_atencion
                    INNER JOIN usuarios u         ON c.id_doctor = u.id_usuario
                    LEFT  JOIN informe_atencion ia ON c.id_cita = ia.id_cita
                    LEFT  JOIN planes_tratamiento pt ON ia.id_plan = pt.id_plan
                    LEFT  JOIN paciente_planes pp  ON ia.id_paciente_plan = pp.id_paciente_plan
                    WHERE c.id_paciente = :id
                    ORDER BY c.fecha DESC, c.hora DESC";

            $s = $this->con->prepare($sql);
            $s->execute([':id' => $id_paciente]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarCitasPaciente: " . $e->getMessage());
            return [];
        }
    }

    // ── Saldo del paciente ────────────────────────────────────────
    public function obtenerSaldo($id_paciente)
    {
        try {
            // Servicios sueltos
            $sSrv = $this->con->prepare(
                "SELECT COALESCE(SUM(cs.subtotal), 0)
                 FROM cita_servicios cs
                 INNER JOIN citas c ON cs.id_cita = c.id_cita
                 WHERE c.id_paciente = :id"
            );
            $sSrv->execute([':id' => $id_paciente]);
            $totalServicios = floatval($sSrv->fetchColumn());

            // Planes costo_total (monto acordado)
            $sCostoFijo = $this->con->prepare(
                "SELECT COALESCE(SUM(costo_acordado), 0)
                 FROM paciente_planes
                 WHERE id_paciente = :id AND tipo = 'costo_total' AND estado = 'activo'"
            );
            $sCostoFijo->execute([':id' => $id_paciente]);
            $totalCostoFijo = floatval($sCostoFijo->fetchColumn());

            // Cuotas iniciales planes por_sesion
            $sCuota = $this->con->prepare(
                "SELECT COALESCE(SUM(cuota_inicial), 0)
                 FROM paciente_planes
                 WHERE id_paciente = :id AND tipo = 'por_sesion' AND estado = 'activo'"
            );
            $sCuota->execute([':id' => $id_paciente]);
            $totalCuotas = floatval($sCuota->fetchColumn());

            // Aparatología separada
            $sAp = $this->con->prepare(
                "SELECT COALESCE(SUM(pa.precio_acordado), 0)
                 FROM paciente_plan_aparatologia pa
                 INNER JOIN paciente_planes pp ON pa.id_paciente_plan = pp.id_paciente_plan
                 WHERE pp.id_paciente = :id AND pa.incluida_en_costo = 0"
            );
            $sAp->execute([':id' => $id_paciente]);
            $totalAparatologia = floatval($sAp->fetchColumn());

            // Sesiones generadas
            $sSes = $this->con->prepare(
                "SELECT COALESCE(SUM(ps.costo_sesion), 0)
                 FROM plan_sesiones ps
                 INNER JOIN paciente_planes pp ON ps.id_paciente_plan = pp.id_paciente_plan
                 WHERE pp.id_paciente = :id"
            );
            $sSes->execute([':id' => $id_paciente]);
            $totalSesiones = floatval($sSes->fetchColumn());

            // Total pagado
            $sPag = $this->con->prepare(
                "SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE id_paciente = :id"
            );
            $sPag->execute([':id' => $id_paciente]);
            $totalPagado = floatval($sPag->fetchColumn());

            // Planes activos
            $sPlanes = $this->con->prepare(
                "SELECT COUNT(*) FROM paciente_planes
                 WHERE id_paciente = :id AND estado = 'activo'"
            );
            $sPlanes->execute([':id' => $id_paciente]);
            $planesActivos = intval($sPlanes->fetchColumn());

            $totalGenerado = $totalServicios + $totalCostoFijo + $totalCuotas
                + $totalAparatologia + $totalSesiones;

            return [
                'total_servicios' => $totalGenerado,
                'total_pagado'    => $totalPagado,
                'planes_activos'  => $planesActivos
            ];
        } catch (PDOException $e) {
            error_log("obtenerSaldo: " . $e->getMessage());
            return ['total_servicios' => 0, 'total_pagado' => 0, 'planes_activos' => 0];
        }
    }

    // ── Planes activos del paciente ───────────────────────────────
    public function listarPlanesActivos($id_paciente)
    {

        try {
            $sql = "SELECT
                            pp.id_paciente_plan, pp.tipo, pp.costo_acordado,
                            pp.cuota_inicial, pp.sesiones_pago_est,
                            pp.costo_estimado_sesion, pp.notas, pp.estado,
                            pp.fecha_inicio, pp.creado_en_cita,
                            pp.en_cuotas,
                            pt.nombre_plan, pt.id_plan,
                            CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor
                        FROM paciente_planes pp
                        INNER JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                        INNER JOIN usuarios u ON pp.id_doctor = u.id_usuario
                        WHERE pp.id_paciente = :id AND pp.estado = 'activo'
                        ORDER BY pp.fecha_creacion DESC";

            $s = $this->con->prepare($sql);
            $s->execute([':id' => $id_paciente]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarPlanesActivos: " . $e->getMessage());
            return [];
        }
    }
    public function listarAparatologiaPlan($id_paciente_plan)
    {
        try {
            $s = $this->con->prepare(
                "SELECT pa.id, pa.id_aparatologia, pa.descripcion,
                        pa.precio_base_ref, pa.precio_acordado, pa.incluida_en_costo
                 FROM paciente_plan_aparatologia pa
                 WHERE pa.id_paciente_plan = :id
                 ORDER BY pa.fecha_registro ASC"
            );
            $s->execute([':id' => $id_paciente_plan]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarAparatologiaPlan: " . $e->getMessage());
            return [];
        }
    }
    // ── Guardar informe ───────────────────────────────────────────
    public function guardarInforme($datos, $id_doctor)
    {
        try {
            $this->con->beginTransaction();

            $id_cita = intval($datos['id_cita']);
            $estado  = $datos['estado'] ?? 'borrador';

            // Verificar que la cita pertenece al doctor
            $chk = $this->con->prepare(
                "SELECT id_cita FROM citas WHERE id_cita = :id AND id_doctor = :doc"
            );
            $chk->execute([':id' => $id_cita, ':doc' => $id_doctor]);
            if (!$chk->fetch()) {
                $this->con->rollBack();
                return ['success' => false, 'mensaje' => 'No tienes permiso sobre esta cita'];
            }

            // Manejar plan del paciente
            $id_plan          = !empty($datos['id_plan']) ? intval($datos['id_plan']) : null;
            $id_paciente_plan = !empty($datos['id_paciente_plan']) ? intval($datos['id_paciente_plan']) : null;

            if (!empty($datos['nuevo_plan'])) {
                $np          = $datos['nuevo_plan'];
                $id_paciente = $this->obtenerPacienteDeCita($id_cita);

                // Verificar si ya existe un plan para esta cita
                $chkPlan = $this->con->prepare(
                    "SELECT id_paciente_plan FROM paciente_planes WHERE creado_en_cita = :cita"
                );
                $chkPlan->execute([':cita' => $id_cita]);
                $planExistente = $chkPlan->fetchColumn();

                if ($planExistente) {
                    // Actualizar plan existente
                    $id_paciente_plan = $planExistente;
                    $upd = $this->con->prepare(
                        "UPDATE paciente_planes SET
                            tipo = :tipo, costo_acordado = :costo,
                            en_cuotas = :cuotas, sesiones_pago_est = :ses_est,
                            costo_estimado_sesion = :costo_ses,
                            cuota_inicial = :cuota_ini, notas = :notas
                         WHERE id_paciente_plan = :id"
                    );
                    $upd->execute([
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
                    // Crear nuevo plan
                    $s = $this->con->prepare(
                        "INSERT INTO paciente_planes
                            (id_plan, id_paciente, id_doctor, tipo, costo_acordado,
                             en_cuotas, sesiones_pago_est, costo_estimado_sesion,
                             cuota_inicial, notas, creado_en_cita, fecha_inicio, estado)
                         VALUES
                            (:plan, :pac, :doc, :tipo, :costo,
                             :cuotas, :ses_est, :costo_ses,
                             :cuota_ini, :notas, :cita, CURDATE(), 'activo')"
                    );
                    $s->execute([
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
                }
                
                // Guardar aparatología — SIEMPRE, tanto en plan nuevo como existente
                if (!empty($np['aparatologia'])) {
                    // Eliminar aparatología anterior de este plan en esta cita
                    $this->con->prepare(
                        "DELETE FROM paciente_plan_aparatologia
                         WHERE id_paciente_plan = :pp AND id_cita = :cita"
                    )->execute([':pp' => $id_paciente_plan, ':cita' => $id_cita]);
                
                    $ins = $this->con->prepare(
                        "INSERT INTO paciente_plan_aparatologia
                            (id_paciente_plan, id_cita, id_aparatologia,
                             descripcion, precio_base_ref, precio_acordado, incluida_en_costo)
                         VALUES (:pp, :cita, :ap, :desc, :ref, :acord, :incl)"
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
                        ]);
                    }
                }
                // Obtener id_plan del catalogo para guardarlo en informe
                $id_plan = intval($np['id_plan_catalogo']);
            }

            // Verificar si existe informe
            $existe = $this->con->prepare(
                "SELECT id_informe, estado FROM informe_atencion WHERE id_cita = :id"
            );
            $existe->execute([':id' => $id_cita]);
            $informe = $existe->fetch(PDO::FETCH_ASSOC);

            // Si ya está enviado y tiene pagos, no permitir edición
            if ($informe && $informe['estado'] === 'enviado') {
                $pagos = $this->con->prepare(
                    "SELECT COUNT(*) FROM pagos WHERE id_cita = :id"
                );
                $pagos->execute([':id' => $id_cita]);
                if ($pagos->fetchColumn() > 0) {
                    $this->con->rollBack();
                    return ['success' => false, 'mensaje' => 'No puedes editar un informe con pagos registrados'];
                }
            }

            $fechaEnvio = ($estado === 'enviado') ? date('Y-m-d H:i:s') : null;

            if ($informe) {
                $s = $this->con->prepare(
                    "UPDATE informe_atencion SET
                        motivo_consulta = :motivo, diagnostico = :diag,
                        tratamiento_realizado = :trat, materiales = :mat,
                        observaciones = :obs, id_plan = :plan,
                        id_paciente_plan = :pp,
                        estado = :estado,
                        fecha_envio = COALESCE(fecha_envio, :fecha_envio)
                     WHERE id_informe = :id"
                );
                $s->execute([
                    ':motivo'      => $datos['motivo_consulta'] ?: null,
                    ':diag'        => $datos['diagnostico'] ?: null,
                    ':trat'        => $datos['tratamiento_realizado'] ?: null,
                    ':mat'         => $datos['materiales'] ?: null,
                    ':obs'         => $datos['observaciones'] ?: null,
                    ':plan'        => $id_plan,
                    ':pp'          => $id_paciente_plan,
                    ':estado'      => $estado,
                    ':fecha_envio' => $fechaEnvio,
                    ':id'          => $informe['id_informe']
                ]);
                $id_informe = $informe['id_informe'];
            } else {
                $s = $this->con->prepare(
                    "INSERT INTO informe_atencion
                        (id_cita, id_doctor, motivo_consulta, diagnostico,
                         tratamiento_realizado, materiales, observaciones,
                         id_plan, id_paciente_plan, estado, fecha_envio)
                     VALUES
                        (:cita, :doc, :motivo, :diag, :trat, :mat, :obs,
                         :plan, :pp, :estado, :fecha_envio)"
                );
                $s->execute([
                    ':cita'        => $id_cita,
                    ':doc'         => $id_doctor,
                    ':motivo'      => $datos['motivo_consulta'] ?: null,
                    ':diag'        => $datos['diagnostico'] ?: null,
                    ':trat'        => $datos['tratamiento_realizado'] ?: null,
                    ':mat'         => $datos['materiales'] ?: null,
                    ':obs'         => $datos['observaciones'] ?: null,
                    ':plan'        => $id_plan,
                    ':pp'          => $id_paciente_plan,
                    ':estado'      => $estado,
                    ':fecha_envio' => $fechaEnvio,
                ]);
                $id_informe = $this->con->lastInsertId();
            }

            // Guardar servicios sueltos
            $this->con->prepare(
                "DELETE FROM cita_servicios WHERE id_cita = :id"
            )->execute([':id' => $id_cita]);

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

    // ── Servicios de una cita ─────────────────────────────────────
    public function listarServiciosCita($id_cita)
    {
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
            error_log("listarServiciosCita: " . $e->getMessage());
            return [];
        }
    }

    // ── Registrar pago ────────────────────────────────────────────
    public function registrarPago($id_paciente, $id_cita, $monto, $observacion, $registrado_por)
    {
        try {
            $s = $this->con->prepare(
                "INSERT INTO pagos (id_paciente, id_cita, monto, observacion, registrado_por)
                 VALUES (:pac, :cita, :monto, :obs, :reg)"
            );
            $s->execute([
                ':pac'   => $id_paciente,
                ':cita'  => $id_cita ?: null,
                ':monto' => $monto,
                ':obs'   => $observacion ?: null,
                ':reg'   => $registrado_por,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("registrarPago: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al registrar pago'];
        }
    }

    // ── Verificar pagos ───────────────────────────────────────────
    public function citaTienePagos($id_cita)
    {
        try {
            $s = $this->con->prepare(
                "SELECT COUNT(*) FROM pagos WHERE id_cita = :id"
            );
            $s->execute([':id' => $id_cita]);
            return $s->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── Enviar a cobrar ───────────────────────────────────────────
    public function enviarACobrar($id_informe, $id_doctor)
    {
        try {
            // Verificar que el informe pertenece al doctor
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

            // Si ya está enviado, verificar que no tenga pagos
            if ($informe['estado'] === 'enviado') {
                $pagos = $this->con->prepare("SELECT COUNT(*) FROM pagos WHERE id_cita = :id");
                $pagos->execute([':id' => $informe['id_cita']]);
                if ($pagos->fetchColumn() > 0) {
                    return ['success' => false, 'mensaje' => 'No puedes modificar un informe con pagos registrados'];
                }
            }

            $this->con->prepare(
                "UPDATE informe_atencion
                 SET estado = 'enviado', fecha_envio = COALESCE(fecha_envio, NOW())
                 WHERE id_informe = :id"
            )->execute([':id' => $id_informe]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("enviarACobrar: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al enviar'];
        }
    }

    // ── Cobros pendientes ─────────────────────────────────────────
    public function listarCobrosPendientes()
    {
        try {
            $sql = "SELECT
                        ia.id_informe, ia.id_cita, ia.fecha_envio,
                        c.fecha, c.hora,
                        CONCAT(p.nombre,' ',p.apellido) AS nombre_paciente,
                        p.id_paciente,
                        ts.nombre_servicio,
                        CONCAT(u.nombre,' ',u.apellidos) AS nombre_doctor,
                        COALESCE((
                            SELECT SUM(cs.subtotal)
                            FROM cita_servicios cs WHERE cs.id_cita = c.id_cita
                        ), 0) AS total_servicios,
                        COALESCE((
                            SELECT SUM(pg.monto)
                            FROM pagos pg WHERE pg.id_cita = c.id_cita
                        ), 0) AS total_pagado
                    FROM informe_atencion ia
                    INNER JOIN citas c      ON ia.id_cita = c.id_cita
                    INNER JOIN pacientes p  ON c.id_paciente = p.id_paciente
                    INNER JOIN usuarios u   ON ia.id_doctor = u.id_usuario
                    LEFT  JOIN tipo_servicio ts ON c.id_tipo_servicio = ts.id_tipo_servicio
                    WHERE ia.estado = 'enviado'
                    HAVING (total_servicios - total_pagado) > 0
                    ORDER BY ia.fecha_envio ASC";

            $s = $this->con->query($sql);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarCobrosPendientes: " . $e->getMessage());
            return [];
        }
    }

    // ── Catálogos ─────────────────────────────────────────────────
    public function listarServicios()
    {
        try {
            $s = $this->con->query(
                "SELECT id_tipo_servicio, nombre_servicio, precio_base
                 FROM tipo_servicio WHERE activo = 1 ORDER BY nombre_servicio ASC"
            );
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarServicios: " . $e->getMessage());
            return [];
        }
    }

    public function listarPlanes()
    {
        try {
            $s = $this->con->query(
                "SELECT id_plan, nombre_plan, costo_referencial AS costo_base, sesiones_est
                 FROM planes_tratamiento WHERE activo = 1 ORDER BY nombre_plan ASC"
            );
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarPlanes: " . $e->getMessage());
            return [];
        }
    }

    public function listarAparatologia()
    {
        try {
            $s = $this->con->query(
                "SELECT id_aparatologia, nombre, precio_base
                 FROM aparatologia WHERE activo = 1 ORDER BY nombre ASC"
            );
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── Cuenta del paciente ───────────────────────────────────────
    public function obtenerCuentaPaciente($id_paciente)
    {
        try {
            $saldo = $this->obtenerSaldo($id_paciente);
            $totalGenerado  = floatval($saldo['total_servicios']);
            $totalPagado    = floatval($saldo['total_pagado']);
            $saldoPendiente = $totalGenerado - $totalPagado;

            $s = $this->con->prepare(
                "SELECT p.monto, p.fecha_pago, p.observacion, p.concepto,
                        CONCAT(u.nombre,' ',u.apellidos) AS registrado_por
                 FROM pagos p
                 INNER JOIN usuarios u ON p.registrado_por = u.id_usuario
                 WHERE p.id_paciente = :id
                 ORDER BY p.fecha_pago DESC"
            );
            $s->execute([':id' => $id_paciente]);
            $pagos = $s->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total_generado'  => $totalGenerado,
                'total_pagado'    => $totalPagado,
                'saldo_pendiente' => $saldoPendiente,
                'detalle_pagos'   => $pagos
            ];
        } catch (PDOException $e) {
            error_log("obtenerCuentaPaciente: " . $e->getMessage());
            return ['total_generado' => 0, 'total_pagado' => 0, 'saldo_pendiente' => 0, 'detalle_pagos' => []];
        }
    }

    // ── Helper privado ────────────────────────────────────────────
    private function obtenerPacienteDeCita($id_cita)
    {
        $s = $this->con->prepare(
            "SELECT id_paciente FROM citas WHERE id_cita = :id"
        );
        $s->execute([':id' => $id_cita]);
        return $s->fetchColumn();
    }
}