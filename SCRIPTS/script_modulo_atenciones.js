(function () {

    const ID_PACIENTE     = window.AT_ID_PACIENTE;
    const ID_USUARIO      = window.AT_ID_USUARIO;
    const PUEDE_REGISTRAR = window.AT_PUEDE_REGISTRAR;
    const PUEDE_PAGAR     = window.AT_PUEDE_PAGAR;

    let todasLasCitas        = [];
    let catalogoServicios    = [];
    let catalogoPlanes       = [];
    let catalogoAparatologia = [];
    let serviciosInforme     = [];
    let aparatologiaInforme  = [];
    let pacientePlanes       = [];
    let filtroActivo         = 'todas';
    let citaActual           = null; // cita abierta en el modal

    // ── Inicializar ───────────────────────────────────────────────
    cargarCatalogos().then(() => {
        cargarCitas();
        cargarPlanesActivos();
    });
    registrarListeners();

    // Exponer funciones necesarias para callbacks en onclick inline
    window._at_cargarCitas         = () => cargarCitas();
    window._at_cargarPlanesActivos = () => cargarPlanesActivos();

    // ── Catálogos ─────────────────────────────────────────────────
    async function cargarCatalogos() {
        const [resS, resP, resA] = await Promise.all([
            fetch('../CONTROLADORES/controlador_atenciones.php?accion=listar_servicios'),
            fetch('../CONTROLADORES/controlador_atenciones.php?accion=listar_planes'),
            fetch('../CONTROLADORES/controlador_atenciones.php?accion=listar_aparatologia')
        ]);
        catalogoServicios    = await resS.json();
        catalogoPlanes       = await resP.json();
        catalogoAparatologia = await resA.json();

        // Poblar selects
        poblarSelect('selectServicio',         catalogoServicios,    'id_tipo_servicio', 'nombre_servicio', 'precio_base', 'Seleccionar servicio...');
        poblarSelect('selectFiltroServicio',   catalogoServicios,    'id_tipo_servicio', 'nombre_servicio', null,          'Todos los servicios');
        poblarSelect('selectTipoPlanCatalogo', catalogoPlanes,       'id_plan',          'nombre_plan',     'costo_base',  'Seleccionar...');
        poblarSelect('selectFiltroPlan',       catalogoPlanes,       'id_plan',          'nombre_plan',     null,          'Todos los planes');
        poblarSelect('selectAparato',          catalogoAparatologia, 'id_aparatologia',  'nombre',          'precio_base', 'Seleccionar...');
    }

    function poblarSelect(id, lista, valCampo, txtCampo, precioCampo, placeholder) {
        const sel = document.getElementById(id);
        if (!sel) return;
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        lista.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valCampo];
            opt.textContent = item[txtCampo];
            if (precioCampo) opt.dataset.precio = item[precioCampo] || 0;
            sel.appendChild(opt);
        });
    }

    // ── Planes de tratamiento: ficha completa ────────────────────
    let planesFicha  = [];
    let permisosPlan = { editar: false, editar_costos: false };
    const PCTRL = '../CONTROLADORES/controlador_planes.php';
    const $id = x => document.getElementById(x);
    const escP = v => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const r2P = n => Math.round((Number(n) || 0) * 100) / 100;
    const sol = n => 'S/ ' + r2P(n).toFixed(2);
    const fcorta = f => f ? String(f).split(' ')[0].split('-').reverse().join('/') : '—';
    const plural = (n, uno, varios) => n === 1 ? uno : varios;
    const pct = (a, b) => b > 0 ? Math.max(0, Math.min(100, Math.round(a / b * 100))) : 0;
    function plToast(texto, tipo) {
        const t = document.createElement('div'); t.textContent = texto;
        t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10001;color:#fff;padding:12px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.18);max-width:360px;font-size:13.5px;background:' + (tipo === 'error' ? '#c0392b' : '#27ae60');
        document.body.appendChild(t); setTimeout(() => t.remove(), 4000);
    }
    async function plPedir(url, opciones) {
        let res; try { res = await fetch(url, opciones); } catch (e) { return { success: false, mensaje: 'No se pudo conectar con el servidor' }; }
        let txt = ''; try { txt = await res.text(); } catch (e) { /* sin cuerpo */ }
        try { const j = JSON.parse(txt); if (res.status === 403 && j && !j.mensaje) j.mensaje = 'No tiene permiso para esta acción'; return j; }
        catch (e) { return { success: false, mensaje: res.status === 403 ? 'No tiene permiso para esta acción' : 'Respuesta inválida del servidor (HTTP ' + res.status + ')' }; }
    }

    function cargarPlanesActivos() {
        fetch(`${PCTRL}?accion=listar&id_paciente=${ID_PACIENTE}&todos=1`)
            .then(r => r.json())
            .then(d => { if (!d || !d.success) return; planesFicha = d.planes; permisosPlan = d.permisos || permisosPlan; pintarPlanes(); })
            .catch(() => { /* sin planes: la seccion queda oculta */ });
    }

    // ── Iconos (trazo simple, heredan el color del texto) ──
    const ICONOS = {
        sparkles: '<path d="M12 3l1.9 5.8L20 10l-6.1 1.2L12 17l-1.9-5.8L4 10l6.1-1.2z"/><path d="M19 3v4M17 5h4M5 17v4M3 19h4"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        wallet:   '<path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        receipt:  '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        alerta:   '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        check:    '<path d="M20 6L9 17l-5-5"/>',
        lapiz:    '<path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5z"/>',
        mas:      '<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>',
        arriba:   '<path d="M18 15l-6-6-6 6"/>',
        abajo:    '<path d="M6 9l6 6 6-6"/>',
        ojo:      '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    };
    const ico = (n, cls = '') => `<svg class="pl-ico ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONOS[n] || ''}</svg>`;
    const solM = n => 'S/ ' + r2P(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fechaHora = (f, h) => `${fcorta(f)}${h ? ' ' + String(h).slice(0, 5) : ''}`;

    // Aviso de la proxima sesion
    function textoProxima(p) {
        const a = p.aviso_proxima;
        if (a.adicional) return `La sesión n.º ${a.numero} será adicional y sin costo.`;
        return a.mensaje;
    }

    function tarjetaPlan(p, abierta) {
        const tipoTxt = p.tipo === 'costo_total' ? 'Costo fijo' : 'Por sesión';
        const D = p.dinero, nSes = p.sesiones_realizadas, est = p.sesiones_estimadas, adic = p.sesiones_adicionales;
        const comprometido = D.sesiones + D.aparatologia + D.cuota_inicial;
        const estadoTxt = p.estado === 'activo' ? 'Activo' : (p.estado === 'completado' ? 'Completado' : 'Cancelado');
        const nBorr = p.sesiones_borrador || 0;

        // menu "..." (solo con acciones disponibles)
        const items = [];
        if (permisosPlan.editar || permisosPlan.editar_costos) items.push(['editar', 'Editar plan']);
        if (permisosPlan.editar_costos) items.push(['corregir', 'Corregir costos']);
        if (permisosPlan.editar && p.estado === 'activo') items.push(['completar', 'Marcar como completado']);
        if (permisosPlan.editar && p.estado !== 'activo') items.push(['reabrir', 'Reabrir plan']);
        const menu = items.length ? `<div class="pl-menu-caja"><button type="button" class="pl-ibtn" data-menu="1" aria-label="Más acciones">${ico('mas')}</button>
                <div class="pl-menu" style="display:none;">${items.map(([a, t]) => `<button type="button" data-accion="${a}" data-id="${p.id_paciente_plan}">${t}</button>`).join('')}</div></div>` : '';

        // indicadores (columna izquierda)
        const kpiCosto = p.tipo === 'costo_total'
            ? ['COSTO ACORDADO', solM(p.costo_acordado), p.en_cuotas ? `Costo fijo en ${p.sesiones_pago_est} cuota${p.sesiones_pago_est > 1 ? 's' : ''}` : 'Plan a costo fijo']
            : ['COSTO POR SESIÓN', solM(p.costo_estimado_sesion || 0), 'Plan por sesión'];
        const kpis = [
            ['receipt', ...kpiCosto],
            ['wallet', 'PAGADO TOTAL', solM(D.pagado_total), comprometido > 0 ? `de ${solM(comprometido)}${D.pendiente_cobrable > 0.004 ? ' · por cobrar ' + solM(D.pendiente_cobrable) : ''}` : 'Sin cobros registrados'],
            ['activity', 'SESIONES', `${nSes} realizada${nSes === 1 ? '' : 's'}`, est ? (adic ? `${est} estimadas · ${adic} adicional${adic > 1 ? 'es' : ''}` : `${est} sesion${est === 1 ? '' : 'es'} estimada${est === 1 ? '' : 's'}`) : 'Sin estimación'],
            ['calendar', 'FECHA DE INICIO', fcorta(p.fecha_inicio), p.fecha_fin_est ? 'Fin estimado ' + fcorta(p.fecha_fin_est) : 'Sin fecha estimada de fin'],
        ].map(([ic, et, val, sub]) => `<div class="pl-kpi"><span class="pl-kpi-ico">${ico(ic)}</span><div><span class="pl-kpi-et">${et}</span><span class="pl-kpi-val">${val}</span><span class="pl-kpi-sub">${escP(sub)}</span></div></div>`).join('');

        // condiciones y detalle economico (nada de lo que ya mostraba la ficha se pierde)
        const cond = [
            ['Tipo de cobro', tipoTxt], ['Costo acordado', p.costo_acordado ? solM(p.costo_acordado) : '—'],
            ['En cuotas', p.en_cuotas ? `Sí · ${p.sesiones_pago_est} cuota${p.sesiones_pago_est > 1 ? 's' : ''}` : 'No'],
            p.tipo !== 'costo_total' ? ['Costo por sesión', p.costo_estimado_sesion ? solM(p.costo_estimado_sesion) : '—'] : null,
            ['Cuota inicial', solM(p.cuota_inicial)], ['Sesiones estimadas', est || '—'],
        ].filter(Boolean).map(([k, v]) => `<div class="pl-cond"><span>${k}</span><b>${escP(v)}</b></div>`).join('');
        const eco = `<div class="pl-eco" style="display:none;">
            <span class="pl-etq">Condiciones del plan</span>${cond}
            <span class="pl-etq">Resumen económico</span>
            <div class="pl-cond"><span>Sesiones</span><b>${solM(D.sesiones)}</b></div><div class="pl-cond pl-cond-sub"><span>pagado</span><b>${solM(D.pagado_sesiones)}</b></div>
            <div class="pl-cond"><span>Aparatología aparte</span><b>${solM(D.aparatologia)}</b></div><div class="pl-cond pl-cond-sub"><span>pagado</span><b>${solM(D.pagado_aparatologia)}</b></div>
            <div class="pl-cond"><span>Cuota inicial</span><b>${solM(D.cuota_inicial)}</b></div><div class="pl-cond pl-cond-sub"><span>pagado</span><b>${solM(D.pagado_cuota)}</b></div>
            ${D.por_enviar > 0 ? `<div class="pl-cond"><span>Por enviar a cobrar</span><b>${solM(D.por_enviar)}</b></div>` : ''}
            <div class="pl-cond pl-cond-total"><span>Pendiente de cobro</span><b>${solM(D.pendiente_cobrable)}</b></div>
            ${p.notas ? `<div class="pl-notas"><span class="pl-etq">Notas</span><p>${escP(p.notas)}</p></div>` : ''}</div>`;

        // tarjeta de aviso (una sola, como en el diseño)
        const peligro = (p.alertas || []).find(a => a.nivel === 'peligro');
        const ok = (p.alertas || []).find(a => a.nivel === 'ok');
        const infos = (p.alertas || []).filter(a => a.nivel === 'info' || a.nivel === 'aviso');
        let aviso = '';
        const frase = (partes) => partes.filter(Boolean).join(' y ') + '.';
        const textoBorr = nBorr ? (nBorr === 1 ? 'una atención en borrador' : `${nBorr} atenciones en borrador`) : '';
        if (peligro) {
            aviso = { tit: 'Revisar costos', txt: frase([`Hay ${solM(D.exceso)} adicionales`, textoBorr]), accion: permisosPlan.editar_costos ? ['corregir', 'Corregir costos'] : null };
        } else if (ok) {
            aviso = { tit: 'Plan listo para cerrar', txt: ok.texto, accion: permisosPlan.editar ? ['completar', 'Marcar como completado'] : null };
        } else if (infos.length) {
            aviso = { tit: 'Para tener en cuenta', txt: infos.map(a => a.texto).join(' '), accion: null };
        }
        const tarjetaAviso = aviso ? `<div class="pl-alerta"><span class="pl-alerta-ico">${ico('alerta')}</span><div class="pl-alerta-cuerpo"><b>${escP(aviso.tit)}</b><p>${escP(aviso.txt)}</p>
                ${aviso.accion ? `<button type="button" class="pl-link" data-accion="${aviso.accion[0]}" data-id="${p.id_paciente_plan}">${aviso.accion[1]}</button>` : ''}</div></div>` : '';
        const proxima = `<div class="pl-aviso-prox"><span class="pl-prox-etq">PRÓXIMA SESIÓN</span><p>${escP(textoProxima(p))}</p></div>`;

        return `
        <div class="plan-item plan-desplegable pl-card${abierta ? ' plan-abierto' : ''}" data-plan-id="${p.id_paciente_plan}">
            <div class="pl-head">
                <span class="pl-head-ico">${ico('sparkles')}</span>
                <div class="pl-head-main">
                    <div class="pl-head-titulo"><span class="plan-nombre">${escP(p.nombre_plan)}</span><span class="badge-plan ${escP(p.estado)}">${estadoTxt}</span></div>
                    <div class="pl-head-sub">${escP(p.nombre_doctor)} · ${tipoTxt}</div>
                    <div class="pl-meta">
                        <span>${ico('calendar')}<b>${p.citas_asociadas}</b> cita${p.citas_asociadas === 1 ? '' : 's'} asociada${p.citas_asociadas === 1 ? '' : 's'}</span>
                        <span>${ico('activity')}<b>${nSes}</b>${est ? ' de ' + est : ''} sesion${(est || nSes) === 1 ? '' : 'es'}</span>
                        <span>${ico('wallet')}<b>${solM(D.pagado_total)}</b> pagado</span>
                    </div>
                </div>
                <div class="pl-head-acc">${menu}<button type="button" class="pl-ibtn btn-toggle-plan" aria-label="Ver detalles">${ico(abierta ? 'arriba' : 'abajo')}</button></div>
            </div>
            <div class="plan-detalles" style="display:${abierta ? 'grid' : 'none'};">
                <div class="pl-col-resumen">
                    <span class="pl-etq-azul">RESUMEN</span><h5>Información del plan</h5>
                    <div class="pl-kpis">${kpis}</div>
                    <button type="button" class="pl-link pl-link-eco" data-eco="1">Condiciones y detalle económico</button>
                    ${eco}
                    ${tarjetaAviso}${proxima}
                </div>
                <div class="pl-col-historial">
                    <div class="pl-hist-cab"><div><span class="pl-etq-azul">HISTORIAL</span><h5>Sesiones del tratamiento</h5></div><div class="pl-leyenda"></div></div>
                    <div class="pl-linea" data-cargada="0"><p class="pl-ayuda">Cargando...</p></div>
                    ${(permisosPlan.editar || permisosPlan.editar_costos) ? `<div class="pl-acciones"><button type="button" class="pl-btn-principal" data-accion="editar" data-id="${p.id_paciente_plan}">${ico('lapiz')}Editar plan</button></div>` : ''}
                </div>
            </div>
        </div>`;
    }
    const esc2 = v => String(v).replace(/[^a-z]/gi, '');

    function pintarLinea(cont, d) {
        const estadoDe = s => Number(s.costo) <= 0.004 ? ['Sin costo', 'libre', ''] : (Number(s.pagado) >= Number(s.costo) - 0.004 ? ['Pagada', 'pagada', ico('check')] : (Number(s.pagado) > 0.004 ? ['Pago parcial', 'parcial', ''] : ['Pendiente', 'pendiente', '']));
        const cuenta = { pagada: 0, pendiente: 0, libre: 0 };
        const filas = d.sesiones.map(s => {
            const [t, c, ic] = estadoDe(s);
            if (c === 'pagada') cuenta.pagada++; else if (c === 'libre') cuenta.libre++; else cuenta.pendiente++;
            const marcas = [s.adicional ? 'Adicional' : '', s.origen === 'retroactivo' ? 'Cita pasada' : '', s.estado_informe === 'enviado' ? '' : 'Atención en borrador'].filter(Boolean).join(' · ');
            const ap = (s.aparatologia || []).map(a => `<small>Aparatología: ${escP(a.descripcion)} ×${a.cantidad} — ${a.incluida ? 'incluida en el costo' : solM(Number(a.precio_acordado) * a.cantidad) + ', pagado ' + solM(a.pagado)}</small>`).join('');
            const resto = Number(s.costo) > 0.004 && Math.abs(Number(s.costo) - Number(s.pagado)) > 0.004 ? `<small>pagado ${solM(s.pagado)}</small>` : '';
            return `<div class="pl-sesion"><div class="pl-sesion-linea"><span><span class="pl-num">${s.numero}</span></span>
                    <span class="pl-cita-id">#${s.id_cita}</span>
                    <span class="pl-fecha"><b>${escP(fcorta(s.fecha))}</b><small>${escP(String(s.hora || '').slice(0, 5))}</small></span>
                    <span class="pl-doc"><b>${escP(s.doctor)}</b>${ap}${marcas ? `<small>${marcas}</small>` : ''}</span>
                    <span class="pl-estado"><span class="pl-pill pl-pill-${c}">${ic}${t}</span><b>${solM(s.costo)}</b>${resto}</span>
                    <span class="pl-ver"><button type="button" class="pl-ojo" data-cita="${s.id_cita}" title="Ver detalle de la sesión" aria-label="Ver detalle de la sesión ${s.numero}">${ico('ojo')}</button></span></div></div>`;
        }).join('');
        cont.innerHTML = d.sesiones.length ? `<div class="pl-linea-cab"><span>N.º</span><span>Cita</span><span>Fecha y hora</span><span>Odontólogo / detalle</span><span>Estado y costo</span><span></span></div>${filas}` : '<p class="pl-ayuda">Este plan todavía no tiene sesiones registradas.</p>';
        cont.dataset.cargada = '1';
        cont.querySelectorAll('.pl-ojo').forEach(b => b.addEventListener('click', () => abrirDetalleCita(b.dataset.cita)));
        const ley = cont.closest('.plan-item').querySelector('.pl-leyenda');
        if (ley) ley.innerHTML = [['pagada', cuenta.pagada, 'pagada', 'pagadas'], ['pendiente', cuenta.pendiente, 'pendiente', 'pendientes'], ['libre', cuenta.libre, 'sin costo', 'sin costo']]
            .filter(x => x[1] > 0).map(x => `<span><i class="pl-punto pl-punto-${x[0]}"></i>${x[1]} ${x[1] === 1 ? x[2] : x[3]}</span>`).join('');
    }

    function pintarPlanes() {
        const seccion = $id('seccionPlanes'), lista = $id('listaPlanes');
        if (!seccion || !lista) return;
        seccion.style.display = planesFicha.length ? 'block' : 'none';
        const verFin = $id('chkPlanesFinalizados') && $id('chkPlanesFinalizados').checked;
        const visibles = planesFicha.filter(p => verFin || p.estado === 'activo');
        // Las tarjetas nacen CERRADAS; solo se conserva lo que el usuario abrio (para no cerrarla al guardar un cambio)
        const abiertos = new Set([...lista.querySelectorAll('.plan-abierto')].map(e => e.dataset.planId));
        lista.innerHTML = visibles.length ? visibles.map(p => tarjetaPlan(p, abiertos.has(String(p.id_paciente_plan)))).join('') : '<p class="pl-ayuda">No hay planes activos. Marque «Mostrar planes finalizados» para ver los anteriores.</p>';
        lista.querySelectorAll('.pl-card').forEach(item => {
            const cargar = async () => {
                const cont = item.querySelector('.pl-linea');
                if (cont.dataset.cargada === '1') return;
                const d = await plPedir(`${PCTRL}?accion=detalle&id_paciente_plan=${item.dataset.planId}`);
                if (d.success) pintarLinea(cont, d.plan); else cont.innerHTML = `<p class="pl-ayuda">${escP(d.mensaje || 'No se pudo cargar')}</p>`;
            };
            item.querySelector('.btn-toggle-plan').addEventListener('click', () => {
                const detalles = item.querySelector('.plan-detalles'), abrir = detalles.style.display === 'none';
                detalles.style.display = abrir ? 'grid' : 'none'; item.classList.toggle('plan-abierto', abrir);
                item.querySelector('.btn-toggle-plan').innerHTML = ico(abrir ? 'arriba' : 'abajo');
                if (abrir) cargar();
            });
            item.querySelector('[data-eco]').addEventListener('click', () => { const e = item.querySelector('.pl-eco'); e.style.display = e.style.display === 'none' ? 'block' : 'none'; });
            const mb = item.querySelector('[data-menu]');
            if (mb) mb.addEventListener('click', e => { e.stopPropagation(); const m = item.querySelector('.pl-menu'); const abrir = m.style.display === 'none'; lista.querySelectorAll('.pl-menu').forEach(x => { x.style.display = 'none'; }); m.style.display = abrir ? 'block' : 'none'; });
            if (item.classList.contains('plan-abierto')) cargar();
        });
        lista.querySelectorAll('[data-accion]').forEach(b => b.addEventListener('click', e => {
            e.stopPropagation();
            lista.querySelectorAll('.pl-menu').forEach(x => { x.style.display = 'none'; });
            const plan = planesFicha.find(x => String(x.id_paciente_plan) === b.dataset.id);
            if (!plan) return;
            if (b.dataset.accion === 'editar') abrirEditar(plan);
            else if (b.dataset.accion === 'completar') abrirEditar(plan, 'completado');
            else if (b.dataset.accion === 'reabrir') abrirEditar(plan, 'activo');
            else if (b.dataset.accion === 'corregir') abrirCorregir(plan);
        }));
    }
    document.addEventListener('click', () => { document.querySelectorAll('#listaPlanes .pl-menu').forEach(x => { x.style.display = 'none'; }); });

    // ── Detalle de una sesion / cita (el "ojito") ─────────────────
    let citaEnDetalle = null;
    function cerrarDetalleCita() { const m = $id('modalDetalleCita'); if (m) m.style.display = 'none'; citaEnDetalle = null; }
    async function abrirDetalleCita(idCita) {
        const modal = $id('modalDetalleCita'); if (!modal) return;
        citaEnDetalle = Number(idCita);
        $id('sdTitulo').textContent = `Cita #${idCita}`; $id('sdSubtitulo').textContent = '';
        $id('sdCuerpo').innerHTML = '<p class="pl-ayuda">Cargando...</p>'; $id('sdAbrirInforme').style.display = 'none';
        modal.style.display = 'flex';
        const d = await plPedir(`${PCTRL}?accion=detalle_cita&id_cita=${encodeURIComponent(idCita)}`);
        if (!d.success) { $id('sdCuerpo').innerHTML = `<p class="pl-mensaje">${escP(d.mensaje || 'No se pudo cargar el detalle')}</p>`; return; }
        const c = d.cita, inf = d.informe, ses = d.sesion;
        $id('sdTitulo').textContent = `Cita #${c.id_cita}${ses ? ' · Sesión n.º ' + ses.numero : ''}`;
        $id('sdSubtitulo').textContent = `${fechaHora(c.fecha, c.hora)} · ${c.doctor}`;
        const campo = (et, v) => `<div class="sd-campo"><span>${et}</span><p>${v ? escP(v) : '<em>Sin registrar</em>'}</p></div>`;
        const filas = (cab, lista) => lista.length ? `<table class="sd-tabla"><thead><tr>${cab.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${lista.join('')}</tbody></table>` : '';
        let html = `<div class="sd-bloque"><span class="sd-etq">Datos de la cita</span><div class="sd-grid">
            ${campo('Fecha y hora', fechaHora(c.fecha, c.hora))}${campo('Odontólogo', c.doctor)}${campo('Sede', c.nombre_sede)}
            ${campo('Tipo de atención', c.tipo_atencion)}${campo('Estado', c.estado_cita + (c.origen === 'retroactivo' ? ' · cita pasada' : ''))}${campo('Duración', c.duracion_minutos ? c.duracion_minutos + ' min' : '')}
            </div>${campo('Motivo de la cita', c.motivo)}</div>`;
        if (ses) html += `<div class="sd-bloque"><span class="sd-etq">Sesión del plan</span><p class="sd-linea">Sesión n.º ${ses.numero} de <b>${escP(ses.nombre_plan)}</b>${ses.adicional ? ' · adicional' : ''} · costo ${solM(ses.costo)} · pagado ${solM(ses.pagado)}</p></div>`;
        html += `<div class="sd-bloque"><span class="sd-etq">Informe de atención</span>`;
        if (!inf) html += '<p class="sd-vacio">Esta cita todavía no tiene informe.</p>';
        else html += `<p class="sd-linea">${inf.estado === 'enviado' ? 'Enviado a cobrar' + (inf.fecha_envio ? ' el ' + fechaHora(inf.fecha_envio, String(inf.fecha_envio).split(' ')[1]) : '') : 'Guardado como borrador (todavía no se envía a cobrar)'}${inf.nombre_plan ? ' · plan ' + escP(inf.nombre_plan) : ''}</p>
            ${campo('Motivo de consulta', inf.motivo_consulta)}${campo('Hallazgos clínicos', inf.diagnostico)}${campo('Tratamiento realizado', inf.tratamiento_realizado)}
            <div class="sd-grid sd-grid-2">${campo('Materiales', inf.materiales)}${campo('Observaciones', inf.observaciones)}</div>`;
        html += '</div>';
        if (d.servicios.length) html += `<div class="sd-bloque"><span class="sd-etq">Servicios cobrados</span>${filas(['Servicio', 'Cant.', 'Precio', 'Subtotal', 'Pagado'], d.servicios.map(x => `<tr><td>${escP(x.nombre)}</td><td>${x.cantidad}</td><td>${solM(x.precio_unitario)}</td><td>${solM(x.subtotal)}</td><td>${solM(x.pagado)}</td></tr>`))}</div>`;
        if (d.aparatologia.length) html += `<div class="sd-bloque"><span class="sd-etq">Aparatología</span>${filas(['Pieza', 'Cant.', 'Precio acordado', 'Pagado'], d.aparatologia.map(x => `<tr><td>${escP(x.descripcion)}</td><td>${x.cantidad}</td><td>${Number(x.incluida) ? 'Incluida en el costo' : solM(Number(x.precio_acordado) * x.cantidad)}</td><td>${Number(x.incluida) ? '—' : solM(x.pagado)}</td></tr>`))}</div>`;
        html += `<div class="sd-bloque"><span class="sd-etq">Pagos de esta cita</span>` + (d.pagos.length
            ? filas(['Fecha', 'Concepto', 'Método', 'Monto'], d.pagos.map(x => `<tr class="${Number(x.anulado) || x.id_pago_anulado ? 'sd-anulado' : ''}"><td>${escP(fcorta(x.fecha_pago))}${x.origen === 'retroactivo' ? ' <small>fecha pasada</small>' : ''}</td><td>${x.id_pago_anulado ? 'Anulación: ' : ''}${escP(x.concepto)}${Number(x.anulado) ? ' <small>anulado</small>' : ''}</td><td>${escP(x.metodo || 'No registrado')}${x.nro_comprobante ? ' · N.º ' + escP(x.nro_comprobante) : ''}</td><td>${solM(x.monto)}</td></tr>`))
            : '<p class="sd-vacio">Todavía no hay pagos registrados para esta cita.</p>') + '</div>';
        $id('sdCuerpo').innerHTML = html;
        if (PUEDE_REGISTRAR && typeof window.abrirInforme === 'function' && (inf || c.estado_cita === 'Completada')) {
            $id('sdAbrirInforme').textContent = inf ? 'Abrir informe' : 'Registrar informe'; $id('sdAbrirInforme').style.display = '';
        }
    }
    window.verDetalleCita = abrirDetalleCita;
    $id('sdCerrar') && $id('sdCerrar').addEventListener('click', cerrarDetalleCita);
    $id('sdCerrar2') && $id('sdCerrar2').addEventListener('click', cerrarDetalleCita);
    $id('sdAbrirInforme') && $id('sdAbrirInforme').addEventListener('click', () => {
        const id = citaEnDetalle; cerrarDetalleCita();
        if (id) window.abrirInforme(id, () => { window._at_cargarCitas(); window._at_cargarPlanesActivos(); });
    });
    $id('modalDetalleCita') && $id('modalDetalleCita').addEventListener('click', e => { if (e.target === $id('modalDetalleCita')) cerrarDetalleCita(); });
    $id('chkPlanesFinalizados') && $id('chkPlanesFinalizados').addEventListener('change', pintarPlanes);

    // ── Editar plan ───────────────────────────────────────────────
    let planEnEdicion = null;
    const num = x => { const v = $id(x).value; return v === '' ? null : Number(v); };
    function valoresFormulario() {
        return { estado: $id('epEstado').value, fecha_fin_est: $id('epFechaFin').value || null, notas: $id('epNotas').value.trim() || null, tipo: $id('epTipo').value,
                 costo_acordado: num('epCostoTotal'), en_cuotas: $id('epEnCuotas').checked ? 1 : 0, sesiones_pago_est: num('epNumCuotas'), costo_estimado_sesion: num('epCostoSesion'), cuota_inicial: num('epCuotaIni') || 0 };
    }
    function valoresPlan(p) {
        return { estado: p.estado, fecha_fin_est: p.fecha_fin_est || null, notas: p.notas || null, tipo: p.tipo, costo_acordado: p.costo_acordado === null || p.costo_acordado === '' ? null : Number(p.costo_acordado),
                 en_cuotas: Number(p.en_cuotas) ? 1 : 0, sesiones_pago_est: p.sesiones_pago_est ? Number(p.sesiones_pago_est) : null, costo_estimado_sesion: p.costo_estimado_sesion === null || p.costo_estimado_sesion === '' ? null : Number(p.costo_estimado_sesion), cuota_inicial: Number(p.cuota_inicial) || 0 };
    }
    const FIN = ['tipo', 'costo_acordado', 'en_cuotas', 'sesiones_pago_est', 'costo_estimado_sesion', 'cuota_inicial'];
    function cambiosEdicion() {
        const ahora = valoresFormulario(), antes = valoresPlan(planEnEdicion), c = {};
        Object.keys(ahora).forEach(k => {
            if (FIN.includes(k) && !permisosPlan.editar_costos) return;
            if (!FIN.includes(k) && !permisosPlan.editar) return;
            const a = antes[k], b = ahora[k];
            const igual = (a === null || b === null) ? a === b : (typeof a === 'number' && typeof b === 'number' ? Math.abs(a - b) < 0.005 : String(a) === String(b));
            if (!igual) c[k] = b;
        });
        // segun el tipo, algunos datos no aplican o son DERIVADOS (los recalcula el servidor): no cuentan como cambio del usuario
        if (ahora.tipo === 'costo_total') delete c.costo_estimado_sesion;                       // = costo / cuotas, lo calcula el sistema
        if (c.tipo === 'por_sesion') { delete c.costo_acordado; delete c.sesiones_pago_est; delete c.en_cuotas; }
        return c;
    }
    function actualizarFormularioPlan() {
        const total = $id('epTipo').value === 'costo_total';
        $id('epGrCostoTotal').style.display = total ? '' : 'none';
        $id('epGrCuotas').style.display = total ? '' : 'none';
        $id('epGrCostoSesion').style.display = total ? 'none' : '';
        $id('epNumCuotas').style.display = total && $id('epEnCuotas').checked ? '' : 'none';
        const n = Number($id('epNumCuotas').value) || 0, costo = Number($id('epCostoTotal').value) || 0;
        $id('epPrevCuota').textContent = total && $id('epEnCuotas').checked && n > 0 && costo > 0 ? `${n} cuotas de ${sol(costo / n)} (la última ajusta los centavos). Las sesiones después de la cuota ${n} no se cobran.` : '';
        const c = cambiosEdicion(), pideMotivo = ('estado' in c) || Object.keys(c).some(k => FIN.includes(k));
        $id('epMotivoReq').style.display = pideMotivo ? '' : 'none';
        $id('epRecalcular').closest('label').style.display = Object.keys(c).some(k => FIN.includes(k)) ? '' : 'none';
    }
    function abrirEditar(p, estadoSugerido) {
        planEnEdicion = p;
        $id('epIdPlan').value = p.id_paciente_plan; $id('epSubtitulo').textContent = `${p.nombre_plan} · inició ${fcorta(p.fecha_inicio)}`;
        $id('epEstado').value = estadoSugerido || p.estado; $id('epFechaFin').value = p.fecha_fin_est || ''; $id('epNotas').value = p.notas || '';
        $id('epTipo').value = p.tipo; $id('epCostoTotal').value = p.costo_acordado || ''; $id('epEnCuotas').checked = !!Number(p.en_cuotas); $id('epNumCuotas').value = p.sesiones_pago_est || '';
        $id('epCostoSesion').value = p.tipo === 'por_sesion' && p.costo_estimado_sesion ? p.costo_estimado_sesion : ''; $id('epCuotaIni').value = Number(p.cuota_inicial) || 0;
        $id('epRecalcular').checked = true; $id('epMotivo').value = ''; $id('epMensaje').style.display = 'none';
        ['epEstado', 'epFechaFin', 'epNotas'].forEach(x => { $id(x).disabled = !permisosPlan.editar; });
        ['epTipo', 'epCostoTotal', 'epEnCuotas', 'epNumCuotas', 'epCostoSesion', 'epCuotaIni', 'epRecalcular'].forEach(x => { $id(x).disabled = !permisosPlan.editar_costos; });
        const av = $id('epAvisoDinero');
        av.style.display = permisosPlan.editar_costos ? 'none' : 'block';
        av.textContent = permisosPlan.editar_costos ? '' : 'Solo quien tiene el permiso «Editar costos» puede cambiar costos y cuotas. Pídaselo al administrador.';
        actualizarFormularioPlan();
        $id('modalEditarPlan').style.display = 'flex';
    }
    function cerrarEditar() { $id('modalEditarPlan').style.display = 'none'; planEnEdicion = null; }
    function mensajeEdicion(t) { const m = $id('epMensaje'); m.textContent = t || ''; m.style.display = t ? 'block' : 'none'; }
    async function guardarEdicion() {
        if (!planEnEdicion) return;
        mensajeEdicion('');
        const cambios = cambiosEdicion();
        if (!Object.keys(cambios).length) return mensajeEdicion('No hay cambios que guardar');
        const pideMotivo = ('estado' in cambios) || Object.keys(cambios).some(k => FIN.includes(k));
        if (pideMotivo && $id('epMotivo').value.trim().length < 5) return mensajeEdicion('Indique el motivo del cambio (mínimo 5 caracteres)');
        const b = $id('epGuardar'); b.disabled = true;
        const r = await plPedir(`${PCTRL}?accion=editar`, { method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_paciente_plan: planEnEdicion.id_paciente_plan, cambios, motivo: $id('epMotivo').value.trim(), recalcular: $id('epRecalcular').checked }) });
        b.disabled = false;
        if (!r.success) return mensajeEdicion(r.mensaje || 'No se pudo guardar');
        let txt = r.mensaje || 'Plan actualizado';
        if (r.sesiones_recalculadas) txt += ` · ${r.sesiones_recalculadas} ${plural(r.sesiones_recalculadas, 'sesión recalculada', 'sesiones recalculadas')}`;
        const cp = r.sesiones_con_pagos_sin_cambiar || [];
        if (cp.length) txt += ` · ${plural(cp.length, 'la sesión', 'las sesiones')} ${cp.join(', ')} ${plural(cp.length, 'ya tiene pagos y no se tocó', 'ya tienen pagos y no se tocaron')}`;
        plToast(txt, 'exito'); cerrarEditar(); cargarPlanesActivos();
    }
    ['epEstado', 'epFechaFin', 'epNotas', 'epTipo', 'epCostoTotal', 'epEnCuotas', 'epNumCuotas', 'epCostoSesion', 'epCuotaIni'].forEach(x => { const el = $id(x); if (el) { el.addEventListener('input', actualizarFormularioPlan); el.addEventListener('change', actualizarFormularioPlan); } });
    $id('epGuardar') && $id('epGuardar').addEventListener('click', guardarEdicion);
    $id('epCancelar') && $id('epCancelar').addEventListener('click', cerrarEditar);
    $id('epCerrar') && $id('epCerrar').addEventListener('click', cerrarEditar);

    // ── Corregir costos ───────────────────────────────────────────
    let planEnCorreccion = null;
    async function abrirCorregir(p) {
        planEnCorreccion = p; $id('ccSubtitulo').textContent = p.nombre_plan; $id('ccMensaje').style.display = 'none';
        $id('ccTabla').innerHTML = '<p class="pl-ayuda">Cargando...</p>'; $id('ccResumen').textContent = ''; $id('ccAplicar').disabled = true;
        $id('modalCorregir').style.display = 'flex';
        const d = await plPedir(`${PCTRL}?accion=previsualizar_recalculo&id_paciente_plan=${p.id_paciente_plan}`);
        if (!d.success) { $id('ccTabla').innerHTML = `<p class="pl-mensaje" style="display:block;">${escP(d.mensaje || 'No se pudo cargar')}</p>`; return; }
        const etq = { igual: 'Correcta', corregir: 'Se corregirá', con_pagos: 'No se toca: tiene pagos' };
        $id('ccTabla').innerHTML = '<table class="pl-tabla"><thead><tr><th>Sesión</th><th>Costo actual</th><th>Según el plan</th><th>Pagado</th><th>Qué pasará</th></tr></thead><tbody>' +
            d.filas.map(f => `<tr class="${f.accion}"><td>#${f.numero}${f.adicional ? ' <small>(adicional)</small>' : ''}</td><td>${sol(f.costo_actual)}</td><td>${sol(f.costo_regla)}</td><td>${sol(f.pagado)}</td><td>${etq[f.accion]}</td></tr>`).join('') + '</tbody></table>';
        const sesTxt = n => `${n} ${plural(n, 'sesión', 'sesiones')}`;
        const deMas = d.pagado_de_mas > 0 ? ` Hay ${sol(d.pagado_de_mas)} cobrados de más en ${plural(d.con_pagos, 'esa sesión', 'esas sesiones')}: si corresponde, anule esos pagos y vuelva a corregir.` : '';
        $id('ccResumen').innerHTML = d.a_corregir
            ? `Se ${plural(d.a_corregir, 'corregirá', 'corregirán')} <strong>${sesTxt(d.a_corregir)}</strong>: <strong>${sol(d.diferencia)}</strong> menos por cobrar.` + (d.con_pagos ? ` ${sesTxt(d.con_pagos)} con pagos no se ${plural(d.con_pagos, 'toca', 'tocan')}.${deMas}` : '')
            : (d.con_pagos ? `No hay sesiones sin pagos por corregir. ${sesTxt(d.con_pagos)} con pagos no se ${plural(d.con_pagos, 'toca', 'tocan')}.${deMas}` : 'Todas las sesiones ya tienen el costo correcto.');
        $id('ccAplicar').disabled = !d.a_corregir; $id('ccAplicar').textContent = d.a_corregir ? `Corregir ${sesTxt(d.a_corregir)}` : 'Corregir';
    }
    function cerrarCorregir() { $id('modalCorregir').style.display = 'none'; planEnCorreccion = null; }
    async function aplicarCorreccion() {
        if (!planEnCorreccion) return;
        $id('ccAplicar').disabled = true;
        const r = await plPedir(`${PCTRL}?accion=aplicar_recalculo`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id_paciente_plan: planEnCorreccion.id_paciente_plan }) });
        if (!r.success) { $id('ccMensaje').textContent = r.mensaje || 'No se pudo corregir'; $id('ccMensaje').style.display = 'block'; $id('ccAplicar').disabled = false; return; }
        plToast(r.mensaje || 'Costos corregidos', 'exito'); cerrarCorregir(); cargarPlanesActivos();
    }
    $id('ccAplicar') && $id('ccAplicar').addEventListener('click', aplicarCorreccion);
    $id('ccCancelar') && $id('ccCancelar').addEventListener('click', cerrarCorregir);
    $id('ccCerrar') && $id('ccCerrar').addEventListener('click', cerrarCorregir);

    // ── Citas ─────────────────────────────────────────────────────
    function cargarCitas() {
        document.getElementById('listaCitas').innerHTML = '<p class="cargando-at">Cargando...</p>';
        fetch(`../CONTROLADORES/controlador_atenciones.php?accion=listar_citas&id_paciente=${ID_PACIENTE}`)
            .then(r => r.json())
            .then(citas => { todasLasCitas = citas; aplicarFiltro(); });
    }

    function aplicarFiltro() {
        let citas = [...todasLasCitas];
        if (filtroActivo === 'sin_informe') {
            citas = citas.filter(c => !c.id_informe && c.id_estado_cita == 4);
        } else if (filtroActivo === 'por_servicio') {
            const val = document.getElementById('selectFiltroServicio').value;
            if (val) citas = citas.filter(c => c.id_tipo_servicio == val);
        } else if (filtroActivo === 'por_plan') {
            const val = document.getElementById('selectFiltroPlan').value;
            if (val) citas = citas.filter(c => c.id_plan == val);
        } else if (filtroActivo === 'pendiente_cobro') {
            citas = citas.filter(c => c.id_informe && c.estado_informe === 'borrador');
        }
        document.getElementById('totalCitas').textContent = citas.length;
        renderCitas(citas);
    }

    function renderCitas(citas) {
        const lista = document.getElementById('listaCitas');
        if (!citas.length) {
            lista.innerHTML = '<div class="at-sin-datos"><p>No hay citas para mostrar.</p></div>';
            return;
        }

        lista.innerHTML = citas.map(c => {
            const tieneInforme = !!c.id_informe;
            const esCompletada = c.id_estado_cita == 4;
            const estadoInf    = c.estado_informe;

            // Badge estado
            let badge = '';
            if (!esCompletada) {
                badge = `<span class="at-badge pendiente">${c.estado}</span>`;
            } else if (!tieneInforme) {
                badge = `<span class="at-badge sin-informe">Sin informe</span>`;
            } else if (estadoInf === 'enviado') {
                badge = `<span class="at-badge enviado">Enviado a cobrar</span>`;
            } else {
                badge = `<span class="at-badge borrador-inf">Informe guardado</span><span class="at-badge pendiente-cobro">Pendiente de cobro</span>`;
            }

            // Botón acción
            let btnAccion = '';
            if (esCompletada && !tieneInforme && PUEDE_REGISTRAR) {
                btnAccion = `<button class="at-btn-accion registrar" onclick="window.abrirInforme(${c.id_cita}, () => { window._at_cargarCitas(); window._at_cargarPlanesActivos(); })">Registrar informe</button>`;
            } else if (tieneInforme && PUEDE_REGISTRAR) {
                btnAccion = `<button class="at-btn-accion editar" onclick="window.abrirInforme(${c.id_cita}, () => { window._at_cargarCitas(); window._at_cargarPlanesActivos(); })">Ver informe</button>`;
            }

            const btnVer = `<button type="button" class="at-btn-accion ver" onclick="window.verDetalleCita(${c.id_cita})" title="Ver el detalle de esta cita">${ico('ojo')} Ver detalle</button>`;

            // Botón pago
            let btnPago = '';
            if (tieneInforme && estadoInf === 'enviado' && PUEDE_PAGAR) {
                btnPago = `<button class="at-btn-accion pago" onclick="window.abrirModalPago(${c.id_cita},${ID_PACIENTE})">Registrar pago</button>`;
            }

            return `
                <div class="cita-at-item ${!tieneInforme && esCompletada ? 'pendiente-informe' : ''} ${tieneInforme && estadoInf === 'borrador' ? 'por-cobrar' : ''}" data-id="${c.id_cita}"> <div class="cita-at-header"> <div class="cita-at-id">#${c.id_cita}</div> ${badge}
                        <span class="cita-at-fecha">${formatFecha(c.fecha)} · ${c.hora?.substring(0,5)||''}</span> <span class="cita-at-servicio">${c.nombre_servicio||'—'}</span> <span class="cita-at-doctor">${c.nombre_doctor}</span> ${c.nombre_plan ? `<span class="cita-at-plan">${c.nombre_plan}</span>` : ''}
                    </div> ${tieneInforme ? `
                        <div class="cita-at-detalle"> ${c.diagnostico ? `<p><strong>Hallazgos:</strong> ${c.diagnostico}</p>` : ''}
                            ${c.tratamiento_realizado ? `<p><strong>Tratamiento:</strong> ${c.tratamiento_realizado}</p>` : ''}
                        </div>` : ''}
                    <div class="cita-at-footer"> <div class="cita-at-acciones"> ${btnVer}${btnAccion}
                            ${btnPago}
                        </div> </div> </div>`;
        }).join('');

        // Resaltar cita si viene desde Mi Agenda
        const idResaltar = sessionStorage.getItem('resaltar_cita');
        if (idResaltar) {
            sessionStorage.removeItem('resaltar_cita');
            const target = lista.querySelector(`[data-id="${idResaltar}"]`);
            if (target) {
                target.classList.add('cita-resaltada');
                setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 150);
                setTimeout(() => target.classList.remove('cita-resaltada'), 3500);
            }
        }
    }
    // ── Modal pago ────────────────────────────────────────────────
    window.abrirModalPago = function(id_cita) {
        document.getElementById('pago_id_cita').value    = id_cita;
        document.getElementById('pagoMonto').value       = '';
        document.getElementById('pagoObservacion').value = '';
        document.getElementById('pagoComprobante').value = '';
        cargarMetodosPago();
        document.getElementById('resumenCobro').innerHTML = '<p style="font-size:13px;color:#636e72;">Registra el pago correspondiente a esta cita.</p>';
        document.getElementById('modalPago').style.display = 'flex';
    };

    // ── Metodos de pago (catalogo editable en Configuracion) ──────
    let metodosPago = [];
    function cargarMetodosPago() {
        const sel = document.getElementById('pagoMetodo');
        fetch('../CONTROLADORES/controlador_atenciones.php?accion=metodos_pago')
            .then(r => r.json())
            .then(d => {
                metodosPago = (d && d.success) ? d.metodos : [];
                sel.innerHTML = '<option value="">Seleccione...</option>' + metodosPago.map(m =>
                    `<option value="${Number(m.id_metodo_pago)}" data-ref="${Number(m.requiere_referencia)}">${String(m.nombre).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</option>`).join('');
                actualizarReqComprobante();
            })
            .catch(() => { sel.innerHTML = '<option value="">No se pudieron cargar los métodos</option>'; });
    }
    function actualizarReqComprobante() {
        const sel = document.getElementById('pagoMetodo');
        const req = sel.selectedOptions[0] && sel.selectedOptions[0].dataset.ref === '1';
        document.getElementById('pagoComprobanteReq').style.display = req ? 'inline' : 'none';
    }

    // ── Registrar listeners globales ──────────────────────────────
    function registrarListeners() {

        document.getElementById('pagoMetodo').addEventListener('change', actualizarReqComprobante);

        // Confirmar pago
        document.getElementById('btnConfirmarPago').onclick = () => {
            const monto = parseFloat(document.getElementById('pagoMonto').value || 0);
            if (monto <= 0) { mostrarMensaje('El monto debe ser mayor a 0', 'error'); return; }
            const metodo = document.getElementById('pagoMetodo').value;
            if (!metodo) { mostrarMensaje('Seleccione el método de pago', 'error'); return; }
            const fd = new FormData();
            fd.append('id_metodo_pago',  metodo);
            fd.append('nro_comprobante', document.getElementById('pagoComprobante').value.trim());
            fd.append('accion',      'registrar_pago');
            fd.append('id_paciente', ID_PACIENTE);
            fd.append('id_cita',     document.getElementById('pago_id_cita').value);
            fd.append('monto',       monto);
            fd.append('observacion', document.getElementById('pagoObservacion').value);
            fetch('../CONTROLADORES/controlador_atenciones.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensaje(data.anticipo > 0 ? 'Pago registrado. S/ ' + Number(data.anticipo).toFixed(2) + ' quedaron como saldo a favor del paciente' : 'Pago registrado', 'exito');
                        document.getElementById('modalPago').style.display = 'none';
                        cargarCitas();
                    } else {
                        mostrarMensaje(data.mensaje || 'Error', 'error');
                    }
                });
        };

        // Filtros
        document.querySelectorAll('.btn-filtro-at').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('.btn-filtro-at').forEach(b => b.classList.remove('activo'));
                btn.classList.add('activo');
                filtroActivo = btn.dataset.filtro;
                const secFilt = document.getElementById('filtrosSecundarios');
                const selServ = document.getElementById('selectFiltroServicio');
                const selPlan = document.getElementById('selectFiltroPlan');
                selServ.style.display = 'none';
                selPlan.style.display = 'none';
                secFilt.style.display = 'none';
                if (filtroActivo === 'por_servicio') { secFilt.style.display = 'flex'; selServ.style.display = 'block'; }
                if (filtroActivo === 'por_plan')     { secFilt.style.display = 'flex'; selPlan.style.display = 'block'; }
                aplicarFiltro();
            };
        });

        document.getElementById('selectFiltroServicio').onchange = aplicarFiltro;
        document.getElementById('selectFiltroPlan').onchange     = aplicarFiltro;

        // Cerrar modal pago
        document.getElementById('btnCerrarPago')?.addEventListener('click', () => {
            document.getElementById('modalPago').style.display = 'none';
        });
        document.getElementById('btnCancelarPago')?.addEventListener('click', () => {
            document.getElementById('modalPago').style.display = 'none';
        });
    }

    // ── Helpers ───────────────────────────────────────────────────
    function formatFecha(str) {
        if (!str) return '—';
        const [y, m, d] = str.split('-');
        const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        return `${parseInt(d)} ${meses[parseInt(m)-1]} ${y}`;
    }

    function mostrarMensaje(msg, tipo = 'exito') {
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        aviso.innerHTML = `<span class="texto">${msg}</span>`;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.classList.add('mostrar'), 50);
        setTimeout(() => { aviso.classList.remove('mostrar'); setTimeout(() => aviso.remove(), 400); }, 3000);
    }

})();