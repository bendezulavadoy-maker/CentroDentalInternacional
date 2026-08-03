<?php
// Verificar sesión del portal
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['portal']['id_paciente'])) {
    header('Location: index.php');
    exit;
}
$nombrePaciente = $_SESSION['portal']['nombre'] . ' ' . $_SESSION['portal']['apellido'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Portal — Dental Internacional</title>
    <link rel="stylesheet" href="style_portal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body>

<!-- HEADER -->
<header class="portal-header">
    <div class="logo">
        <i class="ti ti-tooth" style="font-size:24px;"></i>
        Dental Internacional
    </div>
    <nav class="portal-nav">
        <a href="#" class="activo" onclick="mostrarSeccion('mis-citas')">
            <i class="ti ti-calendar"></i> Mis citas
        </a>
        <a href="#" onclick="mostrarSeccion('nueva-cita')">
            <i class="ti ti-calendar-plus"></i> Nueva cita
        </a>
        <button onclick="cerrarSesion()">
            <i class="ti ti-logout"></i> Salir
        </button>
    </nav>
</header>

<div class="portal-main">

    <!-- BIENVENIDA -->
    <div class="bienvenida" id="card-bienvenida">
        <img id="foto-paciente"
             src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='8' r='4' fill='rgba(255,255,255,0.5)'/%3E%3Cpath d='M4 20c0-4 3.6-7 8-7s8 3 8 7' fill='rgba(255,255,255,0.5)'/%3E%3C/svg%3E"
             alt="foto">
        <div>
            <h2>Hola, <?= htmlspecialchars($nombrePaciente) ?> 👋</h2>
            <p id="resumen-citas">Cargando tus citas...</p>
        </div>
    </div>

    <!-- ══ SECCIÓN: MIS CITAS ══ -->
    <div id="sec-mis-citas">
        <div id="alerta-citas"></div>

        <!-- Filtro rápido -->
        <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
            <button class="btn btn-secundario filtro-cita activo" data-filtro="proximas"
                    onclick="filtrarCitas('proximas',this)">
                Próximas
            </button>
            <button class="btn btn-secundario filtro-cita" data-filtro="pasadas"
                    onclick="filtrarCitas('pasadas',this)">
                Historial
            </button>
            <button class="btn btn-secundario filtro-cita" data-filtro="todas"
                    onclick="filtrarCitas('todas',this)">
                Todas
            </button>
        </div>

        <div id="lista-citas" class="lista-citas">
            <div style="text-align:center;padding:30px;">
                <span class="spinner"></span>
                <p style="color:var(--texto-ter);margin-top:8px;font-size:13px;">Cargando citas...</p>
            </div>
        </div>
    </div>

    <!-- ══ SECCIÓN: NUEVA CITA ══ -->
    <div id="sec-nueva-cita" style="display:none;">
        <div class="tarjeta">
            <div class="tarjeta-header">
                <i class="ti ti-calendar-plus" style="font-size:16px;color:var(--azul);"></i>
                <h3>Solicitar nueva cita</h3>
            </div>
            <div class="tarjeta-body">
                <div id="alerta-nueva-cita"></div>
                <div class="grid-2" style="gap:14px;">
                    <div class="campo">
                        <label>Tipo de atención</label>
                        <select id="nc-tipo-atencion" onchange="actualizarDuracion()">
                            <option value="">Selecciona el tipo...</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Duración estimada</label>
                        <select id="nc-duracion">
                            <option value="30">30 minutos</option>
                            <option value="45">45 minutos</option>
                            <option value="60" selected>60 minutos</option>
                            <option value="90">90 minutos</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Doctor / Dentista <span class="req">*</span></label>
                        <select id="nc-doctor" onchange="cargarSlotsPortal()">
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Sede de atención <span class="req">*</span></label>
                        <select id="nc-sede" onchange="cargarSlotsPortal()">
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Fecha <span class="req">*</span></label>
                        <input type="date" id="nc-fecha" onchange="cargarSlotsPortal()">
                    </div>
                    <div class="campo">
                        <label>Hora disponible <span class="req">*</span></label>
                        <div id="nc-slots"
                             style="padding:10px;background:var(--gris-bg);border-radius:6px;
                                    border:1px solid var(--gris-borde);min-height:42px;">
                            <small style="color:var(--texto-ter);font-style:italic;">
                                Selecciona doctor, sede y fecha
                            </small>
                        </div>
                        <input type="hidden" id="nc-hora">
                    </div>
                    <div class="campo" style="grid-column:1/-1;">
                        <label>Motivo de la cita <span class="req">*</span></label>
                        <textarea id="nc-motivo" rows="3"
                                  placeholder="Describe brevemente el motivo de tu visita..."></textarea>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                    <button class="btn btn-secundario" onclick="mostrarSeccion('mis-citas')">Cancelar</button>
                    <button class="btn btn-primario" onclick="agendarCita()">
                        <i class="ti ti-calendar-check"></i> Confirmar cita
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ MODAL: REPROGRAMAR CITA ══ -->
    <div id="modal-reprogramar"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
                z-index:1000;align-items:center;justify-content:center;padding:16px;">
        <div style="background:white;border-radius:12px;width:100%;max-width:520px;overflow:hidden;
                    box-shadow:0 12px 40px rgba(0,0,0,0.2);">
            <div style="background:var(--azul);padding:16px 20px;display:flex;align-items:center;
                        justify-content:space-between;">
                <h3 style="color:white;font-size:15px;font-weight:600;">Reprogramar cita</h3>
                <button onclick="cerrarModalReprogramar()"
                        style="background:none;border:none;color:rgba(255,255,255,0.8);
                               font-size:20px;cursor:pointer;">✕</button>
            </div>
            <div style="padding:20px;">
                <div id="alerta-reprogramar"></div>
                <input type="hidden" id="rep-id-cita">
                <input type="hidden" id="rep-id-doctor">
                <input type="hidden" id="rep-id-sede">
                <input type="hidden" id="rep-duracion">
                <div style="background:var(--gris-bg);border-radius:6px;padding:10px 14px;
                             margin-bottom:16px;font-size:13px;color:var(--texto-sec);">
                    <strong id="rep-info-cita"></strong>
                </div>
                <div class="grid-2" style="gap:12px;">
                    <div class="campo">
                        <label>Nueva fecha <span class="req">*</span></label>
                        <input type="date" id="rep-fecha" onchange="cargarSlotsReprogramar()">
                    </div>
                    <div class="campo">
                        <label>Nueva hora <span class="req">*</span></label>
                        <div id="rep-slots"
                             style="padding:8px;background:var(--gris-bg);border-radius:6px;
                                    border:1px solid var(--gris-borde);min-height:38px;">
                            <small style="color:var(--texto-ter);font-style:italic;">Selecciona una fecha</small>
                        </div>
                        <input type="hidden" id="rep-hora">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                    <button class="btn btn-secundario" onclick="cerrarModalReprogramar()">Cancelar</button>
                    <button class="btn btn-primario" onclick="confirmarReprogramar()">
                        <i class="ti ti-calendar-stats"></i> Confirmar nueva fecha
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /portal-main -->

<script>
// ── Estado ─────────────────────────────────────────────────────
let todasLasCitas = [];
let filtroActivo  = 'proximas';

// ── Init ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    // Fecha mínima = hoy
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('nc-fecha').min = hoy;
    document.getElementById('rep-fecha').min = hoy;

    await Promise.all([cargarMisCitas(), cargarSelectores()]);
});

