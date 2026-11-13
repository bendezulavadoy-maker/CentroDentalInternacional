<?php
/**
 * LIMITE DE INTENTOS (contra adivinar contrasenas). Usa la tabla portal_intentos.
 *
 *   LimiteIntentos::bloqueado($clave, $maximo, $minutos)  -> true si ya hubo $maximo intentos fallidos recientes
 *   LimiteIntentos::registrar($clave)                      -> anota un intento fallido
 *   LimiteIntentos::limpiar($clave)                        -> borra los intentos (al acertar)
 *
 * Si la tabla no existe no bloquea a nadie (queda aviso en el log), igual que el portal.
 */
require_once __DIR__ . '/conexion.php';

class LimiteIntentos {

    private static function db() {
        static $pdo = null;
        if ($pdo === null) { $pdo = (new Conexion())->getConexion(); }
        return $pdo;
    }

    private static function clave($c) { return substr((string)$c, 0, 120); }   // la columna es VARCHAR(120)

    public static function bloqueado($clave, $maximo, $minutos = 15) {
        try {
            $minutos = max(1, (int)$minutos);
            $st = self::db()->prepare("SELECT COUNT(*) FROM portal_intentos WHERE clave = :c AND fecha_hora > (NOW() - INTERVAL $minutos MINUTE)");
            $st->execute([':c' => self::clave($clave)]);
            return (int)$st->fetchColumn() >= (int)$maximo;
        } catch (Throwable $e) {
            error_log('LimiteIntentos (consulta): ' . $e->getMessage());
            return false;
        }
    }

    public static function registrar($clave) {
        try {
            self::db()->prepare("INSERT INTO portal_intentos (clave, fecha_hora) VALUES (:c, NOW())")->execute([':c' => self::clave($clave)]);
            if (random_int(1, 25) === 1) {   // de vez en cuando se limpia lo viejo
                self::db()->exec("DELETE FROM portal_intentos WHERE fecha_hora < (NOW() - INTERVAL 1 DAY)");
            }
        } catch (Throwable $e) {
            error_log('LimiteIntentos (registro): ' . $e->getMessage());
        }
    }

    public static function limpiar($clave) {
        try {
            self::db()->prepare("DELETE FROM portal_intentos WHERE clave = :c")->execute([':c' => self::clave($clave)]);
        } catch (Throwable $e) {
            error_log('LimiteIntentos (limpieza): ' . $e->getMessage());
        }
    }
}
?>
