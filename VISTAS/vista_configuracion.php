<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<link rel="stylesheet" href="../ESTILOS/style_configuracion.css">
<div class="fondo-config">
<div class="contenedor-config">

    <div class="encabezado-config">
        <h2>Configuración del Sistema</h2>
    </div>

    <!-- PESTAÑAS -->
    <div class="tabs-config">
        <button class="tab-btn activo" data-tab="roles">Roles</button>
        <button class="tab-btn" data-tab="permisos">Permisos por Rol</button>
        <button class="tab-btn" data-tab="sedes">Sedes</button>
        <button class="tab-btn" data-tab="servicios">Servicios</button>
        <button class="tab-btn" data-tab="planes">Planes</button>
        <button class="tab-btn" data-tab="aparatologia">Aparatología</button>
        <button class="tab-btn" data-tab="tipos_atencion">Tipos de Atención</button>
        <button class="tab-btn" data-tab="horarios">Horarios</button>
        <button class="tab-btn" data-tab="google_calendar">Google Calendar</button>
    </div>

    <!-- TAB: ROLES -->
    <section id="tab-roles" class="tab-contenido activo">
        <div class="seccion-header">
            <h3>Gestión de Roles</h3>
            <button id="btnNuevoRol" class="btn-principal">+ Nuevo Rol</button>
        </div>
        <table id="tablaRoles">
            <thead>
                <tr><th>ID</th><th>Nombre del Rol</th><th>Acciones</th></tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="formRolContenedor" style="display:none;" class="form-inline">
            <h4 id="tituloFormRol">Nuevo Rol</h4>
            <div class="grupo-campo">
                <label>Nombre del Rol: <span class="campo-obligatorio">*</span></label>
                <input type="text" id="inputNombreRol" maxlength="50" placeholder="Ej: Radiologista">
            </div>
            <div class="form-botones">
                <button id="btnCancelarRol" class="btn-secundario">Cancelar</button>
                <button id="btnGuardarRol" class="btn-guardar">Guardar</button>
            </div>
        </div>
    </section>

    <!-- TAB: PERMISOS -->
    <section id="tab-permisos" class="tab-contenido">
        <div class="seccion-header">
            <h3>Permisos por Rol</h3>
            <p class="subtitulo">Marca los módulos a los que cada rol puede acceder.</p>
        </div>
        <div id="tablaPermisos"><p class="cargando">Cargando...</p></div>
        <div class="form-botones" style="margin-top:20px;">
            <button id="btnGuardarPermisos" class="btn-guardar">Guardar Permisos</button>
        </div>
    </section>

    <!-- TAB: SEDES -->
    <section id="tab-sedes" class="tab-contenido">
        <div class="seccion-header">
            <h3>Gestión de Sedes</h3>
            <button id="btnNuevaSede" class="btn-principal">+ Nueva Sede</button>
        </div>
        <table id="tablaSedes">
            <thead>
                <tr>
                    <th>ID</th><th>Nombre</th><th>Dirección</th>
                    <th>Teléfono</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="formSedeContenedor" style="display:none;" class="form-inline">
            <h4 id="tituloFormSede">Nueva Sede</h4>
            <div class="grupo-campo">
                <label>Nombre: <span class="campo-obligatorio">*</span></label>
                <input type="text" id="inputNombreSede" maxlength="100" placeholder="Ej: Sede Norte">
            </div>
            <div class="grupo-campo">
                <label>Dirección: <span class="campo-obligatorio">*</span></label>
                <input type="text" id="inputDireccionSede" maxlength="100">
            </div>
            <div class="grupo-campo">
                <label>Teléfono:</label>
                <input type="text" id="inputTelefonoSede" maxlength="9" placeholder="9 dígitos">
            </div>
            <div class="grupo-campo">
                <label>Estado:</label>
                <select id="selectActivoSede">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
            <div class="form-botones">
                <button id="btnCancelarSede" class="btn-secundario">Cancelar</button>
                <button id="btnGuardarSede" class="btn-guardar">Guardar</button>
            </div>
        </div>
    </section>

    <!-- TAB: SERVICIOS -->
    <section id="tab-servicios" class="tab-contenido">
        <div class="seccion-header">
            <h3>Servicios y Precios</h3>
            <button id="btnNuevoServicio" class="btn-principal">+ Nuevo Servicio</button>
        </div>
        <p class="subtitulo">Define los servicios que ofrece el centro y sus precios base.</p>
        <table id="tablaServicios">
            <thead>
                <tr>
                    <th>Nombre</th><th>Precio base (S/)</th>
                    <th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="formServicioContenedor" style="display:none;" class="form-inline">
            <h4 id="tituloFormServicio">Nuevo Servicio</h4>
            <div class="grupo-campo">
                <label>Nombre: <span class="campo-obligatorio">*</span></label>
                <input type="text" id="inputNombreServicio" maxlength="100"
                       placeholder="Ej: Limpieza dental">
            </div>
            <div class="grupo-campo">
                <label>Precio base (S/):</label>
                <input type="number" id="inputPrecioServicio" step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="grupo-campo">
                <label>Estado:</label>
                <select id="selectActivoServicio">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
            <div class="form-botones">
                <button id="btnCancelarServicio" class="btn-secundario">Cancelar</button>
                <button id="btnGuardarServicio" class="btn-guardar">Guardar</button>
            </div>
        </div>
    </section>

    <!-- TAB: PLANES -->
    <section id="tab-planes" class="tab-contenido">
        <div class="seccion-header">
            <h3>Planes de Tratamiento</h3>
            <button id="btnNuevoPlan" class="btn-principal">+ Nuevo Plan</button>
        </div>
        <p class="subtitulo">Define los planes disponibles con sus pasos sugeridos.</p>
        <div id="listaPlanesConfig" class="lista-planes-config">
            <p class="cargando">Cargando...</p>
        </div>
    </section>

    <!-- TAB: APARATOLOGÍA -->
    <section id="tab-aparatologia" class="tab-contenido">
        <div class="seccion-header">
            <h3>Aparatología</h3>
            <button id="btnNuevaAparatologia" class="btn-principal">+ Nueva Aparatología</button>
        </div>
        <p class="subtitulo">Define los aparatos y materiales usados en tratamientos con sus precios base.</p>
        <table id="tablaAparatologia">
            <thead>
                <tr>
                    <th>Nombre</th><th>Precio base (S/)</th>
                    <th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="formAparatologiaContenedor" style="display:none;" class="form-inline">
            <h4 id="tituloFormAparatologia">Nueva Aparatología</h4>
            <div class="grupo-campo">
                <label>Nombre: <span class="campo-obligatorio">*</span></label>
                <input type="text" id="inputNombreAparatologia" maxlength="100"
                       placeholder="Ej: Brackets metálicos">
            </div>
            <div class="grupo-campo">
                <label>Precio base (S/):</label>
                <input type="number" id="inputPrecioAparatologia" step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="grupo-campo">
                <label>Estado:</label>
                <select id="selectActivoAparatologia">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
            <div class="form-botones">
                <button id="btnCancelarAparatologia" class="btn-secundario">Cancelar</button>
                <button id="btnGuardarAparatologia" class="btn-guardar">Guardar</button>
            </div>
        </div>
    </section>

    <!-- TAB: TIPOS DE ATENCIÓN -->
    <section id="tab-tipos_atencion" class="tab-contenido">
        <div class="seccion-header">
            <h3>Tipos de Atención</h3>
            <button id="btnNuevoTipoAtencion" class="btn-principal">+ Nuevo tipo</button>
        </div>
        <table id="tablaTiposAtencion">
            <thead>
                <tr><th>Nombre</th><th>Duración</th><th>Color</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="formTipoAtencionContenedor" style="display:none;" class="form-inline">
            <h4 id="tituloFormTipoAtencion">Nuevo Tipo de Atención</h4>
            <input type="hidden" id="idTipoAtencionEditar">
            <div class="grupo-campo">
                <label>Nombre <span class="campo-obligatorio">*</span></label>
                <input type="text" id="inputNombreTipoAtencion" maxlength="80" placeholder="Ej: Control ortodoncia">
            </div>
            <div class="grupo-campo">
                <label>Duración estimada (minutos) <span class="campo-obligatorio">*</span></label>
                <select id="selectDuracionTipoAtencion">
                    <option value="30">30 min</option>
                    <option value="45">45 min</option>
                    <option value="60" selected>60 min</option>
                    <option value="90">90 min</option>
                    <option value="120">120 min</option>
                </select>
            </div>
            <div class="grupo-campo">
                <label>Color en calendario</label>
                <input type="color" id="inputColorTipoAtencion" value="#2a4d8f">
            </div>
            <div class="grupo-campo" id="grupoActivoTipoAtencion" style="display:none;">
                <label>
                    <input type="checkbox" id="checkActivoTipoAtencion" checked> Activo
                </label>
            </div>
            <div class="form-botones">
                <button id="btnCancelarTipoAtencion" class="btn-secundario">Cancelar</button>
                <button id="btnGuardarTipoAtencion" class="btn-guardar">Guardar</button>
            </div>
        </div>
    </section>

    <!-- TAB: HORARIOS -->
    <section id="tab-horarios" class="tab-contenido">
        <div class="seccion-header">
            <h3>Horarios de Doctores</h3>
        </div>

        <!-- Selector de doctor y semana -->
        <div class="horarios-controles">
            <div class="grupo-campo-inline">
                <label>Doctor</label>
                <div style="position:relative;">
                    <input type="text" id="buscarDoctorHorario" class="input-buscar-doctor"
                           placeholder="Buscar por nombre o DNI..." autocomplete="off">
                    <div id="dropdownDoctores" style="display:none;position:absolute;top:100%;left:0;width:100%;background:white;border:0.5px solid #dde1e5;border-radius:5px;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:999;max-height:200px;overflow-y:auto;"></div>
                </div>
                <input type="hidden" id="selectDoctorHorario">
            </div>
            <div class="grupo-campo-inline">
                <label>Semana del</label>
                <input type="date" id="fechaInicioSemana" class="input-buscar-doctor">
            </div>
            <div class="horarios-controles-btns">
                <button id="btnCargarHorario" class="btn-secundario">Ver semana</button>
                <button id="btnCopiarSemana" class="btn-secundario" style="display:none;">Copiar semana anterior</button>
                <button id="btnReplicarRango" class="btn-secundario" style="display:none;">Replicar en rango</button>
                <button id="btnNuevoHorario" class="btn-principal" style="display:none;">+ Agregar horario</button>
                <button id="btnNuevoBloqueo" class="btn-danger-outline" style="display:none;">+ Bloqueo</button>
            </div>
        </div>

        <!-- Grilla de semana -->
        <div id="horariosGrilla" style="display:none;">
            <div id="calendarioSemana"></div>
        </div>
        <p id="horariosVacio" class="cargando" style="display:none;">Selecciona un doctor para ver su horario.</p>

        <!-- Form agregar/editar horario -->
        <div id="formHorarioContenedor" style="display:none;" class="form-inline">
            <h4 id="tituloFormHorario">Nuevo horario</h4>
            <input type="hidden" id="idHorarioEditar">
            <input type="hidden" id="idDoctorHorario">
            <div class="grupo-campo">
                <label>Sede <span class="campo-obligatorio">*</span></label>
                <select id="selectSedeHorario"></select>
            </div>
            <div class="grupo-campo">
                <label>Fecha <span class="campo-obligatorio">*</span></label>
                <input type="date" id="fechaHorario">
            </div>
            <div class="horario-horas">
                <div class="grupo-campo">
                    <label>Hora inicio <span class="campo-obligatorio">*</span></label>
                    <input type="time" id="horaInicioHorario" step="1800">
                </div>
                <div class="grupo-campo">
                    <label>Hora fin <span class="campo-obligatorio">*</span></label>
                    <input type="time" id="horaFinHorario" step="1800">
                </div>
            </div>
            <div class="form-botones">
                <button id="btnCancelarHorario" class="btn-secundario">Cancelar</button>
                <button id="btnGuardarHorario" class="btn-guardar">Guardar</button>
            </div>
        </div>

        <!-- Form bloqueo -->
        <div id="formBloqueoContenedor" style="display:none;" class="form-inline">
            <h4>Nuevo bloqueo</h4>
            <input type="hidden" id="idDoctorBloqueo">
            <div class="horario-horas">
                <div class="grupo-campo">
                    <label>Fecha inicio <span class="campo-obligatorio">*</span></label>
                    <input type="date" id="fechaBloqueoInicio">
                </div>
                <div class="grupo-campo">
                    <label>Fecha fin <span class="campo-obligatorio">*</span></label>
                    <input type="date" id="fechaBloqueoFin">
                </div>
            </div>
            <div class="grupo-campo">
                <label>
                    <input type="checkbox" id="checkTodoDia" checked> Todo el día
                </label>
            </div>
            <div id="horasBloqueoContenedor" style="display:none;" class="horario-horas">
                <div class="grupo-campo">
                    <label>Hora inicio</label>
                    <input type="time" id="horaInicioBloqueo" step="1800">
                </div>
                <div class="grupo-campo">
                    <label>Hora fin</label>
                    <input type="time" id="horaFinBloqueo" step="1800">
                </div>
            </div>
            <div class="grupo-campo">
                <label>Motivo</label>
                <input type="text" id="motivoBloqueo" placeholder="Ej: Vacaciones, Feriado...">
            </div>
            <div class="form-botones">
                <button id="btnCancelarBloqueo" class="btn-secundario">Cancelar</button>
                <button id="btnGuardarBloqueo" class="btn-guardar">Guardar bloqueo</button>
            </div>
        </div>
    </section>
    <section id="tab-google_calendar" class="tab-contenido">
    <?php include 'vista_google_calendar.php'; ?>
