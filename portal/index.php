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

    <!-- ══ PASO 1: DOCUMENTO ══ -->
    <div class="caja-acceso" id="paso-dni">
        <div class="caja-acceso-header">
            <h1>Dental Internacional</h1>
            <p>Portal de autogestión para pacientes</p>
        </div>
        <div class="caja-acceso-body">
            <div id="alerta-dni"></div>
            <div class="campo" style="margin-bottom:12px;">
                <label>Tipo de documento</label>
                <select id="selectTipoDoc">
                    <option value="1">DNI</option>
                </select>
            </div>
            <div class="campo" style="margin-bottom:16px;">
                <label>Número de documento <span class="req">*</span></label>
                <input type="text" id="inputDni" maxlength="8" placeholder="12345678" autocomplete="off"
                       style="font-size:18px;text-align:center;letter-spacing:4px;">
                <span class="ayuda" id="ayudaDoc">8 dígitos — sin puntos ni espacios</span>
            </div>
            <button class="btn btn-primario btn-full" onclick="verificarDni()">
                <i class="ti ti-arrow-right"></i> Continuar
            </button>
            <div class="separador">o</div>
            <p style="text-align:center;font-size:12px;color:var(--texto-sec);">
                ¿Eres nuevo paciente? Ingresa tu documento y te guiaremos para registrarte.
            </p>
        </div>
    </div>

    <!-- ══ PASO 2A: LOGIN (paciente existente con contraseña) ══ -->
    <div class="caja-acceso" id="paso-login" style="display:none;">
        <div class="caja-acceso-header">
            <h1>Bienvenido de vuelta</h1>
            <p id="login-nombre-paciente">Ingresa tu contraseña para continuar</p>
        </div>
        <div class="caja-acceso-body">
            <div id="alerta-login"></div>
            <div class="campo" style="margin-bottom:14px;">
                <label>Documento</label>
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
                    <i class="ti ti-arrow-left"></i> Cambiar documento
                </button>
            </div>
        </div>
    </div>

    <!-- ══ PASO 2B: CREAR CONTRASEÑA (paciente existente sin contraseña) ══ -->
    <div class="caja-acceso" id="paso-crear-pass" style="display:none;">
        <div class="caja-acceso-header">
            <h1>Crea tu acceso</h1>
            <p>Es tu primera vez en el portal. Crea una contraseña.</p>
        </div>
        <div class="caja-acceso-body">
            <div id="alerta-crear-pass"></div>
            <p style="font-size:13px;color:var(--texto-sec);margin-bottom:16px;
                      padding:10px 12px;background:#eef2fb;border-radius:6px;">
                Encontramos tu historial como paciente. Para confirmar que eres tú, ingresa el teléfono que dejaste en la clínica y crea una contraseña.
            </p>
            <div class="campo" style="margin-bottom:12px;">
                <label>Teléfono registrado en la clínica <span class="req">*</span></label>
                <input type="tel" id="nuevaTelefono" maxlength="9" placeholder="987654321" autocomplete="off">
                <span class="ayuda">9 dígitos — el mismo número que dejaste en la clínica</span>
            </div>
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
                    <i class="ti ti-arrow-left"></i> Cambiar documento
                </button>
            </div>
        </div>
    </div>

    <!-- ══ PASO 3: REGISTRO COMPLETO (paciente nuevo) ══ -->
    <div id="paso-registro" style="display:none;width:100%;max-width:720px;">
        <div class="tarjeta">
            <div class="caja-acceso-header" style="background:var(--azul);padding:20px 28px;">
                <h1 style="color:white;font-size:18px;">Nuevo paciente — Registro</h1>
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
                            <label id="reg-doc-label">Documento <span class="req">*</span></label>
                            <input type="text" id="reg-dni" maxlength="20" readonly
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
                            Paciente menor de edad — datos del apoderado
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
                                <label>Tipo de documento del apoderado</label>
                                <select id="apo-tipo-doc">
                                    <option value="1">DNI</option>
                                </select>
                            </div>
                            <div class="campo">
                                <label>Número de documento del apoderado <span class="req">*</span></label>
                                <input type="text" id="apo-dni" maxlength="8" placeholder="12345678" autocomplete="off">
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
                <i class="ti ti-arrow-left"></i> Cambiar documento
            </button>
        </div>
    </div>

</div><!-- /pantalla-acceso -->

<script>
// ── Estado global ──────────────────────────────────────────────
let tipoDocActual = '1';
let dniActual = '';
let pasoActual = 1;
let tiposDocumento = [];
let catalogosCargados = false;

// Regla por defecto (DNI) mientras no cargue el catálogo
const REGLA_DNI = { id_tipo_documento: 1, codigo: '1', abreviatura: 'DNI', longitud_min: 8, longitud_max: 8, solo_numeros: 1 };

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

// ── Documento de identidad: reglas según el tipo (catálogo tipo_documento) ──
function reglaDocumento(idTipo) {
    return tiposDocumento.find(t => String(t.id_tipo_documento) === String(idTipo)) || REGLA_DNI;
}

