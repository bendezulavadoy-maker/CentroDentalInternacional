let pacienteActual = null; // 👈 MOVER ESTA DECLARACIÓN AQUÍ (FUERA DE LA FUNCIÓN)
function iniciarModuloHistoriaClinica() {
    console.log('🏥 Iniciando módulo de Historia Clínica');

    // =====================================================
    // 🔹 ELEMENTOS DEL DOM
    // =====================================================
    const inputBuscar = document.getElementById('inputBuscarPaciente');
    const resultadosBusqueda = document.getElementById('resultadosBusqueda');
    const btnVolverPacientes = document.getElementById('btnVolverPacientes');
    const contenidoHistoria = document.getElementById('contenidoHistoriaClinica');
    const mensajeSinPaciente = document.getElementById('mensajeSinPaciente');
    const btnGestionarAlergias = document.getElementById('btnGestionarAlergias');
    const modalAlergias = document.getElementById('modalAlergias');
    const btnCerrarModalAlergias = document.getElementById('btnCerrarModalAlergias');
    const btnCancelarAlergias = document.getElementById('btnCancelarAlergias');
    const btnGuardarAlergias = document.getElementById('btnGuardarAlergias');
    const btnAgregarMedicamento = document.getElementById('btnAgregarMedicamento');
    const inputBuscarMedicamento = document.getElementById('inputBuscarMedicamento');
    const listaAlergiasModal = document.getElementById('listaAlergiasModal');
    const resultadosMedicamentos = document.getElementById('resultadosMedicamentos');
    // En la sección de elementos del DOM, agregar:
const btnGenerarPDF = document.getElementById('btnGenerarPDF');


    let alergiasTemporales = [];
    let timeoutBusqueda = null;
    let timeoutMedicamentos = null;

    // =====================================================
    // 🔹 INICIALIZACIÓN
    // =====================================================
    
    // Verificar si hay un paciente en sessionStorage
    const pacienteGuardado = sessionStorage.getItem('paciente_historia_actual');
    if (pacienteGuardado) {
        const paciente = JSON.parse(pacienteGuardado);
        const pestanaInicial = paciente.pestana || 'citas';
        if (paciente.id_cita) {
            sessionStorage.setItem('resaltar_cita', paciente.id_cita);
        }
        sessionStorage.removeItem('paciente_historia_actual');
        cargarHistoriaClinica(paciente.id, pestanaInicial);
    }

    // Verificar si hay ID en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const idPacienteUrl = urlParams.get('id_paciente');
    if (idPacienteUrl) {
        cargarHistoriaClinica(idPacienteUrl);
    }
    // En la función de inicialización, agregar el evento:
    if (btnGenerarPDF) {
    btnGenerarPDF.addEventListener('click', generarPDFHistoriaClinica);
     }

    // =====================================================
    // 🔍 BUSCADOR DE PACIENTES
    // =====================================================
    
    inputBuscar.addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        const termino = this.value.trim();

        if (termino.length < 2) {
            resultadosBusqueda.style.display = 'none';
            return;
        }

        timeoutBusqueda = setTimeout(() => {
            buscarPacientes(termino);
        }, 300);
    });

    function buscarPacientes(termino) {
        fetch(`../CONTROLADORES/controlador_historia_clinica.php?accion=buscar_paciente&termino=${encodeURIComponent(termino)}`)
            .then(res => res.json())
            .then(pacientes => {
                mostrarResultadosBusqueda(pacientes);
            })
            .catch(err => {
                console.error('Error al buscar pacientes:', err);
                resultadosBusqueda.innerHTML = '<div class="resultado-item">❌ Error al buscar</div>';
                resultadosBusqueda.style.display = 'block';
            });
    }

    function mostrarResultadosBusqueda(pacientes) {
        if (pacientes.length === 0) {
            resultadosBusqueda.innerHTML = '<div class="resultado-item">No se encontraron pacientes</div>';
            resultadosBusqueda.style.display = 'block';
            return;
        }

        let html = '';
        pacientes.forEach(p => {
            html += `
                <div class="resultado-item" data-id="${p.id_paciente}">
                    <strong>${p.nombre} ${p.apellido}</strong>
                    <small>DNI: ${p.dni} | Tel: ${p.telefono || 'N/A'}</small>
                </div>
            `;
        });

        resultadosBusqueda.innerHTML = html;
        resultadosBusqueda.style.display = 'block';

        // Agregar evento click a cada resultado
        document.querySelectorAll('.resultado-item[data-id]').forEach(item => {
            item.addEventListener('click', function() {
                const idPaciente = this.dataset.id;
                cargarHistoriaClinica(idPaciente);
                resultadosBusqueda.style.display = 'none';
                inputBuscar.value = '';
            });
        });
    }

    // Cerrar resultados al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!inputBuscar.contains(e.target) && !resultadosBusqueda.contains(e.target)) {
            resultadosBusqueda.style.display = 'none';
        }
    });

    // =====================================================
    // 📋 CARGAR HISTORIA CLÍNICA
    // =====================================================
    
    function cargarHistoriaClinica(idPaciente, pestanaInicial = 'citas')  {
        console.log('📋 Cargando historia clínica del paciente:', idPaciente);

        fetch(`../CONTROLADORES/controlador_historia_clinica.php?accion=cargar_historia&id_paciente=${idPaciente}`)
            .then(res => res.json())
            .then(data => {
                console.log('✅ Datos recibidos:', data);
                if (data.success) {
                    pacienteActual = data.paciente;
                    console.log('👤 Paciente actual guardado:', pacienteActual);
                    mostrarInformacionPaciente(data.paciente);
                    cargarAlergias(idPaciente);
                    mensajeSinPaciente.style.display = 'none';
                    contenidoHistoria.style.display = 'block';

                    // Activar pestaña inicial automáticamente
                    const pestanaTarget = document.querySelector(`.pestana[data-modulo="${pestanaInicial}"]`);
                    if (pestanaTarget) {
                        document.querySelectorAll('.pestana').forEach(p => p.classList.remove('activa'));
                        pestanaTarget.classList.add('activa');
                    }
                    cargarModulo(pestanaInicial);
                } else {
                    console.error('❌ Error en respuesta:', data.mensaje);
                    mostrarMensajeSistema('❌ ' + data.mensaje, 'error');
                }
            })
            .catch(err => {
                console.error('❌ Error al cargar historia clínica:', err);
                mostrarMensajeSistema('❌ Error al cargar la historia clínica', 'error');
            });
    }

    function mostrarInformacionPaciente(paciente) {
        console.log('📝 Mostrando información del paciente:', paciente);
        
        // Foto
        const rutaFoto = paciente.foto ? `../${paciente.foto}` : '../IMAGENES/perfiles_pacientes/default.png';
        document.getElementById('fotoPaciente').src = rutaFoto;

        // Datos básicos
        document.getElementById('nombrePaciente').textContent = `${paciente.nombre} ${paciente.apellido}`;
        document.getElementById('dniPaciente').textContent = paciente.dni || 'N/A';
        document.getElementById('fechaNacimientoPaciente').textContent = paciente.fecha_nacimiento ? formatearFecha(paciente.fecha_nacimiento) : 'N/A';
        document.getElementById('edadPaciente').textContent = paciente.fecha_nacimiento ? calcularEdad(paciente.fecha_nacimiento) : 'N/A';
        document.getElementById('sexoPaciente').textContent = paciente.nombre_sexo || 'N/A';
        document.getElementById('telefonoPaciente').textContent = paciente.telefono || 'N/A';
        document.getElementById('correoPaciente').textContent = paciente.correo || 'N/A';

        // Mostrar mensaje de cumpleaños
        const contenedorCumple = document.getElementById('contenedorMensajeCumpleanos');
        if (paciente.fecha_nacimiento && contenedorCumple) {
            const mensajeCumple = calcularDiasParaCumpleanos(paciente.fecha_nacimiento);
            console.log('🎂 Mensaje de cumpleaños:', mensajeCumple);
            contenedorCumple.innerHTML = mensajeCumple;
            contenedorCumple.style.display = mensajeCumple ? 'block' : 'none';
        } else {
            console.warn('⚠️ No se encontró el contenedor de cumpleaños o fecha de nacimiento');
        }

        // Información del apoderado
        const infoApoderado = document.getElementById('infoApoderado');
        if (paciente.apoderado) {
            document.getElementById('nombreApoderado').textContent = `${paciente.apoderado.nombre} ${paciente.apoderado.apellido}`;
            document.getElementById('telefonoApoderado').textContent = paciente.apoderado.telefono || 'N/A';
            document.getElementById('tipoFamiliarApoderado').textContent = paciente.apoderado.tipo_familiar || 'N/A';
            infoApoderado.style.display = 'block';
        } else {
            infoApoderado.style.display = 'none';
        }
    }
    // Función para generar el PDF