</section>

</div>

    <!-- MODAL PLAN -->
    <!-- MODAL PLAN -->
<div id="modalPlanConfig" class="cfg-modal-overlay" style="display:none;">
    <div class="cfg-modal">
        <div class="cfg-modal-header">
            <h3 id="tituloModalPlan">Nuevo Plan de Tratamiento</h3>
            <button class="cfg-btn-cerrar" id="btnCerrarModalPlan">&#10005;</button>
        </div>
        <div class="cfg-modal-body">
            <div class="grupo-campo">
                <label>Nombre del plan: <span class="campo-obligatorio">*</span></label>
                <input type="text" id="planNombre" maxlength="100"
                       placeholder="Ej: Ortodoncia completa">
            </div>
            <div class="grupo-campo">
                <label>Descripción:</label>
                <textarea id="planDescripcion" rows="2"
                          placeholder="Descripción general del plan..."></textarea>
            </div>
            <div class="grupo-campo">
                <label>Costo referencial (S/):</label>
                <input type="number" id="planCostoRef" step="0.01" min="0"
                       placeholder="Opcional">
                <small>Solo orientativo — el odontólogo define el costo real por paciente</small>
            </div>
            <div class="grupo-campo">
                <label>Estado:</label>
                <select id="planActivo">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
        </div>
        <div class="cfg-modal-footer">
            <button type="button" class="btn-secundario"
                    id="btnCancelarModalPlan">Cancelar</button>
            <button type="button" class="btn-guardar"
                    id="btnGuardarPlan">Guardar plan</button>
        </div>
    </div>
    </div>


</div>
    <!-- Modal: Replicar horario en rango -->
    <div id="modalReplicarRango" class="cfg-modal-overlay" style="display:none;">
        <div class="cfg-modal" style="max-width:420px;">
            <div class="cfg-modal-header">
                <h3>Replicar horario en rango</h3>
                <button class="cfg-modal-cerrar" id="btnCerrarModalRango">&#10005;</button>
            </div>
            <div class="cfg-modal-body">
                <p style="font-size:13px;color:#5d6d7e;margin-bottom:14px;">
                    Se replicará el horario de la semana actual en todas las semanas del rango seleccionado.
                </p>
                <div class="grupo-campo">
                    <label>Desde <span class="campo-obligatorio">*</span></label>
                    <input type="date" id="rangoReplicarDesde">
                </div>
                <div class="grupo-campo">
                    <label>Hasta <span class="campo-obligatorio">*</span></label>
                    <input type="date" id="rangoReplicarHasta">
                </div>
            </div>
            <div class="cfg-modal-footer">
                <button class="btn-secundario" id="btnCancelarRango">Cancelar</button>
                <button class="btn-guardar" id="btnConfirmarRango">Replicar</button>
            </div>
        </div>
    </div>