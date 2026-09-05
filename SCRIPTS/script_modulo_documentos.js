// =====================================================
// 📁 MÓDULO DE DOCUMENTOS - PARTE 1
// =====================================================

console.log('📁 Iniciando módulo de documentos');

// =====================================================
// 🔹 VARIABLES GLOBALES
// =====================================================
var carpetaActual = null;
var rutaActual = [];
var estructuraCarpetas = [];
var documentosActuales = [];
var portapapeles = null; // { tipo: 'carpeta'|'documento', id, nombre, accion: 'copiar'|'cortar' }

// =====================================================
// 🔹 ELEMENTOS DEL DOM
// =====================================================
var elementos = {
    // Estadísticas
    totalCarpetas: document.getElementById('totalCarpetas'),
    totalDocumentos: document.getElementById('totalDocumentos'),
    tamanoTotal: document.getElementById('tamanoTotal'),
    
    // Navegación
    breadcrumb: document.querySelector('.breadcrumb-navegacion'),
    arbolCarpetas: document.getElementById('arbolCarpetas'),
    
    // Contenido
    gridCarpetas: document.getElementById('gridCarpetas'),
    listaDocumentos: document.getElementById('listaDocumentos'),
    
    // Botones principales
    btnNuevaCarpeta: document.getElementById('btnNuevaCarpeta'),
    btnSubirDocumento: document.getElementById('btnSubirDocumento'),
    btnPegar: document.getElementById('btnPegar'),
    
    // Modal Nueva Carpeta
    modalNuevaCarpeta: document.getElementById('modalNuevaCarpeta'),
    formNuevaCarpeta: document.getElementById('formNuevaCarpeta'),
    nombreCarpeta: document.getElementById('nombreCarpeta'),
    colorCarpeta: document.getElementById('colorCarpeta'),
    btnCerrarModalCarpeta: document.getElementById('btnCerrarModalCarpeta'),
    btnCancelarCarpeta: document.getElementById('btnCancelarCarpeta'),
    btnGuardarCarpeta: document.getElementById('btnGuardarCarpeta'),
    
    // Modal Subir Documento
    modalSubirDocumento: document.getElementById('modalSubirDocumento'),
    formSubirDocumento: document.getElementById('formSubirDocumento'),
    zonaSubida: document.getElementById('zonaSubida'),
    inputArchivo: document.getElementById('inputArchivo'),
    btnSeleccionarArchivo: document.getElementById('btnSeleccionarArchivo'),
    archivoSeleccionado: document.getElementById('archivoSeleccionado'),
    nombreArchivoSeleccionado: document.getElementById('nombreArchivoSeleccionado'),
    tamanoArchivoSeleccionado: document.getElementById('tamanoArchivoSeleccionado'),
    iconoArchivoSeleccionado: document.getElementById('iconoArchivoSeleccionado'),
    btnEliminarArchivo: document.getElementById('btnEliminarArchivo'),
    tituloDocumento: document.getElementById('tituloDocumento'),
    descripcionDocumento: document.getElementById('descripcionDocumento'),
    carpetaDestino: document.getElementById('carpetaDestino'),
    btnCerrarModalDocumento: document.getElementById('btnCerrarModalDocumento'),
    btnCancelarDocumento: document.getElementById('btnCancelarDocumento'),
    btnGuardarDocumento: document.getElementById('btnGuardarDocumento'),
    
    // Modal Editar Carpeta
    modalEditarCarpeta: document.getElementById('modalEditarCarpeta'),
    editarIdCarpeta: document.getElementById('editarIdCarpeta'),
    editarNombreCarpeta: document.getElementById('editarNombreCarpeta'),
    editarColorCarpeta: document.getElementById('editarColorCarpeta'),
    btnCerrarModalEditarCarpeta: document.getElementById('btnCerrarModalEditarCarpeta'),
    btnCancelarEditarCarpeta: document.getElementById('btnCancelarEditarCarpeta'),
    btnGuardarEditarCarpeta: document.getElementById('btnGuardarEditarCarpeta'),
    
    // Modal Editar Documento
    modalEditarDocumento: document.getElementById('modalEditarDocumento'),
    editarIdDocumento: document.getElementById('editarIdDocumento'),
    editarTituloDocumento: document.getElementById('editarTituloDocumento'),
    editarDescripcionDocumento: document.getElementById('editarDescripcionDocumento'),
    btnCerrarModalEditarDocumento: document.getElementById('btnCerrarModalEditarDocumento'),
    btnCancelarEditarDocumento: document.getElementById('btnCancelarEditarDocumento'),
    btnGuardarEditarDocumento: document.getElementById('btnGuardarEditarDocumento'),

    // Modal Vista Previa
    modalPreviewDocumento: document.getElementById('modalPreviewDocumento'),
    tituloPreviewDocumento: document.getElementById('tituloPreviewDocumento'),
    contenedorPreview: document.getElementById('contenedorPreview'),
    btnCerrarModalPreview: document.getElementById('btnCerrarModalPreview'),
    btnCerrarPreview: document.getElementById('btnCerrarPreview'),
    btnDescargarDesdePreview: document.getElementById('btnDescargarDesdePreview')
};

