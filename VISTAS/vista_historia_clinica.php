<?php require_once '../CONFIG/verificar_sesion.php'; ?>
<link rel="stylesheet" href="../ESTILOS/style_historia_clinica.css">

<div class="contenedor-historia-clinica">
    <div class="layout-historia">

        <!-- Sidebar del paciente + checklist de Historia Clínica (siempre visible) -->
        <aside id="sidebarPaciente" class="sidebar-paciente">
                <button id="btnToggleSidebarPaciente" class="btn-toggle-sidebar-paciente" title="Ocultar/mostrar panel del paciente">
                    <span class="icono-toggle">‹</span>
                </button>

                <div class="sidebar-paciente-contenido">
                    <!-- Cabecera azul con el paciente -->
                    <div class="sp-cabecera">
                        <div class="avatar-paciente-sidebar">
                            <img id="fotoPaciente" src="../IMAGENES/perfiles_pacientes/default.png" alt="Foto del paciente">
                            <span id="inicialesPaciente" class="sp-iniciales" style="display:none;"></span>
                        </div>
                        <h3 class="nombre-paciente" id="nombrePaciente">---</h3>
                        <p class="dni-paciente-sidebar"><span id="tipoDocPaciente">DNI</span> <span id="dniPaciente">---</span></p>
                        <p class="responsable-paciente-sidebar" id="lineaResponsable" style="display:none;">Odontólogo responsable: <span id="doctorResponsable"></span></p>
                        <p class="sp-sexoedad"><span id="sexoEdadPaciente">---</span></p>
                        <div id="contenedorMensajeCumpleanos" class="contenedor-mensaje-cumpleanos"></div>
                    </div>

                    <div class="sp-cuerpo">
                        <div class="datos-sidebar-paciente">
                            <div class="dato-sidebar"><svg class="sp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg><span id="telefonoPaciente">---</span></div>
                            <div class="dato-sidebar"><svg class="sp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg><span id="correoPaciente">---</span></div>
                        </div>

                        <div class="sp-botones">
                            <button id="btnGestionarAlergias" class="btn-ver-alergias"><svg class="sp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg><span class="sp-txt">Alergias</span></button>
                            <button id="btnGenerarPDF" class="btn-generar-pdf-sidebar" title="Generar Historia Clínica en PDF"><svg class="sp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg><span class="sp-txt">PDF</span></button>
                        </div>

                        <!-- Información del apoderado (solo si existe) -->
                        <div id="infoApoderado" class="info-apoderado-seccion" style="display:none;">
                            <h4>Apoderado</h4>
                            <div class="sp-apod">
                                <span class="sp-apod-avatar" id="inicialesApoderado">--</span>
                                <div><b class="sp-apod-nombre" id="nombreApoderado">---</b><small id="tipoFamiliarApoderado">---</small></div>
                            </div>
                            <div class="sp-apod-tel"><svg class="sp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg><span id="telefonoApoderado">---</span></div>
                        </div>

                        <div id="avisoSoloLectura" class="aviso-solo-lectura" style="display:none;"></div>

                        <div class="sp-hc-cab"><p class="titulo-checklist-sidebar">Historia clínica</p><b id="hcPorcentaje">0%</b></div>
                        <p class="sp-hc-sub" id="hcResumen">0 de 7 secciones completas</p>
                        <div class="sp-barra"><div id="hcBarra" style="width:0%"></div></div>
                        <div class="checklist-sidebar">
                            <div class="check-item-sidebar completo no-clic" data-seccion="filiacion" title="Se completa al registrar al paciente">
                                <span class="check-texto">Filiación</span>
                                <span class="check-icono">✓</span>
                            </div>
                            <div class="check-item-sidebar" data-seccion="motivo_consulta">
                                <span class="check-texto">Motivo de consulta</span>
                                <span class="check-icono">–</span>
                            </div>
                            <div class="check-item-sidebar" data-seccion="antecedentes_personales">
                                <span class="check-texto">Antecedentes personales</span>
                                <span class="check-icono">–</span>
                            </div>
                            <div class="check-item-sidebar" data-seccion="antecedentes_familiares">
                                <span class="check-texto">Antecedentes familiares</span>
                                <span class="check-icono">–</span>
                            </div>
                            <div class="check-item-sidebar" data-seccion="examen_general">
                                <span class="check-texto">Examen general</span>
                                <span class="check-icono">–</span>
                            </div>
                            <div class="check-item-sidebar" data-seccion="examen_extraoral">
                                <span class="check-texto">Examen extraoral</span>
                                <span class="check-icono">–</span>
                            </div>
                            <div class="check-item-sidebar" data-seccion="examen_intraoral">
                                <span class="check-texto">Examen intraoral</span>
                                <span class="check-icono">–</span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Columna derecha: buscador (siempre visible) + contenido -->
            <div class="columna-derecha-historia">
                <!-- Buscador de paciente -->
                <div class="encabezado-historia">
                    <h2>Historia Clínica</h2>
                    <div class="buscador-paciente">
                        <input type="text"
                            id="inputBuscarPaciente"
                            placeholder="Buscar paciente por documento o nombre..."
                            class="input-busqueda-paciente">
                        <div id="resultadosBusqueda" class="resultados-busqueda" style="display:none;"></div>
                    </div>
                    <button id="btnVolverPacientes" class="btn-secundario"><i class="ti ti-arrow-left"></i> Volver a Pacientes</button>
                </div>

                <!-- Pestañas de módulos (oculto hasta que se cargue un paciente) -->
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

                <!-- Mensaje cuando no hay paciente seleccionado -->
                <div id="mensajeSinPaciente" class="mensaje-sin-paciente">
                    <div class="icono-mensaje"><i class="ti ti-search"></i></div>
                    <p>Busque un paciente para ver su historia clínica</p>
                    <p class="texto-pequeno">Use el buscador superior para encontrar un paciente por documento o nombre</p>
                </div>
            </div>
        </div>
    </div>

