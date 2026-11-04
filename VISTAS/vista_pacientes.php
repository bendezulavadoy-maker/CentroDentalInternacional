<?php
require_once '../CONFIG/verificar_sesion.php';
require_once '../CONFIG/permisos.php';
// El boton y la ventana de importacion solo existen para quien tiene el permiso (el servidor lo vuelve a exigir)
$puedeImportar = Permisos::puede('pacientes.importar');
?>
<link rel="stylesheet" href="../ESTILOS/style_pacientes.css">
<?php if ($puedeImportar): ?><link rel="stylesheet" href="../ESTILOS/style_importacion.css"><?php endif; ?>

<div class="contenedor-personal">

    <!-- ENCABEZADO -->
    <div class="encabezado-personal">
        <h2>Gestión de Pacientes</h2>
        <div style="display:flex; gap:10px; align-items:center;">
            <?php if ($puedeImportar): ?>
            <button id="btnImportarPacientes" type="button" class="btn-secundario">
                <i class="ti ti-file-import"></i> Importar desde Excel
            </button>
            <?php endif; ?>
            <button id="btnNuevoPersonal" class="btn-principal">
                <i class="ti ti-user-plus"></i> Nuevo Paciente
            </button>
        </div>
    </div>

    <!-- LISTADO -->
    <section id="seccionListadoPersonal">
        <div class="barra-busqueda">
            <input type="text"
                   id="inputBusqueda"
                   placeholder="Buscar por nombre, documento, teléfono o correo..."
                   class="input-busqueda">
        </div>
        <table id="tablaPacientes">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Documento</th>
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
                        <label>Documento de identidad <span class="campo-obligatorio">*</span></label>
                        <div class="grupo-documento">
                            <select name="id_tipo_documento" id="selectTipoDocumento">
                                <option value="1">DNI</option>
                            </select>
                            <input type="text" name="nro_documento" id="inputNroDocumento" maxlength="8" required placeholder="12345678">
                        </div>
                        <small class="ayuda-campo" id="ayudaDocumento">8 dígitos sin puntos</small>
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
                        <label>Grado de Instrucción</label>
                        <select name="grado_instruccion" id="selectGrado_instruccion">
                            <option value="">Selecciona...</option>
                        </select>
                    </div>

                    <div class="grupo-campo">
                        <label>Ocupación</label>
                        <input type="text" name="ocupacion" placeholder="Ej: Docente, Estudiante...">
                    </div>

                    <div class="grupo-campo">
                        <label>Odontólogo responsable</label>
                        <select name="id_doctor_responsable" id="selectResponsable">
                            <option value="">Sin asignar</option>
                        </select>
                        <small class="ayuda-campo" id="ayudaResponsable">Puede modificar los documentos del paciente</small>
                    </div>

                    <div class="grupo-campo">
                        <label>Teléfono <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="telefono" maxlength="9" required placeholder="987654321">
                        <small class="ayuda-campo">9 dígitos</small>
                    </div>

                    <div class="grupo-campo">
                        <label>Correo</label>
                        <input type="email" name="correo" placeholder="correo@ejemplo.com">
                    </div>

                    <div class="grupo-campo campo-ancho">
                        <label>Dirección <span class="campo-obligatorio">*</span></label>
                        <input type="text" name="direccion" required placeholder="Av. Principal 123, Urb...">
                    </div>

                    <div class="grupo-campo campo-ancho" id="grupoApoderado">
                        <label>Apoderado
                            <span style="font-weight:400;color:#9aa3b0;font-size:11px;">(obligatorio si es menor de edad; opcional en adultos)</span>
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
                    <label>Documento de identidad <span class="campo-obligatorio">*</span></label>
                    <div class="grupo-documento">
                        <select name="tipo_doc_apoderado" id="tipoDocApoderado">
                            <option value="1">DNI</option>
                        </select>
                        <input type="text" name="nro_documento_apoderado" id="dniApoderado"
                               maxlength="8" required placeholder="12345678">
                    </div>
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

    <!-- MODAL: RESTABLECER ACCESO AL PORTAL -->
    <div id="modalRestablecerPortal" class="modal-overlay">
        <div class="modal-contenido" style="max-width:420px;">
            <div class="modal-encabezado">
                <h3>Restablecer acceso al portal</h3>
                <button type="button" class="btn-cerrar-modal" id="btnCerrarRestablecer">✕</button>
            </div>
            <div style="padding:20px;">
                <p style="margin:0 0 8px;font-size:13.5px;color:#45505c;">
                    Se eliminará la contraseña del portal de <strong id="restablecerNombrePaciente"></strong>.
                </p>
                <p style="margin:0 0 8px;font-size:13.5px;color:#45505c;">
                    Para volver a entrar, el paciente deberá crear una nueva con su documento y el teléfono
                    registrado en la clínica (<strong id="restablecerTelefonoPaciente"></strong>).
                </p>
                <p style="margin:0;font-size:12px;color:#7f8c8d;">
                    Confirma la identidad del paciente antes de continuar.
                </p>
            </div>
            <div class="modal-botones">
                <button type="button" class="btn-secundario" id="btnCancelarRestablecer">Cancelar</button>
                <button type="button" class="btn-guardar" id="btnConfirmarRestablecer" style="margin-top:0;">
                    Sí, restablecer
                </button>
            </div>
        </div>
    </div>

