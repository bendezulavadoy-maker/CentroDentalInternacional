<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

/** Error de regla de negocio: su mensaje SI se le muestra al usuario (los errores tecnicos no). */
if (!class_exists('NegocioException')) {
    class NegocioException extends Exception {}
}

class ModeloDocumentos {
    private $conexion;
    private $baseArchivos;   // ruta absoluta de ARCHIVOS/documentos_pacientes

    const MAX_ELEMENTOS_COPIA = 300;

    public function __construct() {
        $this->conexion = (new Conexion())->getConexion();
        $this->baseArchivos = dirname(__DIR__) . '/ARCHIVOS/documentos_pacientes';
    }

    // =====================================================
    // Transacciones: si ya hay una abierta (operacion por lote), se reutiliza
    // =====================================================
    private function transaccion(callable $fn) {
        $propia = !$this->conexion->inTransaction();
        if ($propia) $this->conexion->beginTransaction();
        try {
            $r = $fn();
            if ($propia && $this->conexion->inTransaction()) $this->conexion->commit();
            return $r;
        } catch (Throwable $e) {
            if ($propia && $this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $e;
        }
    }

    /** Para que el controlador agrupe varias operaciones en una sola transaccion (todo o nada) */
    public function enTransaccion(callable $fn) {
        return $this->transaccion($fn);
    }

    // =====================================================
    // Validaciones de texto
    // =====================================================
    private function limpiarNombre($nombre, $max, $campo) {
        $nombre = trim(preg_replace('/\s+/u', ' ', (string)$nombre));
        $largo = mb_strlen($nombre, 'UTF-8');
        if ($largo < 1) {
            throw new NegocioException("$campo es obligatorio");
        }
        if ($largo > $max) {
            throw new NegocioException("$campo no puede superar los $max caracteres");
        }
        if (preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/', $nombre)) {
            throw new NegocioException("$campo contiene caracteres no permitidos (< >)");
        }
        return $nombre;
    }

    private function colorValido($color) {
        return (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) ? $color : '#3498db';
    }

    private function iconoValido($icono) {
        return (is_string($icono) && preg_match('/^[a-z0-9\-]{1,40}$/', $icono)) ? $icono : 'ti-folder';
    }

    private function idONull($valor) {
        return ($valor === null || $valor === '' || (int)$valor === 0) ? null : (int)$valor;
    }

    // =====================================================
    // Lectura de entidades (incluye las eliminadas, para papelera y validaciones)
    // =====================================================
    public function carpetaRaw($idCarpeta) {
        $s = $this->conexion->prepare("SELECT * FROM carpetas_documentos WHERE id_carpeta = :id");
        $s->execute([':id' => (int)$idCarpeta]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function documentoRaw($idDocumento) {
        $s = $this->conexion->prepare("SELECT * FROM documentos_paciente WHERE id_documento = :id");
        $s->execute([':id' => (int)$idDocumento]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function pacienteDeCarpeta($idCarpeta) {
        $c = $this->carpetaRaw($idCarpeta);
        return $c ? (int)$c['id_paciente'] : null;
    }

    public function pacienteDeDocumento($idDocumento) {
        $d = $this->documentoRaw($idDocumento);
        return $d ? (int)$d['id_paciente'] : null;
    }

    public function pacienteExiste($idPaciente) {
        $s = $this->conexion->prepare("SELECT 1 FROM pacientes WHERE id_paciente = :id");
        $s->execute([':id' => (int)$idPaciente]);
        return (bool)$s->fetchColumn();
    }

    /** Carpeta activa que pertenece al paciente, o error de negocio */
    private function exigirCarpetaActiva($idCarpeta, $idPaciente, $campo = 'La carpeta') {
        $c = $this->carpetaRaw($idCarpeta);
        if (!$c || (int)$c['activo'] !== 1) {
            throw new NegocioException("$campo no existe");
        }
        if ((int)$c['id_paciente'] !== (int)$idPaciente) {
            throw new NegocioException("$campo no pertenece a este paciente");
        }
        return $c;
    }

    // =====================================================
    // Estructura de carpetas
    // =====================================================
    public function crearEstructuraInicial($idPaciente, $idUsuario) {
        try {
            $stmt = $this->conexion->prepare("CALL crear_estructura_carpetas_inicial(:id_paciente, :id_usuario)");
            $ok = $stmt->execute([':id_paciente' => (int)$idPaciente, ':id_usuario' => (int)$idUsuario]);
            $stmt->closeCursor();
            return $ok;
        } catch (PDOException $e) {
            error_log("Error al crear estructura inicial: " . $e->getMessage());
            return false;
        }
    }

    public function existeEstructura($idPaciente) {
        $s = $this->conexion->prepare("SELECT COUNT(*) FROM carpetas_documentos WHERE id_paciente = :p AND activo = 1");
        $s->execute([':p' => (int)$idPaciente]);
        return (int)$s->fetchColumn() > 0;
    }

    /** Arbol completo de carpetas del paciente (una sola consulta) */
    public function obtenerEstructuraCarpetas($idPaciente) {
        $s = $this->conexion->prepare(
            "SELECT * FROM vista_carpetas_completa WHERE id_paciente = :p ORDER BY orden ASC, nombre_carpeta ASC"
        );
        $s->execute([':p' => (int)$idPaciente]);
        $filas = $s->fetchAll(PDO::FETCH_ASSOC);

        $porPadre = [];
        foreach ($filas as $f) {
            foreach (['id_carpeta', 'id_paciente', 'es_sistema', 'num_subcarpetas', 'num_documentos'] as $k) {
                $f[$k] = (int)$f[$k];
            }
            $f['id_carpeta_padre'] = $f['id_carpeta_padre'] === null ? null : (int)$f['id_carpeta_padre'];
            $porPadre[$f['id_carpeta_padre'] ?? 0][] = $f;
        }
        $construir = function ($padre) use (&$construir, &$porPadre) {
            $res = [];
            foreach ($porPadre[$padre] ?? [] as $c) {
                $c['subcarpetas'] = $construir($c['id_carpeta']);
                $res[] = $c;
            }
            return $res;
        };
        return $construir(0);
    }

    public function obtenerCarpeta($idCarpeta) {
        $s = $this->conexion->prepare("SELECT * FROM vista_carpetas_completa WHERE id_carpeta = :id");
        $s->execute([':id' => (int)$idCarpeta]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    // =====================================================
    // Nombres y jerarquia
    // =====================================================
    private function existeNombreCarpeta($idPaciente, $idPadre, $nombre, $excluirId = 0) {
        $sql = "SELECT 1 FROM carpetas_documentos
                WHERE id_paciente = :p AND activo = 1 AND nombre_carpeta = :n AND id_carpeta <> :x
                  AND " . ($idPadre === null ? "id_carpeta_padre IS NULL" : "id_carpeta_padre = :padre") . " LIMIT 1";
        $params = [':p' => (int)$idPaciente, ':n' => $nombre, ':x' => (int)$excluirId];
        if ($idPadre !== null) $params[':padre'] = (int)$idPadre;
        $s = $this->conexion->prepare($sql);
        $s->execute($params);
        return (bool)$s->fetchColumn();
    }

    /** Nombre libre en el destino: "Nombre", "Nombre (sufijo)", "Nombre (sufijo 2)"... */
    private function nombreLibre($idPaciente, $idPadre, $base, $sufijo) {
        if (!$this->existeNombreCarpeta($idPaciente, $idPadre, $base)) return $base;
        $recorte = mb_substr($base, 0, 100 - mb_strlen(" ($sufijo 99)", 'UTF-8'), 'UTF-8');
        $candidato = "$recorte ($sufijo)";
        $n = 2;
        while ($this->existeNombreCarpeta($idPaciente, $idPadre, $candidato) && $n < 100) {
            $candidato = "$recorte ($sufijo $n)";
            $n++;
        }
        return $candidato;
    }

    /** ¿$idPosible es la misma carpeta o esta dentro de ella (a cualquier profundidad)? */
    private function esMismaODescendiente($idCarpeta, $idPosible) {
        $actual = (int)$idPosible;
        $guardia = 0;
        while ($actual && $guardia++ < 200) {
            if ($actual === (int)$idCarpeta) return true;
            $c = $this->carpetaRaw($actual);
            $actual = $c && $c['id_carpeta_padre'] !== null ? (int)$c['id_carpeta_padre'] : 0;
        }
        return false;
    }

    /** IDs de la carpeta y de todas sus subcarpetas activas */
    private function idsDeArbol($idCarpeta) {
        $ids = [(int)$idCarpeta];
        $pendientes = [(int)$idCarpeta];
        while (!empty($pendientes)) {
            $in = implode(',', array_fill(0, count($pendientes), '?'));
            $s = $this->conexion->prepare("SELECT id_carpeta FROM carpetas_documentos WHERE activo = 1 AND id_carpeta_padre IN ($in)");
            $s->execute($pendientes);
            $hijos = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
            $pendientes = array_values(array_diff($hijos, $ids));
            $ids = array_merge($ids, $pendientes);
            if (count($ids) > 5000) throw new NegocioException('La carpeta tiene demasiados elementos');
        }
        return $ids;
    }

    // =====================================================
    // Carpetas: crear, renombrar, mover, copiar, eliminar
    // =====================================================
    public function crearCarpeta($datos) {
        return $this->transaccion(function () use ($datos) {
            $idPaciente = (int)$datos['id_paciente'];
            $nombre = $this->limpiarNombre($datos['nombre_carpeta'] ?? '', 100, 'El nombre de la carpeta');
            $idPadre = $this->idONull($datos['id_carpeta_padre'] ?? null);

            if (!$this->pacienteExiste($idPaciente)) throw new NegocioException('El paciente no existe');
            if ($idPadre !== null) $this->exigirCarpetaActiva($idPadre, $idPaciente, 'La carpeta de destino');
            if ($this->existeNombreCarpeta($idPaciente, $idPadre, $nombre)) {
                throw new NegocioException('Ya existe una carpeta con ese nombre en esta ubicación');
            }

            $s = $this->conexion->prepare(
                "INSERT INTO carpetas_documentos (nombre_carpeta, id_carpeta_padre, id_paciente, es_sistema, orden, icono, color, creado_por)
                 VALUES (:nombre, :padre, :paciente, :sistema, :orden, :icono, :color, :creado)"
            );
            $s->execute([
                ':nombre'   => $nombre,
                ':padre'    => $idPadre,
                ':paciente' => $idPaciente,
                ':sistema'  => !empty($datos['es_sistema']) ? 1 : 0,
                ':orden'    => (int)($datos['orden'] ?? 0),
                ':icono'    => $this->iconoValido($datos['icono'] ?? ''),
                ':color'    => $this->colorValido($datos['color'] ?? ''),
                ':creado'   => (int)$datos['creado_por'],
            ]);
            $id = (int)$this->conexion->lastInsertId();
            $this->registrarHistorialCarpeta($id, 'crear', $datos['creado_por'], ['nombre' => $nombre]);
            return $id;
        });
    }

    public function editarCarpeta($idCarpeta, $datos, $idUsuario) {
        return $this->transaccion(function () use ($idCarpeta, $datos, $idUsuario) {
            $c = $this->carpetaRaw($idCarpeta);
            if (!$c || (int)$c['activo'] !== 1) throw new NegocioException('La carpeta no existe');
            if ((int)$c['es_sistema'] === 1) throw new NegocioException('Las carpetas del sistema no se pueden modificar');

            $nombre = $this->limpiarNombre($datos['nombre_carpeta'] ?? '', 100, 'El nombre de la carpeta');
            $idPadre = $c['id_carpeta_padre'] === null ? null : (int)$c['id_carpeta_padre'];
            if ($this->existeNombreCarpeta($c['id_paciente'], $idPadre, $nombre, $idCarpeta)) {
                throw new NegocioException('Ya existe una carpeta con ese nombre en esta ubicación');
            }
            $color = $this->colorValido($datos['color'] ?? $c['color']);

            $s = $this->conexion->prepare(
                "UPDATE carpetas_documentos SET nombre_carpeta = :n, color = :color, editado_por = :u WHERE id_carpeta = :id"
            );
            $s->execute([':n' => $nombre, ':color' => $color, ':u' => (int)$idUsuario, ':id' => (int)$idCarpeta]);

            $this->registrarHistorialCarpeta($idCarpeta, $nombre !== $c['nombre_carpeta'] ? 'renombrar' : 'editar', $idUsuario,
                ['anterior' => $c['nombre_carpeta'], 'nuevo' => $nombre]);
            return true;
        });
    }

    /** Devuelve true si la movio, o 'sin_cambios' si ya estaba en el destino */
    public function moverCarpeta($idCarpeta, $idDestino, $idUsuario) {
        return $this->transaccion(function () use ($idCarpeta, $idDestino, $idUsuario) {
            $c = $this->carpetaRaw($idCarpeta);
            if (!$c || (int)$c['activo'] !== 1) throw new NegocioException('La carpeta no existe');
            if ((int)$c['es_sistema'] === 1) throw new NegocioException('Las carpetas del sistema no se pueden mover');

            $idDestino = $this->idONull($idDestino);
            $padreActual = $c['id_carpeta_padre'] === null ? null : (int)$c['id_carpeta_padre'];

            if ($idDestino !== null) {
                $this->exigirCarpetaActiva($idDestino, $c['id_paciente'], 'La carpeta de destino');
                if ($this->esMismaODescendiente($idCarpeta, $idDestino)) {
                    throw new NegocioException('No se puede mover una carpeta dentro de sí misma o de una de sus subcarpetas');
                }
            }
            if ($idDestino === $padreActual) return 'sin_cambios';
            if ($this->existeNombreCarpeta($c['id_paciente'], $idDestino, $c['nombre_carpeta'], $idCarpeta)) {
                throw new NegocioException('Ya existe una carpeta llamada "' . $c['nombre_carpeta'] . '" en el destino');
            }

            $s = $this->conexion->prepare("UPDATE carpetas_documentos SET id_carpeta_padre = :d, editado_por = :u WHERE id_carpeta = :id");
            $s->execute([':d' => $idDestino, ':u' => (int)$idUsuario, ':id' => (int)$idCarpeta]);

            $this->registrarHistorialCarpeta($idCarpeta, 'mover', $idUsuario, ['desde' => $padreActual, 'hacia' => $idDestino]);
            return true;
        });
    }

    /** Copia una carpeta con todo su contenido. Devuelve el id de la copia. */
    public function copiarCarpeta($idCarpeta, $idDestino, $idUsuario, &$archivosCreados = null) {
        $propio = ($archivosCreados === null);   // si no lo da quien llama, limpia solo
        if ($propio) $archivosCreados = [];
        try {
            return $this->transaccion(function () use ($idCarpeta, $idDestino, $idUsuario, &$archivosCreados) {
                $c = $this->carpetaRaw($idCarpeta);
                if (!$c || (int)$c['activo'] !== 1) throw new NegocioException('La carpeta no existe');

                $idDestino = $this->idONull($idDestino);
                if ($idDestino !== null) {
                    $this->exigirCarpetaActiva($idDestino, $c['id_paciente'], 'La carpeta de destino');
                    if ($this->esMismaODescendiente($idCarpeta, $idDestino)) {
                        throw new NegocioException('No se puede copiar una carpeta dentro de sí misma o de una de sus subcarpetas');
                    }
                }

                $ids = $this->idsDeArbol($idCarpeta);
                $in = implode(',', array_fill(0, count($ids), '?'));
                $sd = $this->conexion->prepare("SELECT COUNT(*) FROM documentos_paciente WHERE activo = 1 AND id_carpeta IN ($in)");
                $sd->execute($ids);
                if (count($ids) + (int)$sd->fetchColumn() > self::MAX_ELEMENTOS_COPIA) {
                    throw new NegocioException('La carpeta tiene demasiados elementos para copiarla de una vez');
                }

                return $this->copiarCarpetaRec($c, $idDestino, $idUsuario, $archivosCreados, true);
            });
        } catch (Throwable $e) {
            if ($propio) { foreach ($archivosCreados as $ruta) { if (is_file($ruta)) @unlink($ruta); } }
            throw $e;
        }
    }

    private function copiarCarpetaRec(array $original, $idPadreDestino, $idUsuario, array &$archivosCreados, $esRaiz) {
        $nombre = $esRaiz
            ? $this->nombreLibre($original['id_paciente'], $idPadreDestino, $original['nombre_carpeta'], 'copia')
            : $original['nombre_carpeta'];
        $idNueva = $this->crearCarpeta([
            'nombre_carpeta'   => $nombre,
            'id_carpeta_padre' => $idPadreDestino,
            'id_paciente'      => $original['id_paciente'],
            'es_sistema'       => 0,                       // una copia nunca es "del sistema"
            'orden'            => $original['orden'],
            'icono'            => $original['icono'],
            'color'            => $original['color'],
            'creado_por'       => $idUsuario,
        ]);

        $sd = $this->conexion->prepare("SELECT * FROM documentos_paciente WHERE activo = 1 AND id_carpeta = :c");
        $sd->execute([':c' => (int)$original['id_carpeta']]);
        foreach ($sd->fetchAll(PDO::FETCH_ASSOC) as $doc) {
            $this->copiarDocumentoInterno($doc, $idNueva, $idUsuario, $archivosCreados, false);
        }

        $sc = $this->conexion->prepare("SELECT * FROM carpetas_documentos WHERE activo = 1 AND id_carpeta_padre = :c AND id_carpeta <> :nueva");
        $sc->execute([':c' => (int)$original['id_carpeta'], ':nueva' => $idNueva]);
        foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $sub) {
            $this->copiarCarpetaRec($sub, $idNueva, $idUsuario, $archivosCreados, false);
        }
        return $idNueva;
    }

    /** Eliminar = enviar a la papelera (la carpeta y todo lo que contiene, con el mismo lote) */
    public function eliminarCarpeta($idCarpeta, $idUsuario) {
        return $this->transaccion(function () use ($idCarpeta, $idUsuario) {
            $c = $this->carpetaRaw($idCarpeta);
            if (!$c || (int)$c['activo'] !== 1) throw new NegocioException('La carpeta no existe');
            if ((int)$c['es_sistema'] === 1) throw new NegocioException('Las carpetas del sistema no se pueden eliminar');

            $lote = bin2hex(random_bytes(16));
            $ids = $this->idsDeArbol($idCarpeta);
            $in = implode(',', array_fill(0, count($ids), '?'));

            $sc = $this->conexion->prepare(
                "UPDATE carpetas_documentos SET activo = 0, fecha_eliminacion = NOW(), eliminado_por = ?, lote_eliminacion = ?, editado_por = ?
                 WHERE activo = 1 AND id_carpeta IN ($in)"
            );
            $sc->execute(array_merge([(int)$idUsuario, $lote, (int)$idUsuario], $ids));
            $nCarpetas = $sc->rowCount();

            $sd = $this->conexion->prepare(
                "UPDATE documentos_paciente SET activo = 0, fecha_eliminacion = NOW(), eliminado_por = ?, lote_eliminacion = ?, editado_por = ?
                 WHERE activo = 1 AND id_carpeta IN ($in)"
            );
            $sd->execute(array_merge([(int)$idUsuario, $lote, (int)$idUsuario], $ids));
            $nDocs = $sd->rowCount();

            $this->registrarHistorialCarpeta($idCarpeta, 'eliminar', $idUsuario,
                ['nombre' => $c['nombre_carpeta'], 'carpetas' => $nCarpetas, 'documentos' => $nDocs, 'lote' => $lote]);
            return ['lote' => $lote, 'carpetas' => $nCarpetas, 'documentos' => $nDocs, 'nombre' => $c['nombre_carpeta'],
                    'id_paciente' => (int)$c['id_paciente']];
        });
    }

    // =====================================================
    // Documentos
    // =====================================================
    public function obtenerDocumentosCarpeta($idCarpeta) {
        $s = $this->conexion->prepare("SELECT * FROM vista_documentos_completa WHERE id_carpeta = :c ORDER BY fecha_subida DESC");
        $s->execute([':c' => (int)$idCarpeta]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Documentos que no estan dentro de ninguna carpeta (se muestran en "Inicio") */
    public function obtenerDocumentosRaiz($idPaciente) {
        $s = $this->conexion->prepare("SELECT * FROM vista_documentos_completa WHERE id_paciente = :p AND id_carpeta IS NULL ORDER BY fecha_subida DESC");
        $s->execute([':p' => (int)$idPaciente]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDocumentosPaciente($idPaciente, $limit = null) {
        $sql = "SELECT * FROM vista_documentos_completa WHERE id_paciente = :p ORDER BY fecha_subida DESC";
        if ($limit) $sql .= " LIMIT " . max(1, (int)$limit);
        $s = $this->conexion->prepare($sql);
        $s->execute([':p' => (int)$idPaciente]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDocumento($idDocumento) {
        $s = $this->conexion->prepare("SELECT * FROM vista_documentos_completa WHERE id_documento = :id");
        $s->execute([':id' => (int)$idDocumento]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    public function subirDocumento($datos) {
        return $this->transaccion(function () use ($datos) {
            $idPaciente = (int)$datos['id_paciente'];
            if (!$this->pacienteExiste($idPaciente)) throw new NegocioException('El paciente no existe');
            $idCarpeta = $this->idONull($datos['id_carpeta'] ?? null);
            if ($idCarpeta !== null) $this->exigirCarpetaActiva($idCarpeta, $idPaciente, 'La carpeta de destino');
            $titulo = $this->limpiarNombre($datos['titulo'], 200, 'El título');

            $s = $this->conexion->prepare(
                "INSERT INTO documentos_paciente (id_paciente, id_carpeta, titulo, archivo, tipo, extension, tamano_archivo, descripcion, subido_por)
                 VALUES (:p, :c, :t, :a, :tipo, :ext, :tam, :d, :u)"
            );
            $s->execute([
                ':p' => $idPaciente, ':c' => $idCarpeta, ':t' => $titulo, ':a' => $datos['archivo'],
                ':tipo' => $datos['tipo'], ':ext' => $datos['extension'], ':tam' => (int)$datos['tamano_archivo'],
                ':d' => ($datos['descripcion'] ?? '') !== '' ? $datos['descripcion'] : null, ':u' => (int)$datos['subido_por'],
            ]);
            $id = (int)$this->conexion->lastInsertId();
            $this->registrarHistorialDocumento($id, 'subir', $datos['subido_por'], ['titulo' => $titulo, 'carpeta' => $idCarpeta]);
            return $id;
        });
    }

    public function editarDocumento($idDocumento, $datos, $idUsuario) {
        return $this->transaccion(function () use ($idDocumento, $datos, $idUsuario) {
            $d = $this->documentoRaw($idDocumento);
            if (!$d || (int)$d['activo'] !== 1) throw new NegocioException('El documento no existe');
            $titulo = $this->limpiarNombre($datos['titulo'] ?? '', 200, 'El título');
            $descripcion = isset($datos['descripcion']) ? trim((string)$datos['descripcion']) : null;
            if ($descripcion !== null && preg_match('/[<>]/', $descripcion)) {
                throw new NegocioException('La descripción contiene caracteres no permitidos (< >)');
            }
            if ($descripcion !== null && mb_strlen($descripcion, 'UTF-8') > 1000) {
                throw new NegocioException('La descripción no puede superar los 1000 caracteres');
            }
            $s = $this->conexion->prepare("UPDATE documentos_paciente SET titulo = :t, descripcion = :d, editado_por = :u WHERE id_documento = :id");
            $s->execute([':t' => $titulo, ':d' => $descripcion === '' ? null : $descripcion, ':u' => (int)$idUsuario, ':id' => (int)$idDocumento]);
            $this->registrarHistorialDocumento($idDocumento, 'editar', $idUsuario, ['anterior' => $d['titulo'], 'nuevo' => $titulo]);
            return true;
        });
    }

    /** Devuelve true si lo movio, o 'sin_cambios' si ya estaba en el destino */
    public function moverDocumento($idDocumento, $idDestino, $idUsuario) {
        return $this->transaccion(function () use ($idDocumento, $idDestino, $idUsuario) {
            $d = $this->documentoRaw($idDocumento);
            if (!$d || (int)$d['activo'] !== 1) throw new NegocioException('El documento no existe');
            $idDestino = $this->idONull($idDestino);
            $actual = $d['id_carpeta'] === null ? null : (int)$d['id_carpeta'];

            if ($idDestino !== null) $this->exigirCarpetaActiva($idDestino, $d['id_paciente'], 'La carpeta de destino');
            if ($idDestino === $actual) return 'sin_cambios';

            $s = $this->conexion->prepare("UPDATE documentos_paciente SET id_carpeta = :c, editado_por = :u WHERE id_documento = :id");
            $s->execute([':c' => $idDestino, ':u' => (int)$idUsuario, ':id' => (int)$idDocumento]);
            $this->registrarHistorialDocumento($idDocumento, 'mover', $idUsuario, ['desde' => $actual, 'hacia' => $idDestino]);
            return true;
        });
    }

    public function copiarDocumento($idDocumento, $idDestino, $idUsuario, &$archivosCreados = null) {
        $propio = ($archivosCreados === null);
        if ($propio) $archivosCreados = [];
        try {
            return $this->transaccion(function () use ($idDocumento, $idDestino, $idUsuario, &$archivosCreados) {
                $d = $this->documentoRaw($idDocumento);
                if (!$d || (int)$d['activo'] !== 1) throw new NegocioException('El documento no existe');
                $idDestino = $this->idONull($idDestino);
                if ($idDestino !== null) $this->exigirCarpetaActiva($idDestino, $d['id_paciente'], 'La carpeta de destino');
                return $this->copiarDocumentoInterno($d, $idDestino, $idUsuario, $archivosCreados, true);
            });
        } catch (Throwable $e) {
            if ($propio) { foreach ($archivosCreados as $ruta) { if (is_file($ruta)) @unlink($ruta); } }
            throw $e;
        }
    }

    private function copiarDocumentoInterno(array $d, $idCarpetaDestino, $idUsuario, array &$archivosCreados, $conSufijo) {
        $origen = $this->rutaFisicaSegura($d['archivo']);
        if (!$origen) throw new NegocioException('No se encontró el archivo de "' . $d['titulo'] . '"');

        $ext = strtolower(pathinfo($origen, PATHINFO_EXTENSION));
        $nombre = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
        $destino = dirname($origen) . '/' . $nombre;
        if (!copy($origen, $destino)) throw new NegocioException('No se pudo duplicar el archivo');
        $archivosCreados[] = $destino;

        $titulo = $conSufijo ? mb_substr($d['titulo'], 0, 190, 'UTF-8') . ' (copia)' : $d['titulo'];
        $s = $this->conexion->prepare(
            "INSERT INTO documentos_paciente (id_paciente, id_carpeta, titulo, archivo, tipo, extension, tamano_archivo, descripcion, subido_por)
             VALUES (:p, :c, :t, :a, :tipo, :ext, :tam, :d, :u)"
        );
        $s->execute([
            ':p' => (int)$d['id_paciente'], ':c' => $idCarpetaDestino, ':t' => $titulo,
            ':a' => 'ARCHIVOS/documentos_pacientes/' . (int)$d['id_paciente'] . '/' . $nombre,
            ':tipo' => $d['tipo'], ':ext' => $d['extension'], ':tam' => $d['tamano_archivo'],
            ':d' => $d['descripcion'], ':u' => (int)$idUsuario,
        ]);
        $id = (int)$this->conexion->lastInsertId();
        $this->registrarHistorialDocumento($id, 'subir', $idUsuario, ['titulo' => $titulo, 'copiado_de' => (int)$d['id_documento']]);
        return $id;
    }

    /** Eliminar = enviar a la papelera */
    public function eliminarDocumento($idDocumento, $idUsuario) {
        return $this->transaccion(function () use ($idDocumento, $idUsuario) {
            $d = $this->documentoRaw($idDocumento);
            if (!$d || (int)$d['activo'] !== 1) throw new NegocioException('El documento no existe');
            $lote = bin2hex(random_bytes(16));
            $s = $this->conexion->prepare(
                "UPDATE documentos_paciente SET activo = 0, fecha_eliminacion = NOW(), eliminado_por = :u, lote_eliminacion = :l, editado_por = :u2
                 WHERE id_documento = :id AND activo = 1"
            );
            $s->execute([':u' => (int)$idUsuario, ':l' => $lote, ':u2' => (int)$idUsuario, ':id' => (int)$idDocumento]);
            $this->registrarHistorialDocumento($idDocumento, 'eliminar', $idUsuario, ['titulo' => $d['titulo'], 'lote' => $lote]);
            return ['lote' => $lote, 'carpetas' => 0, 'documentos' => 1, 'nombre' => $d['titulo'], 'id_paciente' => (int)$d['id_paciente']];
        });
    }

    // =====================================================
    // Archivos fisicos
    // =====================================================

    /** Ruta absoluta del archivo SOLO si esta dentro de ARCHIVOS/documentos_pacientes */
    public function rutaFisicaSegura($archivoRelativo) {
        $base = realpath($this->baseArchivos);
        if ($base === false) return null;
        $ruta = realpath(dirname(__DIR__) . '/' . ltrim((string)$archivoRelativo, '/'));
        if ($ruta === false || !is_file($ruta)) return null;
        return (strpos($ruta, $base . DIRECTORY_SEPARATOR) === 0) ? $ruta : null;
    }

    // =====================================================
    // Papelera
    // =====================================================

    /** Elementos eliminados de un paciente: cada fila es lo que se elimino directamente (no su contenido) */
    public function listarPapelera($idPaciente, $dias) {
        $dias = max(1, (int)$dias);
        $res = [];

        $sc = $this->conexion->prepare(
            "SELECT c.id_carpeta AS id, c.nombre_carpeta AS nombre, c.fecha_eliminacion, c.lote_eliminacion AS lote,
                    CONCAT(u.nombre, ' ', u.apellidos) AS eliminado_por_nombre,
                    IFNULL(pa.nombre_carpeta, 'Inicio') AS ubicacion,
                    (SELECT COUNT(*) FROM carpetas_documentos x WHERE x.lote_eliminacion = c.lote_eliminacion AND x.activo = 0)
                  + (SELECT COUNT(*) FROM documentos_paciente y WHERE y.lote_eliminacion = c.lote_eliminacion COLLATE utf8mb4_general_ci AND y.activo = 0) AS elementos,
                    GREATEST(0, :d1 - TIMESTAMPDIFF(DAY, c.fecha_eliminacion, NOW())) AS dias_restantes
             FROM carpetas_documentos c
             LEFT JOIN usuarios u ON u.id_usuario = c.eliminado_por
             LEFT JOIN carpetas_documentos pa ON pa.id_carpeta = c.id_carpeta_padre
             WHERE c.activo = 0 AND c.id_paciente = :p AND c.lote_eliminacion IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM carpetas_documentos q WHERE q.id_carpeta = c.id_carpeta_padre AND q.lote_eliminacion = c.lote_eliminacion)"
        );
        $sc->execute([':d1' => $dias, ':p' => (int)$idPaciente]);
        foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $f) { $f['tipo'] = 'carpeta'; $res[] = $f; }

        $sd = $this->conexion->prepare(
            "SELECT d.id_documento AS id, d.titulo AS nombre, d.fecha_eliminacion, d.lote_eliminacion AS lote, d.extension,
                    CONCAT(u.nombre, ' ', u.apellidos) AS eliminado_por_nombre,
                    IFNULL(pa.nombre_carpeta, 'Inicio') AS ubicacion,
                    1 AS elementos,
                    GREATEST(0, :d1 - TIMESTAMPDIFF(DAY, d.fecha_eliminacion, NOW())) AS dias_restantes
             FROM documentos_paciente d
             LEFT JOIN usuarios u ON u.id_usuario = d.eliminado_por
             LEFT JOIN carpetas_documentos pa ON pa.id_carpeta = d.id_carpeta
             WHERE d.activo = 0 AND d.id_paciente = :p AND d.lote_eliminacion IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM carpetas_documentos q WHERE q.id_carpeta = d.id_carpeta AND q.lote_eliminacion COLLATE utf8mb4_general_ci = d.lote_eliminacion)"
        );
        $sd->execute([':d1' => $dias, ':p' => (int)$idPaciente]);
        foreach ($sd->fetchAll(PDO::FETCH_ASSOC) as $f) { $f['tipo'] = 'documento'; $res[] = $f; }

        usort($res, function ($a, $b) { return strcmp($b['fecha_eliminacion'], $a['fecha_eliminacion']); });
        foreach ($res as &$r) {
            $r['id'] = (int)$r['id'];
            $r['elementos'] = (int)$r['elementos'];
            $r['dias_restantes'] = (int)$r['dias_restantes'];
        }
        return $res;
    }

    /** Paciente al que pertenece un lote de la papelera (null si no existe) */
    public function pacienteDeLote($lote) {
        $s = $this->conexion->prepare(
            "SELECT id_paciente FROM carpetas_documentos WHERE lote_eliminacion = :l AND activo = 0
             UNION SELECT id_paciente FROM documentos_paciente WHERE lote_eliminacion = :l2 AND activo = 0 LIMIT 1"
        );
        $s->execute([':l' => $lote, ':l2' => $lote]);
        $p = $s->fetchColumn();
        return $p === false ? null : (int)$p;
    }

    /**
     * Restaura todo lo que se elimino junto. Si el lugar original ya no esta disponible
     * (su carpeta tambien esta eliminada), vuelve a "Inicio". Si el nombre ya esta en uso, lo renombra.
     */
    public function restaurarLote($lote, $idUsuario) {
        if (!preg_match('/^[0-9a-f]{32}$/', (string)$lote)) throw new NegocioException('Elemento de papelera no válido');

        return $this->transaccion(function () use ($lote, $idUsuario) {
            $sc = $this->conexion->prepare("SELECT * FROM carpetas_documentos WHERE lote_eliminacion = :l AND activo = 0");
            $sc->execute([':l' => $lote]);
            $carpetas = $sc->fetchAll(PDO::FETCH_ASSOC);
            $sd = $this->conexion->prepare("SELECT * FROM documentos_paciente WHERE lote_eliminacion = :l AND activo = 0");
            $sd->execute([':l' => $lote]);
            $documentos = $sd->fetchAll(PDO::FETCH_ASSOC);
            if (empty($carpetas) && empty($documentos)) throw new NegocioException('Ese elemento ya no está en la papelera');

            $idPaciente = !empty($carpetas) ? (int)$carpetas[0]['id_paciente'] : (int)$documentos[0]['id_paciente'];
            $idsLote = array_map(function ($c) { return (int)$c['id_carpeta']; }, $carpetas);
            $aInicio = false;

            // Carpeta(s) raiz del lote: vuelve a su lugar original si sigue disponible
            foreach ($carpetas as $c) {
                $padre = $c['id_carpeta_padre'] === null ? null : (int)$c['id_carpeta_padre'];
                if ($padre !== null && in_array($padre, $idsLote, true)) continue;   // va dentro del lote: no cambia
                $padreActivo = ($padre === null);
                if ($padre !== null) {
                    $p = $this->carpetaRaw($padre);
                    $padreActivo = $p && (int)$p['activo'] === 1;
                }
                $destino = $padreActivo ? $padre : null;
                if (!$padreActivo) $aInicio = true;
                $nombre = $this->nombreLibre($c['id_paciente'], $destino, $c['nombre_carpeta'], 'restaurada');
                $u = $this->conexion->prepare("UPDATE carpetas_documentos SET id_carpeta_padre = :p, nombre_carpeta = :n WHERE id_carpeta = :id");
                $u->execute([':p' => $destino, ':n' => $nombre, ':id' => (int)$c['id_carpeta']]);
            }

            // Documento(s) raiz del lote
            foreach ($documentos as $d) {
                $carp = $d['id_carpeta'] === null ? null : (int)$d['id_carpeta'];
                if ($carp !== null && in_array($carp, $idsLote, true)) continue;
                $carpetaActiva = ($carp === null);
                if ($carp !== null) {
                    $p = $this->carpetaRaw($carp);
                    $carpetaActiva = $p && (int)$p['activo'] === 1;
                }
                if (!$carpetaActiva) {
                    $this->conexion->prepare("UPDATE documentos_paciente SET id_carpeta = NULL WHERE id_documento = :id")
                                   ->execute([':id' => (int)$d['id_documento']]);
                    $aInicio = true;
                }
            }

            $uc = $this->conexion->prepare(
                "UPDATE carpetas_documentos SET activo = 1, fecha_eliminacion = NULL, eliminado_por = NULL, lote_eliminacion = NULL, editado_por = :u
                 WHERE lote_eliminacion = :l AND activo = 0"
            );
            $uc->execute([':u' => (int)$idUsuario, ':l' => $lote]);
            $ud = $this->conexion->prepare(
                "UPDATE documentos_paciente SET activo = 1, fecha_eliminacion = NULL, eliminado_por = NULL, lote_eliminacion = NULL, editado_por = :u
                 WHERE lote_eliminacion = :l AND activo = 0"
            );
            $ud->execute([':u' => (int)$idUsuario, ':l' => $lote]);

            foreach ($carpetas as $c)   $this->registrarHistorialCarpeta($c['id_carpeta'], 'editar', $idUsuario, ['restaurado' => true]);
            foreach ($documentos as $d) $this->registrarHistorialDocumento($d['id_documento'], 'editar', $idUsuario, ['restaurado' => true]);

            return ['carpetas' => count($carpetas), 'documentos' => count($documentos), 'a_inicio' => $aInicio, 'id_paciente' => $idPaciente];
        });
    }

    /**
     * Borrado definitivo de lo que lleva mas de $dias dias en la papelera (archivo fisico incluido).
     * Quien llama debe dejar constancia en la bitacora.
     */
    public function purgarPapelera($dias) {
        $dias = max(1, (int)$dias);
        $archivos = [];
        $nDocs = 0;
        $nCarpetas = 0;

        $this->transaccion(function () use ($dias, &$archivos, &$nDocs, &$nCarpetas) {
            $sd = $this->conexion->prepare(
                "SELECT id_documento, archivo FROM documentos_paciente
                 WHERE activo = 0 AND fecha_eliminacion IS NOT NULL AND fecha_eliminacion < DATE_SUB(NOW(), INTERVAL :d DAY)"
            );
            $sd->execute([':d' => $dias]);
            foreach ($sd->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $archivos[] = $d['archivo'];
                $this->conexion->prepare("DELETE FROM documentos_paciente WHERE id_documento = :id AND activo = 0")
                               ->execute([':id' => (int)$d['id_documento']]);
                $nDocs++;
            }

            $sc = $this->conexion->prepare(
                "SELECT id_carpeta FROM carpetas_documentos
                 WHERE activo = 0 AND fecha_eliminacion IS NOT NULL AND fecha_eliminacion < DATE_SUB(NOW(), INTERVAL :d DAY)"
            );
            $sc->execute([':d' => $dias]);
            $ids = array_map('intval', $sc->fetchAll(PDO::FETCH_COLUMN));
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                // La llave foranea borra en cascada las subcarpetas: si alguna sigue activa, se pasa a Inicio antes
                $this->conexion->prepare("UPDATE carpetas_documentos SET id_carpeta_padre = NULL WHERE activo = 1 AND id_carpeta_padre IN ($in)")->execute($ids);
                $del = $this->conexion->prepare("DELETE FROM carpetas_documentos WHERE activo = 0 AND id_carpeta IN ($in)");
                $del->execute($ids);
                $nCarpetas = $del->rowCount();
            }
        });

        // Los archivos fisicos se borran despues de confirmar la base de datos
        $borrados = 0;
        foreach ($archivos as $rel) {
            $ruta = $this->rutaFisicaSegura($rel);
            if ($ruta && @unlink($ruta)) $borrados++;
            elseif ($ruta) error_log("purgarPapelera: no se pudo borrar $rel");
        }
        return ['documentos' => $nDocs, 'carpetas' => $nCarpetas, 'archivos_borrados' => $borrados];
    }

    /** Ejecuta la purga como maximo una vez por hora (no hace falta programar nada en el servidor) */
    public function purgarSiCorresponde($dias) {
        try {
            $s = $this->conexion->query("SELECT valor FROM configuracion_sistema WHERE clave = 'papelera_ultima_limpieza'");
            $ultima = $s ? $s->fetchColumn() : false;
            $ahora = $this->conexion->query("SELECT NOW()")->fetchColumn();
            if ($ultima && (strtotime($ahora) - strtotime($ultima)) < 3600) return null;

            $this->conexion->prepare(
                "INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES ('papelera_ultima_limpieza', :v, 'Uso interno: ultima vez que se vacio la papelera')
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
            )->execute([':v' => $ahora]);

            return $this->purgarPapelera($dias);
        } catch (Throwable $e) {
            error_log('purgarSiCorresponde: ' . $e->getMessage());
            return null;
        }
    }

    // =====================================================
    // Estadisticas e historial
    // =====================================================
    public function obtenerEstadisticas($idPaciente) {
        $s = $this->conexion->prepare(
            "SELECT COUNT(*) AS total_documentos, COUNT(DISTINCT id_carpeta) AS total_carpetas_usadas,
                    SUM(tamano_archivo) AS tamano_total, MAX(fecha_subida) AS ultimo_documento
             FROM documentos_paciente WHERE id_paciente = :p AND activo = 1"
        );
        $s->execute([':p' => (int)$idPaciente]);
        return $s->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarHistorialCarpeta($idCarpeta, $accion, $idUsuario, $detalles) {
        try {
            $s = $this->conexion->prepare("INSERT INTO historial_carpetas (id_carpeta, accion, id_usuario, detalles) VALUES (:c, :a, :u, :d)");
            return $s->execute([':c' => (int)$idCarpeta, ':a' => $accion, ':u' => (int)$idUsuario, ':d' => json_encode($detalles, JSON_UNESCAPED_UNICODE)]);
        } catch (PDOException $e) {
            error_log("Error al registrar historial de carpeta: " . $e->getMessage());
            return false;
        }
    }

    public function registrarHistorialDocumento($idDocumento, $accion, $idUsuario, $detalles) {
        try {
            $s = $this->conexion->prepare("INSERT INTO historial_documentos (id_documento, accion, id_usuario, detalles) VALUES (:d, :a, :u, :det)");
            return $s->execute([':d' => (int)$idDocumento, ':a' => $accion, ':u' => (int)$idUsuario, ':det' => json_encode($detalles, JSON_UNESCAPED_UNICODE)]);
        } catch (PDOException $e) {
            error_log("Error al registrar historial de documento: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerHistorialCarpeta($idCarpeta, $limit = 10) {
        $s = $this->conexion->prepare(
            "SELECT h.*, CONCAT(u.nombre, ' ', u.apellidos) AS usuario_nombre FROM historial_carpetas h
             INNER JOIN usuarios u ON h.id_usuario = u.id_usuario WHERE h.id_carpeta = :id ORDER BY h.fecha_accion DESC LIMIT :l"
        );
        $s->bindValue(':id', (int)$idCarpeta, PDO::PARAM_INT);
        $s->bindValue(':l', max(1, (int)$limit), PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerHistorialDocumento($idDocumento, $limit = 10) {
        $s = $this->conexion->prepare(
            "SELECT h.*, CONCAT(u.nombre, ' ', u.apellidos) AS usuario_nombre FROM historial_documentos h
             INNER JOIN usuarios u ON h.id_usuario = u.id_usuario WHERE h.id_documento = :id ORDER BY h.fecha_accion DESC LIMIT :l"
        );
        $s->bindValue(':id', (int)$idDocumento, PDO::PARAM_INT);
        $s->bindValue(':l', max(1, (int)$limit), PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
