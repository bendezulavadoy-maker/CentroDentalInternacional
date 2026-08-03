<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloInicioSesion {

    private $conexion;

    public function __construct() {
        $db = new Conexion();
        $this->conexion = $db->getConexion();
    }

    public function verificarUsuario($codigo_usuario, $contrasena) {
        try {
            $sql  = "SELECT * FROM usuarios WHERE codigo_usuario = :codigo_usuario";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':codigo_usuario', $codigo_usuario, PDO::PARAM_STR);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
                return $usuario;
            }
            return false;

        } catch (PDOException $e) {
            error_log("Error en verificarUsuario: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerPermisos($id_rol) {
        try {
            $sql  = "SELECT modulo FROM permisos_rol WHERE id_rol = :id_rol";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rol', $id_rol, PDO::PARAM_INT);
            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $filas; // ej: ['historia_clinica', 'pacientes', 'citas']
        } catch (PDOException $e) {
            error_log("Error en obtenerPermisos: " . $e->getMessage());
            return [];
        }
    }
}
?>