<!-- Modal para gestionar alergias -->
<div id="modalAlergias" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3> Gestionar Alergias a Medicamentos</h3>
            <button type="button" class="btn-cerrar-modal" id="btnCerrarModalAlergias"><i class="ti ti-x"></i></button>
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
                    <button id="btnAgregarMedicamento" class="btn-agregar">Agregar</button>
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
            <button type="button" class="btn-guardar" id="btnGuardarAlergias">Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- Modal: Antecedentes (incluye motivo de consulta) -->
<div id="modalMotivoConsulta" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Motivo de Consulta</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalMotivoConsulta"><i class="ti ti-x"></i></button>
        </div>
        <div class="modal-cuerpo">
            <textarea id="txtMotivoConsulta" rows="4" class="input-textarea" placeholder="Describa el motivo de la consulta..."></textarea>
        </div>
        <div class="modal-botones">
            <button type="button" class="btn-secundario" data-cerrar-modal="modalMotivoConsulta">Cancelar</button>
            <button type="button" class="btn-guardar" id="btnGuardarMotivoConsulta">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Exámen Clínico General -->
<div id="modalExamenGeneral" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Exámen Clínico General</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalExamenGeneral"><i class="ti ti-x"></i></button>
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
            <button type="button" class="btn-guardar" id="btnGuardarExamenGeneral">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Antecedentes Personales -->
<div id="modalAntecedentesPersonales" class="modal-overlay">
    <div class="modal-contenido modal-ancho">
        <div class="modal-encabezado">
            <h3>Antecedentes Personales</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalAntecedentesPersonales"><i class="ti ti-x"></i></button>
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
            <button type="button" class="btn-guardar" id="btnGuardarAntecedentesPersonales">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Antecedentes Familiares -->
<div id="modalAntecedentesFamiliares" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Antecedentes Familiares</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalAntecedentesFamiliares"><i class="ti ti-x"></i></button>
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
            <button type="button" class="btn-guardar" id="btnGuardarAntecedentesFamiliares">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Exámen Extraoral -->
<div id="modalExamenExtraoral" class="modal-overlay">
    <div class="modal-contenido modal-ancho">
        <div class="modal-encabezado">
            <h3>Exámen Extraoral</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalExamenExtraoral"><i class="ti ti-x"></i></button>
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
            <button type="button" class="btn-guardar" id="btnGuardarExamenExtraoral">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Exámen Intraoral -->
<div id="modalExamenIntraoral" class="modal-overlay">
    <div class="modal-contenido">
        <div class="modal-encabezado">
            <h3>Exámen Intraoral — Tejidos Blandos</h3>
            <button type="button" class="btn-cerrar-modal" data-cerrar-modal="modalExamenIntraoral"><i class="ti ti-x"></i></button>
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
            <button type="button" class="btn-guardar" id="btnGuardarExamenIntraoral">Guardar</button>
        </div>
    </div>
</div>

<script src="../SCRIPTS/script_historia_clinica.js"></script>