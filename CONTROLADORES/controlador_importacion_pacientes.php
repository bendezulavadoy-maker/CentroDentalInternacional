<?php
// Importacion masiva de pacientes (Excel/CSV).
// Solo con sesion iniciada, acceso al modulo Pacientes Y el permiso "Importar pacientes desde Excel"
// (Configuracion > Permisos por Rol > Acciones permitidas). Por defecto: solo el Administrador.
// Cualquier error inesperado (de PHP o una excepcion) se responde como JSON legible, en vez de una pagina
// en blanco o con avisos mezclados. El detalle tecnico solo se muestra a quien ya paso la validacion de permisos.
ob_start();
ini_set('display_errors', 0);
$GLOBALS['imp_detalle_errores'] = false;
function imp_error_json($detalle) {
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
    // El Administrador siempre ve el detalle (por ejemplo "falta un archivo"); los demas, solo si ya tienen permiso
    $esAdmin = isset($_SESSION['usuario']['id_rol']) && (int)$_SESSION['usuario']['id_rol'] === 1;
    $texto = ($GLOBALS['imp_detalle_errores'] || $esAdmin) ? 'Error del servidor: ' . $detalle : 'Error del servidor. Intente de nuevo.';
    echo json_encode(['success' => false, 'mensaje' => $texto], JSON_UNESCAPED_UNICODE);
}
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        imp_error_json($e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')');
    }
});
set_exception_handler(function ($ex) {
    error_log('Importacion de pacientes: ' . $ex->getMessage() . ' en ' . $ex->getFile() . ':' . $ex->getLine());
    imp_error_json($ex->getMessage() . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ')');
});

require_once '../CONFIG/permisos.php';
requerirPermisoApi('pacientes');
require_once '../CONFIG/bitacora.php';
require_once '../MODELOS/modelo_importacion_pacientes.php';

$accion    = $_GET['accion'] ?? $_POST['accion'] ?? '';
$idUsuario = (int)($_SESSION['usuario']['id_usuario'] ?? 0);

function salida(array $datos, $codigo = 200) {
    while (ob_get_level() > 0) ob_end_clean();                 // descarta cualquier aviso suelto: la respuesta es solo JSON
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}
function soloPost() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') salida(['success' => false, 'mensaje' => 'Método no permitido'], 405);
}

Permisos::requerir('pacientes.importar');         // 403 si el rol no tiene el permiso
$GLOBALS['imp_detalle_errores'] = true;           // desde aqui, quien llega es un usuario autorizado

$modelo = new ModeloImportacionPacientes();
const MAX_ERRORES_MOSTRADOS = 300;                // el resto se informa solo como cantidad

