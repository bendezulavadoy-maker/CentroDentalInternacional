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

    // ── Planes activos ────────────────────────────────────────────
    function cargarPlanesActivos() {
        fetch(`../CONTROLADORES/controlador_atenciones.php?accion=listar_planes_activos&id_paciente=${ID_PACIENTE}`)
            .then(r => r.json())
            .then(planes => {
                pacientePlanes = planes;
                const seccion = document.getElementById('seccionPlanes');
                const lista   = document.getElementById('listaPlanes');
                if (!planes.length) { seccion.style.display = 'none'; return; }
                seccion.style.display = 'block';
                lista.innerHTML = planes.map(p => {
                    const costo = p.costo_acordado ? `S/ ${parseFloat(p.costo_acordado).toFixed(2)}` : '—';
                    const tipo  = p.tipo === 'costo_total' ? 'Costo fijo' : 'Por sesión';
                    const enCuotas = parseInt(p.en_cuotas) ? 'Sí' : 'No';
                    const cuotaInicial = p.cuota_inicial ? `S/ ${parseFloat(p.cuota_inicial).toFixed(2)}` : '—';
                    const costoSesion = p.costo_estimado_sesion ? `S/ ${parseFloat(p.costo_estimado_sesion).toFixed(2)}` : '—';
                    const fechaInicio = p.fecha_inicio ? p.fecha_inicio.split(' ')[0].split('-').reverse().join('/') : '—';
                    const notas = p.notas || '';

                    return `
                    <div class="plan-item plan-desplegable" data-plan-id="${p.id_paciente_plan}"> <div class="plan-cabecera"> <div class="plan-info"> <span class="plan-nombre">${p.nombre_plan}</span> <span class="plan-doctor">${p.nombre_doctor} · ${tipo} · ${costo}</span> </div> <div class="plan-cabecera-right"> <span class="badge-plan activo">Activo</span> <button class="btn-toggle-plan" aria-label="Ver detalles">▼</button> </div> </div> <div class="plan-detalles" style="display:none;"> <div class="plan-grid-detalle"> <div class="plan-dato"><span class="plan-dato-label">Tipo de cobro</span><span class="plan-dato-valor">${tipo}</span></div> <div class="plan-dato"><span class="plan-dato-label">Costo acordado</span><span class="plan-dato-valor">${costo}</span></div> <div class="plan-dato"><span class="plan-dato-label">En cuotas</span><span class="plan-dato-valor">${enCuotas}</span></div> ${parseInt(p.en_cuotas) ? `<div class="plan-dato"><span class="plan-dato-label">Cuota inicial</span><span class="plan-dato-valor">${cuotaInicial}</span></div>` : ''}
                                ${p.tipo !== 'costo_total' ? `<div class="plan-dato"><span class="plan-dato-label">Costo por sesión</span><span class="plan-dato-valor">${costoSesion}</span></div>` : ''}
                                ${p.sesiones_pago_est ? `<div class="plan-dato"><span class="plan-dato-label">Sesiones estimadas</span><span class="plan-dato-valor">${p.sesiones_pago_est}</span></div>` : ''}
                                <div class="plan-dato"><span class="plan-dato-label">Fecha inicio</span><span class="plan-dato-valor">${fechaInicio}</span></div> </div> ${notas ? `<div class="plan-notas"><span class="plan-dato-label">Notas</span><p>${notas}</p></div>` : ''}
                        </div> </div>`;
                }).join('');

                // Eventos de desplegable
                lista.querySelectorAll('.plan-desplegable').forEach(item => {
                    item.querySelector('.btn-toggle-plan').addEventListener('click', () => {
                        const detalles = item.querySelector('.plan-detalles');
                        const btn      = item.querySelector('.btn-toggle-plan');
                        const abierto  = detalles.style.display !== 'none';
                        detalles.style.display = abierto ? 'none' : 'block';
                        btn.textContent = abierto ? '▼' : '▲';
                        item.classList.toggle('plan-abierto', !abierto);
                    });
                });
            });
    }

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
                    <div class="cita-at-footer"> <div class="cita-at-acciones"> ${btnAccion}
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
        document.getElementById('resumenCobro').innerHTML = '<p style="font-size:13px;color:#636e72;">Registra el pago correspondiente a esta cita.</p>';
        document.getElementById('modalPago').style.display = 'flex';
    };

    // ── Registrar listeners globales ──────────────────────────────
    function registrarListeners() {

        // Confirmar pago
        document.getElementById('btnConfirmarPago').onclick = () => {
            const monto = parseFloat(document.getElementById('pagoMonto').value || 0);
            if (monto <= 0) { mostrarMensaje('El monto debe ser mayor a 0', 'error'); return; }
            const fd = new FormData();
            fd.append('accion',      'registrar_pago');
            fd.append('id_paciente', ID_PACIENTE);
            fd.append('id_cita',     document.getElementById('pago_id_cita').value);
            fd.append('monto',       monto);
            fd.append('observacion', document.getElementById('pagoObservacion').value);
            fetch('../CONTROLADORES/controlador_atenciones.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensaje('Pago registrado', 'exito');
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