// =====================================================
// 🔹 INICIALIZACIÓN
// =====================================================
function inicializarModuloDocumentos() {
    console.log('🚀 Inicializando módulo de documentos para paciente:', ID_PACIENTE_ACTUAL);
    
    if (!ID_PACIENTE_ACTUAL) {
        mostrarMensaje('❌ No se pudo cargar el módulo: ID de paciente no válido', 'error');
        return;
    }
    
    // Eventos de botones principales
    elementos.btnNuevaCarpeta.addEventListener('click', abrirModalNuevaCarpeta);
    elementos.btnSubirDocumento.addEventListener('click', abrirModalSubirDocumento);
    elementos.btnPegar.addEventListener('click', pegarElemento);
    
    // Eventos Modal Nueva Carpeta
    elementos.btnCerrarModalCarpeta.addEventListener('click', cerrarModalNuevaCarpeta);
    elementos.btnCancelarCarpeta.addEventListener('click', cerrarModalNuevaCarpeta);
    elementos.btnGuardarCarpeta.addEventListener('click', guardarNuevaCarpeta);
    
    // Eventos Modal Subir Documento
    elementos.btnCerrarModalDocumento.addEventListener('click', cerrarModalSubirDocumento);
    elementos.btnCancelarDocumento.addEventListener('click', cerrarModalSubirDocumento);
    elementos.btnGuardarDocumento.addEventListener('click', guardarDocumento);
    elementos.btnSeleccionarArchivo.addEventListener('click', () => elementos.inputArchivo.click());
    elementos.inputArchivo.addEventListener('change', manejarSeleccionArchivo);
    elementos.btnEliminarArchivo.addEventListener('click', limpiarArchivoSeleccionado);
    
    // Drag & Drop
    elementos.zonaSubida.addEventListener('click', () => elementos.inputArchivo.click());
    elementos.zonaSubida.addEventListener('dragover', manejarDragOver);
    elementos.zonaSubida.addEventListener('dragleave', manejarDragLeave);
    elementos.zonaSubida.addEventListener('drop', manejarDrop);
    
    // Eventos Modal Editar Carpeta
    elementos.btnCerrarModalEditarCarpeta.addEventListener('click', cerrarModalEditarCarpeta);
    elementos.btnCancelarEditarCarpeta.addEventListener('click', cerrarModalEditarCarpeta);
    elementos.btnGuardarEditarCarpeta.addEventListener('click', guardarEdicionCarpeta);
    
    // Eventos Modal Editar Documento
    elementos.btnCerrarModalEditarDocumento.addEventListener('click', cerrarModalEditarDocumento);
    elementos.btnCancelarEditarDocumento.addEventListener('click', cerrarModalEditarDocumento);
    elementos.btnGuardarEditarDocumento.addEventListener('click', guardarEdicionDocumento);

    // Eventos Modal Vista Previa
    elementos.btnCerrarModalPreview.addEventListener('click', cerrarModalPreview);
    elementos.btnCerrarPreview.addEventListener('click', cerrarModalPreview);
    
    // Cargar datos iniciales
    cargarEstructuraCarpetas();
    cargarEstadisticas();
    actualizarBotonPegar();
}

// =====================================================
// 📊 CARGAR ESTADÍSTICAS
// =====================================================
function cargarEstadisticas() {
    fetch(`../CONTROLADORES/controlador_documentos.php?accion=obtener_estadisticas&id_paciente=${ID_PACIENTE_ACTUAL}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const stats = data.estadisticas;
                elementos.totalDocumentos.textContent = stats.total_documentos || 0;
                elementos.tamanoTotal.textContent = formatearTamano(stats.tamano_total || 0);
            }
        })
        .catch(err => console.error('❌ Error al cargar estadísticas:', err));
}

// =====================================================
// 🗂️ CARGAR ESTRUCTURA DE CARPETAS
// =====================================================
function cargarEstructuraCarpetas() {
    console.log('📂 Cargando estructura de carpetas...');
    
    fetch(`../CONTROLADORES/controlador_documentos.php?accion=obtener_estructura&id_paciente=${ID_PACIENTE_ACTUAL}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                estructuraCarpetas = data.estructura;
                console.log('✅ Estructura cargada:', estructuraCarpetas);
                
                renderizarArbolCarpetas(estructuraCarpetas);
                cargarContenidoRaiz();
                actualizarEstadisticasCarpetas();
            } else {
                console.error('❌ Error:', data.mensaje);
                elementos.arbolCarpetas.innerHTML = '<div class="mensaje-error">Error al cargar carpetas</div>';
            }
        })
        .catch(err => {
            console.error('❌ Error al cargar estructura:', err);
            elementos.arbolCarpetas.innerHTML = '<div class="mensaje-error">Error de conexión</div>';
        });
}