// ── Navegación ─────────────────────────────────────────────────
function mostrarSeccion(sec) {
    document.getElementById('sec-mis-citas').style.display   = sec === 'mis-citas'   ? '' : 'none';
    document.getElementById('sec-nueva-cita').style.display  = sec === 'nueva-cita'  ? '' : 'none';
    document.querySelectorAll('.portal-nav a').forEach(a => a.classList.remove('activo'));
    if (sec === 'mis-citas')  document.querySelectorAll('.portal-nav a')[0].classList.add('activo');
    if (sec === 'nueva-cita') document.querySelectorAll('.portal-nav a')[1].classList.add('activo');
}

// ── Cargar mis citas ───────────────────────────────────────────
async function cargarMisCitas() {
    try {
        const res  = await fetch('controlador_portal.php?accion=mis_citas');
        todasLasCitas = await res.json();
        filtrarCitas(filtroActivo);
        actualizarResumen();
    } catch {
        document.getElementById('lista-citas').innerHTML =
            '<div class="alerta alerta-error"><i class="ti ti-alert-circle"></i>Error al cargar tus citas.</div>';
    }
}

function actualizarResumen() {
    const proximas = todasLasCitas.filter(c =>
        c.fecha >= new Date().toISOString().split('T')[0] && [1,2].includes(parseInt(c.id_estado_cita))
    ).length;
    document.getElementById('resumen-citas').textContent =
        proximas > 0
            ? `Tienes ${proximas} cita${proximas>1?'s':''} próxima${proximas>1?'s':''} pendiente${proximas>1?'s':''}`
            : 'No tienes citas próximas';
}

