<?php
// --- DEBUG: mostrar todos los errores ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../CONFIG/conexion.php";
require_once "../MODELO/modelo_registrar_paciente.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $conexion = new Conexion();
    $db = $conexion->getConexion();

    $modelo = new ModeloRegistrarPaciente($db);

    // --- Subir foto ---
    $ruta_foto = "../IMAGENES/perfiles_pacientes/default_user.png"; // por defecto
    if (!empty($_FILES['foto']['name'])) {
        $nombre_foto = time() . "_" . basename($_FILES["foto"]["name"]);
        $ruta_destino = "../IMAGENES/perfiles_pacientes/" . $nombre_foto;

        // Verificar que la carpeta exista y sea escribible
        if (!is_dir("../IMAGENES/perfiles_pacientes/")) {
            mkdir("../IMAGENES/perfiles_pacientes/", 0777, true);
        }

        // Intentar mover el archivo
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $ruta_destino)) {
            $ruta_foto = $ruta_destino;
        } else {
            // En caso de fallo, mantener la imagen por defecto
            $ruta_foto = "../IMAGENES/perfiles_pacientes/default_user.png";
        }
    }

    // --- Datos a insertar ---
    $datos = [
        "nombre" => $_POST["nombre"] ?? '',
        "apellido" => $_POST["apellido"] ?? '',
        "dni" => $_POST["dni"] ?? '',
        "fecha_nacimiento" => $_POST["fecha_nacimiento"] ?? null,
        "telefono" => $_POST["telefono"] ?? '',
        "direccion" => $_POST["direccion"] ?? '',
        "correo" => $_POST["correo"] ?? '',
        "foto" => $ruta_foto
    ];

    // --- Insertar en BD ---
    try {
        $resultado = $modelo->registrarPaciente($datos);

        if ($resultado === true) {
            echo json_encode(["estado" => "ok", "mensaje" => "Paciente registrado correctamente"]);
        } else {
            echo json_encode(["estado" => "error", "mensaje" => $resultado]);
        }

    } catch (Exception $e) {
        echo json_encode(["estado" => "error", "mensaje" => "Excepción: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["estado" => "error", "mensaje" => "Método no permitido"]);
}