// =====================================================
// 🌳 RENDERIZAR ÁRBOL DE CARPETAS
// =====================================================
function renderizarArbolCarpetas(carpetas, contenedor = elementos.arbolCarpetas, nivel = 0) {
    if (nivel === 0) {
        contenedor.innerHTML = '';
        
        // Elemento raíz
        const itemRaiz = document.createElement('div');
        itemRaiz.className = 'item-arbol nivel-0 activo';
        itemRaiz.innerHTML = `
            <span class="icono-carpeta"><i class="ti ti-home" style="color:#2a4d8f"></i></span>
            <span class="nombre-carpeta">Inicio</span>
        `;
        itemRaiz.addEventListener('click', (e) => {
            e.stopPropagation();
            navegarACarpeta(null);
        });
        contenedor.appendChild(itemRaiz);
    }
    
    if (!carpetas || carpetas.length === 0) {
        if (nivel === 0) {
            contenedor.innerHTML += '<div class="mensaje-vacio">No hay carpetas</div>';
        }
        return;
    }
    
    carpetas.forEach(carpeta => {
        const item = document.createElement('div');
        item.className = `item-arbol nivel-${nivel}`;
        item.dataset.idCarpeta = carpeta.id_carpeta;
        
        const tieneSubcarpetas = carpeta.subcarpetas && carpeta.subcarpetas.length > 0;
        
        item.innerHTML = `
            ${tieneSubcarpetas ? '<span class="toggle-subcarpetas">▶</span>' : '<span class="espaciador"></span>'}
            <span class="icono-carpeta"><i class="ti ti-folder" style="color:${carpeta.color || '#2a4d8f'}"></i></span>
            <span class="nombre-carpeta">${carpeta.nombre_carpeta}</span>
            <span class="contador-items">${carpeta.num_documentos || 0}</span>
        `;
        
        // Click en el nombre de la carpeta
        const nombreCarpeta = item.querySelector('.nombre-carpeta');
        nombreCarpeta.addEventListener('click', (e) => {
            e.stopPropagation();
            navegarACarpeta(carpeta);
        });
        
        // Toggle de subcarpetas
        if (tieneSubcarpetas) {
            const toggle = item.querySelector('.toggle-subcarpetas');
            const contenedorSubcarpetas = document.createElement('div');
            contenedorSubcarpetas.className = 'contenedor-subcarpetas';
            contenedorSubcarpetas.style.display = 'none';
            
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const expandido = contenedorSubcarpetas.style.display === 'block';
                contenedorSubcarpetas.style.display = expandido ? 'none' : 'block';
                toggle.textContent = expandido ? '▶' : '▼';
            });
            
            contenedor.appendChild(item);
            contenedor.appendChild(contenedorSubcarpetas);
            
            renderizarArbolCarpetas(carpeta.subcarpetas, contenedorSubcarpetas, nivel + 1);
        } else {
            contenedor.appendChild(item);
        }
    });
}

// =====================================================
// 🧭 NAVEGACIÓN
// =====================================================
function navegarACarpeta(carpeta) {
    console.log('🧭 Navegando a carpeta:', carpeta);
    
    carpetaActual = carpeta;
    
    // Actualizar items activos en el árbol
    document.querySelectorAll('.item-arbol').forEach(item => {
        item.classList.remove('activo');
    });
    
    if (carpeta) {
        const itemArbol = document.querySelector(`.item-arbol[data-id-carpeta="${carpeta.id_carpeta}"]`);
        if (itemArbol) itemArbol.classList.add('activo');
    } else {
        document.querySelector('.item-arbol.nivel-0').classList.add('activo');
    }
    
    actualizarBreadcrumb();
    cargarContenidoCarpeta(carpeta);
}

function cargarContenidoRaiz() {
    carpetaActual = null;
    actualizarBreadcrumb();
    
    // Carpetas raíz
    const carpetasRaiz = estructuraCarpetas;
    renderizarCarpetas(carpetasRaiz);
    
    // No mostrar documentos en la raíz
    elementos.listaDocumentos.innerHTML = `
        <div class="mensaje-vacio-ilustrado">
            <span class="icono-vacio"><i class="ti ti-folder-open"></i></span>
            <p>Selecciona una carpeta para ver sus documentos</p>
            <span class="texto-secundario-vacio">Los documentos que subas aparecerán aquí</span>
        </div>`;
}

function cargarContenidoCarpeta(carpeta) {
    if (!carpeta) {
        cargarContenidoRaiz();
        return;
    }
    
    // Cargar subcarpetas
    const subcarpetas = carpeta.subcarpetas || [];
    renderizarCarpetas(subcarpetas);
    
    // Cargar documentos
    cargarDocumentosCarpeta(carpeta.id_carpeta);
}

