<?php
require_once __DIR__ . '/../CONFIG/conexion.php';
require_once __DIR__ . '/modelo_cobros.php';
require_once __DIR__ . '/../CONFIG/plan_costos.php';

/**
 * Citas retroactivas: registrar atenciones de FECHAS PASADAS (historial de la clinica).
 *
 * Lo que hace distinto de una cita normal:
 *  - La fecha tiene que ser anterior a hoy (la agenda normal sigue bloqueando fechas pasadas).
 *  - No valida horarios ni disponibilidad del doctor (es historia, no agenda).
 *  - NO crea evento en Google Calendar, NO envia WhatsApp y las marcas de recordatorio quedan en "enviado".
 *  - Queda marcada origen = 'retroactivo' para distinguirla siempre.
 *  - Puede incluir servicios, la atencion (informe) y los pagos, todo en UNA transaccion: o se guarda todo o nada.
 */
class ModeloCitasRetroactivas {

    const ESTADOS_PERMITIDOS = [3, 4, 5];     // Cancelada, Completada, No asistio
    const ESTADO_COMPLETADA  = 4;
    const FECHA_MINIMA       = '2000-01-01';
    const MAX_SERVICIOS      = 30;

    private $con;

    public function __construct() {
        $this->con = (new Conexion())->getConexion();
    }

