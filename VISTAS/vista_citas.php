<?php
require_once '../CONFIG/verificar_sesion.php';
require_once '../CONFIG/permisos.php';
// La ventana de "cita pasada" solo existe para quien tiene el permiso (el servidor lo vuelve a exigir)
$puedeCitaPasada = Permisos::puede('citas.registrar_retroactiva');
?>
<link rel="stylesheet" href="../ESTILOS/style_citas.css">
<?php if ($puedeCitaPasada): ?><link rel="stylesheet" href="../ESTILOS/style_citas_retroactivas.css"><?php endif; ?>

<div class="contenedor-citas">
    <div class="encabezado-citas">
        <h2>Gestión de Citas Odontológicas</h2>
        <div style="display:flex; gap:10px; align-items:center;">
            <?php if ($puedeCitaPasada): ?>
            <button id="btnCitaPasada" type="button" class="btn-secundario">Registrar cita pasada</button>
            <?php endif; ?>
            <button id="btnNuevaCita" class="btn-principal">Nueva Cita</button>
        </div>
    </div>

    <!-- Listado -->
    <section id="seccionListadoCitas">
        <div class="barra-busqueda-filtros">
            <div class="grupo-busqueda"> 
                <input type="text" id="inputBusqueda"
                       placeholder="Buscar por paciente, doctor o documento..."
                       class="input-busqueda">
            </div>
            <div class="grupo-filtros">
                <select id="filtroFecha" class="filtro-select">
                    <option value="">Todas las fechas</option>
                    <option value="hoy">Hoy</option>
                    <option value="semana">Esta semana</option>
                    <option value="mes">Este mes</option>
                </select>
            </div>
        </div>

        <div class="estadisticas-rapidas">
            <div class="stat-card stat-todos filtro-activo" data-estado="">
                <div class="stat-icono"></div>
                <div class="stat-info"><h3 id="statTodos">0</h3><p>Todas</p></div>
            </div>
            <div class="stat-card stat-programada" data-estado="programada">
                <div class="stat-icono"></div>
                <div class="stat-info"><h3 id="statProgramadas">0</h3><p>Programadas</p></div>
            </div>
            <div class="stat-card stat-confirmada" data-estado="confirmada">
                <div class="stat-icono"></div>
                <div class="stat-info"><h3 id="statConfirmadas">0</h3><p>Confirmadas</p></div>
            </div>
            <div class="stat-card stat-completada" data-estado="completada">
                <div class="stat-icono"></div>
                <div class="stat-info"><h3 id="statCompletadas">0</h3><p>Completadas</p></div>
            </div>
            <div class="stat-card stat-cancelada" data-estado="cancelada">
                <div class="stat-icono"></div>
                <div class="stat-info"><h3 id="statCanceladas">0</h3><p>Canceladas</p></div>
            </div>
            <div class="stat-card stat-no-asistio" data-estado="no asistió">
                <div class="stat-icono"></div>
                <div class="stat-info"><h3 id="statNoAsistio">0</h3><p>No Asistió</p></div>
            </div>
        </div>

        <div class="tabla-responsive">
            <table id="tablaCitas">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paciente</th>
                        <th>Doctor</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Estado</th>
                        <th>Atención</th>
                        <th>Sede</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <!-- Detalle de cita -->
    <section id="seccionDetalleCita" style="display:none;">
        <button id="btnVolverListado" class="btn-secundario">← Volver</button>
        <div id="detalleCita"></div>
    </section>

    <!-- Formulario registro/edición -->
    <section id="seccionNuevaCita" style="display:none;">
        <button id="btnVolverListado2" class="btn-secundario">← Volver</button>
        <h3 id="tituloCita">Registrar Nueva Cita</h3>

        <form id="formCita">
            <input type="hidden" id="idCitaEditar" name="id_cita">

            <div class="contenedor-formulario-citas">

                <!-- ══ COLUMNA IZQUIERDA ══ -->
                <div class="columna-formulario">

                    <h4 class="subtitulo-seccion">Paciente y Doctor</h4>

                    <div class="grupo-campo">
                        <label>Paciente <span class="campo-obligatorio">*</span></label>
                        <div class="input-con-autocompletar">
                            <input type="text" id="inputPaciente"
                                   placeholder="Buscar por nombre o documento..."
                                   autocomplete="off">
                            <input type="hidden" id="idPacienteSeleccionado" name="id_paciente">
                            <div id="sugerenciasPaciente" class="sugerencias-autocompletar"></div>
                        </div>
                        <small class="ayuda-campo">Escriba al menos 2 caracteres para buscar</small>
                    </div>

                    <div class="grupo-campo">
                        <label>Doctor / Dentista <span class="campo-obligatorio">*</span></label>
                        <div class="input-con-autocompletar">
                            <input type="text" id="inputDoctor"
                                   placeholder="Buscar dentista..."
                                   autocomplete="off">
                            <input type="hidden" id="idDoctorSeleccionado" name="id_doctor">
                            <div id="sugerenciasDoctor" class="sugerencias-autocompletar"></div>
                        </div>
                        <small class="ayuda-campo">Escriba al menos 2 caracteres para buscar</small>
                    </div>

                    <h4 class="subtitulo-seccion">Sede y Fecha</h4>

                    <div class="grupo-campo">
                        <label>Sede de Atención <span class="campo-obligatorio">*</span></label>
                        <select name="id_sede_atencion" id="selectSede" required>
                            <option value="">Cargando...</option>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label>Fecha <span class="campo-obligatorio">*</span></label>
                        <input type="date" name="fecha" id="fechaCita" required>
                    </div>

                    <input type="hidden" name="hora" id="horaCita">

                    <div class="grupo-campo">
                        <label>Estado de Cita <span class="campo-obligatorio">*</span></label>
                        <select name="id_estado_cita" id="selectEstadoCita" required>
                            <option value="">Cargando...</option>
                        </select>
                    </div>

                </div>

                <!-- ══ COLUMNA DERECHA ══ -->
                <div class="columna-formulario">

                    <h4 class="subtitulo-seccion">Tipo de Atención y Horario</h4>

                    <div class="grupo-campo">
                        <label>Tipo de Atención <span class="campo-obligatorio">*</span></label>
                        <select name="id_tipo_atencion" id="selectTipoAtencion">
                            <option value="">Selecciona el tipo</option>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label>Duración estimada</label>
                        <select name="duracion_minutos" id="selectDuracion">
                            <option value="30">30 minutos</option>
                            <option value="45">45 minutos</option>
                            <option value="60" selected>60 minutos</option>
                            <option value="90">90 minutos</option>
                            <option value="120">120 minutos</option>
                        </select>
                    </div>

                    <div class="grupo-campo" id="campoHoraSlots">
                        <label>Hora disponible <span class="campo-obligatorio">*</span></label>
                        <div id="slotsContenedor">
                            <small style="color:#95a5a6;font-style:italic;">Selecciona doctor, sede y fecha para ver horarios disponibles.</small>
                        </div>
                    </div>

                    <h4 class="subtitulo-seccion">Motivo y Plan</h4>

                    <div class="grupo-campo">
                        <label>Motivo de la Cita <span class="campo-obligatorio">*</span></label>
                        <textarea name="motivo"
                                  id="textareaMotivo"
                                  rows="3"
                                  placeholder="Describa el motivo de la cita..."
                                  required></textarea>
                        <small class="ayuda-campo">Describa brevemente el motivo de la cita</small>
                    </div>

                    <div class="grupo-campo">
                        <label>Plan activo relacionado <small style="color:#95a5a6;">(opcional)</small></label>
                        <select id="selectPlanCita" name="id_paciente_plan">
                            <option value="">Sin plan asociado</option>
                        </select>
                    </div>

                    <h4 class="subtitulo-seccion">Información Médica — Alergias</h4>

                    <div id="mensajeAlergiasExistentes" style="display:none;" class="mensaje-alergias-existentes">
                        <div class="icono-alerta"><i class="ti ti-alert-triangle"></i></div>
                        <div class="contenido-alerta">
                            <strong>El paciente ya tiene estos medicamentos alérgicos registrados:</strong>
                            <div id="listaAlergiasExistentes" class="lista-alergias-existentes"></div>
                        </div>
                    </div>

                    <div id="contenedorPreguntaInicial" style="display:none;" class="grupo-campo">
                        <label id="textoPreguntaInicial">¿El paciente tiene alergias a medicamentos?</label>
                        <div class="grupo-radio">
                            <label class="radio-label">
                                <input type="radio" name="tiene_alergia" value="no" checked>
                                <span>No</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="tiene_alergia" value="si">
                                <span id="textoOpcionSi">Sí</span>
                            </label>
                        </div>
                    </div>

                    <div class="grupo-campo" id="contenedorChipsMedicamentos" style="display:none;">
                        <label>Seleccionar medicamentos alérgicos</label>
                        <small class="ayuda-campo">Haga clic en los medicamentos para seleccionar o deseleccionar</small>
                        <div id="chipsMedicamentos" class="chips-medicamentos"></div>
                        <input type="hidden" name="alergias_medicamentos_hidden" id="alergiasMedicamentosHidden">
                    </div>

                    <button type="submit" class="btn-guardar" id="btnGuardarCita">Guardar Cita</button>

                </div>
            </div>
        </form>
    </section>
