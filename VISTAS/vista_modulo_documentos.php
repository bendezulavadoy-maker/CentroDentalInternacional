<?php
$idPaciente = $_GET['id_paciente'] ?? 0;
?>

<link rel="stylesheet" href="../ESTILOS/style_modulo_documentos.css">

<div class="contenedor-modulo-documentos">
    <!-- 📊 Header con estadísticas -->
    <div class="header-documentos">
        <div class="estadisticas-documentos">
            <div class="stat-item">
                <span class="stat-icono"><i class="ti ti-folder"></i></span>
                <div class="stat-info">
                    <span class="stat-valor" id="totalCarpetas">0</span>
                    <span class="stat-label">Carpetas</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icono"><i class="ti ti-file-text"></i></span>
                <div class="stat-info">
                    <span class="stat-valor" id="totalDocumentos">0</span>
                    <span class="stat-label">Documentos</span>
                </div>
            </div>
            <div class="stat-item">
                <span class="stat-icono"><i class="ti ti-database"></i></span>
                <div class="stat-info">
                    <span class="stat-valor" id="tamanoTotal">0 MB</span>
                    <span class="stat-label">Espacio usado</span>
                </div>
            </div>
        </div>

        <div class="acciones-header">
            <button class="btn-accion-header" id="btnPegar" title="Pegar" disabled>
                <i class="ti ti-clipboard"></i> Pegar
            </button>
            <button class="btn-accion-header" id="btnNuevaCarpeta" title="Nueva carpeta">
                <i class="ti ti-folder-plus"></i> Nueva Carpeta
            </button>
            <button class="btn-accion-header btn-primario" id="btnSubirDocumento" title="Subir documento">
                <i class="ti ti-upload"></i> Subir Documento
            </button>
        </div>
    </div>

    <!-- 🗂️ Navegación de ruta (Breadcrumb) -->
    <div class="breadcrumb-navegacion">
        <button class="breadcrumb-item activo" data-id="raiz">
            <span>🏠</span> Inicio
        </button>
    </div>

    <!-- 📁 Área principal: Carpetas y Documentos -->
    <div class="contenedor-principal">
        <!-- Columna izquierda: Árbol de carpetas -->
        <div class="columna-arbol">
            <div class="header-arbol">
                <h3>Estructura de Carpetas</h3>
            </div>
            <div class="arbol-carpetas" id="arbolCarpetas">
                <div class="cargando-arbol">⏳ Cargando estructura...</div>
            </div>
        </div>

        <!-- Columna derecha: Contenido -->
        <div class="columna-contenido">
            <!-- Carpetas del nivel actual -->
            <div class="seccion-carpetas">
                <h3 class="titulo-seccion">Carpetas</h3>
                <div class="grid-carpetas" id="gridCarpetas">
                    <div class="mensaje-vacio">No hay carpetas en esta ubicación</div>
                </div>
            </div>

            <!-- Documentos del nivel actual -->
            <div class="seccion-documentos">
                <h3 class="titulo-seccion">Documentos</h3>
                <div class="lista-documentos" id="listaDocumentos">
                    <div class="mensaje-vacio">No hay documentos en esta carpeta</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- 🗂️ MODAL: Nueva Carpeta -->
<!-- ===================================================== -->
<div id="modalNuevaCarpeta" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Nueva Carpeta</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalCarpeta">✖</button>
        </div>
        <div class="modal-cuerpo">
            <form id="formNuevaCarpeta">
                <div class="form-group">
                    <label for="nombreCarpeta">Nombre de la carpeta</label>
                    <input type="text" 
                           id="nombreCarpeta" 
                           class="input-text" 
                           placeholder="Ej: Estudios de laboratorio" 
                           required 
                           maxlength="100">
                </div>
                <div class="form-group">
                    <label for="colorCarpeta">Color</label>
                    <input type="color" id="colorCarpeta" class="input-color" value="#5a6a89">
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarCarpeta">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarCarpeta">
                <span>💾</span> Crear Carpeta
            </button>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- ⬆️ MODAL: Subir Documento -->
