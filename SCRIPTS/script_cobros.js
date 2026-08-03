/**
 * MÓDULO COBROS
 * Panel buzón + registro de pagos para asistente/admin
 */
(function () {

    const CTRL = '../CONTROLADORES/controlador_cobros.php';
    let pacienteActivo = null;
    let tabActiva      = 'cobro';

    // ── Helpers ───────────────────────────────────────────────────
    function fmt(n) { return 'S/ ' + parseFloat(n || 0).toFixed(2); }

    function fmtFecha(str) {
        if (!str) return '—';
        const [y, m, d] = str.split('-');
        return `${d}/${m}/${y}`;
    }

    function mostrarMsg(msg, tipo = 'exito') {
        const el = document.createElement('div');
        el.className = `cobros-mensaje ${tipo}`;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.classList.add('mostrar'), 30);
        setTimeout(() => { el.classList.remove('mostrar'); setTimeout(() => el.remove(), 300); }, 3500);
    }

    function pendiente(total, pagado) {
        return Math.max(0, parseFloat(total || 0) - parseFloat(pagado || 0));
    }

    // ── Buzón ─────────────────────────────────────────────────────
    function cargarBuzon() {
        fetch(`${CTRL}?accion=listar_buzon`)
            .then(r => r.json())
            .then(lista => {
                const cont = document.getElementById('cobrosBuzonLista');
                document.getElementById('cobroContador').textContent = lista.length;

                if (!lista.length) {
                    cont.innerHTML = '<p class="cobros-cargando">Sin cobros pendientes</p>';
                    return;
                }

                cont.innerHTML = lista.map(p => `
                    <div class="cobros-paciente-item" data-id="${p.id_paciente}">
                        <div class="cobros-pac-nombre">${p.nombre_paciente}</div>
                        <div class="cobros-pac-meta">
                            <span>DNI ${p.dni_paciente}</span>
                            <span class="cobros-pac-badge">${p.total_informes} informe${p.total_informes > 1 ? 's' : ''}</span>
                        </div>
                    </div>`).join('');

                cont.querySelectorAll('.cobros-paciente-item').forEach(item => {
                    item.addEventListener('click', () => {
                        cont.querySelectorAll('.cobros-paciente-item').forEach(i => i.classList.remove('activo'));
                        item.classList.add('activo');
                        const pac = lista.find(p => String(p.id_paciente) === item.dataset.id);
                        pacienteActivo = pac;
                        cargarDetalle(pac);
                    });
                });
            });
    }

    // ── Detalle ───────────────────────────────────────────────────
    function cargarDetalle(pac) {
        document.getElementById('cobrosDetalleVacio').style.display = 'none';
        const cont = document.getElementById('cobrosDetalleContenido');
        cont.style.display = 'block';
        cont.innerHTML = '<p class="cobros-cargando">Cargando...</p>';

        fetch(`${CTRL}?accion=detalle_cobro&id_paciente=${pac.id_paciente}`)
            .then(r => r.json())
            .then(data => renderDetalle(pac, data));
    }

    function renderDetalle(pac, data) {
        const cont = document.getElementById('cobrosDetalleContenido');

        let html = `
            <div class="cobros-det-header">
                <div>
                    <p class="cobros-det-nombre">${pac.nombre_paciente}</p>
                    <p class="cobros-det-sub">DNI ${pac.dni_paciente}</p>
                </div>
                <button class="cobros-btn-registrar" id="btnAbrirPago">Registrar pagos</button>
            </div>
            <div class="cobros-tabs">
                <button class="cobros-tab ${tabActiva === 'cobro' ? 'activo' : ''}" data-tab="cobro">Cobro</button>
                <button class="cobros-tab ${tabActiva === 'saldos' ? 'activo' : ''}" data-tab="saldos">Resumen de saldos</button>
            </div>
            <div id="cobrosTabContenido">
                ${tabActiva === 'cobro' ? renderTabCobro(data) : ''}
            </div>`;

        cont.innerHTML = html;

        cont.querySelectorAll('.cobros-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                tabActiva = tab.dataset.tab;
                cont.querySelectorAll('.cobros-tab').forEach(t => t.classList.remove('activo'));
                tab.classList.add('activo');
                const tc = document.getElementById('cobrosTabContenido');
                if (tabActiva === 'cobro') {
                    tc.innerHTML = renderTabCobro(data);
                } else {
                    tc.innerHTML = '<p class="cobros-cargando">Cargando...</p>';
                    cargarSaldos(pac.id_paciente);
                }
            });
        });

        document.getElementById('btnAbrirPago').addEventListener('click', abrirModalPago);
    }

    // ── Tab Cobro ─────────────────────────────────────────────────
    function renderTabCobro(data) {
        let html = '';

        // ── 1. DEUDAS ANTERIORES (primero, obligatorio saldar) ────
        if (data.deudas_anteriores && data.deudas_anteriores.length) {
            data.deudas_anteriores.forEach(deuda => {
                html += `<div class="cobros-seccion cobros-sec-deuda">
                    <p class="cobros-sec-titulo">Deuda anterior — ${deuda.nombre_plan}</p>
                    <p class="cobros-sec-nota">Estos montos deben saldarse antes de registrar el cobro de la cita actual.</p>`;

                // Sesiones anteriores con deuda
                if (deuda.sesiones && deuda.sesiones.length) {
                    html += rowsHeader('Sesión anterior', true);
                    deuda.sesiones.forEach(ses => {
                        const pend = pendiente(ses.costo_sesion, ses.pagado);
                        html += `<div class="cobros-fila">
                            <span class="cobros-fila-nombre">Sesión #${ses.numero_sesion} — ${fmtFecha(ses.fecha)}</span>
                            <span class="cobros-fila-monto">${fmt(ses.costo_sesion)}</span>
                            <span class="cobros-fila-pagado">${fmt(ses.pagado)}</span>
                            <span class="cobros-fila-pendiente">${fmt(pend)}</span>
                            <span class="cobros-fila-input">
                                <input type="number" step="0.01" min="0" max="${pend}"
                                       class="cobros-monto-input cobros-input-deuda"
                                       data-tipo="sesion"
                                       data-id-sesion="${ses.id_sesion}"
                                       data-id-paciente-plan="${deuda.id_paciente_plan}"
                                       data-id-cita="${ses.id_cita}"
                                       placeholder="${pend.toFixed(2)}">
                            </span>
                        </div>`;
                    });
                }

                // Aparatología anterior con deuda
                if (deuda.aparatologia && deuda.aparatologia.length) {
                    html += rowsHeader('Aparatología anterior', true);
                    deuda.aparatologia.forEach(a => {
                        const total = parseFloat(a.precio_acordado) * parseInt(a.cantidad || 1);
                        const pend  = pendiente(total, a.pagado);
                        html += `<div class="cobros-fila">
                            <span class="cobros-fila-nombre">${a.descripcion} x${a.cantidad || 1} — cita ${fmtFecha(a.fecha)}</span>
                            <span class="cobros-fila-monto">${fmt(total)}</span>
                            <span class="cobros-fila-pagado">${fmt(a.pagado)}</span>
                            <span class="cobros-fila-pendiente">${fmt(pend)}</span>
                            <span class="cobros-fila-input">
                                <input type="number" step="0.01" min="0" max="${pend}"
                                       class="cobros-monto-input cobros-input-deuda"
                                       data-tipo="aparatologia"
                                       data-id-aparatologia-item="${a.id}"
                                       data-id-paciente-plan="${deuda.id_paciente_plan}"
                                       data-id-cita="${a.id_cita}"
                                       placeholder="${pend.toFixed(2)}">
                            </span>
                        </div>`;
                    });
                }

                // Servicios sueltos anteriores con deuda
                if (deuda.servicios && deuda.servicios.length) {
                    html += rowsHeader('Servicios anteriores', true);
                    deuda.servicios.forEach(s => {
                        const pend = pendiente(s.subtotal, s.pagado);
                        html += `<div class="cobros-fila">
                            <span class="cobros-fila-nombre">${s.nombre_servicio} x${s.cantidad} — ${fmtFecha(s.fecha)}</span>
                            <span class="cobros-fila-monto">${fmt(s.subtotal)}</span>
                            <span class="cobros-fila-pagado">${fmt(s.pagado)}</span>
                            <span class="cobros-fila-pendiente">${fmt(pend)}</span>
                            <span class="cobros-fila-input">
                                <input type="number" step="0.01" min="0" max="${pend}"
                                       class="cobros-monto-input cobros-input-deuda"
                                       data-tipo="total"
                                       data-id-cita-servicio="${s.id_cita_servicio}"
                                       data-id-cita="${s.id_cita}"
                                       placeholder="${pend.toFixed(2)}">
                            </span>
                        </div>`;
                    });
                }

                html += `</div>`;
            });
        }

        // ── 2. CITAS ENVIADAS ────────────────────────────────────
        // Solo la primera (más reciente) es la cita actual; las demás son deuda
        const hayDeudas = (data.deudas_anteriores && data.deudas_anteriores.length) ||
                          (data.citas && data.citas.length > 1);

        if (data.citas && data.citas.length) {
            data.citas.forEach((cita, idx) => {
                const esCitaActual = idx === 0;
                const bloqueado    = !esCitaActual || hayDeudas;
                const claseSeccion = !esCitaActual ? 'cobros-sec-deuda' : '';
                const etiqueta     = !esCitaActual ? 'Deuda anterior' : 'Cita actual';

                html += `<div class="cobros-seccion ${claseSeccion}">
                    <p class="cobros-sec-titulo">${etiqueta} — ${fmtFecha(cita.fecha)} ${cita.hora ? cita.hora.substring(0,5) : ''}</p>`;

                if (!esCitaActual) {
                    html += `<p class="cobros-sec-nota">Salda esta deuda antes de cobrar la cita actual.</p>`;
                } else if (hayDeudas) {
                    html += `<p class="cobros-sec-nota cobros-nota-bloqueada">Salda primero las deudas anteriores para habilitar este cobro.</p>`;
                }

                // Servicios sueltos de esta cita
                if (cita.servicios && cita.servicios.length) {
                    html += rowsHeader('Servicios');
                    cita.servicios.forEach(s => {
                        const pend = pendiente(s.subtotal, s.pagado);
                        html += inputFila(
                            `${s.nombre_servicio} x${s.cantidad}`,
                            s.subtotal, s.pagado, pend,
                            { tipo: 'total', 'id-cita': cita.id_cita, 'id-cita-servicio': s.id_cita_servicio },
                            bloqueado
                        );
                    });
                }

                // Sesión del plan
                if (cita.sesion) {
                    const ses  = cita.sesion;
                    const plan = cita.plan;
                    const pend = pendiente(ses.costo_sesion, ses.pagado);
                    html += rowsHeader(`Sesión #${ses.numero_sesion} — ${plan ? plan.nombre_plan : 'Plan'}`);
                    html += inputFila(
                        `Sesión #${ses.numero_sesion}${plan ? ' · ' + plan.nombre_plan : ''}`,
                        ses.costo_sesion, ses.pagado, pend,
                        { tipo: 'sesion', 'id-sesion': ses.id_sesion,
                          'id-paciente-plan': cita.id_paciente_plan,
                          'id-cita': cita.id_cita },
                        bloqueado
                    );
                }

                // Aparatología separada de esta cita
                if (cita.aparatologia_sep && cita.aparatologia_sep.length) {
                    html += rowsHeader('Aparatología adicional');
                    cita.aparatologia_sep.forEach(a => {
                        const total = parseFloat(a.precio_acordado) * parseInt(a.cantidad || 1);
                        const pend  = pendiente(total, a.pagado);
                        html += inputFila(
                            `${a.descripcion} x${a.cantidad || 1}`,
                            total, a.pagado, pend,
                            { tipo: 'aparatologia',
                              'id-aparatologia-item': a.id,
                              'id-paciente-plan': cita.id_paciente_plan,
                              'id-cita': cita.id_cita },
                            bloqueado
                        );
                    });
                }

                html += `</div>`;
            });
        }

        if (!html) {
            html = '<p class="cobros-vacio">No hay cobros pendientes.</p>';
        }

        return html;
    }

    // ── Helpers de render ─────────────────────────────────────────
    function rowsHeader(label, esDeuda = false) {
        return `<div class="cobros-fila header-fila ${esDeuda ? 'deuda-header' : ''}">
            <span>${label}</span>
            <span>Total</span><span>Pagado</span><span>Pendiente</span><span>A cobrar</span>
        </div>`;
    }

    function inputFila(nombre, total, pagado, pend, dataAttrs, forzarDisabled = false) {
        const disabled = (pend <= 0 || forzarDisabled) ? 'disabled' : '';
        const dataStr  = Object.entries(dataAttrs)
            .map(([k, v]) => `data-${k}="${v}"`)
            .join(' ');
        return `<div class="cobros-fila">
            <span class="cobros-fila-nombre">${nombre}</span>
            <span class="cobros-fila-monto">${fmt(total)}</span>
            <span class="cobros-fila-pagado">${fmt(pagado)}</span>
            <span class="cobros-fila-pendiente ${pend <= 0 ? 'cero' : ''}">${fmt(pend)}</span>
            <span class="cobros-fila-input">
                <input type="number" step="0.01" min="0" max="${pend}"
                       class="cobros-monto-input" ${dataStr}
                       placeholder="${pend > 0 && !forzarDisabled ? pend.toFixed(2) : '—'}" ${disabled}>
            </span>
        </div>`;
    }

    // ── Tab Saldos ────────────────────────────────────────────────
    function cargarSaldos(id_paciente) {
        fetch(`${CTRL}?accion=resumen_saldos&id_paciente=${id_paciente}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('cobrosTabContenido').innerHTML = renderTabSaldos(data);
            });
    }

    function renderTabSaldos(data) {
        let html = '';
        let totalGlobal = 0;

        if (data.servicios) {
            const total = parseFloat(data.servicios.total || 0);
            const pago  = parseFloat(data.servicios.pagado || 0);
            const saldo = total - pago;
            totalGlobal += saldo;
            if (total > 0) {
                html += `<div class="cobros-seccion">
                    <p class="cobros-sec-titulo">Servicios sueltos</p>
                    <div class="cobros-fila">
                        <span class="cobros-fila-nombre">Total facturado</span>
                        <span class="cobros-fila-monto">${fmt(total)}</span>
                        <span class="cobros-fila-pagado">${fmt(pago)}</span>
                        <span class="cobros-fila-pendiente ${saldo <= 0 ? 'cero' : ''}">${fmt(saldo)}</span>
                    </div>
                </div>`;
            }
        }

        if (data.planes && data.planes.length) {
            data.planes.forEach(plan => {
                const cuota    = parseFloat(plan.cuota_inicial || 0);
                const pagCuota = parseFloat(plan.pagado_cuota || 0);
                const totAp    = parseFloat(plan.total_ap_sep || 0);
                const pagAp    = parseFloat(plan.pagado_ap_sep || 0);
                const totSes   = parseFloat(plan.total_sesiones || 0);
                const pagSes   = parseFloat(plan.pagado_sesiones || 0);

                const saldoCuota = cuota - pagCuota;
                const saldoAp    = totAp - pagAp;
                const saldoSes   = totSes - pagSes;
                const saldoPlan  = saldoCuota + saldoAp + saldoSes;
                totalGlobal += saldoPlan;

                html += `<div class="cobros-seccion">
                    <p class="cobros-sec-titulo">Plan: ${plan.nombre_plan}</p>
                    <div class="cobros-fila header-fila">
                        <span>Concepto</span><span>Total</span><span>Pagado</span><span>Saldo</span>
                    </div>`;

                if (cuota > 0) html += saldoFila('Cuota inicial', cuota, pagCuota, saldoCuota);
                if (totAp > 0) html += saldoFila('Aparatología adicional', totAp, pagAp, saldoAp);
                if (totSes > 0) html += saldoFila('Sesiones', totSes, pagSes, saldoSes);

                html += `<div class="cobros-total-fila">
                    <span>Total plan</span>
                    <span class="${saldoPlan > 0 ? 'cobros-fila-pendiente' : 'cobros-fila-pagado'}">${fmt(saldoPlan)} pendiente</span>
                </div></div>`;
            });
        }

        html = `<div class="cobros-total-global">
            <span class="cobros-total-global-label">Total pendiente del paciente</span>
            <span class="cobros-total-global-monto">${fmt(totalGlobal)}</span>
        </div>` + html;

        return html;
    }

    function saldoFila(nombre, total, pagado, saldo) {
        return `<div class="cobros-fila">
            <span class="cobros-fila-nombre">${nombre}</span>
            <span class="cobros-fila-monto">${fmt(total)}</span>
            <span class="cobros-fila-pagado">${fmt(pagado)}</span>
            <span class="cobros-fila-pendiente ${saldo <= 0 ? 'cero' : ''}">${fmt(saldo)}</span>
        </div>`;
    }

    // ── Modal de pago ─────────────────────────────────────────────
    function abrirModalPago() {
        const inputs  = document.querySelectorAll('.cobros-monto-input:not(:disabled)');
        const deudaInputs   = document.querySelectorAll('.cobros-input-deuda');
        const citaInputs    = document.querySelectorAll('.cobros-monto-input:not(.cobros-input-deuda):not(:disabled)');

        // Validar: si hay deudas, al menos una debe tener monto
        const hayDeuda    = deudaInputs.length > 0;
        const deudaCubierta = Array.from(deudaInputs).some(i => parseFloat(i.value || 0) > 0);
        const hayCitaMonto  = Array.from(citaInputs).some(i => parseFloat(i.value || 0) > 0);

        if (hayDeuda && !deudaCubierta && hayCitaMonto) {
            mostrarMsg('Hay deudas anteriores pendientes. Registra al menos un pago de deuda antes de cobrar la cita actual.', 'error');
            return;
        }

        const pagos = [];
        inputs.forEach(inp => {
            if (parseFloat(inp.value || 0) > 0) pagos.push(inp);
        });

        if (!pagos.length) {
            mostrarMsg('Ingresa al menos un monto a cobrar', 'error');
            return;
        }

        let totalModal = 0;
        let resumenHtml = '';

        pagos.forEach(inp => {
            const monto  = parseFloat(inp.value);
            const fila   = inp.closest('.cobros-fila');
            const nombre = fila?.querySelector('.cobros-fila-nombre')?.textContent || '—';
            const esDeuda = inp.classList.contains('cobros-input-deuda');
            totalModal += monto;
            resumenHtml += `<div class="cobros-resumen-item ${esDeuda ? 'cobros-resumen-deuda' : ''}">
                <span>${esDeuda ? '[Deuda anterior] ' : ''}${nombre}</span>
                <span>${fmt(monto)}</span>
            </div>`;
        });

        resumenHtml += `<div class="cobros-resumen-total">
            <span>Total a registrar</span><span>${fmt(totalModal)}</span>
        </div>`;

        document.getElementById('resumenPagosModal').innerHTML = resumenHtml;
        document.getElementById('pagoObservacionGlobal').value = '';
        document.getElementById('modalConfirmarPago').style.display = 'flex';
    }

    function confirmarPagos() {
        const inputs = document.querySelectorAll('.cobros-monto-input:not(:disabled)');
        const obs    = document.getElementById('pagoObservacionGlobal').value.trim();
        const pagos  = [];

        inputs.forEach(inp => {
            const monto = parseFloat(inp.value || 0);
            if (monto <= 0) return;
            const d = inp.dataset;
            pagos.push({
                tipo_pago:            d.tipo,
                monto,
                id_cita:              d.idCita             || null,
                id_paciente_plan:     d.idPacientePlan     || null,
                id_sesion:            d.idSesion           || null,
                id_aparatologia_item: d.idAparatologiaItem || null,
                id_cita_servicio:     d.idCitaServicio     || null,
                observacion:          obs || null,
            });
        });

        if (!pagos.length) return;

        const btn = document.getElementById('btnConfirmarPago');
        btn.disabled = true;
        btn.textContent = 'Registrando...';

        fetch(`${CTRL}?accion=registrar_pagos`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_paciente: pacienteActivo.id_paciente, pagos })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Registrar pagos';
            document.getElementById('modalConfirmarPago').style.display = 'none';
            if (data.success) {
                mostrarMsg('Pagos registrados correctamente');
                cargarBuzon();
                cargarDetalle(pacienteActivo);
            } else {
                mostrarMsg(data.mensaje || 'Error al registrar pagos', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = 'Registrar pagos';
            mostrarMsg('Error de conexión', 'error');
        });
    }

    // ── Listeners ─────────────────────────────────────────────────
    document.getElementById('btnConfirmarPago')?.addEventListener('click', confirmarPagos);
    document.getElementById('btnCancelarPago')?.addEventListener('click', () => {
        document.getElementById('modalConfirmarPago').style.display = 'none';
    });
    document.getElementById('btnCerrarModalPago')?.addEventListener('click', () => {
        document.getElementById('modalConfirmarPago').style.display = 'none';
    });

    // ── Init ──────────────────────────────────────────────────────
    cargarBuzon();

    window.iniciarModuloCobros = function () {
        tabActiva = 'cobro';
        pacienteActivo = null;
        cargarBuzon();
    };

})();