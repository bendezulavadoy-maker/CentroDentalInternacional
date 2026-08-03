<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<link rel="stylesheet" href="../ESTILOS/style_pacientes.css">

<div class="contenedor-personal">

    <!-- ENCABEZADO -->
    <div class="encabezado-personal">
        <h2>Gestión de Pacientes</h2>
        <button id="btnNuevoPersonal" class="btn-principal">
            <i class="ti ti-user-plus"></i> Nuevo Paciente
        </button>
    </div>

    <!-- LISTADO -->
    <section id="seccionListadoPersonal">
        <div class="barra-busqueda">
            <input type="text"
                   id="inputBusqueda"
                   placeholder="Buscar por nombre, DNI, teléfono o correo..."
                   class="input-busqueda">
        </div>
        <table id="tablaPacientes">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>DNI</th>
                    <th>Teléfono</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>

    <!-- DETALLE -->
    <section id="seccionDetallePacientes" style="display:none;">
        <button id="btnVolverListado" class="btn-secundario">
            <i class="ti ti-arrow-left"></i> Volver
        </button>
        <div id="detallePacientes"></div>
    </section>

    <!-- FORMULARIO REGISTRO / EDICIÓN -->
    <section id="seccionNuevoPaciente" style="display:none;">
        <button id="btnVolverListado2" class="btn-secundario">
            <i class="ti ti-arrow-left"></i> Volver
        </button>

        <div class="tarjeta-formulario">
            <div class="tarjeta-formulario-header">
                <i class="ti ti-user-plus"></i>
                <span id="tituloFormPaciente">Registrar nuevo paciente</span>
            </div>

            <form id="formPaciente" enctype="multipart/form-data">
                <div class="tarjeta-formulario-body">

                    <!-- FOTO -->
                    <div class="columna-izquierda">
                        <div class="grupo-foto">
                            <div id="previewFoto" class="preview-foto">
                                <i class="ti ti-camera"></i>
                            </div>
                        </div>
                        <input type="file" name="foto" id="fotoInput"
                               accept="image/*" style="display:none;">
                        <label for="fotoInput" id="labelFoto" class="btn-file">
                            <i class="ti ti-upload" style="font-size:13px;"></i>
                            Subir foto
                        </label>
                        <span class="ayuda-foto">Opcional · JPG o PNG</span>
                    </div>

                    <!-- CAMPOS EN GRID -->
                    <div class="columna-derecha">

                    <div class="grupo-campo">
                        <label>Nombre <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="nombre" required placeholder="Nombres completos">
                    </div>

                    <div class="grupo-campo">
                        <label>Apellidos <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="apellidos" required placeholder="Apellidos completos">
                    </div>

                    <div class="grupo-campo">
                        <label>DNI <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="dni" maxlength="8" required placeholder="12345678">
                        <small class="ayuda-campo">8 dígitos sin puntos</small>
                    </div>

                    <div class="grupo-campo">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
                        <div id="bloqueEdad"></div>
                    </div>

                    <div class="grupo-campo">
                        <label>Sexo <span class="campo-obligatorio">*</span></label>
                        <select name="sexo" id="selectSex" required>
                            <option value="">Selecciona...</option>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label>Estado Civil <span class="campo-obligatorio">*</span></label>
                        <select name="estado_civil" id="selectEstado_civil" required>
                            <option value="">Selecciona...</option>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label>Grado de Instrucción <span class="campo-obligatorio">*</span></label>
                        <select name="grado_instruccion" id="selectGrado_instruccion" required>
                            <option value="">Selecciona...</option>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label>Ocupación <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="ocupacion" required placeholder="Ej: Docente, Estudiante...">
                    </div>

                    <div class="grupo-campo">
                        <label>Teléfono <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="telefono" maxlength="9" required placeholder="987654321">
                        <small class="ayuda-campo">9 dígitos</small>
                    </div>

                    <div class="grupo-campo">
                        <label>Correo <span class="campo-obligatorio">*</span></label>
                        <input type="email" name="correo" required placeholder="correo@ejemplo.com">
                    </div>

                    <div class="grupo-campo campo-ancho">
                        <label>Dirección <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="direccion" required placeholder="Av. Principal 123, Urb...">
                    </div>

                    <div class="grupo-campo campo-ancho" id="grupoApoderado">
                        <label>Apoderado
                            <span style="font-weight:400;color:#9aa3b0;font-size:11px;">(se activa si es menor de edad)</span>
                        </label>
                        <div id="contenedor-apoderado-info"></div>
                    </div>

                    <div class="grupo-campo campo-ancho">
                        <label>Observaciones <span style="font-weight:400;color:#9aa3b0;font-size:11px;">(opcional)</span></label>
                        <textarea name="observaciones" rows="3"
                                  placeholder="Notas adicionales sobre el paciente..."></textarea>
                    </div>

                    </div>
                </div>

                <div class="tarjeta-formulario-footer">
                    <button type="button" class="btn-secundario" style="margin-bottom:0;" onclick="document.getElementById('btnVolverListado2').click()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-guardar">
                        <i class="ti ti-device-floppy"></i> Guardar paciente
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- MODAL APODERADO -->
    <div id="modalApoderado" class="modal-overlay">
        <div class="modal-contenido">
            <div class="modal-encabezado">
                <h3>Datos del Apoderado</h3>
                <button type="button" class="btn-cerrar-modal" id="btnCerrarModal">✕</button>
            </div>
            <form id="formApoderado">
                <div class="grupo-campo">
                    <label>Nombre <span class="campo-obligatorio">*</span></label>
                    <input type="text" name="nombre_apoderado" id="nombreApoderado"
                           required placeholder="Nombres">
                </div>
                <div class="grupo-campo">
                    <label>Apellido <span class="campo-obligatorio">*</span></label>
                    <input type="text" name="apellido_apoderado" id="apellidoApoderado"
                           required placeholder="Apellidos">
                </div>
                <div class="grupo-campo">
                    <label>DNI <span class="campo-obligatorio">*</span></label>
                    <input type="text" name="dni_apoderado" id="dniApoderado"
                           maxlength="8" required placeholder="12345678">
                </div>
                <div class="grupo-campo">
                    <label>Parentesco <span class="campo-obligatorio">*</span></label>
                    <select name="tipo_familiar" id="tipoFamiliar" required>
                        <option value="">Selecciona...</option>
                    </select>
                </div>
                <div class="grupo-campo">
                    <label>Teléfono <span class="campo-obligatorio">*</span></label>
                    <input type="text" name="telefono_apoderado" id="telefonoApoderado"
                           maxlength="9" required placeholder="987654321">
                </div>
            </form>
            <div class="modal-botones">
                <button type="button" class="btn-secundario" id="btnCancelarApoderado">Cancelar</button>
                <button type="submit" form="formApoderado" class="btn-guardar" style="margin-top:0;">
                    <i class="ti ti-check"></i> Guardar Apoderado
                </button>
            </div>
        </div>
    </div>

</div>

<script src="../SCRIPTS/script_pacientes.js"></script>