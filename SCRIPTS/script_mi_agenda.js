function iniciarModuloMiAgenda() {

    // ── Estado global ─────────────────────────────────────────────
    let todasLasCitas  = [];   // citas cargadas del servidor
    let citasFiltradas = [];   // citas después de aplicar filtros de cliente
    let vistaActual    = 'lista';
    let periodoActivo  = 'hoy';

    // ── Elementos DOM ─────────────────────────────────────────────
    const contenedor       = document.getElementById('contenedorCitas');
    const fechaDesdeInput  = document.getElementById('fechaDesde');
    const fechaHastaInput  = document.getElementById('fechaHasta');
    const btnAnterior      = document.getElementById('btnAnterior');
    const btnSiguiente     = document.getElementById('btnSiguiente');
    const btnAplicar       = document.getElementById('btnAplicarRango');
    const btnsCambioVista  = document.querySelectorAll('.btn-vista');
    const btnsPeriodo      = document.querySelectorAll('.btn-periodo');
    const filtroEstado     = document.getElementById('filtroEstado');
    const filtroPaciente   = document.getElementById('filtroPaciente');
    const btnLimpiar       = document.getElementById('btnLimpiarFiltros');
    const panelDetalle     = document.getElementById('panelDetalleCita');
    const contenidoDetalle = document.getElementById('contenidoDetalleCita');
    const btnCerrarPanel   = document.getElementById('btnCerrarPanel');

    // ── Colores por estado ────────────────────────────────────────
    const coloresEstado = {
        'Programada'  : { bg: '#e8f4fd', borde: '#2980b9', texto: '#1a5276' },
        'Confirmada'  : { bg: '#e9f7ef', borde: '#27ae60', texto: '#1e8449' },
        'En atención' : { bg: '#fef9e7', borde: '#f39c12', texto: '#9a7d0a' },
        'Completada'  : { bg: '#f4f6f7', borde: '#95a5a6', texto: '#616a6b' },
        'Cancelada'   : { bg: '#fdedec', borde: '#e74c3c', texto: '#922b21' },
        'No asistió'  : { bg: '#fdf2f8', borde: '#8e44ad', texto: '#6c3483' },
    };

    // ── Helpers de fecha ──────────────────────────────────────────
    function hoy() {
        return formatearFecha(new Date());
    }

    function formatearFecha(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }

    function formatearFechaLegible(str) {
        const [y, m, d] = str.split('-');
        const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        return `${parseInt(d)} ${meses[parseInt(m)-1]} ${y}`;
    }

    function lunesDeEstaSemana() {
        const hoyD = new Date();
        const dow  = hoyD.getDay() === 0 ? 7 : hoyD.getDay();
        const lunes = new Date(hoyD);
        lunes.setDate(hoyD.getDate() - (dow - 1));
        return lunes;
    }

    function calcularEdad(fechaNac) {
        const hoyD = new Date();
        const nac  = new Date(fechaNac);
        let e = hoyD.getFullYear() - nac.getFullYear();
        if (hoyD < new Date(hoyD.getFullYear(), nac.getMonth(), nac.getDate())) e--;
        return e;
    }

    // ── Períodos rápidos ──────────────────────────────────────────
    function aplicarPeriodo(periodo) {
        periodoActivo = periodo;
        btnsPeriodo.forEach(b => b.classList.toggle('activo', b.dataset.periodo === periodo));

        const h = new Date();

        if (periodo === 'hoy') {
            fechaDesdeInput.value = hoy();
            fechaHastaInput.value = hoy();
            cargarDesdeServidor(hoy(), hoy());

        } else if (periodo === 'semana') {
            const lunes   = lunesDeEstaSemana();
            const domingo = new Date(lunes);
            domingo.setDate(lunes.getDate() + 6);
            fechaDesdeInput.value = formatearFecha(lunes);
            fechaHastaInput.value = formatearFecha(domingo);
            cargarDesdeServidor(formatearFecha(lunes), formatearFecha(domingo));

        } else if (periodo === 'mes') {
            const primero = new Date(h.getFullYear(), h.getMonth(), 1);
            const ultimo  = new Date(h.getFullYear(), h.getMonth() + 1, 0);
            fechaDesdeInput.value = formatearFecha(primero);
            fechaHastaInput.value = formatearFecha(ultimo);
            cargarDesdeServidor(formatearFecha(primero), formatearFecha(ultimo));

        } else if (periodo === 'todas') {
            fechaDesdeInput.value = '';
            fechaHastaInput.value = '';
            cargarDesdeServidor(null, null);
        }
    }

    btnsPeriodo.forEach(btn => {
        btn.addEventListener('click', () => aplicarPeriodo(btn.dataset.periodo));
    });

    // ── Rango manual ──────────────────────────────────────────────
    btnAplicar.addEventListener('click', () => {
        const desde = fechaDesdeInput.value;
        const hasta = fechaHastaInput.value;
        if (!desde || !hasta) { mostrarMensaje('Selecciona ambas fechas', 'error'); return; }
        if (desde > hasta)    { mostrarMensaje('La fecha inicio no puede ser mayor al fin', 'error'); return; }
        // Desmarcar período al usar rango manual
        periodoActivo = null;
        btnsPeriodo.forEach(b => b.classList.remove('activo'));
        cargarDesdeServidor(desde, hasta);
    });

    // ── Navegación anterior / siguiente ───────────────────────────
    btnAnterior.addEventListener('click', () => navegar(-1));
    btnSiguiente.addEventListener('click', () => navegar(1));

    function navegar(direccion) {
        const desde = fechaDesdeInput.value;
        const hasta = fechaHastaInput.value;
        if (!desde || !hasta) return;

        const d1    = new Date(desde + 'T00:00:00');
        const d2    = new Date(hasta  + 'T00:00:00');
        const dias  = Math.round((d2 - d1) / 86400000) + 1;

        d1.setDate(d1.getDate() + (direccion * dias));
        d2.setDate(d2.getDate() + (direccion * dias));

        fechaDesdeInput.value = formatearFecha(d1);
        fechaHastaInput.value = formatearFecha(d2);

        periodoActivo = null;
        btnsPeriodo.forEach(b => b.classList.remove('activo'));
        cargarDesdeServidor(formatearFecha(d1), formatearFecha(d2));
    }

    // ── Cambio de vista ───────────────────────────────────────────
    btnsCambioVista.forEach(btn => {
        btn.addEventListener('click', () => {
            btnsCambioVista.forEach(b => b.classList.remove('activo'));
            btn.classList.add('activo');
            vistaActual = btn.dataset.vista;
            renderizar(citasFiltradas);
        });
    });

    // ── Filtros de cliente ────────────────────────────────────────
    filtroEstado.addEventListener('change', aplicarFiltrosCliente);
    filtroPaciente.addEventListener('input', aplicarFiltrosCliente);

    btnLimpiar.addEventListener('click', () => {
        filtroEstado.value   = '';
        filtroPaciente.value = '';
        aplicarFiltrosCliente();
    });

    function aplicarFiltrosCliente() {
        const estado   = filtroEstado.value.toLowerCase();
        const paciente = filtroPaciente.value.toLowerCase().trim();

        citasFiltradas = todasLasCitas.filter(c => {
            const matchEstado   = !estado   || c.estado.toLowerCase() === estado;
            const matchPaciente = !paciente ||
                c.nombre_paciente.toLowerCase().includes(paciente) ||
                (c.dni_paciente && c.dni_paciente.includes(paciente));
            return matchEstado && matchPaciente;
        });

        actualizarContadores(citasFiltradas);
        renderizar(citasFiltradas);
    }

    // ── Carga desde servidor ──────────────────────────────────────
    function cargarDesdeServidor(desde, hasta) {
        contenedor.innerHTML = '<p class="cargando-agenda">Cargando...</p>';
        panelDetalle.style.display = 'none';

        let url = '../CONTROLADORES/controlador_mi_agenda.php?accion=listar';
        if (desde && hasta) url += `&desde=${desde}&hasta=${hasta}`;

        fetch(url)
            .then(r => r.json())
            .then(citas => {
                todasLasCitas = citas;
                aplicarFiltrosCliente();
            })
            .catch(() => {
                contenedor.innerHTML = '<p class="cargando-agenda">Error al cargar las citas.</p>';
            });
    }

    // ── Renderizar según vista ────────────────────────────────────
    function renderizar(citas) {
        if (vistaActual === 'lista') {
            renderLista(citas);
        } else {
            renderCalendario(citas);
        }
    }

    // ── VISTA LISTA ───────────────────────────────────────────────
    function renderLista(citas) {
        if (citas.length === 0) {
            contenedor.innerHTML = `
                <div class="sin-citas"> <p>No hay citas para mostrar con los filtros seleccionados.</p> </div>`;
            return;
        }

        // Agrupar por fecha
        const porDia = {};
        citas.forEach(c => {
            if (!porDia[c.fecha]) porDia[c.fecha] = [];
            porDia[c.fecha].push(c);
        });

        let html = '<div class="lista-citas">';
        Object.entries(porDia).forEach(([fecha, citasDia]) => {
            const f = new Date(fecha + 'T00:00:00');
            const esHoy = fecha === hoy();
            const opFecha = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
            html += `
                <div class="separador-fecha ${esHoy ? 'separador-hoy' : ''}"> ${f.toLocaleDateString('es-PE', opFecha)}
                    ${esHoy ? '<span class="etiqueta-hoy">Hoy</span>' : ''}
                </div>`;

            citasDia.forEach(c => {
                const color = coloresEstado[c.estado] || coloresEstado['Programada'];
                const foto  = c.foto_paciente
                    ? `../${c.foto_paciente}`
                    : '../IMAGENES/perfiles_pacientes/default.png';

                html += `
                    <div class="tarjeta-cita"
                         style="border-left:4px solid ${color.borde}; background:${color.bg};"
                         data-id="${c.id_cita}"> <div class="cita-hora"> <span class="hora-grande">${c.hora.substring(0,5)}</span> </div> <div class="cita-foto"> <img src="${foto}" alt="Paciente"
                                 onerror="this.src='../IMAGENES/perfiles_pacientes/default.png'"> </div> <div class="cita-info"> <p class="cita-paciente">${c.nombre_paciente}</p> <p class="cita-detalle">${c.tipo_atencion || c.nombre_servicio || 'Sin tipo'} &nbsp;|&nbsp; ${c.nombre_sede}</p> ${c.motivo ? `<p class="cita-motivo">${c.motivo}</p>` : ''}
                        </div> <div class="cita-estado-col"> <span class="badge-estado-cita"
                                  style="color:${color.texto}; border-color:${color.borde};"> ${c.estado}
                            </span> <button class="btn-ver-cita" data-id="${c.id_cita}">Ver cita</button> </div> </div>`;
            });
        });
        html += '</div>';
        contenedor.innerHTML = html;

        document.querySelectorAll('.tarjeta-cita').forEach(card => {
            card.addEventListener('click', e => {
                if (e.target.closest('button')) return;
                abrirDetalle(card.dataset.id);
            });
        });

        document.querySelectorAll('.btn-ver-cita').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                abrirDetalle(btn.dataset.id);
            });
        });
    }

    // ── VISTA CALENDARIO ─────────────────────────────────────────
    function renderCalendario(citas) {
        // Determinar mes a mostrar (basado en fechaDesde o hoy)
        const refFecha = fechaDesdeInput.value
            ? new Date(fechaDesdeInput.value + 'T00:00:00')
            : new Date();

        const anio = refFecha.getFullYear();
        const mes  = refFecha.getMonth();

        const primero  = new Date(anio, mes, 1);
        const ultimo   = new Date(anio, mes + 1, 0);
        const inicioDow = primero.getDay() === 0 ? 7 : primero.getDay(); // 1=lunes

        // Indexar citas por fecha
        const porDia = {};
        citas.forEach(c => {
            if (!porDia[c.fecha]) porDia[c.fecha] = [];
            porDia[c.fecha].push(c);
        });

        const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        let html = `
            <div class="calendario-contenedor"> <div class="calendario-mes-nav"> <button class="btn-mes-ant" id="btnMesAnterior">&#8249;</button> <span class="calendario-titulo">${meses[mes]} ${anio}</span> <button class="btn-mes-sig" id="btnMesSiguiente">&#8250;</button> </div> <div class="calendario-grid"> <div class="cal-dia-nombre">Lun</div> <div class="cal-dia-nombre">Mar</div> <div class="cal-dia-nombre">Mié</div> <div class="cal-dia-nombre">Jue</div> <div class="cal-dia-nombre">Vie</div> <div class="cal-dia-nombre">Sáb</div> <div class="cal-dia-nombre">Dom</div>`;

        // Celdas vacías antes del primer día
        for (let i = 1; i < inicioDow; i++) {
            html += `<div class="cal-celda vacia"></div>`;
        }

        for (let dia = 1; dia <= ultimo.getDate(); dia++) {
            const fechaKey = `${anio}-${String(mes+1).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
            const citasDia = porDia[fechaKey] || [];
            const esHoy    = fechaKey === hoy();

            // Contar por estado para los puntos
            const coloresPuntos = [...new Set(citasDia.map(c => (coloresEstado[c.estado] || coloresEstado['Programada']).borde))];

            html += `
                <div class="cal-celda ${esHoy ? 'cal-hoy' : ''} ${citasDia.length > 0 ? 'cal-con-citas' : ''}"
                     data-fecha="${fechaKey}"> <span class="cal-numero">${dia}</span> ${citasDia.length > 0 ? `
                        <div class="cal-puntos"> ${coloresPuntos.slice(0,3).map(c => `<span class="cal-punto" style="background:${c}"></span>`).join('')}
                        </div> <span class="cal-conteo">${citasDia.length} cita${citasDia.length > 1 ? 's' : ''}</span> ` : ''}
                </div>`;
        }

        html += `</div></div>`; // cierra grid y contenedor

        // Mini lista del día seleccionado
        html += `<div id="detalleDiaCalendario" class="detalle-dia-calendario"></div>`;

        contenedor.innerHTML = html;

        // Clic en celda con citas → muestra lista de ese día abajo
        contenedor.querySelectorAll('.cal-celda.cal-con-citas').forEach(celda => {
            celda.addEventListener('click', () => {
                contenedor.querySelectorAll('.cal-celda').forEach(c => c.classList.remove('cal-seleccionada'));
                celda.classList.add('cal-seleccionada');
                mostrarDetalleDiaCalendario(celda.dataset.fecha, porDia[celda.dataset.fecha] || []);
            });
        });

        // Navegación mes
        document.getElementById('btnMesAnterior').addEventListener('click', () => {
            const nueva = new Date(anio, mes - 1, 1);
            fechaDesdeInput.value = formatearFecha(nueva);
            fechaHastaInput.value = formatearFecha(new Date(nueva.getFullYear(), nueva.getMonth() + 1, 0));
            renderCalendario(citas);
        });

        document.getElementById('btnMesSiguiente').addEventListener('click', () => {
            const nueva = new Date(anio, mes + 1, 1);
            fechaDesdeInput.value = formatearFecha(nueva);
            fechaHastaInput.value = formatearFecha(new Date(nueva.getFullYear(), nueva.getMonth() + 1, 0));
            renderCalendario(citas);
        });
    }

    function mostrarDetalleDiaCalendario(fecha, citas) {
        const panel = document.getElementById('detalleDiaCalendario');
        const f = new Date(fecha + 'T00:00:00');
        const op = { weekday: 'long', day: 'numeric', month: 'long' };

        let html = `<div class="detalle-dia-header">${f.toLocaleDateString('es-PE', op)}</div>`;
        citas.forEach(c => {
            const color = coloresEstado[c.estado] || coloresEstado['Programada'];
            html += `
                <div class="tarjeta-cita-mini"
                     style="border-left:3px solid ${color.borde}; background:${color.bg};"
                     data-id="${c.id_cita}"> <span class="hora-mini">${c.hora.substring(0,5)}</span> <span class="paciente-mini">${c.nombre_paciente}</span> <span class="badge-estado-cita" style="color:${color.texto}; border-color:${color.borde};">${c.estado}</span> <button class="btn-ver-cita" data-id="${c.id_cita}">Ver cita</button> </div>`;
        });

        panel.innerHTML = html;

        panel.querySelectorAll('.btn-ver-cita').forEach(btn => {
            btn.addEventListener('click', () => abrirDetalle(btn.dataset.id));
        });
    }

    // ── Detalle lateral ───────────────────────────────────────────
    function abrirDetalle(id_cita) {
        panelDetalle.style.display = 'block';
        contenidoDetalle.innerHTML = '<p class="cargando-agenda">Cargando...</p>';

        fetch(`../CONTROLADORES/controlador_mi_agenda.php?accion=ver_cita&id=${id_cita}`)
            .then(r => r.json())
            .then(c => {
                if (c.success === false) {
                    contenidoDetalle.innerHTML = `<p class="error-detalle">${c.mensaje}</p>`;
                    return;
                }

                const color = coloresEstado[c.estado] || coloresEstado['Programada'];
                const foto  = c.foto_paciente
                    ? `../${c.foto_paciente}`
                    : '../IMAGENES/perfiles_pacientes/default.png';
                const edad  = c.fecha_nacimiento ? calcularEdad(c.fecha_nacimiento) + ' años' : '';

                contenidoDetalle.innerHTML = `
                    <div class="detalle-paciente"> <img src="${foto}" alt="Paciente"
                             onerror="this.src='../IMAGENES/perfiles_pacientes/default.png'"> <div> <p class="detalle-nombre">${c.nombre_paciente}</p> <p class="detalle-sub">DNI: ${c.dni_paciente}${edad ? ' · ' + edad : ''}</p> </div> </div> <div class="detalle-seccion"> <p><strong>Fecha:</strong> ${formatearFechaLegible(c.fecha)}</p> <p><strong>Hora:</strong> ${c.hora.substring(0,5)}</p> <p><strong>Tipo de atención:</strong> ${c.tipo_atencion || c.nombre_servicio || 'No especificado'}</p> <p><strong>Sede:</strong> ${c.nombre_sede}</p> <p><strong>Teléfono:</strong> ${c.telefono_paciente}</p> ${c.motivo ? `<p><strong>Motivo:</strong> ${c.motivo}</p>` : ''}
                        ${c.observaciones ? `<p><strong>Observaciones:</strong> ${c.observaciones}</p>` : ''}
                    </div> <div class="detalle-estado" style="margin-bottom:16px;"> <span class="badge-estado-cita"
                              style="color:${color.texto}; border-color:${color.borde};"> ${c.estado}
                        </span> </div> <div class="detalle-acciones"> <button class="btn-ver-paciente-detalle" data-paciente="${c.id_paciente}" data-pestana="${['4','5'].includes(String(c.id_estado_cita)) ? 'atenciones' : 'citas'}" data-label="${String(c.id_estado_cita) === '4' ? 'Ver atención registrada' : String(c.id_estado_cita) === '5' ? 'Ver historial de atenciones' : 'Ver citas activas'}">${String(c.id_estado_cita) === '4' ? 'Ver atención registrada' : String(c.id_estado_cita) === '5' ? 'Ver historial de atenciones' : 'Ver citas activas'}</button> </div>`;

                const btnVer = contenidoDetalle.querySelector('.btn-ver-paciente-detalle');
                if (btnVer) {
                    btnVer.addEventListener('click', () => {
                        panelDetalle.style.display = 'none';
                        irAHistoriaClinica(btnVer.dataset.paciente, btnVer.dataset.pestana || 'citas', c.id_cita);
                    });
                }
            });
    }

    btnCerrarPanel.addEventListener('click', () => {
        panelDetalle.style.display = 'none';
    });

    // ── Contadores ────────────────────────────────────────────────
    function actualizarContadores(citas) {
        document.getElementById('totalProgramadas').textContent =
            citas.filter(c => c.estado === 'Programada').length;
        document.getElementById('totalConfirmadas').textContent =
            citas.filter(c => c.estado === 'Confirmada').length;
        document.getElementById('totalEnAtencion').textContent =
            citas.filter(c => c.estado === 'En atención').length;
        document.getElementById('totalCompletadas').textContent =
            citas.filter(c => c.estado === 'Completada').length;
    }

    // ── Navegar a Historia Clínica ────────────────────────────────
    function irAHistoriaClinica(id_paciente, pestana = 'citas', id_cita = null) {
        sessionStorage.setItem('paciente_historia_actual', JSON.stringify({
            id: id_paciente,
            pestana: pestana
        }));
        if (id_cita) {
            sessionStorage.setItem('resaltar_cita', id_cita);
        }
        if (typeof window.navegarAModulo === 'function') {
            window.navegarAModulo('historia_clinica');
        }
    }

    // ── Notificación ──────────────────────────────────────────────
    function mostrarMensaje(msg, tipo = 'exito') {
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        aviso.innerHTML = `<span class="texto">${msg}</span>`;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.classList.add('mostrar'), 50);
        setTimeout(() => {
            aviso.classList.remove('mostrar');
            setTimeout(() => aviso.remove(), 400);
        }, 3000);
    }

    // ── Arrancar ──────────────────────────────────────────────────
    aplicarPeriodo('hoy');
}