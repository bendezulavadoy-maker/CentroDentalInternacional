<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Paciente — Dental Internacional</title>
    <link rel="stylesheet" href="style_portal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body>

<div class="pantalla-acceso" id="pantalla">

    <!-- ══ PASO 1: DNI ══ -->
    <div class="caja-acceso" id="paso-dni">
        <div class="caja-acceso-header">
            <h1>🦷 Dental Internacional</h1>
            <p>Portal de autogestión para pacientes</p>
        </div>
        <div class="caja-acceso-body">
            <div id="alerta-dni"></div>
            <div class="campo" style="margin-bottom:16px;">
                <label>Ingresa tu DNI <span class="req">*</span></label>
                <input type="text" id="inputDni" maxlength="8" placeholder="12345678"
                       style="font-size:18px;text-align:center;letter-spacing:4px;"
                       oninput="this.value=this.value.replace(/\D/g,'')">
                <span class="ayuda">8 dígitos — sin puntos ni espacios</span>
            </div>
            <button class="btn btn-primario btn-full" onclick="verificarDni()">
                <i class="ti ti-arrow-right"></i> Continuar
            </button>
            <div class="separador">o</div>
            <p style="text-align:center;font-size:12px;color:var(--texto-sec);">
                ¿Eres nuevo paciente? Ingresa tu DNI y te guiaremos para registrarte.
            </p>
        </div>
    </div>

    <!-- ══ PASO 2A: LOGIN (paciente existente con contraseña) ══ -->
    <div class="caja-acceso" id="paso-login" style="display:none;">
        <div class="caja-acceso-header">
            <h1>👋 Bienvenido de vuelta</h1>
            <p id="login-nombre-paciente">Ingresa tu contraseña para continuar</p>
        </div>
        <div class="caja-acceso-body">
            <div id="alerta-login"></div>
            <div class="campo" style="margin-bottom:14px;">
                <label>DNI</label>
                <input type="text" id="login-dni-mostrado" disabled
                       style="background:#f4f6f9;color:var(--texto-sec);">
            </div>
            <div class="campo" style="margin-bottom:16px;">
                <label>Contraseña <span class="req">*</span></label>
                <div style="position:relative;">
                    <input type="password" id="inputPassword" placeholder="••••••••"
                           onkeydown="if(event.key==='Enter') iniciarSesion()">
                    <i class="ti ti-eye" id="togglePass"
                       style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                              cursor:pointer;color:var(--texto-ter);font-size:16px;"
                       onclick="togglePassword()"></i>
                </div>
            </div>
            <button class="btn btn-primario btn-full" onclick="iniciarSesion()">
                <i class="ti ti-login"></i> Ingresar
            </button>
            <div style="text-align:center;margin-top:14px;">
                <button class="btn btn-secundario" onclick="volverDni()" style="font-size:12px;">
                    <i class="ti ti-arrow-left"></i> Cambiar DNI
                </button>
            </div>
        </div>
    </div>

    <!-- ══ PASO 2B: CREAR CONTRASEÑA (paciente existente sin contraseña) ══ -->
    <div class="caja-acceso" id="paso-crear-pass" style="display:none;">
        <div class="caja-acceso-header">
            <h1>🔑 Crea tu acceso</h1>
            <p>Es tu primera vez en el portal. Crea una contraseña.</p>
        </div>
        <div class="caja-acceso-body">
            <div id="alerta-crear-pass"></div>
            <p style="font-size:13px;color:var(--texto-sec);margin-bottom:16px;
                      padding:10px 12px;background:#eef2fb;border-radius:6px;">
                Encontramos tu historial como paciente. Solo necesitas crear una contraseña para acceder al portal.
            </p>
            <div class="campo" style="margin-bottom:12px;">
                <label>Nueva contraseña <span class="req">*</span></label>
                <input type="password" id="nuevaPass1" placeholder="Mínimo 6 caracteres">
                <span class="ayuda">Mínimo 6 caracteres</span>
            </div>
            <div class="campo" style="margin-bottom:16px;">
                <label>Confirmar contraseña <span class="req">*</span></label>
                <input type="password" id="nuevaPass2" placeholder="Repite la contraseña">
            </div>
            <button class="btn btn-primario btn-full" onclick="crearContraseña()">
                <i class="ti ti-check"></i> Crear contraseña e ingresar
            </button>
            <div style="text-align:center;margin-top:12px;">
                <button class="btn btn-secundario" onclick="volverDni()" style="font-size:12px;">
                    <i class="ti ti-arrow-left"></i> Cambiar DNI
                </button>
            </div>
        </div>
    </div>

    <!-- ══ PASO 3: REGISTRO COMPLETO (paciente nuevo) ══ -->
    <div id="paso-registro" style="display:none;width:100%;max-width:720px;">
        <div class="tarjeta">
            <div class="caja-acceso-header" style="background:var(--azul);padding:20px 28px;">
                <h1 style="color:white;font-size:18px;">🦷 Nuevo paciente — Registro</h1>
                <p style="color:rgba(255,255,255,0.8);font-size:13px;">
                    Completa tus datos para agendar citas en Dental Internacional
                </p>
            </div>

            <div style="padding:24px 28px;">
                <!-- Pasos visuales -->
                <div class="pasos" id="pasos-registro">
                    <div class="paso activo" id="paso-vis-1">
                        <div class="paso-num">1</div><span>Datos personales</span>
                    </div>
                    <div class="paso-linea"></div>
                    <div class="paso" id="paso-vis-2">
                        <div class="paso-num">2</div><span>Contacto</span>
                    </div>
                    <div class="paso-linea"></div>
                    <div class="paso" id="paso-vis-3">
                        <div class="paso-num">3</div><span>Acceso</span>
                    </div>
                </div>

                <div id="alerta-registro"></div>

                <!-- SECCIÓN 1: Datos personales -->
                <div id="reg-sec-1">
                    <div class="grid-2" style="gap:14px;">
                        <div class="campo">
                            <label>Nombre(s) <span class="req">*</span></label>
                            <input type="text" id="reg-nombre" placeholder="Ej: María Elena">
                        </div>
                        <div class="campo">
                            <label>Apellido(s) <span class="req">*</span></label>
                            <input type="text" id="reg-apellido" placeholder="Ej: García López">
                        </div>
                        <div class="campo">
                            <label>DNI <span class="req">*</span></label>
                            <input type="text" id="reg-dni" maxlength="8" readonly
                                   style="background:#f4f6f9;color:var(--texto-sec);">
                        </div>
                        <div class="campo">
                            <label>Fecha de nacimiento <span class="req">*</span></label>
                            <input type="date" id="reg-fecha-nac">
                        </div>
                        <div class="campo">
                            <label>Sexo <span class="req">*</span></label>
                            <select id="reg-sexo">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Estado civil <span class="req">*</span></label>
                            <select id="reg-estado-civil">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Grado de instrucción <span class="req">*</span></label>
                            <select id="reg-grado">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label>Ocupación <span class="req">*</span></label>
                            <input type="text" id="reg-ocupacion" placeholder="Ej: Estudiante, Docente...">
                        </div>
                    </div>

                    <!-- Foto (opcional) -->
                    <div style="margin-top:16px;">
                        <label style="font-size:12px;font-weight:600;color:var(--texto-sec);display:block;margin-bottom:6px;">
                            Foto de perfil <span class="opc" style="font-weight:400;font-size:11px;color:var(--texto-ter);">(opcional)</span>
                        </label>
                        <div class="upload-foto" onclick="document.getElementById('reg-foto').click()">
                            <img id="preview-foto" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='%23ddd'/%3E%3Cpath d='M4 20c0-4 3.6-7 8-7s8 3 8 7' fill='%23ddd'/%3E%3C/svg%3E"
                                 alt="foto">
                            <div class="upload-foto-info">
                                <p>Seleccionar foto</p>
                                <small>JPG o PNG, máx. 2MB</small>
                            </div>
                        </div>
                        <input type="file" id="reg-foto" accept="image/jpeg,image/png"
                               style="display:none;" onchange="previsualizarFoto(this)">
                    </div>

                    <!-- Apoderado (condicional — menor de edad) -->
                    <div id="seccion-apoderado" style="display:none;margin-top:16px;
                         padding:14px;background:#fff9f0;border:1px solid #fad7a0;border-radius:8px;">
                        <p style="font-size:12px;font-weight:600;color:var(--naranja);
                                  margin-bottom:12px;text-transform:uppercase;letter-spacing:.4px;">
                            ⚠️ Paciente menor de edad — datos del apoderado
                        </p>
                        <div class="grid-2" style="gap:12px;">
                            <div class="campo">
                                <label>Nombre del apoderado <span class="req">*</span></label>
                                <input type="text" id="apo-nombre" placeholder="Nombre(s)">
                            </div>
                            <div class="campo">
                                <label>Apellido del apoderado <span class="req">*</span></label>
                                <input type="text" id="apo-apellido" placeholder="Apellido(s)">
                            </div>
                            <div class="campo">
                                <label>DNI del apoderado <span class="req">*</span></label>
                                <input type="text" id="apo-dni" maxlength="8" placeholder="12345678">
                            </div>
                            <div class="campo">
                                <label>Teléfono del apoderado <span class="req">*</span></label>
                                <input type="tel" id="apo-telefono" maxlength="9" placeholder="987654321">
                            </div>
                            <div class="campo">
                                <label>Parentesco <span class="req">*</span></label>
                                <select id="apo-parentesco">
                                    <option value="">Selecciona...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                        <button class="btn btn-primario" onclick="siguientePaso(2)">
                            Continuar <i class="ti ti-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- SECCIÓN 2: Contacto -->
                <div id="reg-sec-2" style="display:none;">
                    <div class="grid-2" style="gap:14px;">
                        <div class="campo">
                            <label>Teléfono <span class="req">*</span></label>
                            <input type="tel" id="reg-telefono" maxlength="9" placeholder="987654321">
                            <span class="ayuda">9 dígitos — este número recibirá los recordatorios de WhatsApp</span>
                        </div>
                        <div class="campo">
                            <label>Correo electrónico <span class="req">*</span></label>
                            <input type="email" id="reg-correo" placeholder="correo@ejemplo.com">
                        </div>
                        <div class="campo" style="grid-column:1/-1;">
                            <label>Dirección <span class="req">*</span></label>
                            <input type="text" id="reg-direccion" placeholder="Av. Principal 123, Urbanización...">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:20px;">
                        <button class="btn btn-secundario" onclick="siguientePaso(1)">
                            <i class="ti ti-arrow-left"></i> Atrás
                        </button>
                        <button class="btn btn-primario" onclick="siguientePaso(3)">
                            Continuar <i class="ti ti-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- SECCIÓN 3: Contraseña -->
                <div id="reg-sec-3" style="display:none;">
                    <div class="grid-1" style="gap:14px;max-width:380px;margin:0 auto;">
                        <div class="campo">
                            <label>Contraseña <span class="req">*</span></label>
                            <input type="password" id="reg-pass1" placeholder="Mínimo 6 caracteres">
                            <span class="ayuda">Mínimo 6 caracteres</span>
                        </div>
                        <div class="campo">
                            <label>Confirmar contraseña <span class="req">*</span></label>
                            <input type="password" id="reg-pass2" placeholder="Repite la contraseña">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:20px;">
                        <button class="btn btn-secundario" onclick="siguientePaso(2)">
                            <i class="ti ti-arrow-left"></i> Atrás
                        </button>
                        <button class="btn btn-primario" id="btnRegistrar" onclick="registrarPaciente()">
                            <i class="ti ti-user-check"></i> Crear cuenta e ingresar
                        </button>
                    </div>
                </div>

            </div>
        </div>
        <div style="text-align:center;margin-top:12px;">
            <button class="btn btn-secundario" onclick="volverDni()"
                    style="background:rgba(255,255,255,0.15);color:white;border-color:rgba(255,255,255,0.3);">
                <i class="ti ti-arrow-left"></i> Cambiar DNI
            </button>
        </div>
    </div>

