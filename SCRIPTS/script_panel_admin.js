document.addEventListener('DOMContentLoaded', () => {
    const enlaces    = document.querySelectorAll('aside a');
    const contenedor = document.getElementById('contenido-principal');
    const permisos   = window.permisosUsuario || [];

    const modulos = {
        pacientes:        { archivo: '../VISTAS/vista_pacientes.php',        script: '../SCRIPTS/script_pacientes.js',        init: 'iniciarModuloPacientes'       },
        personal:         { archivo: '../VISTAS/vista_personal.php',         script: '../SCRIPTS/script_personal.js',         init: 'iniciarModuloPersonal'        },
        citas:            { archivo: '../VISTAS/vista_citas.php',            script: '../SCRIPTS/script_citas.js',            init: 'iniciarModuloCitas'           },
        mi_agenda:        { archivo: '../VISTAS/vista_mi_agenda.php',        script: '../SCRIPTS/script_mi_agenda.js',        init: 'iniciarModuloMiAgenda'        },
        historia_clinica: { archivo: '../VISTAS/vista_historia_clinica.php', script: '../SCRIPTS/script_historia_clinica.js', init: 'iniciarModuloHistoriaClinica' },
        configuracion:    { archivo: '../VISTAS/vista_configuracion.php',    script: '../SCRIPTS/script_configuracion.js',    init: 'iniciarModuloConfiguracion'   },
        cobros:           { archivo: '../VISTAS/vista_cobros.php',           script: '../SCRIPTS/script_cobros.js',           init: 'iniciarModuloCobros'          },
    };

    let moduloEnCarga = false;

    function cargarModuloEnPanel(vista) {
        const modulo = modulos[vista];
        if (!modulo) return;
        if (moduloEnCarga) return; // evitar carga duplicada simultánea
        moduloEnCarga = true;

        // Limpiar flags de inicialización
        window._moduloPacientesIniciado  = false;
        window._moduloPersonalIniciado   = false;
        window._moduloCitasIniciado      = false;
        window._moduloHistoriaIniciado   = false;
        window._moduloAgendaIniciado     = false;
        window._moduloConfigIniciado     = false;
        window._moduloCobrosIniciado      = false;

        fetch(modulo.archivo)
            .then(res => res.text())
            .then(html => {
                contenedor.innerHTML = html;

                const oldScript = document.querySelector(`script[src="${modulo.script}"]`);
                if (oldScript) oldScript.remove();

                const script  = document.createElement('script');
                script.src    = modulo.script;
                script.onload = () => {
                    moduloEnCarga = false;
                    if (typeof window[modulo.init] === 'function') {
                        window[modulo.init]();
                    } else {
                        console.error(`Función ${modulo.init} no encontrada`);
                    }
                };
                document.body.appendChild(script);
            })
            .catch(err => {
                moduloEnCarga = false;
                console.error('Error cargando módulo:', err);
                contenedor.innerHTML = `
                    <section class="bienvenida">
                        <h2>Error</h2>
                        <p>No se pudo cargar el módulo. Intenta nuevamente.</p>
                    </section>`;
            });
    }

    // Eventos del menú lateral
    enlaces.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const vista = e.currentTarget.getAttribute('data-vista');

            // Verificar permiso
            if (!permisos.includes(vista)) {
                contenedor.innerHTML = `
                    <section class="bienvenida">
                        <h2>Acceso denegado</h2>
                        <p>No tienes permiso para acceder a este módulo.</p>
                    </section>`;
                return;
            }

            cargarModuloEnPanel(vista);
        });
    });

    // Función global para que otros módulos puedan navegar entre secciones
    window.navegarAModulo = function(vista, datos) {
        if (datos) {
            Object.entries(datos).forEach(([k, v]) => {
                sessionStorage.setItem(k, JSON.stringify(v));
            });
        }

        // Si el módulo está en el menú visible, simular clic
        const enlace = document.querySelector(`aside a[data-vista="${vista}"]`);
        if (enlace && permisos.includes(vista)) {
            enlace.click();
        } else {
            // Si no está en el menú (ej: historia_clinica oculta), cargarlo directo
            cargarModuloEnPanel(vista);
        }
    };
});