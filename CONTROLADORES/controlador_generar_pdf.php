<?php
/**
 * controlador_generar_pdf.php
 * Genera el PDF de la Historia Clínica de un paciente.
 * Las secciones sin datos (Antecedentes, Exámenes) se omiten del PDF.
 */

// Resguardo para hosting compartido con límites bajos (Dompdf puede ser pesado)
// Si el hosting no permite cambiar estos valores, PHP simplemente ignora el ini_set sin error.
ini_set('memory_limit', '256M');
set_time_limit(60);

require_once '../MODELOS/modelo_historia_clinica.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$accion = $_GET['accion'] ?? '';
if ($accion !== 'generar_historia_clinica') {
    http_response_code(400);
    echo 'Acción no válida';
    exit;
}

$idPaciente = $_GET['id_paciente'] ?? 0;
if (!$idPaciente) {
    http_response_code(400);
    echo 'ID de paciente no proporcionado';
    exit;
}

$modelo = new ModeloHistoriaClinica();

$paciente = $modelo->obtenerPacienteCompleto($idPaciente);
if (!$paciente) {
    http_response_code(404);
    echo 'Paciente no encontrado';
    exit;
}

$historia = $modelo->obtenerOCrearHistoriaClinica($idPaciente);
$idHistoria = $historia['id_historia'];

$antecedentes    = $modelo->obtenerAntecedentes($idHistoria);
$examenGeneral   = $modelo->obtenerExamenGeneral($idHistoria);
$examenExtraoral = $modelo->obtenerExamenExtraoral($idHistoria);
$examenIntraoral = $modelo->obtenerExamenIntraoral($idHistoria);
$alergias        = $modelo->listarAlergiasPaciente($idPaciente);

// ══════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════

// Una sección "tiene datos" si al menos un campo (que no sea metadata) no está vacío
function seccionTieneDatos($datos) {
    if (!$datos) return false;
    $ignorar = ['id_historia', 'fecha_actualizacion'];
    foreach ($datos as $campo => $valor) {
        if (in_array($campo, $ignorar)) continue;
        if ($valor !== null && $valor !== '') return true;
    }
    return false;
}

function calcularEdad($fechaNacimiento) {
    if (!$fechaNacimiento) return '---';
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return $nacimiento->diff($hoy)->y . ' años';
}

function e($valor) {
    return htmlspecialchars($valor ?? '---', ENT_QUOTES, 'UTF-8');
}

// Muestra el valor o una línea en blanco (estilo formulario físico) si el campo puntual está vacío
function campo($valor) {
    $v = trim((string)($valor ?? ''));
    return $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : '&nbsp;';
}

// Marca [x] o [ ] para las opciones tipo checkbox del exámen extraoral
function opcion($valorActual, $valorOpcion, $etiqueta) {
    $marcado = ($valorActual === $valorOpcion);
    $caja = $marcado ? '&#9746;' : '&#9744;'; // ☒ / ☐
    $estilo = $marcado ? 'font-weight:bold;' : '';
    return "<span style=\"margin-right:14px;{$estilo}\">{$caja} {$etiqueta}</span>";
}

$labelsSimetria       = ['simetrico' => 'Simétrico', 'asimetrico' => 'Asimétrico'];
$labelsNormalAlterada = ['normal' => 'Normal', 'alterada' => 'Alterada'];
$labelsPerfilAP       = ['concavo' => 'Cóncavo', 'recto' => 'Recto', 'convexo' => 'Convexo'];
$labelsPerfilV        = ['hipo' => 'Hipo', 'normo' => 'Normo', 'hiper' => 'Hiper'];
$labelsDeglucion      = ['normal' => 'Normal', 'atipica' => 'Atípica'];
$labelsRespiracion    = ['nasal' => 'Nasal', 'nasobucal' => 'Nasobucal', 'bucal' => 'Bucal'];
$labelsHabitos        = ['presente' => 'Presente', 'ausente' => 'Ausente'];

$nombreCompleto = trim(($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? ''));

