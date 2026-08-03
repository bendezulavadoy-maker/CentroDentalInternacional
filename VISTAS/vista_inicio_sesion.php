<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio de Sesión</title>
    <link rel="stylesheet" href="../ESTILOS/style_inicio_sesion.css">
</head>
<body>
    <div class="contenedor-login">
        <h2>Iniciar Sesión</h2>
        <form method="POST" action="../CONTROLADORES/controlador_inicio_sesion.php">
            <label for="codigo_usuario">Código de usuario:</label>
            <input type="text" id="codigo_usuario" name="codigo_usuario" required>

            <label for="contrasena">Contraseña:</label>
            <input type="password" id="contrasena" name="contrasena" required>

            <button type="submit">Ingresar</button>
        </form>

        <?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'inactivo'): ?>
        <p class="error">⚠️ Tu cuenta está inactiva. Contacta al administrador.</p>
    <?php else: ?>
        <p class="error">❌ Usuario o contraseña incorrectos.</p>
    <?php endif; ?>
<?php endif; ?>
    </div>
</body>
</html>