// =====================================================
// 📋 BREADCRUMB
// =====================================================
function actualizarBreadcrumb() {
    const breadcrumb = elementos.breadcrumb;
    breadcrumb.innerHTML = '';
    
    // Inicio
    const btnInicio = document.createElement('button');
    btnInicio.className = 'breadcrumb-item';
    btnInicio.innerHTML = '<i class="ti ti-home"></i> Inicio';
    btnInicio.addEventListener('click', () => navegarACarpeta(null));
    breadcrumb.appendChild(btnInicio);
    
    // Carpeta actual
    if (carpetaActual) {
        const separador = document.createElement('span');
        separador.className = 'breadcrumb-separador';
        separador.textContent = '›';
        breadcrumb.appendChild(separador);
        
        const btnCarpeta = document.createElement('button');
        btnCarpeta.className = 'breadcrumb-item activo';
        btnCarpeta.textContent = carpetaActual.nombre_carpeta;
        breadcrumb.appendChild(btnCarpeta);
    } else {
        btnInicio.classList.add('activo');
    }
}

// =====================================================
// 📁 RENDERIZAR CARPETAS
// =====================================================
function renderizarCarpetas(carpetas) {
    if (!carpetas || carpetas.length === 0) {
        elementos.gridCarpetas.innerHTML = '<div class="mensaje-vacio">No hay carpetas en esta ubicación</div>';
        return;
    }
    
    let html = '';
    carpetas.forEach(carpeta => {
        html += `
            <div class="tarjeta-carpeta" data-id="${carpeta.id_carpeta}">
                <div class="carpeta-header">
                    <span class="carpeta-icono"><i class="ti ti-folder" style="color:${carpeta.color || '#2a4d8f'}"></i></span>
                    <div class="carpeta-menu">
                        <button class="btn-menu-carpeta" data-id="${carpeta.id_carpeta}">⋮</button>
                        <div class="menu-opciones" style="display: none;">
                            <button class="opcion-menu" onclick="abrirCarpeta(${carpeta.id_carpeta})">Abrir</button>
                            <button class="opcion-menu" onclick="copiarElemento('carpeta', ${carpeta.id_carpeta}, '${(carpeta.nombre_carpeta || '').replace(/'/g, "\\'")}')">Copiar</button>
                            ${!carpeta.es_sistema ? `
                                <button class="opcion-menu" onclick="cortarElemento('carpeta', ${carpeta.id_carpeta}, '${(carpeta.nombre_carpeta || '').replace(/'/g, "\\'")}')">Cortar</button>
                                <button class="opcion-menu" onclick="editarCarpeta(${carpeta.id_carpeta})">Editar</button>
                                <button class="opcion-menu opcion-peligro" onclick="eliminarCarpeta(${carpeta.id_carpeta})">Eliminar</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
                <div class="carpeta-body" onclick="navegarACarpeta(${JSON.stringify(carpeta).replace(/"/g, '&quot;')})">
                    <h4 class="carpeta-nombre">${carpeta.nombre_carpeta}</h4>
                    <div class="carpeta-stats">
                        <span class="stat">${carpeta.num_subcarpetas || 0} carpetas</span>
                        <span class="stat">${carpeta.num_documentos || 0} archivos</span>
                    </div>
                </div>
                <div class="carpeta-footer">
                    <span class="carpeta-creador" title="Creado por ${carpeta.creado_por_nombre}">
                        ${carpeta.creado_por_nombre || 'Sistema'}
                    </span>
                </div>
            </div>
        `;
    });
    
    elementos.gridCarpetas.innerHTML = html;
    
    // Eventos de menús
    document.querySelectorAll('.btn-menu-carpeta').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenuCarpeta(e.target);
        });
    });
}

function toggleMenuCarpeta(btn) {
    const menu = btn.nextElementSibling;
    const todosMenus = document.querySelectorAll('.menu-opciones');
    
    todosMenus.forEach(m => {
        if (m !== menu) m.style.display = 'none';
    });
    
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Cerrar menús al hacer click fuera
document.addEventListener('click', (e) => {
    if (!e.target.closest('.carpeta-menu')) {
        document.querySelectorAll('.menu-opciones').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});

// (Continúa en Parte 2...)
// =====================================================
// 📁 MÓDULO DE DOCUMENTOS - PARTE 2
// =====================================================

// =====================================================
// 📄 CARGAR Y RENDERIZAR DOCUMENTOS
// =====================================================
function cargarDocumentosCarpeta(idCarpeta) {
    fetch(`../CONTROLADORES/controlador_documentos.php?accion=obtener_documentos_carpeta&id_carpeta=${idCarpeta}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                documentosActuales = data.documentos;
                renderizarDocumentos(data.documentos);
            } else {
                elementos.listaDocumentos.innerHTML = '<div class="mensaje-error">Error al cargar documentos</div>';
            }
        })
        .catch(err => {
            console.error('❌ Error:', err);
            elementos.listaDocumentos.innerHTML = '<div class="mensaje-error">Error de conexión</div>';
        });
}