<!-- ===================================================== -->
<div id="modalSubirDocumento" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>⬆️ Subir Documento</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalDocumento">✖</button>
        </div>
        <div class="modal-cuerpo">
            <form id="formSubirDocumento" enctype="multipart/form-data">
                <div class="zona-subida" id="zonaSubida">
                    <div class="zona-subida-contenido">
                        <span class="icono-subida"><i class="ti ti-cloud-upload"></i></span>
                        <p class="texto-principal">Arrastra y suelta tu archivo aquí</p>
                        <p class="texto-secundario">o haz clic para seleccionar</p>
                        <input type="file" 
                               id="inputArchivo" 
                               name="archivo" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" 
                               hidden 
                               required>
                        <button type="button" class="btn-seleccionar" id="btnSeleccionarArchivo">
                            Seleccionar Archivo
                        </button>
                        <p class="texto-info">Formatos: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX (máx. 10MB)</p>
                    </div>
                </div>

                <div class="archivo-seleccionado" id="archivoSeleccionado" style="display: none;">
                    <div class="info-archivo">
                        <span class="icono-archivo" id="iconoArchivoSeleccionado"></span>
                        <div class="detalles-archivo">
                            <p class="nombre-archivo" id="nombreArchivoSeleccionado"></p>
                            <p class="tamano-archivo" id="tamanoArchivoSeleccionado"></p>
                        </div>
                        <button type="button" class="btn-eliminar-archivo" id="btnEliminarArchivo">✕</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="tituloDocumento">Título del documento</label>
                    <input type="text" 
                           id="tituloDocumento" 
                           name="titulo" 
                           class="input-text" 
                           placeholder="Ej: Radiografía panorámica - Enero 2024" 
                           maxlength="200">
                </div>

                <div class="form-group">
                    <label for="descripcionDocumento">Descripción (opcional)</label>
                    <textarea id="descripcionDocumento" 
                              name="descripcion" 
                              class="input-textarea" 
                              rows="3" 
                              placeholder="Agrega una descripción o notas sobre este documento..."></textarea>
                </div>

                <div class="form-group">
                    <label for="carpetaDestino">Guardar en carpeta</label>
                    <select id="carpetaDestino" name="id_carpeta" class="input-select">
                        <option value="">Raíz (sin carpeta)</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarDocumento">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarDocumento" disabled>
                <span>⬆️</span> Subir Documento
            </button>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- ✏️ MODAL: Editar Carpeta -->
<!-- ===================================================== -->
<div id="modalEditarCarpeta" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Editar Carpeta</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalEditarCarpeta">✖</button>
        </div>
        <div class="modal-cuerpo">
            <form id="formEditarCarpeta">
                <input type="hidden" id="editarIdCarpeta">
                
                <div class="form-group">
                    <label for="editarNombreCarpeta">Nombre de la carpeta</label>
                    <input type="text" 
                           id="editarNombreCarpeta" 
                           class="input-text" 
                           required 
                           maxlength="100">
                </div>
                <div class="form-group">
                    <label for="editarColorCarpeta">Color</label>
                    <input type="color" id="editarColorCarpeta" class="input-color">
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarEditarCarpeta">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarEditarCarpeta">
                <span>💾</span> Guardar Cambios
            </button>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- ✏️ MODAL: Editar Documento -->
<!-- ===================================================== -->
<div id="modalEditarDocumento" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Editar Documento</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalEditarDocumento">✖</button>
        </div>
        <div class="modal-cuerpo">
            <form id="formEditarDocumento">
                <input type="hidden" id="editarIdDocumento">
                
                <div class="form-group">
                    <label for="editarTituloDocumento">Título del documento</label>
                    <input type="text" 
                           id="editarTituloDocumento" 
                           class="input-text" 
                           required 
                           maxlength="200">
                </div>

                <div class="form-group">
                    <label for="editarDescripcionDocumento">Descripción</label>
                    <textarea id="editarDescripcionDocumento" 
                              class="input-textarea" 
                              rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarEditarDocumento">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarEditarDocumento">
                <span>💾</span> Guardar Cambios
            </button>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- 👁️ MODAL: Vista previa de documento -->
<!-- ===================================================== -->
<div id="modalPreviewDocumento" class="modal-overlay">
    <div class="modal-contenido modal-ancha">
        <div class="modal-encabezado">
            <h3 id="tituloPreviewDocumento">Vista previa</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalPreview">✖</button>
        </div>
        <div class="modal-cuerpo">
            <div class="contenedor-preview" id="contenedorPreview">
                <!-- Se llena dinámicamente -->
            </div>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCerrarPreview">Cerrar</button>
            <button type="button" class="btn-primario" id="btnDescargarDesdePreview">Descargar</button>
        </div>
    </div>
</div>

<script>
    // Pasar el ID del paciente al script
    const ID_PACIENTE_ACTUAL = <?php echo $idPaciente; ?>;
</script>
<script src="../SCRIPTS/script_modulo_documentos.js"></script>