function generarPDFHistoriaClinica() {
    console.log('📄 Generando PDF de Historia Clínica');
    
    if (!pacienteActual) {
        mostrarMensajeSistema('❌ No hay paciente seleccionado', 'error');
        return;
    }

    // Deshabilitar botón mientras se genera
    btnGenerarPDF.disabled = true;
    btnGenerarPDF.classList.add('cargando');
    btnGenerarPDF.textContent = 'Generando PDF...';

    // Llamar al controlador que generará el PDF
    fetch(`../CONTROLADORES/controlador_generar_pdf.php?accion=generar_historia_clinica&id_paciente=${pacienteActual.id_paciente}`, {
        method: 'GET'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error al generar el PDF');
        }
        return response.blob();
    })
    .then(blob => {
        // Crear un enlace temporal para descargar el PDF
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Historia_Clinica_${pacienteActual.nombre}_${pacienteActual.apellido}_${new Date().toISOString().split('T')[0]}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        mostrarMensajeSistema('✅ PDF generado correctamente', 'exito');
    })
    .catch(err => {
        console.error('❌ Error al generar PDF:', err);
        mostrarMensajeSistema('❌ Error al generar el PDF. Por favor, intente nuevamente.', 'error');
    })
    .finally(() => {
        // Restaurar botón
        btnGenerarPDF.disabled = false;
        btnGenerarPDF.classList.remove('cargando');
        btnGenerarPDF.textContent = '📄 Generar PDF';
    });
}

    // =====================================================
    // ⚠️ GESTIÓN DE ALERGIAS
    // =====================================================
    
    function cargarAlergias(idPaciente) {
        console.log('💊 Cargando alergias del paciente:', idPaciente);
        fetch(`../CONTROLADORES/controlador_historia_clinica.php?accion=listar_alergias&id_paciente=${idPaciente}`)
            .then(res => res.json())
            .then(alergias => {
                console.log('✅ Alergias recibidas:', alergias);
                mostrarAlergias(alergias);
            })
            .catch(err => {
                console.error('❌ Error al cargar alergias:', err);
            });
    }

    function mostrarAlergias(alergias) {
        const listaAlergias = document.getElementById('listaAlergias');
        
        if (alergias.length === 0) {
            listaAlergias.innerHTML = '<span class="sin-datos">No se han registrado alergias</span>';
            return;
        }

        let html = '';
        alergias.forEach(alergia => {
            html += `
                <div class="alergia-tag">
                    <span class="icono-alergia">⚠️</span>
                    <span>${alergia.medicamento}</span>
                </div>
            `;
        });

        listaAlergias.innerHTML = html;
    }

    // Modal de alergias
    btnGestionarAlergias.addEventListener('click', abrirModalAlergias);
    btnCerrarModalAlergias.addEventListener('click', cerrarModalAlergias);
    btnCancelarAlergias.addEventListener('click', cerrarModalAlergias);
    btnGuardarAlergias.addEventListener('click', guardarAlergias);
    btnAgregarMedicamento.addEventListener('click', agregarMedicamentoTemporal);

    // 🔹 BÚSQUEDA DE MEDICAMENTOS EN TIEMPO REAL
    inputBuscarMedicamento.addEventListener('input', function() {
        clearTimeout(timeoutMedicamentos);
        const termino = this.value.trim();

        if (termino.length < 2) {
            resultadosMedicamentos.style.display = 'none';
            return;
        }

        timeoutMedicamentos = setTimeout(() => {
            buscarMedicamentos(termino);
        }, 300);
    });

    function buscarMedicamentos(termino) {
        console.log('🔍 Buscando medicamentos con término:', termino);
        fetch(`../CONTROLADORES/controlador_historia_clinica.php?accion=buscar_medicamentos&termino=${encodeURIComponent(termino)}`)
            .then(res => res.json())
            .then(medicamentos => {
                console.log('✅ Medicamentos encontrados:', medicamentos);
                mostrarResultadosMedicamentos(medicamentos, termino);
            })
            .catch(err => {
                console.error('❌ Error al buscar medicamentos:', err);
            });
    }

    function mostrarResultadosMedicamentos(medicamentos, termino) {
        if (medicamentos.length === 0) {
            resultadosMedicamentos.innerHTML = `
                <div class="resultado-medicamento nuevo" data-medicamento="${termino}">
                    <span>➕ Agregar "${termino}" (nuevo)</span>
                </div>
            `;
        } else {
            let html = '';
            medicamentos.forEach(med => {
                html += `
                    <div class="resultado-medicamento" data-medicamento="${med.medicamento}">
                        <span>${med.medicamento}</span>
                    </div>
                `;
            });
            
            // Agregar opción de crear nuevo si no está en la lista
            const existeExacto = medicamentos.some(m => m.medicamento.toLowerCase() === termino.toLowerCase());
            if (!existeExacto) {
                html += `
                    <div class="resultado-medicamento nuevo" data-medicamento="${termino}">
                        <span>➕ Agregar "${termino}" (nuevo)</span>
                    </div>
                `;
            }
            
            resultadosMedicamentos.innerHTML = html;
        }

        resultadosMedicamentos.style.display = 'block';

        // Agregar eventos a los resultados
        document.querySelectorAll('.resultado-medicamento').forEach(item => {
            item.addEventListener('click', function() {
                const medicamento = this.dataset.medicamento;
                agregarMedicamentoDesdeBusqueda(medicamento);
                inputBuscarMedicamento.value = '';
                resultadosMedicamentos.style.display = 'none';
            });
        });
    }

    function agregarMedicamentoDesdeBusqueda(medicamento) {
        console.log('➕ Agregando medicamento:', medicamento);
        
        // Verificar si ya existe
        const existe = alergiasTemporales.some(a => 
            a.medicamento.toLowerCase() === medicamento.toLowerCase()
        );

        if (existe) {
            console.warn('⚠️ Medicamento ya existe en la lista');
            mostrarMensajeSistema('⚠️ Este medicamento ya está en la lista', 'error');
            return;
        }

        alergiasTemporales.push({
            id_alergia_medicamentos: null,
            medicamento: medicamento,
            nuevo: true
        });

        console.log('✅ Medicamento agregado. Lista actual:', alergiasTemporales);
        renderizarAlergiasModal();
        mostrarMensajeSistema('✅ Medicamento agregado', 'exito');
    }

    // Cerrar resultados de medicamentos al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!inputBuscarMedicamento.contains(e.target) && !resultadosMedicamentos.contains(e.target)) {
            resultadosMedicamentos.style.display = 'none';
        }
    });

    function abrirModalAlergias() {
        console.log('🔓 Intentando abrir modal de alergias');
        console.log('👤 Paciente actual:', pacienteActual);
        
        if (!pacienteActual) {
            console.error('❌ No hay paciente seleccionado');
            mostrarMensajeSistema('❌ No hay paciente seleccionado', 'error');
            return;
        }

        console.log('✅ Paciente encontrado, ID:', pacienteActual.id_paciente);

        // Cargar alergias actuales
        fetch(`../CONTROLADORES/controlador_historia_clinica.php?accion=listar_alergias&id_paciente=${pacienteActual.id_paciente}`)
            .then(res => res.json())
            .then(alergias => {
                console.log('✅ Alergias cargadas para modal:', alergias);
                alergiasTemporales = [...alergias];
                renderizarAlergiasModal();
                modalAlergias.style.display = 'flex';
                modalAlergias.style.justifyContent = 'center';
                modalAlergias.style.alignItems = 'center';
                setTimeout(() => modalAlergias.classList.add('mostrar'), 10);
                document.body.style.overflow = 'hidden';
                console.log('✅ Modal abierto correctamente');
            })
            .catch(err => {
                console.error('❌ Error al cargar alergias para modal:', err);
            });
    }

    function cerrarModalAlergias() {
        console.log('🔒 Cerrando modal de alergias');
        modalAlergias.classList.remove('mostrar');
        document.body.style.overflow = '';
        setTimeout(() => {
            modalAlergias.style.display = 'none';
            inputBuscarMedicamento.value = '';
            resultadosMedicamentos.style.display = 'none';
        }, 300);
    }

    function agregarMedicamentoTemporal() {
        const medicamento = inputBuscarMedicamento.value.trim();
        console.log('➕ Intentando agregar medicamento manualmente:', medicamento);
        
        if (!medicamento) {
            console.warn('⚠️ Campo vacío');
            mostrarMensajeSistema('❌ Ingrese el nombre del medicamento', 'error');
            return;
        }

        // Verificar si ya existe
        const existe = alergiasTemporales.some(a => 
            a.medicamento.toLowerCase() === medicamento.toLowerCase()
        );

        if (existe) {
            console.warn('⚠️ Medicamento duplicado');
            mostrarMensajeSistema('⚠️ Este medicamento ya está en la lista', 'error');
            return;
        }

        alergiasTemporales.push({
            id_alergia_medicamentos: null,
            medicamento: medicamento,
            nuevo: true
        });

        console.log('✅ Medicamento agregado manualmente. Lista:', alergiasTemporales);
        inputBuscarMedicamento.value = '';
        resultadosMedicamentos.style.display = 'none';
        renderizarAlergiasModal();
        mostrarMensajeSistema('✅ Medicamento agregado', 'exito');
    }

    function eliminarAlergiaTemporal(index) {
        console.log('🗑️ Eliminando medicamento en índice:', index);
        alergiasTemporales.splice(index, 1);
        console.log('✅ Lista actualizada:', alergiasTemporales);
        renderizarAlergiasModal();
    }

    function renderizarAlergiasModal() {
        console.log('🎨 Renderizando lista de alergias en modal:', alergiasTemporales);
        
        if (alergiasTemporales.length === 0) {
            listaAlergiasModal.innerHTML = '<p class="sin-datos">No hay alergias registradas</p>';
            return;
        }

        let html = '';
        alergiasTemporales.forEach((alergia, index) => {
            html += `
                <div class="alergia-item-modal">
                    <span>⚠️ ${alergia.medicamento}</span>
                    <button class="btn-eliminar-alergia" data-index="${index}">
                        🗑️ Eliminar
                    </button>
                </div>
            `;
        });

        listaAlergiasModal.innerHTML = html;

        // Agregar eventos a botones de eliminar
        document.querySelectorAll('.btn-eliminar-alergia').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                eliminarAlergiaTemporal(index);
            });
        });
    }

    function guardarAlergias() {
        console.log('💾 Guardando alergias');
        
        if (!pacienteActual) {
            console.error('❌ No hay paciente al guardar');
            return;
        }

        const datos = {
            id_paciente: pacienteActual.id_paciente,
            alergias: alergiasTemporales.map(a => a.medicamento)
        };

        console.log('📤 Datos a enviar:', datos);

        fetch('../CONTROLADORES/controlador_historia_clinica.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                accion: 'guardar_alergias',
                ...datos
            })
        })
        .then(res => res.json())
        .then(data => {
            console.log('✅ Respuesta del servidor:', data);
            if (data.success) {
                mostrarMensajeSistema('✅ Alergias actualizadas correctamente', 'exito');
                cargarAlergias(pacienteActual.id_paciente);
                cerrarModalAlergias();
            } else {
                console.error('❌ Error del servidor:', data.mensaje);
                mostrarMensajeSistema('❌ ' + data.mensaje, 'error');
            }
        })
        .catch(err => {
            console.error('❌ Error al guardar alergias:', err);
            mostrarMensajeSistema('❌ Error al guardar las alergias', 'error');
        });
    }

    // =====================================================
    // 📑 GESTIÓN DE PESTAÑAS
    // =====================================================
    
    document.querySelectorAll('.pestana').forEach(pestana => {
        pestana.addEventListener('click', function() {
            if (!pacienteActual) {
                mostrarMensajeSistema('❌ No hay paciente seleccionado', 'error');
                return;
            }

            // Remover clase activa de todas las pestañas
            document.querySelectorAll('.pestana').forEach(p => p.classList.remove('activa'));
            this.classList.add('activa');

            const modulo = this.dataset.modulo;
            cargarModulo(modulo);
        });
    });

    function cargarModulo(modulo) {
        const contenedor = document.getElementById('contenedorModulo');
        contenedor.innerHTML = '<p class="mensaje-seleccionar">Cargando...</p>';
    
        switch(modulo) {
            case 'citas':
                cargarModuloCitas();
                break;
    
            case 'atenciones':
                cargarModuloAtenciones();
                break;
    
            case 'documentos':
                cargarModuloDocumentos();
                break;
    
                case 'odontograma': {
                    const edadPacOdonto = pacienteActual.fecha_nacimiento
                        ? calcularEdad(pacienteActual.fecha_nacimiento)
                        : 99;
                    window.EDAD_PACIENTE_ODONTO = edadPacOdonto;
                
                    fetch(`../VISTAS/vista_modulo_odontograma.php?id_paciente=${pacienteActual.id_paciente}`)
                        .then(res => res.text())
                        .then(html => {
                            contenedor.innerHTML = html;
                
                            // Ejecutar todos los scripts inline del HTML cargado
                            const scripts = contenedor.querySelectorAll('script');
                            scripts.forEach(oldScript => {
                                const nuevoScript = document.createElement('script');
                                if (oldScript.src) {
                                    // Script externo — ignorar, lo cargamos aparte
                                } else {
                                    // Script inline — ejecutar su contenido
                                    nuevoScript.textContent = oldScript.textContent;
                                    document.body.appendChild(nuevoScript);
                                }
                            });
                
                            // Cargar script_odontograma.js fresco
                            const oldScript = document.querySelector('script[src="../SCRIPTS/script_odontograma.js"]');
                            if (oldScript) oldScript.remove();
                
                            const s = document.createElement('script');
                            s.src = '../SCRIPTS/script_odontograma.js';
                            s.onload = () => {
                                if (typeof iniciarModuloOdontograma === 'function') {
                                    iniciarModuloOdontograma();
                                } else {
                                    console.error('iniciarModuloOdontograma no encontrada');
                                }
                            };
                            document.body.appendChild(s);
                        })
                        .catch(err => {
                            console.error('Error cargando odontograma:', err);
                            contenedor.innerHTML = '<p style="text-align:center;padding:30px;color:#e74c3c;">Error al cargar el odontograma.</p>';
                        });
                    break;
                }
    
            default:
                contenedor.innerHTML = '<p class="mensaje-seleccionar">Módulo no disponible.</p>';
        }
    }
    // =====================================================
