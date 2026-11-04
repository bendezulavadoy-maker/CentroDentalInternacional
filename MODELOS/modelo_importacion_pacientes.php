<?php
require_once __DIR__ . '/../CONFIG/conexion.php';
require_once __DIR__ . '/../CONFIG/hoja_calculo.php';

/**
 * Importacion masiva de pacientes desde Excel/CSV.
 *
 * Reglas (las mismas del formulario de Pacientes):
 *  - Cada fila se valida por separado: las filas buenas se guardan y las malas se listan con su motivo.
 *  - Un paciente que YA existe (mismo tipo + numero de documento) se omite sin tocarlo y no cuenta como error.
 *  - Los menores de edad exigen apoderado.
 *  - Nada de lo importado recibe acceso al portal ni mensajes automaticos.
 *  - Cada carga queda registrada y se puede deshacer mientras los pacientes no tengan actividad.
 */
class ModeloImportacionPacientes {

    const MAX_FILAS = 1000;

    /** nombre interno => nombres de columna aceptados (ya normalizados: minusculas, sin tildes) */
    const COLUMNAS = [
        'tipo_documento'    => ['tipo_documento', 'tipo_de_documento', 'tipo_doc', 'tipo'],
        'nro_documento'     => ['nro_documento', 'numero_documento', 'numero_de_documento', 'n_documento', 'nro_doc', 'documento', 'dni'],
        'nombres'           => ['nombres', 'nombre', 'nombre_s'],
        'apellidos'         => ['apellidos', 'apellido'],
        'fecha_nacimiento'  => ['fecha_nacimiento', 'fecha_de_nacimiento', 'nacimiento', 'f_nacimiento'],
        'sexo'              => ['sexo', 'genero'],
        'estado_civil'      => ['estado_civil'],
        'grado_instruccion' => ['grado_instruccion', 'grado_de_instruccion', 'instruccion'],
        'ocupacion'         => ['ocupacion'],
        'telefono'          => ['telefono', 'celular', 'telefono_celular'],
        'direccion'         => ['direccion', 'domicilio'],
        'correo'            => ['correo', 'correo_electronico', 'email', 'e_mail'],
        'observaciones'     => ['observaciones', 'observacion', 'notas'],
        'odontologo'        => ['codigo_odontologo', 'codigo_odontologo_responsable', 'odontologo_responsable', 'odontologo', 'codigo_doctor'],
        'ap_tipo_documento' => ['apoderado_tipo_documento', 'ap_tipo_documento', 'tipo_documento_apoderado'],
        'ap_nro_documento'  => ['apoderado_nro_documento', 'apoderado_numero_documento', 'ap_nro_documento', 'nro_documento_apoderado'],
        'ap_nombres'        => ['apoderado_nombres', 'apoderado_nombre', 'ap_nombres'],
        'ap_apellidos'      => ['apoderado_apellidos', 'apoderado_apellido', 'ap_apellidos'],
        'ap_telefono'       => ['apoderado_telefono', 'ap_telefono', 'telefono_apoderado'],
        'ap_parentesco'     => ['apoderado_parentesco', 'parentesco', 'ap_parentesco', 'apoderado_relacion'],
    ];
    const OBLIGATORIAS = ['tipo_documento', 'nro_documento', 'nombres', 'apellidos', 'fecha_nacimiento',
                          'sexo', 'estado_civil', 'telefono', 'direccion'];
    const COLUMNAS_APODERADO = ['ap_tipo_documento', 'ap_nro_documento', 'ap_nombres', 'ap_apellidos', 'ap_telefono', 'ap_parentesco'];

    private $db;
    private $cat = null;

    public function __construct() {
        $this->db = (new Conexion())->getConexion();
    }