function filtrarCitas(tipo, btn) {
    filtroActivo = tipo;
    if (btn) {
        document.querySelectorAll('.filtro-cita').forEach(b => b.classList.remove('activo'));
        btn.classList.add('activo');
    }
    const hoy = new Date().toISOString().split('T')[0];
    let lista;
    if (tipo === 'proximas') lista = todasLasCitas.filter(c => c.fecha >= hoy && [1,2].includes(parseInt(c.id_estado_cita)));
    else if (tipo === 'pasadas') lista = todasLasCitas.filter(c => c.fecha < hoy || [3,4,5].includes(parseInt(c.id_estado_cita)));
    else lista = todasLasCitas;

    renderCitas(lista, tipo === 'proximas');
}

function renderCitas(lista, mostrarAcciones) {
    const cont = document.getElementById('lista-citas');
    if (!lista.length) {
        cont.innerHTML = '<div class="alerta alerta-info"><i class="ti ti-info-circle"></i>No hay citas en esta sección.</div>';
        return;
    }
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    cont.innerHTML = lista.map(c => {
        const f    = new Date(c.fecha + 'T00:00:00');
        const dia  = f.getDate();
        const mes  = meses[f.getMonth()];
        const hora = c.hora ? c.hora.substring(0,5) : '';
        const badge = badgeEstado(c.estado);
        const esCancelable   = [1,2].includes(parseInt(c.id_estado_cita)) && c.fecha >= new Date().toISOString().split('T')[0];
        const esReprogramable = esCancelable;

        return `
        <div class="cita-item ${c.fecha < new Date().toISOString().split('T')[0] ? 'pasada' : ''}">
            <div class="cita-fecha-hora">
                <div class="dia">${dia}</div>
                <div class="mes">${mes}</div>
                <div class="hora">${hora}</div>
            </div>
            <div class="cita-info">
                <div class="doctor">Dr(a). ${c.nombre_doctor}</div>
                <div class="detalle">
                    ${c.tipo_atencion || 'Consulta'} &nbsp;·&nbsp; 📍 ${c.nombre_sede || ''}
                    ${c.motivo ? `<br><span style="color:var(--texto-ter);">${c.motivo}</span>` : ''}
                </div>
            </div>
            <div class="cita-acciones">
                ${badge}
                ${esReprogramable ? `<button class="btn btn-secundario" style="font-size:11px;padding:5px 10px;"
                    onclick="abrirReprogramar(${c.id_cita},'${c.nombre_doctor}','${c.fecha}','${hora}',${c.duracion_minutos||30},${c.id_sede_atencion||0},${c.id_doctor||0})">
                    <i class="ti ti-calendar-stats"></i> Reprogramar
                </button>` : ''}
                ${esCancelable ? `<button class="btn btn-peligro" style="font-size:11px;padding:5px 10px;"
                    onclick="cancelarCita(${c.id_cita})">
                    <i class="ti ti-x"></i> Cancelar
                </button>` : ''}
            </div>
        </div>`;
    }).join('');
}

function badgeEstado(estado) {
    const map = {
        'programada': 'badge-programada', 'confirmada': 'badge-confirmada',
        'completada': 'badge-completada', 'cancelada':  'badge-cancelada',
        'no asistió':'badge-no-asistio'
    };
    const cls = map[(estado||'').toLowerCase()] || '';
    return `<span class="badge ${cls}">${estado}</span>`;
}

// ── Cancelar cita ──────────────────────────────────────────────
async function cancelarCita(id) {
    if (!confirm('¿Estás seguro de que deseas cancelar esta cita?')) return;
    const fd = new FormData();
    fd.append('accion','cancelar_cita');
    fd.append('id_cita', id);
    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            mostrarAlertaGlobal('✅ Cita cancelada correctamente', 'exito');
            await cargarMisCitas();
        } else {
            mostrarAlertaGlobal('❌ ' + (data.mensaje || 'Error al cancelar'), 'error');
        }
    } catch {
        mostrarAlertaGlobal('❌ Error de conexión', 'error');
    }
}

// ── Cargar selectores ──────────────────────────────────────────
async function cargarSelectores() {
    try {
        const [docs, sedes, tipos] = await Promise.all([
            fetch('controlador_portal.php?accion=doctores').then(r=>r.json()),
            fetch('controlador_portal.php?accion=sedes').then(r=>r.json()),
            fetch('controlador_portal.php?accion=tipos_atencion').then(r=>r.json()),
        ]);

        const poblar = (id, items, val, label, placeholder) => {
            const s = document.getElementById(id);
            s.innerHTML = `<option value="">${placeholder}</option>`;
            items.forEach(i => {
                const o = document.createElement('option');
                o.value = i[val]; o.textContent = i[label];
                s.appendChild(o);
            });
        };
        poblar('nc-doctor',       docs,  'id_usuario',       'nombre',    'Selecciona el doctor...');
        poblar('nc-sede',         sedes, 'id_sede_atencion', 'nombre_sede','Selecciona la sede...');
        poblar('nc-tipo-atencion',tipos, 'id_tipo_atencion', 'nombre',    'Tipo de atención (opcional)');

        // Guardar duración por tipo
        window.duracionesPorTipo = {};
        tipos.forEach(t => { window.duracionesPorTipo[t.id_tipo_atencion] = t.duracion_minutos; });
    } catch {}
}

