// =====================================================
// 🏥 MÓDULO DE CITAS MEJORADO - CON PENDIENTES DE INFORME
// =====================================================

(function() {
    console.log('🔵 Iniciando módulo de citas mejorado');

    const listaCitasActivas = document.getElementById('listaCitasActivas');
    const listaPendientes = document.getElementById('listaPendientes');
    const mensajeVacioActivas = document.getElementById('mensajeVacioActivas');
    const mensajeVacioPendientes = document.getElementById('mensajeVacioPendientes');
    const totalCitasActivas = document.getElementById('totalCitasActivas');
    const totalPendientes = document.getElementById('totalPendientes');
    
    const modalAtencion = document.getElementById('modalFormularioAtencion');
    const formAtencion = document.getElementById('formAtencion');
    const btnCerrarModalAtencion = document.getElementById('btnCerrarModalAtencion');
    const btnCancelarAtencion = document.getElementById('btnCancelarAtencion');
    const tituloModalAtencion = document.getElementById('tituloModalAtencion');

    let citasActivasData = [];
    let citasPendientesData = [];

    cargarCitas();
    configurarEventos();
    configurarPestanas();

    // =====================================================
    // 🔧 CONFIGURAR EVENTOS
    // =====================================================
    function configurarEventos() {
        btnCerrarModalAtencion.addEventListener('click', cerrarModalAtencion);
        btnCancelarAtencion.addEventListener('click', cerrarModalAtencion);
        formAtencion.addEventListener('submit', guardarAtencion);

        document.getElementById('precioUnitario').addEventListener('input', calcularTotales);
        document.getElementById('aCuenta').addEventListener('input', calcularTotales);
    }
    // =====================================================
// 🔀 CONFIGURAR PESTAÑAS
// =====================================================
function configurarPestanas() {
    const pestanas = document.querySelectorAll('.pestana');
    const paneles = document.querySelectorAll('.panel-pestana');

    pestanas.forEach(pestana => {
        pestana.addEventListener('click', () => {
            const tipoPestana = pestana.dataset.pestana;

            // Remover clase activa de todas las pestañas y paneles
            pestanas.forEach(p => p.classList.remove('activa'));
            paneles.forEach(panel => panel.classList.remove('activo'));

            // Activar pestaña y panel correspondiente
            pestana.classList.add('activa');
            
            if (tipoPestana === 'activas') {
                document.getElementById('panelActivas').classList.add('activo');
            } else if (tipoPestana === 'pendientes') {
                document.getElementById('panelPendientes').classList.add('activo');
            }
        });
    });
}

    // =====================================================
    // CARGAR CITAS (SEPARADAS POR TIPO)
    // =====================================================
    function cargarCitas() {
        console.log('🔄 Cargando citas...');

        fetch(`../CONTROLADORES/controlador_citas_doctor.php?accion=listar_citas&id_paciente=${window.ID_PACIENTE}&id_doctor=${window.ID_DOCTOR}`)
            .then(res => res.json())
            .then(data => {
                console.log('Citas recibidas:', data);
                separarCitas(data);
                renderizarCitasActivas();
                renderizarPendientes();
            })
            .catch(err => {
                console.error('Error al cargar citas:', err);
                mostrarMensaje('Error al cargar las citas', 'error');
            });
    }

    // =====================================================
    // 🔀 SEPARAR CITAS POR TIPO
    // =====================================================
    function separarCitas(citas) {
        citasActivasData = [];
        citasPendientesData = [];

        citas.forEach(cita => {
            const estadoStr = String(cita.id_estado_cita);
            
            // Citas activas: Programadas (1), Confirmadas (2), En Atención (6)
            if (['1', '2', '6'].includes(estadoStr)) {
                citasActivasData.push(cita);
            }
            
            // Pendientes de informe: Completadas (4) SIN atención registrada
            if (estadoStr === '4' && !cita.id_informe) {
                citasPendientesData.push(cita);
            }
        });

        console.log('Citas activas:', citasActivasData.length);
        console.log('Pendientes de informe:', citasPendientesData.length);
    }

    // =====================================================
    // 🎨 RENDERIZAR CITAS ACTIVAS
    // =====================================================
    function renderizarCitasActivas() {
        console.log('Renderizando citas activas:', citasActivasData.length);

        if (citasActivasData.length === 0) {
            listaCitasActivas.style.display = 'none';
            mensajeVacioActivas.style.display = 'block';
            totalCitasActivas.textContent = '0';
            return;
        }

        listaCitasActivas.style.display = 'flex';
        mensajeVacioActivas.style.display = 'none';
        totalCitasActivas.textContent = citasActivasData.length;

        listaCitasActivas.innerHTML = '';

        citasActivasData.forEach(cita => {
            const tarjeta = crearTarjetaCitaActiva(cita);
            listaCitasActivas.appendChild(tarjeta);
        });
        document.getElementById('contadorActivas').textContent = citasActivasData.length;

        // Resaltar cita si viene desde Mi Agenda
        const idResaltar = sessionStorage.getItem('resaltar_cita');
        if (idResaltar) {
            sessionStorage.removeItem('resaltar_cita');
            const target = listaCitasActivas.querySelector(`[data-id="${idResaltar}"]`)?.closest('.tarjeta-cita');
            if (target) {
                target.classList.add('cita-resaltada');
                setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 150);
                setTimeout(() => target.classList.remove('cita-resaltada'), 3500);
            }
        }
    }

    // =====================================================
    // 🎨 RENDERIZAR PENDIENTES
    // =====================================================
    function renderizarPendientes() {
        console.log('Renderizando pendientes:', citasPendientesData.length);

        if (citasPendientesData.length === 0) {
            listaPendientes.style.display = 'none';
            mensajeVacioPendientes.style.display = 'block';
            totalPendientes.textContent = '0';
            return;
        }

        listaPendientes.style.display = 'flex';
        mensajeVacioPendientes.style.display = 'none';
        totalPendientes.textContent = citasPendientesData.length;

        listaPendientes.innerHTML = '';

        citasPendientesData.forEach(cita => {
            const tarjeta = crearTarjetaPendiente(cita);
            listaPendientes.appendChild(tarjeta);
        });
        // En renderizarPendientes(), agregar:
document.getElementById('contadorPendientes').textContent = citasPendientesData.length;
    }

    // =====================================================
    // 🏷️ CREAR TARJETA DE CITA ACTIVA
    // =====================================================
    function crearTarjetaCitaActiva(cita) {
        const div = document.createElement('div');
        div.className = 'tarjeta-cita compacta';
        
        const estadoStr = String(cita.id_estado_cita);
        
        if (estadoStr === '6') {
            div.classList.add('estado-en-atencion');
        }

        const badgeEstado = obtenerBadgeEstado(cita.estado, estadoStr);
        const horaEstimada = cita.hora || 'No especificada';

        div.innerHTML = `
            <div class="cabecera-compacta" data-id="${cita.id_cita}"> <div class="info-principal"> <div class="fila-principal"> <span class="numero-cita-mini">#${cita.id_cita}</span> ${badgeEstado}
                    </div> <div class="datos-rapidos"> <span class="dato-rapido">${formatearFecha(cita.fecha)}</span> <span class="dato-rapido">⏰ ${horaEstimada}</span> <span class="dato-rapido">${truncarTexto(cita.nombre_servicio || 'Sin servicio', 25)}</span> </div> </div> <div class="acciones-rapidas"> ${generarBotonesRapidos(cita)}
                    <button class="btn-expandir" data-accion="expandir"> <span class="icono-expandir">▼</span> </button> </div> </div> <div class="detalles-expandibles" style="display: none;"> <div class="separador-expansion"></div> <div class="grid-detalles"> ${cita.hora_inicio ? `
                    <div class="detalle-item"> <span class="detalle-label">Hora Inicio</span> <span class="detalle-valor">${cita.hora_inicio}</span> </div> ` : ''}

                    ${cita.hora_fin ? `
                    <div class="detalle-item"> <span class="detalle-label">Hora Fin</span> <span class="detalle-valor">${cita.hora_fin}</span> </div> ` : ''}
                </div> ${cita.motivo ? `
                <div class="motivo-expandible"> <span class="detalle-label">Motivo de Consulta</span> <p class="detalle-valor">${cita.motivo}</p> </div> ` : ''}
            </div> `;

        agregarEventosBotones(div, cita, 'activa');
        return div;
    }

    // =====================================================
    // 🏷️ CREAR TARJETA PENDIENTE
    // =====================================================
    function crearTarjetaPendiente(cita) {
        const div = document.createElement('div');
        div.className = 'tarjeta-cita compacta tarjeta-pendiente';

        const tiempoAtencion = calcularTiempoAtencion(cita.hora_inicio, cita.hora_fin);

        div.innerHTML = `
             <div class="cabecera-compacta" data-id="${cita.id_cita}"> <div class="info-principal"> <div class="fila-principal"> <span class="numero-cita-mini">#${cita.id_cita}</span> <span class="badge-estado-mini completada">Completada</span> <span class="badge-pendiente">Sin informe</span> </div> <div class="datos-rapidos"> <span class="dato-rapido">${formatearFecha(cita.fecha)}</span> <span class="dato-rapido">⏰ ${cita.hora || 'N/A'}</span> <span class="dato-rapido">${tiempoAtencion}</span> <span class="dato-rapido">${truncarTexto(cita.nombre_servicio || 'Sin servicio', 25)}</span> </div> </div> <div class="acciones-rapidas"> <button class="btn-llenar-informe" data-id="${cita.id_cita}"> Llenar Informe
                    </button> <button class="btn-expandir" data-accion="expandir"> <span class="icono-expandir">▼</span> </button> </div> </div> <div class="detalles-expandibles" style="display: none;"> <div class="separador-expansion"></div> <div class="grid-detalles"> <div class="detalle-item"> <span class="detalle-label">Hora Inicio</span> <span class="detalle-valor">${cita.hora_inicio || 'N/A'}</span> </div> <div class="detalle-item"> <span class="detalle-label">Hora Fin</span> <span class="detalle-valor">${cita.hora_fin || 'N/A'}</span> </div> <div class="detalle-item"> <span class="detalle-label">Duración</span> <span class="detalle-valor">${tiempoAtencion}</span> </div> </div> ${cita.motivo ? `
                <div class="motivo-expandible"> <span class="detalle-label">Motivo de Consulta</span> <p class="detalle-valor">${cita.motivo}</p> </div> ` : ''}
            </div> `;

        agregarEventosBotones(div, cita, 'pendiente');
        return div;
    }

    // =====================================================
    // 🎯 GENERAR BOTONES RÁPIDOS
    // =====================================================
    function generarBotonesRapidos(cita) {
        let botones = '';
        const estadoStr = String(cita.id_estado_cita);

        if ((estadoStr === '1' || estadoStr === '2') && !cita.hora_inicio) {
            botones += `<button class="btn-accion-texto btn-iniciar-texto" data-accion="iniciar" data-id="${cita.id_cita}"> Iniciar Atención
            </button>`;
        }

        if (estadoStr === '6' && cita.hora_inicio && !cita.hora_fin) {
            botones += `<button class="btn-accion-texto btn-terminar-texto" data-accion="terminar" data-id="${cita.id_cita}"> Terminar Atención
            </button>`;
        }

        return botones;
    }

    // =====================================================
    // 🔗 AGREGAR EVENTOS A BOTONES
    // =====================================================
    function agregarEventosBotones(tarjeta, cita, tipo) {
        const btnExpandir = tarjeta.querySelector('[data-accion="expandir"]');
        const detallesExpandibles = tarjeta.querySelector('.detalles-expandibles');
        const iconoExpandir = tarjeta.querySelector('.icono-expandir');

        if (btnExpandir && detallesExpandibles) {
            btnExpandir.addEventListener('click', () => {
                const estaExpandido = detallesExpandibles.style.display !== 'none';
                
                if (estaExpandido) {
                    detallesExpandibles.style.display = 'none';
                    iconoExpandir.textContent = '▼';
                    tarjeta.classList.remove('expandida');
                } else {
                    detallesExpandibles.style.display = 'block';
                    iconoExpandir.textContent = '▲';
                    tarjeta.classList.add('expandida');
                }
            });
        }

        if (tipo === 'activa') {
            const btnIniciar = tarjeta.querySelector('[data-accion="iniciar"]');
            if (btnIniciar) {
                btnIniciar.addEventListener('click', () => iniciarAtencion(cita.id_cita));
            }

            const btnTerminar = tarjeta.querySelector('[data-accion="terminar"]');
            if (btnTerminar) {
                btnTerminar.addEventListener('click', () => terminarAtencion(cita.id_cita));
            }
        }

        if (tipo === 'pendiente') {
            const btnLlenarInforme = tarjeta.querySelector('.btn-llenar-informe');
            if (btnLlenarInforme) {
                btnLlenarInforme.addEventListener('click', () => abrirModalInforme(cita));
            }
        }
    }

    // =====================================================
    // ▶️ INICIAR ATENCIÓN
    // =====================================================
    function iniciarAtencion(idCita) {
        if (!confirm('¿Desea iniciar la atención de esta cita?')) return;

        fetch('../CONTROLADORES/controlador_citas_doctor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=iniciar_atencion&id_cita=${idCita}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje('Atención iniciada correctamente', 'exito');
                cargarCitas();
            } else {
                mostrarMensaje('' + data.mensaje, 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            mostrarMensaje('Error al iniciar atención', 'error');
        });
    }

    // =====================================================
    // ⏹️ TERMINAR ATENCIÓN
    // =====================================================
    function terminarAtencion(idCita) {
        if (!confirm('¿Desea terminar la atención de esta cita?')) return;

        fetch('../CONTROLADORES/controlador_citas_doctor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=terminar_atencion&id_cita=${idCita}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje('Atención registrada. Aparecerá en Pendientes de informe', 'exito');
                cargarCitas();
            } else {
                mostrarMensaje('' + data.mensaje, 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            mostrarMensaje('Error al terminar atención', 'error');
        });
    }

    // =====================================================
    // ABRIR MODAL DE INFORME
    // =====================================================
    function abrirModalInforme(cita) {
        window.abrirInforme(cita.id_cita, () => cargarCitas());
    }

    // =====================================================
    // CERRAR MODAL
    // =====================================================
    function cerrarModalAtencion() {
        modalAtencion.style.display = 'none';
        modalAtencion.classList.remove('mostrar');
        formAtencion.reset();
    }

    // =====================================================
    // 💾 GUARDAR ATENCIÓN
    // =====================================================
    function guardarAtencion(e) {
        e.preventDefault();

        const formData = new FormData(formAtencion);
        formData.append('accion', 'guardar_atencion');

        console.log('Guardando atención...');

        fetch('../CONTROLADORES/controlador_citas_doctor.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            console.log('Respuesta:', data);
            
            if (data.success) {
                const mensaje = data.accion === 'actualizar' 
                    ? 'Atención actualizada correctamente' 
                    : 'Informe registrado correctamente';
                
                mostrarMensaje(mensaje, 'exito');
                cerrarModalAtencion();
                cargarCitas();
            } else {
                mostrarMensaje('' + data.mensaje, 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            mostrarMensaje('Error al guardar atención', 'error');
        });
    }

    // =====================================================
    // 🧮 CALCULAR TOTALES
    // =====================================================
    function calcularTotales() {
        const precioUnitario = parseFloat(document.getElementById('precioUnitario').value) || 0;
        const aCuenta = parseFloat(document.getElementById('aCuenta').value) || 0;

        const total = precioUnitario;
        const resta = Math.max(0, total - aCuenta);

        document.getElementById('totalAtencion').value = total.toFixed(2);
        document.getElementById('resta').value = resta.toFixed(2);
    }

    // =====================================================
    // 🛠️ FUNCIONES AUXILIARES
    // =====================================================
    function calcularTiempoAtencion(horaInicio, horaFin) {
        if (!horaInicio || !horaFin) return 'N/A';
        
        const inicio = new Date('2000-01-01 ' + horaInicio);
        const fin = new Date('2000-01-01 ' + horaFin);
        const diferencia = (fin - inicio) / 1000 / 60;
        
        return Math.round(diferencia) + ' min';
    }

    function calcularTiempoAtencionNumerico(horaInicio, horaFin) {
        if (!horaInicio || !horaFin) return 0;
        
        const inicio = new Date('2000-01-01 ' + horaInicio);
        const fin = new Date('2000-01-01 ' + horaFin);
        const diferencia = (fin - inicio) / 1000 / 60;
        
        return Math.round(diferencia);
    }

    function obtenerBadgeEstado(estado, idEstado) {
        let clase = '';
        
        switch(idEstado) {
            case '1': clase = 'programada'; break;
            case '2': clase = 'confirmada'; break;
            case '6': clase = 'en-atencion'; break;
            default: clase = 'programada';
        }

        return `<span class="badge-estado-mini ${clase}">${estado}</span>`;
    }

    function formatearFecha(fecha) {
        if (!fecha) return 'N/A';
        const [año, mes, dia] = fecha.split('-');
        return `${dia}/${mes}/${año}`;
    }

    function truncarTexto(texto, maxLength) {
        if (!texto) return '';
        if (texto.length <= maxLength) return texto;
        return texto.substring(0, maxLength) + '...';
    }

    function mostrarMensaje(mensaje, tipo) {
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        aviso.innerHTML = `<span>${mensaje}</span>`;
        aviso.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${tipo === 'exito' ? '#2e6da4' : '#8b3a3a'};
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideIn 0.4s ease;
        `;

        document.body.appendChild(aviso);
        setTimeout(() => aviso.remove(), 4000);
    }

})();