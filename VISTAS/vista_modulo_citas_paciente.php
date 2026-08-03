<?php
// vista_modulo_citas_paciente.php - CON SECCIÓN DE PENDIENTES
require_once '../CONFIG/verificar_sesion.php';

$id_paciente = $_GET['id_paciente'] ?? 0;
$id_doctor = $_SESSION['usuario']['id_usuario'] ?? 0;
$id_rol = $_SESSION['usuario']['id_rol'] ?? 0;

if ($id_rol != 2) {
    echo '<div style="padding: 40px; text-align: center; color: #e74c3c;">
            <h3>❌ Acceso Denegado</h3>
            <p>Solo los doctores pueden acceder a este módulo.</p>
          </div>';
    exit;
}

if ($id_paciente == 0 || $id_doctor == 0) {
    echo '<div style="padding: 40px; text-align: center; color: #f39c12;">
            <h3>⚠️ Información Incompleta</h3>
            <p>No se pudo obtener la información necesaria.</p>
          </div>';
    exit;
}
?>

<link rel="stylesheet" href="../ESTILOS/style_modulo_citas_doctor.css">

<div class="contenedor-citas-doctor">
    
    <!-- SISTEMA DE PESTAÑAS -->
    <div class="pestanas-citas">
        <button class="pestana activa" data-pestana="activas">
            <span>Citas Activas</span>
            <span class="pestana-contador" id="contadorActivas">0</span>
        </button>
        <button class="pestana" data-pestana="pendientes">
            
            <span>Pendientes de Informe</span>
            <span class="pestana-contador pendiente" id="contadorPendientes">0</span>
        </button>
    </div>

    <!-- CONTENIDO DE PESTAÑAS -->
    <div class="contenido-pestanas">
        
        <!-- PANEL: CITAS ACTIVAS -->
        <div class="panel-pestana activo" id="panelActivas">
            <div class="cabecera-seccion">
                <div class="info-seccion">
                    <h3>Citas Activas</h3>
                    <p class="descripcion-seccion">Citas programadas, confirmadas y en atención</p>
                </div>
                <div class="info-contador">
                    <span id="totalCitasActivas">0</span> citas
                </div>
            </div>

            <div id="listaCitasActivas" class="lista-citas">
                <!-- Se llenará dinámicamente -->
            </div>

            <div id="mensajeVacioActivas" class="mensaje-vacio" style="display:none;">
                <div class="icono-vacio">📅</div>
                <p>No hay citas activas</p>
                <small>Las citas programadas, confirmadas y en atención aparecerán aquí</small>
            </div>
        </div>

        <!-- PANEL: PENDIENTES DE INFORME -->
        <div class="panel-pestana" id="panelPendientes">
            <div class="cabecera-seccion">
                <div class="info-seccion">
                    <h3>Pendientes de Informe</h3>
                    <p class="descripcion-seccion">Citas completadas que requieren registro de atención</p>
                </div>
                <div class="info-contador pendiente">
                    <span id="totalPendientes">0</span> pendientes
                </div>
            </div>

            <div id="listaPendientes" class="lista-citas">
                <!-- Se llenará dinámicamente -->
            </div>

            <div id="mensajeVacioPendientes" class="mensaje-vacio" style="display:none;">
                <div class="icono-vacio">✅</div>
                <p>No hay informes pendientes</p>
                <small>Las citas completadas sin informe aparecerán aquí</small>
            </div>
        </div>

    </div>
</div>

<!-- MODAL: FORMULARIO DE ATENCIÓN -->
<div id="modalFormularioAtencion" class="modal-overlay">
    <div class="modal-contenido modal-atencion">
        <div class="modal-encabezado">
            <h3 id="tituloModalAtencion"> Registrar Atención</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalAtencion">✖</button>
        </div>
        
        <form id="formAtencion" class="modal-cuerpo">
            <input type="hidden" id="idCitaAtencion" name="id_cita">
            <input type="hidden" id="idPacienteAtencion" name="id_paciente">
            <input type="hidden" id="idAtencionEditar" name="id_atencion">
            
            <!-- Información de la cita -->
            <div class="info-cita-atencion">
                <div class="dato-atencion">
                    <span class="label-atencion">📅 Fecha:</span>
                    <span id="fechaCitaAtencion" class="valor-atencion">--/--/----</span>
                </div>
                <div class="dato-atencion">
                    <span class="label-atencion">⏱️ Tiempo de Atención:</span>
                    <span id="tiempoAtencion" class="valor-atencion">-- minutos</span>
                </div>
                <div class="dato-atencion">
                    <span class="label-atencion">🦷 Servicio:</span>
                    <span id="servicioAtencion" class="valor-atencion">--</span>
                </div>
                <div class="dato-atencion">
                    <span class="label-atencion">👨‍⚕️ Doctor:</span>
                    <span id="doctorAtencion" class="valor-atencion">--</span>
                </div>
            </div>

            <!-- Formulario -->
            <div class="grupo-campo">
                <label for="tratamientoAtencion">Tratamiento Realizado *</label>
                <textarea id="tratamientoAtencion" 
                          name="tratamiento" 
                          rows="4" 
                          placeholder="Describa el tratamiento realizado..."
                          required></textarea>
            </div>

            <div class="fila-campos">
                <div class="grupo-campo">
                    <label for="precioUnitario">Precio C/U *</label>
                    <input type="number" 
                           id="precioUnitario" 
                           name="precio_unitario" 
                           step="0.01" 
                           min="0"
                           placeholder="0.00"
                           required>
                </div>

                <div class="grupo-campo">
                    <label for="totalAtencion">Total</label>
                    <input type="number" 
                           id="totalAtencion" 
                           name="total" 
                           step="0.01" 
                           min="0"
                           placeholder="0.00"
                           readonly>
                </div>
            </div>

            <div class="fila-campos">
                <div class="grupo-campo">
                    <label for="aCuenta"> A Cuenta</label>
                    <input type="number" 
                           id="aCuenta" 
                           name="a_cuenta" 
                           step="0.01" 
                           min="0"
                           placeholder="0.00">
                </div>

                <div class="grupo-campo">
                    <label for="resta">Resta</label>
                    <input type="number" 
                           id="resta" 
                           name="resta" 
                           step="0.01" 
                           min="0"
                           placeholder="0.00"
                           readonly>
                </div>
            </div>

            <div class="modal-botones">
                <button type="button" class="btn-secundario" id="btnCancelarAtencion">
                    Cancelar
                </button>
                <button type="submit" class="btn-guardar">
                    Guardar Atención
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.ID_PACIENTE = <?php echo intval($id_paciente); ?>;
    window.ID_DOCTOR = <?php echo intval($id_doctor); ?>;
</script>
<?php include '../VISTAS/vista_modulo_informe.php'; ?>
<script src="../SCRIPTS/script_modulo_citas_doctor.js"></script>