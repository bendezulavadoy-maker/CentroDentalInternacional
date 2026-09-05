<?php
/**
 * GoogleCalendarService.php
 * Sincronizacion de citas con Google Calendar
 * Un calendario por doctor - cada doctor autoriza su propio Google Calendar
 */

// Cargar variables del .env (si no se cargaron ya desde conexion.php)
if (!function_exists('cargarEnv')) {
    function cargarEnv($ruta) {
        if (!file_exists($ruta)) return;
        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas as $linea) {
            if (strpos(trim($linea), '#') === 0) continue;
            if (strpos($linea, '=') === false) continue;
            list($clave, $valor) = explode('=', $linea, 2);
            putenv(trim($clave) . '=' . trim($valor));
        }
    }
}
cargarEnv(__DIR__ . '/../.env');

class GoogleCalendarService {

    private $clientId;
    private $clientSecret;
    private $redirectUri  = 'https://dentalinternacional.site/SERVICIOS/google_callback.php';
    private $scope        = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile';
    private $tokenFile;   // Ruta al token del doctor
    private $db;

    public function __construct($db = null) {
        $this->clientId     = getenv('GOOGLE_CLIENT_ID');
        $this->clientSecret = getenv('GOOGLE_CLIENT_SECRET');
        $this->db = $db;
    }

    // ======================================================
    // AUTENTICACION - URL para que el doctor autorice
    // ======================================================

