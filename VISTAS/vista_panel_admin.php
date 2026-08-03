<?php
require_once '../CONFIG/verificar_sesion.php';

$nombreUsuario = $_SESSION['usuario']['nombre']    ?? 'Usuario';
$apellidoUsuario = $_SESSION['usuario']['apellidos'] ?? '';
$idRol         = $_SESSION['usuario']['id_rol']    ?? 0;
$permisos      = $_SESSION['usuario']['permisos']  ?? [];
$fotoUsuario   = $_SESSION['usuario']['foto']      ?? null;

// Nombre del rol
$nombreRol = match((int)$idRol) {
    1 => 'Administrador',
    2 => 'Odontólogo',
    3 => 'Asistente',
    default => 'Usuario'
};

// Módulos con íconos Tabler (sin emojis)
$todosLosModulos = [
    'pacientes'       => ['icono' => 'ti-users',           'etiqueta' => 'Pacientes',        'grupo' => 'clinica'],
    'citas'           => ['icono' => 'ti-calendar',        'etiqueta' => 'Citas',            'grupo' => 'clinica'],
    'mi_agenda'       => ['icono' => 'ti-calendar-stats',  'etiqueta' => 'Mi Agenda',        'grupo' => 'clinica'],
    'historia_clinica'=> ['icono' => 'ti-clipboard-list',  'etiqueta' => 'Historias Clínicas','grupo' => 'clinica'],
    'cobros'          => ['icono' => 'ti-receipt',         'etiqueta' => 'Cobros',           'grupo' => 'clinica'],
    'personal'        => ['icono' => 'ti-stethoscope',     'etiqueta' => 'Personal',         'grupo' => 'admin'],
    'configuracion'   => ['icono' => 'ti-settings',        'etiqueta' => 'Configuración',    'grupo' => 'admin'],
];

$grupos = [
    'clinica' => 'Gestión clínica',
    'admin'   => 'Administración',
];

// Módulos visibles para este usuario
$modulosVisibles = array_filter($todosLosModulos, fn($k) => in_array($k, $permisos), ARRAY_FILTER_USE_KEY);

