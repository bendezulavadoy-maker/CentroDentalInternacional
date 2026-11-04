function iniciarModuloPacientes() {
    window._moduloPacientesIniciado = false; // Siempre reiniciar al entrar al módulo

    try {

        // Escapa texto antes de insertarlo como HTML: un dato con etiquetas (por ejemplo una
        // direccion escrita por un paciente desde el portal) se muestra como texto, no se ejecuta.
        const esc = valor => String(valor ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        const tablaBody = document.querySelector('#tablaPacientes tbody');
        const seccionListado = document.getElementById('seccionListadoPersonal');
        const seccionNuevo = document.getElementById('seccionNuevoPaciente');
        const seccionDetalle = document.getElementById('seccionDetallePacientes');
        const detalleDiv = document.getElementById('detallePacientes');
        const btnNuevo = document.getElementById('btnNuevoPersonal');
        const btnVolverListado = document.getElementById('btnVolverListado');
        const btnVolverListado2 = document.getElementById('btnVolverListado2');
        let form = document.getElementById('formPaciente');
        if (!form) { console.error('[pacientes] CRÍTICO: #formPaciente no existe en el DOM. Abortando init.'); return; }
        // Marcar el form para saber si ya tiene listener registrado
        if (form.dataset.listenerRegistrado === 'true') {
            // El módulo ya fue inicializado antes, limpiar listeners clonando
            // IMPORTANTE: reasignar "form" al clon, si no, los listeners se
            // adjuntan a un elemento que ya no está en el DOM
            const formNuevo = form.cloneNode(true);
            form.parentNode.replaceChild(formNuevo, form);
            form = formNuevo;
        }
        form.dataset.listenerRegistrado = 'true';
        const fotoInput = document.getElementById('fotoInput');
        const preview = document.getElementById('previewFoto');
        const labelFoto = document.getElementById('labelFoto');
        const inputFileWrapper = document.querySelector('.input-file-wrapper');
        const selectSexo = document.getElementById('selectSex');
        const selectEstadoCivil = document.getElementById('selectEstado_civil');
        const selectGradoInstruccion = document.getElementById('selectGrado_instruccion');
        const selectTipoDocumento = document.getElementById('selectTipoDocumento');
        const selectResponsable = document.getElementById('selectResponsable');
        const ayudaResponsable = document.getElementById('ayudaResponsable');
        let puedeAsignarResponsable = false;
        const ayudaDocumento = document.getElementById('ayudaDocumento');
        const inputBusqueda = document.getElementById('inputBusqueda');
        const inputFechaNacimiento = document.getElementById('fecha_nacimiento');

        let modoEdicion = false;
        let idEdicion = null;
        let fotoActual = null;
        let pacientesCompleto = [];
        let datosApoderado = null;
        let idApoderadoActual = null;
        let apoderadoGuardado = false;
        let tiposDocumento = [];

        const REGEX_TELEFONO = /^[0-9]{9}$/;
        const REGEX_NOMBRES = /^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/;
        const REGEX_CORREO = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/;

        // Regla por defecto (DNI) mientras no cargue el catálogo
        const REGLA_DNI = { id_tipo_documento: 1, codigo: '1', abreviatura: 'DNI', longitud_min: 8, longitud_max: 8, solo_numeros: 1 };

        inicializarModalApoderado();
        cargarSelectores();
        listarPacientes();

        // =====================================================
        // Documento de identidad: reglas según el tipo (catálogo tipo_documento)
        // =====================================================
        function reglaDocumento(idTipo) {
            return tiposDocumento.find(t => String(t.id_tipo_documento) === String(idTipo)) || REGLA_DNI;
        }

        // Deja solo los caracteres permitidos y respeta la longitud máxima
        function filtrarNumeroDocumento(valor, regla) {
            const soloNum = Number(regla.solo_numeros) === 1;
            let v = soloNum ? valor.replace(/[^0-9]/g, '') : valor.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            return v.substring(0, Number(regla.longitud_max));
        }

        // Devuelve el texto del error, o '' si el número es válido
        function evaluarDocumento(valor, regla) {
            const abrev = regla.abreviatura;
            const soloNum = Number(regla.solo_numeros) === 1;
            const min = Number(regla.longitud_min);
            const max = Number(regla.longitud_max);
            if (valor === '') return `El número de ${abrev} es obligatorio`;
            if (soloNum && !/^[0-9]+$/.test(valor)) return `El ${abrev} solo admite dígitos`;
            if (!soloNum && !/^[A-Za-z0-9]+$/.test(valor)) return `El ${abrev} solo admite letras y números`;
            if (valor.length < min || valor.length > max) {
                const unidad = soloNum ? 'dígitos' : 'caracteres';
                return min === max
                    ? `El ${abrev} debe tener exactamente ${min} ${unidad}`
                    : `El ${abrev} debe tener entre ${min} y ${max} ${unidad}`;
            }
            if (regla.codigo === '1' && /^(\d)\1{7}$/.test(valor)) return 'DNI no válido';
            return '';
        }

        // Ajusta longitud máxima, placeholder y texto de ayuda según el tipo elegido
        function aplicarReglasDocumento(select, input, ayuda) {
            if (!select || !input) return;
            const regla = reglaDocumento(select.value);
            const soloNum = Number(regla.solo_numeros) === 1;
            const min = Number(regla.longitud_min);
            const max = Number(regla.longitud_max);
            input.maxLength = max;
            input.placeholder = regla.codigo === '1' ? '12345678' : `Número de ${regla.abreviatura}`;
            input.value = filtrarNumeroDocumento(input.value, regla);
            if (ayuda) {
                const unidad = soloNum ? 'dígitos' : 'caracteres';
                ayuda.textContent = min === max
                    ? `${min} ${unidad}${soloNum ? ' sin puntos' : ''}`
                    : `Entre ${min} y ${max} ${unidad}`;
            }
        }

        // Pasa el texto a mayúsculas sin mover el cursor si se edita a mitad de palabra
        function aMayusculas(input) {
            const ini = input.selectionStart, fin = input.selectionEnd;
            input.value = input.value.toUpperCase();
            try { input.setSelectionRange(ini, fin); } catch (e) {}
        }

        function textoParentesco(a) {
            return (a && (a.tipo_familiar || a.tipo_familiar_descripcion)) || '';
        }

        // =====================================================
        // Cargar selectores
        // =====================================================
        function cargarSelectores() {
            fetch('../CONTROLADORES/controlador_pacientes.php?accion=listar_tipos_documento')
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) return;
                    tiposDocumento = data;
                    const llenar = sel => {
                        if (!sel) return;
                        const actual = sel.value;
                        sel.innerHTML = '';
                        data.forEach(t => {
                            const esDni = t.codigo === '1';
                            // defaultSelected: así form.reset() vuelve a DNI
                            sel.appendChild(new Option(t.abreviatura, t.id_tipo_documento, esDni, esDni));
                        });
                        if (actual) sel.value = actual;
                    };
                    const selApo = document.getElementById('tipoDocApoderado');
                    llenar(selectTipoDocumento);
                    llenar(selApo);
                    aplicarReglasDocumento(selectTipoDocumento, form.querySelector('input[name="nro_documento"]'), ayudaDocumento);
                    aplicarReglasDocumento(selApo, document.getElementById('dniApoderado'), null);
                }).catch(err => console.error('Error al cargar tipos de documento:', err));

            fetch('../CONTROLADORES/controlador_pacientes.php?accion=listar_doctores_responsables')
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !selectResponsable) return;
                    puedeAsignarResponsable = !!data.puede_asignar;
                    selectResponsable.innerHTML = '<option value="">Sin asignar</option>';
                    data.doctores.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id_usuario;
                        opt.textContent = d.nombre + ' ' + d.apellidos;
                        selectResponsable.appendChild(opt);
                    });
                    selectResponsable.disabled = !puedeAsignarResponsable;
                    if (ayudaResponsable) {
                        ayudaResponsable.textContent = puedeAsignarResponsable
                            ? 'Puede modificar los documentos del paciente'
                            : 'Solo el personal autorizado puede asignarlo';
                    }
                }).catch(() => { /* el campo queda sin opciones */ });

            fetch('../CONTROLADORES/controlador_pacientes.php?accion=listar_sexo')
                .then(res => res.json())
                .then(data => {
                    selectSexo.innerHTML = '<option value="">Seleccione...</option>';
                    data.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id_sexo;
                        opt.textContent = s.nombre_sexo;
                        selectSexo.appendChild(opt);
                    });
                }).catch(() => { selectSexo.innerHTML = '<option value="">Error al cargar</option>'; });

            fetch('../CONTROLADORES/controlador_pacientes.php?accion=listar_estado_civil')
                .then(res => res.json())
                .then(data => {
                    selectEstadoCivil.innerHTML = '<option value="">Seleccione...</option>';
                    data.forEach(e => {
                        const opt = document.createElement('option');
                        opt.value = e.id_estado_civil;
                        opt.textContent = e.nombre_estado_civil;
                        selectEstadoCivil.appendChild(opt);
                    });
                }).catch(() => { selectEstadoCivil.innerHTML = '<option value="">Error al cargar</option>'; });

            fetch('../CONTROLADORES/controlador_pacientes.php?accion=listar_grado_instruccion')
                .then(res => res.json())
                .then(data => {
                    selectGradoInstruccion.innerHTML = '<option value="">Seleccione...</option>';
                    data.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g.id_grado_instruccion;
                        opt.textContent = g.nombre_grado_instruccion;
                        selectGradoInstruccion.appendChild(opt);
                    });
                }).catch(() => { selectGradoInstruccion.innerHTML = '<option value="">Error al cargar</option>'; });
        }

        // =====================================================
        // Cargar tipos de familiar
        // =====================================================
        function cargarTiposFamiliar() {
            const selectTipo = document.getElementById('tipoFamiliar');
            if (!selectTipo) return;
            fetch('../CONTROLADORES/controlador_pacientes.php?accion=listar_tipo_familiar')
                .then(res => res.json())
                .then(data => {
                    selectTipo.innerHTML = '<option value="">Seleccione...</option>';
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(tipo => {
                            const opt = document.createElement('option');
                            opt.value = tipo.id_tipo_familiar;
                            opt.textContent = tipo.descripcion;
                            selectTipo.appendChild(opt);
                        });
                    } else {
                        selectTipo.innerHTML = '<option value="">No hay tipos disponibles</option>';
                    }
                }).catch(err => {
                    console.error('Error al cargar tipos de familiar:', err);
                    selectTipo.innerHTML = '<option value="">Error al cargar</option>';
                });
        }

        // =====================================================
        // Indicador de apoderado (solo para paciente NUEVO)
        // =====================================================
        if (inputFechaNacimiento) {
            inputFechaNacimiento.addEventListener('change', function() {
                renderizarBloqueApoderado();
            });
        }

        // Bloque del apoderado, según la edad y si ya hay uno cargado:
        //   - Menor de edad: obligatorio (alerta hasta que se agregue).
        //   - Adulto: opcional (personas adultas con apoderado o representante).
        function renderizarBloqueApoderado(conError = false) {
            const contenedor = document.getElementById('contenedor-apoderado-info');
            if (!contenedor) return;

            const edad = calcularEdadNumero(inputFechaNacimiento ? inputFechaNacimiento.value : '');
            const esMenor = edad !== null && edad < 18;
            const hayApoderado = datosApoderado && datosApoderado.nombre;

            if (hayApoderado) {
                const abrev = datosApoderado.tipo_documento_abrev || 'DNI';
                // Solo en adultos se puede quitar; en menores solo editar
                const btnQuitar = esMenor ? '' : `
                        <button type="button" class="btn-editar-apoderado" id="btnQuitarApoderado" style="margin-left:6px;">
                            <i class="ti ti-user-minus"></i> Quitar
                        </button>`;
                contenedor.innerHTML = `
                    <div class="info-apoderado">
                        <p><strong>${esc(datosApoderado.nombre)} ${esc(datosApoderado.apellido)}</strong></p>
                        <p>${esc(textoParentesco(datosApoderado))} — ${esc(abrev)}: ${esc(datosApoderado.nro_documento)}</p>
                        <p>Tel: ${esc(datosApoderado.telefono)}</p>
                        <button type="button" class="btn-editar-apoderado" id="btnEditarApoderado"><i class="ti ti-edit"></i> Editar</button>${btnQuitar}
                    </div>
                `;
                document.getElementById('btnEditarApoderado').addEventListener('click', () => mostrarModalApoderado(datosApoderado));
                const quitar = document.getElementById('btnQuitarApoderado');
                if (quitar) quitar.addEventListener('click', () => {
                    datosApoderado = null;
                    idApoderadoActual = null;
                    apoderadoGuardado = false;
                    renderizarBloqueApoderado();
                });
            } else if (esMenor) {
                contenedor.innerHTML = `
                    <div class="alerta-apoderado${conError ? ' error' : ''}">
                        <p><strong>${conError ? 'Falta información del apoderado' : 'Paciente menor de edad'}</strong>${conError ? '' : ' — se requieren datos del apoderado'}</p>
                        <button type="button" class="btn-agregar-apoderado" id="btnAgregarApoderado">
                            <i class="ti ti-user-plus"></i> ${conError ? 'Agregar Apoderado Ahora' : 'Agregar Apoderado'}
                        </button>
                    </div>
                `;
                document.getElementById('btnAgregarApoderado').addEventListener('click', () => mostrarModalApoderado());
            } else {
                contenedor.innerHTML = `
                    <div class="apoderado-opcional">
                        <p style="margin:0 0 8px;font-size:12px;color:#7f8c8d;">Opcional: agrégalo si el paciente cuenta con un apoderado o representante.</p>
                        <button type="button" class="btn-agregar-apoderado" id="btnAgregarApoderado">
                            <i class="ti ti-user-plus"></i> Agregar apoderado
                        </button>
                    </div>
                `;
                document.getElementById('btnAgregarApoderado').addEventListener('click', () => mostrarModalApoderado());
            }
        }

        // =====================================================
        // Modal apoderado
        // =====================================================
        function inicializarModalApoderado() {
            const modal = document.getElementById('modalApoderado');
            if (!modal) { console.error('Modal de apoderado no encontrado'); return; }

            document.getElementById('btnCerrarModal').addEventListener('click', cerrarModalApoderado);
            document.getElementById('btnCancelarApoderado').addEventListener('click', cerrarModalApoderado);
            document.getElementById('formApoderado').addEventListener('submit', guardarDatosApoderado);

            const dniApo = document.getElementById('dniApoderado');
            const tipoDocApo = document.getElementById('tipoDocApoderado');
            const telApo = document.getElementById('telefonoApoderado');
            const nomApo = document.getElementById('nombreApoderado');
            const apeApo = document.getElementById('apellidoApoderado');

            if (dniApo) dniApo.addEventListener('input', function() {
                this.value = filtrarNumeroDocumento(this.value, reglaDocumento(tipoDocApo ? tipoDocApo.value : 1));
            });
            if (tipoDocApo) tipoDocApo.addEventListener('change', () => aplicarReglasDocumento(tipoDocApo, dniApo, null));
            if (telApo) telApo.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, '').substring(0, 9); });
            if (nomApo) nomApo.addEventListener('input', function() { this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g, ''); aMayusculas(this); });
            if (apeApo) apeApo.addEventListener('input', function() { this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g, ''); aMayusculas(this); });

            cargarTiposFamiliar();
        }

        function mostrarModalApoderado(datosExistentes = null) {
            const modal = document.getElementById('modalApoderado');
            const formApo = document.getElementById('formApoderado');
            const tipoDocApo = document.getElementById('tipoDocApoderado');
            const dniApo = document.getElementById('dniApoderado');

            if (datosExistentes) {
                document.getElementById('nombreApoderado').value = datosExistentes.nombre || '';
                document.getElementById('apellidoApoderado').value = datosExistentes.apellido || '';
                if (tipoDocApo) tipoDocApo.value = datosExistentes.id_tipo_documento || 1;
                aplicarReglasDocumento(tipoDocApo, dniApo, null);
                dniApo.value = datosExistentes.nro_documento || '';
                document.getElementById('tipoFamiliar').value = datosExistentes.id_tipo_familiar || '';
                document.getElementById('telefonoApoderado').value = datosExistentes.telefono || '';
            } else {
                formApo.reset();
                aplicarReglasDocumento(tipoDocApo, dniApo, null);
            }

            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            setTimeout(() => { modal.classList.add('mostrar'); }, 10);
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalApoderado() {
            const modal = document.getElementById('modalApoderado');
            modal.classList.remove('mostrar');
            document.body.style.overflow = '';
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        }

        function guardarDatosApoderado(e) {
            e.preventDefault();
            const formApo = e.target;

            datosApoderado = {
                nombre:            formApo.nombre_apoderado.value.trim().toUpperCase(),
                apellido:          formApo.apellido_apoderado.value.trim().toUpperCase(),
                id_tipo_documento: formApo.tipo_doc_apoderado.value,
                nro_documento:               formApo.nro_documento_apoderado.value.trim().toUpperCase(),
                id_tipo_familiar:  formApo.tipo_familiar.value,
                telefono:          formApo.telefono_apoderado.value.trim()
            };

            if (!datosApoderado.nombre || !datosApoderado.apellido || !datosApoderado.nro_documento ||
                !datosApoderado.id_tipo_familiar || !datosApoderado.telefono) {
                mostrarMensajeSistema('Todos los campos del apoderado son obligatorios', 'error');
                return;
            }
            const reglaApo = reglaDocumento(datosApoderado.id_tipo_documento);
            const msgDoc = evaluarDocumento(datosApoderado.nro_documento, reglaApo);
            if (msgDoc) { mostrarMensajeSistema('Apoderado: ' + msgDoc, 'error'); return; }
            if (!REGEX_TELEFONO.test(datosApoderado.telefono)) { mostrarMensajeSistema('El teléfono del apoderado debe tener 9 dígitos', 'error'); return; }
            if (!REGEX_NOMBRES.test(datosApoderado.nombre)) { mostrarMensajeSistema('El nombre del apoderado solo puede contener letras', 'error'); return; }
            if (!REGEX_NOMBRES.test(datosApoderado.apellido)) { mostrarMensajeSistema('El apellido del apoderado solo puede contener letras', 'error'); return; }

            // Textos para mostrar en la interfaz
            const selectTipo = document.getElementById('tipoFamiliar');
            datosApoderado.tipo_familiar = selectTipo ? selectTipo.options[selectTipo.selectedIndex]?.text : '';
            datosApoderado.tipo_documento_abrev = reglaApo.abreviatura;

            apoderadoGuardado = true;
            actualizarCampoApoderado();
            cerrarModalApoderado();
            mostrarMensajeSistema('Datos del apoderado guardados correctamente', 'exito');
        }

        function actualizarCampoApoderado() {
            renderizarBloqueApoderado();
        }

        // =====================================================
        // Listar pacientes
        // =====================================================
        function listarPacientes() {
            fetch('../CONTROLADORES/controlador_pacientes.php?accion=listar')
                .then(res => res.json())
                .then(data => { pacientesCompleto = data; renderizarTabla(data); })
                .catch(err => console.error('Error al listar pacientes:', err));
        }

        function renderizarTabla(datos) {
            tablaBody.innerHTML = '';
            if (datos.length === 0) {
                tablaBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#999;">No se encontraron pacientes</td></tr>`;
                return;
            }
            datos.forEach(p => {
                tablaBody.innerHTML += `
                    <tr>
                        <td>${esc(p.id_paciente)}</td>
                        <td>${esc(p.nombre)}</td>
                        <td>${esc(p.apellido)}</td>
                        <td>${esc(p.tipo_documento_abrev || 'DNI')} ${esc(p.nro_documento)}</td>
                        <td>${esc(p.telefono)}</td>
                        <td>
                            <button class="btn-historia" data-id="${esc(p.id_paciente)}" title="Gestionar Historia Clínica"><i class="ti ti-clipboard-list"></i> Historia</button>
                            <button class="btn-ver" data-id="${esc(p.id_paciente)}" title="Ver Detalles"><i class="ti ti-eye"></i> Ver</button>
                        </td>
                    </tr>
                `;
            });
            document.querySelectorAll('.btn-historia').forEach(btn => {
                btn.addEventListener('click', e => abrirHistoriaClinica(e.currentTarget.dataset.id));
            });
            document.querySelectorAll('.btn-ver').forEach(btn => {
                btn.addEventListener('click', e => verDetalle(e.currentTarget.dataset.id));
            });
        }

        function abrirHistoriaClinica(idPaciente) {
            sessionStorage.setItem('paciente_historia_actual', JSON.stringify({
                id: idPaciente,
                pestana: 'citas'
            }));
            if (typeof window.navegarAModulo === 'function') {
                window.navegarAModulo('historia_clinica');
            } else {
                cargarModulo('../VISTAS/vista_historia_clinica.php');
            }
        }

        if (inputBusqueda) {
            inputBusqueda.addEventListener('input', (e) => {
                const terminoBusqueda = e.target.value.toLowerCase().trim();
                if (terminoBusqueda === '') { renderizarTabla(pacientesCompleto); return; }
                const filtrados = pacientesCompleto.filter(p => {
                    const nombreCompleto = `${p.nombre} ${p.apellido}`.toLowerCase();
                    return nombreCompleto.includes(terminoBusqueda) ||
                           p.nro_documento.toLowerCase().includes(terminoBusqueda) ||
                           (p.telefono && p.telefono.toLowerCase().includes(terminoBusqueda)) ||
                           (p.correo && p.correo.toLowerCase().includes(terminoBusqueda));
                });
                renderizarTabla(filtrados);
            });
        }

        // =====================================================
        // Validaciones
        // =====================================================
        const inputDni = form.querySelector('input[name="nro_documento"]');
        const inputNombre = form.querySelector('input[name="nombre"]');
        const inputApellidos = form.querySelector('input[name="apellidos"]');
        const inputCorreo = form.querySelector('input[name="correo"]');
        const inputTelefono = form.querySelector('input[name="telefono"]');

        if (inputDni) {
            inputDni.addEventListener('input', function() {
                this.value = filtrarNumeroDocumento(this.value, reglaDocumento(selectTipoDocumento.value));
                validarCampoDocumento(this);
            });
            inputDni.addEventListener('blur', function() { validarCampoDocumento(this); });
        }
        if (selectTipoDocumento) {
            selectTipoDocumento.addEventListener('change', function() {
                aplicarReglasDocumento(selectTipoDocumento, inputDni, ayudaDocumento);
                if (inputDni.value.trim() !== '') validarCampoDocumento(inputDni);
                else eliminarMensajeError(inputDni);
            });
        }
        if (inputTelefono) {
            inputTelefono.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, '').substring(0, 9); validarCampoTelefono(this); });
            inputTelefono.addEventListener('blur', function() { validarCampoTelefono(this); });
        }
        if (inputNombre) inputNombre.addEventListener('input', function() { aMayusculas(this); validarCampoTexto(this, 'nombre'); });
        if (inputApellidos) inputApellidos.addEventListener('input', function() { aMayusculas(this); validarCampoTexto(this, 'apellidos'); });
        if (inputCorreo) inputCorreo.addEventListener('blur', function() { validarCampoCorreo(this); });

        function validarCampoDocumento(input) {
            eliminarMensajeError(input);
            const valor = input.value.trim();
            const mensaje = evaluarDocumento(valor, reglaDocumento(selectTipoDocumento.value));
            if (mensaje) { mostrarError(input, mensaje); return false; }
            input.classList.add('campo-valido');
            return true;
        }

        function validarCampoTelefono(input) {
            eliminarMensajeError(input);
            const valor = input.value.trim();
            if (valor === '') { mostrarError(input, 'El teléfono es obligatorio'); return false; }
            if (!REGEX_TELEFONO.test(valor)) { mostrarError(input, 'El teléfono debe tener exactamente 9 dígitos'); return false; }
            input.classList.add('campo-valido');
            return true;
        }

        function validarCampoTexto(input, tipo) {
            eliminarMensajeError(input);
            const valor = input.value.trim();
            if (valor === '') { mostrarError(input, `${tipo === 'nombre' ? 'El nombre' : 'Los apellidos'} son obligatorios`); return false; }
            if (!REGEX_NOMBRES.test(valor)) { mostrarError(input, 'Solo se permiten letras, espacios y acentos'); return false; }
            if (valor.length < 2) { mostrarError(input, 'Debe tener al menos 2 caracteres'); return false; }
            input.classList.add('campo-valido');
            return true;
        }

        function validarCampoCorreo(input) {
            eliminarMensajeError(input);
            const valor = input.value.trim();
            if (valor === '') { input.classList.remove('campo-invalido'); return true; } // Opcional
            if (!REGEX_CORREO.test(valor)) { mostrarError(input, 'Formato de correo inválido (ej: usuario@dominio.com)'); return false; }
            input.classList.add('campo-valido');
            return true;
        }

        // Los mensajes se agregan al contenedor del campo (no al padre inmediato),
        // porque el documento comparte fila con el selector de tipo.
        function contenedorCampo(input) {
            return input.closest('.grupo-campo') || input.parentElement;
        }

        function mostrarError(input, mensaje) {
            input.classList.remove('campo-valido');
            input.classList.add('campo-invalido');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'mensaje-error-campo';
            errorDiv.textContent = mensaje;
            contenedorCampo(input).appendChild(errorDiv);
        }

        function eliminarMensajeError(input) {
            input.classList.remove('campo-invalido', 'campo-valido');
            const errorExistente = contenedorCampo(input).querySelector('.mensaje-error-campo');
            if (errorExistente) errorExistente.remove();
        }

        function validarFormularioCompleto() {
            let esValido = true;
            if (!validarCampoDocumento(inputDni)) esValido = false;
            if (!validarCampoTexto(inputNombre, 'nombre')) esValido = false;
            if (!validarCampoTexto(inputApellidos, 'apellidos')) esValido = false;
            if (!validarCampoCorreo(inputCorreo)) esValido = false;
            if (!validarCampoTelefono(inputTelefono)) esValido = false;
            if (!selectSexo.value) { mostrarError(selectSexo, 'Debe seleccionar un sexo'); esValido = false; }
            if (!selectEstadoCivil.value) { mostrarError(selectEstadoCivil, 'Debe seleccionar un estado civil'); esValido = false; }
            return esValido;
        }

        // =====================================================
        // Calcular edad
        // =====================================================
        function calcularEdadNumero(fechaNacimiento) {
            if (!fechaNacimiento) return null;
            const hoy = new Date();
            const nacimiento = new Date(fechaNacimiento + "T00:00:00");
            let edad = hoy.getFullYear() - nacimiento.getFullYear();
            const mes = hoy.getMonth() - nacimiento.getMonth();
            if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
            return edad;
        }

        function calcularEdad(fechaNacimiento) {
            const edad = calcularEdadNumero(fechaNacimiento);
            return edad !== null ? `${edad} años` : 'No especificada';
        }

        // =====================================================
        // Nuevo paciente
        // =====================================================
        btnNuevo.addEventListener('click', () => {
            modoEdicion = false;
            idEdicion = null;
            fotoActual = null;
            datosApoderado = null;
            idApoderadoActual = null;
            apoderadoGuardado = false;

            form.reset();
            aplicarReglasDocumento(selectTipoDocumento, inputDni, ayudaDocumento);
            form.querySelectorAll('.campo-invalido, .campo-valido').forEach(el => el.classList.remove('campo-invalido', 'campo-valido'));
            form.querySelectorAll('.mensaje-error-campo').forEach(el => el.remove());

            if (inputFileWrapper) inputFileWrapper.style.display = 'block';
            if (labelFoto) { labelFoto.innerHTML = '<i class="ti ti-upload" style="font-size:13px;"></i> Subir foto'; labelFoto.classList.remove('archivo-seleccionado'); }

            preview.innerHTML = `<i class="ti ti-camera"></i>`;
            removeExistingEditButton();
            removeExistingBirthdayBlock();

            renderizarBloqueApoderado();

            document.getElementById('tituloFormPaciente').textContent = 'Registrar nuevo paciente';
            form.querySelector('.btn-guardar').innerHTML = '<i class="ti ti-device-floppy"></i> Guardar paciente';
            form.querySelector('.btn-guardar').style.display = 'inline-block';
            form.querySelectorAll('input, select, textarea').forEach(el => el.removeAttribute('disabled'));
            if (selectResponsable) selectResponsable.disabled = !puedeAsignarResponsable;
            fotoInput.removeAttribute('disabled');

            btnNuevo.style.display = 'none';
            seccionListado.style.display = 'none';
            seccionDetalle.style.display = 'none';
            seccionNuevo.style.display = 'block';
        });

        // =====================================================
        // Volver al listado
        // =====================================================
        function mostrarListado() {
            removeExistingEditButton();
            removeExistingBirthdayBlock();
            fotoActual = null;
            datosApoderado = null;
            idApoderadoActual = null;
            apoderadoGuardado = false;
            modoEdicion = false;

            form.querySelectorAll('.campo-invalido, .campo-valido').forEach(el => el.classList.remove('campo-invalido', 'campo-valido'));
            form.querySelectorAll('.mensaje-error-campo').forEach(el => el.remove());

            seccionNuevo.style.display = 'none';
            seccionDetalle.style.display = 'none';
            seccionListado.style.display = 'block';
            btnNuevo.style.display = 'inline-block';
            listarPacientes();
        }

        btnVolverListado.addEventListener('click', mostrarListado);
        if (btnVolverListado2) btnVolverListado2.addEventListener('click', mostrarListado);

        // =====================================================
        // Ver detalle — UN SOLO FETCH, apoderado incluido
        // =====================================================
        function verDetalle(id) {
            fetch(`../CONTROLADORES/controlador_pacientes.php?accion=ver&id=${id}`)
                .then(res => res.json())
                .then(p => mostrarDetalle(p))
                .catch(err => console.error(err));
        }

        // =====================================================
        // Mostrar detalle
        // =====================================================
        function mostrarDetalle(p) {
            const detalleDiv = document.getElementById('detallePacientes');
            if (!detalleDiv) return;

            // Formatear fecha
            const fmtFecha = f => {
                if (!f) return '—';
                const [y,m,d] = f.split('-');
                return `${d}/${m}/${y}`;
            };
            // Calcular edad
            const calcEdad = f => {
                if (!f) return '';
                const hoy = new Date(), nac = new Date(f);
                let e = hoy.getFullYear() - nac.getFullYear();
                const m = hoy.getMonth() - nac.getMonth();
                if (m < 0 || (m===0 && hoy.getDate() < nac.getDate())) e--;
                return ` (${e} años)`;
            };

            const iniciales = ((p.nombre||'').charAt(0) + (p.apellido||'').charAt(0)).toUpperCase();
            const avatarHtml = p.foto
                ? `<img class="detalle-avatar" src="../${esc(p.foto)}" alt="Foto"
                        onerror="this.outerHTML='<div class=\\'detalle-avatar\\'>${esc(iniciales)}</div>'">`
                : `<div class="detalle-avatar">${esc(iniciales)}</div>`;

            const apo = p.apoderado ? `
                <div class="info-apoderado">
                    <p><strong>${esc(p.apoderado.nombre)} ${esc(p.apoderado.apellido)}</strong></p>
                    <p>${esc(textoParentesco(p.apoderado))} — ${esc(p.apoderado.tipo_documento_abrev || 'DNI')}: ${esc(p.apoderado.nro_documento)}</p>
                    <p>Tel: ${esc(p.apoderado.telefono)}</p>
                </div>` : '<span class="sin-apoderado">Sin apoderado registrado</span>';

            detalleDiv.innerHTML = `
                <div class="detalle-paciente-card">
                    <div class="detalle-header">
                        ${avatarHtml}
                        <div class="detalle-header-info">
                            <p class="nombre">${esc(p.nombre)} ${esc(p.apellido)}</p>
                            <p class="meta">${esc(p.tipo_documento_abrev || 'DNI')} ${esc(p.nro_documento)} · ${esc(p.nombre_sexo || '')}</p>
                        </div>
                    </div>

                    <div class="detalle-body">
                        <div class="detalle-seccion">
                            <h4>Información personal</h4>
                            <div class="detalle-fila">
                                <span class="etiqueta">Fecha de nacimiento</span>
                                <span class="valor">${esc(fmtFecha(p.fecha_nacimiento))}${esc(calcEdad(p.fecha_nacimiento))}</span>
                            </div>
                            <div class="detalle-fila">
                                <span class="etiqueta">Estado civil</span>
                                <span class="valor">${esc(p.nombre_estado_civil || '—')}</span>
                            </div>
                            <div class="detalle-fila">
                                <span class="etiqueta">Grado de instrucción</span>
                                <span class="valor">${esc(p.nombre_grado_instruccion || '—')}</span>
                            </div>
                            <div class="detalle-fila">
                                <span class="etiqueta">Ocupación</span>
                                <span class="valor">${esc(p.ocupacion || '—')}</span>
                            </div>
                            <div class="detalle-fila">
                                <span class="etiqueta">Odontólogo responsable</span>
                                <span class="valor">${esc(p.doctor_responsable_nombre || 'Sin asignar')}</span>
                            </div>
                        </div>

                        <div class="detalle-seccion">
                            <h4>Contacto</h4>
                            <div class="detalle-fila">
                                <span class="etiqueta">Teléfono</span>
                                <span class="valor">${esc(p.telefono || '—')}</span>
                            </div>
                            <div class="detalle-fila">
                                <span class="etiqueta">Correo</span>
                                <span class="valor">${esc(p.correo || '—')}</span>
                            </div>
                            <div class="detalle-fila">
                                <span class="etiqueta">Dirección</span>
                                <span class="valor">${esc(p.direccion || '—')}</span>
                            </div>
                        </div>

                        <div class="detalle-seccion campo-ancho">
                            <h4>Apoderado</h4>
                            ${apo}
                        </div>

                        <div class="detalle-seccion campo-ancho">
                            <h4>Portal del paciente</h4>
                            <div class="detalle-fila">
                                <span class="etiqueta">Acceso al portal</span>
                                <span class="valor">${p.tiene_acceso_portal ? 'Activo' : 'Sin crear'}</span>
                            </div>
                        </div>

                        ${p.observaciones ? `
                        <div class="detalle-seccion campo-ancho">
                            <h4>Observaciones</h4>
                            <p style="font-size:13px;color:#1a2332;">${esc(p.observaciones)}</p>
                        </div>` : ''}
                    </div>

                    <div class="detalle-footer">
                        <button class="btn-principal" onclick="editarPaciente(${p.id_paciente})">
                            <i class="ti ti-edit"></i> Editar
                        </button>
                        <button class="btn-historia" id="btnHistoriaDetalle">
                            <i class="ti ti-clipboard-list"></i> Historia clínica
                        </button>
                        ${(p.tiene_acceso_portal && p.permisos && p.permisos.restablecer_portal) ? `
                        <button class="btn-secundario" id="btnRestablecerPortal" style="margin-bottom:0;">
                            <i class="ti ti-key"></i> Restablecer acceso al portal
                        </button>` : ''}
                    </div>
                </div>
            `;

            const btnHistoria = document.getElementById('btnHistoriaDetalle');
            if (btnHistoria) btnHistoria.addEventListener('click', () => {
                if (window.navegarAModulo) {
                    window.navegarAModulo('historia_clinica', { id_paciente: p.id_paciente, nombre_paciente: `${p.nombre} ${p.apellido}` });
                }
            });

            const btnPortal = document.getElementById('btnRestablecerPortal');
            if (btnPortal) btnPortal.addEventListener('click', () => abrirModalRestablecerPortal(p));

            seccionListado.style.display   = 'none';
            seccionNuevo.style.display     = 'none';
            seccionDetalle.style.display   = 'block';
            btnNuevo.style.display         = 'none';

            idEdicion   = p.id_paciente;
            fotoActual  = p.foto || null;
            if (p.apoderado) {
                datosApoderado    = p.apoderado;
                idApoderadoActual = p.id_apoderado;
                apoderadoGuardado = true;
            } else {
                datosApoderado    = null;
                idApoderadoActual = null;
                apoderadoGuardado = false;
            }
        }


        // =====================================================
        // Modal: restablecer acceso al portal del paciente
        // =====================================================
        function abrirModalRestablecerPortal(p) {
            const modal = document.getElementById('modalRestablecerPortal');
            if (!modal) return;

            document.getElementById('restablecerNombrePaciente').textContent = `${p.nombre} ${p.apellido}`;
            document.getElementById('restablecerTelefonoPaciente').textContent = p.telefono || '—';

            const btnSi = document.getElementById('btnConfirmarRestablecer');
            const btnNo = document.getElementById('btnCancelarRestablecer');
            const btnX  = document.getElementById('btnCerrarRestablecer');

            function cerrar() {
                modal.classList.remove('mostrar');
                document.body.style.overflow = '';
                setTimeout(() => { modal.style.display = 'none'; }, 300);
                btnSi.removeEventListener('click', onSi);
                btnNo.removeEventListener('click', cerrar);
                btnX.removeEventListener('click', cerrar);
            }

            async function onSi() {
                btnSi.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('accion', 'restablecer_acceso_portal');
                    fd.append('id_paciente', p.id_paciente);
                    const res  = await fetch('../CONTROLADORES/controlador_pacientes.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        cerrar();
                        mostrarMensajeSistema('Acceso al portal restablecido', 'exito');
                        verDetalle(p.id_paciente); // vuelve a pintar el detalle ya sin el botón
                    } else {
                        mostrarMensajeSistema(data.mensaje || 'No se pudo restablecer el acceso', 'error');
                    }
                } catch (err) {
                    console.error(err);
                    mostrarMensajeSistema('Error en la petición', 'error');
                }
                btnSi.disabled = false;
            }

            btnSi.addEventListener('click', onSi);
            btnNo.addEventListener('click', cerrar);
            btnX.addEventListener('click', cerrar);

            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            setTimeout(() => { modal.classList.add('mostrar'); }, 10);
            document.body.style.overflow = 'hidden';
        }

        // =====================================================
        // Editar paciente
        // =====================================================
        function editarPaciente(p) {
            if (typeof p === 'number' || typeof p === 'string') {
                fetch(`../CONTROLADORES/controlador_pacientes.php?accion=ver&id=${p}`)
                    .then(r => r.json()).then(pac => editarPaciente(pac)).catch(console.error);
                return;
            }

            modoEdicion  = true;
            idEdicion    = p.id_paciente;
            fotoActual   = p.foto || fotoActual;

            const tituloForm = document.getElementById('tituloFormPaciente');
            if (tituloForm) tituloForm.textContent = 'Editar paciente';

            form.querySelectorAll('input, select, textarea').forEach(el => el.removeAttribute('disabled'));
            if (selectResponsable) selectResponsable.disabled = !puedeAsignarResponsable;
            fotoInput.removeAttribute('disabled');

            // Llenar el formulario con los datos del paciente
            form.nombre.value            = p.nombre || '';
            form.apellidos.value         = p.apellido || '';
            // Primero el tipo de documento (define longitud y caracteres permitidos), luego el número
            if (selectTipoDocumento) selectTipoDocumento.value = p.id_tipo_documento || 1;
            aplicarReglasDocumento(selectTipoDocumento, inputDni, ayudaDocumento);
            form.nro_documento.value               = p.nro_documento || '';
            form.fecha_nacimiento.value  = p.fecha_nacimiento || '';
            form.telefono.value          = p.telefono || '';
            form.correo.value            = p.correo || '';
            form.direccion.value         = p.direccion || '';
            form.ocupacion.value         = p.ocupacion || '';
            form.observaciones.value     = p.observaciones || '';
            if (selectSexo)              selectSexo.value = p.id_sexo || '';
            if (selectEstadoCivil)       selectEstadoCivil.value = p.id_estado_civil || '';
            if (selectGradoInstruccion)  selectGradoInstruccion.value = p.id_grado_instruccion || '';
            if (selectResponsable) {
                // Si el responsable ya no es un odontologo activo, se conserva visible para no borrarlo sin querer
                if (p.id_doctor_responsable && !Array.from(selectResponsable.options).some(o => o.value == p.id_doctor_responsable)) {
                    const opt = document.createElement('option');
                    opt.value = p.id_doctor_responsable;
                    opt.textContent = (p.doctor_responsable_nombre || 'Responsable actual') + ' (inactivo)';
                    selectResponsable.appendChild(opt);
                }
                selectResponsable.value = p.id_doctor_responsable || '';
            }

            // Limpiar marcas de validación visual de una sesión anterior
            form.querySelectorAll('.campo-invalido, .campo-valido').forEach(el => el.classList.remove('campo-invalido', 'campo-valido'));
            form.querySelectorAll('.mensaje-error-campo').forEach(el => el.remove());

            if (p.fecha_nacimiento) appendBirthdayBlock(p.fecha_nacimiento);

            // Mostrar foto actual si existe
            if (p.foto) {
                preview.innerHTML = `<img src="../${esc(p.foto)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
            } else {
                preview.innerHTML = `<i class="ti ti-camera"></i>`;
            }
            if (labelFoto) { labelFoto.innerHTML = '<i class="ti ti-upload" style="font-size:13px;"></i> Cambiar foto'; labelFoto.classList.remove('archivo-seleccionado'); }

            const btnGuardar = form.querySelector('.btn-guardar');
            if (btnGuardar) { btnGuardar.innerHTML = '<i class="ti ti-device-floppy"></i> Actualizar paciente'; btnGuardar.style.display = ''; }

            renderizarBloqueApoderado();

            // Mostrar la sección del formulario y ocultar las demás
            seccionListado.style.display = 'none';
            seccionDetalle.style.display = 'none';
            seccionNuevo.style.display   = 'block';

            btnNuevo.style.display = 'none';
            removeExistingEditButton();
        }

        // =====================================================
        // Guardar / actualizar — listener único con bandera
        // =====================================================
        let submitHandler = null; // referencia al handler para poder removerlo

        function registrarSubmitHandler() {
            // Remover handler anterior si existe
            if (submitHandler) {
                form.removeEventListener('submit', submitHandler);
            }

            submitHandler = function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                const btnGuardar = form.querySelector('.btn-guardar');
                if (btnGuardar.disabled) return false;

                if (!validarFormularioCompleto()) {
                    mostrarMensajeSistema('Por favor, corrija los errores del formulario', 'error');
                    return false;
                }

                const fechaNac = form.fecha_nacimiento.value;
                const edad = calcularEdadNumero(fechaNac);

                const datosApoderadoSnap    = datosApoderado;
                const idApoderadoSnap       = idApoderadoActual;
                const apoderadoGuardadoSnap = apoderadoGuardado;

                if (edad !== null && edad < 18) {
                    const tieneApoderado = (datosApoderadoSnap && datosApoderadoSnap.nombre) ||
                                           idApoderadoSnap || apoderadoGuardadoSnap;
                    if (!tieneApoderado) {
                        mostrarMensajeSistema('Debe registrar un apoderado para pacientes menores de edad', 'error');
                        renderizarBloqueApoderado(true);
                        return false;
                    }
                }

                const datos = new FormData(form);

                if (datosApoderadoSnap && datosApoderadoSnap.nombre) {
                    datos.append('datos_apoderado', JSON.stringify(datosApoderadoSnap));
                    if (idApoderadoSnap) datos.append('id_apoderado', idApoderadoSnap);
                } else if (idApoderadoSnap) {
                    datos.append('id_apoderado', idApoderadoSnap);
                }

                if (modoEdicion) {
                    datos.append('accion', 'editar');
                    datos.append('id_paciente', idEdicion);
                    if (!fotoInput.files || fotoInput.files.length === 0) {
                        datos.append('foto_actual', fotoActual || '');
                    }
                } else {
                    datos.append('accion', 'registrar');
                }

                const textoOriginal = btnGuardar.textContent;
                btnGuardar.disabled = true;
                btnGuardar.textContent = 'Guardando...';

                fetch('../CONTROLADORES/controlador_pacientes.php', { method: 'POST', body: datos })
                    .then(res => res.json())
                    .then(data => {
                        btnGuardar.disabled = false;
                        btnGuardar.textContent = textoOriginal;

                        if (data.success) {
                            if (modoEdicion) {
                                mostrarMensajeSistema('Paciente actualizado correctamente', 'exito');
                                setTimeout(() => {
                                    fetch(`../CONTROLADORES/controlador_pacientes.php?accion=ver&id=${idEdicion}`)
                                        .then(res => res.json())
                                        .then(p => { modoEdicion = false; mostrarDetalle(p); });
                                }, 1000);
                            } else {
                                mostrarMensajeSistema('Paciente registrado correctamente', 'exito');
                                setTimeout(() => {
                                    datosApoderado    = null;
                                    idApoderadoActual = null;
                                    apoderadoGuardado = false;
                                    mostrarListado();
                                }, 2000);
                            }
                        } else {
                            mostrarMensajeSistema(`${data.mensaje || 'Error al guardar'}`, 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        btnGuardar.disabled = false;
                        btnGuardar.textContent = textoOriginal;
                        mostrarMensajeSistema('Error en la petición', 'error');
                    });

                return false;
            };

            form.addEventListener('submit', submitHandler);
        }

        // Registrar el handler al iniciar
        registrarSubmitHandler();

        // =====================================================
        // Vista previa foto
        // =====================================================
        fotoInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (file) {
                if (labelFoto) { labelFoto.textContent = file.name; labelFoto.classList.add('archivo-seleccionado'); }
                const reader = new FileReader();
                reader.onload = e2 => {
                    preview.innerHTML = `<img src="${e2.target.result}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
                };
                reader.readAsDataURL(file);
            } else {
                if (labelFoto) { labelFoto.innerHTML = '<i class="ti ti-upload" style="font-size:13px;"></i> Subir foto'; labelFoto.classList.remove('archivo-seleccionado'); }
            }
        });

        // =====================================================
        // Mensaje del sistema
        // =====================================================
        function mostrarMensajeSistema(msg, tipo = "exito") {
            const aviso = document.createElement("div");
            aviso.className = `mensaje-sistema ${tipo}`;
            aviso.innerHTML = `<span class="texto">${esc(msg)}</span>`;
            document.body.appendChild(aviso);
            setTimeout(() => aviso.classList.add("mostrar"), 100);
            setTimeout(() => { aviso.classList.remove("mostrar"); setTimeout(() => aviso.remove(), 400); }, 3000);
        }

        // =====================================================
        // Helpers
        // =====================================================
        function removeExistingBirthdayBlock() {
            const prev = document.querySelector('.bloque-edad');
            if (prev) prev.remove();
        }

        function appendBirthdayBlock(fechaNacimiento) {
            removeExistingBirthdayBlock();
            const bloque = document.createElement('div');
            bloque.classList.add('bloque-edad');
            bloque.innerHTML = `<p class="edad-texto">Edad: ${calcularEdad(fechaNacimiento)}</p>`;
            const grupoFoto = preview.parentElement;
            if (inputFileWrapper) grupoFoto.insertBefore(bloque, inputFileWrapper);
            else grupoFoto.appendChild(bloque);
        }

        function removeExistingEditButton() {
            const prevBtn = document.getElementById('btnEditarDetalle');
            if (prevBtn) prevBtn.remove();
        }

        function appendEditButton(p) {
            removeExistingEditButton();
            const btn = document.createElement('button');
            btn.id = 'btnEditarDetalle';
            btn.type = 'button';
            btn.className = 'btn-editar';
            btn.innerHTML = '<i class="ti ti-edit"></i> Editar';
            btn.addEventListener('click', () => editarPaciente(p));
            let columnaIzq = preview.closest('.columna-izquierda') || preview.parentElement;
            columnaIzq.appendChild(btn);
        }

        function cargarModulo(ruta) {
            const contenedorPrincipal = document.querySelector('.contenedor-principal') ||
                                        document.querySelector('main') ||
                                        document.querySelector('#contenido-principal');
            if (!contenedorPrincipal) { console.error('No se encontró contenedor principal'); return; }
            contenedorPrincipal.innerHTML = '<div style="text-align:center;padding:50px;"><p>Cargando módulo...</p></div>';
            fetch(ruta)
                .then(res => res.text())
                .then(html => {
                    contenedorPrincipal.innerHTML = html;
                    if (ruta.includes('historia_clinica')) cargarScript('../SCRIPTS/script_historia_clinica.js');
                })
                .catch(err => {
                    console.error('Error al cargar módulo:', err);
                    contenedorPrincipal.innerHTML = '<div style="text-align:center;padding:50px;"><p>Error al cargar el módulo</p></div>';
                });
        }

        function cargarScript(rutaScript) {
            const scriptExistente = document.querySelector(`script[src="${rutaScript}"]`);
            if (scriptExistente) scriptExistente.remove();
            const script = document.createElement('script');
            script.src = rutaScript;
            script.onload = () => {
                if (typeof iniciarModuloHistoriaClinica === 'function') iniciarModuloHistoriaClinica();
            };
            document.body.appendChild(script);
        }


        // Exponer funciones usadas en onclick="" inline del HTML generado dinámicamente
        window.editarPaciente = editarPaciente;

        // Importacion masiva desde Excel: el boton solo existe si el usuario tiene el permiso
        window.recargarListaPacientes = listarPacientes;
        if (document.getElementById('btnImportarPacientes')) {
            const rutaImp = '../SCRIPTS/script_importacion_pacientes.js';
            const previo = document.querySelector(`script[src="${rutaImp}"]`);
            if (previo) previo.remove();
            const scriptImp = document.createElement('script');
            scriptImp.src = rutaImp;
            scriptImp.onload = () => { if (typeof iniciarImportacionPacientes === 'function') iniciarImportacionPacientes(); };
            document.body.appendChild(scriptImp);
        }

    } catch (errorInit) {
        console.error('[pacientes] ERROR FATAL durante inicialización:', errorInit);
        console.error('[pacientes] Stack:', errorInit.stack);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarModuloPacientes);
} else {
    iniciarModuloPacientes();
}