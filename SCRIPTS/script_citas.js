// =====================================================
// 🚀 MÓDULO DE GESTIÓN DE CITAS ODONTOLÓGICAS - PARTE 1/6
// Inicialización, Variables Globales y Elementos del DOM
// =====================================================

function iniciarModuloCitas() {
    console.log("🔵 === INICIANDO MÓDULO DE CITAS ===");
    
    // =====================================================
    // 📌 ELEMENTOS DEL DOM
    // =====================================================
    const tablaBody = document.querySelector('#tablaCitas tbody');
    const seccionListado = document.getElementById('seccionListadoCitas');
    const seccionNueva = document.getElementById('seccionNuevaCita');
    const seccionDetalle = document.getElementById('seccionDetalleCita');
    const detalleDiv = document.getElementById('detalleCita');
    const btnNueva = document.getElementById('btnNuevaCita');
    const btnVolverListado = document.getElementById('btnVolverListado');
    const btnVolverListado2 = document.getElementById('btnVolverListado2');
    const form = document.getElementById('formCita');

    // Inputs principales
    const inputPaciente = document.getElementById('inputPaciente');
    const inputDoctor = document.getElementById('inputDoctor');
    const idPacienteSeleccionado = document.getElementById('idPacienteSeleccionado');
    const idDoctorSeleccionado = document.getElementById('idDoctorSeleccionado');
    const sugerenciasPaciente = document.getElementById('sugerenciasPaciente');
    const sugerenciasDoctor = document.getElementById('sugerenciasDoctor');

    // Selects
    const selectEstadoCita = document.getElementById('selectEstadoCita');
    // selectTipoServicio es opcional — no existe en el HTML como select fijo,
    // se accede siempre con getElementById para evitar crashes
    const selectSede = document.getElementById('selectSede');

    // Inputs de fecha y hora
    const fechaCita = document.getElementById('fechaCita');
    const horaCita = document.getElementById('horaCita');

    // Campo de motivo (textarea)
    const textareaMotivo = document.getElementById('textareaMotivo');

    // Radio buttons
    const radiosTieneAlergia = document.querySelectorAll('input[name="tiene_alergia"]');

    // Elementos de alergias existentes
    const mensajeAlergiasExistentes = document.getElementById('mensajeAlergiasExistentes');
    const listaAlergiasExistentes = document.getElementById('listaAlergiasExistentes');

    // Búsqueda y filtros
    const inputBusqueda = document.getElementById('inputBusqueda');
    const filtroFecha = document.getElementById('filtroFecha');

    // Estadísticas (ahora son botones de filtro)
    const statTodos = document.getElementById('statTodos');
    const statProgramadas = document.getElementById('statProgramadas');
    const statConfirmadas = document.getElementById('statConfirmadas');
    const statCompletadas = document.getElementById('statCompletadas');
    const statCanceladas = document.getElementById('statCanceladas');
    const statNoAsistio = document.getElementById('statNoAsistio');

    // Campo oculto para ID en edición
    const idCitaEditar = document.getElementById('idCitaEditar');

    // =====================================================
    // 📊 VARIABLES LOCALES (NO GLOBALES)
    // =====================================================
    let modoEdicion = false;
    let idEdicion = null;
    let citasCompleto = [];
    let timeoutBusquedaPaciente = null;
    let timeoutBusquedaDoctor = null;
    let alergiasActualesPaciente = [];
    let estadoFiltroActual = '';

    // ✅ VERIFICAR SI LOS ELEMENTOS EXISTEN
    if (!tablaBody || !form || !seccionListado) {
        console.error("❌ Elementos del DOM no encontrados. El módulo de citas no puede inicializarse.");
        return;
    }

    // =====================================================
    // 🎬 INICIALIZACIÓN
    // =====================================================
    cargarSelectores();
    listarCitas();
    configurarEventos();
    configurarEventosEstadisticas(); // ← ✅ AGREGAR ESTA LÍNEA
    establecerFechaMinima();

    console.log("✅ Módulo de citas inicializado correctamente");

    // =====================================================
    // 🔹 Establecer fecha mínima (hoy)
    // =====================================================
    function establecerFechaMinima() {
        const hoy = new Date();
        const año = hoy.getFullYear();
        const mes = String(hoy.getMonth() + 1).padStart(2, '0');
        const dia = String(hoy.getDate()).padStart(2, '0');
        const fechaMinima = `${año}-${mes}-${dia}`;
        
        if (fechaCita) {
            fechaCita.setAttribute('min', fechaMinima);
        }
        
        console.log("📅 Fecha mínima establecida:", fechaMinima);
    }

    // =====================================================
    // 🔹 Cargar selectores dinámicamente
    // =====================================================
    function cargarSelectores() {
        console.log("🔵 === CARGANDO SELECTORES ===");
        
        // Cargar Estados de Cita
        // Tipos de atención
        fetch('../CONTROLADORES/controlador_configuracion.php?accion=listar_tipos_atencion')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('selectTipoAtencion');
                if (!sel) return;
                sel.innerHTML = '<option value="">Selecciona el tipo</option>' +
                    data.filter(t => parseInt(t.activo) === 1).map(t =>
                        `<option value="${t.id_tipo_atencion}" data-duracion="${t.duracion_minutos}">${t.nombre} (${t.duracion_minutos} min)</option>`
                    ).join('');
                sel.addEventListener('change', () => {
                    const opt = sel.selectedOptions[0];
                    if (opt && opt.dataset.duracion) {
                        const selDur = document.getElementById('selectDuracion');
                        if (selDur) selDur.value = opt.dataset.duracion;
                    }
                    cargarSlots();
                });
            });

        fetch('../CONTROLADORES/controlador_citas.php?accion=listar_estados_cita')
            .then(res => res.json())
            .then(data => {
                selectEstadoCita.innerHTML = '<option value="">Seleccione...</option>';
                data.forEach(estado => {
                    const opt = document.createElement('option');
                    opt.value = estado.id_estado_cita;
                    opt.textContent = estado.estado;
                    selectEstadoCita.appendChild(opt);
                });
            })
            .catch(() => {
                selectEstadoCita.innerHTML = '<option value="">Error al cargar</option>';
            });

        // Cargar Tipos de Servicio (solo si existe el select en el DOM)
        const selTipoServicioEl = document.getElementById('selectTipoServicio');
        if (selTipoServicioEl) {
            fetch('../CONTROLADORES/controlador_citas.php?accion=listar_tipos_servicio')
                .then(res => res.json())
                .then(data => {
                    console.log("✅ Tipos de servicio cargados:", data.length);
                    selTipoServicioEl.innerHTML = '<option value="">Seleccione...</option>';
                    data.forEach(servicio => {
                        const opt = document.createElement('option');
                        opt.value = servicio.id_tipo_servicio;
                        opt.textContent = servicio.nombre_servicio;
                        selTipoServicioEl.appendChild(opt);
                    });
                })
                .catch(() => {
                    console.error("❌ Error al cargar tipos de servicio");
                    selTipoServicioEl.innerHTML = '<option value="">Error al cargar</option>';
                });
        }

        // Cargar Sedes — guardamos la promesa para que llenarFormularioEdicion pueda esperarla
        window._sedesListasResolve = null;
        window._sedesListas = new Promise(resolve => { window._sedesListasResolve = resolve; });

        fetch('../CONTROLADORES/controlador_citas.php?accion=listar_sedes')
            .then(res => res.json())
            .then(data => {
                console.log("✅ Sedes cargadas:", data.length);
                selectSede.innerHTML = '<option value="">Seleccione...</option>';
                data.forEach(sede => {
                    const opt = document.createElement('option');
                    opt.value = sede.id_sede_atencion;
                    opt.textContent = sede.nombre_sede ? `${sede.nombre_sede} — ${sede.direccion_sede}` : sede.direccion_sede;
                    selectSede.appendChild(opt);
                });
                window._sedesListasResolve(); // ← señal: sedes ya cargadas
            })
            .catch(() => {
                console.error("❌ Error al cargar sedes");
                selectSede.innerHTML = '<option value="">Error al cargar</option>';
                window._sedesListasResolve(); // resolvemos igual para no bloquear
            });

        console.log("ℹ️ Catálogo de medicamentos se cargará dinámicamente como chips");
    }

   // =====================================================
    // 🚀 PARTE 2/6: Configuración de Eventos y Autocompletar
    // =====================================================

    // =====================================================
    // 🔹 Configurar eventos
    // =====================================================
    function configurarEventos() {
        // Slots: recargar al cambiar sede, fecha o duración
        document.getElementById('selectSede')?.addEventListener('change', cargarSlots);
        document.getElementById('selectDuracion')?.addEventListener('change', cargarSlots);
        document.getElementById('fechaCita')?.addEventListener('change', cargarSlots);

        console.log("🔵 === CONFIGURANDO EVENTOS ===");
        
        // Botones principales
        btnNueva.addEventListener('click', mostrarFormularioNuevo);
        btnVolverListado.addEventListener('click', () => mostrarListado(false));
        btnVolverListado2.addEventListener('click', () => mostrarListado(false));
        
        // Formulario
        form.addEventListener('submit', guardarCita);
        
        // Autocompletar
        inputPaciente.addEventListener('input', buscarPacientes);
        inputDoctor.addEventListener('input', buscarDoctores);
        
        // Detectar cuando se limpia el campo de paciente
        inputPaciente.addEventListener('input', (e) => {
            if (e.target.value.trim() === '') {
                console.log('🔵 Campo paciente limpiado - Ocultando alergias');
                limpiarSeccionAlergias();
                idPacienteSeleccionado.value = '';
            }
        });
        
        // Proteger contra reseteo accidental en selectores
        [selectEstadoCita, selectSede].forEach(select => {
            if (!select) return;
            select.addEventListener('change', (e) => {
                console.log(`🔵 Select ${e.target.id} cambiado a:`, e.target.value);
            });
        });
        
        // Cerrar sugerencias al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!sugerenciasPaciente.contains(e.target) && e.target !== inputPaciente) {
                sugerenciasPaciente.style.display = 'none';
            }
            if (!sugerenciasDoctor.contains(e.target) && e.target !== inputDoctor) {
                sugerenciasDoctor.style.display = 'none';
            }
        });
        
        // Radio buttons de alergia
        radiosTieneAlergia.forEach(radio => {
            radio.addEventListener('change', toggleCamposAlergia);
        });
        
        // Búsqueda y filtros
        inputBusqueda.addEventListener('input', filtrarCitas);
        filtroFecha.addEventListener('change', filtrarCitas);
        
        // Eventos de clic en cajas de estadísticas (filtro por estado)
        document.querySelectorAll('.stat-card').forEach(caja => {
            caja.addEventListener('click', function() {
                const estadoSeleccionado = this.dataset.estado || '';
                document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('filtro-activo'));
                this.classList.add('filtro-activo');
                estadoFiltroActual = estadoSeleccionado;
                fetch('../CONTROLADORES/controlador_citas.php?accion=listar')
                    .then(res => res.json())
                    .then(data => {
                        citasCompleto = [...data];
                        filtrarCitas();
                    })
                    .catch(() => filtrarCitas());
            });
        });
        
        console.log("✅ Eventos configurados correctamente");
    }

