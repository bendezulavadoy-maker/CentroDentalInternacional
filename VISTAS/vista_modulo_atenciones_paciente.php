<?php
require_once '../CONFIG/verificar_sesion.php';

$id_paciente     = intval($_GET['id_paciente'] ?? 0);
$id_usuario      = intval($_SESSION['usuario']['id_usuario'] ?? 0);
$id_rol          = intval($_SESSION['usuario']['id_rol'] ?? 0);
$puede_registrar = ($id_rol === 2);
$puede_pagar     = ($id_rol === 1 || $id_rol === 3);
?>
<link rel="stylesheet" href="../ESTILOS/style_modulo_atenciones.css">
<script>
    window.AT_ID_PACIENTE     = <?php echo $id_paciente; ?>;
    window.AT_ID_USUARIO      = <?php echo $id_usuario; ?>;
    window.AT_ID_ROL          = <?php echo $id_rol; ?>;
    window.AT_PUEDE_REGISTRAR = <?php echo $puede_registrar ? 'true' : 'false'; ?>;
    window.AT_PUEDE_PAGAR     = <?php echo $puede_pagar ? 'true' : 'false'; ?>;
</script>

<div class="contenedor-atenciones">

    <!-- PLANES ACTIVOS -->
    <div class="seccion-planes" id="seccionPlanes" style="display:none;">
        <div class="seccion-header">
            <h4 class="seccion-titulo">Planes de Tratamiento Activos</h4>
        </div>
        <div id="listaPlanes"></div>
    </div>

    <!-- FILTROS -->
    <div class="barra-filtros-at">
        <div class="filtros-grupo">
            <button class="btn-filtro-at activo" data-filtro="todas">Todas</button>
            <button class="btn-filtro-at" data-filtro="sin_informe">Sin informe</button>
            <button class="btn-filtro-at" data-filtro="por_servicio">Por servicio</button>
            <button class="btn-filtro-at" data-filtro="por_plan">Por plan</button>
            <button class="btn-filtro-at" data-filtro="pendiente_cobro">Por cobrar</button>
        </div>
        <div class="filtros-secundarios" id="filtrosSecundarios" style="display:none;">
            <select id="selectFiltroServicio" style="display:none;"></select>
            <select id="selectFiltroPlan" style="display:none;"></select>
        </div>
        <span class="total-resultados"><span id="totalCitas">0</span> citas</span>
    </div>

    <!-- LISTA DE CITAS -->
    <div id="listaCitas" class="lista-citas-at">
        <p class="cargando-at">Cargando...</p>
    </div>

</div>

<!-- MODAL REGISTRAR PAGO -->
<div id="modalPago" class="at-modal-overlay" style="display:none;">
    <div class="at-modal at-modal-sm">
        <div class="at-modal-header">
            <h3>Registrar Pago</h3>
            <button class="at-btn-cerrar" id="btnCerrarPago">&#10005;</button>
        </div>
        <div class="at-modal-body">
            <input type="hidden" id="pago_id_cita">
            <div id="resumenCobro" class="at-resumen-cobro"></div>
            <div class="at-grupo">
                <label>Monto a pagar (S/) <span class="at-requerido">*</span></label>
                <input type="number" id="pagoMonto" step="0.01" min="0"
                       placeholder="0.00" class="at-input">
            </div>
            <div class="at-grupo">
                <label>Observación</label>
                <input type="text" id="pagoObservacion" placeholder="Opcional..." class="at-input">
            </div>
        </div>
        <div class="at-modal-footer">
            <button type="button" class="at-btn-secundario" id="btnCancelarPago">Cancelar</button>
            <button type="button" class="at-btn-principal" id="btnConfirmarPago">Registrar pago</button>
        </div>
    </div>
</div>

<?php include '../VISTAS/vista_modulo_informe.php'; ?>
<script src="../SCRIPTS/script_modulo_atenciones.js"></script>