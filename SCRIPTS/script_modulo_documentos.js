// =====================================================
// MODULO DE DOCUMENTOS DEL PACIENTE
// Seleccion multiple, cortar/copiar/pegar, arrastrar y soltar, renombrar,
// eliminar (a la papelera) y papelera para quien tenga ese permiso.
// El servidor decide siempre los permisos: aqui solo se ocultan botones.
// =====================================================
(function () {
    'use strict';

    // Si el modulo ya estaba cargado (se reabrio la pestaña o se cambio de paciente) se limpia la instancia anterior
    if (typeof window.__moduloDocumentosLimpiar === 'function') {
        try { window.__moduloDocumentosLimpiar(); } catch (e) { /* la instancia anterior ya no existe */ }
    }

    const raiz = document.getElementById('moduloDocumentos');
    if (!raiz) return;

    let CFG = {};
    try { CFG = JSON.parse(raiz.dataset.config || '{}'); } catch (e) { CFG = {}; }
    const ID_PACIENTE = parseInt(CFG.id_paciente, 10) || 0;
    const CTRL = '../CONTROLADORES/controlador_documentos.php';
    const EXT_PREVIEW = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    // ---- Estado ----
    let permisos = Object.assign({ subir: false, crear_carpeta: false, descargar: false, renombrar: false,
                                   mover: false, eliminar: false, papelera: false }, CFG.permisos || {});
    let papeleraDias = CFG.papelera_dias || 15;
    let maxMb = CFG.max_mb || 10;
    let extensiones = CFG.extensiones || ['pdf'];
    let estructura = [];
    let mapa = {};                 // id de carpeta -> carpeta (con .padre)
    let carpetaActual = null;      // id de la carpeta abierta; null = Inicio
    let documentos = [];           // documentos de la ubicacion actual
    const seleccion = new Map();   // 'c:ID' | 'd:ID' -> { tipo, id, nombre, sistema }
    let portapapeles = null;       // { accion: 'cortar'|'copiar', items: [{ tipo, id, nombre }] }
    let arrastre = null;           // { items: [{ tipo, id }] } mientras se arrastra
    let eliminarPendiente = null;
    let ocupado = false;
    const limpiezas = [];

    // ---- Elementos ----
    const $ = id => document.getElementById(id);
    const el = {
        totalCarpetas: $('totalCarpetas'), totalDocumentos: $('totalDocumentos'), tamanoTotal: $('tamanoTotal'),
        breadcrumb: raiz.querySelector('.breadcrumb-navegacion'), arbol: $('arbolCarpetas'),
        grid: $('gridCarpetas'), lista: $('listaDocumentos'),
        barraSel: $('barraSeleccion'), barraPortapapeles: $('barraPortapapeles'),
        btnPapelera: $('btnPapelera'), btnPegar: $('btnPegar'), btnNuevaCarpeta: $('btnNuevaCarpeta'), btnSubir: $('btnSubirDocumento'),
        mNueva: $('modalNuevaCarpeta'), nombreCarpeta: $('nombreCarpeta'), colorCarpeta: $('colorCarpeta'),
        mSubir: $('modalSubirDocumento'), zonaSubida: $('zonaSubida'), inputArchivo: $('inputArchivo'),
        archivoSel: $('archivoSeleccionado'), nombreArchivoSel: $('nombreArchivoSeleccionado'), tamanoArchivoSel: $('tamanoArchivoSeleccionado'),
        iconoArchivoSel: $('iconoArchivoSeleccionado'), tituloDoc: $('tituloDocumento'), descDoc: $('descripcionDocumento'),
        carpetaDestino: $('carpetaDestino'), btnGuardarDoc: $('btnGuardarDocumento'), textoInfoArchivo: $('textoInfoArchivo'),
        mEditCarpeta: $('modalEditarCarpeta'), editIdCarpeta: $('editarIdCarpeta'), editNombreCarpeta: $('editarNombreCarpeta'), editColorCarpeta: $('editarColorCarpeta'),
        mEditDoc: $('modalEditarDocumento'), editIdDoc: $('editarIdDocumento'), editTituloDoc: $('editarTituloDocumento'), editDescDoc: $('editarDescripcionDocumento'),
        mEliminar: $('modalConfirmarEliminar'), textoEliminar: $('textoConfirmarEliminar'), listaEliminar: $('listaConfirmarEliminar'),
        ayudaEliminar: $('ayudaConfirmarEliminar'), btnConfirmarEliminar: $('btnConfirmarEliminar'),
        mPapelera: $('modalPapelera'), ayudaPapelera: $('ayudaPapelera'), listaPapelera: $('listaPapelera'),
        mPreview: $('modalPreviewDocumento'), tituloPreview: $('tituloPreviewDocumento'), contenedorPreview: $('contenedorPreview'),
        btnDescargarPreview: $('btnDescargarDesdePreview'),
    };

    // =====================================================
    // Utilidades
    // =====================================================
    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function colorSeguro(c) { return /^#[0-9a-fA-F]{6}$/.test(c || '') ? c : '#2a4d8f'; }
    function clave(tipo, id) { return (tipo === 'carpeta' ? 'c:' : 'd:') + id; }
    function on(nodo, evento, fn, opciones) { if (nodo) nodo.addEventListener(evento, fn, opciones); }
    function onDoc(evento, fn) {
        document.addEventListener(evento, fn);
        limpiezas.push(() => document.removeEventListener(evento, fn));
    }
    function formatearTamano(bytes) {
        bytes = Number(bytes) || 0;
        if (!bytes) return '0 B';
        const u = ['B', 'KB', 'MB', 'GB'];
        const i = Math.min(u.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
        return (Math.round(bytes / Math.pow(1024, i) * 100) / 100) + ' ' + u[i];
    }
    function formatearFecha(f) {
        if (!f) return '';
        const d = new Date(String(f).replace(' ', 'T'));
        return isNaN(d) ? '' : d.toLocaleDateString('es-PE', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    function mostrarMensaje(texto, tipo) {
        const aviso = document.createElement('div');
        aviso.textContent = texto;
        aviso.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;color:white;padding:12px 22px;border-radius:8px;' +
            'box-shadow:0 4px 12px rgba(0,0,0,.15);opacity:0;transform:translateX(100%);transition:all .4s ease;max-width:360px;font-size:13.5px;' +
            'background:' + (tipo === 'error' ? '#c0392b' : '#27ae60');
        document.body.appendChild(aviso);
        setTimeout(() => { aviso.style.opacity = '1'; aviso.style.transform = 'translateX(0)'; }, 50);
        setTimeout(() => { aviso.style.opacity = '0'; aviso.style.transform = 'translateX(100%)'; setTimeout(() => aviso.remove(), 400); }, 3500);
    }
    function abrirModal(m) { if (!m) return; m.style.display = 'flex'; setTimeout(() => m.classList.add('mostrar'), 10); }
    function cerrarModal(m) { if (!m) return; m.classList.remove('mostrar'); setTimeout(() => { m.style.display = 'none'; }, 300); }

    async function pedir(url, opciones) {
        let res;
        try { res = await fetch(url, opciones); }
        catch (e) { return { success: false, mensaje: 'No se pudo conectar con el servidor' }; }
        try { return await res.json(); }
        catch (e) { return { success: false, mensaje: 'Respuesta inválida del servidor' }; }
    }
    function llamar(accion, payload) {
        return pedir(CTRL, { method: 'POST', headers: { 'Content-Type': 'application/json' },
                             body: JSON.stringify(Object.assign({ accion: accion }, payload || {})) });
    }
    function consultar(accion, params) {
        return pedir(CTRL + '?' + new URLSearchParams(Object.assign({ accion: accion }, params || {})).toString());
    }

    // =====================================================
    // Estructura de carpetas
    // =====================================================
    function construirMapa() {
        mapa = {};
        (function recorrer(nodos, padre) {
            nodos.forEach(c => { c.padre = padre; mapa[c.id_carpeta] = c; recorrer(c.subcarpetas || [], c.id_carpeta); });
        })(estructura, null);
    }
    function hijosDe(id) { return id === null ? estructura : ((mapa[id] && mapa[id].subcarpetas) || []); }
    function ancestrosDe(id) {      // el id y todos sus padres hasta la raiz
        const r = []; let a = id; let guardia = 0;
        while (a !== null && a !== undefined && mapa[a] && guardia++ < 100) { r.push(a); a = mapa[a].padre; }
        return r;
    }
    function contarCarpetas(nodos) { return nodos.reduce((n, c) => n + 1 + contarCarpetas(c.subcarpetas || []), 0); }

    async function cargarEstructura() {
        const d = await consultar('obtener_estructura', { id_paciente: ID_PACIENTE });
        if (!d.success) {
            el.arbol.innerHTML = '<div class="mensaje-error">' + esc(d.mensaje || 'Error al cargar carpetas') + '</div>';
            return false;
        }
        estructura = d.estructura || [];
        construirMapa();
        if (d.permisos) permisos = d.permisos;
        if (d.papelera_dias) papeleraDias = d.papelera_dias;
        if (d.max_mb) maxMb = d.max_mb;
        if (d.extensiones) extensiones = d.extensiones;
        if (carpetaActual !== null && !mapa[carpetaActual]) carpetaActual = null;   // la carpeta ya no existe

        // La seleccion y el portapapeles solo conservan lo que sigue existiendo
        [...seleccion.keys()].forEach(k => { if (k.charAt(0) === 'c' && !mapa[Number(k.slice(2))]) seleccion.delete(k); });
        el.totalCarpetas.textContent = contarCarpetas(estructura);
        aplicarPermisosEnCabecera();
        renderArbol(); renderBreadcrumb(); renderCarpetas();
        await cargarDocumentos();
        cargarEstadisticas();
        return true;
    }

    async function cargarDocumentos() {
        const d = carpetaActual === null
            ? await consultar('obtener_documentos_raiz', { id_paciente: ID_PACIENTE })
            : await consultar('obtener_documentos_carpeta', { id_carpeta: carpetaActual });
        documentos = d.success ? (d.documentos || []) : [];
        if (!d.success) mostrarMensaje(d.mensaje || 'No se pudieron cargar los documentos', 'error');
        renderDocumentos();
        renderBarras();
    }

    async function cargarEstadisticas() {
        const d = await consultar('obtener_estadisticas', { id_paciente: ID_PACIENTE });
        if (d.success && d.estadisticas) {
            el.totalDocumentos.textContent = d.estadisticas.total_documentos || 0;
            el.tamanoTotal.textContent = formatearTamano(d.estadisticas.tamano_total || 0);
        }
    }

    function aplicarPermisosEnCabecera() {
        const mostrar = (nodo, ok) => { if (nodo) nodo.style.display = ok ? '' : 'none'; };
        mostrar(el.btnPapelera, permisos.papelera);
        mostrar(el.btnNuevaCarpeta, permisos.crear_carpeta);
        mostrar(el.btnSubir, permisos.subir);
        mostrar(el.btnPegar, permisos.mover || permisos.subir || permisos.crear_carpeta);
    }

    // =====================================================
    // Navegacion
    // =====================================================
    async function navegar(id) {
        carpetaActual = (id === null || id === undefined) ? null : Number(id);
        if (carpetaActual !== null && !mapa[carpetaActual]) carpetaActual = null;
        seleccion.clear();
        renderArbol(); renderBreadcrumb(); renderCarpetas();
        await cargarDocumentos();
    }

    function renderBreadcrumb() {
        const ruta = carpetaActual === null ? [] : ancestrosDe(carpetaActual).reverse();
        let html = '<button class="breadcrumb-item' + (carpetaActual === null ? ' activo' : '') + '" data-accion="crumb" data-id="raiz"><i class="ti ti-home"></i> Inicio</button>';
        ruta.forEach((id, i) => {
            html += '<span class="breadcrumb-separador">›</span>' +
                    '<button class="breadcrumb-item' + (i === ruta.length - 1 ? ' activo' : '') + '" data-accion="crumb" data-id="' + id + '">' + esc(mapa[id].nombre_carpeta) + '</button>';
        });
        el.breadcrumb.innerHTML = html;
    }

    const expandidas = new Set();
    function renderArbol() {
        let html = '<div class="item-arbol nivel-0' + (carpetaActual === null ? ' activo' : '') + '" data-accion="arbol" data-id="raiz">' +
                   '<span class="icono-carpeta"><i class="ti ti-home" style="color:#2a4d8f"></i></span><span class="nombre-carpeta">Inicio</span></div>';
        // Se abren las ramas que llevan a la carpeta actual
        ancestrosDe(carpetaActual).forEach(id => expandidas.add(id));
        (function pintar(nodos, nivel) {
            nodos.forEach(c => {
                const hijas = c.subcarpetas || [];
                const abierta = expandidas.has(c.id_carpeta);
                html += '<div class="item-arbol nivel-' + Math.min(nivel, 2) + (carpetaActual === c.id_carpeta ? ' activo' : '') + '" data-accion="arbol" data-id="' + c.id_carpeta + '" data-id-carpeta="' + c.id_carpeta + '">' +
                        (hijas.length ? '<span class="toggle-subcarpetas" data-accion="arbol-toggle" data-id="' + c.id_carpeta + '">' + (abierta ? '▼' : '▶') + '</span>' : '<span class="espaciador"></span>') +
                        '<span class="icono-carpeta"><i class="ti ti-folder" style="color:' + colorSeguro(c.color) + '"></i></span>' +
                        '<span class="nombre-carpeta">' + esc(c.nombre_carpeta) + '</span>' +
                        '<span class="contador-items">' + (Number(c.num_documentos) || 0) + '</span></div>';
                if (hijas.length) {
                    html += '<div class="contenedor-subcarpetas" style="display:' + (abierta ? 'block' : 'none') + '">';
                    pintar(hijas, nivel + 1);
                    html += '</div>';
                }
            });
        })(estructura, 1);
        el.arbol.innerHTML = html;
    }

    // =====================================================
    // Pintado de carpetas y documentos
    // =====================================================
    function puedeCopiarItem(tipo) { return tipo === 'carpeta' ? permisos.crear_carpeta : permisos.subir; }
    function haySeleccionable() { return permisos.mover || permisos.eliminar || permisos.crear_carpeta || permisos.subir; }

    function opcionMenu(accion, icono, texto, peligro) {
        return '<button class="opcion-menu' + (peligro ? ' opcion-peligro' : '') + '" data-accion="' + accion + '"><i class="ti ' + icono + '" style="margin-right:6px"></i>' + texto + '</button>';
    }

    function renderCarpetas() {
        const carpetas = hijosDe(carpetaActual);
        if (!carpetas.length) { el.grid.innerHTML = '<div class="mensaje-vacio">No hay carpetas en esta ubicación</div>'; return; }
        let html = '';
        carpetas.forEach(c => {
            const sistema = Number(c.es_sistema) === 1;
            const k = clave('carpeta', c.id_carpeta);
            let menu = opcionMenu('abrir', 'ti-folder-open', 'Abrir');
            if (puedeCopiarItem('carpeta')) menu += opcionMenu('copiar', 'ti-copy', 'Copiar');
            if (!sistema && permisos.mover) menu += opcionMenu('cortar', 'ti-scissors', 'Cortar');
            if (!sistema && permisos.renombrar) menu += opcionMenu('renombrar', 'ti-pencil', 'Renombrar');
            if (!sistema && permisos.eliminar) menu += opcionMenu('eliminar', 'ti-trash', 'Eliminar', true);
            const arrastrable = permisos.mover && !sistema;
            html += '<div class="tarjeta-carpeta' + (haySeleccionable() ? ' con-check' : '') + (seleccion.has(k) ? ' seleccionada' : '') + '" data-tipo="carpeta" data-id="' + c.id_carpeta + '" data-clave="' + k + '"' + (arrastrable ? ' draggable="true"' : '') + '>' +
                (haySeleccionable() ? '<label class="check-seleccion"><input type="checkbox" data-accion="seleccionar"' + (seleccion.has(k) ? ' checked' : '') + '></label>' : '') +
                '<div class="carpeta-header"><span class="carpeta-icono"><i class="ti ti-folder" style="color:' + colorSeguro(c.color) + '"></i></span>' +
                '<div class="carpeta-menu"><button class="btn-menu-carpeta" data-accion="menu"><i class="ti ti-dots-vertical"></i></button>' +
                '<div class="menu-opciones" style="display:none;">' + menu + '</div></div></div>' +
                '<div class="carpeta-body" data-accion="abrir"><h4 class="carpeta-nombre">' + esc(c.nombre_carpeta) + '</h4>' +
                '<div class="carpeta-stats"><span class="stat">' + (Number(c.num_subcarpetas) || 0) + ' carpetas</span><span class="stat">' + (Number(c.num_documentos) || 0) + ' archivos</span></div></div>' +
                '<div class="carpeta-footer"><span class="carpeta-creador" title="' + esc('Creado por ' + (c.creado_por_nombre || 'Sistema')) + '">' + esc(c.creado_por_nombre || 'Sistema') + '</span></div></div>';
        });
        el.grid.innerHTML = html;
    }

    function renderDocumentos() {
        if (!documentos.length) { el.lista.innerHTML = '<div class="mensaje-vacio">No hay documentos en esta ubicación</div>'; return; }
        let html = '';
        documentos.forEach(d => {
            const id = Number(d.id_documento);
            const k = clave('documento', id);
            const ext = String(d.extension || '').toLowerCase();
            let menu = '';
            if (permisos.descargar) menu += opcionMenu('descargar', 'ti-download', 'Descargar');
            if (permisos.subir) menu += opcionMenu('copiar', 'ti-copy', 'Copiar');
            if (permisos.mover) menu += opcionMenu('cortar', 'ti-scissors', 'Cortar');
            if (permisos.renombrar) menu += opcionMenu('renombrar', 'ti-pencil', 'Renombrar / editar');
            if (permisos.eliminar) menu += opcionMenu('eliminar', 'ti-trash', 'Eliminar', true);
            const boton = !permisos.descargar ? '' :
                (EXT_PREVIEW.indexOf(ext) >= 0
                    ? '<button class="btn-accion-doc" data-accion="ver" title="Vista previa">Ver</button>'
                    : '<button class="btn-accion-doc" data-accion="descargar" title="Descargar">Descargar</button>');
            html += '<div class="item-documento' + (seleccion.has(k) ? ' seleccionado' : '') + '" data-tipo="documento" data-id="' + id + '" data-clave="' + k + '"' + (permisos.mover ? ' draggable="true"' : '') + '>' +
                (haySeleccionable() ? '<label class="check-seleccion"><input type="checkbox" data-accion="seleccionar"' + (seleccion.has(k) ? ' checked' : '') + '></label>' : '') +
                '<div class="documento-icono">' + esc(ext.toUpperCase() || 'ARCH') + '</div>' +
                '<div class="documento-info"><h4 class="documento-titulo">' + esc(d.titulo) + '</h4>' +
                '<p class="documento-descripcion">' + esc(d.descripcion || 'Sin descripción') + '</p>' +
                '<div class="documento-meta"><span>' + esc(formatearFecha(d.fecha_subida)) + '</span><span>' + esc(formatearTamano(d.tamano_archivo)) + '</span><span>' + esc(d.subido_por_nombre || '') + '</span></div></div>' +
                '<div class="documento-acciones">' + boton +
                (menu ? '<div class="carpeta-menu"><button class="btn-menu-carpeta" data-accion="menu"><i class="ti ti-dots-vertical"></i></button><div class="menu-opciones" style="display:none;">' + menu + '</div></div>' : '') +
                '</div></div>';
        });
        el.lista.innerHTML = html;
    }

    // =====================================================
    // Seleccion y portapapeles
    // =====================================================
    function nombreDeItem(tipo, id) {
        if (tipo === 'carpeta') return mapa[id] ? mapa[id].nombre_carpeta : '';
        const d = documentos.find(x => Number(x.id_documento) === id);
        return d ? d.titulo : '';
    }
    function alternarSeleccion(tipo, id, marcado) {
        const k = clave(tipo, id);
        if (marcado) seleccion.set(k, { tipo: tipo, id: id, nombre: nombreDeItem(tipo, id), sistema: tipo === 'carpeta' && mapa[id] && Number(mapa[id].es_sistema) === 1 });
        else seleccion.delete(k);
        const nodo = raiz.querySelector('[data-clave="' + k + '"]');
        if (nodo) nodo.classList.toggle(tipo === 'carpeta' ? 'seleccionada' : 'seleccionado', marcado);
        renderBarras();
    }
    function limpiarSeleccion() {
        seleccion.clear();
        raiz.querySelectorAll('.seleccionada, .seleccionado').forEach(n => n.classList.remove('seleccionada', 'seleccionado'));
        raiz.querySelectorAll('[data-accion="seleccionar"]').forEach(c => { c.checked = false; });
        renderBarras();
    }
    function seleccionarTodo() {
        hijosDe(carpetaActual).forEach(c => seleccion.set(clave('carpeta', c.id_carpeta), { tipo: 'carpeta', id: c.id_carpeta, nombre: c.nombre_carpeta, sistema: Number(c.es_sistema) === 1 }));
        documentos.forEach(d => { const id = Number(d.id_documento); seleccion.set(clave('documento', id), { tipo: 'documento', id: id, nombre: d.titulo, sistema: false }); });
        renderCarpetas(); renderDocumentos(); renderBarras();
    }

    // Elementos sobre los que actua una accion del menu: toda la seleccion si el elemento forma parte de ella
    function itemsParaAccion(tipo, id) {
        if (seleccion.has(clave(tipo, id)) && seleccion.size > 1) return [...seleccion.values()];
        return [{ tipo: tipo, id: id, nombre: nombreDeItem(tipo, id), sistema: tipo === 'carpeta' && mapa[id] && Number(mapa[id].es_sistema) === 1 }];
    }

    function renderBarras() {
        // Barra de seleccion
        if (seleccion.size === 0) { el.barraSel.style.display = 'none'; el.barraSel.innerHTML = ''; }
        else {
            const items = [...seleccion.values()];
            const hayCarpeta = items.some(i => i.tipo === 'carpeta');
            const haySistema = items.some(i => i.sistema);
            const copiar = items.every(i => puedeCopiarItem(i.tipo));
            let acciones = '<button class="btn-barra" data-accion="barra-todo"><i class="ti ti-checks"></i> Seleccionar todo</button>';
            if (permisos.mover) acciones += '<button class="btn-barra" data-accion="barra-cortar"' + (haySistema ? ' disabled title="Hay carpetas del sistema en la selección"' : '') + '><i class="ti ti-scissors"></i> Cortar</button>';
            if (copiar) acciones += '<button class="btn-barra" data-accion="barra-copiar"><i class="ti ti-copy"></i> Copiar</button>';
            if (permisos.eliminar) acciones += '<button class="btn-barra btn-barra-peligro" data-accion="barra-eliminar"' + (haySistema ? ' disabled title="Hay carpetas del sistema en la selección"' : '') + '><i class="ti ti-trash"></i> Eliminar</button>';
            acciones += '<button class="btn-barra" data-accion="barra-cancelar"><i class="ti ti-x"></i> Cancelar</button>';
            el.barraSel.innerHTML = '<span class="barra-texto">' + items.length + (items.length === 1 ? ' elemento seleccionado' : ' elementos seleccionados') + (hayCarpeta ? ' (las carpetas incluyen su contenido)' : '') + '</span><div class="barra-acciones">' + acciones + '</div>';
            el.barraSel.style.display = 'flex';
        }
        // Barra de portapapeles
        if (!portapapeles) { el.barraPortapapeles.style.display = 'none'; el.barraPortapapeles.innerHTML = ''; }
        else {
            const n = portapapeles.items.length;
            const verbo = portapapeles.accion === 'cortar' ? (n === 1 ? 'cortado' : 'cortados') : (n === 1 ? 'copiado' : 'copiados');
            el.barraPortapapeles.innerHTML = '<span class="barra-texto">' + n + (n === 1 ? ' elemento ' : ' elementos ') + verbo + '. Abre la carpeta de destino y pulsa Pegar.</span>' +
                '<div class="barra-acciones"><button class="btn-barra" data-accion="pegar"><i class="ti ti-clipboard"></i> Pegar aquí</button>' +
                '<button class="btn-barra" data-accion="portapapeles-cancelar"><i class="ti ti-x"></i> Cancelar</button></div>';
            el.barraPortapapeles.style.display = 'flex';
        }
        if (el.btnPegar) {
            el.btnPegar.disabled = !portapapeles;
            el.btnPegar.title = portapapeles ? 'Pegar ' + portapapeles.items.length + ' elemento(s)' : 'Pegar';
        }
    }

    function ponerEnPortapapeles(accion, items) {
        portapapeles = { accion: accion, items: items.map(i => ({ tipo: i.tipo, id: i.id, nombre: i.nombre })) };
        limpiarSeleccion();
        mostrarMensaje(items.length + (items.length === 1 ? ' elemento ' : ' elementos ') + (accion === 'cortar' ? 'listo(s) para mover' : 'copiado(s)') + '. Abre la carpeta de destino y pulsa Pegar.', 'exito');
    }

    function conflictoDeJerarquia(items, destino) {
        // Una carpeta no puede ir dentro de si misma ni de una de sus subcarpetas
        const ancestros = destino === null ? [] : ancestrosDe(destino);
        return items.some(i => i.tipo === 'carpeta' && ancestros.indexOf(i.id) >= 0);
    }

    async function pegar() {
        if (!portapapeles || ocupado) return;
        const destino = carpetaActual;
        const items = portapapeles.items;
        const mover = portapapeles.accion === 'cortar';
        if (conflictoDeJerarquia(items, destino)) {
            mostrarMensaje('No se puede ' + (mover ? 'mover' : 'copiar') + ' una carpeta dentro de sí misma o de una de sus subcarpetas', 'error');
            return;
        }
        ocupado = true;
        const r = await llamar(mover ? 'mover_items' : 'copiar_items', { items: items.map(i => ({ tipo: i.tipo, id: i.id })), id_carpeta_destino: destino });
        ocupado = false;
        if (r.success) {
            mostrarMensaje(r.mensaje || 'Listo', 'exito');
            if (mover) portapapeles = null;      // cortar se consume; copiar se puede pegar varias veces
            await cargarEstructura();
        } else {
            mostrarMensaje(r.mensaje || 'No se pudo completar la acción', 'error');
        }
        renderBarras();
    }

    async function moverItems(items, destino) {
        if (ocupado) return;
        ocupado = true;
        const r = await llamar('mover_items', { items: items.map(i => ({ tipo: i.tipo, id: i.id })), id_carpeta_destino: destino });
        ocupado = false;
        if (r.success) { mostrarMensaje(r.mensaje || 'Movido', 'exito'); limpiarSeleccion(); await cargarEstructura(); }
        else mostrarMensaje(r.mensaje || 'No se pudo mover', 'error');
    }

    // =====================================================
    // Arrastrar y soltar (mover)
    // =====================================================
    function ubicacionActualDe(item) {
        return item.tipo === 'carpeta' ? (mapa[item.id] ? mapa[item.id].padre : null) : carpetaActual;
    }
    function destinoValido(destino) {
        if (!arrastre || !permisos.mover) return false;
        if (conflictoDeJerarquia(arrastre.items, destino)) return false;
        return arrastre.items.some(i => ubicacionActualDe(i) !== destino);   // al menos uno cambiaria de lugar
    }
    function destinoDe(nodo) {
        const carpeta = nodo.closest && nodo.closest('.tarjeta-carpeta[data-tipo="carpeta"]');
        if (carpeta && raiz.contains(carpeta)) return { id: Number(carpeta.dataset.id), nodo: carpeta };
        const arbolItem = nodo.closest && nodo.closest('.item-arbol[data-accion="arbol"]');
        if (arbolItem && raiz.contains(arbolItem)) return { id: arbolItem.dataset.id === 'raiz' ? null : Number(arbolItem.dataset.id), nodo: arbolItem };
        const crumb = nodo.closest && nodo.closest('.breadcrumb-item');
        if (crumb && raiz.contains(crumb)) return { id: crumb.dataset.id === 'raiz' ? null : Number(crumb.dataset.id), nodo: crumb };
        return null;
    }
    function quitarMarcasDeArrastre() {
        raiz.querySelectorAll('.destino-valido, .arrastrando').forEach(n => n.classList.remove('destino-valido', 'arrastrando'));
    }

    on(raiz, 'dragstart', e => {
        const nodo = e.target.closest && e.target.closest('[data-tipo][draggable="true"]');
        if (!nodo || !raiz.contains(nodo)) return;
        const tipo = nodo.dataset.tipo, id = Number(nodo.dataset.id);
        const items = seleccion.has(clave(tipo, id)) ? [...seleccion.values()] : [{ tipo: tipo, id: id }];
        arrastre = { items: items.map(i => ({ tipo: i.tipo, id: i.id })) };
        arrastre.items.forEach(i => { const n = raiz.querySelector('[data-clave="' + clave(i.tipo, i.id) + '"]'); if (n) n.classList.add('arrastrando'); });
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', 'documentos'); } catch (err) { /* algunos navegadores lo exigen */ }
        }
    });
    on(raiz, 'dragend', () => { arrastre = null; quitarMarcasDeArrastre(); });
    on(raiz, 'dragover', e => {
        const t = arrastre ? destinoDe(e.target) : null;
        if (!t || !destinoValido(t.id)) return;
        e.preventDefault();                       // sin esto el navegador no permite soltar
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
        raiz.querySelectorAll('.destino-valido').forEach(n => { if (n !== t.nodo) n.classList.remove('destino-valido'); });
        t.nodo.classList.add('destino-valido');
    });
    on(raiz, 'dragleave', e => {
        const t = destinoDe(e.target);
        if (t && !t.nodo.contains(e.relatedTarget)) t.nodo.classList.remove('destino-valido');
    });
    on(raiz, 'drop', e => {
        const t = arrastre ? destinoDe(e.target) : null;
        if (!t) return;
        e.preventDefault();
        const items = arrastre ? arrastre.items : [];
        const valido = destinoValido(t.id);
        arrastre = null;
        quitarMarcasDeArrastre();
        if (valido) moverItems(items, t.id);
    });

    // =====================================================
    // Eventos de clic (delegacion)
    // =====================================================
    function cerrarMenus() { raiz.querySelectorAll('.menu-opciones').forEach(m => { m.style.display = 'none'; }); }
    onDoc('click', e => { if (!(e.target.closest && e.target.closest('.carpeta-menu'))) cerrarMenus(); });
    onDoc('keydown', e => {
        if (e.key === 'Escape' && !document.querySelector('.modal-overlay.mostrar')) { limpiarSeleccion(); }
    });

    on(raiz, 'change', e => {
        const chk = e.target;
        if (!chk.matches || !chk.matches('[data-accion="seleccionar"]')) return;
        const cont = chk.closest('[data-tipo]');
        if (cont) alternarSeleccion(cont.dataset.tipo, Number(cont.dataset.id), chk.checked);
    });

    on(raiz, 'click', e => {
        const objetivo = e.target.closest && e.target.closest('[data-accion]');
        if (!objetivo || !raiz.contains(objetivo)) return;
        const accion = objetivo.dataset.accion;
        const cont = objetivo.closest('[data-tipo]');
        const tipo = cont ? cont.dataset.tipo : null;
        const id = cont ? Number(cont.dataset.id) : null;

        switch (accion) {
            case 'seleccionar': return;                           // lo maneja "change"
            case 'menu': {
                e.stopPropagation();
                const menu = objetivo.nextElementSibling;
                raiz.querySelectorAll('.menu-opciones').forEach(m => { if (m !== menu) m.style.display = 'none'; });
                if (menu) menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                return;
            }
            case 'abrir': return navegar(id);
            case 'crumb': return navegar(objetivo.dataset.id === 'raiz' ? null : Number(objetivo.dataset.id));
            case 'arbol': return navegar(objetivo.dataset.id === 'raiz' ? null : Number(objetivo.dataset.id));
            case 'arbol-toggle': {
                e.stopPropagation();
                const cid = Number(objetivo.dataset.id);
                if (expandidas.has(cid)) expandidas.delete(cid); else expandidas.add(cid);
                return renderArbol();
            }
            case 'ver': return previsualizar(id);
            case 'descargar': cerrarMenus(); return descargar(id);
            case 'renombrar': cerrarMenus(); return tipo === 'carpeta' ? abrirEditarCarpeta(id) : abrirEditarDocumento(id);
            case 'cortar': cerrarMenus(); return ponerEnPortapapeles('cortar', itemsParaAccion(tipo, id));
            case 'copiar': cerrarMenus(); return ponerEnPortapapeles('copiar', itemsParaAccion(tipo, id));
            case 'eliminar': cerrarMenus(); return pedirEliminar(itemsParaAccion(tipo, id));
            case 'barra-todo': return seleccionarTodo();
            case 'barra-cortar': return ponerEnPortapapeles('cortar', [...seleccion.values()]);
            case 'barra-copiar': return ponerEnPortapapeles('copiar', [...seleccion.values()]);
            case 'barra-eliminar': return pedirEliminar([...seleccion.values()]);
            case 'barra-cancelar': return limpiarSeleccion();
            case 'pegar': return pegar();
            case 'portapapeles-cancelar': portapapeles = null; return renderBarras();
        }
    });

    // =====================================================
    // Eliminar (a la papelera)
    // =====================================================
    function pedirEliminar(items) {
        if (!items.length) return;
        if (items.some(i => i.sistema)) { mostrarMensaje('Las carpetas del sistema no se pueden eliminar', 'error'); return; }
        eliminarPendiente = items.map(i => ({ tipo: i.tipo, id: i.id, nombre: i.nombre }));
        el.textoEliminar.textContent = items.length === 1
            ? '¿Enviar "' + items[0].nombre + '" a la papelera?'
            : '¿Enviar ' + items.length + ' elementos a la papelera?';
        el.listaEliminar.innerHTML = '';
        items.slice(0, 6).forEach(i => {
            const li = document.createElement('li');
            li.textContent = (i.tipo === 'carpeta' ? 'Carpeta: ' : 'Documento: ') + i.nombre;
            el.listaEliminar.appendChild(li);
        });
        if (items.length > 6) { const li = document.createElement('li'); li.textContent = 'y ' + (items.length - 6) + ' más...'; el.listaEliminar.appendChild(li); }
        const hayCarpeta = items.some(i => i.tipo === 'carpeta');
        el.ayudaEliminar.textContent = (hayCarpeta ? 'Las carpetas se envían con todo su contenido. ' : '') +
            (permisos.papelera
                ? 'Podrás recuperarlos desde la Papelera durante ' + papeleraDias + ' días; después se borran definitivamente.'
                : 'Solo un administrador puede recuperarlos, y se borran definitivamente a los ' + papeleraDias + ' días.');
        abrirModal(el.mEliminar);
    }
    async function confirmarEliminar() {
        if (!eliminarPendiente || ocupado) return;
        ocupado = true; el.btnConfirmarEliminar.disabled = true;
        const r = await llamar('eliminar_items', { items: eliminarPendiente.map(i => ({ tipo: i.tipo, id: i.id })) });
        ocupado = false; el.btnConfirmarEliminar.disabled = false;
        if (r.success) {
            mostrarMensaje(r.mensaje || 'Enviado a la papelera', 'exito');
            cerrarModal(el.mEliminar);
            eliminarPendiente = null;
            const quitados = new Set(); // lo eliminado ya no puede quedar seleccionado ni en el portapapeles
            if (portapapeles) portapapeles.items = portapapeles.items.filter(p => !(eliminarIncluye(p)));
            if (portapapeles && !portapapeles.items.length) portapapeles = null;
            limpiarSeleccion();
            await cargarEstructura();
        } else {
            mostrarMensaje(r.mensaje || 'No se pudo eliminar', 'error');
        }
    }
    function eliminarIncluye(p) { return eliminarPendiente && eliminarPendiente.some(i => i.tipo === p.tipo && i.id === p.id); }

    // =====================================================
    // Papelera
    // =====================================================
    async function abrirPapelera() {
        el.ayudaPapelera.textContent = 'Los elementos eliminados se borran definitivamente a los ' + papeleraDias + ' días.';
        el.listaPapelera.innerHTML = '<div class="mensaje-vacio">Cargando...</div>';
        abrirModal(el.mPapelera);
        const d = await consultar('listar_papelera', { id_paciente: ID_PACIENTE });
        if (!d.success) { el.listaPapelera.innerHTML = '<div class="mensaje-error">' + esc(d.mensaje || 'No se pudo cargar la papelera') + '</div>'; return; }
        if (d.papelera_dias) { papeleraDias = d.papelera_dias; el.ayudaPapelera.textContent = 'Los elementos eliminados se borran definitivamente a los ' + papeleraDias + ' días.'; }
        if (!d.items.length) { el.listaPapelera.innerHTML = '<div class="mensaje-vacio">La papelera está vacía</div>'; return; }
        el.listaPapelera.innerHTML = d.items.map(i => {
            const icono = i.tipo === 'carpeta' ? '<i class="ti ti-folder"></i>' : esc(String(i.extension || 'ARCH').toUpperCase());
            const detalle = [
                'Eliminado por ' + (i.eliminado_por_nombre || 'desconocido'),
                formatearFecha(i.fecha_eliminacion),
                'Desde: ' + (i.ubicacion || 'Inicio'),
                i.tipo === 'carpeta' ? (i.elementos + (i.elementos === 1 ? ' elemento' : ' elementos')) : '',
                'Se borra en ' + i.dias_restantes + (i.dias_restantes === 1 ? ' día' : ' días'),
            ].filter(Boolean).map(t => '<span>' + esc(t) + '</span>').join('');
            return '<div class="item-papelera" data-lote="' + esc(i.lote) + '"><div class="documento-icono">' + icono + '</div>' +
                   '<div class="documento-info"><h4 class="documento-titulo">' + esc(i.nombre) + '</h4><div class="documento-meta">' + detalle + '</div></div>' +
                   '<button class="btn-restaurar" data-lote="' + esc(i.lote) + '">Restaurar</button></div>';
        }).join('');
    }
    async function restaurar(lote, boton) {
        if (ocupado) return;
        ocupado = true; boton.disabled = true;
        const r = await llamar('restaurar_papelera', { lote: lote });
        ocupado = false;
        if (r.success) { mostrarMensaje(r.mensaje || 'Elemento restaurado', 'exito'); await cargarEstructura(); await abrirPapelera(); }
        else { boton.disabled = false; mostrarMensaje(r.mensaje || 'No se pudo restaurar', 'error'); }
    }
    on(el.listaPapelera, 'click', e => {
        const b = e.target.closest && e.target.closest('.btn-restaurar');
        if (b) restaurar(b.dataset.lote, b);
    });

    // =====================================================
    // Crear y renombrar carpetas
    // =====================================================
    function abrirNuevaCarpeta() { el.nombreCarpeta.value = ''; abrirModal(el.mNueva); el.nombreCarpeta.focus(); }
    async function guardarNuevaCarpeta() {
        const nombre = el.nombreCarpeta.value.trim();
        if (!nombre) { mostrarMensaje('El nombre de la carpeta es obligatorio', 'error'); return; }
        const b = $('btnGuardarCarpeta'); b.disabled = true;
        const r = await llamar('crear_carpeta', { id_paciente: ID_PACIENTE, nombre_carpeta: nombre, id_carpeta_padre: carpetaActual, color: el.colorCarpeta.value });
        b.disabled = false;
        if (r.success) { mostrarMensaje('Carpeta creada correctamente', 'exito'); cerrarModal(el.mNueva); await cargarEstructura(); }
        else mostrarMensaje(r.mensaje || 'No se pudo crear la carpeta', 'error');
    }
    function abrirEditarCarpeta(id) {
        const c = mapa[id]; if (!c) return;
        el.editIdCarpeta.value = id; el.editNombreCarpeta.value = c.nombre_carpeta; el.editColorCarpeta.value = colorSeguro(c.color);
        abrirModal(el.mEditCarpeta);
    }
    async function guardarEdicionCarpeta() {
        const b = $('btnGuardarEditarCarpeta'); b.disabled = true;
        const r = await llamar('editar_carpeta', { id_carpeta: Number(el.editIdCarpeta.value), nombre_carpeta: el.editNombreCarpeta.value.trim(), color: el.editColorCarpeta.value });
        b.disabled = false;
        if (r.success) { mostrarMensaje('Carpeta actualizada', 'exito'); cerrarModal(el.mEditCarpeta); await cargarEstructura(); }
        else mostrarMensaje(r.mensaje || 'No se pudo actualizar', 'error');
    }
    function abrirEditarDocumento(id) {
        const d = documentos.find(x => Number(x.id_documento) === id); if (!d) return;
        el.editIdDoc.value = id; el.editTituloDoc.value = d.titulo; el.editDescDoc.value = d.descripcion || '';
        abrirModal(el.mEditDoc);
    }
    async function guardarEdicionDocumento() {
        const b = $('btnGuardarEditarDocumento'); b.disabled = true;
        const r = await llamar('editar_documento', { id_documento: Number(el.editIdDoc.value), titulo: el.editTituloDoc.value.trim(), descripcion: el.editDescDoc.value.trim() });
        b.disabled = false;
        if (r.success) { mostrarMensaje('Documento actualizado', 'exito'); cerrarModal(el.mEditDoc); await cargarDocumentos(); }
        else mostrarMensaje(r.mensaje || 'No se pudo actualizar', 'error');
    }

    // =====================================================
    // Subir documentos
    // =====================================================
    function abrirSubir() {
        let opciones = '<option value="">Inicio (sin carpeta)</option>';
        (function agregar(nodos, nivel) {
            nodos.forEach(c => {
                opciones += '<option value="' + c.id_carpeta + '">' + '\u00A0\u00A0'.repeat(nivel) + esc(c.nombre_carpeta) + '</option>';
                agregar(c.subcarpetas || [], nivel + 1);
            });
        })(estructura, 0);
        el.carpetaDestino.innerHTML = opciones;
        if (carpetaActual !== null) el.carpetaDestino.value = String(carpetaActual);
        el.inputArchivo.setAttribute('accept', extensiones.map(x => '.' + x).join(','));
        el.textoInfoArchivo.textContent = 'Formatos: ' + extensiones.map(x => x.toUpperCase()).join(', ') + ' (máx. ' + maxMb + ' MB)';
        limpiarArchivo();
        abrirModal(el.mSubir);
    }
    function cerrarSubir() {
        cerrarModal(el.mSubir);
        setTimeout(() => { $('formSubirDocumento').reset(); limpiarArchivo(); }, 300);
    }
    function limpiarArchivo() {
        el.inputArchivo.value = '';
        el.zonaSubida.style.display = 'block';
        el.archivoSel.style.display = 'none';
        el.btnGuardarDoc.disabled = true;
    }
    function validarArchivo(archivo) {
        const ext = (archivo.name.split('.').pop() || '').toLowerCase();
        if (extensiones.indexOf(ext) < 0) return 'Tipo de archivo no permitido. Se aceptan: ' + extensiones.map(x => x.toUpperCase()).join(', ');
        if (archivo.size <= 0) return 'El archivo está vacío';
        if (archivo.size > maxMb * 1048576) return 'El archivo es demasiado grande. Máximo ' + maxMb + ' MB';
        return '';
    }
    function mostrarArchivo(archivo) {
        const problema = validarArchivo(archivo);
        if (problema) { mostrarMensaje(problema, 'error'); limpiarArchivo(); return false; }
        el.nombreArchivoSel.textContent = archivo.name;
        el.tamanoArchivoSel.textContent = formatearTamano(archivo.size);
        el.iconoArchivoSel.textContent = (archivo.name.split('.').pop() || 'ARCH').toUpperCase();
        el.zonaSubida.style.display = 'none';
        el.archivoSel.style.display = 'block';
        if (!el.tituloDoc.value) el.tituloDoc.value = archivo.name.replace(/\.[^/.]+$/, '');
        el.btnGuardarDoc.disabled = false;
        return true;
    }
    async function guardarDocumento() {
        const archivo = el.inputArchivo.files[0];
        if (!archivo) { mostrarMensaje('Selecciona un archivo', 'error'); return; }
        if (validarArchivo(archivo)) { mostrarMensaje(validarArchivo(archivo), 'error'); return; }
        const fd = new FormData();
        fd.append('accion', 'subir_documento');
        fd.append('archivo', archivo);
        fd.append('id_paciente', ID_PACIENTE);
        fd.append('id_carpeta', el.carpetaDestino.value || '');
        fd.append('titulo', el.tituloDoc.value.trim());
        fd.append('descripcion', el.descDoc.value.trim());
        el.btnGuardarDoc.disabled = true; el.btnGuardarDoc.textContent = 'Subiendo...';
        const r = await pedir(CTRL, { method: 'POST', body: fd });
        el.btnGuardarDoc.textContent = 'Subir Documento';
        if (r.success) {
            mostrarMensaje('Documento subido correctamente', 'exito');
            cerrarSubir();
            await cargarEstructura();
        } else {
            el.btnGuardarDoc.disabled = false;
            mostrarMensaje(r.mensaje || 'No se pudo subir el documento', 'error');
        }
    }

    // =====================================================
    // Ver y descargar (siempre a traves del sistema: el archivo no se abre por su direccion)
    // =====================================================
    function urlDocumento(id, modo) {
        return CTRL + '?' + new URLSearchParams({ accion: 'descargar_documento', id_documento: id, modo: modo }).toString();
    }
    function descargar(id) { window.open(urlDocumento(id, 'descargar'), '_blank'); }
    function previsualizar(id) {
        const d = documentos.find(x => Number(x.id_documento) === id); if (!d) return;
        const ext = String(d.extension || '').toLowerCase();
        el.tituloPreview.textContent = d.titulo || 'Vista previa';
        el.btnDescargarPreview.onclick = () => descargar(id);
        el.contenedorPreview.innerHTML = '';
        if (ext === 'pdf') {
            const f = document.createElement('iframe'); f.setAttribute('src', urlDocumento(id, 'ver')); el.contenedorPreview.appendChild(f);
        } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) >= 0) {
            const im = document.createElement('img'); im.setAttribute('src', urlDocumento(id, 'ver')); im.setAttribute('alt', d.titulo || ''); el.contenedorPreview.appendChild(im);
        } else {
            const m = document.createElement('div'); m.className = 'preview-no-disponible';
            m.textContent = 'La vista previa solo está disponible para PDF e imágenes. Descarga el archivo para verlo.';
            el.contenedorPreview.appendChild(m);
        }
        abrirModal(el.mPreview);
    }
    function cerrarPreview() { cerrarModal(el.mPreview); setTimeout(() => { el.contenedorPreview.innerHTML = ''; }, 300); }

    // =====================================================
    // Botones y modales
    // =====================================================
    on(el.btnPapelera, 'click', abrirPapelera);
    on(el.btnPegar, 'click', pegar);
    on(el.btnNuevaCarpeta, 'click', abrirNuevaCarpeta);
    on(el.btnSubir, 'click', abrirSubir);

    on($('btnCerrarModalCarpeta'), 'click', () => cerrarModal(el.mNueva));
    on($('btnCancelarCarpeta'), 'click', () => cerrarModal(el.mNueva));
    on($('btnGuardarCarpeta'), 'click', guardarNuevaCarpeta);
    on(el.nombreCarpeta, 'keydown', e => { if (e.key === 'Enter') { e.preventDefault(); guardarNuevaCarpeta(); } });

    on($('btnCerrarModalDocumento'), 'click', cerrarSubir);
    on($('btnCancelarDocumento'), 'click', cerrarSubir);
    on(el.btnGuardarDoc, 'click', guardarDocumento);
    on($('btnSeleccionarArchivo'), 'click', () => el.inputArchivo.click());
    on(el.inputArchivo, 'change', () => { if (el.inputArchivo.files[0]) mostrarArchivo(el.inputArchivo.files[0]); });
    on($('btnEliminarArchivo'), 'click', limpiarArchivo);
    on(el.zonaSubida, 'click', () => el.inputArchivo.click());
    on(el.zonaSubida, 'dragover', e => { e.preventDefault(); el.zonaSubida.classList.add('drag-over'); });
    on(el.zonaSubida, 'dragleave', e => { e.preventDefault(); el.zonaSubida.classList.remove('drag-over'); });
    on(el.zonaSubida, 'drop', e => {
        e.preventDefault(); el.zonaSubida.classList.remove('drag-over');
        const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) { el.inputArchivo.files = e.dataTransfer.files; mostrarArchivo(f); }
    });

    on($('btnCerrarModalEditarCarpeta'), 'click', () => cerrarModal(el.mEditCarpeta));
    on($('btnCancelarEditarCarpeta'), 'click', () => cerrarModal(el.mEditCarpeta));
    on($('btnGuardarEditarCarpeta'), 'click', guardarEdicionCarpeta);
    on(el.editNombreCarpeta, 'keydown', e => { if (e.key === 'Enter') { e.preventDefault(); guardarEdicionCarpeta(); } });
    on($('btnCerrarModalEditarDocumento'), 'click', () => cerrarModal(el.mEditDoc));
    on($('btnCancelarEditarDocumento'), 'click', () => cerrarModal(el.mEditDoc));
    on($('btnGuardarEditarDocumento'), 'click', guardarEdicionDocumento);

    on($('btnCerrarModalEliminar'), 'click', () => cerrarModal(el.mEliminar));
    on($('btnCancelarEliminar'), 'click', () => cerrarModal(el.mEliminar));
    on(el.btnConfirmarEliminar, 'click', confirmarEliminar);

    on($('btnCerrarModalPapelera'), 'click', () => cerrarModal(el.mPapelera));
    on($('btnCerrarPapelera'), 'click', () => cerrarModal(el.mPapelera));
    on($('btnCerrarModalPreview'), 'click', cerrarPreview);
    on($('btnCerrarPreview'), 'click', cerrarPreview);

    // =====================================================
    // Inicio y limpieza
    // =====================================================
    window.__moduloDocumentosLimpiar = function () {
        limpiezas.forEach(f => { try { f(); } catch (e) { /* ya no existe */ } });
        delete window.__moduloDocumentosLimpiar;
    };

    if (!ID_PACIENTE) {
        mostrarMensaje('No se pudo cargar el módulo: paciente no válido', 'error');
        return;
    }
    aplicarPermisosEnCabecera();
    renderBarras();
    cargarEstructura();
})();