</div>

<?php if ($puedeCitaPasada): ?>
<!-- MODAL: REGISTRAR CITA PASADA (historial) -->
<div id="modalCitaPasada" class="cp-overlay" style="display:none;">
    <div class="cp-contenido" role="dialog" aria-modal="true" aria-labelledby="cpTitulo">
        <div class="cp-encabezado">
            <h3 id="cpTitulo">Registrar cita pasada</h3>
            <button type="button" id="cpCerrar" class="cp-cerrar" aria-label="Cerrar">&times;</button>
        </div>
        <div class="cp-cuerpo">
            <p class="cp-ayuda">Para cargar atenciones de <strong>fechas anteriores a hoy</strong> (historial). No envía recordatorios, no crea eventos en Google Calendar ni valida horarios. Quedan marcadas como «registradas como pasadas».</p>

            <div class="cp-grupo">
                <label for="cpPaciente">Paciente <span class="cp-req">*</span></label>
                <input type="text" id="cpPaciente" autocomplete="off" placeholder="Escriba nombre o documento (mín. 2 letras)">
                <div id="cpResultadosPaciente" class="cp-lista" style="display:none;"></div>
                <div id="cpPacienteElegido" class="cp-elegido" style="display:none;"></div>
            </div>

            <div class="cp-fila">
                <div class="cp-grupo"><label for="cpFecha">Fecha <span class="cp-req">*</span></label><input type="date" id="cpFecha"></div>
                <div class="cp-grupo"><label for="cpHora">Hora <span class="cp-req">*</span></label><input type="time" id="cpHora"></div>
                <div class="cp-grupo"><label for="cpDuracion">Duración (min)</label><input type="number" id="cpDuracion" min="5" max="480" value="30"></div>
            </div>
            <div class="cp-fila">
                <div class="cp-grupo"><label for="cpDoctor">Odontólogo <span class="cp-req">*</span></label><select id="cpDoctor"></select></div>
                <div class="cp-grupo"><label for="cpSede">Sede <span class="cp-req">*</span></label><select id="cpSede"></select></div>
            </div>
            <div class="cp-fila">
                <div class="cp-grupo"><label for="cpTipoAtencion">Tipo de atención</label><select id="cpTipoAtencion"></select></div>
                <div class="cp-grupo"><label for="cpEstado">Estado <span class="cp-req">*</span></label><select id="cpEstado"></select></div>
            </div>
            <div class="cp-grupo"><label for="cpMotivo">Motivo <span class="cp-req">*</span></label><input type="text" id="cpMotivo" maxlength="1000" placeholder="Ej: Dolor de muela"></div>

            <div id="cpBloqueAtencion">
                <h4 class="cp-subtitulo">Servicios cobrados</h4>
                <table class="cp-tabla" id="cpTablaServicios"><thead><tr><th>Servicio</th><th>Cant.</th><th>Precio (S/)</th><th>Subtotal</th><th></th></tr></thead><tbody></tbody></table>
                <div class="cp-acciones-tabla">
                    <button type="button" id="cpAgregarServicio" class="cp-btn cp-btn-sec cp-btn-chico">+ Agregar servicio</button>
                    <span class="cp-total">Subtotal servicios: <strong id="cpTotalServicios">S/ 0.00</strong></span>
                </div>

                <h4 class="cp-subtitulo">Plan de tratamiento <span class="cp-opc">(ortodoncia, endodoncia, etc.)</span></h4>
                <div class="cp-radios" id="cpModoPlan">
                    <label><input type="radio" name="cpModoPlan" value="" checked> Sin plan</label>
                    <label><input type="radio" name="cpModoPlan" value="existente"> Sesión de un plan existente</label>
                    <label><input type="radio" name="cpModoPlan" value="nuevo"> Plan nuevo (esta es su primera sesión)</label>
                </div>

                <div id="cpPlanExistente" style="display:none;">
                    <div class="cp-fila">
                        <div class="cp-grupo"><label for="cpPlanSel">Plan del paciente <span class="cp-req">*</span></label><select id="cpPlanSel"></select></div>
                        <div class="cp-grupo"><label for="cpPlanNumero">N.º de sesión</label><input type="number" id="cpPlanNumero" min="1" max="999" step="1" placeholder="Automático"></div>
                        <div class="cp-grupo"><label for="cpPlanCostoEx">Costo de la sesión (S/)</label><input type="number" id="cpPlanCostoEx" min="0" step="0.01" placeholder="Según el plan"></div>
                    </div>
                    <p id="cpPlanInfo" class="cp-ayuda"></p>
                </div>

                <div id="cpPlanNuevo" style="display:none;">
                    <div class="cp-fila">
                        <div class="cp-grupo"><label for="cpNpPlan">Plan <span class="cp-req">*</span></label><select id="cpNpPlan"></select></div>
                        <div class="cp-grupo"><label for="cpNpTipo">Tipo de cobro <span class="cp-req">*</span></label>
                            <select id="cpNpTipo"><option value="por_sesion">Por sesión</option><option value="costo_total">Costo total</option></select></div>
                        <div class="cp-grupo" id="cpNpGrCostoSesion"><label for="cpNpCostoSesion">Costo por sesión (S/)</label><input type="number" id="cpNpCostoSesion" min="0" step="0.01"></div>
                        <div class="cp-grupo" id="cpNpGrCostoTotal" style="display:none;"><label for="cpNpCostoTotal">Costo acordado (S/)</label><input type="number" id="cpNpCostoTotal" min="0" step="0.01"></div>
                    </div>
                    <div class="cp-fila">
                        <div class="cp-grupo"><label for="cpNpCuotaIni">Cuota inicial (S/)</label><input type="number" id="cpNpCuotaIni" min="0" step="0.01" value="0"></div>
                        <div class="cp-grupo" id="cpNpGrCuotas" style="display:none;"><label class="cp-check" style="margin:0 0 4px;"><input type="checkbox" id="cpNpEnCuotas"> Se paga en cuotas</label>
                            <input type="number" id="cpNpNumCuotas" min="1" max="120" step="1" placeholder="N.º de cuotas" style="display:none;"></div>
                        <div class="cp-grupo"><label for="cpNpCostoSesionReal">Costo real de la sesión 1 (S/)</label><input type="number" id="cpNpCostoSesionReal" min="0" step="0.01" placeholder="Según el plan"></div>
                    </div>
                    <div class="cp-grupo"><label for="cpNpNotas">Notas del plan</label><input type="text" id="cpNpNotas" maxlength="1000"></div>
                    <p id="cpNpInfo" class="cp-ayuda"></p>
                </div>

                <div id="cpBloqueAparatologia" style="display:none;">
                    <h4 class="cp-subtitulo">Aparatología de esta cita</h4>
                    <table class="cp-tabla" id="cpTablaAparatologia"><thead><tr><th>Pieza</th><th>Cant.</th><th>Precio acordado (S/)</th><th class="cp-col-incl">Incluida en el costo</th><th></th></tr></thead><tbody></tbody></table>
                    <div class="cp-acciones-tabla"><button type="button" id="cpAgregarAparatologia" class="cp-btn cp-btn-sec cp-btn-chico">+ Agregar aparatología</button></div>
                </div>

                <label class="cp-check"><input type="checkbox" id="cpConInforme" checked> Registrar también la atención (diagnóstico y tratamiento)</label>
                <div id="cpCamposInforme">
                    <div class="cp-grupo"><label for="cpDiagnostico">Diagnóstico</label><textarea id="cpDiagnostico" rows="2" maxlength="2000"></textarea></div>
                    <div class="cp-grupo"><label for="cpTratamiento">Tratamiento realizado</label><textarea id="cpTratamiento" rows="2" maxlength="2000"></textarea></div>
                    <div class="cp-grupo"><label for="cpObservaciones">Observaciones</label><textarea id="cpObservaciones" rows="2" maxlength="2000"></textarea></div>
                    <p class="cp-ayuda">Con la atención registrada, la cita figura en el historial y, si queda saldo, en Cobros.</p>
                </div>

                <div id="cpResumenCita" class="cp-resumen">
                    <h4 class="cp-subtitulo" style="margin-top:0;">Resumen de la cita</h4>
                    <div id="cpResumenLineas"></div>
                    <div class="cp-resumen-total">Total de la cita: <strong id="cpTotal">S/ 0.00</strong></div>
                </div>

                <div id="cpBloquePagos">
                    <h4 class="cp-subtitulo">Pagos recibidos <span class="cp-opc">(con la fecha de la cita)</span></h4>
                    <table class="cp-tabla" id="cpTablaPagos"><thead><tr><th>Concepto</th><th>Monto (S/)</th><th>Método</th><th>N.º de operación</th><th></th></tr></thead><tbody></tbody></table>
                    <div class="cp-acciones-tabla">
                        <button type="button" id="cpAgregarPago" class="cp-btn cp-btn-sec cp-btn-chico">+ Agregar pago</button>
                        <span class="cp-total">Pagado: <strong id="cpPagado">S/ 0.00</strong> · Pendiente: <strong id="cpPendiente">S/ 0.00</strong></span>
                    </div>
                </div>
            </div>

            <p id="cpMensaje" class="cp-mensaje" role="alert" style="display:none;"></p>
            <div class="cp-acciones">
                <button type="button" id="cpCancelar" class="cp-btn cp-btn-sec">Cancelar</button>
                <button type="button" id="cpGuardar" class="cp-btn cp-btn-prim">Registrar cita pasada</button>
            </div>

            <hr class="cp-separador">
            <h4 class="cp-subtitulo">Últimas citas pasadas registradas</h4>
            <div id="cpConfirmarEliminar" class="cp-confirmacion" style="display:none;"></div>
            <div id="cpRecientes"><p class="cp-ayuda">Cargando...</p></div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="../SCRIPTS/script_citas.js"></script>