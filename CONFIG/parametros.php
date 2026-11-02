<?php
/**
 * PARAMETROS DEL SISTEMA
 *
 * Ajustes que el administrador cambia desde Configuracion sin tocar codigo.
 * Se guardan en la tabla configuracion_sistema. El catalogo de abajo define cuales
 * existen, de que tipo son y que valores se aceptan, de modo que un valor invalido
 * (por ejemplo permitir subir archivos .php) nunca llega a guardarse.
 *
 * Uso:
 *   Parametros::entero('papelera_dias', 15);
 *   Parametros::lista('documentos_extensiones', ['pdf']);
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/bitacora.php';

class Parametros {

    private static $cache = null;
    private static $pdo = null;

    // Extensiones que el administrador puede habilitar. Nunca se admiten ejecutables,
    // scripts, html ni svg, aunque alguien intente guardarlos.
    const EXTENSIONES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx',
                                    'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];

    public static function catalogo() {
        return [
            'papelera_dias' => [
                'titulo'      => 'Días en la papelera',
                'descripcion' => 'Días que un documento o carpeta eliminado permanece en la papelera antes de borrarse definitivamente.',
                'tipo'        => 'entero', 'min' => 1, 'max' => 365, 'unidad' => 'días', 'defecto' => 15,
            ],
            'documentos_max_mb' => [
                'titulo'      => 'Tamaño máximo por archivo',
                'descripcion' => 'Tamaño máximo de cada documento que se sube. Además lo limita la configuración del servidor.',
                'tipo'        => 'entero', 'min' => 1, 'max' => 100, 'unidad' => 'MB', 'defecto' => 10,
            ],
            'documentos_extensiones' => [
                'titulo'      => 'Tipos de archivo permitidos',
                'descripcion' => 'Extensiones de archivo que se pueden subir, separadas por coma.',
                'tipo'        => 'lista', 'permitidas' => self::EXTENSIONES_PERMITIDAS,
                'defecto'     => 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
        ];
    }

    private static function pdo() {
        if (self::$pdo === null) {
            self::$pdo = (new Conexion())->getConexion();
        }
        return self::$pdo;
    }

    private static function cargar() {
        if (self::$cache === null) {
            try {
                self::$cache = self::pdo()->query("SELECT clave, valor FROM configuracion_sistema")
                                          ->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Throwable $e) {
                error_log('Parametros::cargar: ' . $e->getMessage());
                return [];   // sin tabla: se usan los valores por defecto
            }
        }
        return self::$cache;
    }

    public static function limpiarCache() {
        self::$cache = null;
    }

    public static function get($clave, $porDefecto = null) {
        $todos = self::cargar();
        if (isset($todos[$clave])) return $todos[$clave];
        $cat = self::catalogo();
        return $cat[$clave]['defecto'] ?? $porDefecto;
    }

    public static function entero($clave, $porDefecto = 0) {
        $v = self::get($clave, $porDefecto);
        return is_numeric($v) ? (int)$v : (int)$porDefecto;
    }

    /** Lista de valores separados por coma, ya limpios y en minuscula */
    public static function lista($clave, array $porDefecto = []) {
        $v = self::get($clave, null);
        if ($v === null || trim($v) === '') return $porDefecto;
        $items = array_filter(array_map(function ($x) { return strtolower(trim($x)); }, explode(',', $v)));
        return array_values(array_unique($items));
    }

    // =====================================================
    // Limite real del servidor para subir archivos (php.ini)
    // =====================================================
    private static function iniAMb($valor) {
        $valor = trim((string)$valor);
        if ($valor === '') return 0;
        $n = (float)$valor;
        switch (strtolower(substr($valor, -1))) {
            case 'g': return $n * 1024;
            case 'm': return $n;
            case 'k': return $n / 1024;
            default:  return $n / (1024 * 1024);
        }
    }

    public static function limiteServidorMb() {
        $a = self::iniAMb(ini_get('upload_max_filesize'));
        $b = self::iniAMb(ini_get('post_max_size'));
        $min = min($a ?: PHP_INT_MAX, $b ?: PHP_INT_MAX);
        return $min === PHP_INT_MAX ? 0 : (int)floor($min);
    }

    /** Tamaño maximo efectivo (lo configurado, recortado por el limite del servidor), en bytes */
    public static function maxBytesDocumento() {
        $mb = self::entero('documentos_max_mb', 10);
        $servidor = self::limiteServidorMb();
        if ($servidor > 0) $mb = min($mb, $servidor);
        return max(1, $mb) * 1024 * 1024;
    }

    /** Extensiones permitidas efectivas: lo configurado, siempre dentro de la lista segura */
    public static function extensionesDocumento() {
        $configuradas = self::lista('documentos_extensiones', ['pdf']);
        $validas = array_values(array_intersect($configuradas, self::EXTENSIONES_PERMITIDAS));
        return empty($validas) ? ['pdf'] : $validas;
    }

    // =====================================================
    // Administracion (la verificacion de rol la hace quien llama)
    // =====================================================
    public static function listarParaAdmin() {
        $lista = [];
        foreach (self::catalogo() as $clave => $def) {
            $item = $def;
            $item['clave'] = $clave;
            $item['valor'] = (string)self::get($clave, $def['defecto']);
            if ($clave === 'documentos_max_mb') {
                $item['limite_servidor'] = self::limiteServidorMb();
            }
            $lista[] = $item;
        }
        return $lista;
    }

    /** Valida y guarda un parametro. Devuelve ['success' => bool, 'mensaje' => ...] */
    public static function guardar($clave, $valor) {
        $cat = self::catalogo();
        if (!isset($cat[$clave])) {
            return ['success' => false, 'mensaje' => 'Parámetro desconocido'];
        }
        $def = $cat[$clave];
        $valor = trim((string)$valor);

        if ($def['tipo'] === 'entero') {
            if (!preg_match('/^\d{1,4}$/', $valor)) {
                return ['success' => false, 'mensaje' => "{$def['titulo']}: debe ser un número entero"];
            }
            $n = (int)$valor;
            if ($n < $def['min'] || $n > $def['max']) {
                return ['success' => false, 'mensaje' => "{$def['titulo']}: debe estar entre {$def['min']} y {$def['max']}"];
            }
            $valor = (string)$n;
        } elseif ($def['tipo'] === 'lista') {
            $items = array_values(array_unique(array_filter(array_map(function ($x) {
                return strtolower(trim($x));
            }, explode(',', $valor)))));
            if (empty($items)) {
                return ['success' => false, 'mensaje' => "{$def['titulo']}: indica al menos una extensión"];
            }
            foreach ($items as $it) {
                if (!in_array($it, $def['permitidas'], true)) {
                    return ['success' => false, 'mensaje' => "{$def['titulo']}: la extensión '" . htmlspecialchars($it, ENT_QUOTES, 'UTF-8') . "' no está permitida"];
                }
            }
            $valor = implode(',', $items);
        }

        try {
            $anterior = (string)self::get($clave, $def['defecto']);
            $stmt = self::pdo()->prepare(
                "INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES (:c, :v, :d)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
            );
            $stmt->execute([':c' => $clave, ':v' => $valor, ':d' => $def['descripcion']]);
            self::limpiarCache();
            if ($anterior !== $valor) {
                registrarBitacora('parametro.modificar', 'parametro', null, null,
                                  ['clave' => $clave, 'antes' => $anterior, 'despues' => $valor]);
            }
            return ['success' => true, 'mensaje' => 'Parámetro guardado'];
        } catch (Throwable $e) {
            error_log('Parametros::guardar: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo guardar el parámetro'];
        }
    }
}
?>
