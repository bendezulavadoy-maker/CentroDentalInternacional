<?php
/**
 * Lectura y escritura minima de hojas de calculo, SIN librerias externas
 * (no hace falta Composer ni instalar nada en el hosting; solo la extension "zip" de PHP para .xlsx).
 *
 *   HojaCalculo::leer($ruta, $nombreOriginal)   -> filas del archivo (.xlsx o .csv)
 *   HojaCalculo::escribirXlsx($ruta, $hojas)    -> crea un .xlsx (se usa para la plantilla)
 *
 * Es un lector de ARCHIVOS SUBIDOS POR PERSONAS, asi que se protege de archivos mal formados o hostiles:
 * limite de tamano descomprimido, de numero de filas/columnas, sin entidades externas XML ni red.
 */

class HojaCalculoError extends Exception {}

class HojaCalculo {

    const MAX_BYTES_ARCHIVO = 5242880;     // 5 MB subidos
    const MAX_BYTES_XML     = 15728640;    // 15 MB por parte XML ya descomprimida (frena "bombas zip")
    const MAX_ENTRADAS_ZIP  = 300;
    const MAX_COLUMNAS      = 60;

    /**
     * Lee la primera hoja (o la llamada "Pacientes") de un .xlsx, o un .csv.
     * Devuelve una lista de filas: [ ['fila' => 2, 'celdas' => [0 => 'DNI', 1 => '123', ...]], ... ]
     * Las filas completamente vacias se omiten; 'fila' es el numero real de fila en el archivo.
     */
    public static function leer($ruta, $nombreOriginal, $maxFilas = 1500) {
        if (!is_file($ruta)) throw new HojaCalculoError('No se recibió el archivo');
        if (filesize($ruta) > self::MAX_BYTES_ARCHIVO) {
            throw new HojaCalculoError('El archivo es demasiado grande (máximo 5 MB)');
        }
        $ext = strtolower(pathinfo((string)$nombreOriginal, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            $cabecera = file_get_contents($ruta, false, null, 0, 4);
            if ($cabecera === false || substr($cabecera, 0, 2) !== 'PK') {
                throw new HojaCalculoError('El archivo no es un Excel (.xlsx) válido');
            }
            return self::leerXlsx($ruta, $maxFilas);
        }
        if ($ext === 'csv') return self::leerCsv($ruta, $maxFilas);
        if ($ext === 'xls') {
            throw new HojaCalculoError('El formato .xls (Excel antiguo) no se admite. Guárdelo como .xlsx o .csv desde Excel (Archivo > Guardar como).');
        }
        throw new HojaCalculoError('Formato no admitido. Use la plantilla en formato .xlsx (o .csv).');
    }

    // =====================================================================
    // CSV
    // =====================================================================
    private static function leerCsv($ruta, $maxFilas) {
        $texto = file_get_contents($ruta);
        if ($texto === false) throw new HojaCalculoError('No se pudo leer el archivo');
        if (substr($texto, 0, 3) === "\xEF\xBB\xBF") $texto = substr($texto, 3);          // BOM de UTF-8
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');                // CSV guardado por Excel en Windows
        }
        if (strpos($texto, "\0") !== false) throw new HojaCalculoError('El archivo no es un CSV de texto válido');

        $primera = strtok($texto, "\r\n");
        $mejor = ','; $max = -1;
        foreach ([',', ';', "\t"] as $d) {                                                // Excel en espanol usa ";"
            $n = substr_count((string)$primera, $d);
            if ($n > $max) { $max = $n; $mejor = $d; }
        }

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $texto); rewind($flujo);
        $filas = []; $num = 0;
        while (($celdas = fgetcsv($flujo, 0, $mejor, '"', '')) !== false) {
            $num++;
            if ($celdas === [null]) continue;                                             // linea en blanco
            $celdas = array_slice(array_map(fn($c) => trim((string)$c), $celdas), 0, self::MAX_COLUMNAS);
            if (self::filaVacia($celdas)) continue;
            $filas[] = ['fila' => $num, 'celdas' => $celdas];
            if (count($filas) > $maxFilas + 1) {
                fclose($flujo);
                throw new HojaCalculoError("El archivo tiene demasiadas filas (máximo $maxFilas por carga)");
            }
        }
        fclose($flujo);
        return $filas;
    }

    // =====================================================================
    // XLSX
    // =====================================================================
    private static function leerXlsx($ruta, $maxFilas) {
        if (!class_exists('ZipArchive')) {
            throw new HojaCalculoError('Este servidor no tiene la extensión "zip" de PHP, necesaria para leer .xlsx. Use el formato .csv.');
        }
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) throw new HojaCalculoError('El archivo no es un Excel (.xlsx) válido');

        // Los errores del lector XML se recogen aqui (no salen como avisos de PHP) y se convierten en un error claro
        $erroresAnteriores = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            if ($zip->numFiles > self::MAX_ENTRADAS_ZIP) throw new HojaCalculoError('El archivo no es un Excel (.xlsx) válido');

            $parte = function ($nombre) use ($zip) {
                $st = $zip->statName($nombre);
                if ($st === false) return null;
                if ($st['size'] > self::MAX_BYTES_XML) throw new HojaCalculoError('El archivo es demasiado grande');
                $contenido = $zip->getFromName($nombre);
                return $contenido === false ? null : $contenido;
            };

            $libro = $parte('xl/workbook.xml');
            if ($libro === null) throw new HojaCalculoError('El archivo no es un Excel (.xlsx) válido');

            // 1) Que hoja leer: la llamada "Pacientes"; si no existe, la primera
            $hojas = [];   // [ ['nombre' => ..., 'rid' => ...] ]
            $r = self::lector($libro);
            while ($r->read()) {
                if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'sheet') {
                    $hojas[] = ['nombre' => (string)$r->getAttribute('name'),
                                'rid'    => (string)$r->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')];
                }
            }
            $r->close();
            if (!$hojas) throw new HojaCalculoError('El Excel no tiene hojas');
            $elegida = $hojas[0];
            foreach ($hojas as $h) if (self::normalizar($h['nombre']) === 'pacientes') { $elegida = $h; break; }

            // 2) Ruta de esa hoja
            $rels = $parte('xl/_rels/workbook.xml.rels');
            $destino = null;
            if ($rels !== null) {
                $r = self::lector($rels);
                while ($r->read()) {
                    if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'Relationship' && $r->getAttribute('Id') === $elegida['rid']) {
                        $destino = (string)$r->getAttribute('Target');
                        break;
                    }
                }
                $r->close();
            }
            if (!$destino) $destino = 'worksheets/sheet1.xml';
            $destino = ($destino[0] === '/') ? ltrim($destino, '/') : 'xl/' . $destino;
            $destino = preg_replace('#[^/]+/\.\./#', '', $destino);
            $xmlHoja = $parte($destino);
            if ($xmlHoja === null) throw new HojaCalculoError('No se pudo leer la hoja del Excel');

            // 3) Textos compartidos
            $textos = [];
            $ss = $parte('xl/sharedStrings.xml');
            if ($ss !== null) {
                $r = self::lector($ss);
                while ($r->read()) {
                    if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') {
                        $textos[] = self::textoDe($r->expand());
                    }
                }
                $r->close();
            }

            // 4) Celdas
            $filas = self::leerHoja($xmlHoja, $textos, $maxFilas);
            foreach (libxml_get_errors() as $e) {
                if ($e->level >= LIBXML_ERR_ERROR) throw new HojaCalculoError('El archivo no es un Excel (.xlsx) válido');
            }
            return $filas;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($erroresAnteriores);
            $zip->close();
        }
    }

    private static function leerHoja($xml, $textos, $maxFilas) {
        $r = self::lector($xml);
        $filas = [];
        $numFila = 0; $celdas = [];
        $ok = $r->read();
        while ($ok) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'row') {
                $numFila = (int)$r->getAttribute('r') ?: ($numFila + 1);
                $celdas = [];
                if ($r->isEmptyElement) { $ok = $r->read(); continue; }
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'row') {
                if ($celdas) {
                    $max = max(array_keys($celdas));
                    $densa = [];
                    for ($i = 0; $i <= $max; $i++) $densa[$i] = $celdas[$i] ?? '';
                    if (!self::filaVacia($densa)) {
                        $filas[] = ['fila' => $numFila, 'celdas' => $densa];
                        if (count($filas) > $maxFilas + 1) {
                            $r->close();
                            throw new HojaCalculoError("El archivo tiene demasiadas filas (máximo $maxFilas por carga)");
                        }
                    }
                }
                $celdas = [];
            } elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'c') {
                $ref  = (string)$r->getAttribute('r');
                $tipo = (string)$r->getAttribute('t');
                $col  = self::indiceColumna($ref);
                if ($col !== null && $col < self::MAX_COLUMNAS && !$r->isEmptyElement) {
                    $celdas[$col] = self::valorCelda($r->expand(), $tipo, $textos);
                }
                $ok = $r->next();                       // salta los hijos de la celda (ya leidos)
                continue;
            }
            $ok = $r->read();
        }
        $r->close();
        return $filas;
    }

    private static function valorCelda($nodo, $tipo, $textos) {
        $v = ''; $inline = '';
        foreach ($nodo->childNodes as $h) {
            if ($h->localName === 'v') $v = $h->textContent;
            elseif ($h->localName === 'is') $inline = self::textoDe($h);
        }
        switch ($tipo) {
            case 's':          return trim((string)($textos[(int)$v] ?? ''));
            case 'inlineStr':  return trim($inline);
            case 'str':        return trim($v);
            case 'b':          return $v === '1' ? '1' : '0';
            case 'e':          return '';
            default:           return self::numeroTexto($v);
        }
    }

    /** Un numero de Excel llega como 7.5335753E7 o 45678.0: se deja como texto "limpio". */
    private static function numeroTexto($v) {
        $v = trim($v);
        if ($v === '' || !preg_match('/^-?\d+(\.\d+)?([eE][+-]?\d+)?$/', $v)) return $v;
        $f = (float)$v;
        if (floor($f) == $f && abs($f) < 1e15) return (string)(int)$f;
        return $v;
    }

    private static function textoDe($nodo) {
        $t = '';
        foreach ($nodo->childNodes as $h) {
            if ($h->localName === 't') $t .= $h->textContent;
            elseif ($h->localName === 'r') {                                // texto con formato mixto
                foreach ($h->childNodes as $x) if ($x->localName === 't') $t .= $x->textContent;
            }                                                               // (rPh = lectura fonetica: se ignora)
        }
        return $t;
    }

    private static function lector($xml) {
        $r = new XMLReader();
        // LIBXML_NONET: nunca entrar a la red. Sin LIBXML_NOENT: las entidades NO se expanden.
        if (!$r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) throw new HojaCalculoError('El archivo no es un Excel (.xlsx) válido');
        return $r;
    }

    private static function indiceColumna($ref) {
        if (!preg_match('/^([A-Z]{1,3})\d+$/', $ref, $m)) return null;
        $n = 0;
        foreach (str_split($m[1]) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n - 1;
    }

    private static function filaVacia($celdas) {
        foreach ($celdas as $c) if (trim((string)$c) !== '') return false;
        return true;
    }

    /** minusculas, sin tildes ni signos: "Fecha de Nacimiento" -> "fecha_de_nacimiento" */
    public static function normalizar($s) {
        $s = mb_strtolower(trim((string)$s), 'UTF-8');
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim($s, '_');
    }

    /** Numero de serie de Excel (45678) o texto de fecha -> 'AAAA-MM-DD'; null si no se entiende */
    public static function fecha($valor) {
        $valor = trim((string)$valor);
        if ($valor === '') return null;
        if (preg_match('/^\d{1,6}(\.\d+)?$/', $valor)) {                                    // fecha de Excel
            $serie = (float)$valor;
            if ($serie < 1 || $serie > 2958465) return null;
            $d = gmdate('Y-m-d', (int)(($serie - 25569) * 86400));
            return $d;
        }
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $valor, $m)) {          // DD/MM/AAAA
            [$d, $mes, $a] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } elseif (preg_match('#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})(?:[ T].*)?$#', $valor, $m)) { // AAAA-MM-DD
            [$a, $mes, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } else return null;
        return checkdate($mes, $d, $a) ? sprintf('%04d-%02d-%02d', $a, $mes, $d) : null;
    }

    // =====================================================================
    // Escritura de .xlsx (plantilla)
    // =====================================================================
    /**
     * $hojas = [ [
     *   'nombre'    => 'Pacientes',
     *   'filas'     => [ ['Col A', 'Col B'], ['dato', 'dato'] ],   // la primera es el encabezado
     *   'anchos'    => [18, 30],                                    // opcional
     *   'texto'     => [0, 1],                                      // columnas con formato TEXTO (documentos, telefonos)
     *   'listas'    => [ 5 => "Listas!\$A\$2:\$A\$4" ],             // columna => rango de la lista desplegable
     *   'desde_fila'=> 2, 'hasta_fila' => 1001,                     // alcance de las listas
     *   'estilos'   => ['titulo' => [0]]                            // filas (0-based) en negrita
     * ] ]
     */
    public static function escribirXlsx($ruta, array $hojas) {
        if (!class_exists('ZipArchive')) throw new HojaCalculoError('Este servidor no tiene la extensión "zip" de PHP');
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new HojaCalculoError('No se pudo crear el archivo');

        $tipos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
               . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
               . '<Default Extension="xml" ContentType="application/xml"/>'
               . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
               . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $libro = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($hojas as $i => $h) {
            $n = $i + 1;
            $nombre = preg_replace('/[\[\]:*?\/\\\\]/', ' ', $h['nombre']);
            $tipos .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $libro .= '<sheet name="' . self::xml(mb_substr($nombre, 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $rels  .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', self::xmlHoja($h));
        }
        $total = count($hojas);
        $rels .= '<Relationship Id="rId' . ($total + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $zip->addFromString('[Content_Types].xml', $tipos . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $libro . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/styles.xml', self::xmlEstilos());
        $zip->close();
    }

    private static function xmlEstilos() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>'
             . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
             . '<font><b/><sz val="12"/><name val="Calibri"/></font></fonts>'
             . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
             . '<fill><patternFill patternType="solid"><fgColor rgb="FF2A4D8F"/><bgColor indexed="64"/></patternFill></fill></fills>'
             . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="5">'
             . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                           // 0 normal
             . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                   // 1 texto
             . '<xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf>' // 2 encabezado
             . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>' // 3 texto largo
             . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                            // 4 titulo
             . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function xmlHoja($h) {
        $filas = $h['filas']; $texto = $h['texto'] ?? []; $anchos = $h['anchos'] ?? [];
        $titulo = $h['estilos']['titulo'] ?? []; $largo = $h['estilos']['largo'] ?? [];
        $enc = !isset($h['encabezado']) || $h['encabezado'];
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($enc) $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        if ($anchos) {
            $x .= '<cols>';
            foreach ($anchos as $i => $w) {
                $estilo = in_array($i, $texto, true) ? ' style="1"' : '';
                $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"' . $estilo . '/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach ($filas as $f => $fila) {
            $x .= '<row r="' . ($f + 1) . '"' . ($f === 0 && $enc ? ' ht="32" customHeight="1"' : '') . '>';
            foreach ($fila as $c => $valor) {
                if ($valor === null || $valor === '') continue;
                $ref = self::letraColumna($c) . ($f + 1);
                $estilo = ($f === 0 && $enc) ? 2 : (in_array($f, $titulo, true) ? 4 : (in_array($f, $largo, true) ? 3 : (in_array($c, $texto, true) ? 1 : 0)));
                $x .= '<c r="' . $ref . '" s="' . $estilo . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml((string)$valor) . '</t></is></c>';
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (!empty($h['listas'])) {
            $desde = $h['desde_fila'] ?? 2; $hasta = $h['hasta_fila'] ?? 1001;
            $x .= '<dataValidations count="' . count($h['listas']) . '">';
            foreach ($h['listas'] as $col => $rango) {
                $l = self::letraColumna($col);
                $x .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Valor no válido" error="Elija un valor de la lista." sqref="' . $l . $desde . ':' . $l . $hasta . '"><formula1>' . self::xml($rango) . '</formula1></dataValidation>';
            }
            $x .= '</dataValidations>';
        }
        return $x . '</worksheet>';
    }

    private static function letraColumna($i) {
        $s = '';
        for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) $s = chr(65 + (($n - 1) % 26)) . $s;
        return $s;
    }

    private static function xml($s) {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);          // caracteres que XML no admite
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
