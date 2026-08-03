<?php
require_once '../CONFIG/conexion.php';

// =======================================================
// 🎯 Generar código de usuario basado en el DNI
// =======================================================
if (!isset($_GET['dni'])) {
    echo json_encode(['error' => 'Falta parámetro DNI']);
    exit;
}

$dni = trim($_GET['dni']);

// Validar que tenga formato correcto
if (!preg_match('/^[0-9]{8}$/', $dni)) {
    echo json_encode(['error' => 'DNI inválido']);
    exit;
}

// Obtener los últimos 4 dígitos del DNI
$ultimos4 = substr($dni, -4);
$codigo = "DENTINT" . $ultimos4;

// Verificar si ya existe en la base de datos
$stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE codigo_usuario = ?");
$stmt->bind_param("s", $codigo);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['total'] > 0) {
    echo json_encode(['existe' => true, 'codigo' => $codigo]);
} else {
    echo json_encode(['existe' => false, 'codigo' => $codigo]);
}
?>
