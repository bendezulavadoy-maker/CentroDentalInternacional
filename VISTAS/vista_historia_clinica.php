<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<link rel="stylesheet" href="../ESTILOS/style_historia_clinica.css">

<div class="contenedor-historia-clinica">
    <div class="layout-historia">

        <!-- 🧍 Sidebar del paciente + checklist de Historia Clínica (siempre visible) -->
        <aside id="sidebarPaciente" class="sidebar-paciente">
                <button id="btnToggleSidebarPaciente" class="btn-toggle-sidebar-paciente" title="Ocultar/mostrar panel del paciente">
                    <span class="icono-toggle">‹</span>
                </button>

                <div class="sidebar-paciente-contenido">
                    <div class="avatar-paciente-sidebar">
                        <img id="fotoPaciente" src="../IMAGENES/perfiles_pacientes/default.png" alt="Foto del paciente">
                    </div>
                    <h3 class="nombre-paciente" id="nombrePaciente">---</h3>
                    <p class="dni-paciente-sidebar">DNI <span id="dniPaciente">---</span></p>

                    <div class="datos-sidebar-paciente">
                        <div class="dato-sidebar"><span id="sexoEdadPaciente">---</span></div>
                        <div class="dato-sidebar">📱 <span id="telefonoPaciente">---</span></div>
                        <div class="dato-sidebar">✉️ <span id="correoPaciente">---</span></div>
                    </div>

                    <div id="contenedorMensajeCumpleanos" class="contenedor-mensaje-cumpleanos"></div>

                    <button id="btnGestionarAlergias" class="btn-ver-alergias">Ver alergias</button>
                    <button id="btnGenerarPDF" class="btn-generar-pdf-sidebar" title="Generar Historia Clínica en PDF">
                        📄 Generar PDF
                    </button>

                    <!-- Información del apoderado (solo si existe) -->
                    <div id="infoApoderado" class="info-apoderado-seccion" style="display:none;">
                        <h4>👨‍👩‍👧 Apoderado</h4>
                        <div class="grid-datos-apoderado">
                            <div class="dato-item">
                                <span class="dato-label">Nombre:</span>
                                <span class="dato-valor" id="nombreApoderado">---</span>
                            </div>
                            <div class="dato-item">
                                <span class="dato-label">Teléfono:</span>
                                <span class="dato-valor" id="telefonoApoderado">---</span>
                            </div>
                            <div class="dato-item">
                                <span class="dato-label">Parentesco:</span>
                                <span class="dato-valor" id="tipoFamiliarApoderado">---</span>
                            </div>
                        </div>
                    </div>

                    <p class="titulo-checklist-sidebar">Historia clínica</p>
                    <div class="checklist-sidebar">
                        <div class="check-item-sidebar completo no-clic" data-seccion="filiacion" title="Se completa al registrar al paciente">
                            <span class="check-texto">Filiación</span>
                            <span class="check-icono">✓</span>
                        </div>
                        <div class="check-item-sidebar" data-seccion="motivo_consulta">
                            <span class="check-texto">Motivo de Consulta</span>
                            <span class="check-icono">–</span>
                        </div>
                        <div class="check-item-sidebar" data-seccion="antecedentes_personales">
                            <span class="check-texto">Antec. Personales</span>
                            <span class="check-icono">–</span>
                        </div>
                        <div class="check-item-sidebar" data-seccion="antecedentes_familiares">
                            <span class="check-texto">Antec. Familiares</span>
                            <span class="check-icono">–</span>
                        </div>
                        <div class="check-item-sidebar" data-seccion="examen_general">
                            <span class="check-texto">Exámen general</span>
                            <span class="check-icono">–</span>
                        </div>
                        <div class="check-item-sidebar" data-seccion="examen_extraoral">
                            <span class="check-texto">Extraoral</span>
                            <span class="check-icono">–</span>
                        </div>
                        <div class="check-item-sidebar" data-seccion="examen_intraoral">
                            <span class="check-texto">Intraoral</span>
                            <span class="check-icono">–</span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- 📋 Columna derecha: buscador (siempre visible) + contenido -->
            <div class="columna-derecha-historia">
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

                <!-- 📑 Pestañas de módulos (oculto hasta que se cargue un paciente) -->
                <div id="contenidoHistoriaClinica" class="contenido-historia" style="display:none;">
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

<!-- 📝 Modal: Antecedentes (incluye motivo de consulta) -->
<div id="modalMotivoConsulta" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Motivo de Consulta</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalMotivoConsulta">✖</button>
        </div>
        <div class="modal-cuerpo">
            <textarea id="txtMotivoConsulta" rows="4" class="input-textarea" placeholder="Describa el motivo de la consulta..."></textarea>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-cerrar-modal="modalMotivoConsulta">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarMotivoConsulta">💾 Guardar</button>
        </div>
    </div>
</div>