    // ── Buscar paciente por nombre o documento (para el formulario) ──
    public function buscarPacientes($termino) {
        $termino = trim((string)$termino);
        if (mb_strlen($termino) < 2) return [];
        $like = '%' . addcslashes($termino, '\\%_') . '%';
        $s = $this->con->prepare("SELECT p.id_paciente, CONCAT(p.nombre, ' ', p.apellido) AS nombre, p.nro_documento, td.abreviatura AS tipo_documento_abrev
                                  FROM pacientes p LEFT JOIN tipo_documento td ON td.id_tipo_documento = p.id_tipo_documento
                                  WHERE CONCAT(p.nombre, ' ', p.apellido) LIKE :q OR p.nro_documento LIKE :q
                                  ORDER BY p.nombre, p.apellido LIMIT 8");
        $s->execute([':q' => $like]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Catalogos para el formulario ─────────────────────────────
    public function catalogos() {
        return [
            // incluye doctores inactivos: una atencion antigua pudo ser de alguien que ya no trabaja aqui
            'doctores'       => $this->con->query("SELECT id_usuario, CONCAT(nombre, ' ', apellidos) AS nombre, id_estado
                                                   FROM usuarios WHERE id_rol = 2 ORDER BY (id_estado = 1) DESC, nombre, apellidos")->fetchAll(PDO::FETCH_ASSOC),
            'sedes'          => $this->con->query("SELECT id_sede_atencion, nombre_sede FROM sedes WHERE activo = 1 ORDER BY nombre_sede")->fetchAll(PDO::FETCH_ASSOC),
            'servicios'      => $this->con->query("SELECT id_tipo_servicio, nombre_servicio, precio_base FROM tipo_servicio WHERE activo = 1 ORDER BY nombre_servicio")->fetchAll(PDO::FETCH_ASSOC),
            'tipos_atencion' => $this->con->query("SELECT id_tipo_atencion, nombre, duracion_minutos FROM tipos_atencion WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC),
            'planes'         => $this->con->query("SELECT id_plan, nombre_plan, costo_base, sesiones_est, tiene_costo_inicial, costo_inicial_ref FROM planes_tratamiento WHERE activo = 1 ORDER BY nombre_plan")->fetchAll(PDO::FETCH_ASSOC),
            'aparatologia'   => $this->con->query("SELECT id_aparatologia, nombre, precio_base FROM aparatologia WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC),
            'estados'        => $this->con->query("SELECT id_estado_cita, estado FROM estado_cita WHERE id_estado_cita IN (3, 4, 5) ORDER BY FIELD(id_estado_cita, 4, 5, 3)")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function texto($v, $campo, $max, $obligatorio = false) {
        $v = trim((string)$v);
        if ($v === '') { if ($obligatorio) throw new DomainException("$campo es obligatorio"); return ''; }
        if (mb_strlen($v) > $max) throw new DomainException("$campo es demasiado largo (máx. $max caracteres)");
        if (strpbrk($v, '<>') !== false) throw new DomainException("$campo no puede contener < ni >");
        return $v;
    }

    /** Planes del paciente sobre los que se puede registrar una sesion pasada (activos o ya completados) */
    public function planesDelPaciente($idPaciente) {
        $s = $this->con->prepare("
            SELECT pp.id_paciente_plan, pp.id_plan, pt.nombre_plan, pp.tipo, pp.estado, pp.fecha_inicio, pp.costo_acordado, pp.en_cuotas,
                   pp.sesiones_pago_est, pp.costo_estimado_sesion, pp.cuota_inicial,
                   COALESCE((SELECT SUM(monto) FROM pagos WHERE id_paciente_plan = pp.id_paciente_plan AND tipo_pago = 'cuota_inicial'), 0) AS cuota_pagada,
                   (SELECT COUNT(*) FROM plan_sesiones WHERE id_paciente_plan = pp.id_paciente_plan) AS sesiones,
                   (SELECT COALESCE(MAX(numero_sesion), 0) + 1 FROM plan_sesiones WHERE id_paciente_plan = pp.id_paciente_plan) AS siguiente_numero
            FROM paciente_planes pp INNER JOIN planes_tratamiento pt ON pt.id_plan = pp.id_plan
            WHERE pp.id_paciente = :p AND pp.estado IN ('activo', 'completado')
            ORDER BY pp.fecha_inicio DESC, pp.id_paciente_plan DESC");
        $s->execute([':p' => (int)$idPaciente]);
        $filas = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['aviso'] = PlanCostos::aviso($f, (int)$f['siguiente_numero']);                // que costo lleva la proxima sesion y por que
            $f['costo_sesion_sugerido'] = $f['aviso']['costo_sesion'];
            $f['cuota_pendiente'] = max(0, round((float)$f['cuota_inicial'] - (float)$f['cuota_pagada'], 2));
        }
        return $filas;
    }

    private function numeroOpcional($v, $campo, $min, $max, $entero = false) {
        if ($v === null || $v === '') return null;
        if (!is_numeric($v) || (float)$v < $min || (float)$v > $max || ($entero && (int)$v != $v)) throw new DomainException("$campo no es válido");
        return $entero ? (int)$v : round((float)$v, 2);
    }

    /** Valida y normaliza el bloque "plan" del formulario. Lanza DomainException con un mensaje claro. */
    private function validarPlan(array $plan, $idPaciente, $fechaCita) {
        $modo = $plan['modo'] ?? '';
        if (!in_array($modo, ['existente', 'nuevo'], true)) throw new DomainException('El plan indicado no es válido');
        $r = ['modo' => $modo, 'costo_sesion' => $this->numeroOpcional($plan['costo_sesion'] ?? null, 'El costo de la sesión', 0, 99999.99),
              'numero_sesion' => $this->numeroOpcional($plan['numero_sesion'] ?? null, 'El número de sesión', 1, 999, true), 'aparatologia' => []];

        if ($modo === 'existente') {
            $idpp = (int)($plan['id_paciente_plan'] ?? 0);
            $s = $this->con->prepare("SELECT * FROM paciente_planes WHERE id_paciente_plan = :id AND id_paciente = :p FOR UPDATE");
            $s->execute([':id' => $idpp, ':p' => (int)$idPaciente]);
            $p = $s->fetch(PDO::FETCH_ASSOC);
            if (!$p) throw new DomainException('El plan seleccionado no existe o no es de este paciente');
            if (!in_array($p['estado'], ['activo', 'completado'], true)) throw new DomainException('El plan seleccionado está cancelado');
            if ($p['fecha_inicio'] > $fechaCita) throw new DomainException('El plan empezó el ' . $p['fecha_inicio'] . ', después de la fecha de esta cita');
            $r['plan'] = $p;
        } else {
            $n = (array)($plan['nuevo'] ?? []);
            $s = $this->con->prepare("SELECT id_plan FROM planes_tratamiento WHERE id_plan = :id");
            $s->execute([':id' => (int)($n['id_plan_catalogo'] ?? 0)]);
            if (!$s->fetchColumn()) throw new DomainException('Seleccione el plan de tratamiento');
            $tipo = $n['tipo'] ?? '';
            if (!in_array($tipo, ['por_sesion', 'costo_total'], true)) throw new DomainException('Indique si el plan es por sesión o de costo total');
            $costo = $this->numeroOpcional($n['costo_acordado'] ?? null, 'El costo acordado', 0, 9999999.99);
            if ($tipo === 'costo_total' && $costo === null) throw new DomainException('Indique el costo acordado del plan');
            $enCuotas = $tipo === 'costo_total' && !empty($n['en_cuotas']) ? 1 : 0;
            $sesCuotas = $this->numeroOpcional($n['sesiones_pago_est'] ?? null, 'El número de cuotas', 1, 120, true);
            if ($enCuotas && $sesCuotas === null) throw new DomainException('Indique en cuántas cuotas se paga el plan');
            $costoSes = $this->numeroOpcional($n['costo_estimado_sesion'] ?? null, 'El costo por sesión', 0, 99999.99);
            $cuotaIni = $this->numeroOpcional($n['cuota_inicial'] ?? 0, 'La cuota inicial', 0, 9999999.99) ?? 0.0;
            $notas = $this->texto($n['notas'] ?? '', 'Las notas del plan', 1000);
            $r['plan'] = ['id_plan' => (int)$n['id_plan_catalogo'], 'tipo' => $tipo, 'costo_acordado' => $costo, 'en_cuotas' => $enCuotas,
                          'sesiones_pago_est' => $enCuotas ? $sesCuotas : null, 'costo_estimado_sesion' => $tipo === 'por_sesion' ? $costoSes : null,
                          'cuota_inicial' => $cuotaIni, 'notas' => $notas];
            $r['numero_sesion'] = null;                      // un plan nuevo siempre empieza en la sesion 1
        }

        $aps = (array)($plan['aparatologia'] ?? []);
        if (count($aps) > 20) throw new DomainException('Demasiadas piezas de aparatología (máximo 20)');
        foreach ($aps as $i => $a) {
            $n = $i + 1;
            $s = $this->con->prepare("SELECT nombre, precio_base FROM aparatologia WHERE id_aparatologia = :id");
            $s->execute([':id' => (int)($a['id_aparatologia'] ?? 0)]);
            $cat = $s->fetch(PDO::FETCH_ASSOC);
            if (!$cat) throw new DomainException("Aparatología $n: seleccione una pieza válida");
            $cant = $a['cantidad'] ?? 1;
            if (!is_numeric($cant) || (int)$cant != $cant || (int)$cant < 1 || (int)$cant > 100) throw new DomainException("Aparatología $n: la cantidad debe ser un entero entre 1 y 100");
            $precio = $a['precio_acordado'] ?? null;
            if (!is_numeric($precio) || (float)$precio < 0 || (float)$precio > 99999.99) throw new DomainException("Aparatología $n: el precio no es válido");
            $r['aparatologia'][] = ['id_aparatologia' => (int)$a['id_aparatologia'], 'nombre' => $cat['nombre'], 'precio_base_ref' => (float)$cat['precio_base'],
                                    'precio_acordado' => round((float)$precio, 2), 'cantidad' => (int)$cant,
                                    'incluida' => ($modo === 'nuevo' && !empty($a['incluida_en_costo'])) ? 1 : 0];
        }
        return $r;
    }

    /**
     * $d = [ id_paciente, id_doctor, fecha, hora, duracion_minutos, id_sede_atencion, id_tipo_atencion?, id_estado_cita, motivo,
     *        servicios: [ {id_tipo_servicio, cantidad, precio_unitario} ],
     *        plan: { modo: 'existente'|'nuevo', id_paciente_plan | nuevo:{...}, costo_sesion?, numero_sesion?, aparatologia:[...] },
     *        informe: { registrar: bool, diagnostico, tratamiento_realizado, observaciones },
     *        pagos: [ {monto, id_metodo_pago, nro_comprobante, concepto?: {tipo: servicio|sesion|aparatologia|cuota_inicial, indice?}} ] ]
     */
    public function registrar(array $d, $idUsuario) {
        try {
            $this->con->beginTransaction();

            // ---- Paciente, doctor y sede ----
            $idPaciente = (int)($d['id_paciente'] ?? 0);
            $s = $this->con->prepare("SELECT 1 FROM pacientes WHERE id_paciente = :id");
            $s->execute([':id' => $idPaciente]);
            if (!$s->fetchColumn()) throw new DomainException('Seleccione un paciente válido');

            $idDoctor = (int)($d['id_doctor'] ?? 0);
            $s = $this->con->prepare("SELECT 1 FROM usuarios WHERE id_usuario = :id AND id_rol = 2");
            $s->execute([':id' => $idDoctor]);
            if (!$s->fetchColumn()) throw new DomainException('Seleccione un odontólogo válido');

            $idSede = (int)($d['id_sede_atencion'] ?? 0);
            $s = $this->con->prepare("SELECT 1 FROM sedes WHERE id_sede_atencion = :id");
            $s->execute([':id' => $idSede]);
            if (!$s->fetchColumn()) throw new DomainException('Seleccione una sede válida');

            $idTipoAtencion = (int)($d['id_tipo_atencion'] ?? 0) ?: null;
            if ($idTipoAtencion) {
                $s = $this->con->prepare("SELECT 1 FROM tipos_atencion WHERE id_tipo_atencion = :id");
                $s->execute([':id' => $idTipoAtencion]);
                if (!$s->fetchColumn()) throw new DomainException('El tipo de atención no es válido');
            }

            // ---- Fecha y hora: tiene que ser una fecha PASADA (hora de Lima, como el resto del sistema) ----
            $fecha = trim((string)($d['fecha'] ?? ''));
            $f = DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$f || $f->format('Y-m-d') !== $fecha) throw new DomainException('La fecha no es válida');
            if ($fecha >= date('Y-m-d')) throw new DomainException('Esta pantalla es solo para fechas pasadas. Para hoy o el futuro use la agenda normal');
            if ($fecha < self::FECHA_MINIMA) throw new DomainException('La fecha es demasiado antigua');
            $hora = trim((string)($d['hora'] ?? ''));
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $hora)) throw new DomainException('La hora no es válida (use HH:MM)');
            $hora = substr($hora, 0, 5) . ':00';
            $duracion = (int)($d['duracion_minutos'] ?? 30);
            if ($duracion < 5 || $duracion > 480) throw new DomainException('La duración debe estar entre 5 y 480 minutos');
            $horaFin = date('H:i:s', strtotime("$fecha $hora +{$duracion} minutes"));

            // ---- Estado ----
            $estado = (int)($d['id_estado_cita'] ?? 0);
            if (!in_array($estado, self::ESTADOS_PERMITIDOS, true)) throw new DomainException('El estado debe ser Completada, No asistió o Cancelada');
            $completada = $estado === self::ESTADO_COMPLETADA;

            $motivo = $this->texto($d['motivo'] ?? '', 'El motivo', 1000, true);

            // ---- Duplicado: misma persona, mismo doctor, mismo dia y hora ----
            $s = $this->con->prepare("SELECT COUNT(*) FROM citas WHERE id_paciente = :p AND id_doctor = :d AND fecha = :f AND hora = :h");
            $s->execute([':p' => $idPaciente, ':d' => $idDoctor, ':f' => $fecha, ':h' => $hora]);
            if ($s->fetchColumn() > 0) throw new DomainException('Ya existe una cita de este paciente con este odontólogo en esa fecha y hora');

            // ---- Servicios (solo en citas completadas) ----
            $servicios = []; $totalServicios = 0.0;
            $lista = (array)($d['servicios'] ?? []);
            $planDatos = !empty($d['plan']) ? (array)$d['plan'] : null;
            if (!$completada && ($lista || $planDatos)) throw new DomainException('Solo una cita Completada puede tener servicios, plan, atención o pagos');
            if (count($lista) > self::MAX_SERVICIOS) throw new DomainException('Demasiados servicios (máximo ' . self::MAX_SERVICIOS . ')');
            foreach ($lista as $i => $sv) {
                $n = $i + 1;
                $idSrv = (int)($sv['id_tipo_servicio'] ?? 0);
                $s = $this->con->prepare("SELECT 1 FROM tipo_servicio WHERE id_tipo_servicio = :id");
                $s->execute([':id' => $idSrv]);
                if (!$s->fetchColumn()) throw new DomainException("Servicio $n: seleccione un servicio válido");
                $cant = $sv['cantidad'] ?? 1;
                if (!is_numeric($cant) || (int)$cant != $cant || (int)$cant < 1 || (int)$cant > 100) throw new DomainException("Servicio $n: la cantidad debe ser un entero entre 1 y 100");
                $precio = $sv['precio_unitario'] ?? null;
                if (!is_numeric($precio) || (float)$precio < 0 || (float)$precio > 99999.99) throw new DomainException("Servicio $n: el precio no es válido");
                $cant = (int)$cant; $precio = round((float)$precio, 2);
                $servicios[] = ['id_tipo_servicio' => $idSrv, 'cantidad' => $cant, 'precio' => $precio, 'subtotal' => round($cant * $precio, 2)];
                $totalServicios += round($cant * $precio, 2);
            }
            $totalServicios = round($totalServicios, 2);

            // ---- Plan de tratamiento (sesion y aparatologia) ----
            $plan = $planDatos ? $this->validarPlan($planDatos, $idPaciente, $fecha) : null;

            // ---- Pagos: validacion basica; el reparto fino se hace mas abajo, con los conceptos ya creados ----
            $pagos = (array)($d['pagos'] ?? []);
            $sumaPagos = 0.0; $hayConcepto = false;
            foreach ($pagos as $i => $pg) {
                if (!is_numeric($pg['monto'] ?? null) || round((float)$pg['monto'], 2) <= 0) throw new DomainException('Pago ' . ($i + 1) . ': el monto no es válido');
                $sumaPagos += round((float)$pg['monto'], 2);
                if (!empty($pg['concepto'])) $hayConcepto = true;
            }
            if ($pagos && !$hayConcepto) {                      // pagos "en orden" sobre los servicios (forma simple)
                if (!$servicios) throw new DomainException('Para registrar pagos agregue primero los servicios cobrados');
                if (round($sumaPagos, 2) > $totalServicios + 0.004) {
                    throw new DomainException('Los pagos (S/ ' . number_format($sumaPagos, 2) . ') superan el total de los servicios (S/ ' . number_format($totalServicios, 2) . ')');
                }
            }

            // ---- Atencion (informe): con un plan es obligatoria (Cobros lee el plan desde la atencion enviada) ----
            $inf = (array)($d['informe'] ?? []);
            $registrarInforme = $completada && (!empty($inf['registrar']) || $plan !== null);
            $diag = $trat = $obs = '';
            if ($registrarInforme) {
                $diag = $this->texto($inf['diagnostico'] ?? '', 'El diagnóstico', 2000);
                $trat = $this->texto($inf['tratamiento_realizado'] ?? '', 'El tratamiento realizado', 2000);
                $obs  = $this->texto($inf['observaciones'] ?? '', 'Las observaciones', 2000);
            }

            // ---- La cita (sin calendario, sin WhatsApp; recordatorios marcados como enviados) ----
            $ahora = date('Y-m-d H:i:s');
            $this->con->prepare("
                INSERT INTO citas (id_paciente, id_doctor, fecha, hora, hora_inicio, hora_fin, id_estado_cita, id_tipo_servicio, id_tipo_atencion,
                                   duracion_minutos, id_sede_atencion, motivo, creado_por, fecha_registro,
                                   recordatorio_24h_enviado, recordatorio_2h_enviado, origen)
                VALUES (:p, :d, :f, :h, :hi, :hf, :e, :ts, :ta, :dur, :sede, :mot, :u, :reg, 1, 1, 'retroactivo')")
                ->execute([':p' => $idPaciente, ':d' => $idDoctor, ':f' => $fecha, ':h' => $hora, ':hi' => $hora, ':hf' => $horaFin, ':e' => $estado,
                           ':ts' => $servicios ? $servicios[0]['id_tipo_servicio'] : null, ':ta' => $idTipoAtencion, ':dur' => $duracion,
                           ':sede' => $idSede, ':mot' => $motivo, ':u' => $idUsuario ?: null, ':reg' => $ahora]);
            $idCita = (int)$this->con->lastInsertId();

            // ---- Conceptos cobrables (se arma el mapa a medida que se crean) ----
            $conceptos = [];

            $ins = $this->con->prepare("INSERT INTO cita_servicios (id_cita, id_tipo_servicio, cantidad, precio_unitario, subtotal) VALUES (:c, :s, :q, :p, :t)");
            foreach ($servicios as $i => $sv) {
                $ins->execute([':c' => $idCita, ':s' => $sv['id_tipo_servicio'], ':q' => $sv['cantidad'], ':p' => $sv['precio'], ':t' => $sv['subtotal']]);
                $conceptos["s:$i"] = ['tipo_pago' => 'total', 'campos' => ['id_cita_servicio' => (int)$this->con->lastInsertId()], 'resta' => $sv['subtotal'], 'nombre' => 'el servicio ' . ($i + 1)];
            }

            $idPacientePlan = null; $idPlanCatalogo = null; $resumenPlan = null;
            if ($plan) {
                if ($plan['modo'] === 'nuevo') {
                    $np = $plan['plan'];
                    $this->con->prepare("
                        INSERT INTO paciente_planes (id_plan, id_paciente, id_doctor, tipo, costo_acordado, en_cuotas, sesiones_pago_est, costo_estimado_sesion,
                                                     cuota_inicial, notas, creado_en_cita, fecha_inicio, estado)
                        VALUES (:plan, :pac, :doc, :tipo, :costo, :cuotas, :ses, :csesion, :cuota, :notas, :cita, :inicio, 'activo')")
                        ->execute([':plan' => $np['id_plan'], ':pac' => $idPaciente, ':doc' => $idDoctor, ':tipo' => $np['tipo'], ':costo' => $np['costo_acordado'],
                                   ':cuotas' => $np['en_cuotas'], ':ses' => $np['sesiones_pago_est'], ':csesion' => $np['costo_estimado_sesion'],
                                   ':cuota' => $np['cuota_inicial'], ':notas' => $np['notas'] !== '' ? $np['notas'] : null, ':cita' => $idCita, ':inicio' => $fecha]);
                    $idPacientePlan = (int)$this->con->lastInsertId(); $idPlanCatalogo = $np['id_plan'];
                    $datosCosto = ['tipo' => $np['tipo'], 'en_cuotas' => $np['en_cuotas'], 'costo_acordado' => $np['costo_acordado'],
                                   'sesiones_pago_est' => $np['sesiones_pago_est'], 'costo_estimado_sesion' => $np['costo_estimado_sesion']];
                    $cuotaPendiente = (float)$np['cuota_inicial'];
                    $numero = 1;
                } else {
                    $p = $plan['plan'];
                    $idPacientePlan = (int)$p['id_paciente_plan']; $idPlanCatalogo = (int)$p['id_plan'];
                    $datosCosto = $p;
                    $q = $this->con->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE id_paciente_plan = :id AND tipo_pago = 'cuota_inicial'");
                    $q->execute([':id' => $idPacientePlan]);
                    $cuotaPendiente = max(0, round((float)$p['cuota_inicial'] - (float)$q->fetchColumn(), 2));
                    if ($plan['numero_sesion'] !== null) {
                        $numero = (int)$plan['numero_sesion'];
                        $q = $this->con->prepare("SELECT COUNT(*) FROM plan_sesiones WHERE id_paciente_plan = :id AND numero_sesion = :n");
                        $q->execute([':id' => $idPacientePlan, ':n' => $numero]);
                        if ($q->fetchColumn() > 0) throw new DomainException("La sesión n.º $numero de este plan ya está registrada");
                    } else {
                        $q = $this->con->prepare("SELECT COALESCE(MAX(numero_sesion), 0) + 1 FROM plan_sesiones WHERE id_paciente_plan = :id");
                        $q->execute([':id' => $idPacientePlan]);
                        $numero = (int)$q->fetchColumn();
                    }
                }
                // la sesion de ESTA cita (el costo real de la epoca puede indicarse; si no, rige la regla del plan)
                $costoSesion = $plan['costo_sesion'] !== null ? $plan['costo_sesion'] : PlanCostos::costoSesion($datosCosto, $numero);
                $this->con->prepare("INSERT INTO plan_sesiones (id_paciente_plan, id_cita, numero_sesion, costo_sesion, total_pagado) VALUES (:pp, :c, :n, :cs, 0)")
                     ->execute([':pp' => $idPacientePlan, ':c' => $idCita, ':n' => $numero, ':cs' => $costoSesion]);
                $idSesion = (int)$this->con->lastInsertId();
                $conceptos['ses'] = ['tipo_pago' => 'sesion', 'campos' => ['id_sesion' => $idSesion], 'resta' => $costoSesion, 'nombre' => "la sesión n.º $numero"];

                // aparatologia de la cita (las "incluidas en el costo" no se cobran aparte)
                $insAp = $this->con->prepare("INSERT INTO paciente_plan_aparatologia (id_paciente_plan, id_cita, id_aparatologia, descripcion, precio_base_ref, precio_acordado, incluida_en_costo, cantidad)
                                              VALUES (:pp, :c, :ap, :de, :ref, :ac, :inc, :ca)");
                foreach ($plan['aparatologia'] as $j => $a) {
                    $insAp->execute([':pp' => $idPacientePlan, ':c' => $idCita, ':ap' => $a['id_aparatologia'], ':de' => $a['nombre'], ':ref' => $a['precio_base_ref'],
                                     ':ac' => $a['precio_acordado'], ':inc' => $a['incluida'], ':ca' => $a['cantidad']]);
                    if (!$a['incluida']) {
                        $conceptos["a:$j"] = ['tipo_pago' => 'aparatologia', 'campos' => ['id_aparatologia_item' => (int)$this->con->lastInsertId()],
                                              'resta' => round($a['precio_acordado'] * $a['cantidad'], 2), 'nombre' => 'la aparatología ' . ($j + 1)];
                    }
                }
                if ($cuotaPendiente > 0.004) {
                    $conceptos['cuota'] = ['tipo_pago' => 'cuota_inicial', 'campos' => ['id_paciente_plan' => $idPacientePlan, 'id_cita' => $idCita],
                                           'resta' => $cuotaPendiente, 'nombre' => 'la cuota inicial',
                                           'en_total' => $plan['modo'] === 'nuevo'];      // la cuota de un plan YA existente no es de esta cita
                }
                $resumenPlan = ['id_paciente_plan' => $idPacientePlan, 'sesion' => $numero, 'costo_sesion' => $costoSesion, 'modo' => $plan['modo']];
            }

            // ---- Atencion enviada (para que figure en el historial y en Cobros si queda deuda) ----
            if ($registrarInforme) {
                $this->con->prepare("
                    INSERT INTO informe_atencion (id_cita, id_doctor, motivo_consulta, diagnostico, tratamiento_realizado, observaciones,
                                                  id_plan, id_paciente_plan, fecha_registro, estado, fecha_envio)
                    VALUES (:c, :d, :m, :dg, :t, :o, :ip, :ipp, :fr, 'enviado', :fe)")
                    ->execute([':c' => $idCita, ':d' => $idDoctor, ':m' => $motivo, ':dg' => $diag ?: null, ':t' => $trat ?: null, ':o' => $obs ?: null,
                               ':ip' => $idPlanCatalogo, ':ipp' => $idPacientePlan, ':fr' => "$fecha $hora", ':fe' => "$fecha $hora"]);
            }

            // ---- Pagos: cada uno a su concepto (o "en orden" sobre los servicios), con la fecha de la cita ----
            $totalPagable = 0.0;
            foreach ($conceptos as $cn) if (($cn['en_total'] ?? true) !== false) $totalPagable += $cn['resta'];
            $totalPagable = round($totalPagable, 2);
            $cobros = new ModeloCobros($this->con);           // misma conexion = misma transaccion
            $pagado = 0.0; $pagadoCita = 0.0;                 // pagadoCita = lo aplicado a conceptos de ESTA cita
            foreach ($pagos as $i => $pg) {
                $n = $i + 1; $monto = round((float)$pg['monto'], 2); $items = [];
                if (!empty($pg['concepto'])) {
                    $c = (array)$pg['concepto']; $tipo = $c['tipo'] ?? ''; $ix = isset($c['indice']) ? (int)$c['indice'] : 0;
                    $clave = ['servicio' => "s:$ix", 'sesion' => 'ses', 'aparatologia' => "a:$ix", 'cuota_inicial' => 'cuota'][$tipo] ?? null;
                    if ($clave === null || !isset($conceptos[$clave])) throw new DomainException("Pago $n: el concepto elegido no existe en esta cita");
                    if ($monto > $conceptos[$clave]['resta'] + 0.004) {
                        throw new DomainException("Pago $n: supera lo que falta cobrar de {$conceptos[$clave]['nombre']} (S/ " . number_format($conceptos[$clave]['resta'], 2) . ')');
                    }
                    $items[] = ['tipo_pago' => $conceptos[$clave]['tipo_pago']] + $conceptos[$clave]['campos'] + ['monto' => $monto];
                    $conceptos[$clave]['resta'] = round($conceptos[$clave]['resta'] - $monto, 2);
                    if (($conceptos[$clave]['en_total'] ?? true) !== false) $pagadoCita += $monto;
                } else {                                      // forma simple: se reparte entre los servicios en orden
                    foreach ($conceptos as $k => &$cn) {
                        if ($monto <= 0.004) break;
                        if ($k[0] !== 's' || $k === 'ses') continue;
                        $toma = round(min($monto, $cn['resta']), 2);
                        if ($toma < 0.005) continue;
                        $items[] = ['tipo_pago' => 'total'] + $cn['campos'] + ['monto' => $toma];
                        $cn['resta'] = round($cn['resta'] - $toma, 2); $monto = round($monto - $toma, 2);
                    }
                    unset($cn);
                    if ($monto > 0.004) throw new DomainException("Pago $n: supera lo que falta cobrar de los servicios");
                    $pagadoCita += round((float)$pg['monto'], 2);
                }
                try {
                    $cobros->registrarPagos($items, $idPaciente, $idUsuario, [
                        'id_metodo_pago'  => $pg['id_metodo_pago']  ?? 0,
                        'nro_comprobante' => $pg['nro_comprobante'] ?? '',
                        'fecha_pago'      => $fecha,
                    ]);
                } catch (DomainException $e) {
                    throw new DomainException("Pago $n: " . $e->getMessage());
                }
                $pagado += round((float)$pg['monto'], 2);
            }

            $this->con->commit();
            return ['success' => true, 'id_cita' => $idCita, 'servicios' => count($servicios), 'total' => $totalPagable,
                    'pagado' => round($pagado, 2), 'pagado_cita' => round($pagadoCita, 2), 'pendiente' => round($totalPagable - $pagadoCita, 2), 'informe' => $registrarInforme,
                    'plan' => $resumenPlan, 'origen' => 'retroactivo'];
        } catch (DomainException $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            return ['success' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            error_log('ModeloCitasRetroactivas::registrar: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo registrar la cita (no se guardó nada)'];
        }
    }

    // ── Ultimas citas pasadas registradas (para revisarlas o deshacer una equivocacion) ──
    public function listarRecientes($limite = 15) {
        $s = $this->con->prepare("
            SELECT c.id_cita, c.fecha, c.hora, CONCAT(p.nombre, ' ', p.apellido) AS paciente, CONCAT(u.nombre, ' ', u.apellidos) AS doctor,
                   ec.estado AS estado_nombre, c.fecha_registro,
                   COALESCE((SELECT SUM(subtotal) FROM cita_servicios WHERE id_cita = c.id_cita), 0)
                   + COALESCE((SELECT SUM(costo_sesion) FROM plan_sesiones WHERE id_cita = c.id_cita), 0)
                   + COALESCE((SELECT SUM(precio_acordado * cantidad) FROM paciente_plan_aparatologia WHERE id_cita = c.id_cita AND incluida_en_costo = 0), 0) AS total,
                   COALESCE((SELECT SUM(monto) FROM pagos WHERE id_cita = c.id_cita AND tipo_pago <> 'anticipo'), 0) AS pagado,
                   (SELECT COUNT(*) FROM pagos WHERE id_cita = c.id_cita) AS movimientos_pago
            FROM citas c
            INNER JOIN pacientes p     ON p.id_paciente = c.id_paciente
            INNER JOIN usuarios u      ON u.id_usuario = c.id_doctor
            INNER JOIN estado_cita ec  ON ec.id_estado_cita = c.id_estado_cita
            WHERE c.origen = 'retroactivo'
            ORDER BY c.id_cita DESC LIMIT " . (int)$limite);
        $s->execute();
        $filas = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) { $f['puede_eliminar'] = (int)$f['movimientos_pago'] === 0; unset($f['movimientos_pago']); }
        return $filas;
    }

    // ── Eliminar una cita pasada cargada por error (solo si no tiene movimientos de pago) ──
    public function eliminar($idCita) {
        try {
            $this->con->beginTransaction();
            $s = $this->con->prepare("SELECT origen FROM citas WHERE id_cita = :id FOR UPDATE");
            $s->execute([':id' => (int)$idCita]);
            $origen = $s->fetchColumn();
            if ($origen === false) throw new DomainException('La cita no existe');
            if ($origen !== 'retroactivo') throw new DomainException('Solo se pueden eliminar desde aquí las citas registradas como pasadas');
            $s = $this->con->prepare("SELECT COUNT(*) FROM pagos WHERE id_cita = :id");
            $s->execute([':id' => (int)$idCita]);
            if ($s->fetchColumn() > 0) throw new DomainException('No se puede eliminar: la cita tiene movimientos de pago (aunque estén anulados). Se conservan como historial');
            // Si esta cita CREO un plan, solo se elimina junto con ella si ninguna otra cita lo usa
            $s = $this->con->prepare("SELECT id_paciente_plan FROM paciente_planes WHERE creado_en_cita = :id");
            $s->execute([':id' => (int)$idCita]);
            $planCreado = $s->fetchColumn();
            if ($planCreado) {
                $otros = $this->con->prepare("SELECT (SELECT COUNT(*) FROM plan_sesiones WHERE id_paciente_plan = :p AND id_cita <> :c)
                                                   + (SELECT COUNT(*) FROM paciente_plan_aparatologia WHERE id_paciente_plan = :p2 AND id_cita <> :c2)
                                                   + (SELECT COUNT(*) FROM informe_atencion WHERE id_paciente_plan = :p3 AND id_cita <> :c3)
                                                   + (SELECT COUNT(*) FROM pagos WHERE id_paciente_plan = :p4)");
                $otros->execute([':p' => $planCreado, ':c' => (int)$idCita, ':p2' => $planCreado, ':c2' => (int)$idCita, ':p3' => $planCreado, ':c3' => (int)$idCita, ':p4' => $planCreado]);
                if ($otros->fetchColumn() > 0) throw new DomainException('No se puede eliminar: esta cita creó un plan que ya tiene otras sesiones o pagos');
            }
            $this->con->prepare("DELETE FROM informe_atencion WHERE id_cita = :id")->execute([':id' => (int)$idCita]);
            $this->con->prepare("DELETE FROM paciente_plan_aparatologia WHERE id_cita = :id")->execute([':id' => (int)$idCita]);
            $this->con->prepare("DELETE FROM plan_sesiones WHERE id_cita = :id")->execute([':id' => (int)$idCita]);
            $this->con->prepare("DELETE FROM cita_servicios WHERE id_cita = :id")->execute([':id' => (int)$idCita]);
            if ($planCreado) $this->con->prepare("DELETE FROM paciente_planes WHERE id_paciente_plan = :p")->execute([':p' => $planCreado]);
            $this->con->prepare("DELETE FROM citas WHERE id_cita = :id AND origen = 'retroactivo'")->execute([':id' => (int)$idCita]);
            $this->con->commit();
            return ['success' => true];
        } catch (DomainException $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            return ['success' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->con->inTransaction()) $this->con->rollBack();
            error_log('ModeloCitasRetroactivas::eliminar: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo eliminar (no se cambió nada). Puede que la cita ya tenga otros datos asociados'];
        }
    }
}
