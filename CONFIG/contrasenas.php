<?php
/**
 * REGLAS DE CONTRASENA del personal. Un solo lugar para cambiar la politica.
 *
 *   Contrasenas::validarNueva($nueva, $actual, $codigoUsuario, $nroDocumento)  -> lista de problemas ([] = valida)
 *   Contrasenas::generarTemporal()                                              -> clave temporal aleatoria
 *   Contrasenas::cifrar($clave)                                                 -> hash para guardar en la base
 */
class Contrasenas {

    const MINIMO       = 8;
    const MAXIMO_BYTES = 72;   // bcrypt solo usa los primeros 72 bytes: permitir mas seria enganoso

    // Claves obvias que se rechazan aunque cumplan el largo
    private static $PROHIBIDAS = ['12345', '54321', '111111', '000000', 'qwerty', 'password',
                                  'contrasena', 'contraseña', 'abcdef', 'dental', 'internacional'];

    public static function validarNueva($nueva, $actual = '', $codigoUsuario = '', $nroDocumento = '') {
        $nueva = (string)$nueva;
        $e = [];

        $largo = preg_match_all('/./su', $nueva);   // caracteres (no bytes); no exige la extension mbstring
        if ($largo === false || $largo < self::MINIMO) {
            $e[] = 'Debe tener al menos ' . self::MINIMO . ' caracteres.';
        }
        if (strlen($nueva) > self::MAXIMO_BYTES) {
            $e[] = 'Es demasiado larga (máximo ' . self::MAXIMO_BYTES . ' caracteres).';
        }
        if (!preg_match('/\p{L}/u', $nueva) || !preg_match('/\d/', $nueva)) {
            $e[] = 'Debe incluir al menos una letra y un número.';
        }
        if ($actual !== '' && hash_equals((string)$actual, $nueva)) {
            $e[] = 'Debe ser distinta de la actual.';
        }

        $baja = strtolower($nueva);
        if ($codigoUsuario !== '' && strpos($baja, strtolower($codigoUsuario)) !== false) {
            $e[] = 'No puede contener tu código de usuario.';
        }
        if (strlen((string)$nroDocumento) >= 4 && strpos($baja, strtolower((string)$nroDocumento)) !== false) {
            $e[] = 'No puede contener tu número de documento.';
        }
        foreach (self::$PROHIBIDAS as $p) {
            if (strpos($baja, $p) !== false) {
                $e[] = 'Evita claves obvias (como 12345, dental o qwerty).';
                break;
            }
        }
        return array_values(array_unique($e));
    }

    /** Clave temporal de 12 caracteres, sin letras que se confunden (0/O, 1/l/I). Siempre mezcla mayusculas, minusculas y numeros. */
    public static function generarTemporal($largo = 12) {
        $may = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $min = 'abcdefghijkmnpqrstuvwxyz';
        $num = '23456789';
        $todo = $may . $min . $num;
        $p = [$may[random_int(0, strlen($may) - 1)], $min[random_int(0, strlen($min) - 1)], $num[random_int(0, strlen($num) - 1)]];
        while (count($p) < $largo) {
            $p[] = $todo[random_int(0, strlen($todo) - 1)];
        }
        for ($i = count($p) - 1; $i > 0; $i--) {   // mezcla (Fisher-Yates con aleatorio seguro)
            $j = random_int(0, $i);
            [$p[$i], $p[$j]] = [$p[$j], $p[$i]];
        }
        return implode('', $p);
    }

    public static function cifrar($clave) {
        return password_hash((string)$clave, PASSWORD_BCRYPT);
    }
}
?>
