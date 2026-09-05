document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById('formLogin');
    const numeroUsuario = document.getElementById('numeroUsuario');
    const codigoCompleto = document.getElementById('codigoUsuarioCompleto');

    if (!form || !numeroUsuario || !codigoCompleto) return;

    form.addEventListener('submit', () => {
        codigoCompleto.value = 'DENTINT' + numeroUsuario.value.trim();
    });
});