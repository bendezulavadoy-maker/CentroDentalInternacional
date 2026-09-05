<?php
date_default_timezone_set('America/Lima');

// Cargar variables del archivo .env (una sola vez, sin importar cuantas veces se incluya este archivo)
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

class Conexion {
    private $host     = "localhost";
    private $db_name;
    private $username;
    private $password;
    private $conectar;

    public function __construct() {
        $this->db_name  = getenv('DB_NAME') ?: 'dental_internacional';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';

        try {
            $this->conectar = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conectar->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conectar->exec("SET NAMES utf8mb4");

        } catch (PDOException $exception) {
            echo "Error de conexion: " . $exception->getMessage();
        }
    }

    public function getConexion() {
        return $this->conectar;
    }
}
?>