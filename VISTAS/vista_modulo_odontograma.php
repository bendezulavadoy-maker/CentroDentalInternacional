<?php
require_once '../CONFIG/verificar_sesion.php';

$id_paciente = $_GET['id_paciente'] ?? 0;
$id_usuario  = $_SESSION['usuario']['id_usuario'] ?? 0;
$id_rol      = $_SESSION['usuario']['id_rol'] ?? 0;

// Solo odontólogos pueden editar
$puede_editar = ($id_rol == 2);

if ($id_paciente == 0) {
    echo '<div style="padding:40px;text-align:center;color:#e74c3c;">Paciente no especificado.</div>';
    exit;
}
?>
<link rel="stylesheet" href="../ESTILOS/style_odontograma.css">

<div class="contenedor-odontograma">

    <!-- ENCABEZADO -->
    <div class="odonto-header">
        <div>
            <h3 class="odonto-titulo">Odontograma</h3>
            <p class="odonto-subtitulo">Sistema FDI — Norma Técnica MINSA</p>
        </div>
        <?php if ($puede_editar): ?>
            <button id="btnNuevaVersion" class="btn-nueva-version">+ Nueva versión</button>
            <?php endif; ?>
    </div>

    <!-- LISTA DE VERSIONES -->
    <div id="listaVersiones" class="lista-versiones">
        <p class="cargando-odonto">Cargando versiones...</p>
    </div>

    <!-- VISOR / EDITOR DEL ODONTOGRAMA -->
    <div id="panelOdontograma" class="panel-odontograma" style="display:none;">

        <!-- Info de la versión activa -->
        <div class="version-info-bar">
            <div class="version-info-datos">
                <span id="versionLabel" class="version-label"></span>
                <span id="versionDoctor" class="version-doctor"></span>
                <span id="versionFecha" class="version-fecha"></span>
                <span id="versionEstado" class="version-estado-badge"></span>
            </div>
            <div class="version-acciones">
                <button id="btnCerrarVersion" class="btn-cerrar-version" style="display:none;">
                    Cerrar y guardar definitivamente
                </button>
                <button id="btnGuardarBorrador" class="btn-guardar-borrador" style="display:none;">
                    Guardar borrador
                </button>
            </div>
        </div>

        <!-- HERRAMIENTAS (solo en modo edición) -->
        <div id="barraHerramientas" class="barra-herramientas" style="display:none;">
            <div class="herramientas-grupo">
                <span class="herramientas-label">Hallazgo activo:</span>
                <div id="selectorHallazgo" class="selector-hallazgo">
                    <span id="hallazgoActivo" class="hallazgo-chip">Seleccionar</span>
                </div>
            </div>
            <div id="menuHallazgos" class="menu-hallazgos" style="display:none;"></div>
            <div class="herramientas-grupo">
                <span class="herramientas-label">Color:</span>
                <div class="selector-color">
                    <button class="btn-color azul activo" data-color="azul" title="Azul — buen estado">A</button>
                    <button class="btn-color rojo" data-color="rojo" title="Rojo — mal estado">R</button>
                </div>
            </div>
            <button id="btnBorrar" class="btn-borrar-hallazgo">Borrar hallazgo</button>
        </div>

        <!-- SVG DEL ODONTOGRAMA -->
        <div class="odonto-svg-contenedor">
            <!-- Cambia el viewBox -->
            <svg id="svgOdontograma"
                viewBox="0 0 900 420"
                xmlns="http://www.w3.org/2000/svg"
                class="odonto-svg">

                <!-- Línea central vertical -->
                <line x1="450" y1="0" x2="450" y2="420"
                    stroke="#ccc" stroke-width="1" stroke-dasharray="4,3" />

                <!-- Etiqueta superior — sube más arriba -->
                <text x="225" y="12" text-anchor="middle"
                    class="svg-label-arco">Superior derecho</text>
                <text x="675" y="12" text-anchor="middle"
                    class="svg-label-arco">Superior izquierdo</text>

                <g id="grupoSuperior"></g>

                <!-- Separador horizontal -->
                <line x1="30" y1="210" x2="870" y2="210"
                    stroke="#ddd" stroke-width="1" />

                <!-- Etiqueta inferior -->
                <text x="225" y="230" text-anchor="middle"
                    class="svg-label-arco">Inferior derecho</text>
                <text x="675" y="230" text-anchor="middle"
                    class="svg-label-arco">Inferior izquierdo</text>

                <g id="grupoInferior"></g>
                <g id="grupoTemporales" style="display:none;"></g>
            </svg>
        </div>

        <!-- Leyenda de colores -->
        <div class="odonto-leyenda">
            <span class="leyenda-item azul">Azul — tratamiento correcto / buen estado</span>
            <span class="leyenda-item rojo">Rojo — patología / mal estado / temporal</span>
        </div>

        <!-- ESPECIFICACIONES -->
        <div class="odonto-especificaciones">
            <label class="espec-label">Especificaciones</label>
            <textarea id="txtEspecificaciones"
                class="espec-textarea"
                placeholder="Registre aquí los hallazgos que no pueden representarse gráficamente..."
                rows="3"></textarea>
        </div>

        <!-- DIENTES TEMPORALES toggle -->
        <div id="toggleTemporales" class="toggle-temporales" style="display:none;">
            <label class="toggle-label">
                <input type="checkbox" id="chkTemporales">
                Mostrar dientes temporales (dentición mixta)
            </label>
        </div>

    </div>

    <!-- Modal confirmación cerrar versión -->
    <div id="modalCerrarVersion" class="odonto-modal-overlay" style="display:none;">
        <div class="odonto-modal">
            <h4>Cerrar versión definitivamente</h4>
            <p>Una vez cerrada, esta versión <strong>no podrá modificarse</strong>. Esta acción es irreversible según la Norma Técnica del MINSA.</p>
            <p style="margin-top:10px;">¿Confirmas que los datos registrados son correctos?</p>
            <div class="odonto-modal-botones">
                <button id="btnCancelarCierre" class="btn-cancelar-cierre">Cancelar</button>
                <button id="btnConfirmarCierre" class="btn-confirmar-cierre">Sí, cerrar definitivamente</button>
            </div>
        </div>
    </div>
    <!-- Modal confirmar nueva versión -->
