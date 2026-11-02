<?php
// Documentos del paciente. Solo con sesion iniciada y con acceso al modulo de Historia Clinica;
// cada accion ademas exige su propio permiso (Configuracion -> Permisos por Rol).
require_once '../CONFIG/permisos.php';
requerirPermisoApi('historia_clinica');
require_once '../CONFIG/parametros.php';
require_once '../MODELOS/modelo_documentos.php';

ini_set('display_errors', 0);

$modelo    = new ModeloDocumentos();
$idUsuario = (int)($_SESSION['usuario']['id_usuario'] ?? 0);

// Entrada: cuerpo JSON, formulario o parametros de la URL
$json = json_decode(file_get_contents('php://input'), true);
$data = is_array($json) ? $json : [];
$accion = $data['accion'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';

function param($clave, $defecto = null) {
    global $data;
    return $data[$clave] ?? $_POST[$clave] ?? $_GET[$clave] ?? $defecto;
}

function responder(array $payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function entero($valor) {
    return (is_numeric($valor) && (int)$valor > 0) ? (int)$valor : 0;
}

if ($accion !== 'descargar_documento') {
    header('Content-Type: application/json; charset=utf-8');
}

function permisosDelPaciente($idPaciente) {
    return Permisos::documentosEnPaciente($idPaciente);
}

function pacienteDeItem($modelo, $tipo, $id) {
    $p = $tipo === 'carpeta' ? $modelo->pacienteDeCarpeta($id) : $modelo->pacienteDeDocumento($id);
    if (!$p) throw new NegocioException($tipo === 'carpeta' ? 'La carpeta no existe' : 'El documento no existe');
    return $p;
}

function nombreDeItem($modelo, $tipo, $id) {
    $r = $tipo === 'carpeta' ? $modelo->carpetaRaw($id) : $modelo->documentoRaw($id);
    return $r ? ($tipo === 'carpeta' ? $r['nombre_carpeta'] : $r['titulo']) : '';
}

/** Lista de elementos {tipo, id} enviada por la pantalla (uno o varios, seleccionados juntos) */
function leerItems() {
    $items = param('items');
    if (!is_array($items) || count($items) < 1) throw new NegocioException('No hay elementos seleccionados');
    if (count($items) > 200) throw new NegocioException('Seleccionaste demasiados elementos a la vez');
    $limpios = [];
    foreach ($items as $it) {
        $tipo = $it['tipo'] ?? '';
        $id = entero($it['id'] ?? 0);
        if (!in_array($tipo, ['carpeta', 'documento'], true) || !$id) throw new NegocioException('Elemento no válido');
        $limpios[] = ['tipo' => $tipo, 'id' => $id];
    }
    return $limpios;
}

/** Tipos MIME aceptados por extension (se comparan con el contenido real del archivo) */
function mimesPorExtension() {
    $cdf = ['application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'];
    $zip = ['application/zip', 'application/octet-stream'];
    return [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif'  => ['image/gif'],  'webp' => ['image/webp'],
        'doc'  => array_merge(['application/msword'], $cdf),
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $zip),
        'xls'  => array_merge(['application/vnd.ms-excel'], $cdf),
        'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $zip),
        'ppt'  => array_merge(['application/vnd.ms-powerpoint'], $cdf),
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $zip),
        'txt'  => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
    ];
}

function mimeParaDescarga($ext) {
    $m = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain', 'csv' => 'text/csv',
        'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel', 'ppt' => 'application/vnd.ms-powerpoint',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    return $m[$ext] ?? 'application/octet-stream';
}

