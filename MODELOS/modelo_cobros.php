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
                    p.nro_documento AS nro_documento_paciente,
                    td.abreviatura AS tipo_documento_abrev,
                    COUNT(DISTINCT ia.id_informe) AS total_informes,
                    MAX(ia.fecha_envio) AS ultimo_envio
                FROM informe_atencion ia
                INNER JOIN citas     c ON ia.id_cita    = c.id_cita
                INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
                LEFT  JOIN tipo_documento td ON p.id_tipo_documento = td.id_tipo_documento
                WHERE ia.estado = 'enviado'
                GROUP BY p.id_paciente, p.nombre, p.apellido, p.nro_documento, td.abreviatura
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
    // ═════════════════════════════════════════════════════════════
    // PAGOS: metodo, comprobante, anulacion por movimiento inverso y anticipos
    //
    //  - Un pago NUNCA se borra ni se edita. Anular = registrar un movimiento INVERSO (monto negativo)
    //    del mismo pago, con su motivo. Asi todas las sumas por cita/plan/paciente se corrigen solas.
    //  - Si se paga mas de lo que se debe, lo aplicable va a la deuda y el EXCEDENTE queda como
    //    "anticipo" (tipo_pago = 'anticipo', sin servicio): saldo a favor del paciente.
    //  - Pagar con saldo a favor = un movimiento -X de anticipo + un movimiento +X en la deuda (no es dinero nuevo).
    //  - Todo lo registrado en una misma accion comparte un id_operacion (se anula completo).
    // ═════════════════════════════════════════════════════════════
    const TIPOS_COBRO = ['total', 'cuota_inicial', 'sesion', 'aparatologia'];
    const MONTO_MAXIMO = 9999999.99;

    private function r2($n) { return round((float)$n, 2); }

    /** Metodos que el usuario puede elegir (el de sistema "Saldo a favor" no aparece) */
    public function metodosPago($soloActivos = true)
    {
        $sql = "SELECT id_metodo_pago, codigo, nombre, requiere_referencia
                FROM metodo_pago WHERE es_sistema = 0" . ($soloActivos ? " AND activo = 1" : "") . "
                ORDER BY codigo, nombre";
        return $this->con->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Saldo a favor del paciente = anticipos recibidos - anticipos aplicados (neto, incluye anulaciones) */
    public function saldoAFavor($id_paciente)
    {
        $s = $this->con->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE id_paciente = :p AND tipo_pago = 'anticipo'");
        $s->execute([':p' => $id_paciente]);
        return max(0, $this->r2($s->fetchColumn()));
    }

    private function metodoSistema()
    {
        $m = $this->con->query("SELECT * FROM metodo_pago WHERE codigo = 'SALDO' AND es_sistema = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$m) throw new RuntimeException('Falta el método de pago del sistema (SALDO). Ejecute el paso F2.');
        return $m;
    }

    private function validarMetodo($idMetodo, $comprobante)
    {
        $s = $this->con->prepare("SELECT * FROM metodo_pago WHERE id_metodo_pago = :id AND es_sistema = 0 AND activo = 1");
        $s->execute([':id' => (int)$idMetodo]);
        $m = $s->fetch(PDO::FETCH_ASSOC);
        if (!$m) throw new DomainException('Seleccione un método de pago válido');
        if ((int)$m['requiere_referencia'] === 1 && $comprobante === '') {
            throw new DomainException('El método «' . $m['nombre'] . '» exige el número de operación o comprobante');
        }
        return $m;
    }

    private function limpiarComprobante($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (mb_strlen($v) > 40) throw new DomainException('El número de comprobante admite máximo 40 caracteres');
        if (strpbrk($v, '<>') !== false) throw new DomainException('El número de comprobante no puede contener < ni >');
        return $v;
    }

    private function validarMonto($v, $etiqueta = 'El monto')
    {
        if (!is_numeric($v)) throw new DomainException($etiqueta . ' no es válido');
        $m = $this->r2($v);
        if ($m <= 0) return 0.0;
        if ($m > self::MONTO_MAXIMO) throw new DomainException($etiqueta . ' es demasiado grande');
        return $m;
    }

    /**
     * Busca lo que se esta pagando (bloqueando la fila), comprueba que sea DEL PACIENTE y calcula lo que falta cobrar.
     * Mismas definiciones que usa detalleCobro. Devuelve ['pendiente' => x, 'campos' => [...ids]]
     */
    private function resolverObjetivo(array $it, $idPaciente)
    {
        $tipo = $it['tipo_pago'] ?? '';
        if (!in_array($tipo, self::TIPOS_COBRO, true)) throw new DomainException('Tipo de cobro no válido');

        if ($tipo === 'total') {
            $id = (int)($it['id_cita_servicio'] ?? 0);
            $s = $this->con->prepare("SELECT cs.subtotal AS debido, cs.id_cita, c.id_paciente
                                      FROM cita_servicios cs INNER JOIN citas c ON c.id_cita = cs.id_cita
                                      WHERE cs.id_cita_servicio = :id FOR UPDATE");
            $s->execute([':id' => $id]);
            $f = $s->fetch(PDO::FETCH_ASSOC);
            $pag = $this->con->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE id_cita_servicio = :id AND tipo_pago = 'total'");
            $campos = ['id_cita_servicio' => $id, 'id_cita' => $f['id_cita'] ?? null];
        } elseif ($tipo === 'sesion') {
            $id = (int)($it['id_sesion'] ?? 0);
            $s = $this->con->prepare("SELECT ps.costo_sesion AS debido, ps.id_cita, ps.id_paciente_plan, pp.id_paciente
                                      FROM plan_sesiones ps INNER JOIN paciente_planes pp ON pp.id_paciente_plan = ps.id_paciente_plan
                                      WHERE ps.id_sesion = :id FOR UPDATE");
            $s->execute([':id' => $id]);
            $f = $s->fetch(PDO::FETCH_ASSOC);
            $pag = $this->con->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE id_sesion = :id AND tipo_pago = 'sesion'");
            $campos = ['id_sesion' => $id, 'id_cita' => $f['id_cita'] ?? null, 'id_paciente_plan' => $f['id_paciente_plan'] ?? null];
        } elseif ($tipo === 'aparatologia') {
            $id = (int)($it['id_aparatologia_item'] ?? 0);
            $s = $this->con->prepare("SELECT (pa.precio_acordado * COALESCE(pa.cantidad,1)) AS debido, pa.id_cita, pa.id_paciente_plan, pp.id_paciente
                                      FROM paciente_plan_aparatologia pa INNER JOIN paciente_planes pp ON pp.id_paciente_plan = pa.id_paciente_plan
                                      WHERE pa.id = :id AND pa.incluida_en_costo = 0 FOR UPDATE");
            $s->execute([':id' => $id]);
            $f = $s->fetch(PDO::FETCH_ASSOC);
            $pag = $this->con->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE id_aparatologia_item = :id AND tipo_pago = 'aparatologia'");
            $campos = ['id_aparatologia_item' => $id, 'id_cita' => $f['id_cita'] ?? null, 'id_paciente_plan' => $f['id_paciente_plan'] ?? null];
        } else {   // cuota_inicial: a nivel de plan
            $id = (int)($it['id_paciente_plan'] ?? 0);
            $s = $this->con->prepare("SELECT cuota_inicial AS debido, id_paciente FROM paciente_planes WHERE id_paciente_plan = :id FOR UPDATE");
            $s->execute([':id' => $id]);
            $f = $s->fetch(PDO::FETCH_ASSOC);
            $pag = $this->con->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE id_paciente_plan = :id AND tipo_pago = 'cuota_inicial'");
            // la cita (opcional) solo se acepta si es del mismo paciente
            $idCita = (int)($it['id_cita'] ?? 0) ?: null;
            if ($idCita) {
                $c = $this->con->prepare("SELECT COUNT(*) FROM citas WHERE id_cita = :c AND id_paciente = :p");
                $c->execute([':c' => $idCita, ':p' => $idPaciente]);
                if (!$c->fetchColumn()) $idCita = null;
            }
            $campos = ['id_paciente_plan' => $id, 'id_cita' => $idCita];
        }
        if (!$f || (int)$f['id_paciente'] !== (int)$idPaciente) {
            throw new DomainException('Uno de los conceptos a cobrar no existe o no pertenece a este paciente');
        }
        $pag->execute([':id' => $id]);
        return ['pendiente' => $this->r2($f['debido'] - $pag->fetchColumn()), 'campos' => $campos];
    }

    private function siguienteOperacion()
    {
        return (int)$this->con->query("SELECT COALESCE(MAX(id_operacion), 0) + 1 FROM pagos FOR UPDATE")->fetchColumn();
    }

    private function insertarPago(array $d)
    {
        $d += ['id_cita' => null, 'id_paciente_plan' => null, 'id_sesion' => null, 'id_aparatologia_item' => null, 'id_cita_servicio' => null,
               'observacion' => null, 'concepto' => null, 'nro_comprobante' => null, 'origen' => 'sistema', 'id_pago_anulado' => null,
               'anulado' => 0, 'motivo_anulacion' => null, 'id_par' => null];
        $this->con->prepare("
            INSERT INTO pagos (id_paciente, id_cita, id_paciente_plan, monto, tipo_pago, id_sesion, id_aparatologia_item, id_cita_servicio,
                               observacion, concepto, registrado_por, fecha_pago, fecha_registro, id_metodo_pago, nro_comprobante, origen, id_operacion,
                               id_pago_anulado, anulado, motivo_anulacion, id_par)
            VALUES (:id_paciente, :id_cita, :id_paciente_plan, :monto, :tipo_pago, :id_sesion, :id_aparatologia_item, :id_cita_servicio,
                    :observacion, :concepto, :registrado_por, :fecha_pago, :fecha_registro, :id_metodo_pago, :nro_comprobante, :origen, :id_operacion,
                    :id_pago_anulado, :anulado, :motivo_anulacion, :id_par)
        ")->execute([
            // la fecha de registro la pone PHP (hora de Lima, como el resto del sistema), no el reloj de la base de datos
            ':fecha_registro' => date('Y-m-d H:i:s'),
            ':id_paciente' => $d['id_paciente'], ':id_cita' => $d['id_cita'], ':id_paciente_plan' => $d['id_paciente_plan'], ':monto' => $d['monto'],
            ':tipo_pago' => $d['tipo_pago'], ':id_sesion' => $d['id_sesion'], ':id_aparatologia_item' => $d['id_aparatologia_item'],
            ':id_cita_servicio' => $d['id_cita_servicio'], ':observacion' => $d['observacion'], ':concepto' => $d['concepto'],
            ':registrado_por' => $d['registrado_por'], ':fecha_pago' => $d['fecha_pago'], ':id_metodo_pago' => $d['id_metodo_pago'],
            ':nro_comprobante' => $d['nro_comprobante'], ':origen' => $d['origen'], ':id_operacion' => $d['id_operacion'],
            ':id_pago_anulado' => $d['id_pago_anulado'], ':anulado' => $d['anulado'], ':motivo_anulacion' => $d['motivo_anulacion'], ':id_par' => $d['id_par'],
        ]);
        return (int)$this->con->lastInsertId();
    }

    /**
     * Graba UNA operacion de cobro (dentro de la transaccion abierta por quien llama).
     * $lineas = [ ['tipo_pago', 'campos' => [...], 'monto', 'pendiente', 'observacion'], ... ]
     * $cab = ['id_metodo_pago', 'nro_comprobante', 'fecha_pago' (AAAA-MM-DD), 'usar_saldo' (bool), 'anticipo' (monto extra)]
     */
    private function grabarOperacion($idPaciente, $usuario, array $cab, array $lineas)
    {
        $usarSaldo   = !empty($cab['usar_saldo']);
        $comprobante = $usarSaldo ? '' : $this->limpiarComprobante($cab['nro_comprobante'] ?? '');
        $metodo      = $usarSaldo ? $this->metodoSistema() : $this->validarMetodo($cab['id_metodo_pago'] ?? 0, $comprobante);
        $anticipoExtra = $this->validarMonto($cab['anticipo'] ?? 0, 'El adelanto');

        // Fecha del pago: hoy por defecto; una fecha pasada se marca "retroactivo" (el permiso lo exige quien llama)
        $fecha = trim((string)($cab['fecha_pago'] ?? ''));
        $origen = 'sistema'; $fechaPago = date('Y-m-d H:i:s');
        if ($fecha !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$d || $d->format('Y-m-d') !== $fecha) throw new DomainException('La fecha del pago no es válida');
            if ($fecha > date('Y-m-d')) throw new DomainException('La fecha del pago no puede ser futura');
            if ($fecha < date('Y-m-d')) { $origen = 'retroactivo'; $fechaPago = $fecha . ' ' . date('H:i:s'); }
        }
        if ($usarSaldo && $origen === 'retroactivo') throw new DomainException('El saldo a favor solo se puede aplicar con fecha de hoy');

        $idOp = $this->siguienteOperacion();
        $base = ['id_paciente' => $idPaciente, 'registrado_por' => $usuario, 'fecha_pago' => $fechaPago, 'id_metodo_pago' => (int)$metodo['id_metodo_pago'],
                 'nro_comprobante' => $comprobante !== '' ? $comprobante : null, 'origen' => $origen, 'id_operacion' => $idOp];

        $aplicado = []; $exceso = 0.0;
        foreach ($lineas as $ln) {
            $monto = $this->r2($ln['monto']);
            $aplicar = $this->r2(min($monto, max($ln['pendiente'], 0)));
            $exceso += $this->r2($monto - $aplicar);
            if ($aplicar < 0.005) continue;
            $id = $this->insertarPago($base + $ln['campos'] + ['monto' => $aplicar, 'tipo_pago' => $ln['tipo_pago'], 'observacion' => $ln['observacion'] ?? null]);
            $aplicado[] = ['id' => $id, 'monto' => $aplicar, 'tipo' => $ln['tipo_pago'], 'sesion' => $ln['campos']['id_sesion'] ?? null];
            if ($ln['tipo_pago'] === 'sesion' && !empty($ln['campos']['id_sesion'])) {
                $this->con->prepare("UPDATE plan_sesiones SET total_pagado = total_pagado + :m WHERE id_sesion = :id")
                          ->execute([':m' => $aplicar, ':id' => $ln['campos']['id_sesion']]);
            }
        }
        $anticipo = $this->r2($exceso + $anticipoExtra);
        $totalAplicado = $this->r2(array_sum(array_column($aplicado, 'monto')));

        if ($usarSaldo) {
            if ($anticipo >= 0.005) throw new DomainException('Con el saldo a favor solo se puede pagar lo que se debe (sin sobrante)');
            $saldo = $this->saldoAFavor($idPaciente);
            if ($totalAplicado > $saldo + 0.004) throw new DomainException('El saldo a favor disponible (S/ ' . number_format($saldo, 2) . ') no alcanza para este cobro');
            foreach ($aplicado as $a) {                  // por cada cobro aplicado, un movimiento -X de anticipo (se compensan, no es dinero nuevo)
                $idA = $this->insertarPago($base + ['monto' => -$a['monto'], 'tipo_pago' => 'anticipo', 'id_par' => $a['id']]);
                $this->con->prepare("UPDATE pagos SET id_par = :a WHERE id_pago = :b")->execute([':a' => $idA, ':b' => $a['id']]);
            }
        } elseif ($anticipo >= 0.005) {
            $this->insertarPago($base + ['monto' => $anticipo, 'tipo_pago' => 'anticipo', 'concepto' => 'Anticipo (saldo a favor)']);
        }
        if (!$aplicado && $anticipo < 0.005) throw new DomainException('No hay montos para registrar');

        return ['id_operacion' => $idOp, 'aplicado' => $totalAplicado, 'anticipo' => $usarSaldo ? 0.0 : $anticipo,
                'origen' => $origen, 'metodo' => $metodo['nombre'], 'saldo_a_favor' => $this->saldoAFavor($idPaciente)];
    }

    // ── Registrar pagos desde la pantalla de Cobros ──────────────
    public function registrarPagos($pagos, $id_paciente, $registrado_por, array $cab = [])
    {
        try {
            $this->con->beginTransaction();
            $lineas = [];
            foreach ((array)$pagos as $p) {
                $monto = $this->validarMonto($p['monto'] ?? 0);
                if ($monto <= 0) continue;
                $o = $this->resolverObjetivo((array)$p, $id_paciente);
                $obs = isset($p['observacion']) ? trim((string)$p['observacion']) : '';
                if (strpbrk($obs, '<>') !== false || mb_strlen($obs) > 255) throw new DomainException('La observación no es válida (máx. 255 caracteres, sin < ni >)');
                $lineas[] = ['tipo_pago' => $p['tipo_pago'], 'campos' => $o['campos'], 'monto' => $monto, 'pendiente' => $o['pendiente'], 'observacion' => $obs !== '' ? $obs : null];
            }
            $r = $this->grabarOperacion($id_paciente, $registrado_por, $cab, $lineas);
            $this->con->commit();
            return ['success' => true] + $r;
        } catch (DomainException $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            return ['success' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            error_log("registrarPagos: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al registrar pagos (no se guardó nada)'];
        }
    }

    // ── Registrar un pago a cuenta de una cita (pantalla de Atenciones) ──
    public function registrarPagoCita($id_paciente, $id_cita, $monto, $observacion, $registrado_por, array $cab = [])
    {
        try {
            $this->con->beginTransaction();
            $monto = $this->validarMonto($monto);
            if ($monto <= 0) throw new DomainException('El monto debe ser mayor a 0');
            $s = $this->con->prepare("SELECT id_cita FROM citas WHERE id_cita = :c AND id_paciente = :p FOR UPDATE");
            $s->execute([':c' => (int)$id_cita, ':p' => $id_paciente]);
            if (!$s->fetchColumn()) throw new DomainException('La cita no existe o no pertenece a este paciente');
            $debido = $this->con->prepare("SELECT COALESCE(SUM(subtotal),0) FROM cita_servicios WHERE id_cita = :c");
            $debido->execute([':c' => (int)$id_cita]);
            $pagado = $this->con->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE id_cita = :c");
            $pagado->execute([':c' => (int)$id_cita]);
            $obs = trim((string)$observacion);
            if (strpbrk($obs, '<>') !== false || mb_strlen($obs) > 255) throw new DomainException('La observación no es válida (máx. 255 caracteres, sin < ni >)');
            $lineas = [['tipo_pago' => 'total', 'campos' => ['id_cita' => (int)$id_cita], 'monto' => $monto,
                        'pendiente' => $this->r2($debido->fetchColumn() - $pagado->fetchColumn()), 'observacion' => $obs !== '' ? $obs : null]];
            $r = $this->grabarOperacion($id_paciente, $registrado_por, $cab, $lineas);
            $this->con->commit();
            return ['success' => true] + $r;
        } catch (DomainException $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            return ['success' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            error_log("registrarPagoCita: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'Error al registrar el pago (no se guardó nada)'];
        }
    }

    // ── Anular una operacion completa (movimiento inverso) ───────
    public function anularOperacion($id_operacion, $motivo, $usuario)
    {
        try {
            $motivo = trim((string)$motivo);
            if (mb_strlen($motivo) < 5) throw new DomainException('Escriba el motivo de la anulación (mínimo 5 caracteres)');
            if (mb_strlen($motivo) > 255 || strpbrk($motivo, '<>') !== false) throw new DomainException('El motivo no es válido (máx. 255 caracteres, sin < ni >)');

            $this->con->beginTransaction();
            $s = $this->con->prepare("SELECT * FROM pagos WHERE id_operacion = :o AND id_pago_anulado IS NULL ORDER BY id_pago FOR UPDATE");
            $s->execute([':o' => (int)$id_operacion]);
            $filas = $s->fetchAll(PDO::FETCH_ASSOC);
            if (!$filas) throw new DomainException('La operación no existe');
            $vigentes = array_values(array_filter($filas, fn($f) => (int)$f['anulado'] === 0));
            if (!$vigentes) throw new DomainException('Esta operación ya fue anulada');
            $idPac = (int)$filas[0]['id_paciente'];

            // El saldo a favor nunca puede quedar negativo (si ya se uso en otros cobros, primero se anulan esos)
            $cambioSaldo = 0.0;
            foreach ($vigentes as $f) if ($f['tipo_pago'] === 'anticipo') $cambioSaldo -= (float)$f['monto'];
            $saldoActual = $this->r2($this->con->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE id_paciente = {$idPac} AND tipo_pago = 'anticipo'")->fetchColumn());
            if ($this->r2($saldoActual + $cambioSaldo) < -0.004) {
                throw new DomainException('No se puede anular: el saldo a favor de este pago ya se usó en otros cobros. Anule primero esos cobros.');
            }

            $ahora = date('Y-m-d H:i:s'); $totalRevertido = 0.0;
            foreach ($vigentes as $f) {
                $this->insertarPago([
                    'id_paciente' => $idPac, 'id_cita' => $f['id_cita'], 'id_paciente_plan' => $f['id_paciente_plan'], 'monto' => -(float)$f['monto'],
                    'tipo_pago' => $f['tipo_pago'], 'id_sesion' => $f['id_sesion'], 'id_aparatologia_item' => $f['id_aparatologia_item'],
                    'id_cita_servicio' => $f['id_cita_servicio'], 'observacion' => 'Anulación', 'concepto' => $f['concepto'],
                    'registrado_por' => $usuario, 'fecha_pago' => $ahora, 'id_metodo_pago' => $f['id_metodo_pago'], 'nro_comprobante' => $f['nro_comprobante'],
                    'origen' => 'sistema', 'id_operacion' => (int)$id_operacion, 'id_pago_anulado' => (int)$f['id_pago'], 'motivo_anulacion' => $motivo,
                ]);
                $this->con->prepare("UPDATE pagos SET anulado = 1, motivo_anulacion = :m WHERE id_pago = :id")->execute([':m' => $motivo, ':id' => $f['id_pago']]);
                if ($f['tipo_pago'] === 'sesion' && !empty($f['id_sesion'])) {
                    $this->con->prepare("UPDATE plan_sesiones SET total_pagado = total_pagado - :m WHERE id_sesion = :id")
                              ->execute([':m' => (float)$f['monto'], ':id' => $f['id_sesion']]);
                }
                if ((float)$f['monto'] > 0 && $f['tipo_pago'] !== 'anticipo') $totalRevertido += (float)$f['monto'];
            }
            $this->con->commit();
            return ['success' => true, 'id_paciente' => $idPac, 'lineas' => count($vigentes), 'monto' => $this->r2($totalRevertido),
                    'saldo_a_favor' => $this->saldoAFavor($idPac)];
        } catch (DomainException $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            return ['success' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            error_log("anularOperacion: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo anular (no se cambió nada)'];
        }
    }

    // ── Historial de pagos del paciente, agrupado por operacion ──
    public function historialPagos($id_paciente)
    {
        try {
            $s = $this->con->prepare("
                SELECT pg.id_pago, pg.id_operacion, pg.monto, pg.tipo_pago, pg.fecha_pago, pg.fecha_registro, pg.origen, pg.anulado,
                       pg.id_pago_anulado, pg.motivo_anulacion, pg.nro_comprobante, pg.observacion,
                       mp.nombre AS metodo, CONCAT(u.nombre, ' ', u.apellidos) AS registrado_por_nombre,
                       CASE pg.tipo_pago
                            WHEN 'total'         THEN COALESCE(ts.nombre_servicio, 'Servicio')
                            WHEN 'sesion'        THEN CONCAT('Sesión ', COALESCE(ps.numero_sesion, ''))
                            WHEN 'cuota_inicial' THEN 'Cuota inicial'
                            WHEN 'aparatologia'  THEN COALESCE(pa.descripcion, 'Aparatología')
                            ELSE 'Anticipo (saldo a favor)'
                       END AS concepto_texto
                FROM pagos pg
                LEFT  JOIN metodo_pago mp ON mp.id_metodo_pago = pg.id_metodo_pago
                INNER JOIN usuarios u     ON u.id_usuario = pg.registrado_por
                LEFT  JOIN cita_servicios cs ON cs.id_cita_servicio = pg.id_cita_servicio
                LEFT  JOIN tipo_servicio ts  ON ts.id_tipo_servicio = cs.id_tipo_servicio
                LEFT  JOIN plan_sesiones ps  ON ps.id_sesion = pg.id_sesion
                LEFT  JOIN paciente_plan_aparatologia pa ON pa.id = pg.id_aparatologia_item
                WHERE pg.id_paciente = :id
                ORDER BY pg.id_operacion DESC, pg.id_pago ASC");
            $s->execute([':id' => $id_paciente]);
            $ops = [];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $k = (int)$f['id_operacion'];
                if (!isset($ops[$k])) $ops[$k] = ['id_operacion' => $k, 'fecha_pago' => $f['fecha_pago'], 'registrado_por' => $f['registrado_por_nombre'],
                                                  'metodo' => $f['metodo'] ?: 'No registrado', 'comprobante' => $f['nro_comprobante'], 'origen' => $f['origen'],
                                                  'total' => 0.0, 'originales' => 0, 'anuladas' => 0, 'motivo_anulacion' => null, 'lineas' => []];
                $esInversa = $f['id_pago_anulado'] !== null;
                if (!$esInversa) {
                    $ops[$k]['originales']++;
                    if ((int)$f['anulado'] === 1) { $ops[$k]['anuladas']++; $ops[$k]['motivo_anulacion'] = $f['motivo_anulacion']; }
                    if ((float)$f['monto'] > 0) $ops[$k]['total'] += (float)$f['monto'];
                }
                $ops[$k]['lineas'][] = ['concepto' => $f['concepto_texto'], 'monto' => (float)$f['monto'], 'inversa' => $esInversa, 'anulada' => (int)$f['anulado'] === 1];
            }
            foreach ($ops as &$o) {
                $o['total'] = $this->r2($o['total']);
                $o['estado'] = ($o['originales'] > 0 && $o['anuladas'] === $o['originales']) ? 'anulado' : 'vigente';
                unset($o['originales'], $o['anuladas']);
            }
            unset($o);
            return ['success' => true, 'operaciones' => array_values($ops), 'saldo_a_favor' => $this->saldoAFavor($id_paciente)];
        } catch (PDOException $e) {
            error_log("historialPagos: " . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo cargar el historial'];
        }
    }

    /**
     * Cuotas iniciales de plan que todavia faltan por cobrar. Es una obligacion DEL PLAN (no de una cita),
     * asi que se ofrece en la pestaña Cobro sin depender de la cita y sin bloquearla.
     */
    public function cuotasPendientes($id_paciente)
    {
        try {
            $s = $this->con->prepare("
                SELECT pp.id_paciente_plan, pt.nombre_plan, pp.cuota_inicial,
                       COALESCE((SELECT SUM(pg.monto) FROM pagos pg
                                 WHERE pg.id_paciente_plan = pp.id_paciente_plan AND pg.tipo_pago = 'cuota_inicial'), 0) AS pagado
                FROM paciente_planes pp
                INNER JOIN planes_tratamiento pt ON pt.id_plan = pp.id_plan
                WHERE pp.id_paciente = :id AND pp.estado = 'activo' AND pp.cuota_inicial > 0
                HAVING (pp.cuota_inicial - pagado) > 0.004
                ORDER BY pp.fecha_inicio ASC");
            $s->execute([':id' => $id_paciente]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("cuotasPendientes: " . $e->getMessage());
            return [];
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

            return ['success' => true, 'servicios' => $resSrv, 'planes' => $planes, 'saldo_a_favor' => $this->saldoAFavor($id_paciente)];
        } catch (PDOException $e) {
            error_log("resumenSaldos: " . $e->getMessage());
            return ['success' => false];
        }
    }
}