switch ($accion) {

    // ---------------------------------------------------------------
    case 'plantilla':
        try {
            $tmp = tempnam(sys_get_temp_dir(), 'plt');
            construirPlantilla($tmp, $modelo->catalogos());
            while (ob_get_level() > 0) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plantilla_importacion_pacientes.xlsx"');
            header('Content-Length: ' . filesize($tmp));
            header('Cache-Control: no-store');
            readfile($tmp);
            @unlink($tmp);
        } catch (HojaCalculoError $e) {
            @unlink($tmp ?? '');
            salida(['success' => false, 'mensaje' => $e->getMessage() . '. Puede subir un archivo .csv con las mismas columnas.'], 500);
        }
        exit;

    // ---------------------------------------------------------------
    case 'analizar':                               // revisa el archivo; NO guarda nada
        soloPost();
        if (!isset($_FILES['archivo'])) salida(['success' => false, 'mensaje' => 'Seleccione un archivo']);
        $f = $_FILES['archivo'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $m = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'El archivo supera el tamaño permitido' : 'No se pudo recibir el archivo';
            salida(['success' => false, 'mensaje' => $m]);
        }
        if (!is_uploaded_file($f['tmp_name'])) salida(['success' => false, 'mensaje' => 'Archivo no válido']);
        try {
            @set_time_limit(120);
            $lectura = $modelo->leerArchivo($f['tmp_name'], $f['name']);
            if (!$lectura['filas']) salida(['success' => false, 'mensaje' => 'El archivo no tiene filas de pacientes (solo el encabezado)']);
            $res = $modelo->analizar($lectura['filas']);
        } catch (HojaCalculoError $e) {
            salida(['success' => false, 'mensaje' => $e->getMessage()]);
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['importacion_pacientes'] = ['token' => $token, 'filas' => $lectura['filas'], 'nombre' => $f['name'],
                                              'hora' => time(), 'usuario' => $idUsuario];
        salida(['success' => true, 'token' => $token, 'nombre' => $f['name'],
                'total' => $res['total'], 'validas' => $res['validas'], 'existentes' => $res['existentes'], 'con_error' => $res['con_error'],
                'errores' => array_slice($res['errores'], 0, MAX_ERRORES_MOSTRADOS), 'errores_total' => count($res['errores']),
                'columnas_ignoradas' => $lectura['ignoradas']]);

    // ---------------------------------------------------------------
    case 'importar':                               // guarda las filas buenas del archivo ya revisado
        soloPost();
        $pend = $_SESSION['importacion_pacientes'] ?? null;
        $token = (string)($_POST['token'] ?? '');
        if (!$pend || !hash_equals($pend['token'], $token) || (int)$pend['usuario'] !== $idUsuario) {
            salida(['success' => false, 'mensaje' => 'La revisión del archivo ya no está disponible. Vuelva a subirlo.']);
        }
        if (time() - (int)$pend['hora'] > 1800) {
            unset($_SESSION['importacion_pacientes']);
            salida(['success' => false, 'mensaje' => 'Pasó demasiado tiempo desde la revisión. Vuelva a subir el archivo.']);
        }
        unset($_SESSION['importacion_pacientes']);                  // un archivo revisado se importa UNA sola vez
        session_write_close();
        @set_time_limit(300);
        try {
            $res = $modelo->importar($pend['filas'], $pend['nombre'], $idUsuario);
        } catch (Throwable $e) {
            error_log('Importacion de pacientes: ' . $e->getMessage());
            salida(['success' => false, 'mensaje' => 'No se pudo completar la importación. Revise el historial de cargas antes de repetirla.'], 500);
        }
        registrarBitacora('importacion.pacientes', 'carga', $res['id_carga'], null,
            ['archivo' => $pend['nombre'], 'total' => $res['total'], 'importados' => $res['importados'], 'omitidos' => $res['existentes'], 'con_error' => $res['con_error']]);
        salida(['success' => true, 'id_carga' => $res['id_carga'], 'total' => $res['total'], 'importados' => $res['importados'],
                'existentes' => $res['existentes'], 'con_error' => $res['con_error'],
                'errores' => array_slice($res['errores'], 0, MAX_ERRORES_MOSTRADOS), 'errores_total' => count($res['errores'])]);

    // ---------------------------------------------------------------
    case 'historial':
        salida(['success' => true, 'cargas' => $modelo->historial(15)]);

    // ---------------------------------------------------------------
    case 'revertir':
        soloPost();
        $idCarga = (int)($_POST['id_carga'] ?? 0);
        if ($idCarga <= 0) salida(['success' => false, 'mensaje' => 'Carga no válida']);
        $r = $modelo->revertir($idCarga, $idUsuario);
        if (!empty($r['success'])) {
            registrarBitacora('importacion.revertir', 'carga', $idCarga, null, ['deshechos' => $r['deshechos'], 'conservados' => $r['conservados']]);
        }
        salida($r);

    default:
        salida(['success' => false, 'mensaje' => 'Acción no válida'], 400);
}

// =====================================================================
// Plantilla de Excel (con tus catalogos reales)
// =====================================================================
function construirPlantilla($ruta, array $cat) {
    $columnas = [   // [titulo, ancho, obligatoria]
        ['tipo_documento', 14, 1], ['nro_documento', 16, 1], ['nombres', 24, 1], ['apellidos', 24, 1], ['fecha_nacimiento', 16, 1],
        ['sexo', 12, 1], ['estado_civil', 16, 1], ['grado_instruccion', 22, 0], ['ocupacion', 20, 0], ['telefono', 14, 1],
        ['direccion', 32, 1], ['correo', 28, 0], ['observaciones', 28, 0], ['codigo_odontologo', 18, 0],
        ['apoderado_tipo_documento', 18, 0], ['apoderado_nro_documento', 18, 0], ['apoderado_nombres', 22, 0],
        ['apoderado_apellidos', 22, 0], ['apoderado_telefono', 16, 0], ['apoderado_parentesco', 18, 0],
    ];
    $encabezado = []; $anchos = []; $texto = [];
    foreach ($columnas as $i => $c) { $encabezado[] = $c[0] . ($c[2] ? ' *' : ''); $anchos[] = $c[1]; $texto[] = $i; }

    // Hoja "Listas": una columna por catalogo
    $tipos   = array_map(fn($t) => $t['abreviatura'], $cat['lista_tipos']);
    $codigos = array_map(fn($d) => explode(' - ', $d, 2)[0], $cat['lista_doctores']);
    $reglas  = array_map(function ($t) {
        $largo = ($t['longitud_min'] == $t['longitud_max']) ? $t['longitud_min'] : ($t['longitud_min'] . ' a ' . $t['longitud_max']);
        return $t['abreviatura'] . ' (' . $t['nombre'] . '): ' . $largo . ($t['solo_numeros'] ? ' dígitos' : ' letras o números');
    }, $cat['lista_tipos']);
    $cols = [
        'Tipo de documento' => $tipos, 'Sexo' => $cat['lista_sexo'], 'Estado civil' => $cat['lista_estado_civil'],
        'Grado de instrucción' => $cat['lista_grado'], 'Parentesco' => $cat['lista_familiar'],
        'Código de odontólogo' => $codigos, 'Odontólogo' => array_map(fn($d) => explode(' - ', $d, 2)[1] ?? '', $cat['lista_doctores']),
        'Longitud de documentos' => $reglas,
    ];
    $alto = max(array_map('count', $cols));
    $filasListas = [array_keys($cols)];
    for ($i = 0; $i < $alto; $i++) { $f = []; foreach ($cols as $lista) $f[] = $lista[$i] ?? ''; $filasListas[] = $f; }
    $rango = function ($letra, $n) { return "Listas!\$$letra\$2:\$$letra\$" . max(2, $n + 1); };

    $instrucciones = [
        ['Plantilla de importación de pacientes'],
        ['1. Llene la hoja «Pacientes»: una fila por paciente. Las columnas con asterisco (*) son obligatorias.'],
        ['2. Tipo de documento, sexo, estado civil, grado de instrucción, código de odontólogo y parentesco se eligen de una lista desplegable. Los valores válidos están en la hoja «Listas».'],
        ['3. Fecha de nacimiento: escríbala como DD/MM/AAAA (por ejemplo 17/05/1990).'],
        ['4. Número de documento y teléfono: escríbalos tal cual. Las columnas ya están en formato Texto para que no se pierdan los ceros iniciales (por ejemplo 08123456). La longitud de cada tipo de documento está en la hoja «Listas».'],
        ['5. Teléfono: 9 dígitos. Nombres y apellidos: solo letras, espacios y acentos.'],
        ['6. Menores de 18 años: los datos del apoderado son obligatorios (las 6 columnas que empiezan con «apoderado_»). En adultos son opcionales, pero si llena una de ellas debe llenar las seis.'],
        ['7. Código de odontólogo (opcional): es el odontólogo responsable del paciente. Escriba su código (por ejemplo DENTINT7431).'],
        ['8. Si un paciente ya existe en el sistema (mismo tipo y número de documento), esa fila se omite y NO se modifica al paciente.'],
        ['9. Cada archivo admite hasta ' . ModeloImportacionPacientes::MAX_FILAS . ' filas. No cambie los nombres de las columnas ni borre la fila de encabezado.'],
        ['10. Antes de guardar, el sistema le mostrará un resumen con las filas que tienen problemas. Las filas correctas se importan; las demás puede corregirlas y volver a subirlas (las ya importadas se omiten).'],
        ['11. Estos archivos contienen datos personales de pacientes: bórrelos de su computadora cuando termine la carga.'],
    ];

    HojaCalculo::escribirXlsx($ruta, [
        ['nombre' => 'Pacientes', 'filas' => [$encabezado], 'anchos' => $anchos, 'texto' => $texto,
         'listas' => [0 => $rango('A', count($tipos)), 5 => $rango('B', count($cat['lista_sexo'])), 6 => $rango('C', count($cat['lista_estado_civil'])),
                      7 => $rango('D', count($cat['lista_grado'])), 13 => $rango('F', count($codigos)),
                      14 => $rango('A', count($tipos)), 19 => $rango('E', count($cat['lista_familiar']))],
         'desde_fila' => 2, 'hasta_fila' => ModeloImportacionPacientes::MAX_FILAS + 1],
        ['nombre' => 'Instrucciones', 'filas' => $instrucciones, 'anchos' => [130], 'encabezado' => false, 'estilos' => ['titulo' => [0], 'largo' => range(1, count($instrucciones) - 1)]],
        ['nombre' => 'Listas', 'filas' => $filasListas, 'anchos' => [22, 14, 20, 26, 16, 22, 36, 60]],
    ]);
}