// Deja solo los caracteres permitidos y respeta la longitud máxima
function filtrarNumeroDocumento(valor, regla) {
    const soloNum = Number(regla.solo_numeros) === 1;
    const v = soloNum ? valor.replace(/[^0-9]/g, '') : valor.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    return v.substring(0, Number(regla.longitud_max));
}

// Devuelve el texto del error, o '' si el número es válido
function evaluarDocumento(valor, regla) {
    const abrev = regla.abreviatura;
    const soloNum = Number(regla.solo_numeros) === 1;
    const min = Number(regla.longitud_min);
    const max = Number(regla.longitud_max);
    if (valor === '') return `Ingresa tu número de ${abrev}`;
    if (soloNum && !/^[0-9]+$/.test(valor)) return `El ${abrev} solo admite dígitos`;
    if (!soloNum && !/^[A-Za-z0-9]+$/.test(valor)) return `El ${abrev} solo admite letras y números`;
    if (valor.length < min || valor.length > max) {
        const unidad = soloNum ? 'dígitos' : 'caracteres';
        return min === max
            ? `El ${abrev} debe tener exactamente ${min} ${unidad}`
            : `El ${abrev} debe tener entre ${min} y ${max} ${unidad}`;
    }
    if (regla.codigo === '1' && /^(\d)\1{7}$/.test(valor)) return 'El DNI ingresado no es válido';
    return '';
}

// Ajusta longitud máxima, placeholder y ayuda según el tipo elegido
function aplicarReglasDocumento(selectId, inputId, ayudaId) {
    const sel = document.getElementById(selectId);
    const inp = document.getElementById(inputId);
    if (!sel || !inp) return;
    const regla = reglaDocumento(sel.value);
    const soloNum = Number(regla.solo_numeros) === 1;
    const min = Number(regla.longitud_min);
    const max = Number(regla.longitud_max);
    inp.maxLength = max;
    inp.placeholder = regla.codigo === '1' ? '12345678' : `Número de ${regla.abreviatura}`;
    inp.value = filtrarNumeroDocumento(inp.value, regla);
    const ayuda = ayudaId ? document.getElementById(ayudaId) : null;
    if (ayuda) {
        ayuda.textContent = soloNum
            ? (min === max ? `${min} dígitos — sin puntos ni espacios` : `Entre ${min} y ${max} dígitos`)
            : `Entre ${min} y ${max} caracteres (letras y números)`;
    }
}

// Pasa el texto a mayúsculas sin mover el cursor
function aMayusculas(input) {
    const ini = input.selectionStart, fin = input.selectionEnd;
    input.value = input.value.toUpperCase();
    try { input.setSelectionRange(ini, fin); } catch (e) {}
}

// ── Catálogos (se cargan una sola vez al abrir la página) ─────
async function cargarCatalogos() {
    if (catalogosCargados) return;
    try {
        const res  = await fetch('controlador_portal.php?accion=catalogos');
        const data = await res.json();

        // Conserva la primera opción ("Selecciona...") y vuelve a llenar el resto
        const poblar = (id, items, valKey, labelKey) => {
            const sel = document.getElementById(id);
            if (!sel || !Array.isArray(items)) return;
            while (sel.options.length > 1) sel.remove(1);
            items.forEach(i => sel.appendChild(new Option(i[labelKey], i[valKey])));
        };
        poblar('reg-sexo',        data.sexo,         'id_sexo',              'nombre_sexo');
        poblar('reg-estado-civil',data.estado_civil, 'id_estado_civil',      'nombre_estado_civil');
        poblar('reg-grado',       data.grado,        'id_grado_instruccion', 'nombre_grado_instruccion');
        poblar('apo-parentesco',  data.parentesco,   'id_tipo_familiar',     'descripcion');

        // Tipos de documento: selector del paso 1 y del apoderado
        if (Array.isArray(data.tipos_documento) && data.tipos_documento.length) {
            tiposDocumento = data.tipos_documento;
            ['selectTipoDoc', 'apo-tipo-doc'].forEach(id => {
                const sel = document.getElementById(id);
                sel.innerHTML = '';
                tiposDocumento.forEach(t => {
                    const esDni = t.codigo === '1';
                    sel.appendChild(new Option(t.abreviatura, t.id_tipo_documento, esDni, esDni));
                });
            });
            aplicarReglasDocumento('selectTipoDoc', 'inputDni', 'ayudaDoc');
            aplicarReglasDocumento('apo-tipo-doc', 'apo-dni', null);
        }
        catalogosCargados = true;
    } catch {}
}

