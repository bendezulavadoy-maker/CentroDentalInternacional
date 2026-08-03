function iniciarModuloOdontograma() {

    const ID_PACIENTE  = window.ID_PACIENTE_ODONTO;
    const ID_USUARIO   = window.ID_USUARIO_ODONTO;
    const PUEDE_EDITAR = window.PUEDE_EDITAR_ODONTO;

    let versionActiva   = null;
    let hallazgos       = {};
    let estadoActivo    = null;
    let colorActivo     = 'azul';
    let modoEdicion     = false;
    let catalogoEstados = [];
    let catalogoDientes = {};

    const DIENTES_SUP = [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28];
    const DIENTES_INF = [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38];
    const CARAS_LABELS = { V:'Vestibular', O:'Oclusal', M:'Mesial', D:'Distal', L:'Lingual' };
    const SIGLAS_REQUERIDAS = {
        'Restauración':     ['AM','R','IV','IM','IE'],
        'Corona definitiva':['CC','CF','CMC','CV','CJ','3/4','4/5','7/8'],
        'Tratamiento pulpar':['TC','PC','PP'],
        'Movilidad':        ['M1','M2','M3'],
    };
    const SIGLAS_DISPLAY = {
        'Desgaste oclusal':    'DES',
        'Diente discromico':   'DIS',
        'Diente ectopico':     'E',
        'Diente en clavija':   'CLV',
        'Impactacion':         'I',
        'Implante':            'IMP',
        'Macrodoncia':         'MAC',
        'Microdoncia':         'MIC',
        'Semi impactacion':    'SI',
        'Remanente radicular': 'RR',
        'Diente ausente':      'X',
        'Edentulo total':      'ET',
        'Diente extruido':     'EXT',
        'Diente intruido':     'INT',
        'Giroversion':         'GIR',
        'Migracion':           'MIG',
        'Protesis total':      'PT',
        'Protesis removible':  'PR',
        'Corona definitiva':   'CD',
        'Corona temporal':     'CT',
        'Tratamiento pulpar':  'TP',
        'Movilidad':           'M',
    };
    // ── Inicializar ───────────────────────────────────────────────
    cargarCatalogos().then(() => cargarVersiones());
    dibujarOdontograma();

    // ── Catálogos ─────────────────────────────────────────────────
    async function cargarCatalogos() {
        const [resE, resD] = await Promise.all([
            fetch('../CONTROLADORES/controlador_odontograma.php?accion=listar_estados'),
            fetch('../CONTROLADORES/controlador_odontograma.php?accion=listar_dientes')
        ]);
        catalogoEstados = await resE.json();
        const dientes   = await resD.json();
        dientes.forEach(d => { catalogoDientes[d.numero_fdi] = d.id_diente; });
    }

    // ── Crear versión — scope principal ───────────────────────────
    function crearNuevaVersion() {
        if (!ID_PACIENTE || ID_PACIENTE === 0) {
            mostrarMensaje('Error: paciente no identificado', 'error');
            return;
        }
        // Mostrar modal propio en lugar de confirm()
        const modal = document.getElementById('modalNuevaVersion');
        if (modal) modal.style.display = 'flex';
    }
    
    function ejecutarCrearVersion() {
        const modal = document.getElementById('modalNuevaVersion');
        if (modal) modal.style.display = 'none';
    
        const fd = new FormData();
        fd.append('accion',      'crear_version');
        fd.append('id_paciente', ID_PACIENTE);
        fd.append('notas',       '');
    
        fetch('../CONTROLADORES/controlador_odontograma.php', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(txt => {
                try {
                    const data = JSON.parse(txt);
                    if (data.success) {
                        mostrarMensaje(`Versión ${data.numero} creada`, 'exito');
                        cargarVersiones();
                    } else {
                        mostrarMensaje(data.mensaje || 'Error al crear versión', 'error');
                    }
                } catch(e) {
                    console.error('Respuesta no JSON:', txt);
                    mostrarMensaje('Error de servidor', 'error');
                }
            })
            .catch(() => mostrarMensaje('Error de conexión', 'error'));
    }
    
    
    // ── Versiones ─────────────────────────────────────────────────
    function cargarVersiones() {
        fetch(`../CONTROLADORES/controlador_odontograma.php?accion=listar_versiones&id_paciente=${ID_PACIENTE}`)
            .then(r => r.json())
            .then(versiones => renderVersiones(versiones));
    }

    function renderVersiones(versiones) {
        const lista = document.getElementById('listaVersiones');
        const btnHeader = document.getElementById('btnNuevaVersion');

        if (versiones.length === 0) {
            document.getElementById('panelOdontograma').style.display = 'none';

            if (PUEDE_EDITAR) {
                lista.innerHTML = `
                    <div class="sin-versiones-editable">
                        <p class="sin-versiones-titulo">Sin odontograma registrado</p>
                        <p class="sin-versiones-desc">Este paciente aún no tiene un odontograma inicial.</p>
                        <button class="btn-crear-inicial" id="btnCrearInicial">
                            Crear odontograma inicial
                        </button>
                    </div>`;
                document.getElementById('btnCrearInicial')
                    .addEventListener('click', () => crearNuevaVersion());
                if (btnHeader) btnHeader.style.display = 'none';
            } else {
                lista.innerHTML = `
                    <div class="sin-versiones">
                        <p>No hay odontogramas registrados para este paciente.</p>
                    </div>`;
                if (btnHeader) btnHeader.style.display = 'none';
            }
            return;
        }

        if (btnHeader) btnHeader.style.display = 'inline-flex';

        const vigente   = versiones.find(v => v.es_vigente == 1);
        const historial = versiones.filter(v => v.es_vigente != 1).slice().reverse();

        let html = '';
        if (vigente) {
            html += `
                <div class="version-seccion-label">Vigente</div>
                <div class="version-item vigente" data-id="${vigente.id_version}">
                    ${renderVersionItem(vigente)}
                </div>`;
        }
        if (historial.length > 0) {
            html += `<div class="version-seccion-label">Historial</div>`;
            historial.forEach(v => {
                html += `
                    <div class="version-item" data-id="${v.id_version}">
                        ${renderVersionItem(v)}
                    </div>`;
            });
        }

        lista.innerHTML = html;
        lista.querySelectorAll('.btn-ver-version').forEach(btn => {
            btn.addEventListener('click', () => abrirVersion(btn.dataset.id));
        });
        lista.querySelectorAll('.btn-eliminar-version').forEach(btn => {
            btn.addEventListener('click', () => eliminarVersion(btn.dataset.id));
        });

        if (vigente) abrirVersion(vigente.id_version);
    }
    function eliminarVersion(id_version) {
        const modal = document.getElementById('modalEliminarVersion');
        if (modal) {
            modal.style.display = 'flex';
            document.getElementById('btnConfirmarEliminarVersion').dataset.id = id_version;
        }
    }

    const btnNuevaVersion = document.getElementById('btnNuevaVersion');
if (btnNuevaVersion) btnNuevaVersion.onclick = () => crearNuevaVersion();

const btnConfirmarNueva = document.getElementById('btnConfirmarNuevaVersion');
if (btnConfirmarNueva) btnConfirmarNueva.onclick = () => ejecutarCrearVersion();

const btnCancelarNueva = document.getElementById('btnCancelarNuevaVersion');
if (btnCancelarNueva) btnCancelarNueva.onclick = () => {
    document.getElementById('modalNuevaVersion').style.display = 'none';
};

const btnCancelarEliminar = document.getElementById('btnCancelarEliminarVersion');
if (btnCancelarEliminar) btnCancelarEliminar.onclick = () => {
    document.getElementById('modalEliminarVersion').style.display = 'none';
};

const btnConfirmarEliminar = document.getElementById('btnConfirmarEliminarVersion');
if (btnConfirmarEliminar) btnConfirmarEliminar.onclick = function() {
    const id_version = this.dataset.id;
    document.getElementById('modalEliminarVersion').style.display = 'none';

    const fd = new FormData();
    fd.append('accion',     'eliminar_version');
    fd.append('id_version', id_version);

    fetch('../CONTROLADORES/controlador_odontograma.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            mostrarMensaje(
                data.success ? 'Versión eliminada' : (data.mensaje || 'Error'),
                data.success ? 'exito' : 'error'
            );
            if (data.success) cargarVersiones();
        });
};

    
    function renderVersionItem(v) {
        const badge = v.estado === 'borrador'
            ? '<span class="estado-badge borrador">Borrador</span>'
            : '<span class="estado-badge cerrado">Cerrado</span>';
    
        const btnEliminar = (v.estado === 'borrador' && PUEDE_EDITAR)
            ? `<button class="btn-eliminar-version" data-id="${v.id_version}">Eliminar</button>`
            : '';
    
        return `
            <div class="version-item-datos">
                <span class="version-num">Versión ${v.numero_version}</span>
                ${badge}
                <span class="version-meta">${v.nombre_doctor} · ${formatFecha(v.fecha_creacion)}</span>
                <span class="version-hallazgos">${v.total_hallazgos} hallazgos</span>
                ${v.notas ? `<span class="version-notas">${v.notas}</span>` : ''}
            </div>
            <div style="display:flex;gap:6px;">
                ${btnEliminar}
                <button class="btn-ver-version" data-id="${v.id_version}">Ver</button>
            </div>`;
    }
    // ── Abrir versión ─────────────────────────────────────────────
    function abrirVersion(id_version) {
        versionActiva = id_version;
        const panel = document.getElementById('panelOdontograma');
        panel.style.display = 'block';

        fetch(`../CONTROLADORES/controlador_odontograma.php?accion=listar_versiones&id_paciente=${ID_PACIENTE}`)
            .then(r => r.json())
            .then(versiones => {
                const v = versiones.find(x => x.id_version == id_version);
                if (!v) return;

                document.getElementById('versionLabel').textContent  = `Versión ${v.numero_version}`;
                document.getElementById('versionDoctor').textContent = v.nombre_doctor;
                document.getElementById('versionFecha').textContent  = formatFecha(v.fecha_creacion);
                document.getElementById('versionEstado').innerHTML   =
                    v.estado === 'borrador'
                        ? '<span class="estado-badge borrador">Borrador</span>'
                        : '<span class="estado-badge cerrado">Cerrado</span>';

                        modoEdicion = PUEDE_EDITAR && v.estado === 'borrador' && String(v.creado_por) === String(ID_USUARIO);
                        console.log('creado_por:', v.creado_por, typeof v.creado_por);
                        console.log('ID_USUARIO:', ID_USUARIO, typeof ID_USUARIO);
                        console.log('modoEdicion:', modoEdicion);
                const setDisplay = (id, show) => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = show ? (id === 'barraHerramientas' ? 'flex' : 'inline-flex') : 'none';
                };

                setDisplay('barraHerramientas',  modoEdicion);
                setDisplay('btnGuardarBorrador', modoEdicion);
                setDisplay('btnCerrarVersion',   modoEdicion);

                const espec = document.getElementById('txtEspecificaciones');
                if (espec) {
                    espec.disabled = !modoEdicion;
                    espec.value    = v.notas || '';
                }

                cargarHallazgos(id_version);

                const edad = window.EDAD_PACIENTE_ODONTO || 99;
                const tog  = document.getElementById('toggleTemporales');
                if (tog) tog.style.display = edad < 13 ? 'block' : 'none';
            });
    }

    // ── SVG ───────────────────────────────────────────────────────
    function dibujarOdontograma() {
        const ANCHO = 52, ALTO = 80, GAP = 1;
        dibujarArco('grupoSuperior', DIENTES_SUP, 28,  35, ANCHO, ALTO, GAP, 'superior');
        dibujarArco('grupoInferior', DIENTES_INF, 28, 235, ANCHO, ALTO, GAP, 'inferior');
    }

    function dibujarArco(grupoId, dientes, xInicio, yInicio, ancho, alto, gap, tipo) {
        const g = document.getElementById(grupoId);
        if (!g) return;
        g.innerHTML = '';
        dientes.forEach((fdi, idx) => {
            const x = xInicio + idx * (ancho + gap);
            g.appendChild(crearDienteSVG(fdi, x, yInicio, ancho, alto, tipo));
        });
    }

    function crearDienteSVG(fdi, x, y, ancho, alto, tipo) {
        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.setAttribute('class', 'diente-grupo');
        g.setAttribute('data-fdi', fdi);

        // Número FDI
        const txtNum = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        txtNum.setAttribute('x', x + ancho/2);
        txtNum.setAttribute('y', tipo === 'superior' ? y - 4 : y + alto + 14);
        txtNum.setAttribute('text-anchor', 'middle');
        txtNum.setAttribute('class', 'svg-num-diente');
        txtNum.textContent = fdi;
        g.appendChild(txtNum);

        // Recuadro anotación
        const rectY = tipo === 'superior' ? y : y + alto - 18;
        const rectAnot = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rectAnot.setAttribute('x', x);
        rectAnot.setAttribute('y', rectY);
        rectAnot.setAttribute('width', ancho);
        rectAnot.setAttribute('height', 18);
        rectAnot.setAttribute('class', 'rect-anotacion');
        rectAnot.setAttribute('data-fdi', fdi);
        rectAnot.setAttribute('data-cara', 'RECUADRO');
        g.appendChild(rectAnot);

        // Texto anotación
        const txtAnot = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        txtAnot.setAttribute('x', x + ancho/2);
        txtAnot.setAttribute('y', rectY + 12);
        txtAnot.setAttribute('text-anchor', 'middle');
        txtAnot.setAttribute('class', 'svg-anotacion-texto');
        txtAnot.setAttribute('id', `anot_${fdi}`);
        g.appendChild(txtAnot);

        // Caras
        const coronaY = tipo === 'superior' ? y + 18 : y;
        const coronaH = alto - 18;
        const cx = x + ancho/2;
        const cy = coronaY + coronaH/2;
        const rOcl = 10;

        // Oclusal
        const caraO = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        caraO.setAttribute('x', cx - rOcl);
        caraO.setAttribute('y', cy - rOcl);
        caraO.setAttribute('width', rOcl * 2);
        caraO.setAttribute('height', rOcl * 2);
        caraO.setAttribute('class', 'cara-diente');
        caraO.setAttribute('data-fdi', fdi);
        caraO.setAttribute('data-cara', 'O');
        caraO.setAttribute('fill', 'white');
        g.appendChild(caraO);

        // Vestibular
        g.appendChild(crearTriangulo(cx - ancho/2+2, cy-rOcl, cx+ancho/2-2, cy-rOcl, cx, coronaY+2, fdi, 'V'));
        // Lingual
        g.appendChild(crearTriangulo(cx - ancho/2+2, cy+rOcl, cx+ancho/2-2, cy+rOcl, cx, coronaY+coronaH-2, fdi, 'L'));
        // Mesial
        g.appendChild(crearTriangulo(cx-rOcl, cy-rOcl, cx-rOcl, cy+rOcl, x+2, cy, fdi, 'M'));
        // Distal
        g.appendChild(crearTriangulo(cx+rOcl, cy-rOcl, cx+rOcl, cy+rOcl, x+ancho-2, cy, fdi, 'D'));

        // Borde exterior
        const borde = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        borde.setAttribute('x', x+1);
        borde.setAttribute('y', coronaY);
        borde.setAttribute('width', ancho-2);
        borde.setAttribute('height', coronaH);
        borde.setAttribute('fill', 'none');
        borde.setAttribute('stroke', '#ccc');
        borde.setAttribute('stroke-width', '0.8');
        borde.setAttribute('pointer-events', 'none');
        g.appendChild(borde);

        // Eventos
       
g.querySelectorAll('[data-cara]').forEach(cara => {
    // Clic izquierdo → menú de hallazgos
    cara.addEventListener('click', (e) => {
        e.stopPropagation();
        if (!modoEdicion) return;
        mostrarMenuContextual(e, fdi, cara.getAttribute('data-cara'));
    });
    // Clic derecho → borrar hallazgo directamente
    cara.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        if (!modoEdicion) return;
        const c = cara.getAttribute('data-cara');
        const key = `${fdi}_${c}`;
        if (hallazgos[key]) {
            borrarHallazgo(fdi, c);
        }
    });
});

        return g;
    }

    function crearTriangulo(x1,y1,x2,y2,xp,yp,fdi,cara) {
        const p = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        p.setAttribute('points', `${x1},${y1} ${x2},${y2} ${xp},${yp}`);
        p.setAttribute('class', 'cara-diente');
        p.setAttribute('data-fdi', fdi);
        p.setAttribute('data-cara', cara);
        p.setAttribute('fill', 'white');
        return p;
    }

    // ── Hallazgos ─────────────────────────────────────────────────
    function cargarHallazgos(id_version) {
        hallazgos = {};
        dibujarOdontograma();
    
        fetch(`../CONTROLADORES/controlador_odontograma.php?accion=cargar_hallazgos&id_version=${id_version}`)
            .then(r => r.json())
            .then(data => {
                // Triple rAF para garantizar que el SVG esté en el DOM
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            data.forEach(h => {
                                const cara = h.cara || 'RECUADRO';
                                const key  = `${h.numero_fdi}_${cara}`;
                                hallazgos[key] = h;
    
                                // Verificar que el elemento existe
                                if (cara === 'RECUADRO') {
                                    const el = document.getElementById(`anot_${h.numero_fdi}`);
                                    if (!el) {
                                        console.warn(`anot_${h.numero_fdi} no encontrado, reintentando...`);
                                        setTimeout(() => {
                                            renderHallazgoEnSVG(h.numero_fdi, cara, h.color, h.sigla, h.nombre_estado);
                                        }, 200);
                                        return;
                                    }
                                }
    
                                renderHallazgoEnSVG(h.numero_fdi, cara, h.color, h.sigla, h.nombre_estado);
                            });
                        });
                    });
                });
            });
    }

    function aplicarHallazgo(fdi, cara) {
        if (!estadoActivo) {
            mostrarMensaje('Selecciona un hallazgo primero', 'error');
            return;
        }
    
        const opciones = SIGLAS_REQUERIDAS[estadoActivo.nombre_estado];
        let sigla = '';
        if (opciones) {
            const elegida = prompt(`Tipo para "${estadoActivo.nombre_estado}":\n${opciones.join(' / ')}`);
            if (elegida === null) return;
            sigla = elegida.toUpperCase().trim();
        }
    
        // Verificar que el elemento SVG existe antes de pintar
        const svg = document.getElementById('svgOdontograma');
        const elementos = svg?.querySelectorAll('.cara-diente');
        console.log('fdi:', fdi, 'id_diente:', catalogoDientes[fdi], 'catalogoDientes:', catalogoDientes);
        console.log('versionActiva:', versionActiva);
        // Pintar inmediatamente
        renderHallazgoEnSVG(fdi, cara, colorActivo, sigla, estadoActivo.nombre_estado);
    
        const fd = new FormData();
        fd.append('accion',      'guardar_hallazgo');
        fd.append('id_version',  versionActiva);
        fd.append('id_diente',   catalogoDientes[fdi] || 0);
        fd.append('id_estado',   estadoActivo.id_estado);
        fd.append('cara',        cara);
        fd.append('color',       colorActivo);
        fd.append('sigla',       sigla);
        fd.append('observacion', '');
    
        fetch('../CONTROLADORES/controlador_odontograma.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    hallazgos[`${fdi}_${cara}`] = {
                        numero_fdi: fdi, cara,
                        color: colorActivo, sigla,
                        nombre_estado: estadoActivo.nombre_estado
                    };
                    console.log('Hallazgo guardado OK');
                } else {
                    borrarHallazgoEnSVG(fdi, cara);
                    mostrarMensaje(data.mensaje || 'Error al guardar', 'error');
                }
            });
    }

    function obtenerSigla(estado) {
        const opciones = SIGLAS_REQUERIDAS[estado.nombre_estado];
        if (!opciones) return '';
        const elegida = prompt(`Tipo para "${estado.nombre_estado}":\n${opciones.join(' / ')}`);
        if (!elegida) return null;
        return elegida.toUpperCase().trim();
    }

    function renderHallazgoEnSVG(fdi, cara, color, sigla, nombreEstado) {
        console.log('renderHallazgo llamado:', {fdi, cara, color, sigla, nombreEstado});
        const hex = color === 'rojo' ? '#e74c3c' : '#2a4d8f';
        const svg = document.getElementById('svgOdontograma');
        if (!svg) return;
    
        if (cara === 'RECUADRO') {
            const txt = document.getElementById(`anot_${fdi}`);
            console.log(`Buscando anot_${fdi}:`, txt); // debug
            if (txt) {
                const textoMostrar = sigla || SIGLAS_DISPLAY?.[nombreEstado] || nombreEstado.substring(0,3).toUpperCase();
                console.log(`Texto a mostrar: "${textoMostrar}"`); // debug
                txt.textContent      = textoMostrar;
                txt.style.fill       = hex;
                txt.style.fontSize   = '8px';
                txt.style.fontWeight = '600';
            }
            const rects = svg.querySelectorAll('rect.rect-anotacion');
            rects.forEach(r => {
                if (r.getAttribute('data-fdi') == fdi) {
                    r.style.fill = color === 'rojo' ? '#fdecea' : '#e8f0fb';
                }
            });
        } else {
            const todos = svg.querySelectorAll('.cara-diente');
            todos.forEach(el => {
                if (el.getAttribute('data-fdi') == fdi && el.getAttribute('data-cara') === cara) {
                    el.setAttribute('fill', hex);
                    el.setAttribute('fill-opacity', '0.85');
                    el.style.fill        = hex;
                    el.style.fillOpacity = '0.85';
                    el.classList.add('cara-pintada');
                }
            });
        }
    }

    function borrarHallazgoEnSVG(fdi, cara) {
        const svg = document.getElementById('svgOdontograma');
        if (!svg) return;
    
        if (cara === 'RECUADRO') {
            const txt = document.getElementById(`anot_${fdi}`);
            if (txt) txt.textContent = '';
            const rects = svg.querySelectorAll('rect.rect-anotacion');
            rects.forEach(r => {
                if (r.getAttribute('data-fdi') == fdi) {
                    r.setAttribute('fill', 'white');
                }
            });
        } else {
            svg.querySelectorAll('.cara-diente').forEach(el => {
                if (el.getAttribute('data-fdi') == fdi && el.getAttribute('data-cara') === cara) {
                    el.setAttribute('fill', 'white');
                    el.setAttribute('fill-opacity', '1');
                    el.style.fill        = 'white';
                    el.style.fillOpacity = '1';
                    el.classList.remove('cara-pintada');
                }
            });
        }
    }

    function borrarHallazgo(fdi, cara) {
        const fd = new FormData();
        fd.append('accion',     'borrar_hallazgo');
        fd.append('id_version', versionActiva);
        fd.append('id_diente',  catalogoDientes[fdi]);
        fd.append('cara',       cara);
        fetch('../CONTROLADORES/controlador_odontograma.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    delete hallazgos[`${fdi}_${cara}`];
                    borrarHallazgoEnSVG(fdi, cara);
                }
            });
    }

    // ── Menú contextual ───────────────────────────────────────────
    function mostrarMenuContextual(e, fdi, cara) {
         // Resaltar cara temporalmente
    const svg = document.getElementById('svgOdontograma');
    if (svg) {
        svg.querySelectorAll('.cara-diente').forEach(el => el.classList.remove('cara-hover'));
        svg.querySelectorAll('.cara-diente').forEach(el => {
            if (el.getAttribute('data-fdi') == fdi && el.getAttribute('data-cara') === cara) {
                el.classList.add('cara-hover');
            }
        });
    }
        const menu = document.getElementById('menuContextual');
        if (!menu) return;

        document.getElementById('menuCtxDiente').textContent = `Pieza ${fdi}`;
        document.getElementById('menuCtxCara').textContent   = cara === 'RECUADRO' ? 'Recuadro' : (CARAS_LABELS[cara] || cara);

        const opciones = document.getElementById('menuCtxOpciones');
        opciones.innerHTML = '';
        catalogoEstados.forEach(est => {
            const btn = document.createElement('button');
            btn.className   = `menu-ctx-opcion ${est.color}`;
            btn.textContent = est.nombre_estado;
            btn.addEventListener('click', () => {
                estadoActivo = est;
                colorActivo  = est.color;
                document.querySelectorAll('.btn-color').forEach(b => b.classList.remove('activo'));
                document.querySelector(`.btn-color.${est.color}`)?.classList.add('activo');
                const chip = document.getElementById('hallazgoActivo');
                if (chip) { chip.textContent = est.nombre_estado; chip.className = `hallazgo-chip ${est.color}`; }
                aplicarHallazgo(fdi, cara);
                svg?.querySelectorAll('.cara-hover').forEach(el => el.classList.remove('cara-hover'));
                menu.style.display = 'none';
            });
            opciones.appendChild(btn);
        });

        const keyActual = `${fdi}_${cara}`;
        const btnBorrarCtx = document.getElementById('menuCtxBorrar');
        if (btnBorrarCtx) {
            btnBorrarCtx.style.display = hallazgos[keyActual] ? 'block' : 'none';
            btnBorrarCtx.onclick = () => { borrarHallazgo(fdi, cara); menu.style.display = 'none'; };
        }

        menu.style.left    = `${e.pageX}px`;
        menu.style.top     = `${e.pageY}px`;
        menu.style.display = 'block';
    }

    // ── Event listeners ───────────────────────────────────────────


    // Guardar borrador
    document.getElementById('btnGuardarBorrador')
        ?.addEventListener('click', () => {
            const notas = document.getElementById('txtEspecificaciones')?.value || '';
            const fd = new FormData();
            fd.append('accion',     'actualizar_notas');
            fd.append('id_version', versionActiva);
            fd.append('notas',      notas);
            fetch('../CONTROLADORES/controlador_odontograma.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => mostrarMensaje(
                    data.success ? 'Borrador guardado' : 'Error al guardar',
                    data.success ? 'exito' : 'error'
                ));
        });

    // Cerrar versión
    document.getElementById('btnCerrarVersion')
        ?.addEventListener('click', () => {
            const modal = document.getElementById('modalCerrarVersion');
            if (modal) modal.style.display = 'flex';
        });

    document.getElementById('btnCancelarCierre')
        ?.addEventListener('click', () => {
            const modal = document.getElementById('modalCerrarVersion');
            if (modal) modal.style.display = 'none';
        });

    document.getElementById('btnConfirmarCierre')
        ?.addEventListener('click', () => {
            const notas = document.getElementById('txtEspecificaciones')?.value || '';
            const fdN = new FormData();
            fdN.append('accion',     'actualizar_notas');
            fdN.append('id_version', versionActiva);
            fdN.append('notas',      notas);
            fetch('../CONTROLADORES/controlador_odontograma.php', { method: 'POST', body: fdN })
                .then(() => {
                    const fd = new FormData();
                    fd.append('accion',     'cerrar_version');
                    fd.append('id_version', versionActiva);
                    return fetch('../CONTROLADORES/controlador_odontograma.php', { method: 'POST', body: fd });
                })
                .then(r => r.json())
                .then(data => {
                    const modal = document.getElementById('modalCerrarVersion');
                    if (modal) modal.style.display = 'none';
                    mostrarMensaje(
                        data.success ? 'Versión cerrada definitivamente' : (data.mensaje || 'Error'),
                        data.success ? 'exito' : 'error'
                    );
                    if (data.success) { modoEdicion = false; cargarVersiones(); }
                });
        });

    // Selector hallazgo
    document.getElementById('selectorHallazgo')
        ?.addEventListener('click', e => {
            e.stopPropagation();
            const menu = document.getElementById('menuHallazgos');
            if (!menu) return;
            if (menu.style.display === 'block') { menu.style.display = 'none'; return; }
            menu.innerHTML = '';
            catalogoEstados.forEach(est => {
                const btn = document.createElement('button');
                btn.className   = `menu-hallazgo-opcion ${est.color}`;
                btn.textContent = est.nombre_estado;
                btn.addEventListener('click', ev => {
                    ev.stopPropagation();
                    estadoActivo = est;
                    colorActivo  = est.color;
                    document.querySelectorAll('.btn-color').forEach(b => b.classList.remove('activo'));
                    document.querySelector(`.btn-color.${est.color}`)?.classList.add('activo');
                    const chip = document.getElementById('hallazgoActivo');
                    if (chip) { chip.textContent = est.nombre_estado; chip.className = `hallazgo-chip ${est.color}`; }
                    menu.style.display = 'none';
                });
                menu.appendChild(btn);
            });
            menu.style.display = 'block';
        });

    // Botones de color
    document.querySelectorAll('.btn-color').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.btn-color').forEach(b => b.classList.remove('activo'));
            btn.classList.add('activo');
            colorActivo = btn.dataset.color;
        });
    });

    // Borrar modo
    document.getElementById('btnBorrar')
        ?.addEventListener('click', () => {
            estadoActivo = null;
            const chip = document.getElementById('hallazgoActivo');
            if (chip) { chip.textContent = 'Seleccionar'; chip.className = 'hallazgo-chip'; }
        });

    // Temporales
    document.getElementById('chkTemporales')
        ?.addEventListener('change', function() {
            const g = document.getElementById('grupoTemporales');
            if (g) g.style.display = this.checked ? 'block' : 'none';
        });

    // Cerrar menús al clic fuera
    document.addEventListener('click', () => {
        const mc = document.getElementById('menuContextual');
        const mh = document.getElementById('menuHallazgos');
        if (mc) mc.style.display = 'none';
        if (mh) mh.style.display = 'none';
    });

    // ── Helpers ───────────────────────────────────────────────────
    function formatFecha(str) {
        if (!str) return '';
        return new Date(str).toLocaleDateString('es-PE', { day:'2-digit', month:'short', year:'numeric' });
    }

    function mostrarMensaje(msg, tipo = 'exito') {
        const aviso = document.createElement('div');
        aviso.className = `mensaje-sistema ${tipo}`;
        aviso.innerHTML = `<span class="texto">${msg}</span>`;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.classList.add('mostrar'), 50);
        setTimeout(() => { aviso.classList.remove('mostrar'); setTimeout(() => aviso.remove(), 400); }, 3000);
    }
}