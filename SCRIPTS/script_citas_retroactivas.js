// =====================================================
// CITAS PASADAS (historial): cita + servicios + plan (sesion y aparatologia) + atencion + pagos, en una sola operacion.
// El servidor decide y valida todo: aqui solo se arma el formulario y se envia.
// =====================================================
function iniciarCitasRetroactivas() {
    const $ = id => document.getElementById(id);
    const btnAbrir = $('btnCitaPasada');
    const modal = $('modalCitaPasada');
    if (!btnAbrir || !modal || btnAbrir.dataset.cpIniciado === '1') return;
    btnAbrir.dataset.cpIniciado = '1';

    const CTRL = '../CONTROLADORES/controlador_citas_retroactivas.php';
    const ESTADO_COMPLETADA = '4';
    let ctx = null;                 // catalogos y permisos
    let pacienteElegido = null;     // { id_paciente, nombre, ... }
    let planesPaciente = [];        // planes del paciente elegido
    let ocupado = false;
    let temporizador = null;

    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
    const dinero = n => 'S/ ' + r2(n).toFixed(2);
    function fechaCorta(f) { const [y, m, d] = String(f || '').split('-'); return d ? `${d}/${m}/${y}` : ''; }
    const num = id => { const v = $(id).value; return v === '' ? null : Number(v); };

    async function pedir(url, opciones) {
        let res;
        try { res = await fetch(url, opciones); } catch (e) { return { success: false, mensaje: 'No se pudo conectar con el servidor' }; }
        let texto = ''; try { texto = await res.text(); } catch (e) { /* sin cuerpo */ }
        try { return JSON.parse(texto); }
        catch (e) {
            const det = texto.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
            return { success: false, mensaje: 'Respuesta inválida del servidor (HTTP ' + res.status + ')' + (det ? ': ' + det : '') };
        }
    }
    function aviso(texto, tipo) {
        const t = document.createElement('div');
        t.textContent = texto;
        t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10001;color:#fff;padding:12px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.18);max-width:360px;font-size:13.5px;background:' + (tipo === 'error' ? '#c0392b' : '#27ae60');
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 4000);
    }
    function mensaje(texto) { const m = $('cpMensaje'); m.textContent = texto || ''; m.style.display = texto ? 'block' : 'none'; }

    // ---------- catalogos ----------
    function llenarSelect(id, filas, valor, texto, vacio) {
        $(id).innerHTML = (vacio ? `<option value="">${esc(vacio)}</option>` : '') + filas.map(f => `<option value="${esc(f[valor])}">${esc(typeof texto === 'function' ? texto(f) : f[texto])}</option>`).join('');
    }
    async function cargarContexto() {
        const d = await pedir(CTRL + '?accion=contexto');
        if (!d.success) { mensaje(d.mensaje || 'No se pudieron cargar los datos del formulario'); return false; }
        ctx = d;
        const c = d.catalogos;
        llenarSelect('cpDoctor', c.doctores, 'id_usuario', f => f.nombre + (Number(f.id_estado) === 1 ? '' : ' (inactivo)'), 'Seleccione...');
        llenarSelect('cpSede', c.sedes, 'id_sede_atencion', 'nombre_sede', c.sedes.length === 1 ? '' : 'Seleccione...');
        llenarSelect('cpTipoAtencion', c.tipos_atencion, 'id_tipo_atencion', 'nombre', 'Sin especificar');
        llenarSelect('cpEstado', c.estados, 'id_estado_cita', 'estado');
        llenarSelect('cpNpPlan', c.planes || [], 'id_plan', 'nombre_plan', 'Seleccione el plan...');
        const ayer = new Date(d.hoy + 'T00:00:00'); ayer.setDate(ayer.getDate() - 1);
        $('cpFecha').max = ayer.getFullYear() + '-' + String(ayer.getMonth() + 1).padStart(2, '0') + '-' + String(ayer.getDate()).padStart(2, '0');
        return true;
    }

    // ---------- paciente ----------
    function elegirPaciente(p) {
        pacienteElegido = p;
        $('cpResultadosPaciente').style.display = 'none';
        $('cpPaciente').style.display = 'none';
        const el = $('cpPacienteElegido');
        el.innerHTML = `<strong>${esc(p.nombre)}</strong> · ${esc(p.tipo_documento_abrev || 'DNI')} ${esc(p.nro_documento)} <button type="button" class="cp-link" id="cpCambiarPaciente">Cambiar</button>`;
        el.style.display = 'flex';
        $('cpCambiarPaciente').addEventListener('click', () => {
            pacienteElegido = null; planesPaciente = []; el.style.display = 'none'; $('cpPaciente').style.display = ''; $('cpPaciente').value = ''; $('cpPaciente').focus();
            if (modoPlan() === 'existente') cargarPlanesPaciente();
        });
        if (modoPlan() === 'existente') cargarPlanesPaciente();
    }
    $('cpPaciente').addEventListener('input', () => {
        clearTimeout(temporizador);
        const t = $('cpPaciente').value.trim();
        const lista = $('cpResultadosPaciente');
        if (t.length < 2) { lista.style.display = 'none'; return; }
        temporizador = setTimeout(async () => {
            const d = await pedir(CTRL + '?accion=buscar_paciente&termino=' + encodeURIComponent(t));
            if (!d.success) { lista.innerHTML = `<div class="cp-item-vacio">${esc(d.mensaje || 'Error')}</div>`; lista.style.display = 'block'; return; }
            lista.innerHTML = d.pacientes.length
                ? d.pacientes.map(p => `<div class="cp-item" data-id="${Number(p.id_paciente)}">${esc(p.nombre)} <small>${esc(p.tipo_documento_abrev || 'DNI')} ${esc(p.nro_documento)}</small></div>`).join('')
                : '<div class="cp-item-vacio">Sin resultados</div>';
            lista.style.display = 'block';
            lista.querySelectorAll('.cp-item').forEach(it => it.addEventListener('click', () => elegirPaciente(d.pacientes.find(p => String(p.id_paciente) === it.dataset.id))));
        }, 250);
    });

    // ---------- plan de tratamiento ----------
    const modoPlan = () => { const r = modal.querySelector('input[name="cpModoPlan"]:checked'); return r ? r.value : ''; };

    function costoSugeridoNuevo() {
        const tipo = $('cpNpTipo').value;
        if (tipo === 'costo_total') {
            if (!$('cpNpEnCuotas').checked) return 0;
            const n = Number($('cpNpNumCuotas').value) || 0;
            return n > 0 ? r2((Number($('cpNpCostoTotal').value) || 0) / n) : 0;
        }
        return r2(Number($('cpNpCostoSesion').value) || 0);
    }
    function planElegido() { return planesPaciente.find(p => String(p.id_paciente_plan) === $('cpPlanSel').value) || null; }

    // lo que valdra la sesion de esta cita (lo escrito, o lo que indica el plan)
    function datosSesion() {
        const modo = modoPlan();
        if (modo === 'existente') {
            const p = planElegido();
            const ov = num('cpPlanCostoEx');
            return { costo: ov !== null ? ov : (p ? Number(p.costo_sesion_sugerido) : 0), numero: num('cpPlanNumero') || (p ? Number(p.siguiente_numero) : null) };
        }
        if (modo === 'nuevo') { const ov = num('cpNpCostoSesionReal'); return { costo: ov !== null ? ov : costoSugeridoNuevo(), numero: 1 }; }
        return null;
    }

    async function cargarPlanesPaciente() {
        const sel = $('cpPlanSel');
        planesPaciente = [];
        if (!pacienteElegido) { sel.innerHTML = '<option value="">Elija primero al paciente</option>'; $('cpPlanInfo').textContent = ''; refrescar(); return; }
        sel.innerHTML = '<option value="">Cargando...</option>';
        const d = await pedir(CTRL + '?accion=planes_paciente&id_paciente=' + encodeURIComponent(pacienteElegido.id_paciente));
        if (!d.success) { sel.innerHTML = '<option value="">No se pudieron cargar</option>'; return; }
        planesPaciente = d.planes;
        sel.innerHTML = planesPaciente.length
            ? '<option value="">Seleccione el plan...</option>' + planesPaciente.map(p => `<option value="${Number(p.id_paciente_plan)}">${esc(p.nombre_plan)} — inició ${esc(fechaCorta(p.fecha_inicio))}${p.estado === 'completado' ? ' (completado)' : ''}</option>`).join('')
            : '<option value="">Este paciente no tiene planes</option>';
        $('cpPlanInfo').textContent = planesPaciente.length ? '' : 'Este paciente no tiene planes registrados. Use «Plan nuevo» si esta cita fue el inicio del tratamiento.';
        refrescar();
    }

    function aplicarModoPlan() {
        const modo = modoPlan();
        $('cpPlanExistente').style.display = modo === 'existente' ? 'block' : 'none';
        $('cpPlanNuevo').style.display = modo === 'nuevo' ? 'block' : 'none';
        $('cpBloqueAparatologia').style.display = modo ? 'block' : 'none';
        modal.querySelectorAll('.cp-col-incl').forEach(e => { e.style.display = modo === 'nuevo' ? '' : 'none'; });
        // con un plan, la atencion es obligatoria (Cobros lee el plan desde la atencion enviada)
        $('cpConInforme').checked = modo ? true : $('cpConInforme').checked;
        $('cpConInforme').disabled = !!modo;
        alternarEstado();
        if (modo === 'existente') cargarPlanesPaciente(); else refrescar();
    }
    function alternarTipoNuevo() {
        const total = $('cpNpTipo').value === 'costo_total';
        $('cpNpGrCostoSesion').style.display = total ? 'none' : '';
        $('cpNpGrCostoTotal').style.display = total ? '' : 'none';
        $('cpNpGrCuotas').style.display = total ? '' : 'none';
        $('cpNpNumCuotas').style.display = total && $('cpNpEnCuotas').checked ? '' : 'none';
        refrescar();
    }

    // ---------- aparatologia ----------
    function agregarAparatologia() {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><select class="cp-ap"><option value="">Seleccione...</option>${(ctx.catalogos.aparatologia || []).map(a => `<option value="${Number(a.id_aparatologia)}" data-precio="${esc(a.precio_base)}">${esc(a.nombre)}</option>`).join('')}</select></td>
            <td><input type="number" class="cp-ap-cant" min="1" max="100" step="1" value="1"></td>
            <td><input type="number" class="cp-ap-precio" min="0" step="0.01" placeholder="0.00"></td>
            <td class="cp-col-incl" style="text-align:center;${modoPlan() === 'nuevo' ? '' : 'display:none;'}"><input type="checkbox" class="cp-ap-incl" style="width:auto;"></td>
            <td><button type="button" class="cp-quitar" aria-label="Quitar">&times;</button></td>`;
        tr.querySelector('.cp-ap').addEventListener('change', e => { const o = e.target.selectedOptions[0]; tr.querySelector('.cp-ap-precio').value = o && o.dataset.precio ? Number(o.dataset.precio).toFixed(2) : ''; refrescar(); });
        tr.querySelectorAll('input').forEach(i => i.addEventListener('input', refrescar));
        tr.querySelector('.cp-ap-incl').addEventListener('change', refrescar);
        tr.querySelector('.cp-quitar').addEventListener('click', () => { tr.remove(); refrescar(); });
        $('cpTablaAparatologia').querySelector('tbody').appendChild(tr);
    }

    // ---------- servicios ----------
    function agregarServicio() {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><select class="cp-srv"><option value="">Seleccione...</option>${ctx.catalogos.servicios.map(s => `<option value="${Number(s.id_tipo_servicio)}" data-precio="${esc(s.precio_base)}">${esc(s.nombre_servicio)}</option>`).join('')}</select></td>
            <td><input type="number" class="cp-cant" min="1" max="100" step="1" value="1"></td>
            <td><input type="number" class="cp-precio" min="0" step="0.01" placeholder="0.00"></td>
            <td class="cp-sub">S/ 0.00</td>
            <td><button type="button" class="cp-quitar" aria-label="Quitar">&times;</button></td>`;
        tr.querySelector('.cp-srv').addEventListener('change', e => { const o = e.target.selectedOptions[0]; tr.querySelector('.cp-precio').value = o && o.dataset.precio ? Number(o.dataset.precio).toFixed(2) : ''; refrescar(); });
        tr.querySelectorAll('input').forEach(i => i.addEventListener('input', refrescar));
        tr.querySelector('.cp-quitar').addEventListener('click', () => { tr.remove(); refrescar(); });
        $('cpTablaServicios').querySelector('tbody').appendChild(tr);
    }

    // ---------- conceptos cobrables (a que se aplica cada pago) ----------
    function listarConceptos() {
        const out = [];
        [...$('cpTablaServicios').querySelectorAll('tbody tr')].forEach((tr, i) => {
            const sel = tr.querySelector('.cp-srv'); const nombre = sel.value ? sel.selectedOptions[0].textContent : 'sin elegir';
            if (!sel.value) return;                                  // un servicio sin elegir todavia no es un concepto cobrable
            out.push({ clave: 's:' + i, texto: `Servicio ${i + 1}: ${nombre}`, concepto: { tipo: 'servicio', indice: i }, monto: r2((Number(tr.querySelector('.cp-cant').value) || 0) * (Number(tr.querySelector('.cp-precio').value) || 0)), enTotal: true });
        });
        const modo = modoPlan(), ses = datosSesion();
        if (modo && ses) out.push({ clave: 'ses', texto: `Sesión n.º ${ses.numero || '?'} del plan`, concepto: { tipo: 'sesion' }, monto: r2(ses.costo), enTotal: true });
        if (modo) {
            [...$('cpTablaAparatologia').querySelectorAll('tbody tr')].forEach((tr, j) => {
                if (tr.querySelector('.cp-ap-incl').checked && modo === 'nuevo') return;          // incluida en el costo: no se cobra aparte
                const sel = tr.querySelector('.cp-ap'); const nombre = sel.value ? sel.selectedOptions[0].textContent : 'sin elegir';
                if (!sel.value) return;
                out.push({ clave: 'a:' + j, texto: `Aparatología ${j + 1}: ${nombre}`, concepto: { tipo: 'aparatologia', indice: j }, monto: r2((Number(tr.querySelector('.cp-ap-cant').value) || 0) * (Number(tr.querySelector('.cp-ap-precio').value) || 0)), enTotal: true });
            });
        }
        if (modo === 'nuevo' && (num('cpNpCuotaIni') || 0) > 0) out.push({ clave: 'cuota', texto: 'Cuota inicial del plan', concepto: { tipo: 'cuota_inicial' }, monto: r2(num('cpNpCuotaIni')), enTotal: true });
        if (modo === 'existente') { const p = planElegido(); if (p && Number(p.cuota_pendiente) > 0.004) out.push({ clave: 'cuota', texto: 'Cuota inicial del plan (pendiente)', concepto: { tipo: 'cuota_inicial' }, monto: r2(p.cuota_pendiente), enTotal: false }); }
        return out;
    }
    // cuanto se ha puesto ya en cada concepto (sumando todas las filas de pago), opcionalmente sin contar una fila
    function usadoPorConcepto(excluirTr) {
        const u = {};
        $('cpTablaPagos').querySelectorAll('tbody tr').forEach(tr => {
            if (tr === excluirTr) return;
            const k = tr.querySelector('.cp-concepto').value;
            if (k) u[k] = r2((u[k] || 0) + (Number(tr.querySelector('.cp-monto').value) || 0));
        });
        return u;
    }
    function faltaDe(c, excluirTr) { return r2(c.monto - (usadoPorConcepto(excluirTr)[c.clave] || 0)); }

    function actualizarConceptos() {
        const conceptos = listarConceptos();
        $('cpTablaPagos').querySelectorAll('tbody tr').forEach(tr => {
            const sel = tr.querySelector('.cp-concepto'); const previo = sel.value;
            const otros = usadoPorConcepto(tr);
            // cada fila ofrece SOLO lo que todavia falta cobrar (contando lo que ponen las demas filas), mas su propia eleccion
            const opciones = conceptos.filter(c => r2(c.monto - (otros[c.clave] || 0)) > 0.004 || c.clave === previo);
            sel.innerHTML = opciones.length
                ? opciones.map(c => `<option value="${esc(c.clave)}">${esc(c.texto)} — falta ${dinero(c.monto - (otros[c.clave] || 0))}</option>`).join('')
                : '<option value="">— no queda nada por cobrar —</option>';
            if (opciones.some(c => c.clave === previo)) sel.value = previo;
            // el monto de esta fila se marca en rojo si se pasa de lo que falta
            const c = conceptos.find(x => x.clave === sel.value);
            const monto = Number(tr.querySelector('.cp-monto').value) || 0;
            tr.querySelector('.cp-monto').classList.toggle('cp-invalido', !!c && monto > r2(c.monto - (otros[c.clave] || 0)) + 0.004);
        });
        // "+ Agregar pago" solo si queda algo por cobrar
        const usados = usadoPorConcepto(null);
        const queda = conceptos.some(c => r2(c.monto - (usados[c.clave] || 0)) > 0.004);
        $('cpAgregarPago').disabled = !queda;
        $('cpAgregarPago').title = queda ? '' : 'Ya se cubrió todo lo que hay por cobrar en esta cita';
        return conceptos;
    }

    // ---------- totales ----------
    function refrescar() {
        // textos informativos del plan
        const ses = datosSesion(), modo = modoPlan();
        if (modo === 'existente') {
            const p = planElegido();
            // el servidor explica que costo lleva la sesion y por que (cuota k de N, sesion adicional que no se cobra, etc.)
            const auto = p && p.aviso && num('cpPlanNumero') === null && num('cpPlanCostoEx') === null;
            const notaCuota = p && Number(p.cuota_pendiente) > 0.004 ? ` Cuota inicial pendiente del plan: ${dinero(p.cuota_pendiente)}.` : '';
            $('cpPlanInfo').textContent = p ? (auto ? p.aviso.mensaje : `Se registrará la sesión n.º ${ses.numero} · costo ${dinero(ses.costo)}.`) + notaCuota : ($('cpPlanInfo').textContent && !planesPaciente.length ? $('cpPlanInfo').textContent : '');
            $('cpPlanInfo').style.fontWeight = p && p.aviso && p.aviso.nivel === 'aviso' && num('cpPlanNumero') === null ? '500' : '';
        } else if (modo === 'nuevo') {
            const nC = Number($('cpNpNumCuotas').value) || 0;
            $('cpNpInfo').textContent = `Se creará el plan con inicio el ${fechaCorta($('cpFecha').value) || '(fecha de la cita)'} y su sesión n.º 1 por ${dinero(ses.costo)}.` + ($('cpNpTipo').value === 'costo_total' && $('cpNpEnCuotas').checked && nC > 0 ? ` Las sesiones después de la cuota ${nC} no se cobran.` : '');
        }
        const conceptos = actualizarConceptos();
        let total = 0;
        $('cpTablaServicios').querySelectorAll('tbody tr').forEach(tr => { tr.querySelector('.cp-sub').textContent = dinero((Number(tr.querySelector('.cp-cant').value) || 0) * (Number(tr.querySelector('.cp-precio').value) || 0)); });
        conceptos.forEach(c => { if (c.enTotal) total += c.monto; });
        let pagado = 0, pagadoCita = 0;
        $('cpTablaPagos').querySelectorAll('tbody tr').forEach(tr => {
            const m = Number(tr.querySelector('.cp-monto').value) || 0; pagado += m;
            const c = conceptos.find(x => x.clave === tr.querySelector('.cp-concepto').value);
            if (!c || c.enTotal) pagadoCita += m;
        });
        let subServicios = 0;
        $('cpTablaServicios').querySelectorAll('tbody tr').forEach(tr => { subServicios += (Number(tr.querySelector('.cp-cant').value) || 0) * (Number(tr.querySelector('.cp-precio').value) || 0); });
        $('cpTotalServicios').textContent = dinero(subServicios);
        // resumen de la cita: cada concepto y el total (para no confundirlo con el subtotal de servicios)
        const lineas = [];
        const sv = conceptos.filter(c => c.clave[0] === 's' && c.clave !== 'ses');
        if (sv.length) lineas.push(['Servicios', sv.reduce((a, c) => a + c.monto, 0)]);
        conceptos.filter(c => c.clave === 'ses' || c.clave[0] === 'a' || (c.clave === 'cuota' && c.enTotal)).forEach(c => lineas.push([c.texto, c.monto]));
        let html = lineas.length ? lineas.map(([t, m]) => `<div class="cp-resumen-linea"><span>${esc(t)}</span><span>${dinero(m)}</span></div>`).join('') : '<div class="cp-resumen-linea nota">Todavía no hay nada que cobrar en esta cita.</div>';
        const cuotaEx = conceptos.find(c => c.clave === 'cuota' && !c.enTotal);
        if (cuotaEx) html += `<div class="cp-resumen-linea nota"><span>${esc(cuotaEx.texto)} — no cuenta en el total de esta cita</span><span>${dinero(cuotaEx.monto)}</span></div>`;
        $('cpResumenLineas').innerHTML = html;
        $('cpTotal').textContent = dinero(total);
        $('cpPagado').textContent = dinero(pagado);
        const pend = r2(total - pagadoCita);
        const el = $('cpPendiente'); el.textContent = dinero(pend); el.style.color = pend < 0 ? '#c0392b' : '';
        return { total: r2(total), pagado: r2(pagado), pend, conceptos };
    }

    function agregarPago() {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><select class="cp-concepto"></select></td>
            <td><input type="number" class="cp-monto" min="0" step="0.01" placeholder="0.00"></td>
            <td><select class="cp-metodo"><option value="">Seleccione...</option>${ctx.metodos.map(m => `<option value="${Number(m.id_metodo_pago)}" data-ref="${Number(m.requiere_referencia)}">${esc(m.nombre)}</option>`).join('')}</select></td>
            <td><input type="text" class="cp-comp" maxlength="40" placeholder="Opcional"></td>
            <td><button type="button" class="cp-quitar" aria-label="Quitar">&times;</button></td>`;
        tr.querySelector('.cp-metodo').addEventListener('change', e => { tr.querySelector('.cp-comp').placeholder = e.target.selectedOptions[0] && e.target.selectedOptions[0].dataset.ref === '1' ? 'Obligatorio' : 'Opcional'; });
        tr.querySelector('.cp-monto').addEventListener('input', refrescar);
        tr.querySelector('.cp-concepto').addEventListener('change', refrescar);
        tr.querySelector('.cp-quitar').addEventListener('click', () => { tr.remove(); refrescar(); });
        $('cpTablaPagos').querySelector('tbody').appendChild(tr);
        actualizarConceptos();
    }

    function alternarEstado() {
        const completada = $('cpEstado').value === ESTADO_COMPLETADA;
        $('cpBloqueAtencion').style.display = completada ? 'block' : 'none';
        $('cpCamposInforme').style.display = $('cpConInforme').checked ? 'block' : 'none';
        const pagos = ctx && ctx.permisos_pagos && ctx.permisos_pagos.registrar && ctx.permisos_pagos.retroactivo;
        $('cpBloquePagos').style.display = pagos ? 'block' : 'none';
    }

    // ---------- abrir / cerrar / reiniciar ----------
    async function abrir() {
        modal.style.display = 'flex';
        if (!ctx && !(await cargarContexto())) return;
        reiniciar();
        cargarRecientes();
    }
    function cerrar() { if (!ocupado) modal.style.display = 'none'; }
    function reiniciar() {
        pacienteElegido = null; planesPaciente = [];
        $('cpPaciente').value = ''; $('cpPaciente').style.display = ''; $('cpPacienteElegido').style.display = 'none'; $('cpResultadosPaciente').style.display = 'none';
        ['cpFecha', 'cpHora', 'cpMotivo', 'cpDiagnostico', 'cpTratamiento', 'cpObservaciones', 'cpPlanNumero', 'cpPlanCostoEx', 'cpNpCostoSesion', 'cpNpCostoTotal', 'cpNpNumCuotas', 'cpNpCostoSesionReal', 'cpNpNotas'].forEach(id => { $(id).value = ''; });
        $('cpNpCuotaIni').value = '0'; $('cpNpTipo').value = 'por_sesion'; $('cpNpPlan').value = ''; $('cpNpEnCuotas').checked = false;
        $('cpPlanSel').innerHTML = '<option value="">Elija primero al paciente</option>';
        modal.querySelector('input[name="cpModoPlan"][value=""]').checked = true;
        $('cpDuracion').value = '30'; $('cpConInforme').checked = true; $('cpConInforme').disabled = false;
        $('cpDoctor').value = ''; $('cpTipoAtencion').value = ''; $('cpEstado').value = ESTADO_COMPLETADA;
        if (ctx && ctx.catalogos.sedes.length === 1) $('cpSede').value = ctx.catalogos.sedes[0].id_sede_atencion; else $('cpSede').value = '';
        ['cpTablaServicios', 'cpTablaPagos', 'cpTablaAparatologia'].forEach(id => { $(id).querySelector('tbody').innerHTML = ''; });
        agregarServicio(); alternarTipoNuevo(); aplicarModoPlan(); refrescar(); mensaje('');
        $('cpConfirmarEliminar').style.display = 'none';
    }

    // ---------- guardar ----------
    async function guardar() {
        if (ocupado) return;
        mensaje('');
        if (!pacienteElegido) return mensaje('Seleccione el paciente');
        if (!$('cpFecha').value) return mensaje('Indique la fecha de la cita');
        if (!$('cpHora').value) return mensaje('Indique la hora');
        if (!$('cpDoctor').value) return mensaje('Seleccione el odontólogo');
        if (!$('cpSede').value) return mensaje('Seleccione la sede');
        if (!$('cpMotivo').value.trim()) return mensaje('Indique el motivo de la cita');

        const cuerpo = { id_paciente: Number(pacienteElegido.id_paciente), id_doctor: Number($('cpDoctor').value), fecha: $('cpFecha').value, hora: $('cpHora').value,
                         duracion_minutos: Number($('cpDuracion').value) || 30, id_sede_atencion: Number($('cpSede').value), id_tipo_atencion: Number($('cpTipoAtencion').value) || null,
                         id_estado_cita: Number($('cpEstado').value), motivo: $('cpMotivo').value.trim(), servicios: [], pagos: [] };

        if ($('cpEstado').value === ESTADO_COMPLETADA) {
            for (const [i, tr] of [...$('cpTablaServicios').querySelectorAll('tbody tr')].entries()) {
                if (!tr.querySelector('.cp-srv').value) return mensaje(`Servicio ${i + 1}: seleccione el servicio (o quite la fila)`);
                if (tr.querySelector('.cp-precio').value === '') return mensaje(`Servicio ${i + 1}: indique el precio`);
                cuerpo.servicios.push({ id_tipo_servicio: Number(tr.querySelector('.cp-srv').value), cantidad: Number(tr.querySelector('.cp-cant').value), precio_unitario: Number(tr.querySelector('.cp-precio').value) });
            }

            const modo = modoPlan();
            if (modo) {
                const plan = { modo, aparatologia: [] };
                if (modo === 'existente') {
                    if (!$('cpPlanSel').value) return mensaje('Seleccione el plan del paciente (o elija «Sin plan»)');
                    plan.id_paciente_plan = Number($('cpPlanSel').value);
                    if (num('cpPlanNumero') !== null) plan.numero_sesion = num('cpPlanNumero');
                    if (num('cpPlanCostoEx') !== null) plan.costo_sesion = num('cpPlanCostoEx');
                } else {
                    if (!$('cpNpPlan').value) return mensaje('Seleccione el plan de tratamiento');
                    const tipo = $('cpNpTipo').value;
                    const nuevo = { id_plan_catalogo: Number($('cpNpPlan').value), tipo, cuota_inicial: num('cpNpCuotaIni') || 0, notas: $('cpNpNotas').value.trim() };
                    if (tipo === 'por_sesion') { if (num('cpNpCostoSesion') === null) return mensaje('Indique el costo por sesión del plan'); nuevo.costo_estimado_sesion = num('cpNpCostoSesion'); }
                    else {
                        if (num('cpNpCostoTotal') === null) return mensaje('Indique el costo acordado del plan');
                        nuevo.costo_acordado = num('cpNpCostoTotal');
                        if ($('cpNpEnCuotas').checked) { if (!num('cpNpNumCuotas')) return mensaje('Indique en cuántas cuotas se paga'); nuevo.en_cuotas = 1; nuevo.sesiones_pago_est = num('cpNpNumCuotas'); }
                    }
                    plan.nuevo = nuevo;
                    if (num('cpNpCostoSesionReal') !== null) plan.costo_sesion = num('cpNpCostoSesionReal');
                }
                for (const [j, tr] of [...$('cpTablaAparatologia').querySelectorAll('tbody tr')].entries()) {
                    if (!tr.querySelector('.cp-ap').value) return mensaje(`Aparatología ${j + 1}: seleccione la pieza (o quite la fila)`);
                    if (tr.querySelector('.cp-ap-precio').value === '') return mensaje(`Aparatología ${j + 1}: indique el precio acordado`);
                    plan.aparatologia.push({ id_aparatologia: Number(tr.querySelector('.cp-ap').value), cantidad: Number(tr.querySelector('.cp-ap-cant').value), precio_acordado: Number(tr.querySelector('.cp-ap-precio').value), incluida_en_costo: modo === 'nuevo' && tr.querySelector('.cp-ap-incl').checked ? 1 : 0 });
                }
                cuerpo.plan = plan;
            }
            if ($('cpConInforme').checked || modo) cuerpo.informe = { registrar: true, diagnostico: $('cpDiagnostico').value, tratamiento_realizado: $('cpTratamiento').value, observaciones: $('cpObservaciones').value };

            if ($('cpBloquePagos').style.display !== 'none') {
                const t = refrescar();
                const porConcepto = {};
                for (const [i, tr] of [...$('cpTablaPagos').querySelectorAll('tbody tr')].entries()) {
                    const m = Number(tr.querySelector('.cp-monto').value);
                    if (!(m > 0)) return mensaje(`Pago ${i + 1}: indique el monto (o quite la fila)`);
                    const clave = tr.querySelector('.cp-concepto').value;
                    const c = t.conceptos.find(x => x.clave === clave);
                    if (!c) return mensaje(`Pago ${i + 1}: no hay un concepto al que aplicarlo (agregue servicios o plan primero)`);
                    const sel = tr.querySelector('.cp-metodo');
                    if (!sel.value) return mensaje(`Pago ${i + 1}: seleccione el método de pago`);
                    if (sel.selectedOptions[0].dataset.ref === '1' && !tr.querySelector('.cp-comp').value.trim()) return mensaje(`Pago ${i + 1}: este método exige el número de operación`);
                    porConcepto[clave] = (porConcepto[clave] || 0) + m;
                    cuerpo.pagos.push({ monto: m, id_metodo_pago: Number(sel.value), nro_comprobante: tr.querySelector('.cp-comp').value.trim(), concepto: c.concepto });
                }
                for (const [clave, suma] of Object.entries(porConcepto)) {
                    const c = t.conceptos.find(x => x.clave === clave);
                    if (c && suma > c.monto + 0.004) return mensaje(`Los pagos a «${c.texto}» (${dinero(suma)}) superan lo que se debe (${dinero(c.monto)})`);
                }
            }
        }

        ocupado = true; $('cpGuardar').disabled = true; $('cpGuardar').textContent = 'Registrando...';
        const r = await pedir(CTRL + '?accion=registrar', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo) });
        ocupado = false; $('cpGuardar').disabled = false; $('cpGuardar').textContent = 'Registrar cita pasada';
        if (!r.success) { mensaje(r.mensaje || 'No se pudo registrar'); return; }
        aviso('Cita pasada registrada' + (r.total ? ` · total ${dinero(r.total)}, pendiente ${dinero(r.pendiente)}` : '') + (r.plan ? ` · sesión n.º ${r.plan.sesion}` : ''), 'exito');
        reiniciar();
        cargarRecientes();
        if (typeof window.recargarListaCitas === 'function') window.recargarListaCitas();
    }

    // ---------- recientes y eliminar ----------
    async function cargarRecientes() {
        const d = await pedir(CTRL + '?accion=listar_recientes');
        const cont = $('cpRecientes');
        if (!d.success) { cont.innerHTML = '<p class="cp-ayuda">No se pudo cargar</p>'; return; }
        if (!d.citas.length) { cont.innerHTML = '<p class="cp-ayuda">Todavía no se ha registrado ninguna cita pasada.</p>'; return; }
        cont.innerHTML = '<div class="cp-tabla-caja"><table class="cp-tabla"><thead><tr><th>Fecha</th><th>Paciente</th><th>Odontólogo</th><th>Estado</th><th>Total</th><th>Pagado</th><th></th></tr></thead><tbody>' +
            d.citas.map(c => `<tr><td>${esc(fechaCorta(c.fecha))} ${esc(String(c.hora).slice(0, 5))}</td><td>${esc(c.paciente)}</td><td>${esc(c.doctor)}</td><td>${esc(c.estado_nombre)}</td>
                <td>${dinero(c.total)}</td><td>${dinero(c.pagado)}</td>
                <td>${c.puede_eliminar ? `<button type="button" class="cp-btn cp-btn-peligro cp-btn-chico" data-accion="pedir-eliminar" data-id="${Number(c.id_cita)}">Eliminar</button>` : '<small title="Tiene movimientos de pago">con pagos</small>'}</td></tr>`).join('') +
            '</tbody></table></div>';
    }
    function pedirEliminar(id) {
        const caja = $('cpConfirmarEliminar');
        caja.innerHTML = '<p><strong>¿Eliminar esta cita pasada?</strong> Se borran también sus servicios, su atención y, si la cita creó un plan que nadie más usa, ese plan con su sesión y aparatología. Úselo solo si la cargó por error.</p>' +
            '<div class="cp-acciones"><button type="button" class="cp-btn cp-btn-sec" data-accion="cancelar-eliminar">Cancelar</button>' +
            `<button type="button" class="cp-btn cp-btn-peligro" data-accion="confirmar-eliminar" data-id="${Number(id)}">Sí, eliminar</button></div>`;
        caja.style.display = 'block';
    }
    async function confirmarEliminar(id, boton) {
        if (ocupado) return;
        ocupado = true; boton.disabled = true;
        const r = await pedir(CTRL + '?accion=eliminar', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id_cita: Number(id) }) });
        ocupado = false;
        const caja = $('cpConfirmarEliminar');
        if (r.success) { caja.style.display = 'none'; aviso('Cita eliminada', 'exito'); cargarRecientes(); if (typeof window.recargarListaCitas === 'function') window.recargarListaCitas(); }
        else { caja.innerHTML = `<p class="cp-mensaje" style="display:block;">${esc(r.mensaje || 'No se pudo eliminar')}</p>`; }
    }

    // ---------- eventos ----------
    btnAbrir.addEventListener('click', abrir);
    $('cpCerrar').addEventListener('click', cerrar);
    $('cpCancelar').addEventListener('click', cerrar);
    $('cpGuardar').addEventListener('click', guardar);
    $('cpAgregarServicio').addEventListener('click', () => { agregarServicio(); refrescar(); });
    $('cpAgregarAparatologia').addEventListener('click', () => { agregarAparatologia(); refrescar(); });
    $('cpAgregarPago').addEventListener('click', () => {
        agregarPago(); refrescar();
        const tr = $('cpTablaPagos').querySelector('tbody tr:last-child');          // propone el primer concepto pendiente y cuanto falta
        const c = listarConceptos().find(x => x.clave === tr.querySelector('.cp-concepto').value);
        if (c) { const falta = faltaDe(c, tr); if (falta > 0.004) tr.querySelector('.cp-monto').value = falta.toFixed(2); }
        refrescar();
    });
    $('cpEstado').addEventListener('change', alternarEstado);
    $('cpConInforme').addEventListener('change', alternarEstado);
    modal.querySelectorAll('input[name="cpModoPlan"]').forEach(r => r.addEventListener('change', aplicarModoPlan));
    $('cpPlanSel').addEventListener('change', refrescar);
    ['cpPlanNumero', 'cpPlanCostoEx', 'cpNpCostoSesion', 'cpNpCostoTotal', 'cpNpNumCuotas', 'cpNpCuotaIni', 'cpNpCostoSesionReal', 'cpFecha'].forEach(id => $(id).addEventListener('input', refrescar));
    $('cpNpPlan').addEventListener('change', () => {
        const p = (ctx.catalogos.planes || []).find(x => String(x.id_plan) === $('cpNpPlan').value);
        if (p && Number(p.tiene_costo_inicial) === 1 && !num('cpNpCuotaIni')) $('cpNpCuotaIni').value = Number(p.costo_inicial_ref || 0).toFixed(2);
        refrescar();
    });
    $('cpNpTipo').addEventListener('change', alternarTipoNuevo);
    $('cpNpEnCuotas').addEventListener('change', alternarTipoNuevo);
    modal.addEventListener('click', e => {
        if (e.target === modal) { cerrar(); return; }
        const el = e.target.closest && e.target.closest('[data-accion]');
        if (!el || !modal.contains(el)) return;
        if (el.dataset.accion === 'pedir-eliminar') pedirEliminar(el.dataset.id);
        else if (el.dataset.accion === 'cancelar-eliminar') $('cpConfirmarEliminar').style.display = 'none';
        else if (el.dataset.accion === 'confirmar-eliminar') confirmarEliminar(el.dataset.id, el);
    });
    if (window.__cpTeclado) document.removeEventListener('keydown', window.__cpTeclado);
    window.__cpTeclado = e => { if (e.key === 'Escape' && modal.style.display === 'flex' && document.body.contains(modal)) cerrar(); };
    document.addEventListener('keydown', window.__cpTeclado);
}