// =====================================================
// 📊 CONFIGURAR EVENTOS DE ESTADÍSTICAS - ✅ NUEVO
// =====================================================
function configurarEventosEstadisticas() {
    console.log("🔵 === CONFIGURANDO EVENTOS DE ESTADÍSTICAS ===");
    
    // Usar delegación de eventos en el contenedor padre
    const contenedorEstadisticas = document.querySelector('.stats-container') || 
                                   document.querySelector('.estadisticas-citas') ||
                                   document.body;
    
    // Remover listener anterior si existe
    if (contenedorEstadisticas._clickHandlerCitas) {
        contenedorEstadisticas.removeEventListener('click', contenedorEstadisticas._clickHandlerCitas);
    }
    
    // Crear nuevo handler
    const clickHandler = function(e) {
        const caja = e.target.closest('.stat-card');
        if (!caja) return;
        
        const estadoSeleccionado = caja.dataset.estado || '';
        
        console.log('🔵 ========================================');
        console.log('🔵 CLICK EN ESTADÍSTICA:', estadoSeleccionado);
        console.log('🔵 ========================================');
        
        // Actualizar UI
        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('filtro-activo'));
        caja.classList.add('filtro-activo');
        
        // Establecer filtro
        estadoFiltroActual = estadoSeleccionado;
        
        // ✅ CRÍTICO: Recargar datos desde BD
        console.log('🔄 Recargando desde BD antes de filtrar...');
        fetch('../CONTROLADORES/controlador_citas.php?accion=listar')
            .then(res => res.json())
            .then(data => {
                console.log('✅ Datos recargados desde BD:', data.length);
                citasCompleto = [...data];
                console.log('📦 citasCompleto actualizado:', citasCompleto.length);
                
                // Filtrar
                filtrarCitas();
            })
            .catch(err => {
                console.error('❌ Error al recargar:', err);
                // Si falla, filtrar con datos actuales
                filtrarCitas();
            });
    };
    
    // Guardar referencia y agregar listener
    contenedorEstadisticas._clickHandlerCitas = clickHandler;
    contenedorEstadisticas.addEventListener('click', clickHandler);
    
    console.log("✅ Eventos de estadísticas configurados");
}
    // =====================================================
    // 🔍 Buscar pacientes (autocompletar)
    // =====================================================
    function buscarPacientes(e) {
        clearTimeout(timeoutBusquedaPaciente);
        const termino = e.target.value.trim();
        
        console.log("🔍 Buscando pacientes:", termino);
        
        if (termino.length < 2) {
            sugerenciasPaciente.style.display = 'none';
            idPacienteSeleccionado.value = '';
            limpiarSeccionAlergias();
            return;
        }
        
        timeoutBusquedaPaciente = setTimeout(() => {
            fetch(`../CONTROLADORES/controlador_citas.php?accion=autocompletar_paciente&termino=${encodeURIComponent(termino)}`)
                .then(res => res.json())
                .then(pacientes => {
                    console.log("✅ Pacientes encontrados:", pacientes.length);
                    mostrarSugerenciasPaciente(pacientes);
                    
                    // Si solo hay 1 resultado, auto-seleccionar
                    if (pacientes.length === 1) {
                        console.log("🔵 Solo un paciente encontrado, auto-seleccionando...");
                        setTimeout(() => {
                            seleccionarPaciente(pacientes[0]);
                        }, 500);
                    }
                })
                .catch(err => {
                    console.error('❌ Error al buscar pacientes:', err);
                });
        }, 300);
    }

    //  Mostrar sugerencias de pacientes
    function mostrarSugerenciasPaciente(pacientes) {
        if (pacientes.length === 0) {
            sugerenciasPaciente.innerHTML = '<div class="sugerencia-item sin-resultados">No se encontraron pacientes</div>';
            sugerenciasPaciente.style.display = 'block';
            return;
        }
        
        sugerenciasPaciente.innerHTML = '';
        pacientes.forEach(paciente => {
            const div = document.createElement('div');
            div.className = 'sugerencia-item';
            div.innerHTML = `
                <div class="sugerencia-principal">${paciente.nombre} ${paciente.apellido}</div>
                <div class="sugerencia-secundaria">DNI: ${paciente.dni}</div>
            `;
            div.addEventListener('click', () => {
                seleccionarPaciente(paciente);
            });
            sugerenciasPaciente.appendChild(div);
        });
        sugerenciasPaciente.style.display = 'block';
    }

    // =====================================================
    // 🔹 Seleccionar paciente
    // =====================================================
    function seleccionarPaciente(paciente) {
        console.log("🔵 === PACIENTE SELECCIONADO ===");
        console.log("📋 ID Paciente:", paciente.id_paciente);
        console.log("👤 Nombre:", paciente.nombre, paciente.apellido);
        
        inputPaciente.value = `${paciente.nombre} ${paciente.apellido} - DNI: ${paciente.dni}`;
        idPacienteSeleccionado.value = paciente.id_paciente;
        sugerenciasPaciente.style.display = 'none';
        
        inputPaciente.classList.add('campo-valido');
        inputPaciente.classList.remove('campo-invalido');
        
        console.log("🔵 Llamando a verificarYMostrarAlergias()...");
        
        // ✅ Verificar alergias al seleccionar paciente
        verificarYMostrarAlergias(paciente.id_paciente);
        cargarPlanesPaciente(paciente.id_paciente);
    }

    // =====================================================
    // 🔍 Buscar doctores (autocompletar)
    // =====================================================
    function buscarDoctores(e) {
        clearTimeout(timeoutBusquedaDoctor);
        const termino = e.target.value.trim();
        
        console.log("🔍 Buscando doctores:", termino);
        
        if (termino.length < 2) {
            sugerenciasDoctor.style.display = 'none';
            idDoctorSeleccionado.value = '';
            return;
        }
        
        timeoutBusquedaDoctor = setTimeout(() => {
            fetch(`../CONTROLADORES/controlador_citas.php?accion=autocompletar_doctor&termino=${encodeURIComponent(termino)}`)
                .then(res => res.json())
                .then(doctores => {
                    console.log("✅ Doctores encontrados:", doctores.length);
                    mostrarSugerenciasDoctor(doctores);
                })
                .catch(err => {
                    console.error('❌ Error al buscar doctores:', err);
                });
        }, 300);
    }

    // =====================================================
    // 🔹 Mostrar sugerencias de doctores
    // =====================================================
    function mostrarSugerenciasDoctor(doctores) {
        if (doctores.length === 0) {
            sugerenciasDoctor.innerHTML = '<div class="sugerencia-item sin-resultados">No se encontraron dentistas</div>';
            sugerenciasDoctor.style.display = 'block';
            return;
        }
        
        sugerenciasDoctor.innerHTML = '';
        doctores.forEach(doctor => {
            const div = document.createElement('div');
            div.className = 'sugerencia-item';
            div.innerHTML = `
                <div class="sugerencia-principal">Dr(a). ${doctor.nombre} ${doctor.apellidos}</div>
                <div class="sugerencia-secundaria">DNI: ${doctor.dni}</div>
            `;
            div.addEventListener('click', () => {
                seleccionarDoctor(doctor);
            });
            sugerenciasDoctor.appendChild(div);
        });
        sugerenciasDoctor.style.display = 'block';
    }

    // =====================================================
    // 🔹 Seleccionar doctor
    // =====================================================
    function seleccionarDoctor(doctor) {
        console.log("🔵 === DOCTOR SELECCIONADO ===");
        console.log("📋 ID Doctor:", doctor.id_usuario);
        console.log("👨‍⚕️ Nombre:", doctor.nombre, doctor.apellidos);
        
        inputDoctor.value = `Dr(a). ${doctor.nombre} ${doctor.apellidos}`;
        idDoctorSeleccionado.value = doctor.id_usuario;
        sugerenciasDoctor.style.display = 'none';
        
        inputDoctor.classList.add('campo-valido');
        inputDoctor.classList.remove('campo-invalido');
        cargarPlanesPaciente(idPacienteSeleccionado.value);
        cargarSlots();
    }

    // =====================================================
    // 🚀 PARTE 3/6: Gestión de Alergias
    // =====================================================

    // =====================================================
    // 💊 VERIFICAR Y MOSTRAR ALERGIAS DEL PACIENTE
    // =====================================================
    function verificarYMostrarAlergias(idPaciente) {
        console.log("🔵 ========================================");
        console.log("🔵 === VERIFICAR Y MOSTRAR ALERGIAS ===");
        console.log("🔵 ========================================");
        console.log("📋 ID Paciente recibido:", idPaciente);
        console.log("🔵 URL a consultar:", `../CONTROLADORES/controlador_citas.php?accion=obtener_alergias_paciente&id_paciente=${idPaciente}`);
        
        fetch(`../CONTROLADORES/controlador_citas.php?accion=obtener_alergias_paciente&id_paciente=${idPaciente}`)
            .then(res => {
                console.log("📡 Respuesta recibida del servidor");
                console.log("📡 Status:", res.status, res.statusText);
                return res.json();
            })
            .then(alergias => {
                console.log("✅ Alergias obtenidas:", alergias.length);
                console.log("📦 Datos completos de alergias:", JSON.stringify(alergias, null, 2));
                
                alergiasActualesPaciente = alergias;
                
                const contenedorPreguntaInicial = document.getElementById('contenedorPreguntaInicial');
                const textoPreguntaInicial = document.getElementById('textoPreguntaInicial');
                const textoOpcionSi = document.getElementById('textoOpcionSi');
                
                console.log("🔵 [DEBUG] Elementos del DOM encontrados:");
                console.log("  - contenedorPreguntaInicial:", contenedorPreguntaInicial ? "✅ EXISTE" : "❌ NO EXISTE");
                console.log("  - textoPreguntaInicial:", textoPreguntaInicial ? "✅ EXISTE" : "❌ NO EXISTE");
                console.log("  - textoOpcionSi:", textoOpcionSi ? "✅ EXISTE" : "❌ NO EXISTE");
                
                if (alergias.length > 0) {
                    console.log("💊 El paciente YA TIENE alergias registradas");
                    console.log("💊 Llamando a mostrarMensajeAlergiasExistentes()...");
                    
                    mostrarMensajeAlergiasExistentes(alergias);
                    
                    textoPreguntaInicial.textContent = "¿Desea registrar más medicamentos alérgicos?";
                    textoOpcionSi.textContent = "Sí, registrar más";
                    
                    console.log("✅ Textos actualizados para paciente CON alergias");
                    
                } else {
                    console.log("ℹ️ El paciente NO tiene alergias registradas");
                    
                    mensajeAlergiasExistentes.style.display = 'none';
                    
                    textoPreguntaInicial.textContent = "¿El paciente tiene alergias a medicamentos?";
                    textoOpcionSi.textContent = "Sí";
                    
                    console.log("✅ Textos actualizados para paciente SIN alergias");
                }
                
                console.log("🔵 [DEBUG] Mostrando contenedorPreguntaInicial...");
                contenedorPreguntaInicial.style.display = 'block';
                console.log("✅ contenedorPreguntaInicial.style.display =", contenedorPreguntaInicial.style.display);
                
                console.log("🔵 [DEBUG] Reseteando radio button a 'no'...");
                const radioNo = document.querySelector('input[name="tiene_alergia"][value="no"]');
                if (radioNo) {
                    radioNo.checked = true;
                    console.log("✅ Radio 'no' marcado");
                } else {
                    console.error("❌ Radio 'no' no encontrado!");
                }
                
                console.log("🔵 [DEBUG] Ocultando contenedor de chips...");
                const contenedorChips = document.getElementById('contenedorChipsMedicamentos');
                if (contenedorChips) {
                    contenedorChips.style.display = 'none';
                    console.log("✅ contenedorChipsMedicamentos oculto");
                } else {
                    console.error("❌ contenedorChipsMedicamentos no encontrado!");
                }
                
                console.log("🔵 ========================================");
                console.log("✅ verificarYMostrarAlergias() COMPLETADO");
                console.log("🔵 ========================================");
                
            })
            .catch(err => {
                console.error('❌ ========================================');
                console.error('❌ ERROR al cargar alergias del paciente');
                console.error('❌ ========================================');
                console.error('❌ Error completo:', err);
                console.error('❌ Stack:', err.stack);
                limpiarSeccionAlergias();
            });
    }

    // =====================================================
    // 💊 MOSTRAR MENSAJE DE ALERGIAS EXISTENTES
    // =====================================================
    function mostrarMensajeAlergiasExistentes(alergias) {
        console.log("🔵 === MOSTRAR MENSAJE ALERGIAS EXISTENTES ===");
        console.log("💊 Total de alergias a mostrar:", alergias.length);
        
        if (!mensajeAlergiasExistentes || !listaAlergiasExistentes) {
            console.error("❌ Elementos del DOM no encontrados");
            console.error("❌ mensajeAlergiasExistentes:", mensajeAlergiasExistentes);
            console.error("❌ listaAlergiasExistentes:", listaAlergiasExistentes);
            return;
        }
        
        console.log("🔵 Limpiando lista existente...");
        listaAlergiasExistentes.innerHTML = '';
        
        console.log("🔵 Agregando chips de alergias...");
        alergias.forEach((alergia, index) => {
            console.log(`  💊 Alergia ${index + 1}:`, alergia.medicamento);
            const chip = document.createElement('span');
            chip.className = 'chip-alergia-existente';
            chip.textContent = alergia.medicamento;
            listaAlergiasExistentes.appendChild(chip);
        });
        
        console.log("🔵 Mostrando mensaje de alergias...");
        mensajeAlergiasExistentes.style.display = 'flex';
        console.log("✅ mensajeAlergiasExistentes.style.display =", mensajeAlergiasExistentes.style.display);
        
        console.log("✅ Mensaje de alergias existentes mostrado correctamente");
    }

    // =====================================================
    // 💊 LIMPIAR SECCIÓN DE ALERGIAS
    // =====================================================
    function limpiarSeccionAlergias() {
        console.log("🔵 === LIMPIAR SECCIÓN DE ALERGIAS ===");
        
        alergiasActualesPaciente = [];
        console.log("✅ alergiasActualesPaciente limpiado");
        
        if (mensajeAlergiasExistentes) {
            mensajeAlergiasExistentes.style.display = 'none';
            console.log("✅ mensajeAlergiasExistentes oculto");
        }
        
        if (listaAlergiasExistentes) {
            listaAlergiasExistentes.innerHTML = '';
            console.log("✅ listaAlergiasExistentes limpiado");
        }
        
        const contenedorPreguntaInicial = document.getElementById('contenedorPreguntaInicial');
        if (contenedorPreguntaInicial) {
            contenedorPreguntaInicial.style.display = 'none';
            console.log("✅ contenedorPreguntaInicial oculto");
        }
        
        const contenedorChips = document.getElementById('contenedorChipsMedicamentos');
        if (contenedorChips) {
            contenedorChips.style.display = 'none';
            console.log("✅ contenedorChipsMedicamentos oculto");
        }
        
        const radioNo = document.querySelector('input[name="tiene_alergia"][value="no"]');
        if (radioNo) {
            radioNo.checked = true;
            console.log("✅ Radio 'no' marcado");
        }
        
        console.log("✅ Sección de alergias limpiada completamente");
    }

    // =====================================================
    // 🔹 Toggle campos de alergia
    // =====================================================
    function toggleCamposAlergia() {
    const tieneAlergia = document.querySelector('input[name="tiene_alergia"]:checked').value;
    
    console.log("🔵 === TOGGLE CAMPOS ALERGIA ===");
    console.log("💊 Opción seleccionada:", tieneAlergia);
    
    const contenedorChips = document.getElementById('contenedorChipsMedicamentos');
    const chipsMedicamentos = document.getElementById('chipsMedicamentos');
    
    if (tieneAlergia === 'si') {
        console.log("✅ Usuario seleccionó SÍ - Mostrando chips...");
        contenedorChips.style.display = 'block';
        
        // ✅ CORRECCIÓN: Verificar si realmente hay chips con contenido
        const tieneChipsReales = chipsMedicamentos.querySelectorAll('.chip-medicamento').length > 0;
        
        console.log("🔵 Verificando estado de chips:");
        console.log("   - hasChildNodes():", chipsMedicamentos.hasChildNodes());
        console.log("   - Cantidad de chips reales:", chipsMedicamentos.querySelectorAll('.chip-medicamento').length);
        console.log("   - tieneChipsReales:", tieneChipsReales);
        
        if (!tieneChipsReales) {
            console.log("🔵 No hay chips reales, cargando medicamentos...");
            cargarChipsMedicamentos();
        } else {
            console.log("✅ Chips ya cargados correctamente (" + chipsMedicamentos.querySelectorAll('.chip-medicamento').length + " medicamentos)");
        }
    } else {
        console.log("ℹ️ Usuario seleccionó NO - Ocultando chips...");
        contenedorChips.style.display = 'none';
        limpiarSeleccionChips();
    }
}

    // =====================================================
    // 💊 CARGAR CHIPS DE MEDICAMENTOS
    // =====================================================
    function cargarChipsMedicamentos() {
        console.log("🔵 === CARGAR CHIPS DE MEDICAMENTOS ===");
        
        const chipsMedicamentos = document.getElementById('chipsMedicamentos');
        chipsMedicamentos.innerHTML = '<div style="text-align:center; padding:20px; color:#999;">⏳ Cargando medicamentos...</div>';
        
        console.log("🔵 Consultando catálogo de medicamentos...");
        
        fetch('../CONTROLADORES/controlador_citas.php?accion=listar_alergias_medicamentos')
            .then(res => {
                console.log("📡 Respuesta recibida del servidor");
                return res.json();
            })
            .then(medicamentos => {
                console.log("✅ Medicamentos cargados:", medicamentos.length);
                console.log("📦 Datos:", JSON.stringify(medicamentos.slice(0, 3), null, 2), "...");
                
                chipsMedicamentos.innerHTML = '';
                
                medicamentos.forEach((medicamento, index) => {
                    const chip = document.createElement('div');
                    chip.className = 'chip-medicamento';
                    chip.dataset.id = medicamento.id_alergia_medicamentos;
                    chip.textContent = medicamento.medicamento;
                    
                    chip.addEventListener('click', () => {
                        toggleChipMedicamento(chip);
                    });
                    
                    chipsMedicamentos.appendChild(chip);
                    
                    if (index < 3) {
                        console.log(`  💊 Chip ${index + 1}: ID=${medicamento.id_alergia_medicamentos}, Nombre=${medicamento.medicamento}`);
                    }
                });
                
                console.log("✅ Total de chips generados:", medicamentos.length);
            })
            .catch(err => {
                console.error('❌ Error al cargar medicamentos:', err);
                chipsMedicamentos.innerHTML = '<div style="text-align:center; padding:20px; color:#f44336;">❌ Error al cargar medicamentos</div>';
            });
    }

    // =====================================================
    // 💊 TOGGLE CHIP DE MEDICAMENTO
    // =====================================================
    function toggleChipMedicamento(chip) {
        const idMedicamento = chip.dataset.id;
        
        console.log("🔵 Toggle chip - ID:", idMedicamento, "Nombre:", chip.textContent);
        
        if (chip.classList.contains('seleccionado')) {
            chip.classList.remove('seleccionado');
            console.log("➖ Medicamento deseleccionado");
        } else {
            chip.classList.add('seleccionado');
            console.log("➕ Medicamento seleccionado");
        }
        
        actualizarCampoOcultoAlergias();
    }

    // =====================================================
    // 💊 ACTUALIZAR CAMPO OCULTO CON IDS SELECCIONADOS
    // =====================================================
    function actualizarCampoOcultoAlergias() {
        const chipsSeleccionados = document.querySelectorAll('.chip-medicamento.seleccionado');
        const idsSeleccionados = Array.from(chipsSeleccionados).map(chip => chip.dataset.id);
        
        console.log("📦 IDs seleccionados actualizados:", idsSeleccionados);
        
        const campoOculto = document.getElementById('alergiasMedicamentosHidden');
        campoOculto.value = idsSeleccionados.join(',');
        
        console.log("✅ Campo oculto actualizado:", campoOculto.value);
    }

    // =====================================================
    // 💊 LIMPIAR SELECCIÓN DE CHIPS
    // =====================================================
    function limpiarSeleccionChips() {
        console.log("🔵 Limpiando selección de chips");
        
        const chipsSeleccionados = document.querySelectorAll('.chip-medicamento.seleccionado');
        console.log("📊 Chips a limpiar:", chipsSeleccionados.length);
        
        chipsSeleccionados.forEach(chip => {
            chip.classList.remove('seleccionado');
        });
        
        const campoOculto = document.getElementById('alergiasMedicamentosHidden');
        if (campoOculto) {
            campoOculto.value = '';
            console.log("✅ Campo oculto limpiado");
        }
    }

    // =====================================================
    // 💊 OBTENER ALERGIAS SELECCIONADAS
    // =====================================================
    function obtenerAlergiasSeleccionadas() {
        console.log("🔵 === OBTENER ALERGIAS SELECCIONADAS ===");
        
        const tieneAlergia = document.querySelector('input[name="tiene_alergia"]:checked').value;
        
        console.log("💊 ¿Desea agregar nuevas alergias?:", tieneAlergia);
        
        if (tieneAlergia === 'no') {
            console.log("ℹ️ No se agregarán nuevas alergias");
            return [];
        }
        
        const campoOculto = document.getElementById('alergiasMedicamentosHidden');
        const idsString = campoOculto.value.trim();
        
        console.log("📦 Valor del campo oculto:", idsString);
        
        if (!idsString) {
            console.log("ℹ️ No hay medicamentos seleccionados");
            return [];
        }
        
        const idsSeleccionados = idsString.split(',').filter(id => id.trim() !== '');
        
        console.log("💊 Total de IDs seleccionados:", idsSeleccionados.length);
        console.log("📦 IDs:", idsSeleccionados);
        
        const idsExistentes = alergiasActualesPaciente.map(a => a.id_alergia_medicamentos.toString());
        
        console.log("📦 IDs ya existentes:", idsExistentes);
        
        const idsNuevos = idsSeleccionados.filter(id => !idsExistentes.includes(id));
        
        console.log("💊 Alergias nuevas (filtradas):", idsNuevos.length);
        console.log("📦 IDs finales:", idsNuevos);
        
        if (idsNuevos.length === 0 && idsSeleccionados.length > 0) {
            console.log("⚠️ Todas ya estaban registradas");
        }
        
        return idsNuevos;
    }

   // =====================================================
    // 🚀 PARTE 4/6: Guardar, Editar y Validar Citas
    // =====================================================

    // =====================================================
    // 💾 GUARDAR CITA - ✅ TOTALMENTE CORREGIDO
    // =====================================================
    function guardarCita(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('🔵 ========================================');
        console.log('🔵 === INICIO DE GUARDAR CITA ===');
        console.log('🔵 ========================================');
        
        // ✅ SOLUCIÓN: Leer desde el formulario, NO de variables globales
        const esEdicion = form.dataset.modo === 'edicion';
        const idParaEditar = form.dataset.idCita || null;
        
        console.log('🔍 Datos desde formulario (data attributes):');
        console.log('   - form.dataset.modo:', form.dataset.modo);
        console.log('   - form.dataset.idCita:', form.dataset.idCita);
        console.log('   - esEdicion:', esEdicion);
        console.log('   - idParaEditar:', idParaEditar);
        
        console.log('🔍 Variables globales (referencia):');
        console.log('   - modoEdicion:', modoEdicion);
        console.log('   - idEdicion:', idEdicion);
        
        if (!validarFormularioCita()) {
            console.log('❌ Validación fallida');
            mostrarMensajeSistema('❌ Complete todos los campos obligatorios', 'error');
            return;
        }
        
        const btnGuardar = document.getElementById('btnGuardarCita');
        const textoOriginal = btnGuardar.textContent;
        
        if (btnGuardar.disabled) {
            console.log('⚠️ Botón ya deshabilitado - Evitando doble submit');
            return;
        }
        
        btnGuardar.disabled = true;
        btnGuardar.textContent = '⏳ Guardando...';
        
        const formData = new FormData();
        
        console.log('🔵 ========================================');
        console.log('🔵 DETERMINANDO ACCIÓN');
        console.log('🔵 ========================================');
        
        if (esEdicion && idParaEditar) {
            console.log('🔄 MODO: EDICIÓN');
            console.log('   - ID a editar:', idParaEditar);
            
            formData.append('accion', 'editar');
            formData.append('id_cita', idParaEditar);
        } else {
            console.log('🆕 MODO: REGISTRAR NUEVA');
            
            formData.append('accion', 'registrar');
        }
        
        // Agregar campos
        formData.append('id_paciente', idPacienteSeleccionado.value);
        formData.append('id_doctor', idDoctorSeleccionado.value);
        formData.append('fecha', fechaCita.value);
        formData.append('hora', horaCita.value);
        formData.append('id_estado_cita', selectEstadoCita.value);
        formData.append('id_tipo_servicio', document.getElementById('selectTipoServicio')?.value || '');
        formData.append('id_tipo_atencion', document.getElementById('selectTipoAtencion')?.value || '');
        formData.append('duracion_minutos', document.getElementById('selectDuracion')?.value || 30);
        formData.append('id_paciente_plan', document.getElementById('selectPlanCita')?.value || '');
        formData.append('id_sede_atencion', selectSede.value);
        formData.append('motivo', textareaMotivo.value.trim());
        
        // Alergias
        const alergiasNuevas = obtenerAlergiasSeleccionadas();
        if (alergiasNuevas.length > 0) {
            alergiasNuevas.forEach(idAlergia => {
                formData.append('alergias_medicamentos[]', idAlergia);
            });
        }
        
        console.log('📦 FormData preparado. Enviando...');
        
        fetch('../CONTROLADORES/controlador_citas.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                
                btnGuardar.disabled = false;
                btnGuardar.textContent = textoOriginal;
                
                if (data.success) {
                    console.log(' OPERACIÓN EXITOSA');
                    console.log('   Tipo:', esEdicion ? 'EDICIÓN' : 'REGISTRO');
                    
                    mostrarMensajeSistema(
                        esEdicion ? ' Cita actualizada correctamente' : ' Cita registrada con éxito',
                        'exito'
                    );
                    
                    // Limpiar data attributes
                    delete form.dataset.modo;
                    delete form.dataset.idCita;
                    
                    // Resetear variables globales
                    modoEdicion = false;
                    idEdicion = null;
                    
                    //  DESPUÉS:
                   setTimeout(() => {
                   console.log('🔵 Redirigiendo a listado...');
                   mostrarListado(true); // ← Pasar true para forzar recarga
                  }, 1500);
                } else {
                    console.error('❌ Error:', data.mensaje);
                    mostrarMensajeSistema(`❌ ${data.mensaje}`, 'error');
                }
            } catch (parseError) {
                console.error('❌ Error al parsear:', parseError);
                mostrarMensajeSistema('❌ Respuesta inválida', 'error');
                btnGuardar.disabled = false;
                btnGuardar.textContent = textoOriginal;
            }
        })
        .catch(err => {
            console.error('❌ Error en petición:', err);
            btnGuardar.disabled = false;
            btnGuardar.textContent = textoOriginal;
            mostrarMensajeSistema('❌ Error en la petición', 'error');
        });
    }

    // =====================================================
    // Validar formulario
    // =====================================================
    function validarFormularioCita() {
        console.log('🔵 === VALIDANDO FORMULARIO ===');
        
        let esValido = true;
        
        if (!idPacienteSeleccionado.value) {
            console.log('❌ Paciente no seleccionado');
            mostrarError(inputPaciente, 'Debe seleccionar un paciente');
            esValido = false;
        }
        
        if (!idDoctorSeleccionado.value) {
            console.log('❌ Doctor no seleccionado');
            mostrarError(inputDoctor, 'Debe seleccionar un doctor');
            esValido = false;
        }
        
        if (!fechaCita.value) {
            console.log('❌ Fecha vacía');
            mostrarError(fechaCita, 'La fecha es obligatoria');
            esValido = false;
        }
        
        if (!horaCita.value) {
            const cont = document.getElementById('slotsContenedor');
            if (cont) cont.style.border = '1px solid #e74c3c';
            mostrarMensajeSistema('Selecciona una hora disponible', 'error');
            esValido = false;
        } else {
            const cont = document.getElementById('slotsContenedor');
            if (cont) cont.style.border = '';
        }
        
        if (!selectEstadoCita.value) {
            console.log('❌ Estado no seleccionado');
            mostrarError(selectEstadoCita, 'Debe seleccionar un estado');
            esValido = false;
        }
        
        // Tipo de servicio es opcional — no se valida como obligatorio
        
        if (!selectSede.value) {
            console.log('❌ Sede no seleccionada');
            mostrarError(selectSede, 'Debe seleccionar una sede');
            esValido = false;
        }
        
        if (!textareaMotivo.value.trim()) {
            console.log('❌ Motivo vacío');
            mostrarError(textareaMotivo, 'Debe describir el motivo');
            esValido = false;
        }
        
        console.log('🔵 Resultado validación:', esValido ? '✅ VÁLIDO' : '❌ INVÁLIDO');
        
        return esValido;
    }

    function mostrarError(input, mensaje) {
        eliminarMensajeError(input);
        input.classList.remove('campo-valido');
        input.classList.add('campo-invalido');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'mensaje-error-campo';
        errorDiv.textContent = mensaje;
        input.parentElement.appendChild(errorDiv);
    }

    function eliminarMensajeError(input) {
        input.classList.remove('campo-invalido', 'campo-valido');
        const errorExistente = input.parentElement.querySelector('.mensaje-error-campo');
        if (errorExistente) {
            errorExistente.remove();
        }
    }

    // =====================================================
    //  CARGAR CITA PARA EDITAR - CON LOGS DETALLADOS
    // =====================================================
    function cargarCitaParaEditar(id) {
        console.log('🔵 ========================================');
        console.log('🔵 === CARGAR CITA PARA EDITAR ===');
        console.log('🔵 ========================================');
        console.log('📋 ID de cita recibido:', id);
        console.log('📋 Tipo de ID:', typeof id);
        
        fetch(`../CONTROLADORES/controlador_citas.php?accion=ver&id=${id}`)
            .then(res => res.text())
            .then(text => {
                console.log('📄 Respuesta RAW (primeros 500):', text.substring(0, 500));
                
                try {
                    const cita = JSON.parse(text);
                    console.log('✅ JSON parseado correctamente');
                    console.log('📦 Datos de cita:', JSON.stringify(cita, null, 2));
                    
                    if (cita.success === false) {
                        console.error('❌ Error del servidor:', cita.mensaje);
                        mostrarMensajeSistema('❌ Error al cargar', 'error');
                        return;
                    }
                    
                    console.log('🔵 ========================================');
                    console.log('🔵 ACTIVANDO MODO EDICIÓN (DATA ATTRIBUTES)');
                    console.log('🔵 ========================================');
                    
                    // ✅ SOLUCIÓN: Guardar en el formulario, NO en variables globales
                    form.dataset.modo = 'edicion';
                    form.dataset.idCita = cita.id_cita;
                    
                    console.log('✅ Datos guardados en formulario:');
                    console.log('   - form.dataset.modo:', form.dataset.modo);
                    console.log('   - form.dataset.idCita:', form.dataset.idCita);
                    
                    // También actualizar variables globales (para compatibilidad)
                    modoEdicion = true;
                    idEdicion = cita.id_cita;
                    
                    llenarFormularioEdicion(cita);
                    
                    document.getElementById('tituloCita').textContent = 'Editar Cita';
                    document.getElementById('btnGuardarCita').textContent = '🔄 Actualizar Cita';
                    
                    seccionListado.style.display = 'none';
                    seccionDetalle.style.display = 'none';
                    seccionNueva.style.display = 'block';
                    btnNueva.style.display = 'none';
                    
                    console.log('✅ Formulario cargado para edición');
                    console.log('🔵 ========================================');
                } catch (parseError) {
                    console.error('❌ Error al parsear JSON:', parseError);
                    mostrarMensajeSistema('❌ Respuesta inválida', 'error');
                }
            })
            .catch(err => {
                console.error('❌ Error en petición:', err);
                mostrarMensajeSistema('❌ Error al cargar', 'error');
            });
    }