</div><!-- /pantalla-acceso -->

<script>
// ── Estado global ──────────────────────────────────────────────
let dniActual = '';
let pasoActual = 1;

// ── Helpers ────────────────────────────────────────────────────
function mostrarAlerta(id, msg, tipo = 'error') {
    document.getElementById(id).innerHTML =
        `<div class="alerta alerta-${tipo}"><i class="ti ti-${tipo==='error'?'alert-circle':'check'}"></i>${msg}</div>`;
}
function limpiarAlerta(id) {
    document.getElementById(id).innerHTML = '';
}
function mostrar(id)  { document.getElementById(id).style.display = ''; }
function ocultar(id)  { document.getElementById(id).style.display = 'none'; }

// ── Paso 1: Verificar DNI ──────────────────────────────────────
async function verificarDni() {
    const dni = document.getElementById('inputDni').value.trim();
    if (!/^\d{8}$/.test(dni)) {
        mostrarAlerta('alerta-dni', 'Ingresa un DNI válido de 8 dígitos');
        return;
    }
    limpiarAlerta('alerta-dni');
    dniActual = dni;

    const fd = new FormData();
    fd.append('accion', 'verificar_dni');
    fd.append('dni', dni);

    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();

        ocultar('paso-dni');

        if (data.existe && data.tiene_password) {
            // Paciente existente con contraseña → login
            document.getElementById('login-dni-mostrado').value = dni;
            document.getElementById('login-nombre-paciente').textContent =
                `Hola, ${data.nombre} — ingresa tu contraseña`;
            mostrar('paso-login');
            setTimeout(() => document.getElementById('inputPassword').focus(), 100);

        } else if (data.existe && !data.tiene_password) {
            // Paciente existente sin contraseña → crear contraseña
            mostrar('paso-crear-pass');

        } else {
            // Paciente nuevo → registro completo
            document.getElementById('reg-dni').value = dni;
            await cargarCatalogos();
            mostrar('paso-registro');
        }
    } catch {
        mostrar('paso-dni');
        mostrarAlerta('alerta-dni', 'Error de conexión. Intenta nuevamente.');
    }
}