// 💊 CARGAR MÓDULO DE ATENCIONES
// =====================================================
function cargarModuloAtenciones() {
    console.log('💊 Cargando módulo de atenciones para paciente:', pacienteActual.id_paciente);
    
    const contenedor = document.getElementById('contenedorModulo');
    
    // Cargar la vista del módulo de atenciones
    fetch(`../VISTAS/vista_modulo_atenciones_paciente.php?id_paciente=${pacienteActual.id_paciente}`)
        .then(res => res.text())
        .then(html => {
            contenedor.innerHTML = html;
            
            // Ejecutar el script del módulo si existe
            const scripts = contenedor.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                document.body.appendChild(newScript);
            });
            
            console.log('✅ Módulo de atenciones cargado correctamente');
        })
        .catch(err => {
            console.error('❌ Error al cargar módulo de atenciones:', err);
            contenedor.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #f44336;">
                    <h3>❌ Error al cargar el módulo</h3>
                    <p>No se pudo cargar el módulo de atenciones. Por favor, intente nuevamente.</p>
                </div>
            `;
        });
}
    // =====================================================
// 📅 CARGAR MÓDULO DE CITAS
// =====================================================
function cargarModuloCitas() {
    console.log('📅 Cargando módulo de citas para paciente:', pacienteActual.id_paciente);
    
    const contenedor = document.getElementById('contenedorModulo');
    
    // Cargar la vista del módulo de citas
    fetch(`../VISTAS/vista_modulo_citas_paciente.php?id_paciente=${pacienteActual.id_paciente}`)
        .then(res => res.text())
        .then(html => {
            contenedor.innerHTML = html;
            
            // Ejecutar el script del módulo si existe
            const scripts = contenedor.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                document.body.appendChild(newScript);
            });
            
            console.log('✅ Módulo de citas cargado correctamente');
        })
        .catch(err => {
            console.error('❌ Error al cargar módulo de citas:', err);
            contenedor.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #f44336;">
                    <h3>❌ Error al cargar el módulo</h3>
                    <p>No se pudo cargar el módulo de citas. Por favor, intente nuevamente.</p>
                </div>
            `;
        });
}
// =====================================================
    // 📄 CARGAR MÓDULO DE DOCUMENTOS - 👈 NUEVA FUNCIÓN
    // =====================================================
    function cargarModuloDocumentos() {
        console.log('📄 Cargando módulo de documentos para paciente:', pacienteActual?.id_paciente);
        
        const contenedor = document.getElementById('contenedorModulo');
        
        if (!pacienteActual) {
            console.error('❌ No hay paciente seleccionado');
            contenedor.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #f44336;">
                    <h3>❌ Error</h3>
                    <p>No hay paciente seleccionado. Por favor, seleccione un paciente primero.</p>
                </div>
            `;
            return;
        }
        
        // Cargar la vista del módulo de documentos
        fetch(`../VISTAS/vista_modulo_documentos.php?id_paciente=${pacienteActual.id_paciente}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.text();
            })
            .then(html => {
                contenedor.innerHTML = html;
                
                // Ejecutar el script del módulo si existe
                const scripts = contenedor.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.body.appendChild(newScript);
                });
                
                console.log('✅ Módulo de documentos cargado correctamente');
            })
            .catch(err => {
                console.error('❌ Error al cargar módulo de documentos:', err);
                contenedor.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #f44336;">
                        <h3>❌ Error al cargar el módulo</h3>
                        <p>No se pudo cargar el módulo de documentos. Por favor, intente nuevamente.</p>
                        <p style="font-size: 12px; color: #95a5a6;">Error: ${err.message}</p>
                        <button onclick="location.reload()" style="margin-top: 15px; padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            🔄 Recargar página
                        </button>
                    </div>
                `;
            });
    }
    // =====================================================
    // 🔙 VOLVER A MÓDULO DE PACIENTES
    // =====================================================
    
    btnVolverPacientes.addEventListener('click', function() {
        if (typeof window.navegarAModulo === 'function') {
            window.navegarAModulo('pacientes');
        } else {
            window.location.href = '../VISTAS/vista_pacientes.php';
        }
    });

    // =====================================================
    // 🛠️ FUNCIONES AUXILIARES
    // =====================================================
    
    function formatearFecha(fecha) {
        if (!fecha) return 'N/A';
        const f = new Date(fecha + 'T00:00:00');
        const opciones = { year: 'numeric', month: 'long', day: 'numeric' };
        return f.toLocaleDateString('es-ES', opciones);
    }

    function calcularEdad(fechaNacimiento) {
        if (!fechaNacimiento) return 'N/A';
        
        const hoy = new Date();
        const nacimiento = new Date(fechaNacimiento + 'T00:00:00');
        let edad = hoy.getFullYear() - nacimiento.getFullYear();
        const mes = hoy.getMonth() - nacimiento.getMonth();
        
        if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
            edad--;
        }
        
        return `${edad} años`;
    }

    function calcularDiasParaCumpleanos(fechaNacimiento) {
        if (!fechaNacimiento) {
            console.log('⚠️ No hay fecha de nacimiento');
            return '';
        }
        
        console.log('📅 Calculando días para cumpleaños. Fecha nacimiento:', fechaNacimiento);
        
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        console.log('📅 Fecha de hoy:', hoy.toISOString().split('T')[0]);
        
        const nacimiento = new Date(fechaNacimiento + 'T00:00:00');
        console.log('📅 Fecha de nacimiento parseada:', nacimiento.toISOString().split('T')[0]);
        console.log('📅 Mes de nacimiento:', nacimiento.getMonth() + 1, 'Día:', nacimiento.getDate());
        
        // Obtener el cumpleaños de este año
        let cumpleañosEsteAño = new Date(hoy.getFullYear(), nacimiento.getMonth(), nacimiento.getDate());
        cumpleañosEsteAño.setHours(0, 0, 0, 0);
        console.log('📅 Cumpleaños este año:', cumpleañosEsteAño.toISOString().split('T')[0]);
        
        // Si ya pasó el cumpleaños este año, calcular para el próximo año
        if (cumpleañosEsteAño < hoy) {
            console.log('⏭️ El cumpleaños ya pasó este año, calculando para el próximo');
            cumpleañosEsteAño = new Date(hoy.getFullYear() + 1, nacimiento.getMonth(), nacimiento.getDate());
            cumpleañosEsteAño.setHours(0, 0, 0, 0);
            console.log('📅 Cumpleaños próximo año:', cumpleañosEsteAño.toISOString().split('T')[0]);
        }
        
        // Calcular diferencia en días
        const diferenciaTiempo = cumpleañosEsteAño - hoy;
        const diferenciaDias = Math.floor(diferenciaTiempo / (1000 * 60 * 60 * 24));
        console.log('🔢 Diferencia en días:', diferenciaDias);
        
        let mensaje = '';
        let icono = '🎂';
        
        if (diferenciaDias === 0) {
            mensaje = '¡Hoy es su cumpleaños!';
            icono = '🎉';
            console.log('🎉 HOY ES SU CUMPLEAÑOS!');
        } else if (diferenciaDias === 1) {
            mensaje = 'Mañana es su cumpleaños';
            icono = '🎁';
            console.log('🎁 Mañana es su cumpleaños');
        } else if (diferenciaDias > 1 && diferenciaDias <= 7) {
            mensaje = `Su cumpleaños es en ${diferenciaDias} días`;
            icono = '🎈';
            console.log(`🎈 Cumpleaños en ${diferenciaDias} días`);
        } else if (diferenciaDias > 7 && diferenciaDias <= 30) {
            mensaje = `Cumpleaños en ${diferenciaDias} días`;
            icono = '🎂';
            console.log(`🎂 Cumpleaños en ${diferenciaDias} días`);
        } else {
            // MOSTRAR SIEMPRE, sin importar cuántos días falten
            const meses = Math.floor(diferenciaDias / 30);
            if (meses > 0) {
                mensaje = `Cumpleaños en ${meses} ${meses === 1 ? 'mes' : 'meses'}`;
            } else {
                mensaje = `Cumpleaños en ${diferenciaDias} días`;
            }
            icono = '📅';
            console.log(`📅 Cumpleaños en ${diferenciaDias} días (${meses} meses)`);
        }
        
        const resultado = `<span class="mensaje-cumpleanos">${icono} ${mensaje}</span>`;
        console.log('✅ Mensaje generado:', resultado);
        return resultado;
    }

    function mostrarMensajeSistema(mensaje, tipo = 'exito') {
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        const icono = tipo === 'exito' ? '✅' : '❌';
        aviso.innerHTML = `
            <span class="icono">${icono}</span>
            <span class="texto">${mensaje}</span>
        `;
        aviso.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${tipo === 'exito' ? '#27ae60' : '#e74c3c'};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.4s ease;
        `;
        
        document.body.appendChild(aviso);
        
        setTimeout(() => {
            aviso.style.opacity = '1';
            aviso.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            aviso.style.opacity = '0';
            aviso.style.transform = 'translateX(100%)';
            setTimeout(() => aviso.remove(), 400);
        }, 3000);
    }
}

// Iniciar módulo automáticamente
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarModuloHistoriaClinica);
} else {
    iniciarModuloHistoriaClinica();
}