<div id="modalNuevaVersion" class="odonto-modal-overlay" style="display:none;">
    <div class="odonto-modal">
        <h4>Crear nueva versión</h4>
        <p>Se creará una nueva versión del odontograma para este paciente.</p>
        <p style="margin-top:10px;">¿Confirmas?</p>
        <div class="odonto-modal-botones">
            <button id="btnCancelarNuevaVersion" class="btn-cancelar-cierre">Cancelar</button>
            <button id="btnConfirmarNuevaVersion" class="btn-confirmar-nueva">Crear versión</button>
        </div>
    </div>
</div>
<!-- Modal confirmar eliminar versión -->
<div id="modalEliminarVersion" class="odonto-modal-overlay" style="display:none;">
    <div class="odonto-modal">
        <h4>Eliminar versión</h4>
        <p>Se eliminarán esta versión y todos sus hallazgos registrados.</p>
        <p style="margin-top:10px;">¿Confirmas que deseas eliminarla?</p>
        <div class="odonto-modal-botones">
            <button id="btnCancelarEliminarVersion" class="btn-cancelar-cierre">Cancelar</button>
            <button id="btnConfirmarEliminarVersion" class="btn-confirmar-cierre">Sí, eliminar</button>
        </div>
    </div>
</div>

    <!-- Menú contextual (clic derecho sobre cara del diente) -->
    <div id="menuContextual" class="menu-contextual" style="display:none;">
        <div class="menu-ctx-header">
            <span id="menuCtxDiente" class="menu-ctx-diente"></span>
            <span id="menuCtxCara" class="menu-ctx-cara"></span>
        </div>
        <div id="menuCtxOpciones" class="menu-ctx-opciones"></div>
        <div class="menu-ctx-separador"></div>
        <button id="menuCtxBorrar" class="menu-ctx-borrar">Borrar hallazgo</button>
    </div>

</div>

<script>
    window.ID_PACIENTE_ODONTO = <?php echo intval($id_paciente); ?>;
    window.ID_USUARIO_ODONTO = <?php echo intval($id_usuario); ?>;
    window.PUEDE_EDITAR_ODONTO = <?php echo $puede_editar ? 'true' : 'false'; ?>;
</script>
<script src="../SCRIPTS/script_odontograma.js"></script>