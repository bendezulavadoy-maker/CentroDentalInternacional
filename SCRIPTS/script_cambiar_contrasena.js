(function () {
    const form      = document.getElementById('formCambio');
    if (!form) return;
    const actual    = document.getElementById('claveActual');
    const nueva     = document.getElementById('claveNueva');
    const confirmar = document.getElementById('claveConfirmar');
    const btn       = document.getElementById('btnGuardar');
    const msgError  = document.getElementById('mensajeError');
    const msgOk     = document.getElementById('mensajeOk');

    const MINIMO = 8;
    const reglas = {
        largo:    () => Array.from(nueva.value).length >= MINIMO,
        mezcla:   () => /\p{L}/u.test(nueva.value) && /\d/.test(nueva.value),
        distinta: () => nueva.value !== '' && nueva.value !== actual.value,
        coincide: () => nueva.value !== '' && nueva.value === confirmar.value,
    };

    function evaluar() {
        let todas = actual.value !== '';
        document.querySelectorAll('#reglasClave li').forEach(li => {
            const cumple = reglas[li.dataset.regla]();
            li.classList.toggle('cumple', cumple);
            if (!cumple) todas = false;
        });
        btn.disabled = !todas;
    }
    [actual, nueva, confirmar].forEach(i => i.addEventListener('input', () => { msgError.hidden = true; evaluar(); }));

    document.querySelectorAll('.ver-clave').forEach(b => b.addEventListener('click', () => {
        const campo = document.getElementById(b.dataset.para);
        const ver = campo.type === 'password';
        campo.type = ver ? 'text' : 'password';
        b.textContent = ver ? 'Ocultar' : 'Mostrar';
    }));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (btn.disabled) return;
        btn.disabled = true; msgError.hidden = true; msgOk.hidden = true;
        try {
            const datos = new FormData(form);
            datos.append('accion', 'cambiar');
            const r = await fetch('../CONTROLADORES/controlador_contrasena.php', { method: 'POST', body: datos });
            const d = await r.json();
            if (d.success) {
                msgOk.textContent = d.mensaje || 'Contraseña cambiada.';
                msgOk.hidden = false;
                form.querySelectorAll('input').forEach(i => { i.disabled = true; });
                setTimeout(() => { window.location.href = d.redirigir || 'vista_panel_admin.php'; }, 1200);
                return;
            }
            msgError.textContent = d.mensaje || 'No se pudo cambiar la contraseña.';
            msgError.hidden = false;
        } catch (err) {
            msgError.textContent = 'No se pudo conectar con el servidor. Inténtalo de nuevo.';
            msgError.hidden = false;
        }
        evaluar();
    });

    evaluar();
})();
