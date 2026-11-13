<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class ModeloContrasena{

    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
    }

    public function obtenerUsuario($id) {
        $st = $this->conexion->prepare("SELECT id_usuario, codigo_usuario, nro_documento, contrasena FROM usuarios WHERE id_usuario = :id");
        $st->execute([':id' => (int)$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** La propia persona cambia su clave: ya no esta obligada a cambiarla */
    public function guardarCambio($id, $hash) {
        $st = $this->conexion->prepare("UPDATE usuarios SET contrasena = :h, debe_cambiar_contrasena = 0, contrasena_cambiada = NOW() WHERE id_usuario = :id");
        return $st->execute([':h' => $hash, ':id' => (int)$id]);
    }

    /** El administrador restablece: queda una clave temporal y la persona debe cambiarla al ingresar */
    public function guardarRestablecimiento($id, $hash) {
        $st = $this->conexion->prepare("UPDATE usuarios SET contrasena = :h, debe_cambiar_contrasena = 1, contrasena_cambiada = NULL WHERE id_usuario = :id");
        return $st->execute([':h' => $hash, ':id' => (int)$id]) && $st->rowCount() > 0;
    }
}
?>
