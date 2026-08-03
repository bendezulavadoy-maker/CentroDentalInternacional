<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<link rel="stylesheet" href="../ESTILOS/style_personal.css">

<div class="contenedor-personal">

    <!-- ENCABEZADO -->
    <div class="encabezado-personal">
        <h2>Gestión de Personal</h2>
        <button id="btnNuevoPersonal" class="btn-principal">
            <i class="ti ti-user-plus"></i> Nuevo Personal
        </button>
    </div>

    <!-- LISTADO -->
    <section id="seccionListadoPersonal">
        <div class="barra-busqueda">
            <input type="text"
                   id="inputBusqueda"
                   placeholder="Buscar por nombre, código o correo..."
                   class="input-busqueda">
        </div>
        <table id="tablaPersonal">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>

    <!-- DETALLE -->
    <section id="seccionDetallePersonal" style="display:none;">
        <button id="btnVolverListado" class="btn-secundario">
            <i class="ti ti-arrow-left"></i> Volver
        </button>
        <div id="detallePersonal"></div>
    </section>

    <!-- FORMULARIO REGISTRO / EDICIÓN -->
    <section id="seccionNuevoPersonal" style="display:none;">
        <button id="btnVolverListado2" class="btn-secundario">
            <i class="ti ti-arrow-left"></i> Volver
        </button>

        <div class="tarjeta-formulario">
            <div class="tarjeta-formulario-header">
                <i class="ti ti-user-plus"></i>
                <span id="tituloFormPersonal">Registrar nuevo personal</span>
            </div>

            <form id="formPersonal" enctype="multipart/form-data">
                <div class="tarjeta-formulario-body">

                    <!-- FOTO -->
                    <div class="columna-izquierda">
                        <div class="grupo-foto">
                            <div id="previewFoto" class="preview-foto">
                                <i class="ti ti-camera"></i>
                            </div>
                        </div>
                        <div class="input-file-wrapper">
                            <input type="file" name="foto" id="fotoInput"
                                   accept="image/*" style="display:none;">
                            <label for="fotoInput" id="labelFoto" class="btn-file">
                                <i class="ti ti-upload" style="font-size:13px;"></i>
                                Subir foto
                            </label>
                        </div>
                        <span class="ayuda-foto">Opcional · JPG o PNG</span>

                        <!-- Credenciales generadas -->
                        <div id="infoRegistro" class="info-registro" style="display:none;">
                            <p><strong>Código asignado</strong></p>
                            <p id="codigoAsignado">—</p>
                            <p style="margin-top:8px;"><strong>Contraseña temporal</strong></p>
                            <p id="contrasenaAsignada">—</p>
                            <button type="button" id="btnCopiarTodo" class="btn-secundario" style="width:100%;margin-top:10px;margin-bottom:0;">
                                <i class="ti ti-copy" style="font-size:13px;"></i> Copiar usuario y contraseña
                            </button>
                        </div>
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
                            <small class="ayuda-campo">8 dígitos</small>
                        </div>

                        <div class="grupo-campo">
                            <label>Correo <span class="campo-obligatorio">*</span></label>
                            <input type="email" name="correo" required placeholder="correo@ejemplo.com">
                        </div>

                        <div class="grupo-campo">
                            <label>Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
                        </div>

                        <div class="grupo-campo">
                            <label>Rol <span class="campo-obligatorio">*</span></label>
                            <select name="id_rol" id="selectRol" required>
                                <option value="">Cargando roles...</option>
                            </select>
                        </div>

                        <div class="grupo-campo campo-ancho">
                            <label>Estado <span class="campo-obligatorio">*</span></label>
                            <select name="id_estado" required>
                                <option value="1">Activo</option>
                                <option value="2">Inactivo</option>
                            </select>
                        </div>

                    </div>
                </div>

                <div class="tarjeta-formulario-footer">
                    <button type="button" class="btn-secundario" style="margin-bottom:0;" onclick="document.getElementById('btnVolverListado2').click()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-guardar">
                        <i class="ti ti-device-floppy"></i> Guardar personal
                    </button>
                </div>
            </form>
        </div>
    </section>

</div>

<script src="../SCRIPTS/script_personal.js"></script>