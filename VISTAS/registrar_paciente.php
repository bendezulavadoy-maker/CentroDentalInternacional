<!-- /VISTA/registrar_paciente.php -->
<div class="formulario-paciente">
    <h2>Registrar Paciente</h2>

    <form id="formPaciente" method="POST" enctype="multipart/form-data">

        <!-- FOTO -->
        <div class="foto-contenedor">
            <div class="foto-wrapper">
                <img id="previewFoto" src="../IMAGENES/perfiles_pacientes/default_user.png" alt="Foto del Paciente" class="foto-preview">
                <label for="foto" class="btn-subir-foto">📸</label>
                <input type="file" id="foto" name="foto" accept="image/*" hidden>
            </div>
            <p class="texto-foto">Haz clic en el ícono para subir una foto</p>
        </div>

        <!-- CAMPOS -->
        <div class="form-grid">
            <div class="form-group">
                <label for="dni">DNI:</label>
                <input type="text" id="dni" name="dni" maxlength="8" required>
            </div>

            <div class="form-group">
                <label for="nombre">Nombres:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>

            <div class="form-group">
                <label for="apellido">Apellidos:</label>
                <input type="text" id="apellido" name="apellido" required>
            </div>

            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono:</label>
                <input type="text" id="telefono" name="telefono" maxlength="9">
            </div>

            <div class="form-group">
                <label for="correo">Correo:</label>
                <input type="email" id="correo" name="correo">
            </div>

            <div class="form-group direccion">
                <label for="direccion">Dirección:</label>
                <input type="text" id="direccion" name="direccion">
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn-registrar">💾 Registrar</button>
            <button type="reset" class="btn-limpiar">🧹 Limpiar</button>
        </div>
    </form>
</div>