    // =====================================================================
    // Catalogos
    // =====================================================================
    public function catalogos() {
        if ($this->cat !== null) return $this->cat;
        $n = fn($s) => HojaCalculo::normalizar($s);
        $cat = ['tipos' => [], 'sexo' => [], 'estado_civil' => [], 'grado' => [], 'familiar' => [], 'doctores' => [],
                'lista_tipos' => [], 'lista_sexo' => [], 'lista_estado_civil' => [], 'lista_grado' => [], 'lista_familiar' => [], 'lista_doctores' => []];

        foreach ($this->db->query("SELECT * FROM tipo_documento WHERE activo = 1 ORDER BY id_tipo_documento") as $t) {
            $cat['tipos'][$n($t['abreviatura'])] = $t;
            $cat['tipos'][$n($t['nombre'])]      = $t;
            $cat['lista_tipos'][] = $t;
        }
        foreach ($this->db->query("SELECT id_sexo, nombre_sexo FROM sexo ORDER BY id_sexo") as $f) {
            $cat['sexo'][$n($f['nombre_sexo'])] = (int)$f['id_sexo'];
            $cat['lista_sexo'][] = $f['nombre_sexo'];
        }
        foreach ($this->db->query("SELECT id_estado_civil, nombre_estado_civil FROM estado_civil ORDER BY id_estado_civil") as $f) {
            $cat['estado_civil'][$n($f['nombre_estado_civil'])] = (int)$f['id_estado_civil'];
            $cat['estado_civil'][$n(preg_replace('/\(.*\)/', '', $f['nombre_estado_civil']))] = (int)$f['id_estado_civil'];   // "Soltero" = "Soltero(a)"
            $cat['lista_estado_civil'][] = $f['nombre_estado_civil'];
        }
        foreach ($this->db->query("SELECT id_grado_instruccion, nombre_grado_instruccion FROM grado_instruccion ORDER BY id_grado_instruccion") as $f) {
            $cat['grado'][$n($f['nombre_grado_instruccion'])] = (int)$f['id_grado_instruccion'];
            $cat['lista_grado'][] = $f['nombre_grado_instruccion'];
        }
        foreach ($this->db->query("SELECT id_tipo_familiar, descripcion FROM tipo_familiar ORDER BY id_tipo_familiar") as $f) {
            $cat['familiar'][$n($f['descripcion'])] = (int)$f['id_tipo_familiar'];
            $cat['lista_familiar'][] = $f['descripcion'];
        }
        foreach ($this->db->query("SELECT id_usuario, codigo_usuario, nombre, apellidos FROM usuarios WHERE id_rol = 2 AND id_estado = 1 ORDER BY nombre, apellidos") as $f) {
            $cat['doctores'][strtoupper($f['codigo_usuario'])] = (int)$f['id_usuario'];
            $cat['lista_doctores'][] = $f['codigo_usuario'] . ' - ' . $f['nombre'] . ' ' . $f['apellidos'];
        }
        return $this->cat = $cat;
    }

    // =====================================================================
    // Lectura del archivo -> filas con nombres internos
    // =====================================================================
    /**
     * @return array ['filas' => [ ['fila' => 2, 'datos' => ['nro_documento' => '...', ...]], ... ], 'ignoradas' => ['columna rara', ...]]
     * @throws HojaCalculoError
     */
    public function leerArchivo($ruta, $nombreOriginal) {
        $crudas = HojaCalculo::leer($ruta, $nombreOriginal, self::MAX_FILAS);
        if (!$crudas) throw new HojaCalculoError('El archivo está vacío');

        // La primera fila no vacia es el encabezado
        $encabezado = array_shift($crudas);
        $mapa = []; $ignoradas = [];
        foreach ($encabezado['celdas'] as $i => $texto) {
            $norm = HojaCalculo::normalizar($texto);
            if ($norm === '') continue;
            $interno = null;
            foreach (self::COLUMNAS as $nombre => $alias) {
                if (in_array($norm, $alias, true)) { $interno = $nombre; break; }
            }
            if ($interno === null) $ignoradas[] = trim($texto);
            elseif (!isset($mapa[$interno])) $mapa[$interno] = $i;
        }
        if (!$mapa) throw new HojaCalculoError('No se reconocieron las columnas del archivo. Use la plantilla que se descarga en esta pantalla.');
        $faltan = array_values(array_diff(self::OBLIGATORIAS, array_keys($mapa)));
        if ($faltan) throw new HojaCalculoError('Faltan columnas obligatorias: ' . implode(', ', $faltan) . '. Use la plantilla de esta pantalla.');

        if (count($crudas) > self::MAX_FILAS) {
            throw new HojaCalculoError('El archivo tiene demasiadas filas (máximo ' . self::MAX_FILAS . ' por carga). Divídalo en partes.');
        }
        $filas = [];
        foreach ($crudas as $c) {
            $d = [];
            foreach ($mapa as $nombre => $i) $d[$nombre] = $this->limpiar($c['celdas'][$i] ?? '');
            $filas[] = ['fila' => $c['fila'], 'datos' => $d];
        }
        return ['filas' => $filas, 'ignoradas' => $ignoradas];
    }

