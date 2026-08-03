<?php
require_once "../conexion.php";

class HistoriaClinicaModelo {

    // Obtener datos de un paciente
    public static function obtenerPaciente($id_paciente) {
        global $conn;
        $sql = "SELECT id_paciente, nombre, apellido,dni,fecha_nacimiento_telefono_direccion_correo_fecha_registro,foto FROM pacientes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_paciente);
        $stmt->execute();
        $resultado = $stmt->get_result();
        return $resultado->fetch_assoc();
    }

    // Actualizar foto del paciente
    public static function actualizarFoto($id_paciente, $rutaFoto) {
        global $conn;
        $sql = "UPDATE pacientes SET foto = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $rutaFoto, $id_paciente);
        return $stmt->execute();
    }
}
?>