// =====================================================
    // 🚀 PARTE 5/6: Ver, Eliminar, Listar y Filtrar Citas
    // =====================================================

    // =====================================================
    // 📝 Llenar formulario para edición
    // =====================================================
    function llenarFormularioEdicion(cita) {
        console.log('🔵 === LLENANDO FORMULARIO EDICIÓN ===');
        console.log('📦 Cita:', JSON.stringify(cita, null, 2));
        
        // -- Paciente --
        inputPaciente.value = `${cita.paciente_nombre} ${cita.paciente_apellido} - DNI: ${cita.paciente_dni}`;
        idPacienteSeleccionado.value = cita.id_paciente;
        inputPaciente.classList.add('campo-valido');
        
        // -- Alergias existentes --
        alergiasActualesPaciente = cita.alergias_medicamentos || [];
        const contenedorPreguntaInicial = document.getElementById('contenedorPreguntaInicial');
        const textoPreguntaInicial = document.getElementById('textoPreguntaInicial');
        const textoOpcionSi = document.getElementById('textoOpcionSi');
        if (alergiasActualesPaciente.length > 0) {
            mostrarMensajeAlergiasExistentes(alergiasActualesPaciente);
            textoPreguntaInicial.textContent = "¿Desea registrar más medicamentos alérgicos?";
            textoOpcionSi.textContent = "Sí, registrar más";
        } else {
            mensajeAlergiasExistentes.style.display = 'none';
            textoPreguntaInicial.textContent = "¿El paciente tiene alergias a medicamentos?";
            textoOpcionSi.textContent = "Sí";
        }
        contenedorPreguntaInicial.style.display = 'block';
        document.querySelector('input[name="tiene_alergia"][value="no"]').checked = true;
        document.getElementById('contenedorChipsMedicamentos').style.display = 'none';
        
        // -- Doctor --
        inputDoctor.value = `Dr(a). ${cita.doctor_nombre} ${cita.doctor_apellidos}`;
        idDoctorSeleccionado.value = cita.id_doctor;
        inputDoctor.classList.add('campo-valido');
        
        // -- Fecha (ANTES de sede/doctor para que cargarSlots funcione) --
        fechaCita.value = cita.fecha;
        
        // -- Sede: esperar a que las opciones estén cargadas antes de seleccionar y cargar slots --
        const sedesListas = window._sedesListas || Promise.resolve();
        sedesListas.then(() => {
            selectSede.value = cita.id_sede_atencion || '';
            // -- Cargar slots y pre-seleccionar la hora actual --
            // Se hace DESPUÉS de setear doctor, sede y fecha
            cargarSlotsConPreseleccion(horaActual);
            console.log('✅ Sede seteada y slots cargados. Hora a preseleccionar:', horaActual);
        });
        const selTipoAtencion = document.getElementById('selectTipoAtencion');
        if (selTipoAtencion) selTipoAtencion.value = cita.id_tipo_atencion || '';
        
        const selDuracion = document.getElementById('selectDuracion');
        if (selDuracion) selDuracion.value = cita.duracion_minutos || 30;
        
        // -- Hora actual de la cita (guardamos para pre-seleccionar el slot) --
        // Normalizar hora a HH:MM para comparar con slots
        const horaActual = cita.hora ? cita.hora.substring(0, 5) : '';
        horaCita.value = cita.hora || '';
        
        // -- Estado --
        selectEstadoCita.value = cita.id_estado_cita || '';

        // Filtrar opciones de estado según transiciones permitidas
        filtrarEstadosPermitidos(parseInt(cita.id_estado_cita));
        
        // -- Tipo de servicio --
        const selServicio = document.getElementById('selectTipoServicio');
        if (selServicio) selServicio.value = cita.id_tipo_servicio || '';
        
        // -- Motivo --
        textareaMotivo.value = cita.motivo || '';
        
        // -- Plan del paciente --
        cargarPlanesPaciente(cita.id_paciente, cita.id_paciente_plan);

        // NOTA: cargarSlotsConPreseleccion se llama dentro de sedesListas.then() (ver arriba)
        // para garantizar que las opciones del select de sede ya estén disponibles.
        
        console.log('✅ Formulario de edición llenado. Hora a preseleccionar:', horaActual);
    }

    // =====================================================
    // 👁️ Ver detalle de cita
    // =====================================================
    function verDetalleCita(id) {
        console.log('🔵 === VER DETALLE ===');
        console.log('📋 ID:', id);
        
        fetch(`../CONTROLADORES/controlador_citas.php?accion=ver&id=${id}`)
            .then(res => res.text())
            .then(text => {
                try {
                    const cita = JSON.parse(text);
                    
                    if (cita.success === false) {
                        mostrarMensajeSistema('❌ Error al cargar', 'error');
                        return;
                    }
                    
                    mostrarDetalle(cita);
                } catch (parseError) {
                    console.error('❌ Error:', parseError);
                    mostrarMensajeSistema('❌ Respuesta inválida', 'error');
                }
            })
            .catch(err => {
                console.error('❌ Error:', err);
                mostrarMensajeSistema('❌ Error al cargar', 'error');
            });
    }

    // =====================================================
    // 📄 Mostrar detalle de cita
    // =====================================================
    function mostrarDetalle(cita) {
        const estadoClase = obtenerClaseEstado(cita.estado);
        const horaFormateada = cita.hora || 'N/A';
        
        let seccionAlergias = '';
        if (cita.alergias_medicamentos && cita.alergias_medicamentos.length > 0) {
            const chipsAlergias = cita.alergias_medicamentos
                .map(a => `<span class="chip-alergia-existente">${a.medicamento}</span>`)
                .join('');
            
            seccionAlergias = `
            <div class="seccion-detalle seccion-alerta">
                <h4>⚕️ Información Médica - Alergias</h4>
                <div class="datos-detalle">
                    <p><strong>Medicamentos Alérgicos (${cita.alergias_medicamentos.length}):</strong></p>
                    <div class="lista-alergias-existentes" style="margin-top: 10px;">
                        ${chipsAlergias}
                    </div>
                </div>
            </div>
            `;
        }
        
        detalleDiv.innerHTML = `
            <div class="tarjeta-detalle">
                <div class="encabezado-detalle">
                    <h3>📋 Detalle de la Cita #${cita.id_cita}</h3>
                    <span class="badge-estado-grande ${estadoClase}">${cita.estado}</span>
                </div>
                
                <div class="contenido-detalle">
                    <div class="seccion-detalle">
                        <h4>👤 Información del Paciente</h4>
                        <div class="datos-detalle">
                            <p><strong>Nombre:</strong> ${cita.paciente_nombre} ${cita.paciente_apellido}</p>
                            <p><strong>DNI:</strong> ${cita.paciente_dni}</p>
                        </div>
                    </div>
                    
                    <div class="seccion-detalle">
                        <h4>👨‍⚕️ Doctor Asignado</h4>
                        <div class="datos-detalle">
                            <p><strong>Nombre:</strong> Dr(a). ${cita.doctor_nombre} ${cita.doctor_apellidos}</p>
                        </div>
                    </div>
                    
                    <div class="seccion-detalle">
                        <h4>📅 Fecha y Hora</h4>
                        <div class="datos-detalle">
                            <p><strong>Fecha:</strong> ${formatearFechaLarga(cita.fecha)}</p>
                            <p><strong>Hora:</strong> ${horaFormateada}</p>
                        </div>
                    </div>
                    
                    <div class="seccion-detalle">
                        <h4>🦷 Detalles del Servicio</h4>
                        <div class="datos-detalle">
                            <p><strong>Tipo de Servicio:</strong> ${cita.nombre_servicio || 'N/A'}</p>
                            <p><strong>Sede:</strong> ${cita.sede || 'N/A'}</p>
                            <p><strong>Motivo:</strong> ${cita.motivo || 'N/A'}</p>
                        </div>
                    </div>
                    
                    ${seccionAlergias}
                </div>
                
                <div class="acciones-detalle">
                    <button class="btn-editar" id="btnEditarDetalle">✏️ Editar</button>
                    <button class="btn-eliminar-grande" id="btnEliminarDetalle">🗑️ Eliminar</button>
                </div>
            </div>
        `;
        
        seccionListado.style.display = 'none';
        seccionNueva.style.display = 'none';
        seccionDetalle.style.display = 'block';
        btnNueva.style.display = 'none';
        
        document.getElementById('btnEditarDetalle').addEventListener('click', () => {
            cargarCitaParaEditar(cita.id_cita);
        });
        
        document.getElementById('btnEliminarDetalle').addEventListener('click', () => {
            confirmarEliminarCita(cita.id_cita);
        });
    }

    // =====================================================
    // 🗑️ ELIMINAR CITA
    // =====================================================
    function confirmarEliminarCita(id) {
        if (confirm('⚠️ ¿Eliminar esta cita?\n\nNo se puede deshacer.')) {
            eliminarCita(id);
        }
    }

    function eliminarCita(id) {
        console.log('🗑️ Eliminando cita:', id);
        
        fetch('../CONTROLADORES/controlador_citas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `accion=eliminar&id_cita=${id}`
        })
        .then(res => res.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                
                // ✅ DESPUÉS:
                if (data.success) {
                  mostrarMensajeSistema('✅ Cita eliminada', 'exito');
                  mostrarListado(true); // ← Pasar true para forzar recarga
                }else {
                    mostrarMensajeSistema('❌ Error al eliminar', 'error');
                }
            } catch (parseError) {
                console.error('❌ Error:', parseError);
                mostrarMensajeSistema('❌ Respuesta inválida', 'error');
            }
        })
        .catch(err => {
            console.error('❌ Error:', err);
            mostrarMensajeSistema('❌ Error al eliminar', 'error');
        });
    }

    // =====================================================
    // 📋 LISTAR CITAS
    // =====================================================
    function listarCitas() {
        if (tablaBody) {
            tablaBody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:20px;">⏳ Cargando citas...</td></tr>`;
        }
        // Cargar citas y verificar vencidas en paralelo
        Promise.all([
            fetch('../CONTROLADORES/controlador_citas.php?accion=listar').then(r => r.json()),
            fetch('../CONTROLADORES/controlador_citas.php?accion=listar_vencidas').then(r => r.json())
        ]).then(([citas, vencidas]) => {
            citasCompleto = [...citas];
            filtrarCitas();
            mostrarBannerVencidas(vencidas);
        }).catch(() => {
            fetch('../CONTROLADORES/controlador_citas.php?accion=listar')
                .then(r => r.json())
                .then(data => { citasCompleto = [...data]; filtrarCitas(); })
                .catch(() => mostrarMensajeSistema('❌ Error al cargar citas', 'error'));
        });
    }

    // =====================================================
    // 🎨 Renderizar tabla
    // =====================================================
    function renderizarTablaCitas(datos) {
        console.log('🎨 Renderizando:', datos.length);
        
        tablaBody.innerHTML = '';
        
        if (datos.length === 0) {
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align:center; padding:20px; color:#999;">
                        📭 No hay citas con los filtros seleccionados
                    </td>
                </tr>
            `;
            return;
        }

        datos.forEach(cita => {
            const estadoClase = obtenerClaseEstado(cita.estado);
            const horaFormateada = cita.hora ? cita.hora.substring(0,5) : 'N/A';
            
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td>${cita.id_cita}</td>
                <td>
                    <strong style="font-size:13px;color:#2c3e50;">${cita.paciente_nombre} ${cita.paciente_apellido}</strong><br>
                    <small style="color:#95a5a6;font-size:11px;">DNI: ${cita.paciente_dni}</small>
                </td>
                <td>
                    <span style="font-size:13px;color:#2c3e50;">Dr(a). ${cita.doctor_nombre} ${cita.doctor_apellidos}</span>
                </td>
                <td style="white-space:nowrap;">${formatearFecha(cita.fecha)}</td>
                <td><span style="font-size:13px;font-weight:600;color:#2c3e50;">⏰ ${horaFormateada}</span></td>
                <td><span class="badge-estado ${estadoClase}">${cita.estado}</span></td>
                <td><span class="badge-servicio">${cita.tipo_atencion || cita.nombre_servicio || '—'}</span></td>
                <td><span class="sede-info">📍 ${cita.sede || 'N/A'}</span></td>
                <td style="white-space:nowrap;">
                    <button class="btn-accion btn-ver" data-id="${cita.id_cita}" title="Ver detalle">👁️</button>
                    ${!['completada','cancelada','no asistió'].includes(cita.estado?.toLowerCase())
                        ? `<button class="btn-accion btn-editar" data-id="${cita.id_cita}" title="Editar">✏️</button>`
                        : ''}
                    ${cita.estado?.toLowerCase() !== 'completada'
                        ? `<button class="btn-accion btn-eliminar" data-id="${cita.id_cita}" title="Eliminar">🗑️</button>`
                        : ''}
                </td>
            `;
            
            tablaBody.appendChild(fila);
        });

        document.querySelectorAll('.btn-accion.btn-ver').forEach(btn => {
            btn.addEventListener('click', (e) => verDetalleCita(e.currentTarget.dataset.id));
        });

        document.querySelectorAll('.btn-accion.btn-editar').forEach(btn => {
            btn.addEventListener('click', (e) => cargarCitaParaEditar(e.currentTarget.dataset.id));
        });

        document.querySelectorAll('.btn-accion.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', (e) => confirmarEliminarCita(e.currentTarget.dataset.id));
        });
    }

    // =====================================================
    // 📊 Actualizar estadísticas
    // =====================================================
    function actualizarEstadisticas(datos) {
        const todas      = datos.length;
        const programadas = datos.filter(c => c.estado?.toLowerCase() === 'programada').length;
        const confirmadas = datos.filter(c => c.estado?.toLowerCase() === 'confirmada').length;
        const completadas = datos.filter(c => c.estado?.toLowerCase() === 'completada').length;
        const canceladas  = datos.filter(c => c.estado?.toLowerCase() === 'cancelada').length;
        const noAsistio   = datos.filter(c => c.estado?.toLowerCase() === 'no asistió').length;

        // Leer siempre directo del DOM para evitar referencias obsoletas por cloneNode
        const elTodos       = document.getElementById('statTodos');
        const elProgramadas = document.getElementById('statProgramadas');
        const elConfirmadas = document.getElementById('statConfirmadas');
        const elCompletadas = document.getElementById('statCompletadas');
        const elCanceladas  = document.getElementById('statCanceladas');
        const elNoAsistio   = document.getElementById('statNoAsistio');

        if (elTodos)       elTodos.textContent       = todas;
        if (elProgramadas) elProgramadas.textContent = programadas;
        if (elConfirmadas) elConfirmadas.textContent = confirmadas;
        if (elCompletadas) elCompletadas.textContent = completadas;
        if (elCanceladas)  elCanceladas.textContent  = canceladas;
        if (elNoAsistio)   elNoAsistio.textContent   = noAsistio;
    }

    // =====================================================
    // 🔍 FILTRAR CITAS
    // =====================================================
    function filtrarCitas() {
        console.log('🔵 === FILTRAR CITAS ===');
        console.log('   Filtro búsqueda:', inputBusqueda?.value);
        console.log('   Filtro fecha:', filtroFecha?.value);
        console.log('   Filtro estado:', estadoFiltroActual);
        
        const terminoBusqueda = inputBusqueda?.value?.toLowerCase().trim() || '';
        const fechaFiltro = filtroFecha?.value || '';
        
        let citasFiltradas = [...citasCompleto];
        console.log('   📦 Total de citas antes de filtrar:', citasFiltradas.length);
        
        // Filtro por búsqueda
        if (terminoBusqueda !== '') {
            citasFiltradas = citasFiltradas.filter(c => {
                const pacienteNombre = `${c.paciente_nombre} ${c.paciente_apellido}`.toLowerCase();
                const doctorNombre = `${c.doctor_nombre} ${c.doctor_apellidos}`.toLowerCase();
                const dni = c.paciente_dni?.toLowerCase() || '';
                
                return pacienteNombre.includes(terminoBusqueda) ||
                       doctorNombre.includes(terminoBusqueda) ||
                       dni.includes(terminoBusqueda);
            });
            console.log('   📦 Después de filtrar por búsqueda:', citasFiltradas.length);
        }
        
        // Filtro por estado
        if (estadoFiltroActual !== '') {
            citasFiltradas = citasFiltradas.filter(c => {
                return c.estado?.toLowerCase() === estadoFiltroActual.toLowerCase();
            });
            console.log('   📦 Después de filtrar por estado:', citasFiltradas.length);
        }
        
        // Filtro por fecha
        if (fechaFiltro !== '') {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            citasFiltradas = citasFiltradas.filter(c => {
                const fechaCita = new Date(c.fecha + 'T00:00:00');
                
                if (fechaFiltro === 'hoy') {
                    return fechaCita.getTime() === hoy.getTime();
                } else if (fechaFiltro === 'semana') {
                    const finSemana = new Date(hoy);
                    finSemana.setDate(hoy.getDate() + 7);
                    return fechaCita >= hoy && fechaCita <= finSemana;
                } else if (fechaFiltro === 'mes') {
                    return fechaCita.getMonth() === hoy.getMonth() && 
                           fechaCita.getFullYear() === hoy.getFullYear();
                }
                return true;
            });
            console.log('   📦 Después de filtrar por fecha:', citasFiltradas.length);
        }
        
        console.log('   ✅ Total de citas filtradas:', citasFiltradas.length);
        
        // ✅ CRÍTICO: Actualizar estadísticas CON DATOS COMPLETOS (sin filtros)
        actualizarEstadisticas(citasCompleto);
        
        // Renderizar tabla con datos filtrados
        renderizarTablaCitas(citasFiltradas);
    }

    // =====================================================
    // 🚀 PARTE 6/6 (FINAL): Navegación y Funciones Auxiliares
    // =====================================================

    // =====================================================
    // 🆕 MOSTRAR FORMULARIO NUEVO
    // =====================================================
    function mostrarFormularioNuevo() {
        form.reset();
        delete form.dataset.modo;
        delete form.dataset.idCita;
        inputPaciente.value = '';
        inputDoctor.value = '';
        idPacienteSeleccionado.value = '';
        idDoctorSeleccionado.value = '';
        // Solo Programada y Confirmada disponibles al crear
        filtrarEstadosPermitidos(null);
        
        form.querySelectorAll('.campo-invalido, .campo-valido').forEach(el => {
            el.classList.remove('campo-invalido', 'campo-valido');
        });
        form.querySelectorAll('.mensaje-error-campo').forEach(el => el.remove());
        
        limpiarSeccionAlergias();
        
        const chipsMedicamentos = document.getElementById('chipsMedicamentos');
        if (chipsMedicamentos) chipsMedicamentos.innerHTML = '';
        
        const campoOculto = document.getElementById('alergiasMedicamentosHidden');
        if (campoOculto) campoOculto.value = '';
        
        modoEdicion = false;
        idEdicion = null;
        
        document.getElementById('tituloCita').textContent = 'Registrar Nueva Cita';
        document.getElementById('btnGuardarCita').textContent = '💾 Guardar Cita';
        
        seccionListado.style.display = 'none';
        seccionDetalle.style.display = 'none';
        seccionNueva.style.display = 'block';
        btnNueva.style.display = 'none';
        
        console.log('✅ Formulario nuevo preparado');
    }
    // =====================================================
    // 🔙 MOSTRAR LISTADO
    // =====================================================
    function mostrarListado(forzarRecarga = false) {
        console.log('🔙 === MOSTRAR LISTADO ===');
        console.log('   - forzarRecarga:', forzarRecarga);
        
        // Resetear modo edición
        console.log('🔵 Reseteando modo edición al volver al listado');
        modoEdicion = false;
        idEdicion = null;
        
        // Limpiar data attributes del formulario
        if (form) {
            delete form.dataset.modo;
            delete form.dataset.idCita;
        }
        
        seccionNueva.style.display = 'none';
        seccionDetalle.style.display = 'none';
        seccionListado.style.display = 'block';
        btnNueva.style.display = 'inline-block';
        
        // ✅ CRÍTICO: Recargar datos si viene de guardar/editar/eliminar
        if (forzarRecarga) {
            console.log('🔄 Forzando recarga desde base de datos...');
            // Resetear filtro activo
            estadoFiltroActual = '';
            // Quitar clase activa de todas las cajas
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('filtro-activo'));
            // Agregar clase activa a "Todas"
            document.querySelector('.stat-card[data-estado=""]')?.classList.add('filtro-activo');
        }
        
        listarCitas();
        
        console.log('✅ Listado mostrado');
        console.log('   - modoEdicion:', modoEdicion);
        console.log('   - idEdicion:', idEdicion);
    }

    // =====================================================
    // 🛠️ FUNCIONES AUXILIARES
    // =====================================================

    function obtenerClaseEstado(estado) {
        if (!estado) return '';
        
        const estadoLower = estado.toLowerCase();
        
        if (estadoLower === 'programada') {
            return 'estado-programada';
        } else if (estadoLower === 'confirmada') {
            return 'estado-confirmada';
        } else if (estadoLower === 'completada') {
            return 'estado-completada';
        } else if (estadoLower === 'cancelada') {
            return 'estado-cancelada';
        } else if (estadoLower === 'no asistió') {
            return 'estado-no-asistio';
        }
        
        return '';
    }

    function formatearFecha(fecha) {
        if (!fecha) return 'N/A';
        
        const partes = fecha.split('-');
        if (partes.length !== 3) return fecha;
        
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    function formatearFechaLarga(fecha) {
        if (!fecha) return 'N/A';
        
        const meses = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];
        
        const dias = [
            'domingo', 'lunes', 'martes', 'miércoles', 
            'jueves', 'viernes', 'sábado'
        ];
        
        const fechaObj = new Date(fecha + 'T00:00:00');
        const dia = fechaObj.getDate();
        const mes = meses[fechaObj.getMonth()];
        const año = fechaObj.getFullYear();
        const diaSemana = dias[fechaObj.getDay()];
        
        return `${diaSemana}, ${dia} de ${mes} de ${año}`;
    }

    function mostrarMensajeSistema(mensaje, tipo = 'exito') {
        console.log('📢 Mensaje:', tipo, '-', mensaje);
        
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        const icono = tipo === 'exito' ? '✅' : '❌';
        aviso.innerHTML = `
            <span class="icono">${icono}</span>
            <span class="texto">${mensaje}</span>
        `;
        
        document.body.appendChild(aviso);
        
        setTimeout(() => aviso.classList.add('mostrar'), 100);
        
        setTimeout(() => {
            aviso.classList.remove('mostrar');
            setTimeout(() => aviso.remove(), 400);
        }, 3000);
    }

