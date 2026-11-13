<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['usuario']['id_usuario'])) {
    header('Location: ../VISTAS/vista_inicio_sesion.php?error=2');
    exit();
}
$obligatorio = !empty($_SESSION['usuario']['debe_cambiar_contrasena']);
$nombre      = $_SESSION['usuario']['nombre'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar contraseña — Dental Internacional</title>
    <link rel="stylesheet" href="../ESTILOS/style_inicio_sesion.css">
    <link rel="stylesheet" href="../ESTILOS/style_cambiar_contrasena.css">
</head>
<body>
    <div class="contenedor-login contenedor-cambio">
        <div class="barra-acento"></div>

        <div class="membrete-login">
            <span class="membrete-marca">DENTAL INTERNACIONAL</span>
            <span class="membrete-sub">Sistema de Gestión Clínica</span>
        </div>
        <div class="divisor-login"></div>
        <p class="etiqueta-acceso">Cambiar contraseña</p>

        <?php if ($obligatorio): ?>
            <div class="aviso-cambio" id="avisoObligatorio">
                Hola, <?= htmlspecialchars($nombre) ?>. Por seguridad debes crear una contraseña nueva antes de continuar.
            </div>
        <?php endif; ?>

        <form id="formCambio" autocomplete="off" novalidate>
            <div class="campo-form">
                <label for="claveActual">Contraseña actual</label>
                <div class="campo-clave">
                    <input type="password" id="claveActual" name="actual" autocomplete="current-password" required>
                    <button type="button" class="ver-clave" data-para="claveActual" aria-label="Mostrar u ocultar">Mostrar</button>
                </div>
            </div>
            <div class="campo-form">
                <label for="claveNueva">Contraseña nueva</label>
                <div class="campo-clave">
                    <input type="password" id="claveNueva" name="nueva" autocomplete="new-password" required>
                    <button type="button" class="ver-clave" data-para="claveNueva" aria-label="Mostrar u ocultar">Mostrar</button>
                </div>
            </div>
            <div class="campo-form">
                <label for="claveConfirmar">Repite la contraseña nueva</label>
                <div class="campo-clave">
                    <input type="password" id="claveConfirmar" name="confirmar" autocomplete="new-password" required>
                    <button type="button" class="ver-clave" data-para="claveConfirmar" aria-label="Mostrar u ocultar">Mostrar</button>
                </div>
            </div>

            <ul class="reglas-clave" id="reglasClave">
                <li data-regla="largo">Mínimo 8 caracteres</li>
                <li data-regla="mezcla">Al menos una letra y un número</li>
                <li data-regla="distinta">Distinta de la actual</li>
                <li data-regla="coincide">Las dos contraseñas coinciden</li>
            </ul>

            <p class="mensaje-error" id="mensajeError" hidden></p>
            <p class="mensaje-ok" id="mensajeOk" hidden></p>

            <button type="submit" id="btnGuardar" disabled>Guardar contraseña</button>
        </form>

        <?php if ($obligatorio): ?>
            <a class="enlace-volver" href="../CONTROLADORES/controlador_cerrar_sesion.php">Cerrar sesión</a>
        <?php else: ?>
            <a class="enlace-volver" href="vista_panel_admin.php">Volver al panel</a>
        <?php endif; ?>
    </div>

    <script src="../SCRIPTS/script_cambiar_contrasena.js"></script>
</body>
</html>