// ── Paso 2A: Login ─────────────────────────────────────────────
async function iniciarSesion() {
    const pass = document.getElementById('inputPassword').value;
    if (!pass) { mostrarAlerta('alerta-login', 'Ingresa tu contraseña'); return; }
    limpiarAlerta('alerta-login');

    const fd = new FormData();
    fd.append('accion', 'login');
    fd.append('dni', dniActual);
    fd.append('password', pass);

    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            mostrarAlerta('alerta-login', data.mensaje || 'Contraseña incorrecta');
        }
    } catch {
        mostrarAlerta('alerta-login', 'Error de conexión. Intenta nuevamente.');
    }
}

function togglePassword() {
    const inp  = document.getElementById('inputPassword');
    const icon = document.getElementById('togglePass');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'ti ti-eye-off';
    } else {
        inp.type = 'password';
        icon.className = 'ti ti-eye';
    }
}

// ── Paso 2B: Crear contraseña (paciente existente) ─────────────
async function crearContraseña() {
    const p1 = document.getElementById('nuevaPass1').value;
    const p2 = document.getElementById('nuevaPass2').value;
    if (p1.length < 6) { mostrarAlerta('alerta-crear-pass', 'La contraseña debe tener al menos 6 caracteres'); return; }
    if (p1 !== p2)     { mostrarAlerta('alerta-crear-pass', 'Las contraseñas no coinciden'); return; }
    limpiarAlerta('alerta-crear-pass');

    const fd = new FormData();
    fd.append('accion', 'crear_password');
    fd.append('dni', dniActual);
    fd.append('password', p1);

    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            mostrarAlerta('alerta-crear-pass', data.mensaje || 'Error al crear contraseña');
        }
    } catch {
        mostrarAlerta('alerta-crear-pass', 'Error de conexión. Intenta nuevamente.');
    }
}