// Módulos por grupo
$porGrupo = [];
foreach ($modulosVisibles as $clave => $info) {
    $porGrupo[$info['grupo']][$clave] = $info;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Internacional — Panel</title>
    <link rel="stylesheet" href="../ESTILOS/style_panel_admin.css">
</head>
<body>

<!-- ══ OVERLAY MOBILE ══ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ══ HEADER ══ -->
<header>
    <div class="header-marca">
        <button class="btn-menu-mobile" id="btnMenuMobile" aria-label="Menú">
            <i class="ti ti-menu-2"></i>
        </button>
        <div class="header-marca-icono">
            <i class="ti ti-tooth"></i>
        </div>
        <div class="header-marca-texto">
            <strong>Dental Internacional</strong>
            <span>Sistema de gestión</span>
        </div>
    </div>

    <div class="header-centro">
        <i class="ti ti-chevron-right" style="font-size:14px;color:rgba(255,255,255,0.4);"></i>
        <span id="header-modulo-actual" style="color:rgba(255,255,255,0.7);font-size:13px;">
            Inicio
        </span>
    </div>

    <div class="header-usuario">
        <div class="header-usuario-info">
            <span class="nombre"><?= htmlspecialchars($nombreUsuario . ' ' . $apellidoUsuario) ?></span>
            <span class="rol"><?= htmlspecialchars($nombreRol) ?></span>
        </div>
        <div class="header-avatar">
            <?php if ($fotoUsuario && file_exists('../' . $fotoUsuario)): ?>
                <img src="../<?= htmlspecialchars($fotoUsuario) ?>" alt="Foto">
            <?php else: ?>
                <i class="ti ti-user"></i>
            <?php endif; ?>
        </div>
        <a href="../CONTROLADORES/controlador_cerrar_sesion.php" class="btn-salir">
            <i class="ti ti-logout" style="font-size:15px;"></i>
            <span>Salir</span>
        </a>
    </div>
</header>

<!-- ══ SIDEBAR ══ -->
<aside id="sidebar">
    <nav>
        <ul>
            <?php foreach ($grupos as $grupoKey => $grupoLabel): ?>
                <?php if (!empty($porGrupo[$grupoKey])): ?>
                    <li>
                        <span class="nav-grupo-label"><?= $grupoLabel ?></span>
                    </li>
                    <?php foreach ($porGrupo[$grupoKey] as $clave => $info): ?>
                        <li>
                            <a href="#" data-vista="<?= $clave ?>" data-label="<?= htmlspecialchars($info['etiqueta']) ?>">
                                <i class="ti <?= $info['icono'] ?>"></i>
                                <span><?= htmlspecialchars($info['etiqueta']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><div class="nav-separador"></div></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-info">
            <i class="ti ti-shield-check" style="font-size:13px;"></i>
            <span>v1.0 — <?= date('Y') ?></span>
        </div>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<main id="contenido-principal">
    <div class="bienvenida">
        <div class="bienvenida-icono">
            <i class="ti ti-tooth"></i>
        </div>
        <h2>Bienvenido, <?= htmlspecialchars($nombreUsuario) ?></h2>
        <p>Selecciona un módulo del menú lateral para comenzar.</p>

        <?php if (!empty($modulosVisibles)): ?>
        <div class="bienvenida-cards">
            <?php foreach ($modulosVisibles as $clave => $info): ?>
                <a class="bienvenida-card"
                   href="#"
                   data-vista="<?= $clave ?>"
                   data-label="<?= htmlspecialchars($info['etiqueta']) ?>">
                    <i class="ti <?= $info['icono'] ?>"></i>
                    <span><?= htmlspecialchars($info['etiqueta']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
    window.permisosUsuario = <?= json_encode(array_values($permisos)) ?>;
    window.idRol           = <?= (int)$idRol ?>;
</script>
<script src="../SCRIPTS/script_panel_admin.js"></script>

<script>
// ── Sidebar mobile ────────────────────────────────────────────
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebarOverlay');
const btnMenu  = document.getElementById('btnMenuMobile');

function abrirSidebar() {
    sidebar.classList.add('abierto');
    overlay.classList.add('activo');
}
function cerrarSidebar() {
    sidebar.classList.remove('abierto');
    overlay.classList.remove('activo');
}

btnMenu?.addEventListener('click', () => {
    sidebar.classList.contains('abierto') ? cerrarSidebar() : abrirSidebar();
});
overlay.addEventListener('click', cerrarSidebar);

// ── Marcar item activo y actualizar breadcrumb ────────────────
function marcarActivo(vista, label) {
    document.querySelectorAll('aside a[data-vista]').forEach(a => a.classList.remove('activo'));
    const link = document.querySelector(`aside a[data-vista="${vista}"]`);
    if (link) link.classList.add('activo');
    const modLabel = document.getElementById('header-modulo-actual');
    if (modLabel) modLabel.textContent = label || vista;
    cerrarSidebar();
}

// Interceptar clics en sidebar y cards de bienvenida
document.addEventListener('click', e => {
    const el = e.target.closest('[data-vista]');
    if (el) {
        const vista = el.dataset.vista;
        const label = el.dataset.label || vista;
        marcarActivo(vista, label);
    }
});

// Marcar activo si se carga módulo por URL (?modulo=xxx)
const urlParams = new URLSearchParams(window.location.search);
const moduloUrl = urlParams.get('modulo');
if (moduloUrl) {
    const linkUrl = document.querySelector(`aside a[data-vista="${moduloUrl}"]`);
    if (linkUrl) {
        marcarActivo(moduloUrl, linkUrl.dataset.label);
        linkUrl.click();
    }
}
</script>

</body>
</html>