<?php
require_once __DIR__ . '/../CONFIG/conexion.php';
require_once __DIR__ . '/../CONFIG/plan_costos.php';

/**
 * Ficha del plan de tratamiento: resumen (contadores, avance, alertas), linea de tiempo de sesiones y edicion.
 *  - Lo NO economico (notas, fecha estimada de fin, estado) se edita libremente.
 *  - Lo economico (tipo, costos, cuotas, cuota inicial) exige permiso aparte y un motivo; solo recalcula las sesiones
 *    que TODAVIA NO tienen pagos (los pagos ya hechos nunca se alteran).
 */
class ModeloPlanes {

    const FINANCIEROS = ['tipo', 'costo_acordado', 'en_cuotas', 'sesiones_pago_est', 'costo_estimado_sesion', 'cuota_inicial'];
    const GENERALES   = ['notas', 'fecha_fin_est', 'estado'];
    const ESTADOS     = ['activo', 'completado', 'cancelado'];

    private $con;

    public function __construct() {
        $this->con = (new Conexion())->getConexion();
    }

    private function m($x) { return 'S/ ' . number_format((float)$x, 2); }

    public function obtenerPlan($idPlan, $bloquear = false) {
        $s = $this->con->prepare("
            SELECT pp.*, pt.nombre_plan, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_doctor
            FROM paciente_planes pp
            INNER JOIN planes_tratamiento pt ON pt.id_plan = pp.id_plan
            INNER JOIN usuarios u ON u.id_usuario = pp.id_doctor
            WHERE pp.id_paciente_plan = :id" . ($bloquear ? ' FOR UPDATE' : ''));
        $s->execute([':id' => (int)$idPlan]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Datos crudos del plan: sesiones, aparatologia, pagos y citas ──
    private function cargar(array $pl) {
        $id = (int)$pl['id_paciente_plan'];
        $s = $this->con->prepare("
            SELECT ps.id_sesion, ps.numero_sesion, ps.id_cita, ps.costo_sesion, c.fecha, c.hora, c.origen, ec.estado AS estado_cita,
                   CONCAT(u.nombre, ' ', u.apellidos) AS doctor, ia.estado AS estado_informe,
                   COALESCE((SELECT SUM(monto) FROM pagos WHERE id_sesion = ps.id_sesion AND tipo_pago = 'sesion'), 0) AS pagado
            FROM plan_sesiones ps
            INNER JOIN citas c        ON c.id_cita = ps.id_cita
            INNER JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
            INNER JOIN usuarios u     ON u.id_usuario = c.id_doctor
            LEFT  JOIN informe_atencion ia ON ia.id_cita = ps.id_cita
            WHERE ps.id_paciente_plan = :id ORDER BY ps.numero_sesion ASC");
        $s->execute([':id' => $id]);
        $sesiones = $s->fetchAll(PDO::FETCH_ASSOC);

        $s = $this->con->prepare("
            SELECT pa.id, pa.id_cita, pa.descripcion, pa.cantidad, pa.precio_acordado, pa.incluida_en_costo,
                   COALESCE((SELECT SUM(monto) FROM pagos WHERE id_aparatologia_item = pa.id AND tipo_pago = 'aparatologia'), 0) AS pagado,
                   (SELECT estado FROM informe_atencion WHERE id_cita = pa.id_cita LIMIT 1) AS estado_informe
            FROM paciente_plan_aparatologia pa WHERE pa.id_paciente_plan = :id ORDER BY pa.id_cita, pa.id");
        $s->execute([':id' => $id]);
        $aparatologia = $s->fetchAll(PDO::FETCH_ASSOC);

        $s = $this->con->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE id_paciente_plan = :id AND tipo_pago = 'cuota_inicial'");
        $s->execute([':id' => $id]);
        $pagCuota = (float)$s->fetchColumn();

        $s = $this->con->prepare("
            SELECT COUNT(*) FROM (
                SELECT id_cita FROM plan_sesiones WHERE id_paciente_plan = :a
                UNION SELECT id_cita FROM paciente_plan_aparatologia WHERE id_paciente_plan = :b
                UNION SELECT id_cita FROM informe_atencion WHERE id_paciente_plan = :c) t");
        $s->execute([':a' => $id, ':b' => $id, ':c' => $id]);
        return ['sesiones' => $sesiones, 'aparatologia' => $aparatologia, 'pagado_cuota' => $pagCuota, 'citas' => (int)$s->fetchColumn()];
    }

    // ── Resumen: contadores, dinero, avance y alertas ──
    private function resumir(array $pl, array $d) {
        $ses = $d['sesiones']; $ap = $d['aparatologia'];
        $n = count($ses); $est = PlanCostos::sesionesEstimadas($pl);
        $costoTotalTipo = $pl['tipo'] === 'costo_total'; $enCuotas = $costoTotalTipo && (int)$pl['en_cuotas'];
        $costoSes = $pagSes = $porEnviar = $pendCob = 0.0; $borradores = 0;
        foreach ($ses as $x) {
            $costoSes += (float)$x['costo_sesion']; $pagSes += (float)$x['pagado'];
            if (($x['estado_informe'] ?? '') === 'enviado') $pendCob += max(0, (float)$x['costo_sesion'] - (float)$x['pagado']);
            else { $porEnviar += (float)$x['costo_sesion']; $borradores++; }
        }
        $apSep = $apInc = $pagAp = 0.0;
        foreach ($ap as $a) {
            $t = (float)$a['precio_acordado'] * (int)($a['cantidad'] ?: 1);
            if ((int)$a['incluida_en_costo']) { $apInc += $t; continue; }
            $apSep += $t; $pagAp += (float)$a['pagado'];
            if (($a['estado_informe'] ?? '') === 'enviado') $pendCob += max(0, $t - (float)$a['pagado']);
        }
        $cuotaIni = (float)$pl['cuota_inicial']; $pagCuota = $d['pagado_cuota'];
        $pendCob += max(0, $cuotaIni - $pagCuota);
        $pagadoTotal = $pagSes + $pagAp + $pagCuota;
        $acordado = $costoTotalTipo ? (float)$pl['costo_acordado'] : null;
        $adicionales = $enCuotas ? max(0, $n - PlanCostos::cuotas($pl)) : ($est && $n > $est ? $n - $est : 0);
        $exceso = $costoTotalTipo ? max(0, round($costoSes - (float)$pl['costo_acordado'], 2)) : 0.0;

        $alertas = [];
        if ($exceso > 0.005) $alertas[] = ['nivel' => 'peligro', 'accion' => 'recalcular',
            'texto' => "Las sesiones suman {$this->m($costoSes)} pero el costo acordado del plan es {$this->m($pl['costo_acordado'])}: hay {$this->m($exceso)} de más. Corrija los costos para no cobrar de más."];
        elseif ($adicionales > 0) $alertas[] = ['nivel' => 'info', 'accion' => null,
            'texto' => $enCuotas ? ($adicionales === 1 ? '1 sesión adicional: ya estaba cubierta por el costo acordado y no se cobra.'
                                                      : "$adicionales sesiones adicionales: ya estaban cubiertas por el costo acordado y no se cobran.")
                                 : "Se superaron las $est sesiones estimadas ($n realizadas). Se sigue cobrando por sesión."];
        if ($borradores > 0) $alertas[] = ['nivel' => 'info', 'accion' => null,
            'texto' => ($borradores === 1 ? '1 sesión tiene la atención en borrador: todavía no se envía a cobrar' : "$borradores sesiones tienen la atención en borrador: todavía no se envían a cobrar") . ($porEnviar > 0 ? " ({$this->m($porEnviar)} por enviar)." : '.')];
        if ($pl['estado'] === 'activo' && $pendCob <= 0.004 && $borradores === 0 && $n > 0) {
            $completo = $enCuotas ? ($n >= PlanCostos::cuotas($pl)) : ($est ? $n >= $est : false);
            if ($completo) $alertas[] = ['nivel' => 'ok', 'accion' => 'completar', 'texto' => 'Se completaron las sesiones previstas y no hay deuda. ¿Desea marcar el plan como completado?'];
        }

        return [
            'id_paciente_plan' => (int)$pl['id_paciente_plan'], 'id_paciente' => (int)$pl['id_paciente'], 'nombre_plan' => $pl['nombre_plan'], 'nombre_doctor' => $pl['nombre_doctor'],
            'estado' => $pl['estado'], 'tipo' => $pl['tipo'], 'en_cuotas' => (int)$pl['en_cuotas'], 'costo_acordado' => $pl['costo_acordado'], 'cuota_inicial' => $pl['cuota_inicial'],
            'sesiones_pago_est' => $pl['sesiones_pago_est'], 'costo_estimado_sesion' => $pl['costo_estimado_sesion'], 'fecha_inicio' => $pl['fecha_inicio'], 'fecha_fin_est' => $pl['fecha_fin_est'], 'notas' => $pl['notas'],
            'citas_asociadas' => $d['citas'], 'sesiones_realizadas' => $n, 'sesiones_borrador' => $borradores, 'sesiones_estimadas' => $est, 'sesiones_adicionales' => $adicionales,
            'dinero' => ['acordado' => $acordado, 'sesiones' => round($costoSes, 2), 'pagado_sesiones' => round($pagSes, 2), 'aparatologia' => round($apSep, 2), 'pagado_aparatologia' => round($pagAp, 2),
                         'aparatologia_incluida' => round($apInc, 2), 'cuota_inicial' => round($cuotaIni, 2), 'pagado_cuota' => round($pagCuota, 2), 'pagado_total' => round($pagadoTotal, 2),
                         'pendiente_cobrable' => round($pendCob, 2), 'por_enviar' => round($porEnviar, 2), 'exceso' => round($exceso, 2)],
            'alertas' => $alertas,
            'aviso_proxima' => PlanCostos::aviso($pl, $n + 1),
        ];
    }

    /** Planes del paciente con su resumen. Por defecto solo los activos. */
    public function listar($idPaciente, $todos = false) {
        $s = $this->con->prepare("
            SELECT pp.*, pt.nombre_plan, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_doctor
            FROM paciente_planes pp
            INNER JOIN planes_tratamiento pt ON pt.id_plan = pp.id_plan
            INNER JOIN usuarios u ON u.id_usuario = pp.id_doctor
            WHERE pp.id_paciente = :p" . ($todos ? '' : " AND pp.estado = 'activo'") . "
            ORDER BY (pp.estado = 'activo') DESC, pp.fecha_inicio DESC, pp.id_paciente_plan DESC");
        $s->execute([':p' => (int)$idPaciente]);
        $out = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $pl) $out[] = $this->resumir($pl, $this->cargar($pl));
        return $out;
    }

    /** Ficha completa: resumen + linea de tiempo de sesiones (con su aparatologia) */
    public function detalle($idPlan) {
        $pl = $this->obtenerPlan($idPlan);
        if (!$pl) return null;
        $d = $this->cargar($pl);
        $r = $this->resumir($pl, $d);
        $porCita = [];
        foreach ($d['aparatologia'] as $a) {
            $porCita[$a['id_cita']][] = ['descripcion' => $a['descripcion'], 'cantidad' => (int)$a['cantidad'], 'precio_acordado' => $a['precio_acordado'],
                                         'incluida' => (int)$a['incluida_en_costo'], 'pagado' => $a['pagado']];
        }
        $r['sesiones'] = array_map(fn($x) => [
            'numero' => (int)$x['numero_sesion'], 'id_cita' => (int)$x['id_cita'], 'fecha' => $x['fecha'], 'hora' => $x['hora'], 'doctor' => $x['doctor'],
            'estado_cita' => $x['estado_cita'], 'estado_informe' => $x['estado_informe'], 'origen' => $x['origen'], 'costo' => $x['costo_sesion'], 'pagado' => $x['pagado'],
            'adicional' => PlanCostos::esAdicional($pl, $x['numero_sesion']), 'aparatologia' => $porCita[$x['id_cita']] ?? [],
        ], $d['sesiones']);
        return $r;
    }

    // ───────────────── Detalle de UNA cita / sesion (para el "ojito") ─────────────────
    /** Cita + informe + sesion del plan + servicios + aparatologia + pagos. Solo lectura. */
    public function detalleCita($idCita) {
        $idCita = (int)$idCita;
        $s = $this->con->prepare("
            SELECT c.id_cita, c.id_paciente, c.fecha, c.hora, c.hora_fin, c.duracion_minutos, c.motivo, c.origen, ec.estado AS estado_cita,
                   CONCAT(u.nombre, ' ', u.apellidos) AS doctor, se.nombre_sede, ta.nombre AS tipo_atencion
            FROM citas c
            INNER JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
            INNER JOIN usuarios u     ON u.id_usuario = c.id_doctor
            LEFT  JOIN sedes se       ON se.id_sede_atencion = c.id_sede_atencion
            LEFT  JOIN tipos_atencion ta ON ta.id_tipo_atencion = c.id_tipo_atencion
            WHERE c.id_cita = :id");
        $s->execute([':id' => $idCita]);
        $cita = $s->fetch(PDO::FETCH_ASSOC);
        if (!$cita) return null;

        $s = $this->con->prepare("
            SELECT ia.id_informe, ia.estado, ia.motivo_consulta, ia.diagnostico, ia.tratamiento_realizado, ia.materiales, ia.observaciones,
                   ia.fecha_registro, ia.fecha_envio, pt.nombre_plan
            FROM informe_atencion ia LEFT JOIN planes_tratamiento pt ON pt.id_plan = ia.id_plan
            WHERE ia.id_cita = :id ORDER BY ia.id_informe DESC LIMIT 1");
        $s->execute([':id' => $idCita]);
        $informe = $s->fetch(PDO::FETCH_ASSOC) ?: null;

        $sesion = null;
        $s = $this->con->prepare("
            SELECT ps.id_sesion, ps.numero_sesion, ps.costo_sesion, ps.id_paciente_plan, pt.nombre_plan, pp.tipo, pp.en_cuotas, pp.sesiones_pago_est,
                   COALESCE((SELECT SUM(monto) FROM pagos WHERE id_sesion = ps.id_sesion AND tipo_pago = 'sesion'), 0) AS pagado
            FROM plan_sesiones ps
            INNER JOIN paciente_planes pp ON pp.id_paciente_plan = ps.id_paciente_plan
            INNER JOIN planes_tratamiento pt ON pt.id_plan = pp.id_plan
            WHERE ps.id_cita = :id LIMIT 1");
        $s->execute([':id' => $idCita]);
        if ($x = $s->fetch(PDO::FETCH_ASSOC)) {
            $sesion = ['id_paciente_plan' => (int)$x['id_paciente_plan'], 'numero' => (int)$x['numero_sesion'], 'nombre_plan' => $x['nombre_plan'], 'costo' => $x['costo_sesion'],
                       'pagado' => $x['pagado'], 'adicional' => PlanCostos::esAdicional($x, $x['numero_sesion'])];
        }

        $s = $this->con->prepare("
            SELECT ts.nombre_servicio AS nombre, cs.cantidad, cs.precio_unitario, cs.subtotal,
                   COALESCE((SELECT SUM(monto) FROM pagos WHERE id_cita_servicio = cs.id_cita_servicio AND tipo_pago = 'total'), 0) AS pagado
            FROM cita_servicios cs INNER JOIN tipo_servicio ts ON ts.id_tipo_servicio = cs.id_tipo_servicio
            WHERE cs.id_cita = :id ORDER BY cs.id_cita_servicio");
        $s->execute([':id' => $idCita]);
        $servicios = $s->fetchAll(PDO::FETCH_ASSOC);

        $s = $this->con->prepare("
            SELECT pa.descripcion, pa.cantidad, pa.precio_acordado, pa.incluida_en_costo AS incluida,
                   COALESCE((SELECT SUM(monto) FROM pagos WHERE id_aparatologia_item = pa.id AND tipo_pago = 'aparatologia'), 0) AS pagado
            FROM paciente_plan_aparatologia pa WHERE pa.id_cita = :id ORDER BY pa.id");
        $s->execute([':id' => $idCita]);
        $aparatologia = $s->fetchAll(PDO::FETCH_ASSOC);

        $s = $this->con->prepare("
            SELECT pg.fecha_pago, pg.monto, pg.tipo_pago, pg.origen, pg.nro_comprobante, pg.anulado, pg.id_pago_anulado, mp.nombre AS metodo,
                   CONCAT(ur.nombre, ' ', ur.apellidos) AS registrado_por,
                   CASE pg.tipo_pago
                        WHEN 'total'         THEN COALESCE(ts2.nombre_servicio, 'Servicio')
                        WHEN 'sesion'        THEN CONCAT('Sesión n.º ', COALESCE(ps2.numero_sesion, ''))
                        WHEN 'aparatologia'  THEN COALESCE(pa2.descripcion, 'Aparatología')
                        WHEN 'cuota_inicial' THEN 'Cuota inicial'
                        ELSE 'Adelanto (saldo a favor)' END AS concepto
            FROM pagos pg
            LEFT JOIN metodo_pago mp  ON mp.id_metodo_pago = pg.id_metodo_pago
            LEFT JOIN usuarios ur     ON ur.id_usuario = pg.registrado_por
            LEFT JOIN cita_servicios cs2 ON cs2.id_cita_servicio = pg.id_cita_servicio
            LEFT JOIN tipo_servicio ts2  ON ts2.id_tipo_servicio = cs2.id_tipo_servicio
            LEFT JOIN plan_sesiones ps2  ON ps2.id_sesion = pg.id_sesion
            LEFT JOIN paciente_plan_aparatologia pa2 ON pa2.id = pg.id_aparatologia_item
            WHERE pg.id_cita = :id ORDER BY pg.id_pago");
        $s->execute([':id' => $idCita]);
        $pagos = $s->fetchAll(PDO::FETCH_ASSOC);

        return ['cita' => $cita, 'informe' => $informe, 'sesion' => $sesion, 'servicios' => $servicios, 'aparatologia' => $aparatologia, 'pagos' => $pagos];
    }

    // ───────────────────────── Edicion ─────────────────────────
    private function texto($v, $campo, $max) {
        $v = trim((string)$v);
        if (mb_strlen($v) > $max) throw new DomainException("$campo es demasiado largo (máx. $max caracteres)");
        if (strpbrk($v, '<>') !== false) throw new DomainException("$campo no puede contener < ni >");
        return $v;
    }
    private function numero($v, $campo, $min, $max, $entero = false) {
        if (!is_numeric($v) || (float)$v < $min || (float)$v > $max || ($entero && (int)$v != $v)) throw new DomainException("$campo no es válido");
        return $entero ? (int)$v : round((float)$v, 2);
    }

    /** Valida los cambios y calcula la diferencia SIN escribir nada. Lanza DomainException si algo no es valido. */
    public function prepararEdicion($idPlan, array $cambios) {
        $pl = $this->obtenerPlan($idPlan);
        if (!$pl) throw new DomainException('El plan no existe');
        foreach (array_keys($cambios) as $k) {
            if (!in_array($k, array_merge(self::FINANCIEROS, self::GENERALES), true)) throw new DomainException('Campo no editable: ' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8'));
        }
        $nuevo = $pl;
        foreach ($cambios as $k => $v) {
            switch ($k) {
                case 'notas':          $t = $this->texto($v, 'Las notas', 1000); $nuevo[$k] = $t === '' ? null : $t; break;
                case 'fecha_fin_est':
                    if ($v === null || $v === '') { $nuevo[$k] = null; break; }
                    $f = DateTime::createFromFormat('Y-m-d', (string)$v);
                    if (!$f || $f->format('Y-m-d') !== $v) throw new DomainException('La fecha estimada de fin no es válida');
                    if ($v < $pl['fecha_inicio']) throw new DomainException('La fecha estimada de fin no puede ser anterior al inicio del plan');
                    $nuevo[$k] = $v; break;
                case 'estado':         if (!in_array($v, self::ESTADOS, true)) throw new DomainException('El estado no es válido'); $nuevo[$k] = $v; break;
                case 'tipo':           if (!in_array($v, ['por_sesion', 'costo_total'], true)) throw new DomainException('El tipo de cobro no es válido'); $nuevo[$k] = $v; break;
                case 'costo_acordado': $nuevo[$k] = ($v === null || $v === '') ? null : $this->numero($v, 'El costo acordado', 0, 9999999.99); break;
                case 'en_cuotas':      $nuevo[$k] = !empty($v) ? 1 : 0; break;
                case 'sesiones_pago_est': $nuevo[$k] = ($v === null || $v === '') ? null : $this->numero($v, 'El número de sesiones/cuotas', 1, 120, true); break;
                case 'costo_estimado_sesion': $nuevo[$k] = ($v === null || $v === '') ? null : $this->numero($v, 'El costo por sesión', 0, 99999.99); break;
                case 'cuota_inicial':  $nuevo[$k] = $this->numero($v === '' || $v === null ? 0 : $v, 'La cuota inicial', 0, 9999999.99); break;
            }
        }
        // coherencia entre campos
        if ($nuevo['tipo'] === 'por_sesion') { $nuevo['en_cuotas'] = 0; if ($nuevo['costo_estimado_sesion'] === null) throw new DomainException('Indique el costo por sesión'); }
        else {
            if ($nuevo['costo_acordado'] === null) throw new DomainException('Indique el costo acordado del plan');
            if ((int)$nuevo['en_cuotas'] && !PlanCostos::sesionesEstimadas($nuevo)) throw new DomainException('Indique en cuántas cuotas se paga el plan');
            if ((int)$nuevo['en_cuotas']) $nuevo['costo_estimado_sesion'] = round((float)$nuevo['costo_acordado'] / PlanCostos::cuotas($nuevo), 2);   // se mantiene al dia lo que muestra la ficha
        }
        $s = $this->con->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE id_paciente_plan = :id AND tipo_pago = 'cuota_inicial'");
        $s->execute([':id' => (int)$idPlan]);
        $pagCuota = (float)$s->fetchColumn();
        if ((float)$nuevo['cuota_inicial'] + 0.004 < $pagCuota) throw new DomainException('La cuota inicial no puede ser menor a lo ya pagado (' . $this->m($pagCuota) . ')');

        $iguales = fn($a, $b) => ($a === null || $b === null) ? ($a === $b) : (is_numeric($a) && is_numeric($b) ? abs((float)$a - (float)$b) < 0.005 : (string)$a === (string)$b);
        $diff = []; $financieros = []; $generales = [];
        foreach ($cambios as $k => $_) {
            if (!$iguales($pl[$k], $nuevo[$k])) {
                $diff[$k] = ['antes' => $pl[$k], 'despues' => $nuevo[$k]];
                if (in_array($k, self::FINANCIEROS, true)) $financieros[] = $k; else $generales[] = $k;
            }
        }
        // campos derivados que cambian por arrastre (no los pidio el usuario)
        $derivados = [];
        foreach (['en_cuotas', 'costo_estimado_sesion'] as $k) if (!isset($diff[$k]) && !$iguales($pl[$k], $nuevo[$k]) && $financieros) $derivados[$k] = $nuevo[$k];
        return ['plan' => $pl, 'nuevo' => $nuevo, 'diff' => $diff, 'financieros' => $financieros, 'generales' => $generales, 'derivados' => $derivados];
    }

    /** Escribe la edicion. $recalcular: si hubo cambios economicos, corrige el costo de las sesiones SIN pagos. */
    public function aplicarEdicion(array $prep, $motivo, $recalcular = true) {
        try {
            if (!$prep['diff'] && !$prep['derivados']) return ['success' => true, 'cambios' => 0];
            $motivo = trim((string)$motivo);
            if (($prep['financieros'] || isset($prep['diff']['estado'])) && mb_strlen($motivo) < 5) throw new DomainException('Indique el motivo del cambio (mínimo 5 caracteres)');
            if (strpbrk($motivo, '<>') !== false) throw new DomainException('El motivo no puede contener < ni >');
            $this->con->beginTransaction();
            $this->obtenerPlan($prep['plan']['id_paciente_plan'], true);              // bloqueo
            $sets = []; $par = [':id' => (int)$prep['plan']['id_paciente_plan']];
            foreach (array_unique(array_merge(array_keys($prep['diff']), array_keys($prep['derivados']))) as $k) { $sets[] = "$k = :$k"; $par[":$k"] = $prep['nuevo'][$k]; }
            $this->con->prepare('UPDATE paciente_planes SET ' . implode(', ', $sets) . ' WHERE id_paciente_plan = :id')->execute($par);

            $recalc = 0; $conPagos = [];
            if ($prep['financieros'] && $recalcular) {
                $s = $this->con->prepare("SELECT ps.id_sesion, ps.numero_sesion, ps.costo_sesion,
                        COALESCE((SELECT SUM(monto) FROM pagos WHERE id_sesion = ps.id_sesion AND tipo_pago = 'sesion'), 0) AS pagado
                        FROM plan_sesiones ps WHERE ps.id_paciente_plan = :id ORDER BY ps.numero_sesion");
                $s->execute([':id' => $par[':id']]);
                $upd = $this->con->prepare('UPDATE plan_sesiones SET costo_sesion = :c WHERE id_sesion = :s');
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $x) {
                    $c = PlanCostos::costoSesion($prep['nuevo'], $x['numero_sesion']);
                    if (abs($c - (float)$x['costo_sesion']) < 0.005) continue;
                    if ((float)$x['pagado'] > 0.004) { $conPagos[] = (int)$x['numero_sesion']; continue; }
                    $upd->execute([':c' => $c, ':s' => $x['id_sesion']]); $recalc++;
                }
            }
            $this->con->commit();
            return ['success' => true, 'cambios' => count($prep['diff']), 'sesiones_recalculadas' => $recalc, 'sesiones_con_pagos_sin_cambiar' => $conPagos];
        } catch (DomainException $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            return ['success' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            error_log('ModeloPlanes::aplicarEdicion: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo guardar el plan (no se cambió nada)'];
        }
    }

    // ───────────────── Corregir costos segun la regla del plan ─────────────────
    public function previsualizarRecalculo($idPlan) {
        $pl = $this->obtenerPlan($idPlan);
        if (!$pl) return null;
        $d = $this->cargar($pl); $filas = []; $corregir = 0; $ahorro = 0.0; $conPagos = 0; $pagadoDeMas = 0.0;
        foreach ($d['sesiones'] as $x) {
            $regla = PlanCostos::costoSesion($pl, $x['numero_sesion']); $actual = (float)$x['costo_sesion']; $pag = (float)$x['pagado'];
            if (abs($regla - $actual) < 0.005) $accion = 'igual';
            elseif ($pag > 0.004) { $accion = 'con_pagos'; $conPagos++; $pagadoDeMas += max(0, $pag - $regla); }
            else { $accion = 'corregir'; $corregir++; $ahorro += $actual - $regla; }
            $filas[] = ['numero' => (int)$x['numero_sesion'], 'costo_actual' => $actual, 'costo_regla' => $regla, 'pagado' => $pag, 'accion' => $accion, 'adicional' => PlanCostos::esAdicional($pl, $x['numero_sesion'])];
        }
        return ['id_paciente' => (int)$pl['id_paciente'], 'filas' => $filas, 'a_corregir' => $corregir, 'diferencia' => round($ahorro, 2), 'con_pagos' => $conPagos, 'pagado_de_mas' => round($pagadoDeMas, 2)];
    }

    public function aplicarRecalculo($idPlan) {
        try {
            $this->con->beginTransaction();
            $this->obtenerPlan($idPlan, true);
            $pre = $this->previsualizarRecalculo($idPlan);
            if (!$pre) throw new DomainException('El plan no existe');
            $upd = $this->con->prepare('UPDATE plan_sesiones SET costo_sesion = :c WHERE id_paciente_plan = :p AND numero_sesion = :n');
            $hechas = [];
            foreach ($pre['filas'] as $f) {
                if ($f['accion'] !== 'corregir') continue;
                $upd->execute([':c' => $f['costo_regla'], ':p' => (int)$idPlan, ':n' => $f['numero']]);
                $hechas[] = ['numero' => $f['numero'], 'antes' => $f['costo_actual'], 'despues' => $f['costo_regla']];
            }
            $this->con->commit();
            return ['success' => true, 'corregidas' => $hechas, 'con_pagos_sin_cambiar' => $pre['con_pagos'], 'id_paciente' => $pre['id_paciente']];
        } catch (DomainException $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            return ['success' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            error_log('ModeloPlanes::aplicarRecalculo: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo corregir los costos (no se cambió nada)'];
        }
    }
}