// ── Cargar catálogos para registro ────────────────────────────
async function cargarCatalogos() {
    try {
        const res  = await fetch('controlador_portal.php?accion=catalogos');
        const data = await res.json();

        const poblar = (id, items, valKey, labelKey) => {
            const sel = document.getElementById(id);
            items.forEach(i => {
                const o = document.createElement('option');
                o.value = i[valKey]; o.textContent = i[labelKey];
                sel.appendChild(o);
            });
        };
        poblar('reg-sexo',        data.sexo,        'id_sexo',              'nombre_sexo');
        poblar('reg-estado-civil',data.estado_civil, 'id_estado_civil',      'nombre_estado_civil');
        poblar('reg-grado',       data.grado,        'id_grado_instruccion', 'nombre_grado_instruccion');
        poblar('apo-parentesco',  data.parentesco,   'id_tipo_familiar',     'descripcion');
    } catch {}
}

// ── Detectar menor de edad ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const fechaInput = document.getElementById('reg-fecha-nac');
    if (fechaInput) {
        fechaInput.addEventListener('change', () => {
            const nac  = new Date(fechaInput.value);
            const hoy  = new Date();
            let edad   = hoy.getFullYear() - nac.getFullYear();
            const m    = hoy.getMonth() - nac.getMonth();
            if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
            document.getElementById('seccion-apoderado').style.display =
                edad < 18 ? 'block' : 'none';
        });
    }
});

