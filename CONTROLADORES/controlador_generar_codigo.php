<?php
require_once '../CONFIG/conexion.php';

// =======================================================
// Generar codigo de usuario basado en el DNI
// =======================================================
if (!isset($_GET['dni'])) {
    echo json_encode(['error' => 'Falta parametro DNI']);
    exit;
}

$dni = trim($_GET['dni']);

// Validar que tenga formato correcto
if (!preg_match('/^[0-9]{8}$/', $dni)) {
    echo json_encode(['error' => 'DNI invalido']);
    exit;
}

// Obtener los primeros 4 digitos del DNI
$primeros4 = substr($dni, 0, 4);
$codigo = "DENTINT" . $primeros4;

// Verificar si ya existe en la base de datos
$conexion = (new Conexion())->getConexion();
$stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE codigo_usuario = :codigo");
$stmt->execute([':codigo' => $codigo]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['total'] > 0) {
    echo json_encode(['existe' => true, 'codigo' => $codigo]);
} else {
    echo json_encode(['existe' => false, 'codigo' => $codigo]);
}
?>