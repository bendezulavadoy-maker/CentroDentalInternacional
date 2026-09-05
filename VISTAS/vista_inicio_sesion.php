<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión — Dental Internacional</title>
    <link rel="stylesheet" href="../ESTILOS/style_inicio_sesion.css">
</head>
<body>
    <div class="contenedor-login">
        <div class="barra-acento"></div>

        <div class="membrete-login">
            <span class="membrete-marca">DENTAL INTERNACIONAL</span>
            <span class="membrete-sub">Sistema de Gestión Clínica</span>
        </div>

        <div class="divisor-login"></div>

        <p class="etiqueta-acceso">Acceso al sistema</p>

        <form method="POST" action="../CONTROLADORES/controlador_inicio_sesion.php" id="formLogin">
            <div class="campo-form">
                <label for="numeroUsuario">Código de usuario</label>
                <div class="grupo-usuario">
                    <span class="prefijo-usuario">DENTINT</span>
                    <input type="text"
                        id="numeroUsuario"
                        placeholder="7323"
                        autocomplete="username"
                        inputmode="numeric"
                        required>
                </div>
                <input type="hidden" name="codigo_usuario" id="codigoUsuarioCompleto">
            </div>

            <div class="campo-form">
                <label for="contrasena">Contraseña</label>
                <input type="password"
                    id="contrasena"
                    name="contrasena"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required>
            </div>

            <button type="submit">Ingresar</button>
        </form>

        <?php if (isset($_GET['error'])): ?>
            <?php if ($_GET['error'] === 'inactivo'): ?>
                <p class="mensaje-error">Tu cuenta está inactiva. Contacta al administrador.</p>
            <?php else: ?>
                <p class="mensaje-error">Usuario o contraseña incorrectos.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="../SCRIPTS/script_inicio_sesion.js"></script>
</body>
</html>