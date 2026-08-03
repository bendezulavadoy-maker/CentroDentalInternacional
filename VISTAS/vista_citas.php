<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<link rel="stylesheet" href="../ESTILOS/style_citas.css">

<div class="contenedor-citas">
    <div class="encabezado-citas">
        <h2>Gestión de Citas Odontológicas</h2>
        <button id="btnNuevaCita" class="btn-principal">Nueva Cita</button>
    </div>

    <!-- Listado -->
    <section id="seccionListadoCitas">
        <div class="barra-busqueda-filtros">
            <div class="grupo-busqueda">
                <input type="text" id="inputBusqueda"
                       placeholder="Buscar por paciente, doctor o DNI..."
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
                                   placeholder="Buscar por nombre o DNI..."
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
                        <div class="icono-alerta">⚠️</div>
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

                    <button type="submit" class="btn-guardar" id="btnGuardarCita">💾 Guardar Cita</button>

                </div>
            </div>
        </form>
    </section>
</div>

<script src="../SCRIPTS/script_citas.js"></script>