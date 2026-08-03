<?php
// debug_sesion.php
// Crea este archivo TEMPORALMENTE en la raíz o en VISTAS para verificar tu sesión

session_start();

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<meta charset='UTF-8'>";
echo "<title>Debug de Sesión</title>";
echo "<style>
    body { 
        font-family: Arial, sans-serif; 
        padding: 40px; 
        background: #f5f5f5; 
    }
    .container { 
        max-width: 800px; 
        margin: 0 auto; 
        background: white; 
        padding: 30px; 
        border-radius: 10px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    h1 { 
        color: #667eea; 
        border-bottom: 3px solid #667eea; 
        padding-bottom: 10px; 
    }
    h2 { 
        color: #764ba2; 
        margin-top: 30px; 
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin: 20px 0; 
    }
    th, td { 
        padding: 12px; 
        text-align: left; 
        border-bottom: 1px solid #ddd; 
    }
    th { 
        background: #667eea; 
        color: white; 
        font-weight: 600; 
    }
    tr:hover { 
        background: #f8f9ff; 
    }
    .alert { 
        padding: 15px; 
        border-radius: 8px; 
        margin: 20px 0; 
    }
    .success { 
        background: #d4edda; 
        color: #155724; 
        border-left: 5px solid #28a745; 
    }
    .warning { 
        background: #fff3cd; 
        color: #856404; 
        border-left: 5px solid #ffc107; 
    }
    .error { 
        background: #f8d7da; 
        color: #721c24; 
        border-left: 5px solid #dc3545; 
    }
    .code { 
        background: #f8f9fa; 
        padding: 15px; 
        border-radius: 8px; 
        border-left: 4px solid #667eea; 
        font-family: monospace; 
        margin: 15px 0; 
    }
</style>";
echo "</head><body>";

echo "<div class='container'>";
echo "<h1>🔍 Debug de Sesión PHP</h1>";

// =====================================================
// 1. VERIFICAR SI HAY SESIÓN ACTIVA
// =====================================================
echo "<h2>1️⃣ Estado de la Sesión</h2>";

if (isset($_SESSION) && !empty($_SESSION)) {
    echo "<div class='alert success'>";
    echo "✅ <strong>Sesión activa detectada</strong>";
    echo "</div>";
} else {
    echo "<div class='alert error'>";
    echo "❌ <strong>No hay sesión activa o la sesión está vacía</strong>";
    echo "<p>Asegúrate de haber iniciado sesión primero.</p>";
    echo "</div>";
}

// =====================================================
// 2. MOSTRAR TODAS LAS VARIABLES DE SESIÓN
// =====================================================
echo "<h2>2️⃣ Variables de Sesión Disponibles</h2>";

if (!empty($_SESSION)) {
    echo "<table>";
    echo "<thead><tr><th>Variable</th><th>Valor</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($_SESSION as $key => $value) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
        echo "<td>" . htmlspecialchars(print_r($value, true)) . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "<div class='alert warning'>";
    echo "⚠️ No hay variables en la sesión";
    echo "</div>";
}

// =====================================================
// 3. VERIFICAR VARIABLES ESPECÍFICAS NECESARIAS
// =====================================================
echo "<h2>3️⃣ Verificación de Variables Necesarias</h2>";

$variables_necesarias = [
    'id_usuario' => 'ID del usuario',
    'id_rol' => 'Rol del usuario (2 = Doctor)',
    'nombre' => 'Nombre del usuario',
    'user_id' => 'ID alternativo (si se usa)',
    'rol' => 'Rol alternativo (si se usa)'
];

echo "<table>";
echo "<thead><tr><th>Variable</th><th>Descripción</th><th>Estado</th></tr></thead>";
echo "<tbody>";

foreach ($variables_necesarias as $var => $desc) {
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($var) . "</strong></td>";
    echo "<td>" . htmlspecialchars($desc) . "</td>";
    echo "<td>";
    
    if (isset($_SESSION[$var])) {
        echo "<span style='color: #28a745;'>✅ Existe: " . htmlspecialchars($_SESSION[$var]) . "</span>";
    } else {
        echo "<span style='color: #dc3545;'>❌ No existe</span>";
    }
    
    echo "</td>";
    echo "</tr>";
}

echo "</tbody></table>";

// =====================================================
// 4. CÓDIGO SUGERIDO PARA VISTA_MODULO_CITAS_PACIENTE.PHP
// =====================================================
echo "<h2>4️⃣ Código Sugerido para tu Vista</h2>";

echo "<div class='alert warning'>";
echo "<strong>⚠️ IMPORTANTE:</strong> Basándote en las variables que veas arriba, usa el código correcto:";
echo "</div>";

echo "<div class='code'>";
echo htmlspecialchars("<?php
// En vista_modulo_citas_paciente.php

require_once '../CONFIG/verificar_sesion.php';

\$id_paciente = \$_GET['id_paciente'] ?? 0;

// ✅ OPCIÓN 1: Si tu sesión usa 'id_usuario'
\$id_doctor = \$_SESSION['id_usuario'] ?? 0;

// ✅ OPCIÓN 2: Si tu sesión usa 'user_id'
// \$id_doctor = \$_SESSION['user_id'] ?? 0;

// 🔍 DEBUG temporal
error_log('🔵 ID Doctor: ' . \$id_doctor);
error_log('🔵 ID Paciente: ' . \$id_paciente);
error_log('🔵 Rol: ' . (\$_SESSION['id_rol'] ?? 'No definido'));

// Verificar que sea doctor (rol 2)
if (!isset(\$_SESSION['id_rol']) || \$_SESSION['id_rol'] != 2) {
    echo '<div>❌ Solo doctores pueden acceder</div>';
    exit;
}

if (\$id_paciente == 0 || \$id_doctor == 0) {
    echo '<div>⚠️ Información incompleta</div>';
    exit;
}
?>");
echo "</div>";

// =====================================================
// 5. VERIFICAR ROL
// =====================================================
echo "<h2>5️⃣ Verificación de Rol</h2>";

$id_rol = $_SESSION['id_rol'] ?? $_SESSION['rol'] ?? null;

if ($id_rol === null) {
    echo "<div class='alert warning'>";
    echo "⚠️ No se encontró variable de rol en la sesión";
    echo "</div>";
} elseif ($id_rol == 2) {
    echo "<div class='alert success'>";
    echo "✅ <strong>Usuario es DOCTOR (rol 2)</strong>";
    echo "</div>";
} else {
    echo "<div class='alert error'>";
    echo "❌ <strong>Usuario NO es doctor</strong>";
    echo "<p>Rol actual: " . htmlspecialchars($id_rol) . "</p>";
    echo "</div>";
}

// =====================================================
// 6. INFORMACIÓN DE SESIÓN PHP
// =====================================================
echo "<h2>6️⃣ Información de Sesión PHP</h2>";

echo "<table>";
echo "<tbody>";
echo "<tr><th>Session ID</th><td>" . session_id() . "</td></tr>";
echo "<tr><th>Session Name</th><td>" . session_name() . "</td></tr>";
echo "<tr><th>Session Status</th><td>" . (session_status() === PHP_SESSION_ACTIVE ? '✅ Activa' : '❌ Inactiva') . "</td></tr>";
echo "</tbody></table>";

// =====================================================
// 7. INSTRUCCIONES FINALES
// =====================================================
echo "<h2>7️⃣ Siguiente Paso</h2>";

echo "<div class='alert success'>";
echo "<strong>📝 Instrucciones:</strong>";
echo "<ol style='margin: 10px 0 0 20px;'>";
echo "<li>Identifica qué variable contiene el ID del usuario (id_usuario, user_id, etc.)</li>";
echo "<li>Verifica que el rol sea 2 para doctores</li>";
echo "<li>Usa la variable correcta en vista_modulo_citas_paciente.php</li>";
echo "<li><strong>Elimina este archivo debug_sesion.php después de verificar</strong></li>";
echo "</ol>";
echo "</div>";

echo "<div style='margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px;'>";
echo "<strong>🔒 Seguridad:</strong> Este archivo muestra información sensible. ";
echo "<span style='color: #dc3545;'>ELIMÍNALO después de usarlo.</span>";
echo "</div>";

echo "</div>"; // container
echo "</body></html>";
?>