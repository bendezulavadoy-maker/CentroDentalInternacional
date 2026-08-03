function cargarEnv($ruta) {
    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0) continue;
        list($clave, $valor) = explode('=', $linea, 2);
        putenv(trim($clave) . '=' . trim($valor));
    }
}

cargarEnv(__DIR__ . '/../.env');