// =====================================================
// 🎬 FIN DEL MÓDULO - Cerrar función principal
    // ── Slots disponibles ──────────────────────────────────────────
    // =====================================================
    // 🔔 BANNER DE CITAS VENCIDAS
    // =====================================================
    function mostrarBannerVencidas(vencidas) {
        // Eliminar banner previo si existe
        const previo = document.getElementById('bannerVencidas');
        if (previo) previo.remove();

        if (!vencidas || vencidas.length === 0) return;

        const contenedor = document.getElementById('seccionListadoCitas');
        if (!contenedor) return;

        const banner = document.createElement('div');
        banner.id = 'bannerVencidas';
        banner.style.cssText = `
            background:#fff9f0;border:1px solid #fad7a0;border-radius:8px;
            padding:14px 16px;margin-bottom:16px;
        `;

        const filas = vencidas.map(c => `
            <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;
                        background:white;border-radius:6px;margin-top:6px;flex-wrap:wrap;">
                <span style="font-size:12px;color:#636e72;min-width:78px;">${formatearFecha(c.fecha)}</span>
                <span style="font-size:13px;color:#2c3e50;flex:1;min-width:140px;">
                    ${c.nombre_paciente}
                    <small style="color:#95a5a6;"> — Dr(a). ${c.nombre_doctor}</small>
                </span>
                <span style="font-size:12px;color:#7f8c8d;">${c.hora ? c.hora.substring(0,5) : ''}</span>
                <div style="display:flex;gap:6px;">
                    <button class="btn-cerrar-vencida" data-id="${c.id_cita}" data-estado="5"
                        style="padding:4px 10px;font-size:11px;font-weight:600;border-radius:5px;
                               border:1px solid #f5b7b1;background:#fdf2f2;color:#e74c3c;cursor:pointer;">
                        No asistió
                    </button>
                    <button class="btn-cerrar-vencida" data-id="${c.id_cita}" data-estado="3"
                        style="padding:4px 10px;font-size:11px;font-weight:600;border-radius:5px;
                               border:1px solid #ddd;background:white;color:#636e72;cursor:pointer;">
                        Cancelada
                    </button>
                </div>
            </div>
        `).join('');

        banner.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:18px;">⚠️</span>
                <div style="flex:1;">
                    <p style="font-size:13px;font-weight:600;color:#e67e22;margin:0;">
                        ${vencidas.length} cita${vencidas.length > 1 ? 's' : ''} vencida${vencidas.length > 1 ? 's' : ''} sin cerrar
                    </p>
                    <p style="font-size:12px;color:#e67e22;margin:0;">
                        Tienen fecha pasada y siguen como Programada o Confirmada.
                    </p>
                </div>
                <button id="btnToggleBanner"
                    style="padding:5px 12px;font-size:12px;border-radius:5px;
                           border:1px solid #fad7a0;background:white;color:#e67e22;cursor:pointer;">
                    Revisar ▾
                </button>
            </div>
            <div id="listaBannerVencidas" style="display:none;">${filas}</div>
        `;

        contenedor.insertBefore(banner, contenedor.firstChild);

        // Toggle mostrar/ocultar lista
        document.getElementById('btnToggleBanner').addEventListener('click', function() {
            const lista = document.getElementById('listaBannerVencidas');
            const abierto = lista.style.display !== 'none';
            lista.style.display = abierto ? 'none' : 'block';
            this.textContent = abierto ? 'Revisar ▾' : 'Ocultar ▴';
        });

        // Botones de cierre
        banner.querySelectorAll('.btn-cerrar-vencida').forEach(btn => {
            btn.addEventListener('click', function() {
                const id     = this.dataset.id;
                const estado = this.dataset.estado;
                const label  = estado === '5' ? 'No asistió' : 'Cancelada';
                this.disabled = true;
                this.textContent = '...';

                const fd = new FormData();
                fd.append('accion', 'cerrar_vencida');
                fd.append('id_cita', id);
                fd.append('id_estado_cita', estado);

                fetch('../CONTROLADORES/controlador_citas.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            // Quitar la fila del banner
                            this.closest('div[style]').remove();
                            mostrarMensajeSistema(`✅ Cita marcada como ${label}`, 'exito');
                            // Si no quedan filas, quitar banner
                            if (!banner.querySelectorAll('.btn-cerrar-vencida').length) {
                                banner.remove();
                            }
                            listarCitas();
                        } else {
                            mostrarMensajeSistema(`❌ ${data.mensaje}`, 'error');
                            this.disabled = false;
                            this.textContent = label;
                        }
                    })
                    .catch(() => {
                        mostrarMensajeSistema('❌ Error al actualizar', 'error');
                        this.disabled = false;
                        this.textContent = label;
                    });
            });
        });
    }

    // =====================================================
    // 🔒 FILTRAR ESTADOS SEGÚN TRANSICIONES PERMITIDAS
    // =====================================================
    function filtrarEstadosPermitidos(estadoActualId) {
        // Transiciones permitidas — espejo del modelo PHP
        const transiciones = {
            1: [2, 3],       // Programada → Confirmada, Cancelada
            2: [4, 5, 3],    // Confirmada → Completada, No asistió, Cancelada
            3: [1],          // Cancelada → Programada (reversión)
            4: [],           // Completada → ninguno
            5: [1],          // No asistió → Programada (reversión)
        };
        const permitidos = transiciones[estadoActualId] ?? null;

        Array.from(selectEstadoCita.options).forEach(opt => {
            if (!opt.value) return; // mantener el "Seleccione..."
            const id = parseInt(opt.value);
            if (permitidos === null) {
                // Nueva cita — solo Programada(1) y Confirmada(2)
                opt.disabled = ![1, 2].includes(id);
                opt.style.color = opt.disabled ? '#ccc' : '';
            } else if (id === estadoActualId) {
                // Estado actual — siempre disponible y seleccionado
                opt.disabled = false;
                opt.style.color = '';
            } else if (permitidos.includes(id)) {
                opt.disabled = false;
                opt.style.color = '';
            } else {
                opt.disabled = true;
                opt.style.color = '#ccc';
            }
        });
    }

    function cargarSlots() {
        cargarSlotsConPreseleccion(null);
    }

    // ── Slots con preselección de hora (para edición) ─────────────
    function cargarSlotsConPreseleccion(horaPreseleccionar) {
        const idDoctor = document.getElementById('idDoctorSeleccionado')?.value;
        const idSede   = document.getElementById('selectSede')?.value;
        const fecha    = document.getElementById('fechaCita')?.value;
        const duracion = document.getElementById('selectDuracion')?.value || 30;
        const cont     = document.getElementById('slotsContenedor');
        const horaHid  = document.getElementById('horaCita');
        if (!cont) return;

        if (!idDoctor || !idSede || !fecha) {
            const missing = [];
            if (!idDoctor) missing.push('doctor');
            if (!idSede)   missing.push('sede');
            if (!fecha)    missing.push('fecha');
            cont.innerHTML = `<small style="color:#95a5a6;font-style:italic;">Selecciona ${missing.join(', ')} para ver los slots disponibles.</small>`;
            if (horaHid && !horaPreseleccionar) horaHid.value = '';
            return;
        }

        cont.innerHTML = '<small style="color:#7f8c8d;">Cargando horarios...</small>';
        // Solo limpiar hora si no estamos preseleccionando (edición)
        if (horaHid && !horaPreseleccionar) horaHid.value = '';

        const idCita = document.getElementById('idCitaEditar')?.value || 0;

        fetch(`../CONTROLADORES/controlador_configuracion.php?accion=slots_disponibles&id_doctor=${idDoctor}&id_sede=${idSede}&fecha=${fecha}&duracion=${duracion}&id_cita=${idCita}`)
            .then(r => r.json())
            .then(data => {
                // En modo edición: si el doctor no tiene horario ese día pero la cita ya existe,
                // igual mostramos la hora actual como única opción preseleccionada
                if (!data.disponible || !data.slots || !data.slots.length) {
                    if (horaPreseleccionar) {
                        // Modo edición: mostrar la hora actual aunque el horario no esté disponible
                        cont.innerHTML = 
                            `<div style="display:flex;flex-wrap:wrap;gap:6px;">` +
                            `<button type="button" class="slot-hora-btn slot-hora-seleccionado" data-hora="${horaPreseleccionar}"
                                style="padding:5px 12px;border:0.5px solid #2a4d8f;border-radius:5px;background:#2a4d8f;color:white;font-size:12px;cursor:pointer;">
                                ${horaPreseleccionar} ✓ (hora actual)
                            </button></div>` +
                            `<small style="color:#e67e22;display:block;margin-top:4px;">⚠️ No hay horario configurado para ese día. Hora actual mantenida.</small>`;
                        if (horaHid) horaHid.value = horaPreseleccionar + ':00';
                        // Agregar listener al botón único
                        cont.querySelectorAll('.slot-hora-btn').forEach(btn => {
                            btn.addEventListener('click', () => {
                                if (horaHid) horaHid.value = btn.dataset.hora + ':00';
                            });
                        });
                    } else {
                        cont.innerHTML = `<small style="color:#8b3a3a;background:#f9f0f0;padding:6px 10px;border-radius:4px;display:inline-block;">${data.mensaje || 'El doctor no tiene horario configurado para ese día. Configura el horario en Configuración → Horarios.'}</small>`;
                    }
                    return;
                }

                // Verificar si la hora actual no está en los slots (puede ocurrir en edición por duración distinta)
                const slotsFinales = [...data.slots];
                if (horaPreseleccionar && !slotsFinales.includes(horaPreseleccionar)) {
                    // Agregar la hora actual al inicio de los slots para que pueda ser preseleccionada
                    slotsFinales.unshift(horaPreseleccionar);
                }

                cont.innerHTML = '<div style="display:flex;flex-wrap:wrap;gap:6px;">' +
                    slotsFinales.map(slot => {
                        const esActual = slot === horaPreseleccionar;
                        const estilo = esActual 
                            ? 'padding:5px 12px;border:0.5px solid #2a4d8f;border-radius:5px;background:#2a4d8f;color:white;font-size:12px;cursor:pointer;'
                            : 'padding:5px 12px;border:0.5px solid #dde1e5;border-radius:5px;background:white;color:#2c3e50;font-size:12px;cursor:pointer;';
                        const label = esActual ? `${slot} ✓` : slot;
                        return `<button type="button" class="slot-hora-btn" data-hora="${slot}" style="${estilo}">${label}</button>`;
                    }).join('') + '</div>';

                // Si estamos en modo edición, preseleccionar la hora actual
                if (horaPreseleccionar && horaHid) {
                    horaHid.value = horaPreseleccionar + ':00';
                }

                cont.querySelectorAll('.slot-hora-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        cont.querySelectorAll('.slot-hora-btn').forEach(b => {
                            b.style.background = 'white';
                            b.style.color = '#2c3e50';
                            b.style.borderColor = '#dde1e5';
                            b.textContent = b.dataset.hora; // quitar el ✓ de otros
                        });
                        btn.style.background = '#2a4d8f';
                        btn.style.color = 'white';
                        btn.style.borderColor = '#2a4d8f';
                        if (horaHid) horaHid.value = btn.dataset.hora + ':00';
                    });
                });
            })
            .catch(() => {
                cont.innerHTML = '<small style="color:#8b3a3a;">Error al cargar horarios. Intente nuevamente.</small>';
            });
    }

    // ── Planes activos del paciente (con preselección opcional) ───
    function cargarPlanesPaciente(id_paciente, id_plan_preseleccionar) {
        const sel = document.getElementById('selectPlanCita');
        if (!sel || !id_paciente) return;
        sel.innerHTML = '<option value="">Sin plan asociado</option>';
        fetch(`../CONTROLADORES/controlador_atenciones.php?accion=listar_planes_activos&id_paciente=${id_paciente}`)
            .then(r => r.json())
            .then(planes => {
                if (planes && planes.length) {
                    planes.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id_paciente_plan;
                        opt.textContent = p.nombre_plan + ' — ' + (p.tipo === 'costo_total' ? 'Costo fijo' : 'Por sesión');
                        sel.appendChild(opt);
                    });
                }
                // Preseleccionar el plan asociado a la cita (edición)
                if (id_plan_preseleccionar) {
                    sel.value = id_plan_preseleccionar;
                }
            })
            .catch(() => {});
    }

} // ← Cierre de iniciarModuloCitas()

// =====================================================
// 🎯 AUTO-INICIALIZACIÓN - ✅ CON PROTECCIÓN
// =====================================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarModuloCitas);
} else {
    const contenedorCitas = document.getElementById('seccionListadoCitas');
    if (contenedorCitas && contenedorCitas.offsetParent !== null) {
        iniciarModuloCitas();
    }
}