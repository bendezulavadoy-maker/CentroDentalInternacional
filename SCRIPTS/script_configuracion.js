function iniciarModuloConfiguracion() {

    // ── TABS ──────────────────────────────────────────────────────
    const tabBtns      = document.querySelectorAll('.tab-btn');
    const tabSecciones = document.querySelectorAll('.tab-contenido');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('activo'));
            tabSecciones.forEach(s => s.classList.remove('activo'));
            btn.classList.add('activo');
            const seccion = document.getElementById('tab-' + btn.dataset.tab);
            if (seccion) {
                seccion.classList.add('activo');
                // Expandir el contenedor al tamaño real del contenido
                const cont = document.querySelector('.contenedor-config');
                if (cont) {
                    // Reset para medir correctamente
                    cont.style.minHeight = '';
                    // Forzar repaint y luego ajustar
                    setTimeout(() => {
                        const needed = seccion.getBoundingClientRect().height;
                        const current = cont.getBoundingClientRect().height;
                        if (needed > current - 80) {
                            cont.style.paddingBottom = (needed + 60) + 'px';
                        }
                        seccion.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 50);
                }
            }

            if (btn.dataset.tab === 'servicios' && !btn.dataset.iniciado) {
                btn.dataset.iniciado = '1';
                iniciarSeccionServicios();
            }
            if (btn.dataset.tab === 'horarios') {
                setTimeout(() => {
                    if (typeof window._cargarHorariosSelectors === 'function') {
                        window._cargarHorariosSelectors();
                    }
                }, 80);
            }
            if (btn.dataset.tab === 'planes' && !btn.dataset.iniciado) {
                btn.dataset.iniciado = '1';
                iniciarSeccionPlanes();
            }
            if (btn.dataset.tab === 'aparatologia' && !btn.dataset.iniciado) {
                btn.dataset.iniciado = '1';
                iniciarSeccionAparatologia();
            }
        });
    });

    // ── Cargar al iniciar ─────────────────────────────────────────
    cargarRoles();
    cargarPermisos();
    cargarSedes();
    iniciarSeccionTiposAtencion();
    iniciarSeccionHorarios();

    // =============================================================
    // 🎭 ROLES
    // =============================================================
    const tablaRolesBody   = document.querySelector('#tablaRoles tbody');
    const formRolContenedor = document.getElementById('formRolContenedor');
    const tituloFormRol    = document.getElementById('tituloFormRol');
    const inputNombreRol   = document.getElementById('inputNombreRol');
    const btnNuevoRol      = document.getElementById('btnNuevoRol');
    const btnGuardarRol    = document.getElementById('btnGuardarRol');
    const btnCancelarRol   = document.getElementById('btnCancelarRol');

    let editandoRolId = null;

    function cargarRoles() {
        fetch('../CONTROLADORES/controlador_configuracion.php?accion=listar_roles')
            .then(r => r.json())
            .then(roles => {
                tablaRolesBody.innerHTML = '';
                roles.forEach(r => {
                    const esBase = r.id_rol <= 3;
                    tablaRolesBody.innerHTML += `
                        <tr>
                            <td>${r.id_rol}</td>
                            <td>${r.nombre_rol}</td>
                            <td>
                                <button class="btn-editar-rol btn-accion" data-id="${r.id_rol}" data-nombre="${r.nombre_rol}">✏️ Editar</button>
                                ${!esBase ? `<button class="btn-eliminar-rol btn-accion btn-danger" data-id="${r.id_rol}">🗑️ Eliminar</button>` : '<span class="etiqueta-base">Base</span>'}
                            </td>
                        </tr>`;
                });
                // Eventos de editar
                document.querySelectorAll('.btn-editar-rol').forEach(btn => {
                    btn.addEventListener('click', () => {
                        editandoRolId       = parseInt(btn.dataset.id);
                        inputNombreRol.value = btn.dataset.nombre;
                        tituloFormRol.textContent = 'Editar Rol';
                        formRolContenedor.style.display = 'block';
                    });
                });
                // Eventos de eliminar
                document.querySelectorAll('.btn-eliminar-rol').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (!confirm('¿Seguro que deseas eliminar este rol?')) return;
                        const fd = new FormData();
                        fd.append('accion', 'eliminar_rol');
                        fd.append('id_rol', btn.dataset.id);
                        fetch('../CONTROLADORES/controlador_configuracion.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(data => {
                                mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
                                if (data.success) { cargarRoles(); cargarPermisos(); }
                            });
                    });
                });
            });
    }

    btnNuevoRol.addEventListener('click', () => {
        editandoRolId        = null;
        inputNombreRol.value = '';
        tituloFormRol.textContent = 'Nuevo Rol';
        formRolContenedor.style.display = 'block';
        inputNombreRol.focus();
    });

    btnCancelarRol.addEventListener('click', () => {
        formRolContenedor.style.display = 'none';
        inputNombreRol.value = '';
        editandoRolId = null;
    });

    btnGuardarRol.addEventListener('click', () => {
        const nombre = inputNombreRol.value.trim();
        if (!nombre) { mostrarMensaje('El nombre del rol es obligatorio', 'error'); return; }

        const fd = new FormData();
        fd.append('nombre_rol', nombre);

        if (editandoRolId) {
            fd.append('accion', 'editar_rol');
            fd.append('id_rol', editandoRolId);
        } else {
            fd.append('accion', 'crear_rol');
        }

        fetch('../CONTROLADORES/controlador_configuracion.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
                if (data.success) {
                    formRolContenedor.style.display = 'none';
                    inputNombreRol.value = '';
                    editandoRolId = null;
                    cargarRoles();
                    cargarPermisos();
                }
            });
    });

    // =============================================================
    // 🔐 PERMISOS
    // =============================================================
    const tablaPermisos    = document.getElementById('tablaPermisos');
    const btnGuardarPermisos = document.getElementById('btnGuardarPermisos');

    function cargarPermisos() {
        fetch('../CONTROLADORES/controlador_configuracion.php?accion=listar_permisos')
            .then(r => r.json())
            .then(data => {
                const { roles, modulos, mapa } = data;

                let html = '<table id="matrizPermisos"><thead><tr><th>Módulo</th>';
                roles.forEach(r => { html += `<th>${r.nombre_rol}</th>`; });
                html += '</tr></thead><tbody>';

                Object.entries(modulos).forEach(([clave, etiqueta]) => {
                    html += `<tr><td>${etiqueta}</td>`;
                    roles.forEach(r => {
                        const tiene = mapa[r.id_rol] && mapa[r.id_rol].includes(clave);
                        html += `<td style="text-align:center;">
                            <input type="checkbox" 
                                   class="chk-permiso" 
                                   data-rol="${r.id_rol}" 
                                   data-modulo="${clave}"
                                   ${tiene ? 'checked' : ''}>
                        </td>`;
                    });
                    html += '</tr>';
                });

                html += '</tbody></table>';
                tablaPermisos.innerHTML = html;
            });
    }

    btnGuardarPermisos.addEventListener('click', () => {
        const checkboxes = document.querySelectorAll('.chk-permiso');
        const permisos   = [];

        checkboxes.forEach(chk => {
            if (chk.checked) {
                permisos.push({ id_rol: parseInt(chk.dataset.rol), modulo: chk.dataset.modulo });
            }
        });

        fetch('../CONTROLADORES/controlador_configuracion.php?accion=guardar_permisos', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ permisos })
        })
            .then(r => r.json())
            .then(data => {
                mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
            });
    });

    // =============================================================
    // 🏥 SEDES
    // =============================================================
    const tablaSedesBody     = document.querySelector('#tablaSedes tbody');
    const formSedeContenedor = document.getElementById('formSedeContenedor');
    const tituloFormSede     = document.getElementById('tituloFormSede');
    const inputNombreSede    = document.getElementById('inputNombreSede');
    const inputDireccionSede = document.getElementById('inputDireccionSede');
    const inputTelefonoSede  = document.getElementById('inputTelefonoSede');
    const selectActivoSede   = document.getElementById('selectActivoSede');
    const btnNuevaSede       = document.getElementById('btnNuevaSede');
    const btnGuardarSede     = document.getElementById('btnGuardarSede');
    const btnCancelarSede    = document.getElementById('btnCancelarSede');

    let editandoSedeId = null;

    function cargarSedes() {
        fetch('../CONTROLADORES/controlador_configuracion.php?accion=listar_sedes')
            .then(r => r.json())
            .then(sedes => {
                tablaSedesBody.innerHTML = '';
                sedes.forEach(s => {
                    tablaSedesBody.innerHTML += `
                        <tr>
                            <td>${s.id_sede_atencion}</td>
                            <td>${s.nombre_sede}</td>
                            <td>${s.direccion_sede}</td>
                            <td>${s.telefono_sede || '—'}</td>
                            <td><span class="badge-estado ${s.activo == 1 ? 'activo' : 'inactivo'}">${s.activo == 1 ? 'Activo' : 'Inactivo'}</span></td>
                            <td>
                                <button class="btn-editar-sede btn-accion" 
                                    data-id="${s.id_sede_atencion}"
                                    data-nombre="${s.nombre_sede}"
                                    data-dir="${s.direccion_sede}"
                                    data-tel="${s.telefono_sede || ''}"
                                    data-activo="${s.activo}">✏️ Editar</button>
                            </td>
                        </tr>`;
                });
                document.querySelectorAll('.btn-editar-sede').forEach(btn => {
                    btn.addEventListener('click', () => {
                        editandoSedeId             = parseInt(btn.dataset.id);
                        inputNombreSede.value      = btn.dataset.nombre;
                        inputDireccionSede.value   = btn.dataset.dir;
                        inputTelefonoSede.value    = btn.dataset.tel;
                        selectActivoSede.value     = btn.dataset.activo;
                        tituloFormSede.textContent = 'Editar Sede';
                        formSedeContenedor.style.display = 'block';
                    });
                });
            });
    }

    btnNuevaSede.addEventListener('click', () => {
        editandoSedeId             = null;
        inputNombreSede.value      = '';
        inputDireccionSede.value   = '';
        inputTelefonoSede.value    = '';
        selectActivoSede.value     = '1';
        tituloFormSede.textContent = 'Nueva Sede';
        formSedeContenedor.style.display = 'block';
        inputNombreSede.focus();
    });

    btnCancelarSede.addEventListener('click', () => {
        formSedeContenedor.style.display = 'none';
        editandoSedeId = null;
    });

    btnGuardarSede.addEventListener('click', () => {
        const nombre    = inputNombreSede.value.trim();
        const direccion = inputDireccionSede.value.trim();
        const telefono  = inputTelefonoSede.value.trim();
        const activo    = selectActivoSede.value;

        if (!nombre || !direccion) {
            mostrarMensaje('Nombre y dirección son obligatorios', 'error');
            return;
        }
        if (telefono && !/^[0-9]{9}$/.test(telefono)) {
            mostrarMensaje('El teléfono debe tener exactamente 9 dígitos', 'error');
            return;
        }

        const fd = new FormData();
        fd.append('nombre_sede',    nombre);
        fd.append('direccion_sede', direccion);
        fd.append('telefono_sede',  telefono);
        fd.append('activo',         activo);

        if (editandoSedeId) {
            fd.append('accion',   'editar_sede');
            fd.append('id_sede',  editandoSedeId);
        } else {
            fd.append('accion', 'crear_sede');
        }

        fetch('../CONTROLADORES/controlador_configuracion.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
                if (data.success) {
                    formSedeContenedor.style.display = 'none';
                    editandoSedeId = null;
                    cargarSedes();
                }
            });
    });

    // =============================================================
    // 🪧 Notificación
    // =============================================================
    function mostrarMensaje(msg, tipo = 'exito') {
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        aviso.innerHTML = `
            <span class="icono">${tipo === 'exito' ? '' : ''}</span>
            <span class="texto">${msg}</span>`;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.classList.add('mostrar'), 100);
        setTimeout(() => {
            aviso.classList.remove('mostrar');
            setTimeout(() => aviso.remove(), 400);
        }, 3000);
    }
    // =============================================================
    // SERVICIOS — se inicializa solo cuando se activa la pestaña
    // =============================================================
    function iniciarSeccionServicios() {
        const tablaServiciosBody     = document.querySelector('#tablaServicios tbody');
        const formServicioContenedor = document.getElementById('formServicioContenedor');
        const inputNombreServicio    = document.getElementById('inputNombreServicio');
        const inputPrecioServicio    = document.getElementById('inputPrecioServicio');
        const selectActivoServicio   = document.getElementById('selectActivoServicio');
        const btnNuevoServicio       = document.getElementById('btnNuevoServicio');
        const btnGuardarServicio     = document.getElementById('btnGuardarServicio');
        const btnCancelarServicio    = document.getElementById('btnCancelarServicio');

        let editandoServicioId = null;

        function cargarServicios() {
            fetch('../CONTROLADORES/controlador_configuracion.php?accion=listar_servicios')
                .then(r => r.json())
                .then(servicios => {
                    tablaServiciosBody.innerHTML = '';
                    servicios.forEach(s => {
                        tablaServiciosBody.innerHTML += `
                            <tr>
                                <td>${s.nombre_servicio}</td>
                                <td>S/ ${parseFloat(s.precio_base || 0).toFixed(2)}</td>
                                <td><span class="badge-estado ${s.activo == 1 ? 'activo' : 'inactivo'}">
                                    ${s.activo == 1 ? 'Activo' : 'Inactivo'}
                                </span></td>
                                <td>
                                    <button class="btn-editar-srv btn-accion"
                                        data-id="${s.id_tipo_servicio}"
                                        data-nombre="${s.nombre_servicio}"
                                        data-precio="${s.precio_base}"
                                        data-activo="${s.activo}">Editar</button>
                                </td>
                            </tr>`;
                    });
                    document.querySelectorAll('.btn-editar-srv').forEach(btn => {
                        btn.onclick = () => {
                            editandoServicioId         = btn.dataset.id;
                            inputNombreServicio.value  = btn.dataset.nombre;
                            inputPrecioServicio.value  = parseFloat(btn.dataset.precio).toFixed(2);
                            selectActivoServicio.value = btn.dataset.activo;
                            document.getElementById('tituloFormServicio').textContent = 'Editar Servicio';
                            formServicioContenedor.style.display = 'block';
                        };
                    });
                });
        }

        btnNuevoServicio.onclick = () => {
            editandoServicioId         = null;
            inputNombreServicio.value  = '';
            inputPrecioServicio.value  = '';
            selectActivoServicio.value = '1';
            document.getElementById('tituloFormServicio').textContent = 'Nuevo Servicio';
            formServicioContenedor.style.display = 'block';
            inputNombreServicio.focus();
        };

        btnCancelarServicio.onclick = () => {
            formServicioContenedor.style.display = 'none';
            editandoServicioId = null;
        };

        btnGuardarServicio.onclick = () => {
            const nombre = inputNombreServicio.value.trim();
            if (!nombre) { mostrarMensaje('El nombre es obligatorio', 'error'); return; }

            const fd = new FormData();
            fd.append('nombre_servicio', nombre);
            fd.append('precio_base',     inputPrecioServicio.value || 0);
            fd.append('activo',          selectActivoServicio.value);

            if (editandoServicioId) {
                fd.append('accion',           'editar_servicio');
                fd.append('id_tipo_servicio', editandoServicioId);
            } else {
                fd.append('accion', 'crear_servicio');
            }

            fetch('../CONTROLADORES/controlador_configuracion.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
                    if (data.success) {
                        formServicioContenedor.style.display = 'none';
                        editandoServicioId = null;
                        cargarServicios();
                    }
                });
        };

        cargarServicios();
    }

    // =============================================================
    // PLANES — se inicializa solo cuando se activa la pestaña
    // =============================================================
    function iniciarSeccionPlanes() {
        const listaPlanesConfig    = document.getElementById('listaPlanesConfig');
        const modalPlanConfig      = document.getElementById('modalPlanConfig');
        const btnNuevoPlan         = document.getElementById('btnNuevoPlan');
        const btnGuardarPlan       = document.getElementById('btnGuardarPlan');
        const btnCerrarModalPlan   = document.getElementById('btnCerrarModalPlan');
        const btnCancelarModalPlan = document.getElementById('btnCancelarModalPlan');
    
        let editandoPlanId = null;
    
        function cargarPlanesConfig() {
            fetch('../CONTROLADORES/controlador_configuracion.php?accion=listar_planes_config')
                .then(r => r.json())
                .then(planes => {
                    if (!planes.length) {
                        listaPlanesConfig.innerHTML = '<p class="cargando">No hay planes creados.</p>';
                        return;
                    }
                    listaPlanesConfig.innerHTML = planes.map(p => `
                        <div class="plan-config-item ${p.activo == 0 ? 'inactivo' : ''}">
                            <div class="plan-config-info">
                                <span class="plan-config-nombre">${p.nombre_plan}</span>
                                ${p.descripcion
                                    ? `<span class="plan-config-desc">${p.descripcion}</span>`
                                    : ''}
                                ${p.costo_referencial
                                    ? `<span class="plan-config-costo">
                                         Ref: S/ ${parseFloat(p.costo_referencial).toFixed(2)}
                                       </span>`
                                    : ''}
                            </div>
                            <div class="plan-config-acciones">
                                <span class="badge-estado ${p.activo == 1 ? 'activo' : 'inactivo'}">
                                    ${p.activo == 1 ? 'Activo' : 'Inactivo'}
                                </span>
                                <button class="btn-accion btn-editar-plan"
                                        data-id="${p.id_plan}">Editar</button>
                                <button class="btn-accion btn-danger btn-eliminar-plan"
                                        data-id="${p.id_plan}">Eliminar</button>
                            </div>
                        </div>`).join('');
    
                    document.querySelectorAll('.btn-editar-plan').forEach(btn => {
                        btn.onclick = () => {
                            const plan = planes.find(p => p.id_plan == btn.dataset.id);
                            if (!plan) return;
                            editandoPlanId = plan.id_plan;
                            document.getElementById('tituloModalPlan').textContent  = 'Editar Plan';
                            document.getElementById('planNombre').value             = plan.nombre_plan;
                            document.getElementById('planDescripcion').value        = plan.descripcion || '';
                            document.getElementById('planCostoRef').value           = plan.costo_referencial || '';
                            document.getElementById('planActivo').value             = plan.activo;
                            modalPlanConfig.style.display = 'flex';
                        };
                    });
    
                    document.querySelectorAll('.btn-eliminar-plan').forEach(btn => {
                        btn.onclick = () => {
                            if (!confirm('¿Eliminar este plan?')) return;
                            const fd = new FormData();
                            fd.append('accion',  'eliminar_plan');
                            fd.append('id_plan', btn.dataset.id);
                            fetch('../CONTROLADORES/controlador_configuracion.php',
                                  { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(data => {
                                    mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
                                    if (data.success) cargarPlanesConfig();
                                });
                        };
                    });
                });
        }
    
        btnNuevoPlan.onclick = () => {
            editandoPlanId = null;
            document.getElementById('tituloModalPlan').textContent = 'Nuevo Plan de Tratamiento';
            document.getElementById('planNombre').value            = '';
            document.getElementById('planDescripcion').value       = '';
            document.getElementById('planCostoRef').value          = '';
            document.getElementById('planActivo').value            = '1';
            modalPlanConfig.style.display = 'flex';
        };
    
        btnGuardarPlan.onclick = () => {
            const nombre = document.getElementById('planNombre').value.trim();
            if (!nombre) { mostrarMensaje('El nombre del plan es obligatorio', 'error'); return; }
    
            const datos = {
                id_plan:          editandoPlanId,
                nombre_plan:      nombre,
                descripcion:      document.getElementById('planDescripcion').value.trim(),
                costo_referencial: document.getElementById('planCostoRef').value || null,
                activo:           document.getElementById('planActivo').value,
                pasos:            []  // sin pasos en el catálogo
            };
    
            fetch('../CONTROLADORES/controlador_configuracion.php?accion=guardar_plan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datos)
            })
                .then(r => r.json())
                .then(data => {
                    mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
                    if (data.success) {
                        modalPlanConfig.style.display = 'none';
                        cargarPlanesConfig();
                    }
                });
        };
    
        [btnCerrarModalPlan, btnCancelarModalPlan].forEach(btn => {
            btn.onclick = () => { modalPlanConfig.style.display = 'none'; };
        });
    
        cargarPlanesConfig();
    }
    function iniciarSeccionAparatologia() {
        const tablaBody    = document.querySelector('#tablaAparatologia tbody');
        const formConten   = document.getElementById('formAparatologiaContenedor');
        const inputNombre  = document.getElementById('inputNombreAparatologia');
        const inputPrecio  = document.getElementById('inputPrecioAparatologia');
        const selectActivo = document.getElementById('selectActivoAparatologia');
        const btnNuevo     = document.getElementById('btnNuevaAparatologia');
        const btnGuardar   = document.getElementById('btnGuardarAparatologia');
        const btnCancelar  = document.getElementById('btnCancelarAparatologia');

        let editandoId = null;

        function cargar() {
            fetch('../CONTROLADORES/controlador_configuracion.php?accion=listar_aparatologia')
                .then(r => r.json())
                .then(items => {
                    tablaBody.innerHTML = '';
                    if (!items.length) {
                        tablaBody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#95a5a6;">Sin registros</td></tr>';
                        return;
                    }
                    items.forEach(a => {
                        tablaBody.innerHTML += `
                            <tr>
                                <td>${a.nombre}</td>
                                <td>S/ ${parseFloat(a.precio_base || 0).toFixed(2)}</td>
                                <td><span class="badge-estado ${a.activo == 1 ? 'activo' : 'inactivo'}">
                                    ${a.activo == 1 ? 'Activo' : 'Inactivo'}
                                </span></td>
                                <td>
                                    <button class="btn-editar-apat btn-accion"
                                        data-id="${a.id_aparatologia}"
                                        data-nombre="${a.nombre}"
                                        data-precio="${a.precio_base}"
                                        data-activo="${a.activo}">Editar</button>
                                </td>
                            </tr>`;
                    });
                    document.querySelectorAll('.btn-editar-apat').forEach(btn => {
                        btn.onclick = () => {
                            editandoId         = btn.dataset.id;
                            inputNombre.value  = btn.dataset.nombre;
                            inputPrecio.value  = parseFloat(btn.dataset.precio).toFixed(2);
                            selectActivo.value = btn.dataset.activo;
                            document.getElementById('tituloFormAparatologia').textContent = 'Editar Aparatología';
                            formConten.style.display = 'block';
                        };
                    });
                });
        }

        btnNuevo.onclick = () => {
            editandoId         = null;
            inputNombre.value  = '';
            inputPrecio.value  = '';
            selectActivo.value = '1';
            document.getElementById('tituloFormAparatologia').textContent = 'Nueva Aparatología';
            formConten.style.display = 'block';
            inputNombre.focus();
        };

        btnCancelar.onclick = () => {
            formConten.style.display = 'none';
            editandoId = null;
        };

        btnGuardar.onclick = () => {
            const nombre = inputNombre.value.trim();
            if (!nombre) { mostrarMensaje('El nombre es obligatorio', 'error'); return; }

            const fd = new FormData();
            fd.append('nombre_aparatologia', nombre);
            fd.append('precio_base',         inputPrecio.value || 0);
            fd.append('activo',              selectActivo.value);

            if (editandoId) {
                fd.append('accion',           'editar_aparatologia');
                fd.append('id_aparatologia',  editandoId);
            } else {
                fd.append('accion', 'crear_aparatologia');
            }

            fetch('../CONTROLADORES/controlador_configuracion.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    mostrarMensaje(data.mensaje, data.success ? 'exito' : 'error');
                    if (data.success) {
                        formConten.style.display = 'none';
                        editandoId = null;
                        cargar();
                    }
                });
        };

        cargar();
    }

    // ══════════════════════════════════════════════════════════════
    // TIPOS DE ATENCIÓN
    // ══════════════════════════════════════════════════════════════
    function iniciarSeccionTiposAtencion() {
        const CTRL = '../CONTROLADORES/controlador_configuracion.php';

        function cargarTipos() {
            fetch(`${CTRL}?accion=listar_tipos_atencion`)
                .then(r => r.json())
                .then(lista => {
                    const tbody = document.querySelector('#tablaTiposAtencion tbody');
                    if (!tbody) return;
                    tbody.innerHTML = lista.map(t => `
                        <tr>
                            <td>${t.nombre}</td>
                            <td>${t.duracion_minutos} min</td>
                            <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:${t.color};border:1px solid #ddd;vertical-align:middle;"></span> ${t.color}</td>
                            <td>${parseInt(t.activo) ? 'Activo' : 'Inactivo'}</td>
                            <td>
                                <button class="btn-editar-tipo btn-accion"
                                    data-id="${t.id_tipo_atencion}"
                                    data-nombre="${t.nombre}"
                                    data-duracion="${t.duracion_minutos}"
                                    data-color="${t.color}"
                                    data-activo="${t.activo}">Editar</button>
                            </td>
                        </tr>`).join('');

                    tbody.querySelectorAll('.btn-editar-tipo').forEach(btn => {
                        btn.addEventListener('click', () => {
                            document.getElementById('idTipoAtencionEditar').value    = btn.dataset.id;
                            document.getElementById('inputNombreTipoAtencion').value = btn.dataset.nombre;
                            document.getElementById('selectDuracionTipoAtencion').value = btn.dataset.duracion;
                            document.getElementById('inputColorTipoAtencion').value  = btn.dataset.color;
                            document.getElementById('checkActivoTipoAtencion').checked = btn.dataset.activo === '1';
                            document.getElementById('grupoActivoTipoAtencion').style.display = 'block';
                            document.getElementById('tituloFormTipoAtencion').textContent = 'Editar Tipo';
                            document.getElementById('formTipoAtencionContenedor').style.display = 'block';
                        });
                    });
                });
        }

        document.getElementById('btnNuevoTipoAtencion')?.addEventListener('click', () => {
            document.getElementById('idTipoAtencionEditar').value = '';
            document.getElementById('inputNombreTipoAtencion').value = '';
            document.getElementById('selectDuracionTipoAtencion').value = '60';
            document.getElementById('inputColorTipoAtencion').value = '#2a4d8f';
            document.getElementById('checkActivoTipoAtencion').checked = true;
            document.getElementById('grupoActivoTipoAtencion').style.display = 'none';
            document.getElementById('tituloFormTipoAtencion').textContent = 'Nuevo Tipo de Atención';
            document.getElementById('formTipoAtencionContenedor').style.display = 'block';
        });

        document.getElementById('btnCancelarTipoAtencion')?.addEventListener('click', () => {
            document.getElementById('formTipoAtencionContenedor').style.display = 'none';
        });

        document.getElementById('btnGuardarTipoAtencion')?.addEventListener('click', () => {
            const id      = document.getElementById('idTipoAtencionEditar').value;
            const nombre  = document.getElementById('inputNombreTipoAtencion').value.trim();
            const duracion = document.getElementById('selectDuracionTipoAtencion').value;
            const color   = document.getElementById('inputColorTipoAtencion').value;
            const activo  = document.getElementById('checkActivoTipoAtencion').checked ? 1 : 0;

            if (!nombre) { mostrarMensaje('El nombre es obligatorio', 'error'); return; }

            const fd = new FormData();
            fd.append('nombre', nombre);
            fd.append('duracion_minutos', duracion);
            fd.append('color', color);
            fd.append('activo', activo);

            if (id) {
                fd.append('accion', 'editar_tipo_atencion');
                fd.append('id_tipo_atencion', id);
            } else {
                fd.append('accion', 'crear_tipo_atencion');
            }

            fetch(CTRL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensaje('Guardado correctamente');
                        document.getElementById('formTipoAtencionContenedor').style.display = 'none';
                        cargarTipos();
                    } else {
                        mostrarMensaje(data.mensaje || 'Error al guardar', 'error');
                    }
                });
        });

        // Cargar al activar la pestaña
        document.querySelector('[data-tab="tipos_atencion"]')?.addEventListener('click', cargarTipos);
    }

    // ══════════════════════════════════════════════════════════════
    // HORARIOS
    // ══════════════════════════════════════════════════════════════
    function iniciarSeccionHorarios() {
        const CTRL = '../CONTROLADORES/controlador_configuracion.php';
        let doctorActual = null;
        let semanaActual = null;
        let horariosActuales = [];
        let bloqueosActuales = [];

        function lunes(fecha) {
            const d = new Date(fecha + 'T00:00:00');
            const day = d.getDay();
            const diff = (day === 0 ? -6 : 1 - day);
            d.setDate(d.getDate() + diff);
            return d.toISOString().split('T')[0];
        }

        function fmtFecha(str) {
            if (!str) return '';
            const [y,m,d] = str.split('-');
            return `${d}/${m}`;
        }

        function fmtDia(str) {
            const dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
            return dias[new Date(str + 'T00:00:00').getDay()];
        }

        // Cargar selectores de horario - se llama tras el cambio de tab
        function cargarSelectoresHorario() {
            const selDoc  = document.getElementById('selectDoctorHorario');
            const selSede = document.getElementById('selectSedeHorario');
            if (!selDoc) return;

            // Solo cargar doctores una vez
            if (!selDoc.dataset.cargado) {
                selDoc.dataset.cargado = '1';
                fetch(`${CTRL}?accion=listar_doctores`)
                    .then(r => r.json())
                    .then(lista => {
                        const buscar   = document.getElementById('buscarDoctorHorario');
                        const dropdown = document.getElementById('dropdownDoctores');
                        if (!buscar || !dropdown) return;

                        buscar.addEventListener('input', () => {
                            const q = buscar.value.toLowerCase().trim();
                            if (!q) { dropdown.style.display = 'none'; selDoc.value = ''; doctorActual = null; return; }
                            const filtrados = lista.filter(d =>
                                d.nombre_completo.toLowerCase().includes(q) ||
                                (d.dni && d.dni.includes(q))
                            );
                            dropdown.innerHTML = filtrados.length
                                ? filtrados.map(d => `<div class="doctor-opcion" data-id="${d.id_usuario}"
                                    style="padding:7px 12px;font-size:12px;cursor:pointer;color:#2c3e50;"
                                    onmouseover="this.style.background='#eaf1f8'"
                                    onmouseout="this.style.background=''">${d.nombre_completo}</div>`).join('')
                                : '<div style="padding:8px 12px;font-size:12px;color:#95a5a6;">Sin resultados</div>';
                            dropdown.style.display = 'block';
                            dropdown.querySelectorAll('.doctor-opcion').forEach(opt => {
                                opt.addEventListener('click', () => {
                                    selDoc.value  = opt.dataset.id;
                                    buscar.value  = opt.textContent;
                                    doctorActual  = opt.dataset.id;
                                    dropdown.style.display = 'none';
                                });
                            });
                        });
                        buscar.addEventListener('blur', () => setTimeout(() => { dropdown.style.display = 'none'; }, 200));
                    });
            }

            if (selSede && selSede.options.length === 0) {
                fetch(`${CTRL}?accion=listar_sedes`)
                    .then(r => r.json())
                    .then(lista => {
                        selSede.innerHTML = lista.map(s =>
                            `<option value="${s.id_sede_atencion}">${s.nombre_sede}</option>`
                        ).join('');
                    });
            }

            const fSemana = document.getElementById('fechaInicioSemana');
            if (fSemana && !fSemana.value) {
                fSemana.value = lunes(new Date().toISOString().split('T')[0]);
            }
        }

        // Exponer globalmente para que el tab-switch la llame
        window._cargarHorariosSelectors = cargarSelectoresHorario;

        document.querySelector('[data-tab="horarios"]')?.addEventListener('click', () => {
            setTimeout(cargarSelectoresHorario, 100);
        });

        document.getElementById('btnCargarHorario')?.addEventListener('click', cargarSemana);

        function cargarSemana() {
            const idDoc = document.getElementById('selectDoctorHorario').value;
            const fecha = document.getElementById('fechaInicioSemana').value;
            if (!idDoc || !fecha) { mostrarMensaje('Selecciona doctor y fecha', 'error'); return; }

            doctorActual = idDoc;
            semanaActual = lunes(fecha);
            const fin    = new Date(semanaActual + 'T00:00:00');
            fin.setDate(fin.getDate() + 6);
            const finStr = fin.toISOString().split('T')[0];

            Promise.all([
                fetch(`${CTRL}?accion=listar_horarios_doctor&id_doctor=${idDoc}&fecha_inicio=${semanaActual}&fecha_fin=${finStr}`).then(r=>r.json()),
                fetch(`${CTRL}?accion=listar_bloqueos&id_doctor=${idDoc}&fecha_inicio=${semanaActual}&fecha_fin=${finStr}`).then(r=>r.json())
            ]).then(([horarios, bloqueos]) => {
                horariosActuales = horarios;
                bloqueosActuales = bloqueos;
                renderSemana(horarios, bloqueos);
                document.getElementById('horariosGrilla').style.display = 'block';
                document.getElementById('btnNuevoHorario').style.display = '';
                document.getElementById('btnNuevoBloqueo').style.display = '';
                document.getElementById('btnCopiarSemana').style.display = '';
                document.getElementById('btnReplicarRango').style.display = '';
            });
        }

        function renderSemana(horarios, bloqueos) {
            const cont = document.getElementById('calendarioSemana');
            if (!cont) return;

            // Generar los 7 días de la semana
            let html = '<div class="horario-semana-grid">';
            for (let i = 0; i < 7; i++) {
                const d = new Date(semanaActual + 'T00:00:00');
                d.setDate(d.getDate() + i);
                const fecha = d.toISOString().split('T')[0];

                const horDia = horarios.filter(h => h.fecha === fecha);
                const bloqDia = bloqueos.filter(b => b.fecha === fecha);

                html += `<div class="horario-dia-col">
                    <div class="horario-dia-header">
                        <span class="horario-dia-nombre">${fmtDia(fecha)}</span>
                        <span class="horario-dia-fecha">${fmtFecha(fecha)}</span>
                    </div>
                    <div class="horario-dia-contenido">`;

                horDia.forEach(h => {
                    html += `<div class="horario-bloque horario-bloque-trabajo" data-id="${h.id_horario}">
                        <span class="hb-sede">${h.nombre_sede}</span>
                        <span class="hb-horas">${h.hora_inicio.substring(0,5)} – ${h.hora_fin.substring(0,5)}</span>
                        <div class="hb-acciones">
                            <button class="btn-edit-horario" data-id="${h.id_horario}"
                                data-fecha="${h.fecha}" data-sede="${h.id_sede}"
                                data-ini="${h.hora_inicio}" data-fin="${h.hora_fin}">Editar</button>
                            <button class="btn-del-horario" data-id="${h.id_horario}">Quitar</button>
                        </div>
                    </div>`;
                });

                bloqDia.forEach(b => {
                    html += `<div class="horario-bloque horario-bloque-bloqueo" data-id-bloqueo="${b.id_bloqueo}">
                        <span class="hb-motivo">${b.hora_inicio ? b.hora_inicio.substring(0,5)+' – '+b.hora_fin.substring(0,5) : 'Todo el día'}</span>
                        <span class="hb-motivo-txt">${b.motivo || 'Bloqueo'}</span>
                        <button class="btn-del-bloqueo" data-id="${b.id_bloqueo}">Quitar</button>
                    </div>`;
                });

                html += `<button class="btn-add-dia" data-fecha="${fecha}">+ Horario</button>`;
                html += `</div></div>`;
            }
            html += '</div>';
            cont.innerHTML = html;

            // Eventos inline
            cont.querySelectorAll('.btn-edit-horario').forEach(btn => {
                btn.addEventListener('click', () => abrirFormHorario({
                    id_horario: btn.dataset.id, fecha: btn.dataset.fecha,
                    id_sede: btn.dataset.sede, hora_inicio: btn.dataset.ini, hora_fin: btn.dataset.fin
                }));
            });
            cont.querySelectorAll('.btn-del-horario').forEach(btn => {
                btn.addEventListener('click', () => eliminarHorario(btn.dataset.id));
            });
            cont.querySelectorAll('.btn-del-bloqueo').forEach(btn => {
                btn.addEventListener('click', () => eliminarBloqueo(btn.dataset.id));
            });
            cont.querySelectorAll('.btn-add-dia').forEach(btn => {
                btn.addEventListener('click', () => abrirFormHorario({ fecha: btn.dataset.fecha }));
            });
        }

        function abrirFormHorario(data = {}) {
            document.getElementById('idHorarioEditar').value   = data.id_horario || '';
            document.getElementById('idDoctorHorario').value   = doctorActual;
            document.getElementById('fechaHorario').value      = data.fecha || '';
            document.getElementById('selectSedeHorario').value = data.id_sede || '';
            document.getElementById('horaInicioHorario').value = data.hora_inicio ? data.hora_inicio.substring(0,5) : '';
            document.getElementById('horaFinHorario').value    = data.hora_fin   ? data.hora_fin.substring(0,5)   : '';
            document.getElementById('tituloFormHorario').textContent = data.id_horario ? 'Editar horario' : 'Nuevo horario';
            document.getElementById('formHorarioContenedor').style.display = 'block';
            document.getElementById('formBloqueoContenedor').style.display = 'none';
        }

        document.getElementById('btnNuevoHorario')?.addEventListener('click', () => abrirFormHorario({}));
        document.getElementById('btnCancelarHorario')?.addEventListener('click', () => {
            document.getElementById('formHorarioContenedor').style.display = 'none';
        });

        document.getElementById('btnGuardarHorario')?.addEventListener('click', () => {
            const payload = {
                id_horario:  document.getElementById('idHorarioEditar').value || null,
                id_doctor:   document.getElementById('idDoctorHorario').value,
                id_sede:     document.getElementById('selectSedeHorario').value,
                fecha:       document.getElementById('fechaHorario').value,
                hora_inicio: document.getElementById('horaInicioHorario').value,
                hora_fin:    document.getElementById('horaFinHorario').value,
            };
            if (!payload.fecha || !payload.hora_inicio || !payload.hora_fin) {
                mostrarMensaje('Completa todos los campos', 'error'); return;
            }
            fetch(CTRL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...payload, accion: 'guardar_horario' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje('Horario guardado');
                    document.getElementById('formHorarioContenedor').style.display = 'none';
                    cargarSemana();
                } else mostrarMensaje(data.mensaje || 'Error', 'error');
            });
        });

        function eliminarHorario(id) {
            if (!confirm('¿Quitar este horario?')) return;
            const fd = new FormData();
            fd.append('accion', 'eliminar_horario');
            fd.append('id_horario', id);
            fetch(CTRL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if (d.success) cargarSemana(); });
        }

        // Replicar en rango
        document.getElementById('btnReplicarRango')?.addEventListener('click', () => {
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('rangoReplicarDesde').value = hoy;
            document.getElementById('rangoReplicarHasta').value = '';
            document.getElementById('modalReplicarRango').style.display = 'flex';
        });
        document.getElementById('btnCerrarModalRango')?.addEventListener('click', () => {
            document.getElementById('modalReplicarRango').style.display = 'none';
        });
        document.getElementById('btnCancelarRango')?.addEventListener('click', () => {
            document.getElementById('modalReplicarRango').style.display = 'none';
        });
        document.getElementById('btnConfirmarRango')?.addEventListener('click', () => {
            const desde = document.getElementById('rangoReplicarDesde').value;
            const hasta = document.getElementById('rangoReplicarHasta').value;
            if (!desde || !hasta) { mostrarMensaje('Selecciona el rango de fechas', 'error'); return; }
            if (hasta < desde) { mostrarMensaje('La fecha fin debe ser mayor al inicio', 'error'); return; }
            const btn = document.getElementById('btnConfirmarRango');
            btn.disabled = true; btn.textContent = 'Replicando...';
            fetch(CTRL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'replicar_rango', id_doctor: doctorActual,
                    semana_origen: semanaActual, fecha_inicio: desde, fecha_fin: hasta })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false; btn.textContent = 'Replicar';
                if (data.success) {
                    mostrarMensaje(`Horario replicado en ${data.semanas} semana(s)`);
                    document.getElementById('modalReplicarRango').style.display = 'none';
                    cargarSemana();
                } else mostrarMensaje(data.mensaje || 'Error', 'error');
            });
        });

        // Bloqueos
        document.getElementById('btnNuevoBloqueo')?.addEventListener('click', () => {
            document.getElementById('idDoctorBloqueo').value = doctorActual;
            document.getElementById('fechaBloqueoInicio').value = '';
            document.getElementById('fechaBloqueoFin').value = '';
            document.getElementById('checkTodoDia').checked = true;
            document.getElementById('horasBloqueoContenedor').style.display = 'none';
            document.getElementById('motivoBloqueo').value = '';
            document.getElementById('formBloqueoContenedor').style.display = 'block';
            document.getElementById('formHorarioContenedor').style.display = 'none';
        });

        document.getElementById('checkTodoDia')?.addEventListener('change', function() {
            document.getElementById('horasBloqueoContenedor').style.display = this.checked ? 'none' : 'flex';
        });

        document.getElementById('btnCancelarBloqueo')?.addEventListener('click', () => {
            document.getElementById('formBloqueoContenedor').style.display = 'none';
        });

        document.getElementById('btnGuardarBloqueo')?.addEventListener('click', () => {
            const todoDia  = document.getElementById('checkTodoDia').checked;
            const fechaIni = document.getElementById('fechaBloqueoInicio').value;
            const fechaFin = document.getElementById('fechaBloqueoFin').value;
            if (!fechaIni || !fechaFin) { mostrarMensaje('Selecciona el rango de fechas', 'error'); return; }
            if (fechaFin < fechaIni) { mostrarMensaje('La fecha fin debe ser mayor o igual al inicio', 'error'); return; }
            const payload = {
                accion:       'crear_bloqueo_rango',
                id_doctor:    document.getElementById('idDoctorBloqueo').value,
                fecha_inicio: fechaIni,
                fecha_fin:    fechaFin,
                hora_inicio:  todoDia ? null : document.getElementById('horaInicioBloqueo').value,
                hora_fin:     todoDia ? null : document.getElementById('horaFinBloqueo').value,
                motivo:       document.getElementById('motivoBloqueo').value.trim(),
            };
            fetch(CTRL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje(`Bloqueo registrado (${data.dias} día${data.dias !== 1 ? 's' : ''})`);
                    document.getElementById('formBloqueoContenedor').style.display = 'none';
                    cargarSemana();
                } else mostrarMensaje(data.mensaje || 'Error', 'error');
            });
        });

        function eliminarBloqueo(id) {
            if (!confirm('¿Quitar este bloqueo?')) return;
            const fd = new FormData();
            fd.append('accion', 'eliminar_bloqueo');
            fd.append('id_bloqueo', id);
            fetch(CTRL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if (d.success) cargarSemana(); });
        }

        document.getElementById('btnCopiarSemana')?.addEventListener('click', () => {
            const semanaAnterior = new Date(semanaActual + 'T00:00:00');
            semanaAnterior.setDate(semanaAnterior.getDate() - 7);
            const origen = semanaAnterior.toISOString().split('T')[0];

            if (!confirm(`¿Copiar el horario de la semana del ${fmtFecha(origen)} a la semana actual?`)) return;

            fetch(CTRL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'copiar_semana', id_doctor: doctorActual, fecha_origen: origen, fecha_destino: semanaActual })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje(`${data.copiados} horario(s) copiados`);
                    cargarSemana();
                } else mostrarMensaje(data.mensaje || 'Error', 'error');
            });
        });
    }
}

// ── Google Calendar modal (definido aquí para estar disponible globalmente) ──
window.abrirModalGcal = function(email, url) {
    var modal   = document.getElementById('modalConfirmarGcal');
    var emailEl = document.getElementById('modalEmailDoctor');
    var btn     = document.getElementById('btnContinuarGcal');
    if (!modal || !emailEl || !btn) {
        if (confirm('Conectar Google Calendar del doctor ' + email + '. ¿Continuar?')) {
            window.location.href = url;
        }
        return;
    }
    emailEl.textContent = email;
    btn.href = url;
    modal.style.display = 'flex';
};
window.cerrarModalGcal = function() {
    var modal = document.getElementById('modalConfirmarGcal');
    if (modal) modal.style.display = 'none';
};
document.addEventListener('click', function(e) {
    var modal = document.getElementById('modalConfirmarGcal');
    if (modal && e.target === modal) window.cerrarModalGcal();
});