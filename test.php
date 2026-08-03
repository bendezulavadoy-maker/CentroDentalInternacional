<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Paso 1: PHP funciona<br>";
require_once 'MODELOS/modelo_pacientes.php';
echo "Paso 2: modelo cargado<br>";
$modelo = new ModeloPacientes();
echo "Paso 3: instanciado<br>";
$lista = $modelo->listarPacientes();
echo "Paso 4 tipo: " . gettype($lista) . "<br>";
echo "Paso 4 count: " . (is_array($lista) ? count($lista) : 'no es array') . "<br>";
echo "Paso 4 json: " . json_encode($lista) . "<br>";
echo "JSON error: " . json_last_error_msg() . "<br>";
?>