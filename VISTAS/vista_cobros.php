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

            <div class="cobros-grupo" id="grupoUsarSaldo" style="display:none;">
                <label class="cobros-check">
                    <input type="checkbox" id="pagoUsarSaldo">
                    Pagar con el saldo a favor del paciente <strong>(disponible: <span id="pagoSaldoDisp"></span>)</strong>
                </label>
            </div>
            <div id="bloqueDatosPago">
                <div class="cobros-grupo">
                    <label>Método de pago <span class="cobros-req">*</span></label>
                    <select id="pagoMetodo" class="cobros-input"><option value="">Cargando...</option></select>
                </div>
                <div class="cobros-grupo">
                    <label>N.º de operación o comprobante <span class="cobros-req" id="pagoComprobanteReq" style="display:none;">*</span></label>
                    <input type="text" id="pagoComprobante" class="cobros-input" maxlength="40" placeholder="Opcional">
                </div>
                <div class="cobros-grupo" id="grupoFechaPago" style="display:none;">
                    <label>Fecha del pago</label>
                    <input type="date" id="pagoFecha" class="cobros-input">
                    <small class="cobros-ayuda">Una fecha anterior a hoy queda registrada como pago con fecha pasada.</small>
                </div>
                <div class="cobros-grupo" id="grupoAdelanto">
                    <label>Adelanto (saldo a favor) <span class="cobros-opc">(opcional)</span></label>
                    <input type="number" id="pagoAdelanto" class="cobros-input" step="0.01" min="0" placeholder="0.00">
                    <small class="cobros-ayuda">Dinero recibido de más que queda a favor del paciente para cobros futuros.</small>
                </div>
            </div>
            <div class="cobros-grupo">
                <label>Observación general <span class="cobros-opc">(opcional)</span></label>
                <input type="text" id="pagoObservacionGlobal" class="cobros-input" maxlength="255"
                       placeholder="Ej: Pagó la mamá del paciente...">
            </div>
        </div>
        <div class="cobros-modal-footer">
            <button class="cobros-btn-sec" id="btnCancelarPago">Cancelar</button>
            <button class="cobros-btn-pri" id="btnConfirmarPago">Registrar pagos</button>
        </div>
    </div>
</div>

<!-- Modal anular pago -->
<div id="modalAnularPago" class="cobros-overlay" style="display:none;">
    <div class="cobros-modal">
        <div class="cobros-modal-header">
            <h3>Anular pago</h3>
            <button class="cobros-btn-cerrar" id="btnCerrarModalAnular">&#10005;</button>
        </div>
        <div class="cobros-modal-body">
            <p id="anularResumen" class="cobros-aviso"></p>
            <div class="cobros-grupo">
                <label>Motivo de la anulación <span class="cobros-req">*</span></label>
                <input type="text" id="anularMotivo" class="cobros-input" maxlength="255" placeholder="Ej: Se cobró por error">
            </div>
            <p class="cobros-ayuda">El pago no se borra: queda registrado como anulado y se genera un movimiento inverso. Esta acción queda en la bitácora.</p>
            <p id="anularError" class="cobros-error" style="display:none;"></p>
        </div>
        <div class="cobros-modal-footer">
            <button class="cobros-btn-sec" id="btnCancelarAnular">Cancelar</button>
            <button class="cobros-btn-peligro" id="btnConfirmarAnular">Anular pago</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../ESTILOS/style_cobros.css">
<script src="../SCRIPTS/script_cobros.js"></script>