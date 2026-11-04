// =====================================================
// IMPORTACION MASIVA DE PACIENTES (Excel / CSV)
// Paso 1: elegir archivo  ->  Paso 2: revision (no guarda nada)  ->  Paso 3: resultado
// El servidor decide todo: aqui solo se muestra y se envia.
// =====================================================
function iniciarImportacionPacientes() {
    const $ = id => document.getElementById(id);
    const btnAbrir = $('btnImportarPacientes');
    const modal = $('modalImportarPacientes');
    if (!btnAbrir || !modal || btnAbrir.dataset.impIniciado === '1') return;
    btnAbrir.dataset.impIniciado = '1';

    const CTRL = '../CONTROLADORES/controlador_importacion_pacientes.php';
    const MAX_BYTES = 5 * 1024 * 1024;
    let token = null;
    let ultimosErrores = [];
    let ocupado = false;

    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function formatearFecha(f) {
        if (!f) return '';
        const d = new Date(String(f).replace(' ', 'T'));
        return isNaN(d) ? '' : d.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
    }
    function mensaje(id, texto) {
        const el = $(id);
        if (!el) return;
        el.textContent = texto || '';
        el.style.display = texto ? 'block' : 'none';
    }
    async function pedir(url, opciones) {
        let res;
        try { res = await fetch(url, opciones); }
        catch (e) { return { success: false, mensaje: 'No se pudo conectar con el servidor' }; }
        let texto = '';
        try { texto = await res.text(); } catch (e) { /* sin cuerpo */ }
        try { return JSON.parse(texto); }
        catch (e) {
            // Se muestra lo que realmente respondio el servidor (un error de PHP, una pagina de error...)
            const detalle = texto.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
            return { success: false, mensaje: 'Respuesta inválida del servidor (HTTP ' + res.status + ')' + (detalle ? ': ' + detalle : '') };
        }
    }
    function paso(n) {
        ['impPaso1', 'impPaso2', 'impPaso3'].forEach((id, i) => { $(id).style.display = (i + 1 === n) ? 'block' : 'none'; });
    }
    function tarjeta(valor, texto, clase) {
        return '<div class="imp-tarjeta ' + clase + '"><span class="imp-numero">' + esc(valor) + '</span><span class="imp-texto">' + esc(texto) + '</span></div>';
    }

    // ---------- abrir / cerrar ----------
    function abrir() {
        reiniciar();
        modal.style.display = 'flex';
        cargarHistorial();
    }
    function cerrar() {
        if (ocupado) return;
        modal.style.display = 'none';
        reiniciar();
    }
    function reiniciar() {
        token = null; ultimosErrores = [];
        $('impArchivo').value = '';
        $('impRevisar').disabled = true;
        mensaje('impMensaje', ''); mensaje('impMensaje2', '');
        $('impConfirmarRevertir').style.display = 'none';
        paso(1);
    }

    // ---------- paso 1: elegir y revisar ----------
    $('impArchivo').addEventListener('change', () => {
        const f = $('impArchivo').files[0];
        mensaje('impMensaje', '');
        $('impRevisar').disabled = true;
        if (!f) return;
        const ext = (f.name.split('.').pop() || '').toLowerCase();
        if (ext !== 'xlsx' && ext !== 'csv') {
            mensaje('impMensaje', ext === 'xls' ? 'El formato .xls (Excel antiguo) no se admite. Guárdelo como .xlsx o .csv.' : 'Elija un archivo .xlsx o .csv');
            return;
        }
        if (f.size > MAX_BYTES) { mensaje('impMensaje', 'El archivo es demasiado grande (máximo 5 MB)'); return; }
        if (f.size === 0) { mensaje('impMensaje', 'El archivo está vacío'); return; }
        $('impRevisar').disabled = false;
    });

    async function revisar() {
        const f = $('impArchivo').files[0];
        if (!f || ocupado) return;
        ocupado = true;
        $('impRevisar').disabled = true; $('impRevisar').textContent = 'Revisando...';
        mensaje('impMensaje', '');
        const fd = new FormData();
        fd.append('accion', 'analizar');
        fd.append('archivo', f);
        const r = await pedir(CTRL, { method: 'POST', body: fd });
        ocupado = false;
        $('impRevisar').textContent = 'Revisar archivo';
        $('impRevisar').disabled = false;
        if (!r.success) { mensaje('impMensaje', r.mensaje || 'No se pudo revisar el archivo'); return; }

        token = r.token;
        ultimosErrores = r.errores || [];
        $('impNombreArchivo').textContent = r.nombre;
        $('impResumen').innerHTML =
            tarjeta(r.total, 'filas en el archivo', 'imp-neutra') +
            tarjeta(r.validas, 'listas para importar', 'imp-ok') +
            tarjeta(r.existentes, 'ya existen (se omiten)', 'imp-info') +
            tarjeta(r.con_error, 'con problemas', r.con_error ? 'imp-error' : 'imp-neutra');
        const ign = $('impIgnoradas');
        if (r.columnas_ignoradas && r.columnas_ignoradas.length) {
            ign.textContent = 'Columnas que no se usarán: ' + r.columnas_ignoradas.join(', ');
            ign.style.display = 'block';
        } else ign.style.display = 'none';
        $('impErrores').innerHTML = tablaErrores(r.errores || [], r.errores_total || 0, 'Estas filas NO se importarán. Corríjalas en el Excel y vuelva a subir el archivo (las que ya se importaron se omitirán).');
        $('impConfirmar').disabled = r.validas === 0;
        $('impConfirmar').textContent = r.validas === 0 ? 'No hay filas para importar' : 'Importar ' + r.validas + (r.validas === 1 ? ' paciente' : ' pacientes');
        mensaje('impMensaje2', '');
        paso(2);
    }

    function tablaErrores(errores, total, aviso) {
        if (!total) return '';
        let html = '<div class="imp-bloque-errores"><p class="imp-aviso-errores">' + esc(aviso) + '</p>' +
                   '<div class="imp-tabla-caja"><table class="imp-tabla"><thead><tr><th>Fila</th><th>Paciente</th><th>Problemas</th></tr></thead><tbody>';
        errores.forEach(e => {
            html += '<tr><td>' + esc(e.fila) + '</td><td>' + esc(e.paciente || '') + (e.documento ? '<br><small>' + esc(e.documento) + '</small>' : '') + '</td>' +
                    '<td><ul>' + (e.problemas || []).map(p => '<li>' + esc(p) + '</li>').join('') + '</ul></td></tr>';
        });
        html += '</tbody></table></div>';
        if (total > errores.length) html += '<p class="imp-ayuda">Se muestran las primeras ' + errores.length + ' de ' + total + ' filas con problemas. Descargue la lista completa.</p>';
        html += '<button type="button" class="imp-btn imp-btn-sec imp-btn-chico" data-accion="descargar-errores"><i class="ti ti-download"></i> Descargar la lista de problemas (CSV)</button></div>';
        return html;
    }

    // CSV de problemas (los textos que empiezan con = + - @ se neutralizan para que Excel no los ejecute como formula)
    function descargarErrores() {
        const celda = v => {
            let t = String(v == null ? '' : v);
            if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
            return '"' + t.replace(/"/g, '""') + '"';
        };
        const lineas = [['fila', 'documento', 'paciente', 'problemas'].map(celda).join(';')];
        ultimosErrores.forEach(e => lineas.push([e.fila, e.documento, e.paciente, (e.problemas || []).join(' | ')].map(celda).join(';')));
        const blob = new Blob(['\uFEFF' + lineas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'problemas_importacion.csv';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    // ---------- paso 2 -> 3: importar ----------
    async function importar() {
        if (!token || ocupado) return;
        ocupado = true;
        $('impConfirmar').disabled = true; $('impConfirmar').textContent = 'Importando...';
        $('impVolver').disabled = true;
        const fd = new FormData();
        fd.append('accion', 'importar');
        fd.append('token', token);
        const r = await pedir(CTRL, { method: 'POST', body: fd });
        ocupado = false;
        $('impVolver').disabled = false;
        if (!r.success) {
            $('impConfirmar').disabled = false; $('impConfirmar').textContent = 'Reintentar importación';
            mensaje('impMensaje2', r.mensaje || 'No se pudo importar');
            return;
        }
        token = null;
        ultimosErrores = r.errores || [];
        $('impResumenFinal').innerHTML =
            tarjeta(r.importados, r.importados === 1 ? 'paciente importado' : 'pacientes importados', r.importados ? 'imp-ok' : 'imp-neutra') +
            tarjeta(r.existentes, 'ya existían (omitidos)', 'imp-info') +
            tarjeta(r.con_error, 'con problemas (no importados)', r.con_error ? 'imp-error' : 'imp-neutra');
        $('impErroresFinal').innerHTML = tablaErrores(r.errores || [], r.errores_total || 0, 'Estas filas no se importaron. Corríjalas y vuelva a subir el archivo.');
        paso(3);
        cargarHistorial();
        if (typeof window.recargarListaPacientes === 'function') window.recargarListaPacientes();
    }

    // ---------- historial y deshacer ----------
    async function cargarHistorial() {
        const r = await pedir(CTRL + '?accion=historial');
        const cont = $('impHistorial');
        if (!r.success) { cont.innerHTML = '<p class="imp-ayuda">No se pudo cargar el historial</p>'; return; }
        if (!r.cargas.length) { cont.innerHTML = '<p class="imp-ayuda">Todavía no se ha importado ningún archivo.</p>'; return; }
        let html = '<div class="imp-tabla-caja"><table class="imp-tabla"><thead><tr><th>Fecha</th><th>Archivo</th><th>Importados</th><th>Omitidos</th><th>Con problemas</th><th>Estado</th><th></th></tr></thead><tbody>';
        r.cargas.forEach(c => {
            const vigentes = Number(c.vigentes) || 0;
            const estado = c.estado === 'revertida' ? 'Deshecha' : (c.estado === 'parcial' ? 'Deshecha en parte' : 'Vigente');
            const puede = c.estado !== 'revertida' && vigentes > 0;
            html += '<tr><td>' + esc(formatearFecha(c.fecha_carga)) + '<br><small>' + esc(c.usuario || '') + '</small></td><td>' + esc(c.nombre_archivo) + '</td>' +
                    '<td>' + esc(c.importados) + '</td><td>' + esc(c.omitidos) + '</td><td>' + esc(c.con_error) + '</td><td>' + esc(estado) + '</td>' +
                    '<td>' + (puede ? '<button type="button" class="imp-btn imp-btn-peligro imp-btn-chico" data-accion="pedir-revertir" data-id="' + Number(c.id_carga) + '" data-n="' + vigentes + '">Deshacer</button>' : '') + '</td></tr>';
        });
        cont.innerHTML = html + '</tbody></table></div>';
    }

    function pedirRevertir(id, n) {
        const caja = $('impConfirmarRevertir');
        caja.innerHTML = '<p><strong>¿Deshacer esta carga?</strong> Se eliminarán hasta ' + esc(n) + ' paciente(s) importados. ' +
                         'Los que <u>ya tengan citas, pagos, planes, documentos o historia clínica con datos</u> se conservarán.</p>' +
                         '<div class="imp-acciones"><button type="button" class="imp-btn imp-btn-sec" data-accion="cancelar-revertir">Cancelar</button>' +
                         '<button type="button" class="imp-btn imp-btn-peligro" data-accion="confirmar-revertir" data-id="' + Number(id) + '">Sí, deshacer</button></div>';
        caja.style.display = 'block';
        caja.scrollIntoView && caja.scrollIntoView({ block: 'nearest' });
    }
    async function confirmarRevertir(id, boton) {
        if (ocupado) return;
        ocupado = true; boton.disabled = true; boton.textContent = 'Deshaciendo...';
        const fd = new FormData();
        fd.append('accion', 'revertir');
        fd.append('id_carga', String(id));
        const r = await pedir(CTRL, { method: 'POST', body: fd });
        ocupado = false;
        const caja = $('impConfirmarRevertir');
        if (r.success) {
            caja.innerHTML = '<p>Se deshizo la carga: <strong>' + esc(r.deshechos) + '</strong> paciente(s) eliminados' +
                             (r.conservados ? ' y <strong>' + esc(r.conservados) + '</strong> conservados porque ya tienen actividad.' : '.') + '</p>';
            caja.style.display = 'block';
            cargarHistorial();
            if (typeof window.recargarListaPacientes === 'function') window.recargarListaPacientes();
        } else {
            caja.innerHTML = '<p class="imp-mensaje" style="display:block;">' + esc(r.mensaje || 'No se pudo deshacer') + '</p>';
        }
    }

    // ---------- eventos ----------
    btnAbrir.addEventListener('click', abrir);
    $('impCerrar').addEventListener('click', cerrar);
    $('impListo').addEventListener('click', cerrar);
    $('impRevisar').addEventListener('click', revisar);
    $('impConfirmar').addEventListener('click', importar);
    $('impVolver').addEventListener('click', () => { token = null; $('impArchivo').value = ''; $('impRevisar').disabled = true; paso(1); });
    $('impOtra').addEventListener('click', () => { reiniciar(); });
    modal.addEventListener('click', e => {
        if (e.target === modal) { cerrar(); return; }
        const el = e.target.closest && e.target.closest('[data-accion]');
        if (!el || !modal.contains(el)) return;
        const a = el.dataset.accion;
        if (a === 'descargar-errores') descargarErrores();
        else if (a === 'pedir-revertir') pedirRevertir(el.dataset.id, el.dataset.n);
        else if (a === 'cancelar-revertir') $('impConfirmarRevertir').style.display = 'none';
        else if (a === 'confirmar-revertir') confirmarRevertir(el.dataset.id, el);
    });
    // Un solo escucha de teclado, aunque el modulo se abra varias veces
    if (window.__impTeclado) document.removeEventListener('keydown', window.__impTeclado);
    window.__impTeclado = e => {
        if (e.key === 'Escape' && modal.style.display === 'flex' && document.body.contains(modal)) cerrar();
    };
    document.addEventListener('keydown', window.__impTeclado);
}