</div>

<?php if ($puedeImportar): ?>
<!-- MODAL: IMPORTAR PACIENTES DESDE EXCEL -->
<div id="modalImportarPacientes" class="imp-overlay" style="display:none;">
    <div class="imp-contenido" role="dialog" aria-modal="true" aria-labelledby="impTitulo">
        <div class="imp-encabezado">
            <h3 id="impTitulo">Importar pacientes desde Excel</h3>
            <button type="button" id="impCerrar" class="imp-cerrar" aria-label="Cerrar">&times;</button>
        </div>
        <div class="imp-cuerpo">

            <!-- Paso 1: elegir archivo -->
            <section id="impPaso1">
                <ol class="imp-pasos">
                    <li>Descargue la plantilla y llénela: una fila por paciente.</li>
                    <li>Súbala aquí para <strong>revisarla</strong>. Todavía no se guarda nada.</li>
                    <li>Confirme: se importan las filas correctas y se le muestran las que tienen problemas.</li>
                </ol>
                <a id="impPlantilla" class="imp-btn imp-btn-sec" href="../CONTROLADORES/controlador_importacion_pacientes.php?accion=plantilla">
                    <i class="ti ti-download"></i> Descargar plantilla
                </a>
                <div class="imp-subida">
                    <input type="file" id="impArchivo" accept=".xlsx,.csv">
                    <button type="button" id="impRevisar" class="imp-btn imp-btn-prim" disabled>Revisar archivo</button>
                </div>
                <p class="imp-ayuda">Formatos: .xlsx o .csv &middot; hasta 1000 filas y 5 MB por archivo. Los pacientes que ya existen se omiten sin modificarlos.</p>
                <p id="impMensaje" class="imp-mensaje" role="alert" style="display:none;"></p>
            </section>

            <!-- Paso 2: resultado de la revision -->
            <section id="impPaso2" style="display:none;">
                <p class="imp-archivo">Archivo: <strong id="impNombreArchivo"></strong></p>
                <div class="imp-resumen" id="impResumen"></div>
                <p id="impIgnoradas" class="imp-ayuda" style="display:none;"></p>
                <div id="impErrores"></div>
                <p id="impMensaje2" class="imp-mensaje" role="alert" style="display:none;"></p>
                <div class="imp-acciones">
                    <button type="button" id="impVolver" class="imp-btn imp-btn-sec">Elegir otro archivo</button>
                    <button type="button" id="impConfirmar" class="imp-btn imp-btn-prim">Importar</button>
                </div>
            </section>

            <!-- Paso 3: resultado final -->
            <section id="impPaso3" style="display:none;">
                <div class="imp-resumen" id="impResumenFinal"></div>
                <div id="impErroresFinal"></div>
                <div class="imp-acciones">
                    <button type="button" id="impOtra" class="imp-btn imp-btn-sec">Importar otro archivo</button>
                    <button type="button" id="impListo" class="imp-btn imp-btn-prim">Listo</button>
                </div>
            </section>

            <hr class="imp-separador">
            <h4 class="imp-subtitulo">Cargas anteriores</h4>
            <div id="impConfirmarRevertir" class="imp-confirmacion" style="display:none;"></div>
            <div id="impHistorial"><p class="imp-ayuda">Cargando...</p></div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="../SCRIPTS/script_pacientes.js"></script>