<!-- 📝 Modal: Exámen Clínico General -->
<div id="modalExamenGeneral" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Exámen Clínico General</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalExamenGeneral">✖</button>
        </div>
        <div class="modal-cuerpo">
            <div class="fila-doble">
                <div>
                    <label class="label-campo">Talla (mts)</label>
                    <input type="number" step="0.01" id="inpTalla" class="input-texto">
                </div>
                <div>
                    <label class="label-campo">Peso (kg)</label>
                    <input type="number" step="0.01" id="inpPeso" class="input-texto">
                </div>
            </div>
            <div class="fila-doble">
                <div>
                    <label class="label-campo">Temperatura (°C)</label>
                    <input type="number" step="0.1" id="inpTemperatura" class="input-texto">
                </div>
                <div>
                    <label class="label-campo">Saturación (%)</label>
                    <input type="number" step="0.1" id="inpSaturacion" class="input-texto">
                </div>
            </div>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-cerrar-modal="modalExamenGeneral">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarExamenGeneral">💾 Guardar</button>
        </div>
    </div>
</div>

<!-- 📝 Modal: Antecedentes Personales -->
<div id="modalAntecedentesPersonales" class="modal-overlay">
    <div class="modal-contenido modal-ancho">
        <div class="modal-encabezado">
            <h3>Antecedentes Personales</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalAntecedentesPersonales">✖</button>
        </div>
        <div class="modal-cuerpo">
            <label class="label-campo">Antecedentes médicos</label>
            <textarea id="txtAntecedentesMedica" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Antecedentes odontológicos</label>
            <textarea id="txtAntecedentesOdontologicos" rows="2" class="input-textarea"></textarea>

            <div class="fila-opciones" style="margin-top:10px;">
                <span class="label-campo">Fuma</span>
                <label><input type="radio" name="fuma" value="si"> Sí</label>
                <label><input type="radio" name="fuma" value="no"> No</label>
                <input type="text" id="inpFumaCantidad" placeholder="Cantidad" class="input-texto input-tipo">
                <input type="text" id="inpFumaFrecuencia" placeholder="Frecuencia" class="input-texto input-tipo">
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Alcohol</span>
                <label><input type="radio" name="alcohol" value="si"> Sí</label>
                <label><input type="radio" name="alcohol" value="no"> No</label>
                <input type="text" id="inpAlcoholCantidad" placeholder="Cantidad" class="input-texto input-tipo">
                <input type="text" id="inpAlcoholFrecuencia" placeholder="Frecuencia" class="input-texto input-tipo">
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Sustancias psicoactivas</span>
                <label><input type="radio" name="sustancias_psicoactivas" value="si"> Sí</label>
                <label><input type="radio" name="sustancias_psicoactivas" value="no"> No</label>
            </div>
            <div class="fila-doble">
                <div><label class="label-campo">Especifique</label><input type="text" id="inpSustanciasEspecifique" class="input-texto"></div>
                <div><label class="label-campo">Frecuencia</label><input type="text" id="inpSustanciasFrecuencia" class="input-texto"></div>
            </div>
            <label class="label-campo">Último consumo</label>
            <input type="text" id="inpSustanciasUltimoConsumo" class="input-texto">

            <div class="fila-opciones" style="margin-top:10px;">
                <span class="label-campo">Medicamentos/estimulantes</span>
                <label><input type="radio" name="medicamentos_estimulantes" value="si"> Sí</label>
                <label><input type="radio" name="medicamentos_estimulantes" value="no"> No</label>
            </div>
            <div class="fila-doble">
                <div><label class="label-campo">Especifique</label><input type="text" id="inpMedicamentosEspecifique" class="input-texto"></div>
                <div><label class="label-campo">Frecuencia</label><input type="text" id="inpMedicamentosFrecuencia" class="input-texto"></div>
            </div>
            <label class="label-campo">Último consumo</label>
            <input type="text" id="inpMedicamentosUltimoConsumo" class="input-texto">

            <div class="fila-opciones" style="margin-top:10px;">
                <span class="label-campo">Bruxismo</span>
                <label><input type="radio" name="bruxismo" value="si"> Sí</label>
                <label><input type="radio" name="bruxismo" value="no"> No</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Respiración bucal</span>
                <label><input type="radio" name="respiracion_bucal" value="si"> Sí</label>
                <label><input type="radio" name="respiracion_bucal" value="no"> No</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Embarazo</span>
                <label><input type="radio" name="embarazo" value="si"> Sí</label>
                <label><input type="radio" name="embarazo" value="no"> No</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Lactancia</span>
                <label><input type="radio" name="lactancia" value="si"> Sí</label>
                <label><input type="radio" name="lactancia" value="no"> No</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Trastornos de coagulación</span>
                <label><input type="radio" name="trastornos_coagulacion" value="si"> Sí</label>
                <label><input type="radio" name="trastornos_coagulacion" value="no"> No</label>
            </div>

            <label class="label-campo">Hospitalizaciones previas</label>
            <textarea id="txtHospitalizacionesPrevias" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Cirugías</label>
            <textarea id="txtCirugias" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Medicamentos que consume actualmente</label>
            <textarea id="txtMedicamentosActuales" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Diagnóstico</label>
            <textarea id="txtDiagnostico" rows="3" class="input-textarea"></textarea>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-cerrar-modal="modalAntecedentesPersonales">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarAntecedentesPersonales">💾 Guardar</button>
        </div>
    </div>
