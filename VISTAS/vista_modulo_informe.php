<?php
require_once '../CONFIG/verificar_sesion.php';
$id_usuario = intval($_SESSION['usuario']['id_usuario'] ?? 0);
$id_rol     = intval($_SESSION['usuario']['id_rol'] ?? 0);
$puede_registrar = ($id_rol === 2);
?>
<link rel="stylesheet" href="../ESTILOS/style_modulo_informe.css">
<script>
    window.INF_ID_USUARIO      = <?php echo $id_usuario; ?>;
    window.INF_ID_ROL          = <?php echo $id_rol; ?>;
    window.INF_PUEDE_REGISTRAR = <?php echo $puede_registrar ? 'true' : 'false'; ?>;
</script>

<!-- MODAL PRINCIPAL DEL INFORME -->
<div id="modalInforme" class="inf-overlay" style="display:none;">
    <div class="inf-modal">
        <div class="inf-header">
            <div class="inf-header-info">
                <h3 id="inf-titulo">Informe de Atención</h3>
                <div class="inf-meta-cita" id="inf-meta-cita">
                    <!-- Fecha, servicio, doctor, tiempo -->
                </div>
            </div>
            <button class="inf-btn-cerrar" id="infBtnCerrar">&#10005;</button>
        </div>
        <div class="inf-body" id="inf-body">
            <!-- Contenido dinámico: vista o formulario -->
        </div>
        <div class="inf-footer" id="inf-footer">
            <!-- Botones dinámicos -->
        </div>
    </div>
</div>

<!-- MODAL AGREGAR SERVICIO -->
<div id="inf-modalServicio" class="inf-overlay inf-overlay-sm" style="display:none;">
    <div class="inf-modal-sm">
        <div class="inf-header">
            <h3>Agregar Servicio</h3>
            <button class="inf-btn-cerrar" onclick="document.getElementById('inf-modalServicio').style.display='none'">&#10005;</button>
        </div>
        <div class="inf-body">
            <div class="inf-grupo">
                <label>Servicio <span class="inf-req">*</span></label>
                <select id="inf-selServicio" class="inf-select">
                    <option value="">Seleccionar...</option>
                </select>
            </div>
            <div class="inf-grupo">
                <label>Cantidad</label>
                <input type="number" id="inf-srvCantidad" value="1" min="1" class="inf-input">
            </div>
            <div class="inf-grupo">
                <label>Precio referencial (S/)</label>
                <input type="number" id="inf-srvPrecio" step="0.01" min="0"
                       placeholder="0.00" class="inf-input inf-readonly" readonly>
                <small class="inf-nota">El precio final lo define la asistente al cobrar</small>
            </div>
        </div>
        <div class="inf-footer">
            <button class="inf-btn-sec" onclick="document.getElementById('inf-modalServicio').style.display='none'">Cancelar</button>
            <button class="inf-btn-pri" id="inf-btnConfirmarServicio">Agregar</button>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR APARATO -->
<div id="inf-modalAparato" class="inf-overlay inf-overlay-sm" style="display:none;">
    <div class="inf-modal-sm">
        <div class="inf-header">
            <h3>Agregar Aparatología</h3>
            <button class="inf-btn-cerrar" onclick="document.getElementById('inf-modalAparato').style.display='none'">&#10005;</button>
        </div>
        <div class="inf-body">
            <div class="inf-grupo">
                <label>Aparato <span class="inf-req">*</span></label>
                <select id="inf-selAparato" class="inf-select">
                    <option value="">Seleccionar...</option>
                </select>
            </div>
            <div class="inf-grupo">
                <label>Cantidad <span class="inf-req">*</span></label>
                <input type="number" id="inf-apCantidad" value="1" min="1" step="1" class="inf-input" style="width:80px;">
            </div>
            <div class="inf-grupo">
                <label>Precio referencial (S/)</label>
                <input type="number" id="inf-apPrecioRef" readonly class="inf-input inf-readonly">
                <small class="inf-nota">Del catálogo — solo referencial</small>
            </div>
            <div class="inf-grupo">
                <label>Precio acordado (S/) <span class="inf-req">*</span></label>
                <input type="number" id="inf-apPrecioAcordado" step="0.01" min="0"
                       placeholder="0.00" class="inf-input">
            </div>
            <div class="inf-grupo">
                <label>¿Cómo se cobra?</label>
                <div class="inf-opciones">
                    <label class="inf-radio-label">
                        <input type="radio" name="inf-apCobro" value="incluida" checked>
                        Incluida en cuota inicial
                    </label>
                    <label class="inf-radio-label">
                        <input type="radio" name="inf-apCobro" value="separada">
                        Cobro separado
                    </label>
                </div>
            </div>
        </div>
        <div class="inf-footer">
            <button class="inf-btn-sec" onclick="document.getElementById('inf-modalAparato').style.display='none'">Cancelar</button>
            <button class="inf-btn-pri" id="inf-btnConfirmarAparato">Agregar</button>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR ENVÍO -->
<div id="inf-modalEnvio" class="inf-overlay inf-overlay-sm" style="display:none;">
    <div class="inf-modal-sm">
        <div class="inf-header">
            <h3>Enviar a cobrar</h3>
            <button class="inf-btn-cerrar" onclick="document.getElementById('inf-modalEnvio').style.display='none'">&#10005;</button>
        </div>
        <div class="inf-body">
            <p style="font-size:14px;color:#2c3e50;margin-bottom:12px;">
                ¿Confirmas que el informe es correcto y deseas enviarlo a cobrar?
            </p>
            <div class="inf-aviso">
                Una vez enviado, solo podrás editarlo si no tiene pagos registrados.
            </div>
        </div>
        <div class="inf-footer">
            <button class="inf-btn-sec" onclick="document.getElementById('inf-modalEnvio').style.display='none'">Cancelar</button>
            <button class="inf-btn-pri" id="inf-btnConfirmarEnvio">Sí, enviar</button>
        </div>
    </div>
</div>

<script src="../SCRIPTS/script_modulo_informe.js"></script>