function renderizarDocumentos(documentos) {
    if (!documentos || documentos.length === 0) {
        elementos.listaDocumentos.innerHTML = '<div class="mensaje-vacio">No hay documentos en esta carpeta</div>';
        return;
    }
    
    let html = '';
    documentos.forEach(doc => {
        const extension = (doc.extension || '').toUpperCase();
        const fecha = formatearFecha(doc.fecha_subida);
        const tamano = formatearTamano(doc.tamano_archivo);
        const tituloEscapado = (doc.titulo || '').replace(/'/g, "\\'");
        
        html += `
            <div class="item-documento" data-id="${doc.id_documento}">
                <div class="documento-icono">${extension || 'ARCH'}</div>
                <div class="documento-info">
                    <h4 class="documento-titulo">${doc.titulo}</h4>
                    <p class="documento-descripcion">${doc.descripcion || 'Sin descripción'}</p>
                    <div class="documento-meta">
                        <span>${fecha}</span>
                        <span>${tamano}</span>
                        <span>${doc.subido_por_nombre}</span>
                    </div>
                </div>
                <div class="documento-acciones">
                    <button class="btn-accion-doc" onclick="previsualizarDocumento(${doc.id_documento}, '${doc.archivo}', '${doc.extension}', '${tituloEscapado}')" title="Vista previa">Ver</button>
                    <div class="carpeta-menu">
                        <button class="btn-menu-carpeta btn-menu-documento" data-id="${doc.id_documento}">⋮</button>
                        <div class="menu-opciones" style="display: none;">
                            <button class="opcion-menu" onclick="descargarDocumento(${doc.id_documento}, '${doc.archivo}')">Descargar</button>
                            <button class="opcion-menu" onclick="copiarElemento('documento', ${doc.id_documento}, '${tituloEscapado}')">Copiar</button>
                            <button class="opcion-menu" onclick="cortarElemento('documento', ${doc.id_documento}, '${tituloEscapado}')">Cortar</button>
                            <button class="opcion-menu" onclick="editarDocumento(${doc.id_documento})">Editar</button>
                            <button class="opcion-menu opcion-peligro" onclick="eliminarDocumento(${doc.id_documento})">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    elementos.listaDocumentos.innerHTML = html;

    // Eventos de menús de documentos
    document.querySelectorAll('.btn-menu-documento').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenuCarpeta(e.target);
        });
    });
}

// =====================================================
// 📁 GESTIÓN DE CARPETAS
// =====================================================
function abrirModalNuevaCarpeta() {
    elementos.modalNuevaCarpeta.style.display = 'flex';
    setTimeout(() => elementos.modalNuevaCarpeta.classList.add('mostrar'), 10);
    elementos.nombreCarpeta.value = '';
    elementos.nombreCarpeta.focus();
}

function cerrarModalNuevaCarpeta() {
    elementos.modalNuevaCarpeta.classList.remove('mostrar');
    setTimeout(() => elementos.modalNuevaCarpeta.style.display = 'none', 300);
}

function guardarNuevaCarpeta() {
    const nombre = elementos.nombreCarpeta.value.trim();
    
    if (!nombre) {
        mostrarMensaje('❌ El nombre de la carpeta es obligatorio', 'error');
        return;
    }
    
    const datos = {
        accion: 'crear_carpeta',
        nombre_carpeta: nombre,
        id_paciente: ID_PACIENTE_ACTUAL,
        id_carpeta_padre: carpetaActual ? carpetaActual.id_carpeta : null,
        color: elementos.colorCarpeta.value
    };
    
    elementos.btnGuardarCarpeta.disabled = true;
    elementos.btnGuardarCarpeta.textContent = 'Creando...';
    
    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datos)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ Carpeta creada correctamente', 'exito');
            cerrarModalNuevaCarpeta();
            cargarEstructuraCarpetas();
        } else {
            mostrarMensaje('❌ ' + data.mensaje, 'error');
        }
    })
    .catch(err => {
        console.error('❌ Error:', err);
        mostrarMensaje('❌ Error al crear carpeta', 'error');
    })
    .finally(() => {
        elementos.btnGuardarCarpeta.disabled = false;
        elementos.btnGuardarCarpeta.innerHTML = '<span>💾</span> Crear Carpeta';
    });
}

function editarCarpeta(idCarpeta) {
    fetch(`../CONTROLADORES/controlador_documentos.php?accion=obtener_carpeta&id_carpeta=${idCarpeta}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const carpeta = data.carpeta;
                elementos.editarIdCarpeta.value = carpeta.id_carpeta;
                elementos.editarNombreCarpeta.value = carpeta.nombre_carpeta;
                elementos.editarColorCarpeta.value = carpeta.color || '#5a6a89';
                
                elementos.modalEditarCarpeta.style.display = 'flex';
                setTimeout(() => elementos.modalEditarCarpeta.classList.add('mostrar'), 10);
            }
        })
        .catch(err => console.error('❌ Error:', err));
}

