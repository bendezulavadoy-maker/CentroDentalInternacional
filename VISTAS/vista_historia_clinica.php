<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<link rel="stylesheet" href="../ESTILOS/style_historia_clinica.css">

<div class="contenedor-historia-clinica">
    <!-- 🔍 Buscador de paciente -->
    <div class="encabezado-historia">
        <h2>Historia Clínica</h2>
        <div class="buscador-paciente">
            <input type="text"
                id="inputBuscarPaciente"
                placeholder="🔍 Buscar paciente por DNI o nombre..."
                class="input-busqueda-paciente">
            <div id="resultadosBusqueda" class="resultados-busqueda" style="display:none;"></div>
        </div>
        <button id="btnVolverPacientes" class="btn-secundario">⬅ Volver a Pacientes</button>
    </div>

    <!-- 📄 Contenido de la historia clínica -->
    <div id="contenidoHistoriaClinica" class="contenido-historia" style="display:none;">

        <!-- 🧍 Información del Paciente -->
        <section class="seccion-info-paciente">
            <div class="tarjeta-paciente">
                <!-- Foto del paciente -->
                <div class="columna-foto">
                    <div class="contenedor-foto-paciente">
                        <img id="fotoPaciente" src="../IMAGENES/perfiles_pacientes/default.png" alt="Foto del paciente">
                    </div>

                    <!-- ✅ NUEVO: Botón para generar PDF de Historia Clínica -->
                    <button id="btnGenerarPDF" class="btn-generar-pdf" title="Generar Historia Clínica en PDF">
                        📄 Generar PDF
                    </button>

                    <!-- Mensaje de cumpleaños -->
                    <div id="contenedorMensajeCumpleanos" class="contenedor-mensaje-cumpleanos"></div>
                </div>

                <!-- Datos del paciente -->
                <div class="columna-datos">
                    <h3 class="nombre-paciente" id="nombrePaciente">---</h3>

                    <div class="grid-datos">
                        <div class="dato-item">
                            <span class="dato-label">🆔 DNI:</span>
                            <span class="dato-valor" id="dniPaciente">---</span>
                        </div>

                        <div class="dato-item">
                            <span class="dato-label">🎂 Fecha de Nacimiento:</span>
                            <span class="dato-valor" id="fechaNacimientoPaciente">---</span>
                        </div>

                        <div class="dato-item">
                            <span class="dato-label">📅 Edad:</span>
                            <span class="dato-valor" id="edadPaciente">---</span>
                        </div>

                        <div class="dato-item">
                            <span class="dato-label">⚧ Sexo:</span>
                            <span class="dato-valor" id="sexoPaciente">---</span>
                        </div>

                        <div class="dato-item">
                            <span class="dato-label">📱 Teléfono:</span>
                            <span class="dato-valor" id="telefonoPaciente">---</span>
                        </div>

                        <div class="dato-item">
                            <span class="dato-label">📧 Correo:</span>
                            <span class="dato-valor" id="correoPaciente">---</span>
                        </div>
                    </div>

                    <!-- Información del apoderado (si existe) -->
                    <div id="infoApoderado" class="info-apoderado-seccion" style="display:none;">
                        <h4>👨‍👩‍👧 Información del Apoderado</h4>
                        <div class="grid-datos-apoderado">
                            <div class="dato-item">
                                <span class="dato-label">👤 Nombre:</span>
                                <span class="dato-valor" id="nombreApoderado">---</span>
                            </div>
                            <div class="dato-item">
                                <span class="dato-label">📱 Teléfono:</span>
                                <span class="dato-valor" id="telefonoApoderado">---</span>
                            </div>
                            <div class="dato-item">
                                <span class="dato-label">🔗 Parentesco:</span>
                                <span class="dato-valor" id="tipoFamiliarApoderado">---</span>
                            </div>
                        </div>
                    </div>

                    <!-- Alergias a medicamentos -->
                    <div class="info-alergias">
                        <h4>⚠️ Alergias a Medicamentos</h4>
                        <div id="listaAlergias" class="lista-alergias">
                            <span class="sin-datos">No se han registrado alergias</span>
                        </div>
                        <button id="btnGestionarAlergias" class="btn-accion-pequeno">Gestionar Alergias</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- 📑 Pestañas de módulos -->
        <section class="seccion-modulos">
            <div class="pestanas-modulos">
            <button class="pestana activa" data-modulo="citas">Citas</button>
<button class="pestana" data-modulo="odontograma">Odontograma</button>
<button class="pestana" data-modulo="atenciones">Atenciones</button>
<button class="pestana" data-modulo="documentos">Documentos</button>
            </div>

            <!-- Contenedor dinámico de módulos -->
            <div id="contenedorModulo" class="contenedor-modulo">
                <p class="mensaje-seleccionar">Seleccione una pestaña para ver el contenido</p>
            </div>
        </section>
    </div>

    <!-- 💬 Mensaje cuando no hay paciente seleccionado -->
    <div id="mensajeSinPaciente" class="mensaje-sin-paciente">
        <div class="icono-mensaje">🔍</div>
        <p>Busque un paciente para ver su historia clínica</p>
        <p class="texto-pequeno">Use el buscador superior para encontrar un paciente por DNI o nombre</p>
    </div>
</div>

<!-- 🧑‍⚕️ Modal para gestionar alergias -->
<div id="modalAlergias" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3> Gestionar Alergias a Medicamentos</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalAlergias">✖</button>
        </div>
        <div class="modal-cuerpo">
            <!-- Campo de búsqueda con resultados debajo -->
            <div class="contenedor-busqueda-medicamento">
                <div class="buscar-medicamento">
                    <input type="text"
                        id="inputBuscarMedicamento"
                        placeholder="Buscar o agregar medicamento..."
                        class="input-medicamento"
                        autocomplete="off">
                    <button id="btnAgregarMedicamento" class="btn-agregar">➕ Agregar</button>
                </div>
                <!-- Resultados de búsqueda de medicamentos (AHORA DEBAJO DEL CAMPO) -->
                <div id="resultadosMedicamentos" class="resultados-medicamentos" style="display:none;"></div>
            </div>

            <!-- Lista de alergias agregadas -->
            <div id="listaAlergiasModal" class="lista-alergias-modal">
                <!-- Se llenará dinámicamente -->
            </div>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" id="btnCancelarAlergias">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarAlergias">💾 Guardar Cambios</button>
        </div>
    </div>
</div>

<script src="../SCRIPTS/script_historia_clinica.js"></script>