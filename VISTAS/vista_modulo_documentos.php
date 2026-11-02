<?php
require_once '../CONFIG/verificar_sesion.php';
require_once '../CONFIG/permisos.php';
require_once '../CONFIG/parametros.php';

// El paciente SIEMPRE se convierte a numero (antes se imprimia tal cual dentro de un <script>)
$idPaciente = (int)($_GET['id_paciente'] ?? 0);

// Que botones se muestran. Es solo la primera pintura: el servidor vuelve a decidir en cada accion.
$permisos = [
    'subir' => false, 'crear_carpeta' => false, 'descargar' => false,
    'renombrar' => false, 'mover' => false, 'eliminar' => false, 'papelera' => false,
];
if ($idPaciente > 0) {
    $p = Permisos::enPaciente(['documentos.subir', 'documentos.crear_carpeta', 'documentos.descargar',
                               'documentos.renombrar', 'documentos.mover', 'documentos.eliminar'], $idPaciente);
    $permisos = [
        'subir' => $p['documentos.subir'], 'crear_carpeta' => $p['documentos.crear_carpeta'],
        'descargar' => $p['documentos.descargar'], 'renombrar' => $p['documentos.renombrar'],
        'mover' => $p['documentos.mover'], 'eliminar' => $p['documentos.eliminar'],
        'papelera' => Permisos::puede('documentos.papelera'),
    ];
}
$config = [
    'id_paciente'   => $idPaciente,
    'permisos'      => $permisos,
    'papelera_dias' => Parametros::entero('papelera_dias', 15),
    'max_mb'        => (int)floor(Parametros::maxBytesDocumento() / 1048576),
    'extensiones'   => Parametros::extensionesDocumento(),
];
$configJson = htmlspecialchars(json_encode($config), ENT_QUOTES, 'UTF-8');
function ocultoSi($permitido) { return $permitido ? '' : ' style="display:none;"'; }
?>

<link rel="stylesheet" href="../ESTILOS/style_modulo_documentos.css">

<div class="contenedor-modulo-documentos" id="moduloDocumentos" data-config="<?= $configJson ?>">

    <!-- Encabezado con estadisticas y acciones -->
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
            <button class="btn-accion-header" id="btnPapelera" title="Papelera"<?= ocultoSi($permisos['papelera']) ?>>
                <i class="ti ti-trash"></i> Papelera
            </button>
            <button class="btn-accion-header" id="btnPegar" title="Pegar" disabled<?= ocultoSi($permisos['mover'] || $permisos['subir'] || $permisos['crear_carpeta']) ?>>
                <i class="ti ti-clipboard"></i> Pegar
            </button>
            <button class="btn-accion-header" id="btnNuevaCarpeta" title="Nueva carpeta"<?= ocultoSi($permisos['crear_carpeta']) ?>>
                <i class="ti ti-folder-plus"></i> Nueva Carpeta
            </button>
            <button class="btn-accion-header btn-primario" id="btnSubirDocumento" title="Subir documento"<?= ocultoSi($permisos['subir']) ?>>
                <i class="ti ti-upload"></i> Subir Documento
            </button>
        </div>
    </div>

    <!-- Ruta de navegacion -->
    <div class="breadcrumb-navegacion">
        <button class="breadcrumb-item activo" data-id="raiz">
            <i class="ti ti-home"></i> Inicio
        </button>
    </div>

    <!-- Barras de seleccion y de portapapeles -->
    <div class="barra-portapapeles" id="barraPortapapeles" style="display:none;"></div>
    <div class="barra-seleccion" id="barraSeleccion" style="display:none;"></div>

    <!-- Area principal -->
    <div class="contenedor-principal">
        <div class="columna-arbol">
            <div class="header-arbol">
                <h3>Estructura de Carpetas</h3>
            </div>
            <div class="arbol-carpetas" id="arbolCarpetas">
                <div class="cargando-arbol">Cargando estructura...</div>
            </div>
        </div>

        <div class="columna-contenido">
            <div class="seccion-carpetas">
                <h3 class="titulo-seccion">Carpetas</h3>
                <div class="grid-carpetas" id="gridCarpetas">
                    <div class="mensaje-vacio">No hay carpetas en esta ubicación</div>
                </div>
            </div>

            <div class="seccion-documentos">
                <h3 class="titulo-seccion">Documentos</h3>
                <div class="lista-documentos" id="listaDocumentos">
                    <div class="mensaje-vacio">No hay documentos en esta ubicación</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Nueva carpeta -->
<div id="modalNuevaCarpeta" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Nueva Carpeta</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalCarpeta"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <form id="formNuevaCarpeta">
                <div class="form-group">
                    <label for="nombreCarpeta">Nombre de la carpeta</label>
                    <input type="text" id="nombreCarpeta" class="input-text" placeholder="Ej: Estudios de laboratorio" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="colorCarpeta">Color</label>
                    <input type="color" id="colorCarpeta" class="input-color" value="#5a6a89">
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarCarpeta">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarCarpeta">Crear Carpeta</button>
        </div>
    </div>
</div>