function cerrarModalEditarCarpeta() {
    elementos.modalEditarCarpeta.classList.remove('mostrar');
    setTimeout(() => elementos.modalEditarCarpeta.style.display = 'none', 300);
}

function guardarEdicionCarpeta() {
    const datos = {
        accion: 'editar_carpeta',
        id_carpeta: elementos.editarIdCarpeta.value,
        nombre_carpeta: elementos.editarNombreCarpeta.value.trim(),
        color: elementos.editarColorCarpeta.value
    };
    
    elementos.btnGuardarEditarCarpeta.disabled = true;
    
    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datos)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ Carpeta actualizada', 'exito');
            cerrarModalEditarCarpeta();
            cargarEstructuraCarpetas();
        } else {
            mostrarMensaje('❌ ' + data.mensaje, 'error');
        }
    })
    .finally(() => {
        elementos.btnGuardarEditarCarpeta.disabled = false;
    });
}

function eliminarCarpeta(idCarpeta) {
    if (!confirm('¿Está seguro de eliminar esta carpeta? Los documentos dentro NO se eliminarán.')) {
        return;
    }
    
    const datos = {
        accion: 'eliminar_carpeta',
        id_carpeta: idCarpeta
    };
    
    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datos)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ Carpeta eliminada', 'exito');
            cargarEstructuraCarpetas();
        } else {
            mostrarMensaje('❌ ' + data.mensaje, 'error');
        }
    });
}

// =====================================================
// 📤 SUBIR DOCUMENTOS
// =====================================================
function abrirModalSubirDocumento() {
    cargarSelectCarpetas();
    elementos.modalSubirDocumento.style.display = 'flex';
    setTimeout(() => elementos.modalSubirDocumento.classList.add('mostrar'), 10);
    limpiarArchivoSeleccionado();
}

function cerrarModalSubirDocumento() {
    elementos.modalSubirDocumento.classList.remove('mostrar');
    setTimeout(() => {
        elementos.modalSubirDocumento.style.display = 'none';
        elementos.formSubirDocumento.reset();
        limpiarArchivoSeleccionado();
    }, 300);
}

function cargarSelectCarpetas() {
    let html = '<option value="">Raíz (sin carpeta)</option>';
    
    function agregarCarpetas(carpetas, nivel = 0) {
        carpetas.forEach(carpeta => {
            const espaciado = '&nbsp;&nbsp;'.repeat(nivel);
            html += `<option value="${carpeta.id_carpeta}">${espaciado}${carpeta.nombre_carpeta}</option>`;
            if (carpeta.subcarpetas && carpeta.subcarpetas.length > 0) {
                agregarCarpetas(carpeta.subcarpetas, nivel + 1);
            }
        });
    }
    
    agregarCarpetas(estructuraCarpetas);
    elementos.carpetaDestino.innerHTML = html;
    
    if (carpetaActual) {
        elementos.carpetaDestino.value = carpetaActual.id_carpeta;
    }
}

function manejarSeleccionArchivo(e) {
    const archivo = e.target.files[0];
    if (archivo) {
        mostrarArchivoSeleccionado(archivo);
    }
}

function manejarDragOver(e) {
    e.preventDefault();
    elementos.zonaSubida.classList.add('drag-over');
}

function manejarDragLeave(e) {
    e.preventDefault();
    elementos.zonaSubida.classList.remove('drag-over');
}

function manejarDrop(e) {
    e.preventDefault();
    elementos.zonaSubida.classList.remove('drag-over');
    
    const archivo = e.dataTransfer.files[0];
    if (archivo) {
        elementos.inputArchivo.files = e.dataTransfer.files;
        mostrarArchivoSeleccionado(archivo);
    }
}

function mostrarArchivoSeleccionado(archivo) {
    const extension = archivo.name.split('.').pop().toLowerCase();
    const tamano = formatearTamano(archivo.size);
    const icono = obtenerIconoDocumento(extension);
    
    elementos.nombreArchivoSeleccionado.textContent = archivo.name;
    elementos.tamanoArchivoSeleccionado.textContent = tamano;
    elementos.iconoArchivoSeleccionado.textContent = icono;
    
    elementos.zonaSubida.style.display = 'none';
    elementos.archivoSeleccionado.style.display = 'block';
    
    if (!elementos.tituloDocumento.value) {
        elementos.tituloDocumento.value = archivo.name.replace(/\.[^/.]+$/, '');
    }
    
    elementos.btnGuardarDocumento.disabled = false;
}

function limpiarArchivoSeleccionado() {
    elementos.inputArchivo.value = '';
    elementos.zonaSubida.style.display = 'block';
    elementos.archivoSeleccionado.style.display = 'none';
    elementos.btnGuardarDocumento.disabled = true;
}

