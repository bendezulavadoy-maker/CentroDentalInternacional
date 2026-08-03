<?php
require_once '../CONFIG/conexion.php';
require_once '../SERVICIOS/GoogleCalendarService.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db      = (new Conexion())->getConexion();
$gcal    = new GoogleCalendarService($db);

$stmt    = $db->query("SELECT id_usuario, nombre, apellidos, correo FROM usuarios WHERE id_rol=2 AND id_estado=1 ORDER BY nombre");
$doctores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gcalMsg  = $_GET['gcal']   ?? '';
$gcalDoc  = $_GET['doctor'] ?? '';
$gcalErr  = $_SESSION['gcal_error'] ?? null;
if ($gcalErr) unset($_SESSION['gcal_error']);
?>

<!-- Modal de confirmación previa -->
<div id="modalConfirmarGcal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
            z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:white;border-radius:12px;width:100%;max-width:420px;
                box-shadow:0 12px 40px rgba(0,0,0,0.2);overflow:hidden;">
        <div style="background:#2a4d8f;padding:16px 20px;display:flex;align-items:center;gap:10px;">
            <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
                 style="width:22px;height:22px;" alt="">
            <h3 style="color:white;font-size:15px;font-weight:600;margin:0;">Conectar Google Calendar</h3>
        </div>
        <div style="padding:20px;">
            <p style="font-size:13px;color:#636e72;margin-bottom:14px;">
                Se conectará con la cuenta Google registrada en el sistema para este doctor:
            </p>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;
                        background:#eef2fb;border-radius:8px;border:1px solid #aed6f1;margin-bottom:12px;">
                <img src="https://www.gstatic.com/images/branding/googleg/1x/googleg_32dp.png"
                     style="width:18px;height:18px;" alt="">
                <span id="modalEmailDoctor"
                      style="font-size:13px;font-weight:600;color:#2a4d8f;"></span>
            </div>
            <div style="background:#fff9f0;border:1px solid #fad7a0;border-radius:6px;
                        padding:10px 12px;margin-bottom:16px;">
                <p style="font-size:12px;color:#e67e22;margin:0;">
                    ⚠️ <strong>Importante:</strong> Cuando Google te pida elegir una cuenta,
                    selecciona <strong>exactamente</strong> el correo que aparece arriba.
                    Si usas otra cuenta, la conexión será rechazada.
                </p>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button onclick="cerrarModalGcal()"
                        style="padding:8px 18px;font-size:13px;border-radius:6px;
                               border:1px solid #ddd;background:white;color:#636e72;cursor:pointer;">
                    Cancelar
                </button>
                <a id="btnContinuarGcal" href="#"
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

<div style="background:white;border-radius:10px;border:1px solid #e0e4ea;
            overflow:hidden;margin-bottom:20px;">
    <div style="background:#f0f3f7;padding:12px 20px;border-bottom:1px solid #e0e4ea;
                display:flex;align-items:center;gap:8px;">
        <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
             style="width:20px;height:20px;" alt="">
        <h3 style="font-size:13px;font-weight:600;color:#636e72;
                   text-transform:uppercase;letter-spacing:.5px;margin:0;">
            Sincronización Google Calendar
        </h3>
    </div>
    <div style="padding:20px;">

        <?php if ($gcalMsg === 'conectado'): ?>
            <div style="background:#e9f7ef;border:1px solid #a9dfbf;border-radius:6px;
                        padding:10px 14px;margin-bottom:16px;color:#27ae60;font-size:13px;">
                ✅ Google Calendar conectado correctamente.
            </div>

        <?php elseif ($gcalMsg === 'email_no_coincide' && $gcalErr): ?>
            <div style="background:#fdf2f2;border:1px solid #f5b7b1;border-radius:6px;
                        padding:12px 16px;margin-bottom:16px;">
                <p style="font-size:13px;font-weight:600;color:#e74c3c;margin-bottom:6px;">
                    ❌ La cuenta Google no coincide
                </p>
                <p style="font-size:13px;color:#e74c3c;margin-bottom:4px;">
                    Cuenta usada en Google:
                    <strong><?= htmlspecialchars($gcalErr['email_google']) ?></strong>
                </p>
                <p style="font-size:13px;color:#e74c3c;">
                    Cuenta registrada en el sistema:
                    <strong><?= htmlspecialchars($gcalErr['email_sistema']) ?></strong>
                </p>
                <p style="font-size:12px;color:#636e72;margin-top:8px;">
                    Debes autorizar con el correo registrado en el sistema,
                    o pide al administrador que actualice tu correo.
                </p>
            </div>

        <?php elseif ($gcalMsg === 'cancelado'): ?>
            <div style="background:#f4f6f7;border:1px solid #d5d8dc;border-radius:6px;
                        padding:10px 14px;margin-bottom:16px;color:#636e72;font-size:13px;">
                ℹ️ Conexión cancelada.
            </div>

        <?php elseif ($gcalMsg === 'error'): ?>
            <div style="background:#fdf2f2;border:1px solid #f5b7b1;border-radius:6px;
                        padding:10px 14px;margin-bottom:16px;color:#e74c3c;font-size:13px;">
                ❌ Error al conectar Google Calendar. Intenta nuevamente.
            </div>
        <?php endif; ?>

        <p style="font-size:13px;color:#636e72;margin-bottom:16px;">
            Cada doctor puede conectar su Google Calendar personal. Las citas se sincronizarán
            automáticamente al crear, editar o cancelar.
        </p>

        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($doctores as $doc): ?>
                <?php $conectado = $gcal->doctorConectado($doc['id_usuario']); ?>
                <div style="display:flex;align-items:center;justify-content:space-between;
                            padding:12px 16px;background:#f8f9fa;border-radius:8px;
                            border:1px solid #e0e4ea;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:8px;height:8px;border-radius:50%;
                                    background:<?= $conectado ? '#27ae60' : '#e74c3c' ?>;"></div>
                        <div>
                            <span style="font-size:13px;font-weight:500;color:#2c3e50;display:block;">
                                Dr(a). <?= htmlspecialchars($doc['nombre'] . ' ' . $doc['apellidos']) ?>
                            </span>
                            <span style="font-size:11px;color:#95a5a6;">
                                <?= htmlspecialchars($doc['correo']) ?>
                            </span>
                        </div>
                        <span style="font-size:11px;color:<?= $conectado ? '#27ae60' : '#95a5a6' ?>;">
                            <?= $conectado ? '● Conectado' : '○ No conectado' ?>
                        </span>
                    </div>
                    <div>
                        <?php if ($conectado): ?>
                            <span style="font-size:12px;color:#27ae60;padding:5px 12px;
                                         border:1px solid #a9dfbf;border-radius:5px;
                                         background:#e9f7ef;">
                                ✓ Sincronizado
                            </span>
                        <?php else: ?>
                            <button
                                onclick="abrirModalGcal(
                                    '<?= htmlspecialchars($doc['correo']) ?>',
                                    '<?= $gcal->getUrlAutorizacion($doc['id_usuario']) ?>'
                                )"
                                style="font-size:12px;font-weight:600;color:white;
                                       padding:6px 14px;border-radius:5px;
                                       background:#2a4d8f;border:none;cursor:pointer;
                                       display:inline-flex;align-items:center;gap:6px;">
                                <img src="https://www.gstatic.com/images/branding/product/1x/calendar_32dp.png"
                                     style="width:14px;height:14px;filter:brightness(10);" alt="">
                                Conectar Calendar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script>
// Funciones de modal definidas en script_configuracion.js
</script>