    private function limpiar($v) {
        $v = str_replace("\xC2\xA0", ' ', (string)$v);                    // espacio duro de Excel
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
        return trim(preg_replace('/\s+/u', ' ', $v));
    }

    // =====================================================================
    // Validacion de UNA fila
    // =====================================================================
    /**
     * @param array $vistos  memoria de la carga (documentos y correos ya vistos en el archivo); se actualiza
     * @return array ['estado' => 'valida'|'existe'|'error', 'errores' => [...], 'registro' => [...]|null, 'nombre' => '...']
     */
    public function validarFila(array $d, array &$vistos, $numFila) {
        $cat = $this->catalogos();
        $e = [];
        $val = fn($k) => $d[$k] ?? '';
        $nombreMostrado = trim($val('nombres') . ' ' . $val('apellidos'));

        // ---- Documento ----
        $tipo = $cat['tipos'][HojaCalculo::normalizar($val('tipo_documento'))] ?? null;
        if (!$tipo) {
            $listado = implode(', ', array_map(fn($t) => $t['abreviatura'], $cat['lista_tipos']));
            $e[] = $val('tipo_documento') === '' ? 'Falta el tipo de documento' : 'Tipo de documento «' . $val('tipo_documento') . '» no existe (use: ' . $listado . ')';
        }
        $numero = strtoupper(str_replace(' ', '', $val('nro_documento')));
        if ($tipo) {
            $m = $this->mensajeDocumento($tipo, $numero);
            if ($m) $e[] = $m;
        } elseif ($numero === '') {
            $e[] = 'Falta el número de documento';
        }
        $docValido = $tipo && !$this->mensajeDocumento($tipo, $numero);

        // ---- ¿Ya existe en el sistema? -> se omite sin mas validaciones ----
        $clave = $docValido ? ((int)$tipo['id_tipo_documento'] . '|' . $numero) : null;
        if ($docValido) {
            if (isset($vistos['docs'][$clave])) {
                if ($vistos['docs'][$clave]['existe']) return ['estado' => 'existe', 'errores' => [], 'registro' => null, 'nombre' => $nombreMostrado];
                $e[] = 'Repetido: el mismo documento ya aparece en la fila ' . $vistos['docs'][$clave]['fila'];
            } elseif ($this->existePaciente((int)$tipo['id_tipo_documento'], $numero)) {
                $vistos['docs'][$clave] = ['fila' => $numFila, 'existe' => true];
                return ['estado' => 'existe', 'errores' => [], 'registro' => null, 'nombre' => $nombreMostrado];
            }
        }

        // ---- Nombres ----
        foreach (['nombres' => 'Nombres', 'apellidos' => 'Apellidos'] as $k => $etq) {
            $m = $this->mensajeNombre($val($k), $etq);
            if ($m) $e[] = $m;
        }

        // ---- Fecha de nacimiento ----
        $fecha = null; $edad = null;
        if ($val('fecha_nacimiento') === '') $e[] = 'Falta la fecha de nacimiento';
        else {
            $fecha = HojaCalculo::fecha($val('fecha_nacimiento'));
            if (!$fecha) $e[] = 'Fecha de nacimiento no válida «' . $val('fecha_nacimiento') . '» (use DD/MM/AAAA)';
            elseif ($fecha > date('Y-m-d')) { $e[] = 'La fecha de nacimiento no puede ser futura'; $fecha = null; }
            elseif ((int)substr($fecha, 0, 4) < 1900) { $e[] = 'La fecha de nacimiento no es válida (año anterior a 1900)'; $fecha = null; }
            else $edad = (int)date_diff(date_create($fecha), date_create('today'))->y;
        }

        // ---- Catalogos ----
        $idSexo = $this->buscarSexo($val('sexo'), $cat);
        if ($val('sexo') === '') $e[] = 'Falta el sexo';
        elseif (!$idSexo) $e[] = 'Sexo «' . $val('sexo') . '» no válido (use: ' . implode(', ', $cat['lista_sexo']) . ')';

        $idEstado = $cat['estado_civil'][HojaCalculo::normalizar($val('estado_civil'))] ?? null;
        if ($val('estado_civil') === '') $e[] = 'Falta el estado civil';
        elseif (!$idEstado) $e[] = 'Estado civil «' . $val('estado_civil') . '» no válido (use: ' . implode(', ', $cat['lista_estado_civil']) . ')';

        $idGrado = null;
        if ($val('grado_instruccion') !== '') {
            $idGrado = $cat['grado'][HojaCalculo::normalizar($val('grado_instruccion'))] ?? null;
            if (!$idGrado) $e[] = 'Grado de instrucción «' . $val('grado_instruccion') . '» no válido';
        }

        // ---- Contacto ----
        $telefono = $this->telefono($val('telefono'));
        if ($val('telefono') === '') $e[] = 'Falta el teléfono';
        elseif (!$telefono) $e[] = 'El teléfono debe tener exactamente 9 dígitos';

        if ($val('direccion') === '') $e[] = 'Falta la dirección';
        elseif ($this->tieneEtiquetas($val('direccion')) || mb_strlen($val('direccion')) > 255) $e[] = 'La dirección no es válida (máx. 255 caracteres, sin < ni >)';

        $correo = null;
        if ($val('correo') !== '') {
            $c = $val('correo');
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/', $c) || !filter_var($c, FILTER_VALIDATE_EMAIL) || strlen($c) > 150) {
                $e[] = 'El formato del correo no es válido';
            } else {
                $minus = strtolower($c);
                if (isset($vistos['correos'][$minus])) $e[] = 'El correo está repetido en la fila ' . $vistos['correos'][$minus];
                elseif ($this->existeCorreo($c)) $e[] = 'El correo ya está registrado en el sistema';
                else $correo = $c;
            }
        }
        $ocupacion = $val('ocupacion');
        if ($this->tieneEtiquetas($ocupacion) || mb_strlen($ocupacion) > 100) $e[] = 'La ocupación no es válida (máx. 100 caracteres, sin < ni >)';
        if ($this->tieneEtiquetas($val('observaciones'))) $e[] = 'Las observaciones no pueden contener < ni >';