function guardarDocumento() {
    if (!elementos.inputArchivo.files[0]) {
        mostrarMensaje('❌ Seleccione un archivo', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('accion', 'subir_documento');
    formData.append('archivo', elementos.inputArchivo.files[0]);
    formData.append('id_paciente', ID_PACIENTE_ACTUAL);
    formData.append('id_carpeta', elementos.carpetaDestino.value || '');
    formData.append('titulo', elementos.tituloDocumento.value || elementos.inputArchivo.files[0].name);
    formData.append('descripcion', elementos.descripcionDocumento.value || '');
    
    elementos.btnGuardarDocumento.disabled = true;
    elementos.btnGuardarDocumento.textContent = 'Subiendo...';
    
    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ Documento subido correctamente', 'exito');
            cerrarModalSubirDocumento();
            if (carpetaActual) {
                cargarDocumentosCarpeta(carpetaActual.id_carpeta);
            }
            cargarEstadisticas();
        } else {
            mostrarMensaje('❌ ' + data.mensaje, 'error');
        }
    })
    .catch(err => {
        console.error('❌ Error:', err);
        mostrarMensaje('❌ Error al subir documento', 'error');
    })
    .finally(() => {
        elementos.btnGuardarDocumento.disabled = false;
        elementos.btnGuardarDocumento.innerHTML = '<span>⬆️</span> Subir Documento';
    });
}

// =====================================================
// 📝 EDITAR DOCUMENTO
// =====================================================
function editarDocumento(idDocumento) {
    const doc = documentosActuales.find(d => d.id_documento == idDocumento);
    if (!doc) return;
    
    elementos.editarIdDocumento.value = doc.id_documento;
    elementos.editarTituloDocumento.value = doc.titulo;
    elementos.editarDescripcionDocumento.value = doc.descripcion || '';
    
    elementos.modalEditarDocumento.style.display = 'flex';
    setTimeout(() => elementos.modalEditarDocumento.classList.add('mostrar'), 10);
}

function cerrarModalEditarDocumento() {
    elementos.modalEditarDocumento.classList.remove('mostrar');
    setTimeout(() => elementos.modalEditarDocumento.style.display = 'none', 300);
}

function guardarEdicionDocumento() {
    const datos = {
        accion: 'editar_documento',
        id_documento: elementos.editarIdDocumento.value,
        titulo: elementos.editarTituloDocumento.value.trim(),
        descripcion: elementos.editarDescripcionDocumento.value.trim()
    };
    
    elementos.btnGuardarEditarDocumento.disabled = true;
    
    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datos)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ Documento actualizado', 'exito');
            cerrarModalEditarDocumento();
            if (carpetaActual) {
                cargarDocumentosCarpeta(carpetaActual.id_carpeta);
            }
        } else {
            mostrarMensaje('❌ ' + data.mensaje, 'error');
        }
    })
    .finally(() => {
        elementos.btnGuardarEditarDocumento.disabled = false;
    });
}

// =====================================================
// 📥 DESCARGAR Y ELIMINAR DOCUMENTOS
// =====================================================
function descargarDocumento(idDocumento, rutaArchivo) {
    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            accion: 'registrar_descarga',
            id_documento: idDocumento
        })
    });
    
    window.open('../' + rutaArchivo, '_blank');
}

function eliminarDocumento(idDocumento) {
    if (!confirm('¿Está seguro de eliminar este documento? Esta acción no se puede deshacer.')) {
        return;
    }
    
    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            accion: 'eliminar_documento',
            id_documento: idDocumento
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ Documento eliminado', 'exito');
            if (carpetaActual) {
                cargarDocumentosCarpeta(carpetaActual.id_carpeta);
            }
            cargarEstadisticas();
        } else {
            mostrarMensaje('❌ ' + data.mensaje, 'error');
        }
    });
}

// =====================================================
// 🛠️ FUNCIONES AUXILIARES
// =====================================================
function obtenerIconoDocumento(extension) {
    return (extension || 'arch').toUpperCase();
}