// ══════════════════════════════════════════════════════════════
// CONSTRUCCIÓN DEL HTML
// ══════════════════════════════════════════════════════════════
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 90px 40px 60px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 15px; margin: 0 0 2px; color: #1a2332; }
    h2 {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #2a4d8f;
        background: #eef2fb;
        padding: 5px 8px;
        margin: 14px 0 8px;
        border-left: 3px solid #2a4d8f;
    }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 3px 4px; vertical-align: top; }
    .etiqueta { color: #5a6475; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.3px; }
    .valor { font-size: 11.5px; }
    .caja {
        border: 0.5px solid #dcdfe4;
        border-radius: 4px;
        padding: 8px 10px;
        margin-bottom: 4px;
    }
    .fila-opciones { margin-bottom: 6px; }
    .fila-opciones .etiqueta-fila { display:inline-block; width: 140px; color:#5a6475; font-size:9.5px; text-transform:uppercase; }
    .footer-nota { font-size: 9px; color: #9aa3b0; margin-top: 20px; }
</style>
</head>
<body>

<table style="margin-bottom:6px;">
    <tr>
        <td style="width:70%;">
            <h1>Centro Dental Internacional</h1>
            <span style="font-size:10px;color:#5a6475;">Historia Clínica</span>
        </td>
        <td style="width:30%;text-align:right;">
            <span class="etiqueta">HC N°</span> <span class="valor"><?= e($idHistoria) ?></span><br>
            <span class="etiqueta">Fecha de emisión</span> <span class="valor"><?= date('d/m/Y H:i') ?></span>
        </td>
    </tr>
</table>

<!-- ══ FILIACIÓN — siempre visible ══ -->
<h2>Filiación</h2>
<table>
    <tr>
        <td style="width:50%;">
            <span class="etiqueta">Nombres y apellidos</span><br>
            <span class="valor"><?= e($nombreCompleto) ?></span>
        </td>
        <td style="width:25%;">
            <span class="etiqueta">DNI</span><br>
            <span class="valor"><?= e($paciente['dni'] ?? null) ?></span>
        </td>
        <td style="width:25%;">
            <span class="etiqueta">Edad</span><br>
            <span class="valor"><?= calcularEdad($paciente['fecha_nacimiento'] ?? null) ?></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="etiqueta">Fecha de nacimiento</span><br>
            <span class="valor"><?= $paciente['fecha_nacimiento'] ? date('d/m/Y', strtotime($paciente['fecha_nacimiento'])) : '---' ?></span>
        </td>
        <td>
            <span class="etiqueta">Sexo</span><br>
            <span class="valor"><?= e($paciente['nombre_sexo'] ?? null) ?></span>
        </td>
        <td>
            <span class="etiqueta">Teléfono</span><br>
            <span class="valor"><?= e($paciente['telefono'] ?? null) ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="etiqueta">Correo</span><br>
            <span class="valor"><?= e($paciente['correo'] ?? null) ?></span>
        </td>
        <td>
            <span class="etiqueta">Ocupación</span><br>
            <span class="valor"><?= e($paciente['ocupacion'] ?? null) ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="3">
            <span class="etiqueta">Dirección</span><br>
            <span class="valor"><?= e($paciente['direccion'] ?? null) ?></span>
        </td>
    </tr>
</table>

<?php if (!empty($paciente['apoderado'])): ?>
<h2>Apoderado</h2>
<table>
    <tr>
        <td style="width:40%;">
            <span class="etiqueta">Nombre</span><br>
            <span class="valor"><?= e(($paciente['apoderado']['nombre'] ?? '') . ' ' . ($paciente['apoderado']['apellido'] ?? '')) ?></span>
        </td>
        <td style="width:30%;">
            <span class="etiqueta">Teléfono</span><br>
            <span class="valor"><?= e($paciente['apoderado']['telefono'] ?? null) ?></span>
        </td>
        <td style="width:30%;">
            <span class="etiqueta">Parentesco</span><br>
            <span class="valor"><?= e($paciente['apoderado']['tipo_familiar'] ?? null) ?></span>
        </td>
    </tr>
</table>
<?php endif; ?>

<!-- ══ ALERGIAS — solo si hay registradas ══ -->
<?php if (!empty($alergias)): ?>
<h2>Alergias a medicamentos</h2>
<div class="caja" style="border-color:#e0b040;background:#fffbf0;">
    <?= e(implode(', ', array_map(fn($a) => $a['medicamento'], $alergias))) ?>
</div>
<?php endif; ?>

<!-- ══ ANTECEDENTES — solo si tiene datos ══ -->
<?php if (seccionTieneDatos($antecedentes)): ?>
<h2>Motivo de consulta y antecedentes</h2>
<table>
    <tr><td>
        <span class="etiqueta">Motivo de consulta</span><br>
        <span class="valor"><?= campo($historia['motivo_consulta'] ?? '') ?></span>
    </td></tr>
</table>
<div class="caja">
    <span class="etiqueta">Médica</span><br>
    <span class="valor"><?= campo($antecedentes['medica'] ?? '') ?></span>
</div>
<div class="caja">
    <span class="etiqueta">Odontológicos</span><br>
    <span class="valor"><?= campo($antecedentes['odontologicos'] ?? '') ?></span>
</div>
<div class="caja">
    <span class="etiqueta">Familiares</span><br>
    <span class="valor"><?= campo($antecedentes['familiares'] ?? '') ?></span>
</div>
<?php endif; ?>

<!-- ══ EXÁMEN CLÍNICO GENERAL — solo si tiene datos ══ -->
<?php if (seccionTieneDatos($examenGeneral)): ?>
<h2>Exámen clínico general</h2>
<table>
    <tr>
        <td style="width:50%;">
            <span class="etiqueta">Talla</span>
            <span class="valor"><?= campo($examenGeneral['talla_mts'] ?? '') ?> mts.</span>
        </td>
        <td style="width:50%;">
            <span class="etiqueta">Peso</span>
            <span class="valor"><?= campo($examenGeneral['peso_kg'] ?? '') ?> kg.</span>
        </td>
    </tr>
</table>
<?php endif; ?>

<!-- ══ EXÁMEN EXTRAORAL — solo si tiene datos ══ -->
<?php if (seccionTieneDatos($examenExtraoral)): ?>
<h2>Exámen extraoral</h2>
<div class="fila-opciones"><span class="etiqueta-fila">Simetría</span>
    <?php foreach ($labelsSimetria as $val => $lbl) echo opcion($examenExtraoral['simetria'] ?? null, $val, $lbl); ?>
</div>
<div class="fila-opciones"><span class="etiqueta-fila">Musculatura</span>
    <?php foreach ($labelsNormalAlterada as $val => $lbl) echo opcion($examenExtraoral['musculatura'] ?? null, $val, $lbl); ?>
</div>
<div class="fila-opciones"><span class="etiqueta-fila">P. Antero-posterior</span>
    <?php foreach ($labelsPerfilAP as $val => $lbl) echo opcion($examenExtraoral['perfil_antero_posterior'] ?? null, $val, $lbl); ?>
</div>
<div class="fila-opciones"><span class="etiqueta-fila">P. Vertical</span>
    <?php foreach ($labelsPerfilV as $val => $lbl) echo opcion($examenExtraoral['perfil_vertical'] ?? null, $val, $lbl); ?>
</div>
<div class="fila-opciones"><span class="etiqueta-fila">Fonación</span>
    <?php foreach ($labelsNormalAlterada as $val => $lbl) echo opcion($examenExtraoral['fonacion'] ?? null, $val, $lbl); ?>
</div>
<div class="fila-opciones"><span class="etiqueta-fila">Deglución</span>
    <?php foreach ($labelsDeglucion as $val => $lbl) echo opcion($examenExtraoral['deglucion'] ?? null, $val, $lbl); ?>
    <?php if (!empty($examenExtraoral['deglucion_tipo'])): ?>
        Tipo: <?= e($examenExtraoral['deglucion_tipo']) ?>
    <?php endif; ?>
</div>
<div class="fila-opciones"><span class="etiqueta-fila">Respiración</span>
    <?php foreach ($labelsRespiracion as $val => $lbl) echo opcion($examenExtraoral['respiracion'] ?? null, $val, $lbl); ?>
</div>
<div class="fila-opciones"><span class="etiqueta-fila">Hábitos</span>
    <?php foreach ($labelsHabitos as $val => $lbl) echo opcion($examenExtraoral['habitos'] ?? null, $val, $lbl); ?>
</div>
<?php endif; ?>

<!-- ══ EXÁMEN INTRAORAL — solo si tiene datos ══ -->
<?php if (seccionTieneDatos($examenIntraoral)): ?>
<h2>Exámen intraoral — Tejidos blandos</h2>
<table>
    <tr>
        <td style="width:50%;">
            <span class="etiqueta">Labios</span><br>
            <span class="valor"><?= campo($examenIntraoral['labios'] ?? '') ?></span>
        </td>
        <td style="width:50%;">
            <span class="etiqueta">Orofaringe</span><br>
            <span class="valor"><?= campo($examenIntraoral['orofaringe'] ?? '') ?></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="etiqueta">Vestíbulo</span><br>
            <span class="valor"><?= campo($examenIntraoral['vestibulo'] ?? '') ?></span>
        </td>
        <td>
            <span class="etiqueta">Lengua</span><br>
            <span class="valor"><?= campo($examenIntraoral['lengua'] ?? '') ?></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="etiqueta">Frenillos</span><br>
            <span class="valor"><?= campo($examenIntraoral['frenillos'] ?? '') ?></span>
        </td>
        <td>
            <span class="etiqueta">Piso de boca</span><br>
            <span class="valor"><?= campo($examenIntraoral['piso_boca'] ?? '') ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="etiqueta">Paladar</span><br>
            <span class="valor"><?= campo($examenIntraoral['paladar'] ?? '') ?></span>
        </td>
    </tr>
</table>
<?php endif; ?>

<p class="footer-nota">Documento generado automáticamente por el sistema de gestión — Centro Dental Internacional.</p>

</body>
</html>
<?php
$html = ob_get_clean();

// ══════════════════════════════════════════════════════════════
// RENDERIZAR PDF
// ══════════════════════════════════════════════════════════════
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$slugNombre = preg_replace('/[^A-Za-z0-9_]+/', '_', $nombreCompleto);
$nombreArchivo = "Historia_Clinica_{$slugNombre}_" . date('Y-m-d') . '.pdf';

$dompdf->stream($nombreArchivo, ['Attachment' => true]);