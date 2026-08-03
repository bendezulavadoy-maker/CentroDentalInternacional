<?php
date_default_timezone_set('America/Lima');
class Conexion {
    /*private $host     = "localhost";
    private $db_name  = "fressdrr_dental_internacional";
    private $username = "fressdrr_dental_user";
    private $password = "boliqueso@2002";
    private $conectar;*/
    private $host = "localhost";
    private $db_name = "dental_internacional";  // Nombre bd
    private $username = "root";
    private $password = "";
    private $conectar;


    public function __construct() {
        try {
            $this->conectar = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conectar->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conectar->exec("SET NAMES utf8mb4");
          
        } catch (PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
    }

    public function getConexion() {
        return $this->conectar;
        
    }
}
?>