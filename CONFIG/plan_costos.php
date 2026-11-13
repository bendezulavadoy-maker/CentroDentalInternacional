<?php
/**
 * REGLA UNICA del costo de las sesiones de un plan de tratamiento.
 * La usan el informe del odontologo, las citas pasadas y la ficha del plan, para que todos calculen igual.
 *
 *  - Plan POR SESION: cada sesion cuesta el costo estimado por sesion (sin limite de sesiones).
 *  - Plan de COSTO TOTAL pagado en UN SOLO PAGO: las sesiones no llevan costo (el total se cobra aparte).
 *  - Plan de COSTO TOTAL EN CUOTAS (N cuotas = N sesiones): las sesiones 1..N llevan la cuota; la ultima absorbe
 *    los centimos de redondeo (550 en 3 -> 183.33 + 183.33 + 183.34). Las sesiones DESPUES de la N son
 *    ADICIONALES: ya estan cubiertas por el costo acordado y NO se cobran (S/ 0.00).
 */
class PlanCostos {

    /** Numero de cuotas del plan de costo total (minimo 1) */
    public static function cuotas(array $p) {
        $n = (int)($p['sesiones_pago_est'] ?? 0);
        return $n > 0 ? $n : 1;
    }

    /** Sesiones estimadas del plan (null si no se indico) */
    public static function sesionesEstimadas(array $p) {
        $n = (int)($p['sesiones_pago_est'] ?? 0);
        return $n > 0 ? $n : null;
    }

    /** Costo que debe llevar la sesion n.º $numero */
    public static function costoSesion(array $p, $numero) {
        $numero = (int)$numero;
        if (($p['tipo'] ?? 'por_sesion') === 'por_sesion') return round((float)($p['costo_estimado_sesion'] ?? 0), 2);
        if (!(int)($p['en_cuotas'] ?? 0)) return 0.0;
        $n = self::cuotas($p);
        if ($numero < 1 || $numero > $n) return 0.0;
        $costo = (float)($p['costo_acordado'] ?? 0);
        $cuota = round($costo / $n, 2);
        return $numero < $n ? $cuota : round($costo - $cuota * ($n - 1), 2);
    }

    /** true si la sesion es "adicional" (pasa de las cuotas de un plan de costo total en cuotas) */
    public static function esAdicional(array $p, $numero) {
        return ($p['tipo'] ?? '') === 'costo_total' && (int)($p['en_cuotas'] ?? 0) && (int)$numero > self::cuotas($p);
    }

    /** Aviso para mostrar al elegir o usar el plan: que costo lleva la proxima sesion y por que */
    public static function aviso(array $p, $numeroSiguiente) {
        $n = (int)$numeroSiguiente; $costo = self::costoSesion($p, $n); $m = fn($x) => 'S/ ' . number_format($x, 2);
        $tipo = $p['tipo'] ?? 'por_sesion'; $nivel = 'info'; $adicional = self::esAdicional($p, $n);
        if ($tipo === 'por_sesion') {
            $est = self::sesionesEstimadas($p);
            $msg = "Plan por sesión: cada sesión cuesta {$m($costo)}. Esta será la sesión n.º $n" . ($est ? " de $est estimadas" : '') . '.';
            if ($est && $n > $est) { $msg .= ' Ya se superaron las sesiones estimadas (se sigue cobrando por sesión).'; $nivel = 'aviso'; }
        } elseif (!(int)($p['en_cuotas'] ?? 0)) {
            $msg = "Plan de costo fijo de {$m((float)($p['costo_acordado'] ?? 0))} pagado en un solo pago: las sesiones no tienen costo. Esta será la sesión n.º $n.";
        } elseif ($adicional) {
            $N = self::cuotas($p);
            $msg = "Plan de costo fijo de {$m((float)$p['costo_acordado'])} en $N cuotas: ya se cumplieron las $N sesiones previstas. La sesión n.º $n es adicional y no se cobra ({$m(0)}).";
            $nivel = 'aviso';
        } else {
            $N = self::cuotas($p);
            $msg = "Plan de costo fijo de {$m((float)$p['costo_acordado'])} en $N cuotas. Esta será la sesión n.º $n, cuota $n de $N: {$m($costo)}.";
        }
        return ['mensaje' => $msg, 'nivel' => $nivel, 'costo_sesion' => $costo, 'numero' => $n, 'adicional' => $adicional];
    }
}