// ── Previsualizar foto ─────────────────────────────────────────
function previsualizarFoto(input) {
    if (input.files && input.files[0]) {
        if (input.files[0].size > 2 * 1024 * 1024) {
            alert('La imagen no puede superar 2MB');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => document.getElementById('preview-foto').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Navegación entre pasos de registro ────────────────────────
function siguientePaso(num) {
    if (num > pasoActual && !validarPasoActual()) return;
    limpiarAlerta('alerta-registro');

    // Ocultar sección actual
    document.getElementById(`reg-sec-${pasoActual}`).style.display = 'none';
    // Marcar paso completado
    document.getElementById(`paso-vis-${pasoActual}`).className = 'paso completado';
    document.getElementById(`paso-vis-${pasoActual}`).querySelector('.paso-num').textContent = '✓';

    pasoActual = num;
    document.getElementById(`reg-sec-${pasoActual}`).style.display = '';
    document.getElementById(`paso-vis-${pasoActual}`).className = 'paso activo';
}

function validarPasoActual() {
    if (pasoActual === 1) {
        const campos = ['reg-nombre','reg-apellido','reg-fecha-nac','reg-sexo','reg-estado-civil','reg-grado','reg-ocupacion'];
        for (const id of campos) {
            if (!document.getElementById(id).value.trim()) {
                mostrarAlerta('alerta-registro', 'Completa todos los campos obligatorios');
                document.getElementById(id).focus();
                return false;
            }
        }
        // Validar apoderado si menor
        const secApo = document.getElementById('seccion-apoderado');
        if (secApo.style.display !== 'none') {
            const camposApo = ['apo-nombre','apo-apellido','apo-dni','apo-telefono','apo-parentesco'];
            for (const id of camposApo) {
                if (!document.getElementById(id).value.trim()) {
                    mostrarAlerta('alerta-registro', 'Completa los datos del apoderado');
                    document.getElementById(id).focus();
                    return false;
                }
            }
        }
    }
    if (pasoActual === 2) {
        const tel = document.getElementById('reg-telefono').value.trim();
        const cor = document.getElementById('reg-correo').value.trim();
        const dir = document.getElementById('reg-direccion').value.trim();
        if (!tel || !cor || !dir) {
            mostrarAlerta('alerta-registro', 'Completa todos los campos de contacto');
            return false;
        }
        if (!/^\d{9}$/.test(tel)) {
            mostrarAlerta('alerta-registro', 'El teléfono debe tener 9 dígitos');
            return false;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cor)) {
            mostrarAlerta('alerta-registro', 'Ingresa un correo electrónico válido');
            return false;
        }
    }
    return true;
}

// ── Registrar paciente nuevo ───────────────────────────────────
async function registrarPaciente() {
    const p1 = document.getElementById('reg-pass1').value;
    const p2 = document.getElementById('reg-pass2').value;
    if (p1.length < 6) { mostrarAlerta('alerta-registro', 'La contraseña debe tener al menos 6 caracteres'); return; }
    if (p1 !== p2)     { mostrarAlerta('alerta-registro', 'Las contraseñas no coinciden'); return; }
    limpiarAlerta('alerta-registro');

    const btn = document.getElementById('btnRegistrar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Registrando...';

    const fd = new FormData();
    fd.append('accion',        'registrar_paciente');
    fd.append('dni',           document.getElementById('reg-dni').value);
    fd.append('nombre',        document.getElementById('reg-nombre').value.trim());
    fd.append('apellido',      document.getElementById('reg-apellido').value.trim());
    fd.append('fecha_nac',     document.getElementById('reg-fecha-nac').value);
    fd.append('id_sexo',       document.getElementById('reg-sexo').value);
    fd.append('id_estado_civil',document.getElementById('reg-estado-civil').value);
    fd.append('id_grado',      document.getElementById('reg-grado').value);
    fd.append('ocupacion',     document.getElementById('reg-ocupacion').value.trim());
    fd.append('telefono',      document.getElementById('reg-telefono').value.trim());
    fd.append('correo',        document.getElementById('reg-correo').value.trim());
    fd.append('direccion',     document.getElementById('reg-direccion').value.trim());
    fd.append('password',      p1);

    // Foto
    const fotoFile = document.getElementById('reg-foto').files[0];
    if (fotoFile) fd.append('foto', fotoFile);

    // Apoderado (si menor)
    const secApo = document.getElementById('seccion-apoderado');
    if (secApo.style.display !== 'none') {
        fd.append('apo_nombre',     document.getElementById('apo-nombre').value.trim());
        fd.append('apo_apellido',   document.getElementById('apo-apellido').value.trim());
        fd.append('apo_dni',        document.getElementById('apo-dni').value.trim());
        fd.append('apo_telefono',   document.getElementById('apo-telefono').value.trim());
        fd.append('apo_parentesco', document.getElementById('apo-parentesco').value);
    }

    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            mostrarAlerta('alerta-registro', data.mensaje || 'Error al registrarse');
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-user-check"></i> Crear cuenta e ingresar';
        }
    } catch {
        mostrarAlerta('alerta-registro', 'Error de conexión. Intenta nuevamente.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-user-check"></i> Crear cuenta e ingresar';
    }
}

// ── Volver a DNI ───────────────────────────────────────────────
function volverDni() {
    ['paso-login','paso-crear-pass','paso-registro'].forEach(ocultar);
    limpiarAlerta('alerta-dni');
    mostrar('paso-dni');
    document.getElementById('inputDni').value = '';
    dniActual = '';
    pasoActual = 1;
}

// Enter en DNI
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('inputDni')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') verificarDni();
    });
});
</script>
</body>
</html>