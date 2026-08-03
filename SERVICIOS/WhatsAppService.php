<?php
/**
 * WhatsAppService.php
 * Servicio de notificaciones WhatsApp via Fonnte
 * Dental Internacional
 */
class WhatsAppService {

    private $token    = 'iKxQm5R7RAFDLopXheA3';
    private $endpoint = 'https://api.fonnte.com/send';

    // Número de la recepcionista/admin que recibe avisos internos
    private $numeroRecepcionista = '51933614111';

    // ══════════════════════════════════════════════════════════════
    // ENVÍO BASE
    // ══════════════════════════════════════════════════════════════

    private function enviar($numero, $mensaje) {
        // Dejar solo dígitos
        $numero = preg_replace('/\D/', '', $numero);

        // Si tiene 51 al inicio, quitarlo — Fonnte agrega el código con countryCode
        if (strlen($numero) === 11 && substr($numero, 0, 2) === '51') {
            $numero = substr($numero, 2);
        }
        // Si tiene 9 dígitos ya está listo para enviar con countryCode=51

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'target'      => $numero,
                'message'     => $mensaje,
                'countryCode' => '51',  // ← Perú siempre
            ]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->token,
            ],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($error) {
            error_log("WhatsApp error curl: $error");
            return false;
        }

        $data = json_decode($response, true);
        if (!isset($data['status']) || $data['status'] !== true) {
            error_log("WhatsApp error respuesta: $response");
            return false;
        }

        return true;
    }

    // ══════════════════════════════════════════════════════════════
    // 1. CONFIRMACIÓN AL CREAR CITA
    // ══════════════════════════════════════════════════════════════

    public function citaConfirmada($datosCita) {
        $fecha     = $this->formatearFecha($datosCita['fecha']);
        $hora      = substr($datosCita['hora'], 0, 5);
        $paciente  = $datosCita['nombre_paciente'];
        $doctor    = $datosCita['nombre_doctor'];
        $sede      = $datosCita['nombre_sede'];
        $telefono  = $datosCita['telefono_paciente'];

        $mensaje = "🦷 *Dental Internacional*\n\n"
                 . "Hola *{$paciente}*, tu cita ha sido confirmada.\n\n"
                 . "📅 *Fecha:* {$fecha}\n"
                 . "⏰ *Hora:* {$hora}\n"
                 . "👨‍⚕️ *Doctor:* Dr(a). {$doctor}\n"
                 . "📍 *Sede:* {$sede}\n\n"
                 . "Por favor llega 10 minutos antes.\n"
                 . "Para gestionar tu cita ingresa a nuestro portal:\n"
                 . "🔗 " . $this->urlPortal();

        return $this->enviar($telefono, $mensaje);
    }

    // ══════════════════════════════════════════════════════════════
    // 2. RECORDATORIO 24 HORAS ANTES
    // ══════════════════════════════════════════════════════════════

    public function recordatorio24h($datosCita) {
        $fecha    = $this->formatearFecha($datosCita['fecha']);
        $hora     = substr($datosCita['hora'], 0, 5);
        $paciente = $datosCita['nombre_paciente'];
        $doctor   = $datosCita['nombre_doctor'];
        $sede     = $datosCita['nombre_sede'];
        $telefono = $datosCita['telefono_paciente'];

        $mensaje = "🦷 *Dental Internacional — Recordatorio*\n\n"
                 . "Hola *{$paciente}* 👋\n\n"
                 . "Te recordamos que mañana tienes una cita:\n\n"
                 . "📅 *Fecha:* {$fecha}\n"
                 . "⏰ *Hora:* {$hora}\n"
                 . "👨‍⚕️ *Doctor:* Dr(a). {$doctor}\n"
                 . "📍 *Sede:* {$sede}\n\n"
                 . "¿Necesitas cancelar o reprogramar? Ingresa al portal:\n"
                 . "🔗 " . $this->urlPortal();

        return $this->enviar($telefono, $mensaje);
    }

    // ══════════════════════════════════════════════════════════════
    // 3. RECORDATORIO 2 HORAS ANTES
    // ══════════════════════════════════════════════════════════════

    public function recordatorio2h($datosCita) {
        $hora     = substr($datosCita['hora'], 0, 5);
        $paciente = $datosCita['nombre_paciente'];
        $doctor   = $datosCita['nombre_doctor'];
        $sede     = $datosCita['nombre_sede'];
        $telefono = $datosCita['telefono_paciente'];

        $mensaje = "🦷 *Dental Internacional — Recordatorio*\n\n"
                 . "Hola *{$paciente}* ⏰\n\n"
                 . "Tu cita es *hoy a las {$hora}*.\n\n"
                 . "👨‍⚕️ *Doctor:* Dr(a). {$doctor}\n"
                 . "📍 *Sede:* {$sede}\n\n"
                 . "¡Te esperamos! Por favor llega 10 minutos antes.";

        return $this->enviar($telefono, $mensaje);
    }

    // ══════════════════════════════════════════════════════════════
    // 4. CITA CANCELADA (aviso al paciente)
    // ══════════════════════════════════════════════════════════════

    public function citaCancelada($datosCita) {
        $fecha    = $this->formatearFecha($datosCita['fecha']);
        $hora     = substr($datosCita['hora'], 0, 5);
        $paciente = $datosCita['nombre_paciente'];
        $doctor   = $datosCita['nombre_doctor'];
        $telefono = $datosCita['telefono_paciente'];

        $mensaje = "🦷 *Dental Internacional*\n\n"
                 . "Hola *{$paciente}*, tu cita ha sido cancelada.\n\n"
                 . "📅 *Fecha:* {$fecha}\n"
                 . "⏰ *Hora:* {$hora}\n"
                 . "👨‍⚕️ *Doctor:* Dr(a). {$doctor}\n\n"
                 . "Si deseas reagendar, ingresa al portal:\n"
                 . "🔗 " . $this->urlPortal() . "\n\n"
                 . "O comunícate con nosotros. ¡Gracias!";

        return $this->enviar($telefono, $mensaje);
    }

    // ══════════════════════════════════════════════════════════════
    // 5. AVISO A RECEPCIONISTA — paciente canceló desde portal
    // ══════════════════════════════════════════════════════════════

    public function avisoCancelacionRecepcionista($datosCita) {
        $fecha    = $this->formatearFecha($datosCita['fecha']);
        $hora     = substr($datosCita['hora'], 0, 5);
        $paciente = $datosCita['nombre_paciente'];
        $doctor   = $datosCita['nombre_doctor'];
        $sede     = $datosCita['nombre_sede'];

        $mensaje = "⚠️ *Dental Internacional — Cancelación*\n\n"
                 . "El paciente *{$paciente}* canceló su cita desde el portal.\n\n"
                 . "📅 *Fecha:* {$fecha}\n"
                 . "⏰ *Hora:* {$hora}\n"
                 . "👨‍⚕️ *Doctor:* Dr(a). {$doctor}\n"
                 . "📍 *Sede:* {$sede}\n\n"
                 . "El slot quedó disponible para nuevas citas.";

        return $this->enviar($this->numeroRecepcionista, $mensaje);
    }

    // ══════════════════════════════════════════════════════════════
    // 6. CITA REPROGRAMADA (aviso al paciente)
    // ══════════════════════════════════════════════════════════════

    public function citaReprogramada($datosCita, $nuevaFecha, $nuevaHora) {
        $fechaAnterior = $this->formatearFecha($datosCita['fecha']);
        $horaAnterior  = substr($datosCita['hora'], 0, 5);
        $fechaNueva    = $this->formatearFecha($nuevaFecha);
        $horaNueva     = substr($nuevaHora, 0, 5);
        $paciente      = $datosCita['nombre_paciente'];
        $doctor        = $datosCita['nombre_doctor'];
        $sede          = $datosCita['nombre_sede'];
        $telefono      = $datosCita['telefono_paciente'];

        $mensaje = "🦷 *Dental Internacional — Cita Reprogramada*\n\n"
                 . "Hola *{$paciente}*, tu cita fue reprogramada.\n\n"
                 . "❌ *Anterior:* {$fechaAnterior} a las {$horaAnterior}\n"
                 . "✅ *Nueva:* {$fechaNueva} a las {$horaNueva}\n\n"
                 . "👨‍⚕️ *Doctor:* Dr(a). {$doctor}\n"
                 . "📍 *Sede:* {$sede}\n\n"
                 . "Por favor llega 10 minutos antes.\n"
                 . "🔗 " . $this->urlPortal();

        return $this->enviar($telefono, $mensaje);
    }

    // ══════════════════════════════════════════════════════════════
    // 7. AVISO A RECEPCIONISTA — paciente reprogramó desde portal
    // ══════════════════════════════════════════════════════════════

    public function avisoReprogramacionRecepcionista($datosCita, $nuevaFecha, $nuevaHora) {
        $fechaAnterior = $this->formatearFecha($datosCita['fecha']);
        $horaAnterior  = substr($datosCita['hora'], 0, 5);
        $fechaNueva    = $this->formatearFecha($nuevaFecha);
        $horaNueva     = substr($nuevaHora, 0, 5);
        $paciente      = $datosCita['nombre_paciente'];
        $doctor        = $datosCita['nombre_doctor'];
        $sede          = $datosCita['nombre_sede'];

        $mensaje = "🔄 *Dental Internacional — Reprogramación*\n\n"
                 . "El paciente *{$paciente}* reprogramó su cita desde el portal.\n\n"
                 . "❌ *Anterior:* {$fechaAnterior} a las {$horaAnterior}\n"
                 . "✅ *Nueva:* {$fechaNueva} a las {$horaNueva}\n\n"
                 . "👨‍⚕️ *Doctor:* Dr(a). {$doctor}\n"
                 . "📍 *Sede:* {$sede}";

        return $this->enviar($this->numeroRecepcionista, $mensaje);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    private function formatearFecha($fecha) {
        $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $ts    = strtotime($fecha);
        return $dias[date('w', $ts)] . ' ' . date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
    }

    private function urlPortal() {
        // Cambiar por la URL real cuando el sistema esté en producción
        return 'http://dentalinternacional.site/portal/';
    }
}
?>