function actualizarDuracion() {
    const idTipo = document.getElementById('nc-tipo-atencion').value;
    if (idTipo && window.duracionesPorTipo?.[idTipo]) {
        document.getElementById('nc-duracion').value = window.duracionesPorTipo[idTipo];
    }
    cargarSlotsPortal();
}

// ── Cargar slots (nueva cita) ──────────────────────────────────
async function cargarSlotsPortal() {
    const doc    = document.getElementById('nc-doctor').value;
    const sede   = document.getElementById('nc-sede').value;
    const fecha  = document.getElementById('nc-fecha').value;
    const dur    = document.getElementById('nc-duracion').value;
    const cont   = document.getElementById('nc-slots');
    document.getElementById('nc-hora').value = '';

    if (!doc || !sede || !fecha) {
        cont.innerHTML = '<small style="color:var(--texto-ter);font-style:italic;">Selecciona doctor, sede y fecha</small>';
        return;
    }
    cont.innerHTML = '<span class="spinner"></span>';

    try {
        const res  = await fetch(`controlador_portal.php?accion=slots_portal&id_doctor=${doc}&id_sede=${sede}&fecha=${fecha}&duracion=${dur}`);
        const data = await res.json();
        if (!data.disponible || !data.slots?.length) {
            cont.innerHTML = `<small style="color:var(--rojo);">${data.mensaje || 'No hay horarios disponibles ese día'}</small>`;
            return;
        }
        cont.innerHTML = '<div style="display:flex;flex-wrap:wrap;gap:6px;">' +
            data.slots.map(s => `
                <button type="button" class="btn btn-secundario slot-btn"
                        data-hora="${s}"
                        style="padding:5px 12px;font-size:12px;"
                        onclick="seleccionarSlot(this,'${s}','nc-hora','nc-slots')">
                    ${s}
                </button>`).join('') + '</div>';
    } catch {
        cont.innerHTML = '<small style="color:var(--rojo);">Error al cargar horarios</small>';
    }
}

function seleccionarSlot(btn, hora, hiddenId, contId) {
    document.querySelectorAll(`#${contId} .slot-btn`).forEach(b => {
        b.classList.remove('btn-primario');
        b.classList.add('btn-secundario');
    });
    btn.classList.remove('btn-secundario');
    btn.classList.add('btn-primario');
    document.getElementById(hiddenId).value = hora;
}

// ── Agendar nueva cita ─────────────────────────────────────────
async function agendarCita() {
    const hora = document.getElementById('nc-hora').value;
    if (!document.getElementById('nc-doctor').value ||
        !document.getElementById('nc-sede').value   ||
        !document.getElementById('nc-fecha').value  || !hora) {
        mostrarAlertaSeccion('alerta-nueva-cita', 'Completa todos los campos obligatorios');
        return;
    }
    if (!document.getElementById('nc-motivo').value.trim()) {
        mostrarAlertaSeccion('alerta-nueva-cita', 'Describe el motivo de tu cita');
        return;
    }

    const fd = new FormData();
    fd.append('accion',           'agendar_cita');
    fd.append('id_doctor',        document.getElementById('nc-doctor').value);
    fd.append('id_sede',          document.getElementById('nc-sede').value);
    fd.append('fecha',            document.getElementById('nc-fecha').value);
    fd.append('hora',             hora);
    fd.append('duracion',         document.getElementById('nc-duracion').value);
    fd.append('id_tipo_atencion', document.getElementById('nc-tipo-atencion').value);
    fd.append('motivo',           document.getElementById('nc-motivo').value.trim());

    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            mostrarAlertaGlobal('✅ Cita agendada correctamente', 'exito');
            mostrarSeccion('mis-citas');
            await cargarMisCitas();
        } else {
            mostrarAlertaSeccion('alerta-nueva-cita', '❌ ' + (data.mensaje || 'Error al agendar'));
        }
    } catch {
        mostrarAlertaSeccion('alerta-nueva-cita', '❌ Error de conexión');
    }
}

