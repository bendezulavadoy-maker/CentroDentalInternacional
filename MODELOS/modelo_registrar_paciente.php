<?php
class ModeloRegistrarPaciente {
    private $conexion;

    public function __construct($db) {
        $this->conexion = $db;
    }

    public function registrarPaciente($datos) {
        try {
            $query = "INSERT INTO pacientes 
                        (nombre, apellido, dni, fecha_nacimiento, telefono, direccion, correo, foto)
                      VALUES 
                        (:nombre, :apellido, :dni, :fecha_nacimiento, :telefono, :direccion, :correo, :foto)";
            
            $stmt = $this->conexion->prepare($query);

            $stmt->bindParam(":nombre", $datos['nombre']);
            $stmt->bindParam(":apellido", $datos['apellido']);
            $stmt->bindParam(":dni", $datos['dni']);
            $stmt->bindParam(":fecha_nacimiento", $datos['fecha_nacimiento']);
            $stmt->bindParam(":telefono", $datos['telefono']);
            $stmt->bindParam(":direccion", $datos['direccion']);
            $stmt->bindParam(":correo", $datos['correo']);
            $stmt->bindParam(":foto", $datos['foto']);

            return $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
?>
