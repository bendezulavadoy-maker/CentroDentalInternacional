<?php
require_once '../CONFIG/verificar_sesion.php';
require_once '../CONFIG/conexion.php';
require_once '../SERVICIOS/GoogleCalendarService.php';

$idDoctor     = $_SESSION['usuario']['id_usuario'];
$correoDoctor = $_SESSION['usuario']['correo'] ?? '';
$idRol        = $_SESSION['usuario']['id_rol'] ?? 0;

// Solo mostrar el banner a los odontólogos (rol 2)
$db           = (new Conexion())->getConexion();
$gcal         = new GoogleCalendarService($db);
$gcalConectado = ($idRol == 2) ? $gcal->doctorConectado($idDoctor) : true;

// Mensajes desde el callback
$gcalMsg = $_GET['gcal'] ?? '';
if (session_status() === PHP_SESSION_NONE) session_start();
$gcalErr = $_SESSION['gcal_error'] ?? null;
if ($gcalErr) unset($_SESSION['gcal_error']);
?>
<link rel="stylesheet" href="../ESTILOS/style_mi_agenda.css">

<div class="contenedor-agenda">

    <!-- BANNER GOOGLE CALENDAR -->
    <?php if ($idRol == 2): ?>

        <?php if ($gcalMsg === 'conectado'): ?>
            <div style="background:#e9f7ef;border:1px solid #a9dfbf;border-radius:8px;
                        padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
                <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
                     style="width:20px;height:20px;" alt="">
                <span style="font-size:13px;color:#27ae60;font-weight:500;">
                    ✅ Google Calendar conectado correctamente. Tus citas se sincronizarán automáticamente.
                </span>
            </div>

        <?php elseif ($gcalMsg === 'email_no_coincide' && $gcalErr): ?>
            <div style="background:#fdf2f2;border:1px solid #f5b7b1;border-radius:8px;
                        padding:12px 16px;margin-bottom:16px;">
                <p style="font-size:13px;font-weight:600;color:#e74c3c;margin-bottom:4px;">
                    ❌ La cuenta Google no coincide con tu correo registrado
                </p>
                <p style="font-size:12px;color:#636e72;">
                    Usaste: <strong><?= htmlspecialchars($gcalErr['email_google']) ?></strong>
                    — Debes usar: <strong><?= htmlspecialchars($gcalErr['email_sistema']) ?></strong>
                </p>
            </div>

        <?php elseif (!$gcalConectado): ?>
            <div style="background:#fff9f0;border:1px solid #fad7a0;border-radius:8px;
                        padding:14px 18px;margin-bottom:16px;
                        display:flex;align-items:center;justify-content:space-between;gap:14px;
                        flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
                         style="width:28px;height:28px;" alt="">
                    <div>
                        <p style="font-size:13px;font-weight:600;color:#e67e22;margin-bottom:2px;">
                            Conecta tu Google Calendar
                        </p>
                        <p style="font-size:12px;color:#636e72;">
                            Sincroniza tus citas automáticamente con tu calendario personal.
                        </p>
                    </div>
                </div>
                <button onclick="abrirModalGcalAgenda()"
                        style="padding:8px 18px;font-size:13px;font-weight:600;border-radius:6px;
                               background:#2a4d8f;color:white;border:none;cursor:pointer;
                               display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                    <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
                         style="width:14px;height:14px;filter:brightness(10);" alt="">
                    Conectar Calendar
                </button>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- MODAL CONFIRMACIÓN GOOGLE CALENDAR -->
    <div id="modalGcalAgenda"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
                z-index:9999;align-items:center;justify-content:center;padding:16px;">
        <div style="background:white;border-radius:12px;width:100%;max-width:420px;
                    box-shadow:0 12px 40px rgba(0,0,0,0.2);overflow:hidden;">
            <div style="background:#2a4d8f;padding:16px 20px;display:flex;
                        align-items:center;gap:10px;">
                <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
                     style="width:22px;height:22px;" alt="">
                <h3 style="color:white;font-size:15px;font-weight:600;margin:0;">
                    Conectar Google Calendar
                </h3>
            </div>
            <div style="padding:20px;">
                <p style="font-size:13px;color:#636e72;margin-bottom:14px;">
                    Se conectará con la cuenta Google registrada en tu perfil:
                </p>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;
                            background:#eef2fb;border-radius:8px;border:1px solid #aed6f1;
                            margin-bottom:12px;">
                    <img src="https://www.gstatic.com/images/branding/googleg/1x/googleg_32dp.png"
                         style="width:18px;height:18px;" alt="">
                    <span style="font-size:13px;font-weight:600;color:#2a4d8f;">
                        <?= htmlspecialchars($correoDoctor) ?>
                    </span>
                </div>
                <div style="background:#fff9f0;border:1px solid #fad7a0;border-radius:6px;
                            padding:10px 12px;margin-bottom:16px;">
                    <p style="font-size:12px;color:#e67e22;margin:0;">
                        ⚠️ <strong>Importante:</strong> Cuando Google te pida elegir una cuenta,
                        selecciona <strong>exactamente</strong> el correo de arriba.
                        Si usas otra cuenta, la conexión será rechazada.
                    </p>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button onclick="cerrarModalGcalAgenda()"
                            style="padding:8px 18px;font-size:13px;border-radius:6px;
                                   border:1px solid #ddd;background:white;
                                   color:#636e72;cursor:pointer;">
                        Cancelar
                    </button>
                    <a href="<?= $gcal->getUrlAutorizacion($idDoctor, 'agenda') ?>"
                       style="padding:8px 18px;font-size:13px;font-weight:600;border-radius:6px;
                              background:#2a4d8f;color:white;text-decoration:none;
                              display:inline-flex;align-items:center;gap:6px;">
                        <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
                             style="width:14px;height:14px;filter:brightness(10);" alt="">
                        Continuar con Google
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Funciones de modal definidas en script_mi_agenda.js
    </script>

    <!-- ENCABEZADO -->
    <div class="encabezado-agenda">
        <div>
            <h2>Mi Agenda</h2>
            <p class="subtitulo-agenda">Citas asignadas a tu usuario</p>
        </div>
        <div class="controles-derecha">
            <div class="vistas-agenda">
                <button class="btn-vista activo" data-vista="lista">Lista</button>
                <button class="btn-vista" data-vista="calendario">Calendario</button>
            </div>
        </div>
    </div>

    <!-- BARRA DE NAVEGACIÓN Y FILTROS -->
    <div class="barra-navegacion">

        <!-- Período rápido -->
        <div class="periodos-rapidos">
            <button class="btn-periodo activo" data-periodo="hoy">Hoy</button>
            <button class="btn-periodo" data-periodo="semana">Esta semana</button>
            <button class="btn-periodo" data-periodo="mes">Este mes</button>
            <button class="btn-periodo" data-periodo="todas">Todas</button>
        </div>

        <!-- Selector de rango -->
        <div class="selector-rango">
            <button class="btn-nav-fecha" id="btnAnterior">&#8249;</button>
            <div class="rango-inputs">
                <input type="date" id="fechaDesde" class="input-fecha">
                <span class="rango-separador">—</span>
                <input type="date" id="fechaHasta" class="input-fecha">
            </div>
            <button class="btn-nav-fecha" id="btnSiguiente">&#8250;</button>
            <button class="btn-aplicar" id="btnAplicarRango">Aplicar</button>
        </div>

    </div>

    <!-- FILTROS -->
    <div class="barra-filtros">
        <div class="filtro-grupo">
            <label>Estado</label>
            <select id="filtroEstado">
                <option value="">Todos</option>
                <option value="Programada">Programada</option>
                <option value="Confirmada">Confirmada</option>
                <option value="En atención">En atención</option>
                <option value="Completada">Completada</option>
                <option value="Cancelada">Cancelada</option>
                <option value="No asistió">No asistió</option>
            </select>
        </div>
        <div class="filtro-grupo">
            <label>Paciente</label>
            <input type="text" id="filtroPaciente" placeholder="Nombre o DNI..." class="input-filtro">
        </div>
        <button class="btn-limpiar" id="btnLimpiarFiltros">Limpiar filtros</button>
    </div>

    <!-- CONTADORES -->
    <div class="resumen-agenda">
        <div class="tarjeta-resumen programadas">
            <span class="resumen-numero" id="totalProgramadas">0</span>
            <span class="resumen-label">Programadas</span>
        </div>
        <div class="tarjeta-resumen confirmadas">
            <span class="resumen-numero" id="totalConfirmadas">0</span>
            <span class="resumen-label">Confirmadas</span>
        </div>
        <div class="tarjeta-resumen en-atencion">
            <span class="resumen-numero" id="totalEnAtencion">0</span>
            <span class="resumen-label">En Atención</span>
        </div>
        <div class="tarjeta-resumen completadas">
            <span class="resumen-numero" id="totalCompletadas">0</span>
            <span class="resumen-label">Completadas</span>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div id="contenedorCitas" class="contenedor-citas">
        <p class="cargando-agenda">Cargando citas...</p>
    </div>

    <!-- PANEL LATERAL DETALLE -->
    <div id="panelDetalleCita" class="panel-detalle" style="display:none;">
        <div class="panel-header">
            <h3>Detalle de Cita</h3>
            <button id="btnCerrarPanel" class="btn-cerrar-panel">&#10005;</button>
        </div>
        <div id="contenidoDetalleCita"></div>
    </div>

</div>

<script src="../SCRIPTS/script_mi_agenda.js"></script>