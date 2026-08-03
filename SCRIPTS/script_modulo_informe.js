/**
 * MÓDULO INFORME DE ATENCIÓN
 * Función global: window.abrirInforme(id_cita, callback)
 */
(function () {

    // ── Helpers (declarados primero para disponibilidad inmediata) ─
    function formatFecha(str) {
        if (!str) return '—';
        const [y, m, d] = str.split('-');
        const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        return `${parseInt(d)} ${meses[parseInt(m)-1]} ${y}`;
    }

    function cerrar() {
        document.getElementById('modalInforme').style.display = 'none';
    }

    function mostrarMsg(msg, tipo = 'exito') {
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        aviso.innerHTML = `<span class="texto">${msg}</span>`;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.classList.add('mostrar'), 50);
        setTimeout(() => { aviso.classList.remove('mostrar'); setTimeout(() => aviso.remove(), 400); }, 3000);
    }

    const PUEDE_REGISTRAR = window.INF_PUEDE_REGISTRAR;
    const CTRL = '../CONTROLADORES/controlador_informe.php';

    let citaActual           = null;
    let planesActivos        = [];
    let catalogoServicios    = [];
    let catalogoPlanes       = [];
    let catalogoAparatologia = [];
    let serviciosInforme     = [];
    let aparatologiaInforme  = []; // aparatología de ESTA cita (nueva o editada)
    let planModificado       = false;
    let callbackAlGuardar    = null;

    // ── Catálogos ─────────────────────────────────────────────────
    async function cargarCatalogos() {
        if (catalogoServicios.length) return;
        const [resS, resP, resA] = await Promise.all([
            fetch(`${CTRL}?accion=listar_servicios`),
            fetch(`${CTRL}?accion=listar_planes`),
            fetch(`${CTRL}?accion=listar_aparatologia`)
        ]);
        catalogoServicios    = await resS.json();
        catalogoPlanes       = await resP.json();
        catalogoAparatologia = await resA.json();
        poblarSelect('inf-selServicio', catalogoServicios,    'id_tipo_servicio', 'nombre_servicio', 'precio_base', 'Seleccionar servicio...');
        poblarSelect('inf-selAparato',  catalogoAparatologia, 'id_aparatologia',  'nombre',          'precio_base', 'Seleccionar...');
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

    // ── Apertura global ───────────────────────────────────────────
    window.abrirInforme = async function(id_cita, callback) {
        callbackAlGuardar = callback || null;
        await cargarCatalogos();

        const res  = await fetch(`${CTRL}?accion=obtener_cita&id_cita=${id_cita}`);
        const data = await res.json();
        if (!data.success) { alert('No se pudo cargar la cita'); return; }
        citaActual = data.cita;

        const resPlanes = await fetch(`${CTRL}?accion=listar_planes_activos&id_paciente=${citaActual.id_paciente}`);
        planesActivos = await resPlanes.json();

        renderMetaCita(citaActual);

        if (citaActual.id_informe) {
            mostrarVistaInforme(citaActual);
        } else {
            mostrarFormulario(citaActual, false);
        }

        document.getElementById('modalInforme').style.display = 'flex';
    };

    // ── Meta cita ─────────────────────────────────────────────────
    function renderMetaCita(cita) {
        const tiempo = cita.tiempo_atencion > 0 ? `${cita.tiempo_atencion} min` : '';
        document.getElementById('inf-meta-cita').innerHTML = `
            <span>${formatFecha(cita.fecha)}</span> ${tiempo ? `<span>${tiempo}</span>` : ''}
            <span>${cita.nombre_servicio || '—'}</span> <span>Dr. ${cita.nombre_doctor}</span> ${cita.nombre_sede ? `<span>🏥 ${cita.nombre_sede}</span>` : ''}`;
    }

    // ── Vista estática ────────────────────────────────────────────
    function mostrarVistaInforme(cita) {
        document.getElementById('inf-titulo').textContent = 'Informe de Atención';

        Promise.all([
            fetch(`${CTRL}?accion=verificar_pagos&id_cita=${cita.id_cita}`).then(r => r.json()),
            fetch(`${CTRL}?accion=listar_servicios_cita&id_cita=${cita.id_cita}`).then(r => r.json())
        ]).then(([resP, servicios]) => {
            const tienePagos  = resP.tiene_pagos;
            const puedeEditar = PUEDE_REGISTRAR && !(cita.estado_informe === 'enviado' && tienePagos);

            let html = '<div class="inf-vista">';

            // Datos clínicos
            html += `
                <div class="inf-seccion"> <h4 class="inf-seccion-titulo">Datos Clínicos</h4> <div class="inf-campo"><span class="inf-etiqueta">Motivo de cita</span><span class="inf-valor">${cita.motivo_consulta || '—'}</span></div> <div class="inf-campo"><span class="inf-etiqueta">Hallazgos clínicos</span><span class="inf-valor">${cita.diagnostico || '—'}</span></div> <div class="inf-campo"><span class="inf-etiqueta">Tratamiento realizado</span><span class="inf-valor">${cita.tratamiento_realizado || '—'}</span></div> <div class="inf-fila"> <div class="inf-campo"><span class="inf-etiqueta">Materiales</span><span class="inf-valor">${cita.materiales || '—'}</span></div> <div class="inf-campo"><span class="inf-etiqueta">Observaciones</span><span class="inf-valor">${cita.observaciones || '—'}</span></div> </div> </div>`;

            // Servicios
            if (servicios.length) {
                html += `
                    <div class="inf-seccion"> <h4 class="inf-seccion-titulo">Servicios Realizados</h4> ${servicios.map(s => `
                            <div class="inf-srv-item"> <span class="inf-srv-nombre">${s.nombre_servicio}</span> <span class="inf-srv-cant">x${s.cantidad}</span> <span class="inf-srv-precio">S/ ${parseFloat(s.precio_unitario).toFixed(2)}</span> <span class="inf-srv-sub">S/ ${parseFloat(s.subtotal).toFixed(2)}</span> </div>`).join('')}
                        <div class="inf-srv-total"> <span>Total estimado:</span> <span>S/ ${servicios.reduce((a,s) => a + parseFloat(s.subtotal), 0).toFixed(2)}</span> </div> </div>`;
            }

            // Plan
            if (cita.nombre_plan && cita.plan_tipo) {
                const esCT = cita.plan_tipo === 'costo_total';
                html += `
                    <div class="inf-seccion"> <h4 class="inf-seccion-titulo">Plan de Tratamiento</h4> <div class="inf-plan-vista"> <div class="inf-campo"><span class="inf-etiqueta">Plan</span><span class="inf-valor">${cita.nombre_plan}</span></div> <div class="inf-campo"><span class="inf-etiqueta">Tipo</span><span class="inf-valor">${esCT ? 'Costo total' : 'Por sesión'}</span></div> ${esCT ? `
                            <div class="inf-fila"> <div class="inf-campo"><span class="inf-etiqueta">Monto pactado</span><span class="inf-valor inf-destacado">S/ ${parseFloat(cita.plan_costo_acordado||0).toFixed(2)}</span></div> <div class="inf-campo"><span class="inf-etiqueta">Sesiones est.</span><span class="inf-valor">${cita.plan_sesiones_est || '—'}</span></div> </div> <div class="inf-campo"><span class="inf-etiqueta">En cuotas</span><span class="inf-valor">${cita.plan_en_cuotas == 1 ? 'Sí' : 'No'}</span></div> ${cita.plan_costo_sesion ? `<div class="inf-campo"><span class="inf-etiqueta">Estimado/sesión</span><span class="inf-valor">S/ ${parseFloat(cita.plan_costo_sesion).toFixed(2)}</span></div>` : ''}
                            ` : `
                            <div class="inf-fila"> ${parseFloat(cita.plan_cuota_inicial||0) > 0 ? `<div class="inf-campo"><span class="inf-etiqueta">Cuota inicial</span><span class="inf-valor inf-destacado">S/ ${parseFloat(cita.plan_cuota_inicial).toFixed(2)}</span></div>` : ''}
                                <div class="inf-campo"><span class="inf-etiqueta">Sesiones est.</span><span class="inf-valor">${cita.plan_sesiones_est || '—'}</span></div> </div> ${cita.plan_costo_sesion ? `<div class="inf-campo"><span class="inf-etiqueta">Estimado/sesión</span><span class="inf-valor">S/ ${parseFloat(cita.plan_costo_sesion).toFixed(2)}</span></div>` : ''}
                            `}
                            ${cita.plan_notas ? `<div class="inf-campo"><span class="inf-etiqueta">Notas</span><span class="inf-valor">${cita.plan_notas}</span></div>` : ''}
                        </div> </div>`;
            }

            html += `
                <div class="inf-estado-badge ${cita.estado_informe}"> ${cita.estado_informe === 'enviado' ? '✓ Enviado a cobrar' : 'Informe guardado — pendiente de envío'}
                </div> </div>`;

            document.getElementById('inf-body').innerHTML = html;
            document.getElementById('inf-footer').innerHTML = `
                <button class="inf-btn-sec" id="inf-btnCerrarVista">Cerrar</button> ${puedeEditar ? `<button class="inf-btn-borrador" id="inf-btnEditar">Editar informe</button>` : ''}
                ${cita.estado_informe !== 'enviado' && PUEDE_REGISTRAR ? `<button class="inf-btn-pri" id="inf-btnEnviar">Enviar a cobrar</button>` : ''}`;

            document.getElementById('inf-btnCerrarVista')?.addEventListener('click', cerrar);
            document.getElementById('inf-btnEditar')?.addEventListener('click', () => mostrarFormulario(cita, true));
            document.getElementById('inf-btnEnviar')?.addEventListener('click', () => {
                document.getElementById('inf-btnConfirmarEnvio').dataset.idInforme = cita.id_informe;
                document.getElementById('inf-modalEnvio').style.display = 'flex';
            });
        });
    }

    // ── Formulario ────────────────────────────────────────────────
    function mostrarFormulario(cita, esEdicion) {
        document.getElementById('inf-titulo').textContent = esEdicion ? 'Editar Informe' : 'Registrar Informe';
        planModificado      = !esEdicion;
        serviciosInforme    = [];
        aparatologiaInforme = [];

        document.getElementById('inf-body').innerHTML = `
            <input type="hidden" id="inf-idCita" value="${cita.id_cita}"> <input type="hidden" id="inf-idInforme" value="${cita.id_informe||''}"> <div class="inf-seccion"> <h4 class="inf-seccion-titulo">Datos Clínicos</h4> <div class="inf-grupo"><label>Motivo de cita</label> <textarea id="inf-motivo" rows="2" placeholder="Motivo...">${cita.motivo_consulta||''}</textarea></div> <div class="inf-grupo"><label>Hallazgos clínicos <span class="inf-req">*</span></label> <textarea id="inf-diagnostico" rows="2" placeholder="Hallazgos...">${cita.diagnostico||''}</textarea></div> <div class="inf-grupo"><label>Tratamiento realizado <span class="inf-req">*</span></label> <textarea id="inf-tratamiento" rows="3" placeholder="Tratamiento...">${cita.tratamiento_realizado||''}</textarea></div> <div class="inf-fila"> <div class="inf-grupo"><label>Materiales</label> <textarea id="inf-materiales" rows="2" placeholder="Materiales...">${cita.materiales||''}</textarea></div> <div class="inf-grupo"><label>Observaciones</label> <textarea id="inf-observaciones" rows="2" placeholder="Observaciones...">${cita.observaciones||''}</textarea></div> </div> </div> <div class="inf-seccion"> <div class="inf-seccion-header"> <h4 class="inf-seccion-titulo">Servicios Realizados</h4> <button type="button" class="inf-btn-agregar" id="inf-btnAbrirServicio">+ Agregar servicio</button> </div> <div id="inf-listaServicios"><p class="inf-vacio">No hay servicios agregados</p></div> <div class="inf-total-servicios"><span>Total estimado:</span><span id="inf-totalServicios">S/ 0.00</span></div> </div> <div class="inf-seccion"> <h4 class="inf-seccion-titulo">Plan de Tratamiento</h4> <div class="inf-opciones"> <label class="inf-radio-label"><input type="radio" name="inf-tipoPlan" value="sin_plan" checked> Sin plan</label> <label class="inf-radio-label"><input type="radio" name="inf-tipoPlan" value="plan_existente"> Asociar plan existente</label> <label class="inf-radio-label"><input type="radio" name="inf-tipoPlan" value="nuevo_plan"> Crear nuevo plan</label> </div> <div id="inf-selectorPlanExistente" style="display:none;" class="inf-grupo"> <label>Plan activo del paciente:</label> <select id="inf-selPlanExistente" class="inf-select"> <option value="">Seleccionar plan...</option> </select> </div> <div id="inf-formularioPlan" style="display:none;" class="inf-plan-form"> <div class="inf-grupo"> <label>Tipo de plan: <span class="inf-req">*</span></label> <select id="inf-selPlanCatalogo" class="inf-select"><option value="">Seleccionar...</option></select> <small id="inf-planRef" class="inf-nota"></small> </div> <div class="inf-grupo"> <label>Tipo de cobro: <span class="inf-req">*</span></label> <div class="inf-opciones"> <label class="inf-radio-label"><input type="radio" name="inf-tipoCobro" value="costo_total"> Costo total <small>(Ej: Endodoncia)</small></label> <label class="inf-radio-label"><input type="radio" name="inf-tipoCobro" value="por_sesion"> Por sesión <small>(Ej: Ortodoncia)</small></label> </div> </div> <div id="inf-secCostoTotal" style="display:none;" class="inf-bloque"> <div class="inf-fila"> <div class="inf-grupo"><label>Monto total pactado (S/) <span class="inf-req">*</span></label> <input type="number" id="inf-montoTotal" step="0.01" min="0" placeholder="0.00" class="inf-input"></div> <div class="inf-grupo"><label>Sesiones estimadas</label> <input type="number" id="inf-sesionesTotal" min="1" value="1" class="inf-input"></div> </div> <div class="inf-grupo"><label class="inf-radio-label"><input type="checkbox" id="inf-enCuotas"> Se paga en cuotas</label></div> <div id="inf-resumenCuotas" class="inf-resumen-cuotas" style="display:none;"><span id="inf-cuotaEstimada"></span></div> </div> <div id="inf-secPorSesion" style="display:none;" class="inf-bloque"> <div class="inf-grupo"><label class="inf-radio-label"><input type="checkbox" id="inf-tieneCuotaIni"> Tiene cuota inicial</label></div> <div id="inf-cuotaIniContenedor" style="display:none;"> <div class="inf-fila"> <div class="inf-grupo"><label>Descripción <span class="inf-req">*</span></label> <input type="text" id="inf-descCuotaIni" class="inf-input" placeholder="Ej: Instalación de brackets"></div> <div class="inf-grupo"><label>Monto (S/) <span class="inf-req">*</span></label> <input type="number" id="inf-montoCuotaIni" step="0.01" min="0" placeholder="0.00" class="inf-input"></div> </div> </div> <div class="inf-fila"> <div class="inf-grupo"><label>Sesiones estimadas</label> <input type="number" id="inf-sesionesPorSesion" min="1" value="1" class="inf-input"></div> <div class="inf-grupo"><label>Monto estimado por sesión (S/)</label> <input type="number" id="inf-montoPorSesion" step="0.01" min="0" placeholder="Opcional" class="inf-input"> <small class="inf-nota">Referencial — ajustable al cobrar</small></div> </div> </div> <div class="inf-bloque"> <div class="inf-seccion-header"> <label>Aparatología usada:</label> <button type="button" class="inf-btn-agregar" id="inf-btnAbrirAparato">+ Agregar aparato</button> </div> <div id="inf-listaAparatologia"><p class="inf-vacio">Sin aparatología registrada</p></div> </div> <div class="inf-grupo" style="margin-top:10px;"> <label>Notas del plan</label> <textarea id="inf-planNotas" rows="2" placeholder="Observaciones del plan..."></textarea> </div> </div> </div>`;

        document.getElementById('inf-footer').innerHTML = `
            <button class="inf-btn-sec" id="inf-btnCancelarForm">Cancelar</button> <button class="inf-btn-pri" id="inf-btnGuardar">Guardar informe</button>`;

        // Poblar select plan catálogo
        const selPC = document.getElementById('inf-selPlanCatalogo');
        catalogoPlanes.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id_plan; opt.textContent = p.nombre_plan;
            opt.dataset.precio = p.costo_base || 0;
            selPC.appendChild(opt);
        });

        // Poblar select plan existente
        const selPE = document.getElementById('inf-selPlanExistente');
        selPE.innerHTML = '<option value="">Seleccionar plan...</option>';
        planesActivos.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id_paciente_plan;
            opt.textContent = `${p.nombre_plan} (${p.tipo === 'costo_total' ? 'Costo fijo' : 'Por sesión'})`;
            selPE.appendChild(opt);
        });

        registrarListenersFormulario();

        // Cargar datos si es edición
        if (cita.id_informe) {
            fetch(`${CTRL}?accion=listar_servicios_cita&id_cita=${cita.id_cita}`)
                .then(r => r.json())
                .then(servicios => {
                    serviciosInforme = servicios.map(s => ({
                        id_tipo_servicio: s.id_tipo_servicio,
                        nombre_servicio:  s.nombre_servicio,
                        cantidad:         parseInt(s.cantidad),
                        precio_unitario:  parseFloat(s.precio_unitario),
                        subtotal:         parseFloat(s.subtotal)
                    }));
                    renderServicios();
                });

            if (cita.id_paciente_plan) {
                // Cargar aparatología SOLO de esta cita, luego precargar plan
                fetch(`${CTRL}?accion=listar_aparatologia_plan&id_paciente_plan=${cita.id_paciente_plan}`)
                    .then(r => r.json())
                    .then(aparatos => {
                        const deEstaCita = aparatos.filter(a => String(a.id_cita) === String(cita.id_cita));
                        aparatologiaInforme = deEstaCita.map(a => ({
                            id_aparatologia:   a.id_aparatologia,
                            nombre:            a.descripcion,
                            cantidad:          parseInt(a.cantidad) || 1,
                            precio_base_ref:   parseFloat(a.precio_base_ref),
                            precio_acordado:   parseFloat(a.precio_acordado),
                            subtotal:          (parseInt(a.cantidad) || 1) * parseFloat(a.precio_acordado),
                            incluida_en_costo: parseInt(a.incluida_en_costo)
                        }));
                        // Precargar plan DESPUÉS de tener la aparatología
                        window._precargando = true;
                        precargarPlan(cita);
                        setTimeout(() => { window._precargando = false; }, 300);
                    });
            }
        }

        document.getElementById('inf-btnCancelarForm')?.addEventListener('click', () => {
            cita.id_informe ? mostrarVistaInforme(cita) : cerrar();
        });
        document.getElementById('inf-btnGuardar')?.addEventListener('click', guardar);
    }

    // ── Precargar plan ────────────────────────────────────────────
    function precargarPlan(cita) {
        const radios = document.querySelectorAll('input[name="inf-tipoPlan"]');
        const planDeCita = planesActivos.find(
            p => String(p.id_paciente_plan) === String(cita.id_paciente_plan) &&
                 String(p.creado_en_cita)   === String(cita.id_cita)
        );

        if (planDeCita) {
            // Plan creado en esta cita → mostrar formulario nuevo plan precargado
            if (radios[2]) radios[2].checked = true;
            document.getElementById('inf-formularioPlan').style.display = 'block';

            const selPC = document.getElementById('inf-selPlanCatalogo');
            if (selPC) selPC.value = planDeCita.id_plan;

            const tipoCobro = planDeCita.tipo;
            document.querySelectorAll('input[name="inf-tipoCobro"]').forEach(r => {
                if (r.value === tipoCobro) r.checked = true;
            });

            document.getElementById('inf-secCostoTotal').style.display = tipoCobro === 'costo_total' ? 'block' : 'none';
            document.getElementById('inf-secPorSesion').style.display  = tipoCobro === 'por_sesion'  ? 'block' : 'none';

            if (tipoCobro === 'costo_total') {
                const elM = document.getElementById('inf-montoTotal');
                if (elM) elM.value = planDeCita.costo_acordado || '';
                const elS = document.getElementById('inf-sesionesTotal');
                if (elS) elS.value = planDeCita.sesiones_pago_est || 1;
                const chk = document.getElementById('inf-enCuotas');
                if (chk) {
                    chk.checked = planDeCita.en_cuotas == 1;
                    chk.dispatchEvent(new Event('change'));
                }
            } else {
                const cuotaIni = parseFloat(planDeCita.cuota_inicial || 0);
                if (cuotaIni > 0) {
                    const chk = document.getElementById('inf-tieneCuotaIni');
                    if (chk) { chk.checked = true; chk.dispatchEvent(new Event('change')); }
                    const elM = document.getElementById('inf-montoCuotaIni');
                    if (elM) elM.value = cuotaIni;
                }
                const elS = document.getElementById('inf-sesionesPorSesion');
                if (elS) elS.value = planDeCita.sesiones_pago_est || 1;
                const elC = document.getElementById('inf-montoPorSesion');
                if (elC) elC.value = planDeCita.costo_estimado_sesion || '';
            }

            const elN = document.getElementById('inf-planNotas');
            if (elN) elN.value = planDeCita.notas || '';

            // Renderizar aparatología de esta cita en el formulario
            renderAparatologia();

        } else {
            // Plan de otra cita → Asociar plan existente
            if (radios[1]) radios[1].checked = true;
            document.getElementById('inf-selectorPlanExistente').style.display = 'block';
            const selPE = document.getElementById('inf-selPlanExistente');
            if (selPE) {
                selPE.value = cita.id_paciente_plan;
                // Cargar resumen después de que termine el flag _precargando
                setTimeout(() => cargarResumenPlan(cita.id_paciente_plan), 350);
            }
        }
    }

    // ── Resumen plan existente ────────────────────────────────────
    function cargarResumenPlan(id_pp) {
        fetch(`${CTRL}?accion=obtener_resumen_plan&id_paciente_plan=${id_pp}&id_cita=${citaActual.id_cita}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const { plan, aparatologia, sesiones, sesion_actual } = data;

                const apIncluida = aparatologia.filter(a => a.incluida_en_costo == 1);
                const apSeparadaAnterior = aparatologia.filter(
                    a => a.incluida_en_costo == 0 && String(a.id_cita) !== String(citaActual.id_cita)
                );

                const costoSesion = (plan.tipo === 'costo_total' && plan.en_cuotas == 0)
                    ? 0 : parseFloat(plan.costo_estimado_sesion || 0);

                let html = `<div class="inf-plan-resumen" id="inf-resumenPlanExistente">`;

                html += `<div class="inf-plan-header"> <span class="inf-plan-nombre">${plan.nombre_plan}</span> <span class="inf-plan-tipo">${plan.tipo === 'costo_total' ? 'Costo total' : 'Por sesión'}</span> </div>`;

                // Info plan
                html += `<div class="inf-plan-bloque">`;
                if (plan.tipo === 'costo_total') {
                    html += `
                        <div class="inf-ap-item"><span>Monto total pactado</span> <span class="inf-valor inf-destacado">S/ ${parseFloat(plan.costo_acordado||0).toFixed(2)}</span></div> <div class="inf-ap-item"><span>Pago en cuotas</span> <span>${plan.en_cuotas == 1 ? 'Sí — S/ ' + parseFloat(plan.costo_estimado_sesion||0).toFixed(2) + ' por sesión' : 'No — pago único'}</span></div>`;
                } else {
                    if (parseFloat(plan.cuota_inicial||0) > 0) {
                        html += `<div class="inf-ap-item"><span>Cuota inicial</span> <span class="inf-valor inf-destacado">S/ ${parseFloat(plan.cuota_inicial).toFixed(2)}</span></div>`;
                    }
                    html += `
                        <div class="inf-ap-item"><span>Sesiones estimadas</span><span>${plan.sesiones_pago_est || '—'}</span></div> <div class="inf-ap-item"><span>Costo por sesión</span><span>S/ ${parseFloat(plan.costo_estimado_sesion||0).toFixed(2)}</span></div>`;
                }
                html += `</div>`;

                // Aparatología incluida (solo lectura)
                if (apIncluida.length) {
                    html += `<div class="inf-plan-bloque"> <div class="inf-bloque-titulo">Aparatología incluida en costo inicial</div> ${apIncluida.map(a => `
                            <div class="inf-ap-item readonly"> <span>${a.descripcion}</span> <span>S/ ${parseFloat(a.precio_acordado).toFixed(2)}</span> <span class="inf-badge incluida">Incluida</span> </div>`).join('')}
                    </div>`;
                }

                // Aparatología separada anterior (solo lectura)
                if (apSeparadaAnterior.length) {
                    html += `<div class="inf-plan-bloque"> <div class="inf-bloque-titulo">Aparatología adicional (sesiones anteriores)</div> ${apSeparadaAnterior.map(a => `
                            <div class="inf-ap-item readonly"> <span>${a.descripcion}</span> <span>${a.fecha_cita ? formatFecha(a.fecha_cita) : '—'}</span> <span>S/ ${parseFloat(a.precio_acordado).toFixed(2)}</span> <span class="inf-badge separada">Separada</span> </div>`).join('')}
                    </div>`;
                }

                // Sesiones
                const mostrarSesiones = plan.tipo !== 'costo_total' || plan.en_cuotas == 1;
                if (mostrarSesiones) {
                    html += `<div class="inf-plan-bloque"> <div class="inf-bloque-titulo">Sesiones — Estimado: S/ ${parseFloat(plan.costo_estimado_sesion||0).toFixed(2)} por sesión</div>`;

                    sesiones.forEach(s => {
                        const pagado = parseFloat(s.pagado_sesion || s.total_pagado || 0);
                        const costo  = parseFloat(s.costo_sesion || 0);
                        const esCitaActual = String(s.id_cita) === String(citaActual.id_cita);
                        html += `<div class="inf-sesion-item ${esCitaActual ? 'actual' : ''}"> <span class="inf-ses-num">Sesión #${s.numero_sesion}</span> <span class="inf-ses-fecha">${formatFecha(s.fecha_cita)}</span> <span class="inf-ses-costo">S/ ${costo.toFixed(2)}</span> <span class="inf-ses-pagado ${pagado >= costo ? 'ok' : 'pendiente'}">Pagado: S/ ${pagado.toFixed(2)}</span> ${esCitaActual ? '<span class="inf-badge actual">Esta cita</span>' : ''}
                        </div>`;
                    });

                    if (!sesiones.some(s => String(s.id_cita) === String(citaActual.id_cita))) {
                        html += `<div class="inf-sesion-item nueva"> <span class="inf-ses-num">Sesión #${sesion_actual}</span> <span class="inf-ses-fecha">Hoy</span> <span class="inf-ses-costo">S/ ${costoSesion.toFixed(2)} (estimado)</span> <span class="inf-badge nueva">Esta cita</span> </div>`;
                    }
                    html += `</div>`;
                }

                // Aparatología de esta sesión (editable)
                html += `<div class="inf-plan-bloque"> <div class="inf-seccion-header"> <div class="inf-bloque-titulo">Aparatología en esta sesión</div> <button type="button" class="inf-btn-agregar" id="inf-btnAgregarApSesion">+ Agregar aparato</button> </div> <div id="inf-listaApSesion"><p class="inf-vacio">Sin aparatología en esta sesión</p></div> </div>`;

                html += `</div>`;

                const selExist = document.getElementById('inf-selectorPlanExistente');
                const existente = document.getElementById('inf-resumenPlanExistente');
                if (existente) existente.remove();
                if (selExist) selExist.insertAdjacentHTML('afterend', html);

                // Renderizar aparatología de esta sesión
                renderApSesion();

                document.getElementById('inf-btnAgregarApSesion')?.addEventListener('click', () => {
                    document.getElementById('inf-selAparato').value        = '';
                    document.getElementById('inf-apPrecioRef').value       = '';
                    document.getElementById('inf-apPrecioAcordado').value  = '';
                    const rSep = document.querySelector('input[name="inf-apCobro"][value="separada"]');
                    const rInc = document.querySelector('input[name="inf-apCobro"][value="incluida"]');
                    if (rSep) rSep.checked  = true;
                    if (rInc) rInc.disabled = true;
                    window._infApTarget = 'sesion';
                    document.getElementById('inf-modalAparato').style.display = 'flex';
                });
            });
    }

    // ── Render aparatología sesión ────────────────────────────────
    function renderApSesion() {
        const lista = document.getElementById('inf-listaApSesion');
        if (!lista) return;
        if (!aparatologiaInforme.length) {
            lista.innerHTML = '<p class="inf-vacio">Sin aparatología en esta sesión</p>';
            return;
        }
        lista.innerHTML = aparatologiaInforme.map((a, i) => {
            const cant     = a.cantidad || 1;
            const unitario = parseFloat(a.precio_acordado);
            const subtotal = cant * unitario;
            return `
            <div class="inf-ap-item"> <span>${a.nombre}</span> <span class="inf-srv-cant">x${cant}</span> <span>S/ ${unitario.toFixed(2)} c/u</span> <span class="inf-srv-sub">S/ ${subtotal.toFixed(2)}</span> <span class="inf-badge separada">Separada</span> <button class="inf-btn-del" onclick="window._infEliminarApSesion(${i})">&#10005;</button> </div>`;
        }).join('');
    }

    window._infEliminarApSesion = function(idx) {
        aparatologiaInforme.splice(idx, 1);
        renderApSesion();
    };

    // ── Listeners formulario ──────────────────────────────────────
    function registrarListenersFormulario() {
        // Marcar planModificado al tocar campos del plan
        ['inf-selPlanCatalogo','inf-montoTotal','inf-sesionesTotal','inf-enCuotas',
         'inf-tieneCuotaIni','inf-montoCuotaIni','inf-sesionesPorSesion','inf-montoPorSesion','inf-planNotas'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => { if (!window._precargando) planModificado = true; });
                el.addEventListener('input',  () => { if (!window._precargando) planModificado = true; });
            }
        });
        document.querySelectorAll('input[name="inf-tipoCobro"]').forEach(r => {
            r.addEventListener('change', () => { if (!window._precargando) planModificado = true; });
        });

        // Cambiar tipo plan
        document.querySelectorAll('input[name="inf-tipoPlan"]').forEach(radio => {
            radio.onchange = () => {
                document.getElementById('inf-selectorPlanExistente').style.display =
                    radio.value === 'plan_existente' ? 'block' : 'none';
                document.getElementById('inf-formularioPlan').style.display =
                    radio.value === 'nuevo_plan' ? 'block' : 'none';
                // Limpiar aparatología y resumen al cambiar
                aparatologiaInforme = [];
                const resExist = document.getElementById('inf-resumenPlanExistente');
                if (resExist) resExist.remove();
                renderAparatologia();
            };
        });

        // Cambiar plan existente → cargar resumen
        document.getElementById('inf-selPlanExistente')?.addEventListener('change', function() {
            aparatologiaInforme = []; // Limpiar aparatología al cambiar de plan
            const resExist = document.getElementById('inf-resumenPlanExistente');
            if (resExist) resExist.remove();
            if (!this.value) return;
            cargarResumenPlan(this.value);
        });

        // Tipo cobro
        document.querySelectorAll('input[name="inf-tipoCobro"]').forEach(radio => {
            radio.onchange = () => {
                document.getElementById('inf-secCostoTotal').style.display = radio.value === 'costo_total' ? 'block' : 'none';
                document.getElementById('inf-secPorSesion').style.display  = radio.value === 'por_sesion'  ? 'block' : 'none';
            };
        });

        // Cuota estimada
        const calcCuota = () => {
            const monto    = parseFloat(document.getElementById('inf-montoTotal')?.value || 0);
            const sesiones = parseInt(document.getElementById('inf-sesionesTotal')?.value || 1);
            const enCuotas = document.getElementById('inf-enCuotas')?.checked;
            const resumen  = document.getElementById('inf-resumenCuotas');
            const label    = document.getElementById('inf-cuotaEstimada');
            if (resumen) {
                resumen.style.display = (enCuotas && monto > 0) ? 'block' : 'none';
                if (label && enCuotas && monto > 0)
                    label.textContent = `Estimado por sesión: S/ ${(monto / sesiones).toFixed(2)}`;
            }
        };
        document.getElementById('inf-enCuotas')?.addEventListener('change', calcCuota);
        document.getElementById('inf-montoTotal')?.addEventListener('input', calcCuota);
        document.getElementById('inf-sesionesTotal')?.addEventListener('input', calcCuota);

        // Cuota inicial
        document.getElementById('inf-tieneCuotaIni')?.addEventListener('change', function() {
            document.getElementById('inf-cuotaIniContenedor').style.display = this.checked ? 'block' : 'none';
        });

        // Plan catálogo ref
        document.getElementById('inf-selPlanCatalogo')?.addEventListener('change', function() {
            const precio = parseFloat(this.options[this.selectedIndex]?.dataset.precio || 0);
            const label  = document.getElementById('inf-planRef');
            if (label) label.textContent = precio > 0 ? `Costo referencial: S/ ${precio.toFixed(2)}` : '';
        });

        // Abrir modales
        document.getElementById('inf-btnAbrirServicio')?.addEventListener('click', () => {
            document.getElementById('inf-selServicio').value    = '';
            document.getElementById('inf-srvCantidad').value   = 1;
            document.getElementById('inf-srvPrecio').value     = '';
            document.getElementById('inf-modalServicio').style.display = 'flex';
        });

        document.getElementById('inf-btnAbrirAparato')?.addEventListener('click', () => {
            document.getElementById('inf-selAparato').value        = '';
            document.getElementById('inf-apPrecioRef').value       = '';
            document.getElementById('inf-apPrecioAcordado').value  = '';
            const r = document.querySelector('input[name="inf-apCobro"][value="incluida"]');
            if (r) { r.checked = true; r.disabled = false; }
            window._infApTarget = 'nuevo_plan';
            document.getElementById('inf-modalAparato').style.display = 'flex';
        });
    }

    // ── Render servicios ──────────────────────────────────────────
    function renderServicios() {
        const lista = document.getElementById('inf-listaServicios');
        if (!lista) return;
        if (!serviciosInforme.length) {
            lista.innerHTML = '<p class="inf-vacio">No hay servicios agregados</p>';
            const tot = document.getElementById('inf-totalServicios');
            if (tot) tot.textContent = 'S/ 0.00';
            return;
        }
        lista.innerHTML = serviciosInforme.map((s, i) => `
            <div class="inf-srv-item-form"> <span class="inf-srv-nombre">${s.nombre_servicio}</span> <span class="inf-srv-cant">x${s.cantidad}</span> <span class="inf-srv-precio">S/ ${s.precio_unitario.toFixed(2)}</span> <span class="inf-srv-sub">S/ ${s.subtotal.toFixed(2)}</span> <button class="inf-btn-del" onclick="window._infEliminarSrv(${i})">&#10005;</button> </div>`).join('');
        const total = serviciosInforme.reduce((a, s) => a + s.subtotal, 0);
        const tot = document.getElementById('inf-totalServicios');
        if (tot) tot.textContent = `S/ ${total.toFixed(2)}`;
    }

    window._infEliminarSrv = function(idx) { serviciosInforme.splice(idx, 1); renderServicios(); };

    // ── Render aparatología (para nuevo plan) ─────────────────────
    function renderAparatologia() {
        const lista = document.getElementById('inf-listaAparatologia');
        if (!lista) return;
        if (!aparatologiaInforme.length) {
            lista.innerHTML = '<p class="inf-vacio">Sin aparatología registrada</p>';
            return;
        }
        lista.innerHTML = aparatologiaInforme.map((a, i) => {
            const cant     = a.cantidad || 1;
            const unitario = parseFloat(a.precio_acordado);
            const subtotal = cant * unitario;
            return `
            <div class="inf-srv-item-form"> <span class="inf-srv-nombre">${a.nombre}</span> <span class="inf-srv-cant">x${cant}</span> <span class="inf-srv-precio">S/ ${unitario.toFixed(2)} c/u</span> <span class="inf-srv-sub">S/ ${subtotal.toFixed(2)}</span> <span class="inf-badge ${a.incluida_en_costo ? 'incluida' : 'separada'}">${a.incluida_en_costo ? 'Incluida' : 'Separada'}</span> <button class="inf-btn-del" onclick="window._infEliminarAp(${i})">&#10005;</button> </div>`;
        }).join('');
    }

    window._infEliminarAp = function(idx) { aparatologiaInforme.splice(idx, 1); renderAparatologia(); };

    // ── Guardar ───────────────────────────────────────────────────
    function guardar() {
        const diagnostico = document.getElementById('inf-diagnostico')?.value.trim();
        const tratamiento = document.getElementById('inf-tratamiento')?.value.trim();
        if (!diagnostico || !tratamiento) {
            mostrarMsg('Hallazgos clínicos y tratamiento son obligatorios', 'error');
            return;
        }

        const tipoPlan = document.querySelector('input[name="inf-tipoPlan"]:checked')?.value;
        let id_plan = null, id_paciente_plan = null, nuevoPlan = null;

        if (tipoPlan === 'plan_existente') {
            id_paciente_plan = document.getElementById('inf-selPlanExistente')?.value || null;
            if (id_paciente_plan) {
                const p = planesActivos.find(p => String(p.id_paciente_plan) === String(id_paciente_plan));
                if (p) id_plan = p.id_plan;
            }

        } else if (tipoPlan === 'nuevo_plan') {
            const planDeCita = planesActivos.find(p => String(p.creado_en_cita) === String(citaActual?.id_cita));
            if (planDeCita) id_paciente_plan = planDeCita.id_paciente_plan;

            if (!planModificado) {
                if (planDeCita) id_plan = planDeCita.id_plan;
            } else {
                const tipoCatalogo = document.getElementById('inf-selPlanCatalogo')?.value;
                const tipoCobro    = document.querySelector('input[name="inf-tipoCobro"]:checked')?.value;
                if (!tipoCatalogo) { mostrarMsg('Selecciona el tipo de plan', 'error'); return; }
                if (!tipoCobro)    { mostrarMsg('Selecciona el tipo de cobro', 'error'); return; }

                nuevoPlan = {
                    id_plan_catalogo: tipoCatalogo, tipo: tipoCobro,
                    notas: document.getElementById('inf-planNotas')?.value.trim() || '',
                    aparatologia: aparatologiaInforme
                };

                if (tipoCobro === 'costo_total') {
                    const monto = parseFloat(document.getElementById('inf-montoTotal')?.value || 0);
                    if (!monto) { mostrarMsg('El monto total es obligatorio', 'error'); return; }
                    const sesiones = parseInt(document.getElementById('inf-sesionesTotal')?.value || 1);
                    const enCuotas = document.getElementById('inf-enCuotas')?.checked ? 1 : 0;
                    Object.assign(nuevoPlan, { costo_acordado: monto, en_cuotas: enCuotas, sesiones_pago_est: sesiones,
                        costo_estimado_sesion: enCuotas ? parseFloat((monto/sesiones).toFixed(2)) : null });
                } else {
                    const tieneCuota = document.getElementById('inf-tieneCuotaIni')?.checked;
                    Object.assign(nuevoPlan, {
                        cuota_inicial: tieneCuota ? parseFloat(document.getElementById('inf-montoCuotaIni')?.value || 0) : 0,
                        sesiones_pago_est: parseInt(document.getElementById('inf-sesionesPorSesion')?.value || 1),
                        costo_estimado_sesion: parseFloat(document.getElementById('inf-montoPorSesion')?.value || 0) || null
                    });
                }
            }
        }

        fetch(`${CTRL}?accion=guardar_informe`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_cita:               document.getElementById('inf-idCita')?.value,
                id_paciente_plan,
                motivo_consulta:       document.getElementById('inf-motivo')?.value.trim(),
                diagnostico,
                tratamiento_realizado: tratamiento,
                materiales:            document.getElementById('inf-materiales')?.value.trim(),
                observaciones:         document.getElementById('inf-observaciones')?.value.trim(),
                estado:                'borrador',
                id_plan,
                nuevo_plan:            nuevoPlan,
                servicios:             serviciosInforme,
                aparatologia_sesion:   tipoPlan === 'plan_existente' ? aparatologiaInforme : []
            })
        })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                // PHP emitió output antes del JSON — intentar extraer el JSON del final
                const match = text.match(/(\{.*\})\s*$/s);
                if (match) {
                    try { data = JSON.parse(match[1]); } catch (e2) { data = null; }
                }
                if (!data) {
                    console.error('Respuesta no-JSON del servidor:', text);
                    mostrarMsg('Error de conexión', 'error');
                    return;
                }
            }
            if (data.success) {
                mostrarMsg('Informe guardado correctamente', 'exito');
                cerrar();
                try { if (callbackAlGuardar) callbackAlGuardar(); } catch (cbErr) { console.error('Error en callback:', cbErr); }
            } else {
                mostrarMsg(data.mensaje || 'Error al guardar', 'error');
            }
        })
        .catch(err => {
            if (err instanceof SyntaxError) {
                mostrarMsg('Informe guardado (error de respuesta del servidor)', 'exito');
            } else {
                console.error('Error al guardar informe:', err);
                mostrarMsg('Error de conexión', 'error');
            }
        });
    }

    // ── Listeners globales ────────────────────────────────────────
    document.getElementById('infBtnCerrar')?.addEventListener('click', cerrar);

    document.getElementById('inf-btnConfirmarServicio')?.addEventListener('click', () => {
        const sel    = document.getElementById('inf-selServicio');
        const id     = sel.value;
        const nombre = sel.options[sel.selectedIndex]?.textContent || '';
        const cant   = parseInt(document.getElementById('inf-srvCantidad').value || 1);
        const precio = parseFloat(document.getElementById('inf-srvPrecio').value || 0);
        if (!id) { mostrarMsg('Selecciona un servicio', 'error'); return; }
        const existeSrv = serviciosInforme.findIndex(s => String(s.id_tipo_servicio) === String(id));
        if (existeSrv >= 0) {
            serviciosInforme[existeSrv].cantidad += cant;
            serviciosInforme[existeSrv].subtotal  = serviciosInforme[existeSrv].cantidad * serviciosInforme[existeSrv].precio_unitario;
        } else {
            serviciosInforme.push({ id_tipo_servicio: id, nombre_servicio: nombre, cantidad: cant, precio_unitario: precio, subtotal: cant * precio });
        }
        renderServicios();
        document.getElementById('inf-modalServicio').style.display = 'none';
    });

    document.getElementById('inf-selServicio')?.addEventListener('change', function() {
        document.getElementById('inf-srvPrecio').value =
            parseFloat(this.options[this.selectedIndex]?.dataset.precio || 0).toFixed(2);
    });

    document.getElementById('inf-btnConfirmarAparato')?.addEventListener('click', () => {
        const sel   = document.getElementById('inf-selAparato');
        const id    = sel.value;
        const nombre = sel.options[sel.selectedIndex]?.textContent || '';
        const ref   = parseFloat(document.getElementById('inf-apPrecioRef').value || 0);
        const acord = parseFloat(document.getElementById('inf-apPrecioAcordado').value || 0);
        const cobro = document.querySelector('input[name="inf-apCobro"]:checked')?.value;
        if (!id)     { mostrarMsg('Selecciona un aparato', 'error'); return; }
        if (acord<=0){ mostrarMsg('El precio debe ser mayor a 0', 'error'); return; }

        const cantAp   = parseInt(document.getElementById('inf-apCantidad')?.value || 1);
        const existeAp = aparatologiaInforme.findIndex(a => String(a.id_aparatologia) === String(id));
        if (existeAp >= 0) {
            aparatologiaInforme[existeAp].cantidad = (aparatologiaInforme[existeAp].cantidad || 1) + cantAp;
            aparatologiaInforme[existeAp].subtotal  = aparatologiaInforme[existeAp].cantidad * aparatologiaInforme[existeAp].precio_acordado;
        } else {
            aparatologiaInforme.push({
                id_aparatologia: id, nombre, cantidad: cantAp,
                precio_base_ref: ref, precio_acordado: acord,
                subtotal: cantAp * acord,
                incluida_en_costo: cobro === 'incluida' ? 1 : 0
            });
        }

        if (window._infApTarget === 'sesion') {
            renderApSesion();
            window._infApTarget = null;
            const rInc = document.querySelector('input[name="inf-apCobro"][value="incluida"]');
            if (rInc) rInc.disabled = false;
        } else {
            renderAparatologia();
        }
        document.getElementById('inf-modalAparato').style.display = 'none';
    });

    document.getElementById('inf-selAparato')?.addEventListener('change', function() {
        const precio = parseFloat(this.options[this.selectedIndex]?.dataset.precio || 0);
        document.getElementById('inf-apPrecioRef').value      = precio.toFixed(2);
        document.getElementById('inf-apPrecioAcordado').value = precio.toFixed(2);
    });

    document.getElementById('inf-btnConfirmarEnvio')?.addEventListener('click', function() {
        const id_informe = this.dataset.idInforme;
        if (!id_informe) return;
        const fd = new FormData();
        fd.append('accion', 'enviar_a_cobrar');
        fd.append('id_informe', id_informe);
        fetch(CTRL, { method: 'POST', body: fd })
            .then(r => r.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    const match = text.match(/(\{.*\})\s*$/s);
                    if (match) try { data = JSON.parse(match[1]); } catch (e2) {}
                }
                document.getElementById('inf-modalEnvio').style.display = 'none';
                if (!data) { mostrarMsg('Error de conexión', 'error'); return; }
                mostrarMsg(data.success ? 'Enviado a cobrar' : data.mensaje, data.success ? 'exito' : 'error');
                if (data.success) { cerrar(); try { if (callbackAlGuardar) callbackAlGuardar(); } catch (cbErr) { console.error('Error en callback:', cbErr); } }
            })
            .catch(err => {
                console.error('Error al enviar:', err);
                mostrarMsg('Error de conexión', 'error');
            });
    });

    // ── Helpers ───────────────────────────────────────────────────


})();