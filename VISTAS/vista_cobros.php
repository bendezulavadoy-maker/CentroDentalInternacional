<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<div class="cobros-contenedor">

    <!-- PANEL IZQUIERDO: Buzón -->
    <div class="cobros-buzon" id="cobrosBuzon">
        <div class="cobros-buzon-header">
            <h2 class="cobros-titulo">Cobros pendientes</h2>
            <span class="cobros-contador" id="cobroContador">—</span>
        </div>
        <div class="cobros-buzon-lista" id="cobrosBuzonLista">
            <p class="cobros-cargando">Cargando...</p>
        </div>
    </div>

    <!-- PANEL DERECHO: Detalle del paciente -->
    <div class="cobros-detalle" id="cobrosDetalle">
        <div class="cobros-detalle-vacio" id="cobrosDetalleVacio">
            <p>Selecciona un paciente del listado para ver el detalle de cobro.</p>
        </div>
        <div id="cobrosDetalleContenido" style="display:none;"></div>
    </div>

</div>

<!-- Modal confirmación de pagos -->
<div id="modalConfirmarPago" class="cobros-overlay" style="display:none;">
    <div class="cobros-modal">
        <div class="cobros-modal-header">
            <h3>Confirmar registro de pagos</h3>
            <button class="cobros-btn-cerrar" id="btnCerrarModalPago">&#10005;</button>
        </div>
        <div class="cobros-modal-body">
            <div id="resumenPagosModal"></div>
            <div class="cobros-grupo">
                <label>Observación general <span style="color:#95a5a6;font-size:11px;">(opcional)</span></label>
                <input type="text" id="pagoObservacionGlobal" class="cobros-input"
                       placeholder="Ej: Pago en efectivo...">
            </div>
        </div>
        <div class="cobros-modal-footer">
            <button class="cobros-btn-sec" id="btnCancelarPago">Cancelar</button>
            <button class="cobros-btn-pri" id="btnConfirmarPago">Registrar pagos</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../ESTILOS/style_cobros.css">
<script src="../SCRIPTS/script_cobros.js"></script>