<!-- MODAL: Subir documento -->
<div id="modalSubirDocumento" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Subir Documento</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalDocumento"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <form id="formSubirDocumento" enctype="multipart/form-data">
                <div class="zona-subida" id="zonaSubida">
                    <div class="zona-subida-contenido">
                        <span class="icono-subida"><i class="ti ti-cloud-upload"></i></span>
                        <p class="texto-principal">Arrastra y suelta tu archivo aquí</p>
                        <p class="texto-secundario">o haz clic para seleccionar</p>
                        <input type="file" id="inputArchivo" name="archivo" hidden required>
                        <button type="button" class="btn-seleccionar" id="btnSeleccionarArchivo">Seleccionar Archivo</button>
                        <p class="texto-info" id="textoInfoArchivo"></p>
                    </div>
                </div>

                <div class="archivo-seleccionado" id="archivoSeleccionado" style="display: none;">
                    <div class="info-archivo">
                        <span class="icono-archivo" id="iconoArchivoSeleccionado"></span>
                        <div class="detalles-archivo">
                            <p class="nombre-archivo" id="nombreArchivoSeleccionado"></p>
                            <p class="tamano-archivo" id="tamanoArchivoSeleccionado"></p>
                        </div>
                        <button type="button" class="btn-eliminar-archivo" id="btnEliminarArchivo"><i class="ti ti-x"></i></button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="tituloDocumento">Título del documento</label>
                    <input type="text" id="tituloDocumento" name="titulo" class="input-text" placeholder="Ej: Radiografía panorámica - Enero 2024" maxlength="200">
                </div>

                <div class="form-group">
                    <label for="descripcionDocumento">Descripción (opcional)</label>
                    <textarea id="descripcionDocumento" name="descripcion" class="input-textarea" rows="3" maxlength="1000"
                              placeholder="Agrega una descripción o notas sobre este documento..."></textarea>
                </div>

                <div class="form-group">
                    <label for="carpetaDestino">Guardar en carpeta</label>
                    <select id="carpetaDestino" name="id_carpeta" class="input-select">
                        <option value="">Inicio (sin carpeta)</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarDocumento">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarDocumento" disabled>Subir Documento</button>
        </div>
    </div>
</div>

<!-- MODAL: Renombrar carpeta -->
<div id="modalEditarCarpeta" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Editar Carpeta</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalEditarCarpeta"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <form id="formEditarCarpeta">
                <input type="hidden" id="editarIdCarpeta">
                <div class="form-group">
                    <label for="editarNombreCarpeta">Nombre de la carpeta</label>
                    <input type="text" id="editarNombreCarpeta" class="input-text" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="editarColorCarpeta">Color</label>
                    <input type="color" id="editarColorCarpeta" class="input-color">
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarEditarCarpeta">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarEditarCarpeta">Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- MODAL: Renombrar documento -->
<div id="modalEditarDocumento" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Editar Documento</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalEditarDocumento"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <form id="formEditarDocumento">
                <input type="hidden" id="editarIdDocumento">
                <div class="form-group">
                    <label for="editarTituloDocumento">Título del documento</label>
                    <input type="text" id="editarTituloDocumento" class="input-text" required maxlength="200">
                </div>
                <div class="form-group">
                    <label for="editarDescripcionDocumento">Descripción</label>
                    <textarea id="editarDescripcionDocumento" class="input-textarea" rows="3" maxlength="1000"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarEditarDocumento">Cancelar</button>
            <button type="button" class="btn-primario" id="btnGuardarEditarDocumento">Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- MODAL: Confirmar eliminacion (envia a la papelera) -->
<div id="modalConfirmarEliminar" class="modal-overlay">
    <div class="modal-contenido modal-mediano">
        <div class="modal-encabezado">
            <h3>Eliminar</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalEliminar"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <p class="texto-confirmacion" id="textoConfirmarEliminar"></p>
            <ul class="lista-confirmacion" id="listaConfirmarEliminar"></ul>
            <p class="texto-ayuda-confirmacion" id="ayudaConfirmarEliminar"></p>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarEliminar">Cancelar</button>
            <button type="button" class="btn-peligro" id="btnConfirmarEliminar">Enviar a la papelera</button>
        </div>
    </div>
</div>

<!-- MODAL: Papelera (solo quien tiene ese permiso) -->
<div id="modalPapelera" class="modal-overlay">
    <div class="modal-contenido modal-ancha">
        <div class="modal-encabezado">
            <h3>Papelera</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalPapelera"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <p class="texto-ayuda-confirmacion" id="ayudaPapelera"></p>
            <div class="lista-papelera" id="listaPapelera"></div>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCerrarPapelera">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL: Vista previa (PDF e imagenes; Word y Excel se descargan) -->
<div id="modalPreviewDocumento" class="modal-overlay">
    <div class="modal-contenido modal-ancha">
        <div class="modal-encabezado">
            <h3 id="tituloPreviewDocumento">Vista previa</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalPreview"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <div class="contenedor-preview" id="contenedorPreview"></div>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCerrarPreview">Cerrar</button>
            <button type="button" class="btn-primario" id="btnDescargarDesdePreview">Descargar</button>
        </div>
    </div>
</div>

<script src="../SCRIPTS/script_modulo_documentos.js"></script>