</div>

<!-- 📝 Modal: Antecedentes Familiares -->
<div id="modalAntecedentesFamiliares" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Antecedentes Familiares</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalAntecedentesFamiliares">✖</button>
        </div>
        <div class="modal-cuerpo">
            <label><input type="checkbox" id="chkHipertensionArterial"> Hipertensión arterial</label><br>
            <label><input type="checkbox" id="chkDiabetes"> Diabetes</label><br>
            <label><input type="checkbox" id="chkEnfermedadCardiaca"> Enfermedad cardíaca</label><br>
            <label><input type="checkbox" id="chkAsma"> Asma</label><br>
            <label><input type="checkbox" id="chkEpilepsia"> Epilepsia</label><br>
            <label><input type="checkbox" id="chkHepatitis"> Hepatitis</label><br>
            <label><input type="checkbox" id="chkVih"> VIH</label><br>
            <label><input type="checkbox" id="chkTuberculosis"> Tuberculosis</label><br>
            <label><input type="checkbox" id="chkEnfermedadRenal"> Enfermedad renal</label><br>
            <label><input type="checkbox" id="chkEnfermedadHepatica"> Enfermedad hepática</label><br>
            <label class="label-campo" style="margin-top:10px;">Otro</label>
            <input type="text" id="inpFamiliaresOtro" class="input-texto" placeholder="Especifique otro antecedente familiar">
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-cerrar-modal="modalAntecedentesFamiliares">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarAntecedentesFamiliares">💾 Guardar</button>
        </div>
    </div>
</div>

<!-- 📝 Modal: Exámen Extraoral -->
<div id="modalExamenExtraoral" class="modal-overlay">
    <div class="modal-contenido modal-ancho">
        <div class="modal-encabezado">
            <h3>Exámen Extraoral</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalExamenExtraoral">✖</button>
        </div>
        <div class="modal-cuerpo">
            <div class="fila-opciones">
                <span class="label-campo">Simetría</span>
                <label><input type="radio" name="simetria" value="simetrico"> Simétrico</label>
                <label><input type="radio" name="simetria" value="asimetrico"> Asimétrico</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Musculatura</span>
                <label><input type="radio" name="musculatura" value="normal"> Normal</label>
                <label><input type="radio" name="musculatura" value="alterada"> Alterada</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">P. Antero-posterior</span>
                <label><input type="radio" name="perfil_antero_posterior" value="concavo"> Cóncavo</label>
                <label><input type="radio" name="perfil_antero_posterior" value="recto"> Recto</label>
                <label><input type="radio" name="perfil_antero_posterior" value="convexo"> Convexo</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">P. Vertical</span>
                <label><input type="radio" name="perfil_vertical" value="hipo"> Hipo</label>
                <label><input type="radio" name="perfil_vertical" value="normo"> Normo</label>
                <label><input type="radio" name="perfil_vertical" value="hiper"> Hiper</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Fonación</span>
                <label><input type="radio" name="fonacion" value="normal"> Normal</label>
                <label><input type="radio" name="fonacion" value="alterada"> Alterada</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Deglución</span>
                <label><input type="radio" name="deglucion" value="normal"> Normal</label>
                <label><input type="radio" name="deglucion" value="atipica"> Atípica</label>
                <input type="text" id="inpDeglucionTipo" placeholder="Tipo..." class="input-texto input-tipo">
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Respiración</span>
                <label><input type="radio" name="respiracion" value="nasal"> Nasal</label>
                <label><input type="radio" name="respiracion" value="nasobucal"> Nasobucal</label>
                <label><input type="radio" name="respiracion" value="bucal"> Bucal</label>
            </div>
            <div class="fila-opciones">
                <span class="label-campo">Hábitos</span>
                <label><input type="radio" name="habitos" value="presente"> Presente</label>
                <label><input type="radio" name="habitos" value="ausente"> Ausente</label>
            </div>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-cerrar-modal="modalExamenExtraoral">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarExamenExtraoral">💾 Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Exámen Intraoral -->
<div id="modalExamenIntraoral" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Exámen Intraoral — Tejidos Blandos</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalExamenIntraoral">✖</button>
        </div>
        <div class="modal-cuerpo">
            <label class="label-campo">Labios</label>
            <textarea id="txtLabios" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Vestíbulo</label>
            <textarea id="txtVestibulo" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Frenillos</label>
            <textarea id="txtFrenillos" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Paladar</label>
            <textarea id="txtPaladar" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Orofaringe</label>
            <textarea id="txtOrofaringe" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Lengua</label>
            <textarea id="txtLengua" rows="2" class="input-textarea"></textarea>
            <label class="label-campo">Piso de boca</label>
            <textarea id="txtPisoBoca" rows="2" class="input-textarea"></textarea>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-cerrar-modal="modalExamenIntraoral">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarExamenIntraoral">💾 Guardar</button>
        </div>
    </div>
</div>

<script src="../SCRIPTS/script_historia_clinica.js"></script>