// ── Paso 1: Verificar documento ───────────────────────────────
async function verificarDni() {
    const sel    = document.getElementById('selectTipoDoc');
    const regla  = reglaDocumento(sel.value);
    const numero = document.getElementById('inputDni').value.trim().toUpperCase();

    const mensaje = evaluarDocumento(numero, regla);
    if (mensaje) { mostrarAlerta('alerta-dni', mensaje); return; }
    limpiarAlerta('alerta-dni');
    tipoDocActual = sel.value;
    dniActual = numero;

    const fd = new FormData();
    fd.append('accion', 'verificar_dni');
    fd.append('id_tipo_documento', tipoDocActual);
    fd.append('dni', numero);

    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();

        if (data.success === false) {
            mostrarAlerta('alerta-dni', data.mensaje || 'No se pudo verificar el documento');
            return;
        }

        ocultar('paso-dni');

        if (data.existe && data.tiene_password) {
            // Paciente existente con contraseña → login
            document.getElementById('login-dni-mostrado').value = `${regla.abreviatura} ${numero}`;
            document.getElementById('login-nombre-paciente').textContent = 'Ingresa tu contraseña para continuar';
            mostrar('paso-login');
            setTimeout(() => document.getElementById('inputPassword').focus(), 100);

        } else if (data.existe && !data.tiene_password) {
            // Paciente existente sin contraseña → crear contraseña
            mostrar('paso-crear-pass');
            setTimeout(() => document.getElementById('nuevaTelefono').focus(), 100);

        } else {
            // Paciente nuevo → registro completo
            document.getElementById('reg-dni').value = numero;
            document.getElementById('reg-doc-label').innerHTML = `${regla.abreviatura} <span class="req">*</span>`;
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
    fd.append('id_tipo_documento', tipoDocActual);
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
    const tel = document.getElementById('nuevaTelefono').value.trim();
    const p1  = document.getElementById('nuevaPass1').value;
    const p2  = document.getElementById('nuevaPass2').value;
    if (!/^\d{9}$/.test(tel)) { mostrarAlerta('alerta-crear-pass', 'Ingresa el teléfono de 9 dígitos que registraste en la clínica'); return; }
    if (p1.length < 6) { mostrarAlerta('alerta-crear-pass', 'La contraseña debe tener al menos 6 caracteres'); return; }
    if (p1 !== p2)     { mostrarAlerta('alerta-crear-pass', 'Las contraseñas no coinciden'); return; }
    limpiarAlerta('alerta-crear-pass');

    const fd = new FormData();
    fd.append('accion', 'crear_password');
    fd.append('id_tipo_documento', tipoDocActual);
    fd.append('dni', dniActual);
    fd.append('telefono', tel);
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

// ── Eventos al cargar la página ────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cargarCatalogos();

    // Documento del paso 1
    const selTipo = document.getElementById('selectTipoDoc');
    const inpDni  = document.getElementById('inputDni');
    selTipo.addEventListener('change', () => aplicarReglasDocumento('selectTipoDoc', 'inputDni', 'ayudaDoc'));
    inpDni.addEventListener('input', () => {
        inpDni.value = filtrarNumeroDocumento(inpDni.value, reglaDocumento(selTipo.value));
    });
    inpDni.addEventListener('keydown', e => { if (e.key === 'Enter') verificarDni(); });

    // Documento del apoderado
    const selApo = document.getElementById('apo-tipo-doc');
    const inpApo = document.getElementById('apo-dni');
    selApo.addEventListener('change', () => aplicarReglasDocumento('apo-tipo-doc', 'apo-dni', null));
    inpApo.addEventListener('input', () => {
        inpApo.value = filtrarNumeroDocumento(inpApo.value, reglaDocumento(selApo.value));
    });

    // Teléfonos: solo dígitos
    ['nuevaTelefono', 'reg-telefono', 'apo-telefono'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', () => { el.value = el.value.replace(/\D/g, '').substring(0, 9); });
    });

    // Nombres siempre en mayúsculas
    ['reg-nombre', 'reg-apellido', 'apo-nombre', 'apo-apellido'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', () => aMayusculas(el));
    });

    // Detectar menor de edad
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
            const reglaApo = reglaDocumento(document.getElementById('apo-tipo-doc').value);
            const msgApo = evaluarDocumento(document.getElementById('apo-dni').value.trim().toUpperCase(), reglaApo);
            if (msgApo) {
                mostrarAlerta('alerta-registro', 'Apoderado: ' + msgApo);
                document.getElementById('apo-dni').focus();
                return false;
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
    fd.append('id_tipo_documento', tipoDocActual);
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
        fd.append('apo_nombre',            document.getElementById('apo-nombre').value.trim());
        fd.append('apo_apellido',          document.getElementById('apo-apellido').value.trim());
        fd.append('apo_id_tipo_documento', document.getElementById('apo-tipo-doc').value);
        fd.append('apo_dni',               document.getElementById('apo-dni').value.trim());
        fd.append('apo_telefono',          document.getElementById('apo-telefono').value.trim());
        fd.append('apo_parentesco',        document.getElementById('apo-parentesco').value);
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

// ── Volver al paso del documento ───────────────────────────────
function volverDni() {
    ['paso-login','paso-crear-pass','paso-registro'].forEach(ocultar);
    limpiarAlerta('alerta-dni');
    mostrar('paso-dni');
    document.getElementById('inputDni').value = '';
    document.getElementById('nuevaTelefono').value = '';
    document.getElementById('nuevaPass1').value = '';
    document.getElementById('nuevaPass2').value = '';
    document.getElementById('inputPassword').value = '';
    dniActual = '';
    pasoActual = 1;
    aplicarReglasDocumento('selectTipoDoc', 'inputDni', 'ayudaDoc');
}
</script>
</body>
</html>