// ── Reprogramar ────────────────────────────────────────────────
function abrirReprogramar(idCita, doctor, fecha, hora, duracion, idSede, idDoctor) {
    document.getElementById('rep-id-cita').value   = idCita;
    document.getElementById('rep-id-doctor').value = idDoctor;
    document.getElementById('rep-id-sede').value   = idSede;
    document.getElementById('rep-duracion').value  = duracion;
    document.getElementById('rep-info-cita').textContent =
        `Cita actual: Dr(a). ${doctor} — ${fecha} a las ${hora}`;
    document.getElementById('rep-fecha').value = '';
    document.getElementById('rep-hora').value  = '';
    document.getElementById('rep-slots').innerHTML =
        '<small style="color:var(--texto-ter);font-style:italic;">Selecciona una fecha</small>';
    document.getElementById('alerta-reprogramar').innerHTML = '';
    document.getElementById('modal-reprogramar').style.display = 'flex';
}

function cerrarModalReprogramar() {
    document.getElementById('modal-reprogramar').style.display = 'none';
}

async function cargarSlotsReprogramar() {
    const fecha  = document.getElementById('rep-fecha').value;
    const idDoc  = document.getElementById('rep-id-doctor').value;
    const idSede = document.getElementById('rep-id-sede').value;
    const dur    = document.getElementById('rep-duracion').value || 30;
    const cont   = document.getElementById('rep-slots');
    document.getElementById('rep-hora').value = '';

    if (!fecha || !idDoc || !idSede) {
        cont.innerHTML = '<small style="color:var(--texto-ter);">Selecciona una fecha</small>';
        return;
    }
    cont.innerHTML = '<span class="spinner"></span>';

    try {
        const res  = await fetch(`controlador_portal.php?accion=slots_portal&id_doctor=${idDoc}&id_sede=${idSede}&fecha=${fecha}&duracion=${dur}`);
        const data = await res.json();
        if (!data.disponible || !data.slots?.length) {
            cont.innerHTML = `<small style="color:var(--rojo);">${data.mensaje || 'No hay horarios disponibles'}</small>`;
            return;
        }
        cont.innerHTML = '<div style="display:flex;flex-wrap:wrap;gap:6px;">' +
            data.slots.map(s => `
                <button type="button" class="btn btn-secundario slot-btn"
                        data-hora="${s}" style="padding:4px 10px;font-size:12px;"
                        onclick="seleccionarSlot(this,'${s}','rep-hora','rep-slots')">
                    ${s}
                </button>`).join('') + '</div>';
    } catch {
        cont.innerHTML = '<small style="color:var(--rojo);">Error al cargar horarios</small>';
    }
}

async function confirmarReprogramar() {
    const hora = document.getElementById('rep-hora').value;
    const fecha= document.getElementById('rep-fecha').value;
    if (!fecha || !hora) {
        mostrarAlertaSeccion('alerta-reprogramar', 'Selecciona una fecha y hora');
        return;
    }
    const fd = new FormData();
    fd.append('accion',      'reprogramar_cita');
    fd.append('id_cita',     document.getElementById('rep-id-cita').value);
    fd.append('nueva_fecha', fecha);
    fd.append('nueva_hora',  hora);
    fd.append('id_doctor',   document.getElementById('rep-id-doctor').value);
    fd.append('id_sede',     document.getElementById('rep-id-sede').value);
    fd.append('duracion',    document.getElementById('rep-duracion').value);

    try {
        const res  = await fetch('controlador_portal.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            cerrarModalReprogramar();
            mostrarAlertaGlobal('✅ Cita reprogramada correctamente', 'exito');
            await cargarMisCitas();
        } else {
            mostrarAlertaSeccion('alerta-reprogramar', '❌ ' + (data.mensaje || 'Error al reprogramar'));
        }
    } catch {
        mostrarAlertaSeccion('alerta-reprogramar', '❌ Error de conexión');
    }
}

// ── Cerrar sesión ──────────────────────────────────────────────
async function cerrarSesion() {
    await fetch('controlador_portal.php?accion=logout');
    window.location.href = 'index.php';
}

// ── Helpers alertas ────────────────────────────────────────────
function mostrarAlertaGlobal(msg, tipo) {
    const div = document.getElementById('alerta-citas');
    div.innerHTML = `<div class="alerta alerta-${tipo}">${msg}</div>`;
    setTimeout(() => { div.innerHTML = ''; }, 4000);
}
function mostrarAlertaSeccion(id, msg) {
    document.getElementById(id).innerHTML =
        `<div class="alerta alerta-error"><i class="ti ti-alert-circle"></i>${msg}</div>`;
}
</script>
</body>
</html>