<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloCobros
{
    private $con;

    public function __construct()
    {
        $this->con = (new Conexion())->getConexion();
    }

    // ── Buzón: un registro por paciente con informes enviados ─────
    public function listarBuzon()
    {
        try {
            $sql = "
                SELECT
                    p.id_paciente,
                    CONCAT(p.nombre,' ',p.apellido) AS nombre_paciente,
                    p.dni AS dni_paciente,
                    COUNT(DISTINCT ia.id_informe) AS total_informes,
                    MAX(ia.fecha_envio) AS ultimo_envio
                FROM informe_atencion ia
                INNER JOIN citas     c ON ia.id_cita    = c.id_cita
                INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
                WHERE ia.estado = 'enviado'
                GROUP BY p.id_paciente, p.nombre, p.apellido, p.dni
                ORDER BY ultimo_envio ASC";

            $s = $this->con->query($sql);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("listarBuzon: " . $e->getMessage());
            return [];
        }
    }

    // ── Detalle de cobro por paciente ─────────────────────────────
    // Lógica:
    // 1. Obtener el informe enviado más reciente (cita actual)
    // 2. Obtener deudas pendientes de citas anteriores del mismo plan
    // 3. Separar: servicios sueltos, plan (sesiones, aparatología)
    public function detalleCobro($id_paciente)
    {
        try {
            // ── Informes enviados del paciente ────────────────────
            $sqlInf = "
                SELECT ia.id_informe, ia.id_cita, ia.id_paciente_plan,
                       c.fecha, c.hora
                FROM informe_atencion ia
                INNER JOIN citas c ON ia.id_cita = c.id_cita
                WHERE c.id_paciente = :id AND ia.estado = 'enviado'
                ORDER BY c.fecha DESC, ia.fecha_envio DESC";

            $s = $this->con->prepare($sqlInf);
            $s->execute([':id' => $id_paciente]);
            $informes = $s->fetchAll(PDO::FETCH_ASSOC);

            if (empty($informes)) {
                return ['success' => true, 'citas' => [], 'deudas_anteriores' => []];
            }

            // ── Solo el informe más reciente es la cita actual ──
            // Los demás informes enviados con saldo pasan a deudas anteriores
            $citas = [];
            $informe_actual = $informes[0]; // más reciente (ORDER BY fecha DESC)
            $informes_anteriores_enviados = array_slice($informes, 1);

            foreach ([$informe_actual] as $inf) {
                $id_cita = $inf['id_cita'];
                $id_pp   = $inf['id_paciente_plan'];

                $citaData = [
                    'id_informe'       => $inf['id_informe'],
                    'id_cita'          => $id_cita,
                    'id_paciente_plan' => $id_pp,
                    'fecha'            => $inf['fecha'],
                    'hora'             => $inf['hora'],
                    'servicios'        => [],
                    'sesion'           => null,
                    'aparatologia_sep' => [],
                    'plan'             => null,
                ];

                // Servicios sueltos de esta cita
                $sqlSrv = "
                    SELECT cs.id_cita_servicio, ts.nombre_servicio,
                           cs.cantidad, cs.precio_unitario, cs.subtotal,
                           COALESCE((
                               SELECT SUM(pg.monto) FROM pagos pg
                               WHERE pg.id_cita_servicio = cs.id_cita_servicio
                                 AND pg.tipo_pago = 'total'
                           ), 0) AS pagado
                    FROM cita_servicios cs
                    INNER JOIN tipo_servicio ts ON ts.id_tipo_servicio = cs.id_tipo_servicio
                    WHERE cs.id_cita = :cita";
                $s = $this->con->prepare($sqlSrv);
                $s->execute([':cita' => $id_cita]);
                $citaData['servicios'] = $s->fetchAll(PDO::FETCH_ASSOC);

                // Si tiene plan: sesión y aparatología de esta cita
                if ($id_pp) {
                    // Plan info
                    $sqlPlan = "
                        SELECT pp.id_paciente_plan, pp.tipo, pp.costo_acordado,
                               pp.en_cuotas, pp.sesiones_pago_est,
                               pp.costo_estimado_sesion, pp.cuota_inicial,
                               pt.nombre_plan
                        FROM paciente_planes pp
                        INNER JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                        WHERE pp.id_paciente_plan = :pp";
                    $s = $this->con->prepare($sqlPlan);
                    $s->execute([':pp' => $id_pp]);
                    $citaData['plan'] = $s->fetch(PDO::FETCH_ASSOC);

                    // Sesión de esta cita
                    $sqlSes = "
                        SELECT ps.id_sesion, ps.numero_sesion, ps.costo_sesion,
                               COALESCE((
                                   SELECT SUM(pg.monto) FROM pagos pg
                                   WHERE pg.id_sesion = ps.id_sesion
                                     AND pg.tipo_pago = 'sesion'
                               ), 0) AS pagado
                        FROM plan_sesiones ps
                        WHERE ps.id_paciente_plan = :pp AND ps.id_cita = :cita";
                    $s = $this->con->prepare($sqlSes);
                    $s->execute([':pp' => $id_pp, ':cita' => $id_cita]);
                    $citaData['sesion'] = $s->fetch(PDO::FETCH_ASSOC) ?: null;

                    // Aparatología separada de esta cita
                    $sqlAp = "
                        SELECT pa.id, pa.descripcion,
                               pa.precio_acordado, pa.cantidad,
                               COALESCE((
                                   SELECT SUM(pg.monto) FROM pagos pg
                                   WHERE pg.id_aparatologia_item = pa.id
                                     AND pg.tipo_pago = 'aparatologia'
                               ), 0) AS pagado
                        FROM paciente_plan_aparatologia pa
                        WHERE pa.id_paciente_plan = :pp
                          AND pa.id_cita = :cita
                          AND pa.incluida_en_costo = 0";
                    $s = $this->con->prepare($sqlAp);
                    $s->execute([':pp' => $id_pp, ':cita' => $id_cita]);
                    $citaData['aparatologia_sep'] = $s->fetchAll(PDO::FETCH_ASSOC);
                }

                $citas[] = $citaData;
            }

            // ── Deudas anteriores ─────────────────────────────────
            // Incluye: informes enviados anteriores con saldo + sesiones anteriores con saldo
            $deudas = [];
            $planes_vistos = [];

            // Agregar informes enviados anteriores (todos excepto el más reciente)
            foreach ($informes_anteriores_enviados as $inf_ant) {
                $id_cita_ant = $inf_ant['id_cita'];
                $id_pp_ant   = $inf_ant['id_paciente_plan'];

                // Servicios sueltos de esa cita anterior con saldo
                $sqlSrvAnt = "
                    SELECT cs.id_cita_servicio, ts.nombre_servicio,
                           cs.cantidad, cs.precio_unitario, cs.subtotal, c.fecha,
                           COALESCE((
                               SELECT SUM(pg.monto) FROM pagos pg
                               WHERE pg.id_cita_servicio = cs.id_cita_servicio
                                 AND pg.tipo_pago = 'total'
                           ), 0) AS pagado
                    FROM cita_servicios cs
                    INNER JOIN tipo_servicio ts ON ts.id_tipo_servicio = cs.id_tipo_servicio
                    INNER JOIN citas c          ON c.id_cita = cs.id_cita
                    WHERE cs.id_cita = :cita
                    HAVING (cs.subtotal - pagado) > 0";
                $s = $this->con->prepare($sqlSrvAnt);
                $s->execute([':cita' => $id_cita_ant]);
                $srv_ant = $s->fetchAll(PDO::FETCH_ASSOC);

                // Sesión de esa cita anterior con saldo
                $sqlSesAnt = "
                    SELECT ps.id_sesion, ps.numero_sesion, ps.costo_sesion,
                           ps.id_cita, c.fecha, pt.nombre_plan,
                           COALESCE((
                               SELECT SUM(pg.monto) FROM pagos pg
                               WHERE pg.id_sesion = ps.id_sesion
                                 AND pg.tipo_pago = 'sesion'
                           ), 0) AS pagado
                    FROM plan_sesiones ps
                    INNER JOIN citas c              ON ps.id_cita = c.id_cita
                    INNER JOIN paciente_planes pp    ON ps.id_paciente_plan = pp.id_paciente_plan
                    INNER JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                    WHERE ps.id_cita = :cita
                    HAVING (ps.costo_sesion - pagado) > 0";
                $s = $this->con->prepare($sqlSesAnt);
                $s->execute([':cita' => $id_cita_ant]);
                $ses_ant = $s->fetchAll(PDO::FETCH_ASSOC);

                // Aparatología separada de esa cita anterior con saldo
                $sqlApAnt = "
                    SELECT pa.id, pa.descripcion, pa.precio_acordado, pa.cantidad,
                           pa.id_cita, c.fecha, pt.nombre_plan,
                           COALESCE((
                               SELECT SUM(pg.monto) FROM pagos pg
                               WHERE pg.id_aparatologia_item = pa.id
                                 AND pg.tipo_pago = 'aparatologia'
                           ), 0) AS pagado
                    FROM paciente_plan_aparatologia pa
                    INNER JOIN citas c              ON pa.id_cita = c.id_cita
                    INNER JOIN paciente_planes pp    ON pa.id_paciente_plan = pp.id_paciente_plan
                    INNER JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                    WHERE pa.id_cita = :cita AND pa.incluida_en_costo = 0
                    HAVING ((pa.precio_acordado * COALESCE(pa.cantidad,1)) - pagado) > 0";
                $s = $this->con->prepare($sqlApAnt);
                $s->execute([':cita' => $id_cita_ant]);
                $ap_ant = $s->fetchAll(PDO::FETCH_ASSOC);

                if ($srv_ant || $ses_ant || $ap_ant) {
                    $nombre_plan = $ses_ant[0]['nombre_plan'] ?? $ap_ant[0]['nombre_plan'] ?? null;
                    $deudas[] = [
                        'id_paciente_plan' => $id_pp_ant,
                        'nombre_plan'      => $nombre_plan,
                        'sesiones'         => $ses_ant,
                        'aparatologia'     => $ap_ant,
                        'servicios'        => $srv_ant,
                    ];
                }
            }

            foreach ($informes as $inf) {
                if (!$inf['id_paciente_plan']) continue;
                $id_pp = $inf['id_paciente_plan'];
                if (in_array($id_pp, $planes_vistos)) continue;
                $planes_vistos[] = $id_pp;

                // Solo citas que NO son la cita actual
                $citas_enviadas = array_column(
                    array_filter($informes, fn($i) => $i['id_paciente_plan'] == $id_pp),
                    'id_cita'
                );
                // Excluir también la cita actual
                $citas_excluir = array_unique(array_merge(
                    $citas_enviadas,
                    [$informe_actual['id_cita']]
                ));
                $citas_excluir = array_unique(array_merge($citas_enviadas, [$informe_actual['id_cita']]));
                $placeholders  = implode(',', array_fill(0, count($citas_excluir), '?'));

                // Sesiones anteriores con deuda
                $sqlDeuSes = "
                    SELECT ps.id_sesion, ps.numero_sesion, ps.costo_sesion,
                           ps.id_cita, c.fecha,
                           COALESCE((
                               SELECT SUM(pg.monto) FROM pagos pg
                               WHERE pg.id_sesion = ps.id_sesion
                                 AND pg.tipo_pago = 'sesion'
                           ), 0) AS pagado,
                           pt.nombre_plan
                    FROM plan_sesiones ps
                    INNER JOIN citas c               ON ps.id_cita = c.id_cita
                    INNER JOIN paciente_planes pp     ON ps.id_paciente_plan = pp.id_paciente_plan
                    INNER JOIN planes_tratamiento pt  ON pp.id_plan = pt.id_plan
                    INNER JOIN informe_atencion ia    ON ia.id_cita = ps.id_cita
                                                     AND ia.estado = 'enviado'
                    WHERE ps.id_paciente_plan = ?
                      AND ps.id_cita NOT IN ($placeholders)
                    HAVING (ps.costo_sesion - pagado) > 0
                    ORDER BY ps.numero_sesion ASC";

                $params = array_merge([$id_pp], $citas_excluir);
                $s = $this->con->prepare($sqlDeuSes);
                $s->execute($params);
                $sesiones_deuda = $s->fetchAll(PDO::FETCH_ASSOC);

                // Aparatología separada anterior con deuda
                $sqlDeuAp = "
                    SELECT pa.id, pa.descripcion, pa.precio_acordado, pa.cantidad,
                           pa.id_cita, c.fecha, pt.nombre_plan,
                           COALESCE((
                               SELECT SUM(pg.monto) FROM pagos pg
                               WHERE pg.id_aparatologia_item = pa.id
                                 AND pg.tipo_pago = 'aparatologia'
                           ), 0) AS pagado
                    FROM paciente_plan_aparatologia pa
                    INNER JOIN citas c               ON pa.id_cita = c.id_cita
                    INNER JOIN paciente_planes pp     ON pa.id_paciente_plan = pp.id_paciente_plan
                    INNER JOIN planes_tratamiento pt  ON pp.id_plan = pt.id_plan
                    INNER JOIN informe_atencion ia    ON ia.id_cita = pa.id_cita
                                                     AND ia.estado = 'enviado'
                    WHERE pa.id_paciente_plan = ?
                      AND pa.id_cita NOT IN ($placeholders)
                      AND pa.incluida_en_costo = 0
                    HAVING ((pa.precio_acordado * pa.cantidad) - pagado) > 0
                    ORDER BY pa.fecha_registro ASC";

                $s = $this->con->prepare($sqlDeuAp);
                $s->execute($params);
                $ap_deuda = $s->fetchAll(PDO::FETCH_ASSOC);

                // Servicios sueltos anteriores con deuda
                $sqlDeuSrv = "
                    SELECT cs.id_cita_servicio, ts.nombre_servicio,
                           cs.cantidad, cs.precio_unitario, cs.subtotal,
                           cs.id_cita, c.fecha,
                           COALESCE((
                               SELECT SUM(pg.monto) FROM pagos pg
                               WHERE pg.id_cita_servicio = cs.id_cita_servicio
                                 AND pg.tipo_pago = 'total'
                           ), 0) AS pagado
                    FROM cita_servicios cs
                    INNER JOIN citas c         ON cs.id_cita = c.id_cita
                    INNER JOIN tipo_servicio ts ON ts.id_tipo_servicio = cs.id_tipo_servicio
                    INNER JOIN informe_atencion ia ON ia.id_cita = cs.id_cita
                    WHERE c.id_paciente = ?
                      AND cs.id_cita NOT IN ($placeholders)
                      AND ia.id_paciente_plan = ?
                    HAVING (cs.subtotal - pagado) > 0
                    ORDER BY c.fecha ASC";

                $s = $this->con->prepare($sqlDeuSrv);
                $s->execute(array_merge([$id_paciente], $citas_excluir, [$id_pp]));
                $srv_deuda = $s->fetchAll(PDO::FETCH_ASSOC);

                if ($sesiones_deuda || $ap_deuda || $srv_deuda) {
                    $deudas[] = [
                        'id_paciente_plan' => $id_pp,
                        'nombre_plan'      => $sesiones_deuda[0]['nombre_plan']
                                             ?? $ap_deuda[0]['nombre_plan']
                                             ?? 'Plan',
                        'sesiones'         => $sesiones_deuda,
                        'aparatologia'     => $ap_deuda,
                        'servicios'        => $srv_deuda,
                    ];
                }
            }

            return [
                'success'           => true,
                'citas'             => $citas,
                'deudas_anteriores' => $deudas,
            ];
        } catch (PDOException $e) {
            error_log("detalleCobro: " . $e->getMessage());
            return ['success' => false, 'mensaje' => $e->getMessage()];
        }
    }

    // ── Registrar pagos (múltiples conceptos en una operación) ────
    public function registrarPagos($pagos, $id_paciente, $registrado_por)
    {
        try {
            $this->con->beginTransaction();

            $stmt = $this->con->prepare("
                INSERT INTO pagos
                    (id_paciente, id_cita, id_paciente_plan, monto,
                     tipo_pago, id_sesion, id_aparatologia_item,
                     id_cita_servicio, observacion, registrado_por)
                VALUES
                    (:pac, :cita, :pp, :monto,
                     :tipo, :sesion, :ap_item,
                     :srv_item, :obs, :reg)
            ");

            foreach ($pagos as $p) {
                $monto = floatval($p['monto']);
                if ($monto <= 0) continue;

                $stmt->execute([
                    ':pac'      => $id_paciente,
                    ':cita'     => $p['id_cita']              ?? null,
                    ':pp'       => $p['id_paciente_plan']     ?? null,
                    ':monto'    => $monto,
                    ':tipo'     => $p['tipo_pago'],
                    ':sesion'   => $p['id_sesion']            ?? null,
                    ':ap_item'  => $p['id_aparatologia_item'] ?? null,
                    ':srv_item' => $p['id_cita_servicio']     ?? null,
                    ':obs'      => $p['observacion']          ?? null,
                    ':reg'      => $registrado_por,
                ]);

                // Actualizar total_pagado en plan_sesiones
                if ($p['tipo_pago'] === 'sesion' && !empty($p['id_sesion'])) {
                    $this->con->prepare("
                        UPDATE plan_sesiones
                        SET total_pagado = total_pagado + :monto
                        WHERE id_sesion = :id
                    ")->execute([':monto' => $monto, ':id' => $p['id_sesion']]);
                }
            }

            $this->con->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->con->rollBack();
            error_log("registrarPagos: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al registrar pagos'];
        }
    }

    // ── Resumen de saldos del paciente ────────────────────────────
    public function resumenSaldos($id_paciente)
    {
        try {
            // Servicios sueltos
            $sqlSrv = "
                SELECT
                    COALESCE(SUM(cs.subtotal), 0) AS total,
                    COALESCE(SUM(COALESCE((
                        SELECT SUM(pg.monto) FROM pagos pg
                        WHERE pg.id_cita_servicio = cs.id_cita_servicio
                          AND pg.tipo_pago = 'total'
                    ), 0)), 0) AS pagado
                FROM cita_servicios cs
                INNER JOIN citas c           ON cs.id_cita = c.id_cita
                INNER JOIN informe_atencion ia ON ia.id_cita = cs.id_cita
                WHERE c.id_paciente = :id
                  AND ia.estado = 'enviado'
                  AND ia.id_paciente_plan IS NULL";

            $s = $this->con->prepare($sqlSrv);
            $s->execute([':id' => $id_paciente]);
            $resSrv = $s->fetch(PDO::FETCH_ASSOC);

            // Planes
            $sqlPlanes = "
                SELECT
                    pp.id_paciente_plan, pt.nombre_plan,
                    pp.tipo, pp.costo_acordado, pp.cuota_inicial,
                    COALESCE((SELECT SUM(monto) FROM pagos
                              WHERE id_paciente_plan = pp.id_paciente_plan
                                AND tipo_pago = 'cuota_inicial'), 0) AS pagado_cuota,
                    COALESCE((SELECT SUM(pa.precio_acordado * COALESCE(pa.cantidad,1))
                              FROM paciente_plan_aparatologia pa
                              WHERE pa.id_paciente_plan = pp.id_paciente_plan
                                AND pa.incluida_en_costo = 0), 0) AS total_ap_sep,
                    COALESCE((SELECT SUM(pg.monto) FROM pagos pg
                              INNER JOIN paciente_plan_aparatologia pa
                                      ON pg.id_aparatologia_item = pa.id
                              WHERE pa.id_paciente_plan = pp.id_paciente_plan
                                AND pg.tipo_pago = 'aparatologia'), 0) AS pagado_ap_sep,
                    COALESCE((SELECT SUM(costo_sesion) FROM plan_sesiones
                              WHERE id_paciente_plan = pp.id_paciente_plan), 0) AS total_sesiones,
                    COALESCE((SELECT SUM(monto) FROM pagos
                              WHERE id_paciente_plan = pp.id_paciente_plan
                                AND tipo_pago = 'sesion'), 0) AS pagado_sesiones
                FROM paciente_planes pp
                INNER JOIN planes_tratamiento pt ON pp.id_plan = pt.id_plan
                WHERE pp.id_paciente = :id
                ORDER BY pp.fecha_inicio ASC";

            $s = $this->con->prepare($sqlPlanes);
            $s->execute([':id' => $id_paciente]);
            $planes = $s->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'servicios' => $resSrv, 'planes' => $planes];
        } catch (PDOException $e) {
            error_log("resumenSaldos: " . $e->getMessage());
            return ['success' => false];
        }
    }
}