        // ---- Odontologo responsable (opcional) ----
        $idDoctor = null;
        if ($val('odontologo') !== '') {
            $idDoctor = $cat['doctores'][strtoupper($val('odontologo'))] ?? null;
            if (!$idDoctor) $e[] = 'El código de odontólogo «' . $val('odontologo') . '» no existe o no es un odontólogo activo';
        }

        // ---- Apoderado ----
        $apoderado = null;
        $hayApoderado = false;
        foreach (self::COLUMNAS_APODERADO as $k) if ($val($k) !== '') $hayApoderado = true;
        if ($edad !== null && $edad < 18 && !$hayApoderado) {
            $e[] = 'Es menor de edad: faltan los datos del apoderado';
        } elseif ($hayApoderado) {
            $ea = [];
            $tipoAp = $cat['tipos'][HojaCalculo::normalizar($val('ap_tipo_documento'))] ?? null;
            if ($val('ap_tipo_documento') === '') $ea[] = 'tipo de documento';
            elseif (!$tipoAp) $ea[] = 'tipo de documento «' . $val('ap_tipo_documento') . '» no existe';
            $numAp = strtoupper(str_replace(' ', '', $val('ap_nro_documento')));
            if ($tipoAp) { $m = $this->mensajeDocumento($tipoAp, $numAp); if ($m) $ea[] = $m; }
            elseif ($numAp === '') $ea[] = 'número de documento';
            foreach (['ap_nombres' => 'nombres', 'ap_apellidos' => 'apellidos'] as $k => $etq) {
                $m = $this->mensajeNombre($val($k), ucfirst($etq)); if ($m) $ea[] = mb_strtolower($m, 'UTF-8');
            }
            $telAp = $this->telefono($val('ap_telefono'));
            if (!$telAp) $ea[] = 'teléfono (9 dígitos)';
            $idParentesco = $cat['familiar'][HojaCalculo::normalizar($val('ap_parentesco'))] ?? null;
            if ($val('ap_parentesco') === '') $ea[] = 'parentesco';
            elseif (!$idParentesco) $ea[] = 'parentesco «' . $val('ap_parentesco') . '» no válido (use: ' . implode(', ', $cat['lista_familiar']) . ')';
            if ($tipo && $tipoAp && $numAp !== '' && $docValido && (int)$tipoAp['id_tipo_documento'] === (int)$tipo['id_tipo_documento'] && $numAp === $numero) {
                $ea[] = 'el apoderado no puede ser el mismo paciente';
            }
            if ($ea) $e[] = 'Apoderado: ' . implode('; ', $ea);
            else $apoderado = ['id_tipo_documento' => (int)$tipoAp['id_tipo_documento'], 'nro_documento' => $numAp,
                               'nombre' => mb_strtoupper($val('ap_nombres'), 'UTF-8'), 'apellido' => mb_strtoupper($val('ap_apellidos'), 'UTF-8'),
                               'telefono' => $telAp, 'id_tipo_familiar' => $idParentesco];
        }