function formatearTamano(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function formatearFecha(fecha) {
    if (!fecha) return 'N/A';
    const f = new Date(fecha);
    const opciones = { year: 'numeric', month: 'short', day: 'numeric' };
    return f.toLocaleDateString('es-ES', opciones);
}

function actualizarEstadisticasCarpetas() {
    let total = 0;
    function contar(carpetas) {
        total += carpetas.length;
        carpetas.forEach(c => {
            if (c.subcarpetas) contar(c.subcarpetas);
        });
    }
    contar(estructuraCarpetas);
    elementos.totalCarpetas.textContent = total;
}

function mostrarMensaje(mensaje, tipo = 'exito') {
    const aviso = document.createElement('div');
    aviso.className = `mensaje-sistema ${tipo}`;
    aviso.textContent = mensaje;
    aviso.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 10000;
        background: ${tipo === 'exito' ? '#27ae60' : '#e74c3c'};
        color: white; padding: 15px 25px; border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        opacity: 0; transform: translateX(100%);
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

// =====================================================
// 📋 COPIAR / CORTAR / PEGAR
// =====================================================
function copiarElemento(tipo, id, nombre) {
    portapapeles = { tipo, id, nombre, accion: 'copiar' };
    actualizarBotonPegar();
    mostrarMensaje(`"${nombre}" copiado. Navega a la carpeta destino y presiona "Pegar"`, 'exito');
}

function cortarElemento(tipo, id, nombre) {
    portapapeles = { tipo, id, nombre, accion: 'cortar' };
    actualizarBotonPegar();
    mostrarMensaje(`"${nombre}" listo para mover. Navega a la carpeta destino y presiona "Pegar"`, 'exito');
}

function actualizarBotonPegar() {
    if (!elementos.btnPegar) return;
    elementos.btnPegar.disabled = !portapapeles;
    elementos.btnPegar.title = portapapeles ? `Pegar "${portapapeles.nombre}"` : 'Pegar';
}

function pegarElemento() {
    if (!portapapeles) return;

    const idCarpetaDestino = carpetaActual ? carpetaActual.id_carpeta : null;

    // No permitir pegar una carpeta dentro de sí misma
    if (portapapeles.tipo === 'carpeta' && idCarpetaDestino === portapapeles.id) {
        mostrarMensaje('❌ No puedes pegar una carpeta dentro de sí misma', 'error');
        return;
    }

    let accion, datos;
    if (portapapeles.tipo === 'documento') {
        accion = portapapeles.accion === 'copiar' ? 'copiar_documento' : 'mover_documento';
        datos = { accion, id_documento: portapapeles.id, id_carpeta_destino: idCarpetaDestino };
    } else {
        accion = portapapeles.accion === 'copiar' ? 'copiar_carpeta' : 'mover_carpeta';
        datos = { accion, id_carpeta: portapapeles.id, id_carpeta_destino: idCarpetaDestino, id_paciente: ID_PACIENTE_ACTUAL };
    }

    fetch('../CONTROLADORES/controlador_documentos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('✅ ' + data.mensaje, 'exito');
            // Cortar consume el portapapeles; copiar lo deja disponible para pegar varias veces
            if (portapapeles.accion === 'cortar') {
                portapapeles = null;
                actualizarBotonPegar();
            }
            cargarEstructuraCarpetas();
        } else {
            mostrarMensaje('❌ ' + data.mensaje, 'error');
        }
    })
    .catch(err => {
        console.error('❌ Error al pegar:', err);
        mostrarMensaje('❌ Error al pegar el elemento', 'error');
    });
}

// =====================================================
// 👁️ VISTA PREVIA DE DOCUMENTOS
// =====================================================
var EXTENSIONES_IMAGEN = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
var EXTENSIONES_OFFICE = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

function previsualizarDocumento(idDocumento, archivo, extension, titulo) {
    elementos.tituloPreviewDocumento.textContent = titulo || 'Vista previa';
    elementos.btnDescargarDesdePreview.onclick = () => descargarDocumento(idDocumento, archivo);

    const ext = (extension || '').toLowerCase();
    // URL absoluta del archivo (necesaria para PDF/imagen y para visores externos de Office)
    const urlArchivo = new URL('../' + archivo, window.location.href).href;

    if (ext === 'pdf') {
        elementos.contenedorPreview.innerHTML = `<iframe src="${urlArchivo}"></iframe>`;
    } else if (EXTENSIONES_IMAGEN.includes(ext)) {
        elementos.contenedorPreview.innerHTML = `<img src="${urlArchivo}" alt="${titulo}">`;
    } else if (EXTENSIONES_OFFICE.includes(ext)) {
        const esLocal = /^(localhost|127\.0\.0\.1)/.test(window.location.hostname);
        if (esLocal) {
            // Los visores de Office/Google necesitan una URL pública; en localhost no funcionan
            elementos.contenedorPreview.innerHTML = `
                <div class="preview-no-disponible">
                    La vista previa de archivos Word/Excel/PowerPoint requiere que el sitio
                    esté publicado en internet (no funciona en localhost).<br>
                    Descarga el archivo para verlo, o prueba la vista previa una vez en producción.
                </div>`;
        } else {
            const urlVisor = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(urlArchivo)}`;
            elementos.contenedorPreview.innerHTML = `<iframe src="${urlVisor}"></iframe>`;
        }
    } else {
        elementos.contenedorPreview.innerHTML = `
            <div class="preview-no-disponible">
                Vista previa no disponible para este tipo de archivo.<br>
                Descárgalo para verlo.
            </div>`;
    }

    elementos.modalPreviewDocumento.style.display = 'flex';
    setTimeout(() => elementos.modalPreviewDocumento.classList.add('mostrar'), 10);
}

function cerrarModalPreview() {
    elementos.modalPreviewDocumento.classList.remove('mostrar');
    setTimeout(() => {
        elementos.modalPreviewDocumento.style.display = 'none';
        elementos.contenedorPreview.innerHTML = '';
    }, 300);
}

// =====================================================
// 🚀 INICIALIZAR AL CARGAR
// =====================================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarModuloDocumentos);
} else {
    inicializarModuloDocumentos();
}