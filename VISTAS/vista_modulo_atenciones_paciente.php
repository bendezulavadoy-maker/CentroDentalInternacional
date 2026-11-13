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
        <div class="seccion-header" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <h4 class="seccion-titulo">Planes de Tratamiento</h4>
            <label class="pl-check"><input type="checkbox" id="chkPlanesFinalizados"><span>Mostrar planes finalizados</span></label>
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
                <label>Método de pago <span class="at-requerido">*</span></label>
                <select id="pagoMetodo" class="at-input"><option value="">Cargando...</option></select>
            </div>
            <div class="at-grupo">
                <label>N.º de operación o comprobante <span id="pagoComprobanteReq" class="at-requerido" style="display:none;">*</span></label>
                <input type="text" id="pagoComprobante" maxlength="40" placeholder="Opcional" class="at-input">
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

<!-- MODAL DETALLE DE LA CITA / SESION (solo lectura) -->
<div id="modalDetalleCita" class="at-modal-overlay" style="display:none;">
    <div class="at-modal sd-modal">
        <div class="at-modal-header">
            <div><h3 id="sdTitulo">Cita</h3><div class="at-modal-subtitulo" id="sdSubtitulo"></div></div>
            <button class="at-btn-cerrar" id="sdCerrar">&#10005;</button>
        </div>
        <div class="at-modal-body" id="sdCuerpo"></div>
        <div class="at-modal-footer">
            <button type="button" class="at-btn-secundario" id="sdCerrar2">Cerrar</button>
            <button type="button" class="at-btn-principal" id="sdAbrirInforme" style="display:none;">Abrir informe</button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PLAN -->
<div id="modalEditarPlan" class="at-modal-overlay" style="display:none;">
    <div class="at-modal">
        <div class="at-modal-header">
            <div><h3>Editar plan</h3><div class="at-modal-subtitulo" id="epSubtitulo"></div></div>
            <button class="at-btn-cerrar" id="epCerrar">&#10005;</button>
        </div>
        <div class="at-modal-body">
            <input type="hidden" id="epIdPlan">
            <div class="pl-fila">
                <div class="at-grupo"><label for="epEstado">Estado</label>
                    <select id="epEstado" class="at-input"><option value="activo">Activo</option><option value="completado">Completado</option><option value="cancelado">Cancelado</option></select></div>
                <div class="at-grupo"><label for="epFechaFin">Fecha estimada de fin</label><input type="date" id="epFechaFin" class="at-input"></div>
            </div>
            <div class="at-grupo"><label for="epNotas">Notas del plan</label><textarea id="epNotas" rows="2" maxlength="1000" class="at-input"></textarea></div>

            <div id="epBloqueDinero" class="pl-dinero-edit">
                <h4 class="pl-subtitulo">Costos y cuotas</h4>
                <p id="epAvisoDinero" class="pl-ayuda" style="display:none;"></p>
                <div class="pl-fila">
                    <div class="at-grupo"><label for="epTipo">Tipo de cobro</label>
                        <select id="epTipo" class="at-input"><option value="por_sesion">Por sesión</option><option value="costo_total">Costo fijo</option></select></div>
                    <div class="at-grupo" id="epGrCostoTotal"><label for="epCostoTotal">Costo acordado (S/)</label><input type="number" id="epCostoTotal" min="0" step="0.01" class="at-input"></div>
                    <div class="at-grupo" id="epGrCostoSesion"><label for="epCostoSesion">Costo por sesión (S/)</label><input type="number" id="epCostoSesion" min="0" step="0.01" class="at-input"></div>
                </div>
                <div class="pl-fila">
                    <div class="at-grupo" id="epGrCuotas"><label class="pl-check"><input type="checkbox" id="epEnCuotas"><span>Se paga en cuotas (una por sesión)</span></label>
                        <input type="number" id="epNumCuotas" min="1" max="120" step="1" placeholder="N.º de cuotas" class="at-input"></div>
                    <div class="at-grupo"><label for="epCuotaIni">Cuota inicial (S/)</label><input type="number" id="epCuotaIni" min="0" step="0.01" class="at-input"></div>
                </div>
                <p id="epPrevCuota" class="pl-ayuda"></p>
                <label class="pl-check"><input type="checkbox" id="epRecalcular" checked><span>Corregir también el costo de las sesiones que aún <strong>no tienen pagos</strong> (las que ya tienen pagos nunca se tocan)</span></label>
            </div>

            <div class="at-grupo" style="margin-top:12px;"><label for="epMotivo">Motivo del cambio <span class="at-requerido" id="epMotivoReq" style="display:none;">*</span></label>
                <input type="text" id="epMotivo" maxlength="200" class="at-input" placeholder="Obligatorio si cambia el estado o los costos"></div>
            <p id="epMensaje" class="pl-mensaje" style="display:none;"></p>
        </div>
        <div class="at-modal-footer">
            <button type="button" class="at-btn-secundario" id="epCancelar">Cancelar</button>
            <button type="button" class="at-btn-principal" id="epGuardar">Guardar cambios</button>
        </div>
    </div>
</div>

<!-- MODAL CORREGIR COSTOS DEL PLAN -->
<div id="modalCorregir" class="at-modal-overlay" style="display:none;">
    <div class="at-modal">
        <div class="at-modal-header">
            <div><h3>Corregir costos de las sesiones</h3><div class="at-modal-subtitulo" id="ccSubtitulo"></div></div>
            <button class="at-btn-cerrar" id="ccCerrar">&#10005;</button>
        </div>
        <div class="at-modal-body">
            <p class="pl-ayuda">Compara lo que dice cada sesión con lo que corresponde según el plan acordado. Solo se corrigen las sesiones <strong>sin pagos</strong>.</p>
            <div id="ccTabla"></div>
            <p id="ccResumen" class="pl-ayuda"></p>
            <p id="ccMensaje" class="pl-mensaje" style="display:none;"></p>
        </div>
        <div class="at-modal-footer">
            <button type="button" class="at-btn-secundario" id="ccCancelar">Cancelar</button>
            <button type="button" class="at-btn-principal" id="ccAplicar">Corregir</button>
        </div>
    </div>
</div>

<?php include '../VISTAS/vista_modulo_informe.php'; ?>
<script src="../SCRIPTS/script_modulo_atenciones.js"></script>