try {
    switch ($accion) {

        // =====================================================
        // Lectura
        // =====================================================
        case 'obtener_estructura':
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente || !$modelo->pacienteExiste($idPaciente)) throw new NegocioException('Paciente no válido');

            // Limpieza automatica de la papelera (como maximo una vez por hora)
            $dias = Parametros::entero('papelera_dias', 15);
            $purga = $modelo->purgarSiCorresponde($dias);
            if ($purga && ($purga['documentos'] > 0 || $purga['carpetas'] > 0)) {
                registrarBitacora('papelera.purgar', null, null, null, $purga, 'sistema');
            }

            if (!$modelo->existeEstructura($idPaciente)) {
                $modelo->crearEstructuraInicial($idPaciente, $idUsuario);
            }
            responder([
                'success'       => true,
                'estructura'    => $modelo->obtenerEstructuraCarpetas($idPaciente),
                'permisos'      => permisosDelPaciente($idPaciente),
                'papelera_dias' => $dias,
                'max_mb'        => (int)floor(Parametros::maxBytesDocumento() / 1048576),
                'extensiones'   => Parametros::extensionesDocumento(),
            ]);

        case 'obtener_carpetas_raiz':
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente) throw new NegocioException('Paciente no válido');
            responder(['success' => true, 'carpetas' => $modelo->obtenerEstructuraCarpetas($idPaciente)]);

        case 'obtener_subcarpetas':
            $idCarpeta = entero(param('id_carpeta'));
            if (!$idCarpeta || !$modelo->pacienteDeCarpeta($idCarpeta)) throw new NegocioException('La carpeta no existe');
            $arbol = $modelo->obtenerEstructuraCarpetas($modelo->pacienteDeCarpeta($idCarpeta));
            $buscar = function ($nodos) use (&$buscar, $idCarpeta) {
                foreach ($nodos as $n) {
                    if ($n['id_carpeta'] === $idCarpeta) return $n['subcarpetas'];
                    $r = $buscar($n['subcarpetas']);
                    if ($r !== null) return $r;
                }
                return null;
            };
            responder(['success' => true, 'subcarpetas' => $buscar($arbol) ?? []]);

        case 'obtener_carpeta':
            $idCarpeta = entero(param('id_carpeta'));
            $carpeta = $idCarpeta ? $modelo->obtenerCarpeta($idCarpeta) : false;
            if (!$carpeta) throw new NegocioException('La carpeta no existe');
            responder(['success' => true, 'carpeta' => $carpeta]);

        case 'obtener_documentos_carpeta':
            $idCarpeta = entero(param('id_carpeta'));
            if (!$idCarpeta || !$modelo->pacienteDeCarpeta($idCarpeta)) throw new NegocioException('La carpeta no existe');
            responder(['success' => true, 'documentos' => $modelo->obtenerDocumentosCarpeta($idCarpeta)]);

        case 'obtener_documentos_raiz':
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente) throw new NegocioException('Paciente no válido');
            responder(['success' => true, 'documentos' => $modelo->obtenerDocumentosRaiz($idPaciente)]);

        case 'obtener_documentos_paciente':
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente) throw new NegocioException('Paciente no válido');
            responder(['success' => true, 'documentos' => $modelo->obtenerDocumentosPaciente($idPaciente, entero(param('limit')) ?: null)]);

        case 'obtener_estadisticas':
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente) throw new NegocioException('Paciente no válido');
            responder(['success' => true, 'estadisticas' => $modelo->obtenerEstadisticas($idPaciente)]);

        case 'permisos_documentos':
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente) throw new NegocioException('Paciente no válido');
            responder(['success' => true, 'permisos' => permisosDelPaciente($idPaciente)]);

        case 'obtener_historial_carpeta':
            $idCarpeta = entero(param('id_carpeta'));
            if (!$idCarpeta) throw new NegocioException('La carpeta no existe');
            responder(['success' => true, 'historial' => $modelo->obtenerHistorialCarpeta($idCarpeta, entero(param('limit')) ?: 10)]);

        case 'obtener_historial_documento':
            $idDocumento = entero(param('id_documento'));
            if (!$idDocumento) throw new NegocioException('El documento no existe');
            responder(['success' => true, 'historial' => $modelo->obtenerHistorialDocumento($idDocumento, entero(param('limit')) ?: 10)]);

        // =====================================================
        // Carpetas
        // =====================================================
        case 'crear_carpeta':
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente) throw new NegocioException('Paciente no válido');
            Permisos::requerir('documentos.crear_carpeta', $idPaciente);

            $idCarpeta = $modelo->crearCarpeta([
                'nombre_carpeta'   => param('nombre_carpeta', ''),
                'id_carpeta_padre' => param('id_carpeta_padre'),
                'id_paciente'      => $idPaciente,
                'color'            => param('color', ''),
                'creado_por'       => $idUsuario,
                // "es_sistema", "icono" y "orden" nunca se aceptan desde el navegador
            ]);
            registrarBitacora('carpeta.crear', 'carpeta', $idCarpeta, $idPaciente, ['nombre' => param('nombre_carpeta')]);
            responder(['success' => true, 'mensaje' => 'Carpeta creada correctamente', 'carpeta' => $modelo->obtenerCarpeta($idCarpeta)]);

        case 'editar_carpeta':
            $idCarpeta = entero(param('id_carpeta'));
            $idPaciente = $idCarpeta ? $modelo->pacienteDeCarpeta($idCarpeta) : null;
            if (!$idPaciente) throw new NegocioException('La carpeta no existe');
            Permisos::requerir('documentos.renombrar', $idPaciente);

            $anterior = $modelo->carpetaRaw($idCarpeta);
            $modelo->editarCarpeta($idCarpeta, ['nombre_carpeta' => param('nombre_carpeta', ''), 'color' => param('color')], $idUsuario);
            registrarBitacora('carpeta.renombrar', 'carpeta', $idCarpeta, $idPaciente,
                ['antes' => $anterior['nombre_carpeta'], 'despues' => param('nombre_carpeta')]);
            responder(['success' => true, 'mensaje' => 'Carpeta actualizada']);

        // =====================================================
        // Mover / copiar / eliminar (uno o varios elementos a la vez, todo o nada)
        // =====================================================
        case 'mover_items':
        case 'mover_carpeta':
        case 'mover_documento':
            if ($accion === 'mover_carpeta')   $data['items'] = [['tipo' => 'carpeta',   'id' => param('id_carpeta')]];
            if ($accion === 'mover_documento') $data['items'] = [['tipo' => 'documento', 'id' => param('id_documento')]];
            $items = leerItems();
            if (!array_key_exists('id_carpeta_destino', $data) && !isset($_POST['id_carpeta_destino']) && !isset($_GET['id_carpeta_destino'])) {
                throw new NegocioException('Falta la carpeta de destino');
            }
            $destino = param('id_carpeta_destino');

            foreach ($items as $it) {
                Permisos::requerir('documentos.mover', pacienteDeItem($modelo, $it['tipo'], $it['id']));
            }
            $movidos = 0; $iguales = 0;
            $modelo->enTransaccion(function () use ($modelo, $items, $destino, $idUsuario, &$movidos, &$iguales) {
                foreach ($items as $it) {
                    try {
                        $r = $it['tipo'] === 'carpeta'
                            ? $modelo->moverCarpeta($it['id'], $destino, $idUsuario)
                            : $modelo->moverDocumento($it['id'], $destino, $idUsuario);
                    } catch (NegocioException $e) {
                        throw new NegocioException('No se pudo mover "' . nombreDeItem($modelo, $it['tipo'], $it['id']) . '": ' . $e->getMessage());
                    }
                    if ($r === 'sin_cambios') $iguales++; else $movidos++;
                }
            });
            if ($movidos === 0) {
                responder(['success' => false, 'mensaje' => count($items) === 1 ? 'Ya está en esa ubicación' : 'Los elementos ya estaban en esa ubicación']);
            }
            foreach ($items as $it) {
                registrarBitacora($it['tipo'] . '.mover', $it['tipo'], $it['id'], pacienteDeItem($modelo, $it['tipo'], $it['id']), ['destino' => $destino ? (int)$destino : null]);
            }
            responder(['success' => true, 'mensaje' => $movidos === 1 ? 'Elemento movido' : "$movidos elementos movidos", 'movidos' => $movidos, 'sin_cambios' => $iguales]);

        case 'copiar_items':
        case 'copiar_carpeta':
        case 'copiar_documento':
            if ($accion === 'copiar_carpeta')   $data['items'] = [['tipo' => 'carpeta',   'id' => param('id_carpeta')]];
            if ($accion === 'copiar_documento') $data['items'] = [['tipo' => 'documento', 'id' => param('id_documento')]];
            $items = leerItems();
            $destino = param('id_carpeta_destino');

            foreach ($items as $it) {
                Permisos::requerir($it['tipo'] === 'carpeta' ? 'documentos.crear_carpeta' : 'documentos.subir',
                                   pacienteDeItem($modelo, $it['tipo'], $it['id']));
            }
            $archivosCreados = [];
            $copiados = 0;
            try {
                $modelo->enTransaccion(function () use ($modelo, $items, $destino, $idUsuario, &$archivosCreados, &$copiados) {
                    foreach ($items as $it) {
                        try {
                            if ($it['tipo'] === 'carpeta') $modelo->copiarCarpeta($it['id'], $destino, $idUsuario, $archivosCreados);
                            else                           $modelo->copiarDocumento($it['id'], $destino, $idUsuario, $archivosCreados);
                        } catch (NegocioException $e) {
                            throw new NegocioException('No se pudo copiar "' . nombreDeItem($modelo, $it['tipo'], $it['id']) . '": ' . $e->getMessage());
                        }
                        $copiados++;
                    }
                });
            } catch (Throwable $e) {
                foreach ($archivosCreados as $ruta) { if (is_file($ruta)) @unlink($ruta); }   // la base se revirtio: no dejar archivos sueltos
                throw $e;
            }
            foreach ($items as $it) {
                registrarBitacora($it['tipo'] . '.copiar', $it['tipo'], $it['id'], pacienteDeItem($modelo, $it['tipo'], $it['id']), ['destino' => $destino ? (int)$destino : null]);
            }
            responder(['success' => true, 'mensaje' => $copiados === 1 ? 'Elemento copiado' : "$copiados elementos copiados"]);

        case 'eliminar_items':
        case 'eliminar_carpeta':
        case 'eliminar_documento':
            if ($accion === 'eliminar_carpeta')   $data['items'] = [['tipo' => 'carpeta',   'id' => param('id_carpeta')]];
            if ($accion === 'eliminar_documento') $data['items'] = [['tipo' => 'documento', 'id' => param('id_documento')]];
            $items = leerItems();

            foreach ($items as $it) {
                Permisos::requerir('documentos.eliminar', pacienteDeItem($modelo, $it['tipo'], $it['id']));
            }
            $resumenes = [];
            $modelo->enTransaccion(function () use ($modelo, $items, $idUsuario, &$resumenes) {
                foreach ($items as $it) {
                    try {
                        $resumenes[] = $it['tipo'] === 'carpeta'
                            ? array_merge(['tipo' => 'carpeta', 'id' => $it['id']], $modelo->eliminarCarpeta($it['id'], $idUsuario))
                            : array_merge(['tipo' => 'documento', 'id' => $it['id']], $modelo->eliminarDocumento($it['id'], $idUsuario));
                    } catch (NegocioException $e) {
                        throw new NegocioException('No se pudo eliminar "' . nombreDeItem($modelo, $it['tipo'], $it['id']) . '": ' . $e->getMessage());
                    }
                }
            });
            foreach ($resumenes as $r) {
                registrarBitacora($r['tipo'] . '.eliminar', $r['tipo'], $r['id'], $r['id_paciente'],
                    ['nombre' => $r['nombre'], 'carpetas' => $r['carpetas'], 'documentos' => $r['documentos'], 'lote' => $r['lote']]);
            }
            $n = count($resumenes);
            responder(['success' => true, 'mensaje' => $n === 1 ? 'Enviado a la papelera' : "$n elementos enviados a la papelera"]);

        // =====================================================
        // Documentos
        // =====================================================
        case 'subir_documento':
            $idPaciente = entero($_POST['id_paciente'] ?? 0);
            if (!$idPaciente || !$modelo->pacienteExiste($idPaciente)) throw new NegocioException('Paciente no válido');
            Permisos::requerir('documentos.subir', $idPaciente);

            $archivo = $_FILES['archivo'] ?? null;
            if (!$archivo) throw new NegocioException('No se recibió ningún archivo');
            if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
                throw new NegocioException('El archivo supera el tamaño máximo que permite el servidor');
            }
            if ($archivo['error'] !== UPLOAD_ERR_OK) throw new NegocioException('Error al subir el archivo');
            if ($archivo['size'] <= 0) throw new NegocioException('El archivo está vacío');

            $maxBytes = Parametros::maxBytesDocumento();
            if ($archivo['size'] > $maxBytes) {
                throw new NegocioException('El archivo es demasiado grande. Máximo ' . (int)floor($maxBytes / 1048576) . ' MB');
            }

            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, Parametros::extensionesDocumento(), true)) {
                throw new NegocioException('Tipo de archivo no permitido. Se aceptan: ' . strtoupper(implode(', ', Parametros::extensionesDocumento())));
            }

            // El contenido real del archivo debe corresponder a su extension (no basta con el nombre)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo->file($archivo['tmp_name']);
            $aceptados = mimesPorExtension()[$extension] ?? [];
            if (!in_array($mimeReal, $aceptados, true)) {
                throw new NegocioException('El contenido del archivo no corresponde a su extensión (.' . $extension . ')');
            }

            $idCarpeta = entero($_POST['id_carpeta'] ?? 0) ?: null;

            $titulo = trim((string)($_POST['titulo'] ?? ''));
            if ($titulo === '') {   // titulo tomado del nombre del archivo: se limpian los caracteres no permitidos
                $titulo = trim(preg_replace('/[<>\x00-\x1F]/', '', pathinfo($archivo['name'], PATHINFO_FILENAME)));
                if ($titulo === '') $titulo = 'Documento';
                $titulo = mb_substr($titulo, 0, 200, 'UTF-8');
            }

            $dir = dirname(__DIR__) . '/ARCHIVOS/documentos_pacientes/' . $idPaciente . '/';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new Exception('No se pudo crear la carpeta del paciente');
            $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
            $rutaDestino = $dir . $nombreArchivo;
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) throw new Exception('No se pudo guardar el archivo');

            try {
                $idDocumento = $modelo->subirDocumento([
                    'id_paciente'    => $idPaciente,
                    'id_carpeta'     => $idCarpeta,
                    'titulo'         => $titulo,
                    'archivo'        => 'ARCHIVOS/documentos_pacientes/' . $idPaciente . '/' . $nombreArchivo,
                    'tipo'           => $mimeReal,
                    'extension'      => $extension,
                    'tamano_archivo' => $archivo['size'],
                    'descripcion'    => trim((string)($_POST['descripcion'] ?? '')),
                    'subido_por'     => $idUsuario,
                ]);
            } catch (Throwable $e) {
                @unlink($rutaDestino);   // si no se pudo registrar, no dejar el archivo suelto
                throw $e;
            }
            registrarBitacora('documento.subir', 'documento', $idDocumento, $idPaciente, ['titulo' => $titulo, 'tamano' => (int)$archivo['size']]);
            responder(['success' => true, 'mensaje' => 'Documento subido correctamente', 'id_documento' => $idDocumento]);

        case 'editar_documento':
            $idDocumento = entero(param('id_documento'));
            $idPaciente = $idDocumento ? $modelo->pacienteDeDocumento($idDocumento) : null;
            if (!$idPaciente) throw new NegocioException('El documento no existe');
            Permisos::requerir('documentos.renombrar', $idPaciente);

            $anterior = $modelo->documentoRaw($idDocumento);
            $modelo->editarDocumento($idDocumento, ['titulo' => param('titulo', ''), 'descripcion' => param('descripcion')], $idUsuario);
            registrarBitacora('documento.renombrar', 'documento', $idDocumento, $idPaciente,
                ['antes' => $anterior['titulo'], 'despues' => param('titulo')]);
            responder(['success' => true, 'mensaje' => 'Documento actualizado']);

        // Ver o descargar: el archivo NO se puede abrir por su direccion, solo pasando por aqui
        case 'descargar_documento':
            $idDocumento = entero(param('id_documento'));
            $doc = $idDocumento ? $modelo->obtenerDocumento($idDocumento) : false;
            if (!$doc) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('Documento no encontrado'); }
            Permisos::requerir('documentos.descargar', (int)$doc['id_paciente']);

            $ruta = $modelo->rutaFisicaSegura($doc['archivo']);
            if (!$ruta) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('El archivo no está disponible'); }

            $ext = strtolower($doc['extension']);
            $mostrar = (param('modo') === 'ver') && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
            $nombre = trim(preg_replace('/[^\p{L}\p{N} _\-\.\(\)]/u', '', $doc['titulo'])) ?: 'documento';
            $nombreAscii = preg_replace('/[^A-Za-z0-9 _\-\.\(\)]/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre);

            $modelo->registrarHistorialDocumento($idDocumento, 'descargar', $idUsuario, ['modo' => $mostrar ? 'ver' : 'descargar']);
            registrarBitacora('documento.descargar', 'documento', $idDocumento, (int)$doc['id_paciente'], ['titulo' => $doc['titulo'], 'modo' => $mostrar ? 'ver' : 'descargar']);

            header('Content-Type: ' . mimeParaDescarga($ext));
            header('Content-Disposition: ' . ($mostrar ? 'inline' : 'attachment') . '; filename="' . $nombreAscii . '.' . $ext . '"; filename*=UTF-8\'\'' . rawurlencode($nombre . '.' . $ext));
            header('Content-Length: ' . filesize($ruta));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
            session_write_close();   // no bloquear otras peticiones mientras se envia el archivo
            readfile($ruta);
            exit;

        // =====================================================
        // Papelera (solo quien tenga ese permiso: por defecto el administrador)
        // =====================================================
        case 'listar_papelera':
            Permisos::requerir('documentos.papelera');
            $idPaciente = entero(param('id_paciente'));
            if (!$idPaciente) throw new NegocioException('Paciente no válido');
            $dias = Parametros::entero('papelera_dias', 15);
            responder(['success' => true, 'items' => $modelo->listarPapelera($idPaciente, $dias), 'papelera_dias' => $dias]);

        case 'restaurar_papelera':
            Permisos::requerir('documentos.papelera');
            $lote = (string)param('lote', '');
            $idPaciente = $modelo->pacienteDeLote($lote);
            if (!$idPaciente) throw new NegocioException('Ese elemento ya no está en la papelera');
            $r = $modelo->restaurarLote($lote, $idUsuario);
            registrarBitacora('papelera.restaurar', 'lote', null, $idPaciente, ['lote' => $lote, 'carpetas' => $r['carpetas'], 'documentos' => $r['documentos']]);
            $msg = 'Elemento restaurado' . ($r['a_inicio'] ? ' en Inicio (su ubicación original ya no estaba disponible)' : '');
            responder(['success' => true, 'mensaje' => $msg, 'a_inicio' => $r['a_inicio']]);

        default:
            throw new NegocioException('Acción no válida');
    }

} catch (NegocioException $e) {
    responder(['success' => false, 'mensaje' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('controlador_documentos: ' . $e->getMessage());
    http_response_code(500);
    responder(['success' => false, 'mensaje' => 'Ocurrió un error inesperado. Intenta nuevamente.']);
}