        // Memoria del archivo (solo los documentos validos se registran; asi un error no "reserva" el documento)
        if ($e) return ['estado' => 'error', 'errores' => $e, 'registro' => null, 'nombre' => $nombreMostrado];

        $vistos['docs'][$clave] = ['fila' => $numFila, 'existe' => false];
        if ($correo) $vistos['correos'][strtolower($correo)] = $numFila;
        return ['estado' => 'valida', 'errores' => [], 'nombre' => $nombreMostrado, 'registro' => [
            'nombre'            => mb_strtoupper($val('nombres'), 'UTF-8'),
            'apellido'          => mb_strtoupper($val('apellidos'), 'UTF-8'),
            'id_tipo_documento' => (int)$tipo['id_tipo_documento'],
            'nro_documento'     => $numero,
            'fecha_nacimiento'  => $fecha,
            'telefono'          => $telefono,
            'direccion'         => $val('direccion'),
            'correo'            => $correo,
            'id_estado_civil'   => $idEstado,
            'id_sexo'           => $idSexo,
            'id_grado_instruccion' => $idGrado,
            'ocupacion'         => $ocupacion,
            'observaciones'     => $val('observaciones') === '' ? null : $val('observaciones'),
            'id_doctor_responsable' => $idDoctor,
            'apoderado'         => $apoderado,
        ]];
    }

    /** Misma regla que el formulario de Pacientes (validarDocumento) */
    private function mensajeDocumento($tipo, $numero) {
        $abrev = $tipo['abreviatura']; $min = (int)$tipo['longitud_min']; $max = (int)$tipo['longitud_max'];
        $soloNum = (int)$tipo['solo_numeros'] === 1;
        if ($numero === '') return "Falta el número de $abrev";
        if ($soloNum && !preg_match('/^[0-9]+$/', $numero)) return "El $abrev solo admite dígitos";
        if (!$soloNum && !preg_match('/^[A-Za-z0-9]+$/', $numero)) return "El $abrev solo admite letras y números";
        $largo = strlen($numero);
        if ($largo < $min || $largo > $max) {
            $unidad = $soloNum ? 'dígitos' : 'caracteres';
            $texto = ($min === $max) ? "exactamente $min $unidad" : "entre $min y $max $unidad";
            $pista = ($soloNum && $largo < $min && $largo >= $min - 2) ? ' (si empieza con 0, formatee la columna como Texto en Excel)' : '';
            return "El $abrev debe tener $texto$pista";
        }
        if ($tipo['codigo'] === '1' && in_array($numero, ['00000000','11111111','22222222','33333333','44444444','55555555','66666666','77777777','88888888','99999999'], true)) {
            return 'El DNI ingresado no es válido';
        }
        return null;
    }

    private function mensajeNombre($v, $campo) {
        if ($v === '') return "Falta $campo";
        if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/u', $v)) return "$campo solo puede contener letras, espacios y acentos";
        if (mb_strlen($v) < 2) return "$campo debe tener al menos 2 caracteres";
        if (mb_strlen($v) > 100) return "$campo es demasiado largo (máx. 100)";
        return null;
    }

    private function telefono($v) {
        $v = preg_replace('/[\s\-\(\)\.]/', '', (string)$v);
        if (preg_match('/^(\+?51)(\d{9})$/', $v, $m)) $v = $m[2];                 // +51 987654321
        return preg_match('/^[0-9]{9}$/', $v) ? $v : null;
    }

    private function tieneEtiquetas($v) { return strpbrk((string)$v, '<>') !== false; }

    private function buscarSexo($v, $cat) {
        $n = HojaCalculo::normalizar($v);
        if ($n === '') return null;
        if (isset($cat['sexo'][$n])) return $cat['sexo'][$n];
        if ($n === 'm') return $cat['sexo']['masculino'] ?? null;
        if ($n === 'f') return $cat['sexo']['femenino'] ?? null;
        return null;
    }

    private function existePaciente($idTipo, $numero) {
        $s = $this->db->prepare("SELECT 1 FROM pacientes WHERE id_tipo_documento = :t AND nro_documento = :n LIMIT 1");
        $s->execute([':t' => $idTipo, ':n' => $numero]);
        return (bool)$s->fetchColumn();
    }

    private function existeCorreo($correo) {
        $s = $this->db->prepare("SELECT 1 FROM pacientes WHERE correo = :c LIMIT 1");
        $s->execute([':c' => $correo]);
        return (bool)$s->fetchColumn();
    }

    // =====================================================================
    // Revision (no guarda nada)
    // =====================================================================
    public function analizar(array $filas) {
        $vistos = ['docs' => [], 'correos' => []];
        $res = ['total' => count($filas), 'validas' => 0, 'existentes' => 0, 'con_error' => 0, 'errores' => []];
        foreach ($filas as $f) {
            $r = $this->validarFila($f['datos'], $vistos, $f['fila']);
            if ($r['estado'] === 'valida') $res['validas']++;
            elseif ($r['estado'] === 'existe') $res['existentes']++;
            else { $res['con_error']++; $res['errores'][] = ['fila' => $f['fila'], 'paciente' => $r['nombre'], 'documento' => trim(($f['datos']['tipo_documento'] ?? '') . ' ' . ($f['datos']['nro_documento'] ?? '')), 'problemas' => $r['errores']]; }
        }
        return $res;
    }

    // =====================================================================
    // Importacion
    // =====================================================================
    public function importar(array $filas, $nombreArchivo, $idUsuario) {
        $nombreArchivo = mb_substr(preg_replace('/[^\p{L}\p{N}._\- ()]/u', '_', (string)$nombreArchivo), 0, 150);
        $this->db->prepare("INSERT INTO cargas_masivas (tipo, nombre_archivo, total_filas, id_usuario) VALUES ('pacientes', :n, :t, :u)")
                 ->execute([':n' => $nombreArchivo, ':t' => count($filas), ':u' => $idUsuario ?: null]);
        $idCarga = (int)$this->db->lastInsertId();

        $vistos = ['docs' => [], 'correos' => []];
        $res = ['id_carga' => $idCarga, 'total' => count($filas), 'importados' => 0, 'existentes' => 0, 'con_error' => 0, 'errores' => []];

        foreach ($filas as $f) {
            $r = $this->validarFila($f['datos'], $vistos, $f['fila']);
            if ($r['estado'] === 'existe') { $res['existentes']++; continue; }
            if ($r['estado'] === 'valida') {
                try {
                    $this->insertarPaciente($r['registro'], $idCarga);
                    $res['importados']++;
                    continue;
                } catch (PDOException $ex) {
                    if (strpos($ex->getMessage(), '1062') !== false) { $res['existentes']++; continue; }     // alguien lo registro mientras tanto
                    error_log('Importacion de pacientes, fila ' . $f['fila'] . ': ' . $ex->getMessage());
                    $r['errores'] = ['No se pudo guardar esta fila (error del sistema)'];
                }
            }
            $res['con_error']++;
            $res['errores'][] = ['fila' => $f['fila'], 'paciente' => $r['nombre'], 'documento' => trim(($f['datos']['tipo_documento'] ?? '') . ' ' . ($f['datos']['nro_documento'] ?? '')), 'problemas' => $r['errores']];
        }

        $this->db->prepare("UPDATE cargas_masivas SET importados = :i, omitidos = :o, con_error = :e WHERE id_carga = :c")
                 ->execute([':i' => $res['importados'], ':o' => $res['existentes'], ':e' => $res['con_error'], ':c' => $idCarga]);
        return $res;
    }

    /** Un paciente y su apoderado: todo o nada para esa fila */
    private function insertarPaciente(array $r, $idCarga) {
        $this->db->beginTransaction();
        try {
            $idApoderado = null;
            if ($r['apoderado']) {
                $a = $r['apoderado'];
                $s = $this->db->prepare("SELECT id_apoderado FROM apoderados WHERE id_tipo_documento = :t AND nro_documento = :n LIMIT 1");
                $s->execute([':t' => $a['id_tipo_documento'], ':n' => $a['nro_documento']]);
                $idApoderado = $s->fetchColumn();
                if (!$idApoderado) {                                               // si ya existe se reutiliza SIN modificarlo
                    $this->db->prepare("INSERT INTO apoderados (nombre, apellido, id_tipo_documento, nro_documento, id_tipo_familiar, telefono, id_carga_masiva)
                                        VALUES (:nombre, :apellido, :t, :n, :f, :tel, :c)")
                             ->execute([':nombre' => $a['nombre'], ':apellido' => $a['apellido'], ':t' => $a['id_tipo_documento'], ':n' => $a['nro_documento'],
                                        ':f' => $a['id_tipo_familiar'], ':tel' => $a['telefono'], ':c' => $idCarga]);
                    $idApoderado = $this->db->lastInsertId();
                }
            }
            $this->db->prepare("INSERT INTO pacientes (nombre, apellido, id_tipo_documento, nro_documento, fecha_nacimiento, telefono, direccion, correo,
                                    fecha_registro, foto, id_estado_civil, id_sexo, id_grado_instruccion, ocupacion, observaciones, id_apoderado,
                                    id_doctor_responsable, id_carga_masiva)
                                VALUES (:nombre, :apellido, :t, :n, :fn, :tel, :dir, :correo, :fr, NULL, :ec, :sx, :gr, :ocu, :obs, :apo, :doc, :carga)")
                     ->execute([':nombre' => $r['nombre'], ':apellido' => $r['apellido'], ':t' => $r['id_tipo_documento'], ':n' => $r['nro_documento'],
                                ':fn' => $r['fecha_nacimiento'], ':tel' => $r['telefono'], ':dir' => $r['direccion'], ':correo' => $r['correo'],
                                ':fr' => date('Y-m-d H:i:s'), ':ec' => $r['id_estado_civil'], ':sx' => $r['id_sexo'], ':gr' => $r['id_grado_instruccion'],
                                ':ocu' => $r['ocupacion'], ':obs' => $r['observaciones'], ':apo' => $idApoderado ?: null,
                                ':doc' => $r['id_doctor_responsable'], ':carga' => $idCarga]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // =====================================================================
    // Historial y deshacer
    // =====================================================================
    public function historial($limite = 15) {
        $s = $this->db->prepare(
            "SELECT c.id_carga, c.nombre_archivo, c.total_filas, c.importados, c.omitidos, c.con_error, c.deshechos, c.estado,
                    c.fecha_carga, c.fecha_reversion,
                    CONCAT(u.nombre, ' ', u.apellidos) AS usuario,
                    (SELECT COUNT(*) FROM pacientes p WHERE p.id_carga_masiva = c.id_carga) AS vigentes
             FROM cargas_masivas c
             LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
             WHERE c.tipo = 'pacientes'
             ORDER BY c.id_carga DESC LIMIT " . (int)$limite);
        $s->execute();
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deshace una carga: elimina los pacientes (y apoderados) que nacieron en ella,
     * SALVO los que ya tienen actividad (citas, pagos, planes, documentos, historia con datos...).
     * Esos se conservan y se informan.
     */
    public function revertir($idCarga, $idUsuario) {
        $s = $this->db->prepare("SELECT * FROM cargas_masivas WHERE id_carga = :c AND tipo = 'pacientes'");
        $s->execute([':c' => $idCarga]);
        $carga = $s->fetch(PDO::FETCH_ASSOC);
        if (!$carga) return ['success' => false, 'mensaje' => 'La carga no existe'];
        if ($carga['estado'] === 'revertida') return ['success' => false, 'mensaje' => 'Esta carga ya fue deshecha'];

        // Condicion "sin actividad": ninguna tabla relacionada con datos del paciente
        $sinActividad = ["NOT EXISTS (SELECT 1 FROM citas x WHERE x.id_paciente = p.id_paciente)",
                         "NOT EXISTS (SELECT 1 FROM documentos_paciente x WHERE x.id_paciente = p.id_paciente)",
                         "NOT EXISTS (SELECT 1 FROM lista_espera x WHERE x.id_paciente = p.id_paciente)",
                         "NOT EXISTS (SELECT 1 FROM odontograma_versiones x WHERE x.id_paciente = p.id_paciente)",
                         "NOT EXISTS (SELECT 1 FROM paciente_alergias x WHERE x.id_paciente = p.id_paciente)",
                         "NOT EXISTS (SELECT 1 FROM paciente_planes x WHERE x.id_paciente = p.id_paciente)",
                         "NOT EXISTS (SELECT 1 FROM pagos x WHERE x.id_paciente = p.id_paciente)"];
        // La historia clinica se crea sola al abrirla: solo cuenta si tiene datos (en ella o en sus secciones)
        $hijas = $this->db->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                                   WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'historia_clinica'")->fetchAll(PDO::FETCH_ASSOC);
        $conDatos = ["COALESCE(h.motivo_consulta, '') <> ''", "COALESCE(h.notas, '') <> ''"];
        foreach ($hijas as $t) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $t['TABLE_NAME']) || !preg_match('/^[A-Za-z0-9_]+$/', $t['COLUMN_NAME'])) continue;
            $conDatos[] = "EXISTS (SELECT 1 FROM `{$t['TABLE_NAME']}` y WHERE y.`{$t['COLUMN_NAME']}` = h.id_historia)";
        }
        $sinActividad[] = "NOT EXISTS (SELECT 1 FROM historia_clinica h WHERE h.id_paciente = p.id_paciente AND (" . implode(' OR ', $conDatos) . "))";

        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare("SELECT p.id_paciente FROM pacientes p WHERE p.id_carga_masiva = :c AND " . implode(' AND ', $sinActividad));
            $q->execute([':c' => $idCarga]);
            $borrables = $q->fetchAll(PDO::FETCH_COLUMN);

            foreach ($borrables as $idP) {
                $this->db->prepare("DELETE FROM historia_clinica WHERE id_paciente = :p")->execute([':p' => $idP]);     // vacia (ya se comprobo)
                $this->db->prepare("DELETE FROM pacientes WHERE id_paciente = :p AND id_carga_masiva = :c")->execute([':p' => $idP, ':c' => $idCarga]);
            }
            // Apoderados creados por esta carga que ya no los usa nadie
            $this->db->prepare("DELETE FROM apoderados WHERE id_carga_masiva = :c
                                AND NOT EXISTS (SELECT 1 FROM pacientes p WHERE p.id_apoderado = apoderados.id_apoderado)")
                     ->execute([':c' => $idCarga]);

            $r = $this->db->prepare("SELECT COUNT(*) FROM pacientes WHERE id_carga_masiva = :c");
            $r->execute([':c' => $idCarga]);
            $quedan = (int)$r->fetchColumn();
            $deshechos = count($borrables);
            $this->db->prepare("UPDATE cargas_masivas SET deshechos = deshechos + :d, estado = :e, fecha_reversion = NOW(), id_usuario_reversion = :u WHERE id_carga = :c")
                     ->execute([':d' => $deshechos, ':e' => $quedan === 0 ? 'revertida' : 'parcial', ':u' => $idUsuario ?: null, ':c' => $idCarga]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Deshacer carga de pacientes: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo deshacer la carga (no se cambió nada)'];
        }
        return ['success' => true, 'deshechos' => $deshechos, 'conservados' => $quedan];
    }
}