    public function getUrlAutorizacion($id_doctor, $origen = 'configuracion') {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => $this->scope,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $id_doctor . '|' . $origen,
        ]);
        return 'https://accounts.google.com/o/oauth2/auth?' . $params;
    }

    // ======================================================
    // INTERCAMBIAR CODIGO POR TOKEN
    // ======================================================

    public function intercambiarCodigo($code, $id_doctor) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'code'          => $code,
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri'  => $this->redirectUri,
                'grant_type'    => 'authorization_code',
            ]),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $token = json_decode($response, true);
        if (empty($token['access_token'])) {
            error_log("GoogleCalendar intercambiarCodigo error: $response");
            return ['ok' => false, 'error' => 'token_invalido'];
        }

        $emailGoogle = $this->getEmailGoogle($token['access_token']);
        if (!$emailGoogle) {
            return ['ok' => false, 'error' => 'no_se_pudo_verificar_email'];
        }

        $stmt = $this->db->prepare("SELECT correo FROM usuarios WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_doctor]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $correoDr = strtolower(trim($row['correo'] ?? ''));
        $emailGoogle = strtolower(trim($emailGoogle));

        if ($correoDr !== $emailGoogle) {
            error_log("GoogleCalendar: email no coincide. Doctor: $correoDr | Google: $emailGoogle");
            return [
                'ok'           => false,
                'error'        => 'email_no_coincide',
                'email_google' => $emailGoogle,
                'email_sistema'=> $correoDr,
            ];
        }

        $this->guardarToken($id_doctor, $token);
        return ['ok' => true];
    }

    private function getEmailGoogle($accessToken) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://www.googleapis.com/oauth2/v2/userinfo',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return $data['email'] ?? null;
    }

    // ======================================================
    // GUARDAR / CARGAR TOKEN EN BD
    // ======================================================

    private function guardarToken($id_doctor, $token) {
        if (!$this->db) return;
        $token['created_at'] = time();
        $json = json_encode($token);
        $stmt = $this->db->prepare(
            "UPDATE usuarios SET google_calendar_token = :token WHERE id_usuario = :id"
        );
        $stmt->execute([':token' => $json, ':id' => $id_doctor]);
    }

    private function cargarToken($id_doctor) {
        if (!$this->db) return null;
        $stmt = $this->db->prepare(
            "SELECT google_calendar_token FROM usuarios WHERE id_usuario = :id"
        );
        $stmt->execute([':id' => $id_doctor]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['google_calendar_token'])) return null;
        return json_decode($row['google_calendar_token'], true);
    }

    // ======================================================
    // REFRESCAR ACCESS TOKEN
    // ======================================================

    private function refrescarToken($id_doctor, $token) {
        if (empty($token['refresh_token'])) return null;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'refresh_token' => $token['refresh_token'],
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'refresh_token',
            ]),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $nuevoToken = json_decode($response, true);
        if (empty($nuevoToken['access_token'])) return null;

        $nuevoToken['refresh_token'] = $token['refresh_token'];
        $this->guardarToken($id_doctor, $nuevoToken);
        return $nuevoToken;
    }

    // ======================================================
    // OBTENER ACCESS TOKEN VALIDO
    // ======================================================

    private function getAccessToken($id_doctor) {
        $token = $this->cargarToken($id_doctor);
        if (!$token) return null;

        $expira = ($token['created_at'] ?? 0) + ($token['expires_in'] ?? 3600) - 300;
        if (time() > $expira) {
            $token = $this->refrescarToken($id_doctor, $token);
        }
        return $token ? $token['access_token'] : null;
    }

    // ======================================================
    // VERIFICAR SI EL DOCTOR TIENE CALENDARIO CONECTADO
    // ======================================================

    public function doctorConectado($id_doctor) {
        $token = $this->cargarToken($id_doctor);
        return !empty($token['access_token']);
    }

    // ======================================================
    // REQUEST BASE A GOOGLE CALENDAR API
    // ======================================================

    private function request($method, $url, $id_doctor, $body = null) {
        $accessToken = $this->getAccessToken($id_doctor);
        if (!$accessToken) return null;

        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ];
        if ($body) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            error_log("GoogleCalendar API error $httpCode: $response");
            return null;
        }
        return json_decode($response, true);
    }

    // ======================================================
    // CREAR EVENTO
    // ======================================================

    public function crearEvento($id_doctor, $datosCita) {
        $accessToken = $this->getAccessToken($id_doctor);
        if (!$accessToken) return null;

        $inicio = $datosCita['fecha'] . 'T' . substr($datosCita['hora'], 0, 5) . ':00';
        $duracion = intval($datosCita['duracion_minutos'] ?? 30);
        $fin = date('Y-m-d\TH:i:s', strtotime($inicio) + ($duracion * 60));

        $evento = [
            'summary'     => 'Cita - ' . ($datosCita['nombre_paciente'] ?? 'Paciente'),
            'description' => implode("\n", array_filter([
                'Paciente: ' . ($datosCita['nombre_paciente'] ?? ''),
                'Tipo: '     . ($datosCita['tipo_atencion'] ?? ''),
                'Sede: '     . ($datosCita['nombre_sede'] ?? ''),
                'Motivo: '   . ($datosCita['motivo'] ?? ''),
            ])),
            'start' => ['dateTime' => $inicio, 'timeZone' => 'America/Lima'],
            'end'   => ['dateTime' => $fin,    'timeZone' => 'America/Lima'],
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'popup', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 15],
                ],
            ],
            'colorId' => '1',
        ];

        $resultado = $this->request('POST',
            'https://www.googleapis.com/calendar/v3/calendars/primary/events',
            $id_doctor, $evento
        );

        return $resultado['id'] ?? null;
    }

    // ======================================================
    // ACTUALIZAR EVENTO
    // ======================================================

    public function actualizarEvento($id_doctor, $google_event_id, $datosCita) {
        if (!$google_event_id) return $this->crearEvento($id_doctor, $datosCita);

        $inicio   = $datosCita['fecha'] . 'T' . substr($datosCita['hora'], 0, 5) . ':00';
        $duracion = intval($datosCita['duracion_minutos'] ?? 30);
        $fin      = date('Y-m-d\TH:i:s', strtotime($inicio) + ($duracion * 60));

        $evento = [
            'summary'     => 'Cita - ' . ($datosCita['nombre_paciente'] ?? 'Paciente'),
            'description' => implode("\n", array_filter([
                'Paciente: ' . ($datosCita['nombre_paciente'] ?? ''),
                'Tipo: '     . ($datosCita['tipo_atencion'] ?? ''),
                'Sede: '     . ($datosCita['nombre_sede'] ?? ''),
                'Motivo: '   . ($datosCita['motivo'] ?? ''),
            ])),
            'start' => ['dateTime' => $inicio, 'timeZone' => 'America/Lima'],
            'end'   => ['dateTime' => $fin,    'timeZone' => 'America/Lima'],
        ];

        $this->request('PUT',
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/$google_event_id",
            $id_doctor, $evento
        );
        return $google_event_id;
    }

    // ======================================================
    // ELIMINAR EVENTO
    // ======================================================

    public function eliminarEvento($id_doctor, $google_event_id) {
        if (!$google_event_id) return;
        $this->request('DELETE',
            "https://www.googleapis.com/calendar/v3/calendars/primary/events/$google_event_id",
            $id_doctor
        );
    }
}
?>