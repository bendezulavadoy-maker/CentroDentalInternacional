function iniciarModuloPersonal() {
    const tablaBody = document.querySelector('#tablaPersonal tbody');
    const seccionListado = document.getElementById('seccionListadoPersonal');
    const seccionNuevo = document.getElementById('seccionNuevoPersonal');
    const seccionDetalle = document.getElementById('seccionDetallePersonal');
    const detalleDiv = document.getElementById('detallePersonal');
    const btnNuevo = document.getElementById('btnNuevoPersonal');
    const btnVolverListado = document.getElementById('btnVolverListado');
    const btnVolverListado2 = document.getElementById('btnVolverListado2');
    const form = document.getElementById('formPersonal');
    const fotoInput = document.getElementById('fotoInput');
    const preview = document.getElementById('previewFoto');
    const labelFoto = document.getElementById('labelFoto');
    const inputFileWrapper = document.querySelector('.input-file-wrapper');
    const infoDiv = document.getElementById('infoRegistro');
    const codigoSpan = document.getElementById('codigoAsignado');
    const contrasenaSpan = document.getElementById('contrasenaAsignada');
    const btnCopiarTodo = document.getElementById('btnCopiarTodo');
    const selectRol = document.getElementById('selectRol');
    const tituloFormulario = document.getElementById('tituloFormPersonal');
    const inputBusqueda = document.getElementById('inputBusqueda');

    let modoEdicion = false;
    let idEdicion = null;
    let fotoActual = null;
    let personalCompleto = []; // Almacena todos los registros para búsqueda

    // =====================================================
    // EXPRESIONES REGULARES PARA VALIDACIÓN
    // =====================================================
    const REGEX_DNI = /^[0-9]{8}$/;
    const REGEX_NOMBRES = /^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/;
    const REGEX_CORREO = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/;

    cargarRoles();
    listarPersonal();

    // =====================================================
    // Cargar roles
    // =====================================================
    function cargarRoles() {
        fetch('../CONTROLADORES/controlador_personal.php?accion=listar_roles')
            .then(res => res.json())
            .then(data => {
                selectRol.innerHTML = '<option value="">Seleccione...</option>';
                data.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id_rol;
                    opt.textContent = r.nombre_rol;
                    selectRol.appendChild(opt);
                });
            })
            .catch(() => {
                selectRol.innerHTML = '<option value="">Error al cargar roles</option>';
            });
    }

    // =====================================================
    // Listar personal
    // =====================================================
    function listarPersonal() {
        fetch('../CONTROLADORES/controlador_personal.php?accion=listar')
            .then(res => res.json())
            .then(data => {
                personalCompleto = data; // Guardar para búsqueda
                renderizarTabla(data);
            });
    }

    // =====================================================
    // Renderizar tabla de personal
    // =====================================================
    function renderizarTabla(datos) {
        tablaBody.innerHTML = '';
        if (datos.length === 0) {
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px; color:#999;">
                        No se encontraron registros
                    </td>
                </tr>
            `;
            return;
        }

        datos.forEach(p => {
            tablaBody.innerHTML += `
                <tr>
                    <td>${p.id_usuario}</td>
                    <td>${p.codigo_usuario}</td>
                    <td>${p.nombre} ${p.apellidos}</td>
                    <td>${p.correo}</td>
                    <td>${p.nombre_rol}</td>
                    <td><span class="badge-estado ${p.id_estado == 1 ? 'activo' : 'inactivo'}">${p.id_estado == 1 ? 'Activo' : 'Inactivo'}</span></td>
                    <td>
                        <button class="btn-ver" data-id="${p.id_usuario}"><i class="ti ti-eye"></i> Ver</button>
                    </td>
                </tr>
            `;
        });

        document.querySelectorAll('.btn-ver').forEach(btn => {
            btn.addEventListener('click', e => verDetalle(e.currentTarget.dataset.id));
        });
    }

    // =====================================================
    // Búsqueda de personal
    // =====================================================
    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', (e) => {
            const terminoBusqueda = e.target.value.toLowerCase().trim();
            
            if (terminoBusqueda === '') {
                renderizarTabla(personalCompleto);
                return;
            }

            const resultadosFiltrados = personalCompleto.filter(p => {
                const nombreCompleto = `${p.nombre} ${p.apellidos}`.toLowerCase();
                const codigo = p.codigo_usuario.toLowerCase();
                const correo = p.correo.toLowerCase();
                
                // Búsqueda por coincidencia parcial en nombre, código o correo
                return nombreCompleto.includes(terminoBusqueda) ||
                       codigo.includes(terminoBusqueda) ||
                       correo.includes(terminoBusqueda) ||
                       nombreCompleto.startsWith(terminoBusqueda);
            });

            renderizarTabla(resultadosFiltrados);
        });
    }

    // =====================================================
    // VALIDACIÓN DE CAMPOS EN TIEMPO REAL
    // =====================================================
    const inputDni = form.querySelector('input[name="dni"]');
    const inputNombre = form.querySelector('input[name="nombre"]');
    const inputApellidos = form.querySelector('input[name="apellidos"]');
    const inputCorreo = form.querySelector('input[name="correo"]');

    // Validar DNI
    if (inputDni) {
        inputDni.addEventListener('input', function(e) {
            // Solo permitir números y limitar a 8 caracteres
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 8);
            validarCampoDNI(this);
        });

        inputDni.addEventListener('blur', function() {
            validarCampoDNI(this);
        });
    }

    // Validar nombre
    if (inputNombre) {
        inputNombre.addEventListener('input', function(e) {
            validarCampoTexto(this, 'nombre');
        });
    }

    // Validar apellidos
    if (inputApellidos) {
        inputApellidos.addEventListener('input', function(e) {
            validarCampoTexto(this, 'apellidos');
        });
    }

    // Validar correo
    if (inputCorreo) {
        inputCorreo.addEventListener('blur', function() {
            validarCampoCorreo(this);
        });
    }

    // =====================================================
    // Funciones de validación individuales
    // =====================================================
    function validarCampoDNI(input) {
        eliminarMensajeError(input);
        const valor = input.value.trim();

        if (valor === '') {
            mostrarError(input, 'El DNI es obligatorio');
            return false;
        }

        if (!REGEX_DNI.test(valor)) {
            mostrarError(input, 'El DNI debe tener exactamente 8 dígitos');
            return false;
        }

        if (valor === '00000000' || valor === '11111111' || valor === '99999999') {
            mostrarError(input, 'DNI no válido');
            return false;
        }

        input.classList.add('campo-valido');
        return true;
    }

    function validarCampoTexto(input, tipo) {
        eliminarMensajeError(input);
        const valor = input.value.trim();

        if (valor === '') {
            mostrarError(input, `${tipo === 'nombre' ? 'El nombre' : 'Los apellidos'} son obligatorios`);
            return false;
        }

        if (!REGEX_NOMBRES.test(valor)) {
            mostrarError(input, 'Solo se permiten letras, espacios y acentos');
            return false;
        }

        if (valor.length < 2) {
            mostrarError(input, 'Debe tener al menos 2 caracteres');
            return false;
        }

        input.classList.add('campo-valido');
        return true;
    }

    function validarCampoCorreo(input) {
        eliminarMensajeError(input);
        const valor = input.value.trim();

        if (valor === '') {
            mostrarError(input, 'El correo es obligatorio');
            return false;
        }

        if (!REGEX_CORREO.test(valor)) {
            mostrarError(input, 'Formato de correo inválido (ej: usuario@dominio.com)');
            return false;
        }

        input.classList.add('campo-valido');
        return true;
    }

    function mostrarError(input, mensaje) {
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
    // Validación completa del formulario
    // =====================================================
    function validarFormularioCompleto() {
        let esValido = true;

        // Validar DNI
        if (!validarCampoDNI(inputDni)) {
            esValido = false;
        }

        // Validar nombre
        if (!validarCampoTexto(inputNombre, 'nombre')) {
            esValido = false;
        }

        // Validar apellidos
        if (!validarCampoTexto(inputApellidos, 'apellidos')) {
            esValido = false;
        }

        // Validar correo
        if (!validarCampoCorreo(inputCorreo)) {
            esValido = false;
        }

        // Validar rol
        if (!selectRol.value) {
            mostrarError(selectRol, 'Debe seleccionar un rol');
            esValido = false;
        }

        // Validar estado
        const selectEstado = form.querySelector('select[name="id_estado"]');
        if (!selectEstado.value) {
            mostrarError(selectEstado, 'Debe seleccionar un estado');
            esValido = false;
        }

        return esValido;
    }

    // =====================================================
    // Calcular días para cumpleaños
    // =====================================================
    function calcularDiasCumpleanos(fechaNacimiento) {
        if (!fechaNacimiento) return null;
    
        let fecha;
    
        if (typeof fechaNacimiento === "string" && /^\d{4}-\d{2}-\d{2}$/.test(fechaNacimiento)) {
            fecha = new Date(fechaNacimiento + "T00:00:00"); 
        }
        else if (typeof fechaNacimiento === "string" && /^\d{2}\/\d{2}\/\d{4}$/.test(fechaNacimiento)) {
            const [dia, mes, año] = fechaNacimiento.split('/').map(Number);
            fecha = new Date(año, mes - 1, dia);
        }
        else if (fechaNacimiento instanceof Date) {
            fecha = new Date(
                fechaNacimiento.getFullYear(),
                fechaNacimiento.getMonth(),
                fechaNacimiento.getDate()
            );
        } else {
            return "Formato de fecha inválido";
        }
    
        if (isNaN(fecha.getTime())) {
            return "Fecha inválida";
        }
    
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
    
        let proximoCumple = new Date(hoy.getFullYear(), fecha.getMonth(), fecha.getDate());
        proximoCumple.setHours(0, 0, 0, 0);
    
        if (proximoCumple < hoy) {
            proximoCumple.setFullYear(hoy.getFullYear() + 1);
        }
    
        const diferenciaDias = Math.ceil((proximoCumple - hoy) / (1000 * 60 * 60 * 24));
    
        if (diferenciaDias === 0) return "Hoy es su cumpleaños";
        if (diferenciaDias === 1) return "Mañana es su cumpleaños";
        return `Cumpleaños en ${diferenciaDias} días`;
    }

    // =====================================================
    // Nuevo personal
    // =====================================================
    btnNuevo.addEventListener('click', () => {
        modoEdicion = false;
        idEdicion = null;
        fotoActual = null;
        form.reset();
        infoDiv.style.display = 'none';
        
        // Limpiar validaciones anteriores
        form.querySelectorAll('.campo-invalido, .campo-valido').forEach(el => {
            el.classList.remove('campo-invalido', 'campo-valido');
        });
        form.querySelectorAll('.mensaje-error-campo').forEach(el => el.remove());
        
        if (inputFileWrapper) inputFileWrapper.style.display = 'block';
        if (labelFoto) {
            labelFoto.innerHTML = '<i class="ti ti-upload" style="font-size:13px;"></i> Subir foto';
            labelFoto.classList.remove('archivo-seleccionado');
        }
        
        preview.innerHTML = `<i class="ti ti-camera"></i>`;
        removeExistingCredentialBlock();
        removeExistingEditButton();
        removeExistingBirthdayBlock();

        if (tituloFormulario) tituloFormulario.textContent = 'Registrar nuevo personal';
        form.querySelector('.btn-guardar').innerHTML = '<i class="ti ti-device-floppy"></i> Guardar personal';
        form.querySelector('.btn-guardar').style.display = '';
        form.querySelectorAll('input, select').forEach(el => el.removeAttribute('disabled'));
        
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
        removeExistingCredentialBlock();
        removeExistingEditButton();
        removeExistingBirthdayBlock();
        fotoActual = null;
        modoEdicion = false;

        // Limpiar validaciones
        form.querySelectorAll('.campo-invalido, .campo-valido').forEach(el => {
            el.classList.remove('campo-invalido', 'campo-valido');
        });
        form.querySelectorAll('.mensaje-error-campo').forEach(el => el.remove());

        seccionNuevo.style.display = 'none';
        seccionDetalle.style.display = 'none';
        seccionListado.style.display = 'block';
        btnNuevo.style.display = 'inline-block';
        listarPersonal();
    }

    btnVolverListado.addEventListener('click', mostrarListado);
    if (btnVolverListado2) btnVolverListado2.addEventListener('click', mostrarListado);

    // =====================================================
    // Ver detalle
    // =====================================================
    function verDetalle(id) {
        fetch(`../CONTROLADORES/controlador_personal.php?accion=ver&id=${id}`)
            .then(res => res.json())
            .then(p => mostrarDetalle(p))
            .catch(err => console.error(err));
    }

    // =====================================================
    // Mostrar detalle
    // =====================================================
    function mostrarDetalle(p, mostrarCredenciales = false, credenciales = null) {
        if (!detalleDiv) return;

        const fmtFecha = f => {
            if (!f) return '—';
            const [y,m,d] = f.split('-');
            return `${d}/${m}/${y}`;
        };

        const iniciales = ((p.nombre||'').charAt(0) + (p.apellidos||'').charAt(0)).toUpperCase();
        const fotoSrc = p.foto ? `../${p.foto}` : null;
        const avatarHtml = fotoSrc
            ? `<img class="detalle-avatar" src="${fotoSrc}" alt="Foto"
                    onerror="this.outerHTML='<div class=\\'detalle-avatar\\'>${iniciales}</div>'">`
            : `<div class="detalle-avatar">${iniciales}</div>`;

        const estadoTexto = (p.id_estado == 1) ? 'Activo' : 'Inactivo';
        const estadoColor = (p.id_estado == 1) ? '#2e7d32' : '#9aa3b0';

        const credencialesHtml = (mostrarCredenciales && credenciales) ? `
            <div class="detalle-seccion campo-ancho">
                <h4>Credenciales generadas</h4>
                <div class="info-registro" style="margin-top:0;">
                    <p><strong>Usuario</strong></p>
                    <p id="codigoAsignadoDetalle">${credenciales.codigo}</p>
                    <p style="margin-top:8px;"><strong>Contraseña temporal</strong></p>
                    <p id="contrasenaAsignadaDetalle">${credenciales.contrasena}</p>
                    <button type="button" id="btnCopiarCredencialesDetalle" class="btn-secundario" style="width:100%;margin-top:10px;margin-bottom:0;">
                        <i class="ti ti-copy" style="font-size:13px;"></i> Copiar usuario y contraseña
                    </button>
                </div>
            </div>` : '';

        detalleDiv.innerHTML = `
            <div class="detalle-paciente-card">
                <div class="detalle-header">
                    ${avatarHtml}
                    <div class="detalle-header-info">
                        <p class="nombre">${p.nombre} ${p.apellidos}</p>
                        <p class="meta">${p.nombre_rol || ''} · Código ${p.codigo_usuario || '—'}</p>
                    </div>
                </div>

                <div class="detalle-body">
                    <div class="detalle-seccion">
                        <h4>Información personal</h4>
                        <div class="detalle-fila">
                            <span class="etiqueta">DNI</span>
                            <span class="valor">${p.dni || '—'}</span>
                        </div>
                        <div class="detalle-fila">
                            <span class="etiqueta">Fecha de nacimiento</span>
                            <span class="valor">${fmtFecha(p.fecha_nacimiento)}</span>
                        </div>
                        <div class="detalle-fila">
                            <span class="etiqueta">Estado</span>
                            <span class="valor" style="color:${estadoColor};">${estadoTexto}</span>
                        </div>
                    </div>

                    <div class="detalle-seccion">
                        <h4>Cuenta</h4>
                        <div class="detalle-fila">
                            <span class="etiqueta">Correo</span>
                            <span class="valor">${p.correo || '—'}</span>
                        </div>
                        <div class="detalle-fila">
                            <span class="etiqueta">Rol</span>
                            <span class="valor">${p.nombre_rol || '—'}</span>
                        </div>
                        <div class="detalle-fila">
                            <span class="etiqueta">Código de usuario</span>
                            <span class="valor">${p.codigo_usuario || '—'}</span>
                        </div>
                    </div>

                    ${credencialesHtml}
                </div>

                <div class="detalle-footer">
                    <button class="btn-principal" onclick="editarPersonal(${p.id_usuario})">
                        <i class="ti ti-edit"></i> Editar
                    </button>
                </div>
            </div>
        `;

        seccionListado.style.display = 'none';
        seccionNuevo.style.display   = 'none';
        seccionDetalle.style.display = 'block';
        btnNuevo.style.display       = 'none';

        idEdicion  = p.id_usuario;
        fotoActual = p.foto || null;

        if (mostrarCredenciales && credenciales) {
            const btnCopiar = document.getElementById('btnCopiarCredencialesDetalle');
            if (btnCopiar) {
                btnCopiar.addEventListener('click', () => {
                    navigator.clipboard.writeText(`Usuario: ${credenciales.codigo}\nContraseña: ${credenciales.contrasena}`);
                    mostrarMensajeSistema('Credenciales copiadas');
                });
            }
        }
    }

    // =====================================================
    // Editar personal
    // =====================================================
    function editarPersonal(p) {
        if (typeof p === 'number' || typeof p === 'string') {
            console.log('[personal] editarPersonal recibido id, haciendo fetch:', p);
            fetch(`../CONTROLADORES/controlador_personal.php?accion=ver&id=${p}`)
                .then(r => r.json()).then(per => { console.log('[personal] datos recibidos del fetch:', per); editarPersonal(per); }).catch(console.error);
            return;
        }

        console.log('[personal] editarPersonal con objeto completo, p.dni =', p.dni);

        modoEdicion = true;
        idEdicion = p.id_usuario || idEdicion;
        fotoActual = p.foto || fotoActual;
        if (tituloFormulario) tituloFormulario.textContent = 'Editar personal';

        form.querySelectorAll('input, select').forEach(el => el.removeAttribute('disabled'));

        // Llenar el formulario con los datos del personal
        if (form.nombre)            form.nombre.value = p.nombre || '';
        if (form.apellidos)         form.apellidos.value = p.apellidos || '';
        if (form.dni)                form.dni.value = p.dni || '';
        console.log('[personal] form.dni.value después de asignar:', form.dni ? form.dni.value : 'form.dni no existe');
        if (form.correo)            form.correo.value = p.correo || '';
        if (form.fecha_nacimiento)  form.fecha_nacimiento.value = p.fecha_nacimiento || '';
        if (form.id_rol)            form.id_rol.value = p.id_rol || '';
        if (form.id_estado)         form.id_estado.value = p.id_estado || '';

        // Limpiar marcas de validación de una sesión anterior
        form.querySelectorAll('.campo-invalido, .campo-valido').forEach(el => el.classList.remove('campo-invalido', 'campo-valido'));
        form.querySelectorAll('.mensaje-error-campo').forEach(el => el.remove());

        // Mostrar foto actual si existe
        if (p.foto) {
            preview.innerHTML = `<img src="../${p.foto}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
        } else {
            preview.innerHTML = `<i class="ti ti-camera"></i>`;
        }

        if (infoDiv) infoDiv.style.display = 'none';

        fotoInput.removeAttribute('disabled');

        if (inputFileWrapper) inputFileWrapper.style.display = 'block';
        if (labelFoto) {
            labelFoto.innerHTML = '<i class="ti ti-upload" style="font-size:13px;"></i> Cambiar foto';
            labelFoto.classList.remove('archivo-seleccionado');
        }

        const btnGuardar = form.querySelector('.btn-guardar');
        if (btnGuardar) {
            btnGuardar.innerHTML = '<i class="ti ti-device-floppy"></i> Actualizar personal';
            btnGuardar.style.display = '';
        }

        // Mostrar la sección del formulario y ocultar las demás
        seccionListado.style.display = 'none';
        seccionDetalle.style.display = 'none';
        seccionNuevo.style.display   = 'block';

        btnNuevo.style.display = 'none';
        removeExistingEditButton();
    }

    // =====================================================
    // Guardar / actualizar
    // =====================================================
    form.addEventListener('submit', e => {
        e.preventDefault();

        // Validar formulario completo
        if (!validarFormularioCompleto()) {
            mostrarMensajeSistema('Por favor, corrija los errores del formulario', 'error');
            return;
        }

        const datos = new FormData(form);

        if (modoEdicion) {
            datos.append('accion', 'editar');
            datos.append('id_usuario', idEdicion);
            
            if (!fotoInput.files || fotoInput.files.length === 0) {
                datos.append('foto_actual', fotoActual || '');
            }
        } else {
            datos.append('accion', 'registrar');
        }

        fetch('../CONTROLADORES/controlador_personal.php', { method: 'POST', body: datos })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (modoEdicion) {
                        mostrarMensajeSistema('Datos actualizados correctamente');
                        fetch(`../CONTROLADORES/controlador_personal.php?accion=ver&id=${idEdicion}`)
                            .then(res => res.json())
                            .then(p => {
                                mostrarDetalle(p, false);
                                modoEdicion = false;
                                fotoActual = null;
                            });
                    } else {
                        mostrarMensajeSistema('Personal registrado correctamente', 'exito');
                        fetch(`../CONTROLADORES/controlador_personal.php?accion=ver&id=${data.id_usuario}`)
                            .then(res => res.json())
                            .then(p => {
                                mostrarDetalle(p, true, {
                                    codigo: data.codigo,
                                    contrasena: data.contrasena
                                });
                                modoEdicion = false;
                            });
                    }
                } else {
                    mostrarMensajeSistema(`${data.mensaje || 'Error al guardar'}`, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                mostrarMensajeSistema('Error en la petición', 'error');
            });
    });

    // =====================================================
    // Vista previa foto
    // =====================================================
    fotoInput.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) {
            if (labelFoto) {
                labelFoto.textContent = file.name;
                labelFoto.classList.add('archivo-seleccionado');
            }
            
            const reader = new FileReader();
            reader.onload = e2 => {
                preview.innerHTML = `<img src="${e2.target.result}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
            };
            reader.readAsDataURL(file);
        } else {
            if (labelFoto) {
                labelFoto.innerHTML = '<i class="ti ti-upload" style="font-size:13px;"></i> Subir foto';
                labelFoto.classList.remove('archivo-seleccionado');
            }
        }
    });

    // =====================================================
    // Copiar usuario y contraseña
    // =====================================================
    if (btnCopiarTodo) {
        btnCopiarTodo.addEventListener('click', () => {
            navigator.clipboard.writeText(
                `Usuario: ${codigoSpan.textContent}\nContraseña: ${contrasenaSpan.textContent}`
            );
            mostrarMensajeSistema('Datos copiados');
        });
    }

    // =====================================================
    // Mensaje del sistema
    // =====================================================
    function mostrarMensajeSistema(msg, tipo = "exito") {
        const aviso = document.createElement("div");
        aviso.className = `mensaje-sistema ${tipo}`;
        aviso.innerHTML = `<span class="texto">${msg}</span>`;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.classList.add("mostrar"), 100);
        setTimeout(() => {
            aviso.classList.remove("mostrar");
            setTimeout(() => aviso.remove(), 400);
        }, 3000);
    }

    // =====================================================
    // Helpers
    // =====================================================
    function removeExistingCredentialBlock() {
        const prev = document.querySelector('.bloque-credenciales');
        if (prev) prev.remove();
    }

    function appendCredentialBlock(credenciales) {
        removeExistingCredentialBlock();
        const bloque = document.createElement('div');
        bloque.classList.add('bloque-credenciales');
        bloque.innerHTML = `
            <h4>Credenciales generadas</h4>
            <p><strong>Usuario:</strong> ${credenciales.codigo}</p>
            <p><strong>Contraseña:</strong> ${credenciales.contrasena}</p>
            <button id="btnCopiarCredenciales" class="btn-secundario"><i class="ti ti-copy"></i> Copiar</button>
        `;
        preview.parentElement.appendChild(bloque);
        const btn = bloque.querySelector('#btnCopiarCredenciales');
        if (btn) btn.addEventListener('click', () => {
            navigator.clipboard.writeText(`Usuario: ${credenciales.codigo}\nContraseña: ${credenciales.contrasena}`);
            mostrarMensajeSistema('Credenciales copiadas');
        });
    }

    function removeExistingBirthdayBlock() {
        const prev = document.querySelector('.bloque-cumpleanos');
        if (prev) prev.remove();
    }

    function appendBirthdayBlock(fechaNacimiento) {
        removeExistingBirthdayBlock();
        const mensaje = calcularDiasCumpleanos(fechaNacimiento);
        if (!mensaje) return;

        const bloque = document.createElement('div');
        bloque.classList.add('bloque-cumpleanos');
        bloque.innerHTML = `
            <p class="cumple-texto">${mensaje}</p>
        `;
        
        const grupoFoto = preview.parentElement;
        if (inputFileWrapper) {
            grupoFoto.insertBefore(bloque, inputFileWrapper);
        } else {
            grupoFoto.appendChild(bloque);
        }
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
        btn.addEventListener('click', () => editarPersonal(p));
        let columnaIzq = preview.closest('.columna-izquierda') || preview.parentElement;
        columnaIzq.appendChild(btn);
    }

    // Exponer función usada en onclick="" inline del HTML generado dinámicamente
    window.editarPersonal = editarPersonal;
}