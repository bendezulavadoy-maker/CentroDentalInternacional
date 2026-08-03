document.addEventListener("DOMContentLoaded", () => {
    const contenido = document.getElementById("contenido");

    const btnInicio = document.getElementById("btnInicio");
    const btnRegistrar = document.getElementById("btnRegistrar");
    const btnPacientes = document.getElementById("btnPacientes");
    const btnCerrarSesion = document.getElementById("btnCerrarSesion");

    // --- INICIO ---
    btnInicio.addEventListener("click", () => {
        contenido.innerHTML = `
            <div class="fondo-inicio">
                <img src="../IMAGENES/otros/centro_dental_internacional.jpeg" 
                     alt="Logo Clínica" class="logo-fondo">
            </div>
        `;
    });

    // --- REGISTRAR PACIENTE ---
    btnRegistrar.addEventListener("click", async () => {
        try {
            // Cargar formulario via fetch
            const respuesta = await fetch("../VISTA/registrar_paciente.php");
            const html = await respuesta.text();

            contenido.innerHTML = html;

            // --- Cargar CSS solo una vez ---
            if (!document.querySelector('link[href="../ESTILOS/style_registrar_paciente.css"]')) {
                const link = document.createElement("link");
                link.rel = "stylesheet";
                link.href = "../ESTILOS/style_registrar_paciente.css";
                document.head.appendChild(link);
            }

            // --- Configurar formulario (submit y previsualización) ---
            const form = contenido.querySelector("#formPaciente");
            const inputFoto = contenido.querySelector("#foto");
            const preview = contenido.querySelector("#previewFoto");

            if (form) {
                // Previsualización de foto
                if (inputFoto && preview) {
                    inputFoto.addEventListener("change", (event) => {
                        const archivo = event.target.files[0];
                        if (archivo) {
                            const lector = new FileReader();
                            lector.onload = (e) => (preview.src = e.target.result);
                            lector.readAsDataURL(archivo);
                        }
                    });
                }

                // Submit del formulario
                form.addEventListener("submit", async (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);

                    try {
                        const respuesta = await fetch("../CONTROLADOR/controlador_registrar_paciente.php", {
                            method: "POST",
                            body: formData
                        });

                        const data = await respuesta.json();

                        if (data.estado === "ok") {
                            alert("✅ " + data.mensaje);
                            form.reset();
                            if (preview) preview.src = "../IMAGENES/perfiles_pacientes/default_user.png";
                        } else {
                            alert("⚠️ " + data.mensaje);
                        }
                    } catch (error) {
                        console.error("Error al registrar:", error);
                        alert("❌ Error al conectar con el servidor.");
                    }
                });
            }

        } catch (error) {
            contenido.innerHTML = "<p>Error al cargar la vista del formulario.</p>";
            console.error("Error al cargar registrar_paciente.php:", error);
        }
    });

    // --- PACIENTES ---
    btnPacientes.addEventListener("click", () => {
        contenido.innerHTML = `
            <h2>Lista de Pacientes</h2>
            <p>Aquí irá la tabla...</p>
        `;
    });

    // --- CERRAR SESIÓN ---
    btnCerrarSesion.addEventListener("click", () => {
        window.location.href = "logout.php";
    });
});
