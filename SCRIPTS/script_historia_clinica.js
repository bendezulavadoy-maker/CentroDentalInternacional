let pacienteActual = null; // 👈 MOVER ESTA DECLARACIÓN AQUÍ (FUERA DE LA FUNCIÓN)
let historiaActual = null;
let seccionesActuales = {};

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
                    historiaActual = data.historia;
                    console.log('👤 Paciente actual guardado:', pacienteActual);
                    mostrarInformacionPaciente(data.paciente);
                    cargarAlergias(idPaciente);
                    renderizarChecklistHistoria(data.secciones, data.historia);
                    mensajeSinPaciente.style.display = 'none';
                    contenidoHistoria.style.display = 'block';

                    // Actualizar breadcrumb del header general con el nombre del paciente
                    if (typeof window.actualizarBreadcrumb === 'function' && document.querySelector('aside a[data-vista="historia_clinica"].activo')) {
                        window.actualizarBreadcrumb(['Historias Clínicas', `${data.paciente.nombre} ${data.paciente.apellido}`]);
                    }

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
    function mostrarInfoModificacion(idModal, datos) {
        const cuerpo = document.querySelector(`#${idModal} .modal-cuerpo`);
        if (!cuerpo) return;
        let info = cuerpo.querySelector('.info-modificado');
        if (!info) {
            info = document.createElement('p');
            info.className = 'info-modificado';
            cuerpo.insertBefore(info, cuerpo.firstChild);
        }
        info.textContent = datos && datos.modificado_por_nombre
            ? `Última modificación: ${datos.modificado_por_nombre}`
            : '';
    }
    function mostrarInformacionPaciente(paciente) {
        console.log('📝 Mostrando información del paciente:', paciente);

        // Foto
        const rutaFoto = paciente.foto ? `../${paciente.foto}` : '../IMAGENES/perfiles_pacientes/default.png';
        document.getElementById('fotoPaciente').src = rutaFoto;

        // Datos básicos
        document.getElementById('nombrePaciente').textContent = `${paciente.nombre} ${paciente.apellido}`;
        document.getElementById('dniPaciente').textContent = paciente.dni || 'N/A';
        const edad = paciente.fecha_nacimiento ? calcularEdad(paciente.fecha_nacimiento) : 'N/A';
        const sexo = paciente.nombre_sexo || '';
        document.getElementById('sexoEdadPaciente').textContent = sexo ? `${sexo}, ${edad}` : edad;
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
        const btnAlergias = document.getElementById('btnGestionarAlergias');
        if (!btnAlergias) return;

        if (alergias.length === 0) {
            btnAlergias.textContent = 'Sin alergias';
            btnAlergias.classList.remove('con-alergias');
            btnAlergias.title = '';
            return;
        }

        const nombres = alergias.map(a => a.medicamento).join(', ');
        btnAlergias.textContent = `⚠️ Ver alergias (${alergias.length})`;
        btnAlergias.classList.add('con-alergias');
        btnAlergias.title = nombres;
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
    // 🩺 CHECKLIST DE HISTORIA CLÍNICA (sidebar) — abre modales
    // =====================================================

    const definicionSecciones = {
        motivo_consulta:   { modal: 'modalMotivoConsulta' },
        antecedentes_personales: { modal: 'modalAntecedentesPersonales' },
        antecedentes_familiares: { modal: 'modalAntecedentesFamiliares' },
        examen_general:     { modal: 'modalExamenGeneral' },
        examen_extraoral:   { modal: 'modalExamenExtraoral' },
        examen_intraoral:   { modal: 'modalExamenIntraoral' }
    };

    function renderizarChecklistHistoria(secciones, historia) {
        seccionesActuales = secciones || {};
        const motivoCompleto = !!(historia && historia.motivo_consulta && historia.motivo_consulta.trim() !== '');

        Object.keys(definicionSecciones).forEach(clave => {
            const item = document.querySelector(`.check-item-sidebar[data-seccion="${clave}"]`);
            if (!item) return;
            const icono = item.querySelector('.check-icono');
            const completo = clave === 'motivo_consulta'
                ? motivoCompleto
                : !!(seccionesActuales[clave] && seccionesActuales[clave]._completo);

            icono.textContent = completo ? '✓' : '–';
            item.classList.toggle('completo', completo);
        });
    }

    // Prellenar los campos del modal con lo que ya está guardado
    function prellenarSeccion(seccion) {
        if (seccion === 'motivo_consulta') {
            document.getElementById('txtMotivoConsulta').value = (historiaActual && historiaActual.motivo_consulta) || '';
        } else if (seccion === 'examen_general') {
            const d = seccionesActuales.examen_general || {};
            document.getElementById('inpTalla').value = d.talla_mts || '';
            document.getElementById('inpPeso').value = d.peso_kg || '';
            document.getElementById('inpTemperatura').value = d.temperatura || '';
            document.getElementById('inpSaturacion').value = d.saturacion || '';
            mostrarInfoModificacion('modalExamenGeneral', d);  
        } else if (seccion === 'antecedentes_personales') {
            const dOld = seccionesActuales.antecedentes || {};
            document.getElementById('txtAntecedentesMedica').value = dOld.medica || '';
            document.getElementById('txtAntecedentesOdontologicos').value = dOld.odontologicos || '';
            const d = seccionesActuales.antecedentes_personales || {};
            document.querySelectorAll('#modalAntecedentesPersonales input[type="radio"]').forEach(r => r.checked = false);
            marcarRadio('fuma', d.fuma);
            marcarRadio('alcohol', d.alcohol);
            marcarRadio('sustancias_psicoactivas', d.sustancias_psicoactivas);
            marcarRadio('medicamentos_estimulantes', d.medicamentos_estimulantes);
            marcarRadio('bruxismo', d.bruxismo);
            marcarRadio('respiracion_bucal', d.respiracion_bucal);
            marcarRadio('embarazo', d.embarazo);
            marcarRadio('lactancia', d.lactancia);
            marcarRadio('trastornos_coagulacion', d.trastornos_coagulacion);
            document.getElementById('inpFumaCantidad').value = d.fuma_cantidad || '';
            document.getElementById('inpFumaFrecuencia').value = d.fuma_frecuencia || '';
            document.getElementById('inpAlcoholCantidad').value = d.alcohol_cantidad || '';
            document.getElementById('inpAlcoholFrecuencia').value = d.alcohol_frecuencia || '';
            document.getElementById('inpSustanciasEspecifique').value = d.sustancias_especifique || '';
            document.getElementById('inpSustanciasFrecuencia').value = d.sustancias_frecuencia || '';
            document.getElementById('inpSustanciasUltimoConsumo').value = d.sustancias_ultimo_consumo || '';
            document.getElementById('inpMedicamentosEspecifique').value = d.medicamentos_especifique || '';
            document.getElementById('inpMedicamentosFrecuencia').value = d.medicamentos_frecuencia || '';
            document.getElementById('inpMedicamentosUltimoConsumo').value = d.medicamentos_ultimo_consumo || '';
            document.getElementById('txtHospitalizacionesPrevias').value = d.hospitalizaciones_previas || '';
            document.getElementById('txtCirugias').value = d.cirugias || '';
            document.getElementById('txtMedicamentosActuales').value = d.medicamentos_actuales || '';
            document.getElementById('txtDiagnostico').value = d.diagnostico || '';
            mostrarInfoModificacion('modalAntecedentesPersonales', d); 
        } else if (seccion === 'antecedentes_familiares') {
            const d = seccionesActuales.antecedentes_familiares || {};
            document.getElementById('chkHipertensionArterial').checked = !!Number(d.hipertension_arterial);
            document.getElementById('chkDiabetes').checked = !!Number(d.diabetes);
            document.getElementById('chkEnfermedadCardiaca').checked = !!Number(d.enfermedad_cardiaca);
            document.getElementById('chkAsma').checked = !!Number(d.asma);
            document.getElementById('chkEpilepsia').checked = !!Number(d.epilepsia);
            document.getElementById('chkHepatitis').checked = !!Number(d.hepatitis);
            document.getElementById('chkVih').checked = !!Number(d.vih);
            document.getElementById('chkTuberculosis').checked = !!Number(d.tuberculosis);
            document.getElementById('chkEnfermedadRenal').checked = !!Number(d.enfermedad_renal);
            document.getElementById('chkEnfermedadHepatica').checked = !!Number(d.enfermedad_hepatica);
            document.getElementById('inpFamiliaresOtro').value = d.otros || '';
            mostrarInfoModificacion('modalAntecedentesFamiliares', d);
        } else if (seccion === 'examen_extraoral') {
            const d = seccionesActuales.examen_extraoral || {};
            document.querySelectorAll('#modalExamenExtraoral input[type="radio"]').forEach(r => r.checked = false);
            marcarRadio('simetria', d.simetria);
            marcarRadio('musculatura', d.musculatura);
            marcarRadio('perfil_antero_posterior', d.perfil_antero_posterior);
            marcarRadio('perfil_vertical', d.perfil_vertical);
            marcarRadio('fonacion', d.fonacion);
            marcarRadio('deglucion', d.deglucion);
            marcarRadio('respiracion', d.respiracion);
            marcarRadio('habitos', d.habitos);
            document.getElementById('inpDeglucionTipo').value = d.deglucion_tipo || '';
            mostrarInfoModificacion('modalExamenExtraoral', d); 
        } else if (seccion === 'examen_intraoral') {
            const d = seccionesActuales.examen_intraoral || {};
            document.getElementById('txtLabios').value = d.labios || '';
            document.getElementById('txtVestibulo').value = d.vestibulo || '';
            document.getElementById('txtFrenillos').value = d.frenillos || '';
            document.getElementById('txtPaladar').value = d.paladar || '';
            document.getElementById('txtOrofaringe').value = d.orofaringe || '';
            document.getElementById('txtLengua').value = d.lengua || '';
            document.getElementById('txtPisoBoca').value = d.piso_boca || '';
            mostrarInfoModificacion('modalExamenIntraoral', d);   
        }
    }

    function marcarRadio(nombre, valor) {
        if (!valor) return;
        const radio = document.querySelector(`input[name="${nombre}"][value="${valor}"]`);
        if (radio) radio.checked = true;
    }

    function leerRadio(nombre) {
        const radio = document.querySelector(`input[name="${nombre}"]:checked`);
        return radio ? radio.value : '';
    }

    function abrirModalPorId(idModal) {
        const modal = document.getElementById(idModal);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
        setTimeout(() => modal.classList.add('mostrar'), 10);
    }

    function cerrarModalPorId(idModal) {
        const modal = document.getElementById(idModal);
        if (!modal) return;
        modal.classList.remove('mostrar');
        setTimeout(() => { modal.style.display = 'none'; }, 200);
    }

    document.querySelectorAll('[data-cerrar-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            cerrarModalPorId(this.dataset.cerrarModal);
        });
    });

    // Clic en cada ítem del checklist (Filiación no abre nada, ya está completa)
    document.querySelectorAll('.check-item-sidebar:not(.no-clic)').forEach(item => {
        item.addEventListener('click', function() {
            if (!pacienteActual || !historiaActual) {
                mostrarMensajeSistema('❌ Seleccione un paciente primero', 'error');
                return;
            }
            const seccion = this.dataset.seccion;
            const def = definicionSecciones[seccion];
            if (!def) return;
            prellenarSeccion(seccion);
            abrirModalPorId(def.modal);
        });
    });

    function guardarSeccionHistoria(accion, payload, claveSeccion) {
        fetch('../CONTROLADORES/controlador_historia_clinica.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion, ...payload })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                mostrarMensajeSistema('✅ ' + data.mensaje, 'exito');
                const def = definicionSecciones[claveSeccion];
                if (def) cerrarModalPorId(def.modal);
                refrescarChecklistHistoria();
            } else {
                mostrarMensajeSistema('❌ ' + data.mensaje, 'error');
            }
        })
        .catch(err => {
            console.error('❌ Error al guardar sección:', err);
            mostrarMensajeSistema('❌ Error al guardar. Intente nuevamente', 'error');
        });
    }

    function refrescarChecklistHistoria() {
        if (!historiaActual) return;
        fetch(`../CONTROLADORES/controlador_historia_clinica.php?accion=cargar_secciones&id_historia=${historiaActual.id_historia}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) renderizarChecklistHistoria(data.secciones, historiaActual);
            })
            .catch(err => console.error('❌ Error al refrescar checklist:', err));
    }

    document.getElementById('btnGuardarMotivoConsulta').addEventListener('click', function() {
        const motivo = document.getElementById('txtMotivoConsulta').value.trim();
        historiaActual.motivo_consulta = motivo;
        guardarSeccionHistoria('guardar_motivo_consulta', {
            id_historia: historiaActual.id_historia,
            motivo_consulta: motivo
        }, 'motivo_consulta');
    });

    document.getElementById('btnGuardarExamenGeneral').addEventListener('click', function() {
        guardarSeccionHistoria('guardar_examen_general', {
            id_historia: historiaActual.id_historia,
            talla_mts: document.getElementById('inpTalla').value.trim(),
            peso_kg: document.getElementById('inpPeso').value.trim(),
            temperatura: document.getElementById('inpTemperatura').value.trim(),
            saturacion: document.getElementById('inpSaturacion').value.trim()
        }, 'examen_general');
    });

    document.getElementById('btnGuardarAntecedentesPersonales').addEventListener('click', function() {
        guardarSeccionHistoria('guardar_antecedentes_personales', {
            id_historia: historiaActual.id_historia,
            medica: document.getElementById('txtAntecedentesMedica').value.trim(),
            odontologicos: document.getElementById('txtAntecedentesOdontologicos').value.trim(),
            fuma: leerRadio('fuma'),
            fuma_cantidad: document.getElementById('inpFumaCantidad').value.trim(),
            fuma_frecuencia: document.getElementById('inpFumaFrecuencia').value.trim(),
            alcohol: leerRadio('alcohol'),
            alcohol_cantidad: document.getElementById('inpAlcoholCantidad').value.trim(),
            alcohol_frecuencia: document.getElementById('inpAlcoholFrecuencia').value.trim(),
            sustancias_psicoactivas: leerRadio('sustancias_psicoactivas'),
            sustancias_especifique: document.getElementById('inpSustanciasEspecifique').value.trim(),
            sustancias_frecuencia: document.getElementById('inpSustanciasFrecuencia').value.trim(),
            sustancias_ultimo_consumo: document.getElementById('inpSustanciasUltimoConsumo').value.trim(),
            medicamentos_estimulantes: leerRadio('medicamentos_estimulantes'),
            medicamentos_especifique: document.getElementById('inpMedicamentosEspecifique').value.trim(),
            medicamentos_frecuencia: document.getElementById('inpMedicamentosFrecuencia').value.trim(),
            medicamentos_ultimo_consumo: document.getElementById('inpMedicamentosUltimoConsumo').value.trim(),
            bruxismo: leerRadio('bruxismo'),
            respiracion_bucal: leerRadio('respiracion_bucal'),
            embarazo: leerRadio('embarazo'),
            lactancia: leerRadio('lactancia'),
            trastornos_coagulacion: leerRadio('trastornos_coagulacion'),
            hospitalizaciones_previas: document.getElementById('txtHospitalizacionesPrevias').value.trim(),
            cirugias: document.getElementById('txtCirugias').value.trim(),
            medicamentos_actuales: document.getElementById('txtMedicamentosActuales').value.trim(),
            diagnostico: document.getElementById('txtDiagnostico').value.trim()
        }, 'antecedentes_personales');
    });

    document.getElementById('btnGuardarAntecedentesFamiliares').addEventListener('click', function() {
        guardarSeccionHistoria('guardar_antecedentes_familiares', {
            id_historia: historiaActual.id_historia,
            hipertension_arterial: document.getElementById('chkHipertensionArterial').checked ? 1 : 0,
            diabetes: document.getElementById('chkDiabetes').checked ? 1 : 0,
            enfermedad_cardiaca: document.getElementById('chkEnfermedadCardiaca').checked ? 1 : 0,
            asma: document.getElementById('chkAsma').checked ? 1 : 0,
            epilepsia: document.getElementById('chkEpilepsia').checked ? 1 : 0,
            hepatitis: document.getElementById('chkHepatitis').checked ? 1 : 0,
            vih: document.getElementById('chkVih').checked ? 1 : 0,
            tuberculosis: document.getElementById('chkTuberculosis').checked ? 1 : 0,
            enfermedad_renal: document.getElementById('chkEnfermedadRenal').checked ? 1 : 0,
            enfermedad_hepatica: document.getElementById('chkEnfermedadHepatica').checked ? 1 : 0,
            otros: document.getElementById('inpFamiliaresOtro').value.trim()
        }, 'antecedentes_familiares');
    });

    document.getElementById('btnGuardarExamenExtraoral').addEventListener('click', function() {
        guardarSeccionHistoria('guardar_examen_extraoral', {
            id_historia: historiaActual.id_historia,
            simetria: leerRadio('simetria'),
            musculatura: leerRadio('musculatura'),
            perfil_antero_posterior: leerRadio('perfil_antero_posterior'),
            perfil_vertical: leerRadio('perfil_vertical'),
            fonacion: leerRadio('fonacion'),
            deglucion: leerRadio('deglucion'),
            deglucion_tipo: document.getElementById('inpDeglucionTipo').value.trim(),
            respiracion: leerRadio('respiracion'),
            habitos: leerRadio('habitos')
        }, 'examen_extraoral');
    });

    document.getElementById('btnGuardarExamenIntraoral').addEventListener('click', function() {
        guardarSeccionHistoria('guardar_examen_intraoral', {
            id_historia: historiaActual.id_historia,
            labios: document.getElementById('txtLabios').value.trim(),
            vestibulo: document.getElementById('txtVestibulo').value.trim(),
            frenillos: document.getElementById('txtFrenillos').value.trim(),
            paladar: document.getElementById('txtPaladar').value.trim(),
            orofaringe: document.getElementById('txtOrofaringe').value.trim(),
            lengua: document.getElementById('txtLengua').value.trim(),
            piso_boca: document.getElementById('txtPisoBoca').value.trim()
        }, 'examen_intraoral');
    });

    // =====================================================
    // ↔️ COLAPSAR/EXPANDIR EL SIDEBAR DEL PACIENTE (más espacio)
    // =====================================================
    const btnToggleSidebarPaciente = document.getElementById('btnToggleSidebarPaciente');
    const sidebarPaciente = document.getElementById('sidebarPaciente');
    if (btnToggleSidebarPaciente && sidebarPaciente) {
        btnToggleSidebarPaciente.addEventListener('click', function() {
            const colapsado = sidebarPaciente.classList.toggle('colapsado');
            btnToggleSidebarPaciente.querySelector('.icono-toggle').textContent = colapsado ? '›' : '‹';
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