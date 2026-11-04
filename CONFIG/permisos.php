<?php
/**
 * MOTOR DE PERMISOS
 *
 * Un permiso es:  rol + accion + alcance
 *   alcance = 'ninguno'   -> no puede
 *             'asignados' -> solo en pacientes donde es el odontologo responsable
 *             'todos'     -> en cualquier paciente
 *
 * - permisos_rol (existente) decide que MODULOS ve cada rol.
 * - permisos_accion decide que PUEDE HACER dentro de cada modulo (este motor), segun el ROL.
 * - permisos_usuario_accion guarda EXCEPCIONES por usuario: si existe una para la accion, manda sobre el rol
 *   (permite dar o quitar una accion a una persona concreta). Sin excepcion, vale lo del rol.
 * - El servidor siempre decide: la pantalla solo oculta botones con la misma informacion.
 * - Si algo falla al leer los permisos, se niega (nunca se concede por error).
 *
 * Uso en un controlador:
 *   require_once '../CONFIG/permisos.php';      // ya exige sesion iniciada
 *   Permisos::requerir('documentos.mover', $idPaciente);   // responde 403 si no puede
 *   if (Permisos::puede('documentos.eliminar', $idPaciente)) { ... }
 */
require_once __DIR__ . '/requerir_sesion_api.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/bitacora.php';

class Permisos {

    /** Rol Administrador: unico que puede cambiar permisos (no configurable, evita quedarse sin acceso) */
    const ROL_ADMINISTRADOR = 1;

    private static $cache = [];
    private static $pdo = null;

    // =====================================================
    // Catalogo de acciones (modulo -> acciones)
    // por_paciente = true  -> admite el alcance "Solo asignados"
    // por_paciente = false -> solo "No" o "Todos"
    // Para sumar un modulo nuevo basta declarar aqui sus acciones.
    // =====================================================
    public static function catalogo() {
        return [
            'historia_clinica' => [
                'titulo' => 'Historia clínica',
                'acciones' => [
                    'historia_clinica.editar' => ['titulo' => 'Editar la historia clínica', 'por_paciente' => true],
                ],
            ],
            'documentos' => [
                'titulo' => 'Documentos del paciente',
                'acciones' => [
                    'documentos.subir'         => ['titulo' => 'Subir documentos',        'por_paciente' => true],
                    'documentos.crear_carpeta' => ['titulo' => 'Crear carpetas',          'por_paciente' => true],
                    'documentos.descargar'     => ['titulo' => 'Descargar documentos',    'por_paciente' => true],
                    'documentos.renombrar'     => ['titulo' => 'Renombrar',               'por_paciente' => true],
                    'documentos.mover'         => ['titulo' => 'Mover o cortar y pegar',  'por_paciente' => true],
                    'documentos.eliminar'      => ['titulo' => 'Eliminar (a la papelera)','por_paciente' => true],
                    'documentos.papelera'      => ['titulo' => 'Ver y restaurar la papelera', 'por_paciente' => false],
                ],
            ],
            'pacientes' => [
                'titulo' => 'Pacientes',
                'acciones' => [
                    'pacientes.asignar_responsable' => ['titulo' => 'Asignar odontólogo responsable',   'por_paciente' => false],
                    'pacientes.restablecer_portal'  => ['titulo' => 'Restablecer acceso al portal',     'por_paciente' => true],
                    'pacientes.importar'            => ['titulo' => 'Importar pacientes desde Excel',   'por_paciente' => false],
                ],
            ],
            'cobros' => [
                'titulo' => 'Cobros',
                'acciones' => [
                    'cobros.registrar'             => ['titulo' => 'Registrar pagos y usar el saldo a favor',        'por_paciente' => false],
                    'cobros.anular'                => ['titulo' => 'Anular pagos',                                    'por_paciente' => false],
                    'cobros.registrar_retroactivo' => ['titulo' => 'Registrar pagos con fecha pasada',                'por_paciente' => false],
                ],
            ],
        ];
    }

    /** Alcances validos para una accion, o null si la accion no existe en el catalogo */
    public static function alcancesPermitidos($accion) {
        foreach (self::catalogo() as $modulo) {
            if (isset($modulo['acciones'][$accion])) {
                return $modulo['acciones'][$accion]['por_paciente']
                    ? ['ninguno', 'asignados', 'todos']
                    : ['ninguno', 'todos'];
            }
        }
        return null;
    }

    private static function pdo() {
        if (self::$pdo === null) {
            self::$pdo = (new Conexion())->getConexion();
        }
        return self::$pdo;
    }

    public static function limpiarCache() {
        self::$cache = [];
    }

    // =====================================================
    // Usuario de la sesion
    // =====================================================
    private static function idRol() {
        return (int)($_SESSION['usuario']['id_rol'] ?? 0);
    }

    private static function idUsuario() {
        return (int)($_SESSION['usuario']['id_usuario'] ?? 0);
    }

    // =====================================================
    // Lectura de permisos
    // =====================================================

    /** Mapa accion => alcance de un rol (con cache por peticion) */
    public static function delRol($idRol) {
        $idRol = (int)$idRol;
        if (!isset(self::$cache[$idRol])) {
            try {
                $stmt = self::pdo()->prepare("SELECT accion, alcance FROM permisos_accion WHERE id_rol = :r");
                $stmt->execute([':r' => $idRol]);
                self::$cache[$idRol] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Throwable $e) {
                error_log('Permisos::delRol: ' . $e->getMessage());
                return [];   // si falla, no se cachea y se niega todo
            }
        }
        return self::$cache[$idRol];
    }

    /**
     * Excepciones de UN usuario: mapa accion => alcance. Con cache por peticion.
     * Si la tabla aun no existe (paso F3 sin ejecutar) no hay excepciones y vale el rol.
     * Si falla por otro motivo devuelve null y el llamador NIEGA (nunca se concede por error).
     */
    public static function delUsuario($idUsuario) {
        $idUsuario = (int)$idUsuario;
        $k = 'u' . $idUsuario;
        if (!isset(self::$cache[$k])) {
            try {
                $stmt = self::pdo()->prepare("SELECT accion, alcance FROM permisos_usuario_accion WHERE id_usuario = :u");
                $stmt->execute([':u' => $idUsuario]);
                self::$cache[$k] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Throwable $e) {
                if ($e instanceof PDOException && $e->getCode() === '42S02') return [];     // tabla inexistente: sin excepciones
                error_log('Permisos::delUsuario: ' . $e->getMessage());
                return null;
            }
        }
        return self::$cache[$k];
    }

    /**
     * Alcance de una accion. Sin $idRol: el del USUARIO de la sesion (su excepcion si la tiene; si no, el de su rol).
     * Con $idRol explicito: solo el del rol (lo usa la pantalla de roles).
     */
    public static function alcance($accion, $idRol = null) {
        if ($idRol === null) {
            $excepciones = self::delUsuario(self::idUsuario());
            if ($excepciones === null) return 'ninguno';
            if (isset($excepciones[$accion])) return $excepciones[$accion];
            $idRol = self::idRol();
        }
        $mapa = self::delRol((int)$idRol);
        return $mapa[$accion] ?? 'ninguno';
    }

    /** ¿El usuario es el odontologo responsable de ese paciente? */
    public static function esResponsable($idPaciente, $idUsuario = null) {
        $idUsuario = $idUsuario === null ? self::idUsuario() : (int)$idUsuario;
        $idPaciente = (int)$idPaciente;
        if ($idPaciente <= 0 || $idUsuario <= 0) return false;
        try {
            $stmt = self::pdo()->prepare(
                "SELECT 1 FROM pacientes WHERE id_paciente = :p AND id_doctor_responsable = :u LIMIT 1"
            );
            $stmt->execute([':p' => $idPaciente, ':u' => $idUsuario]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Permisos::esResponsable: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ¿Puede el usuario de la sesion realizar la accion?
     * Para alcance "asignados" hace falta indicar el paciente.
     */
    public static function puede($accion, $idPaciente = null) {
        switch (self::alcance($accion)) {
            case 'todos':
                return true;
            case 'asignados':
                return $idPaciente !== null && self::esResponsable($idPaciente);
            default:
                return false;
        }
    }

    /** Corta la peticion con 403 si no puede (usa la misma respuesta JSON de la guardia de sesion) */
    public static function requerir($accion, $idPaciente = null) {
        if (!self::puede($accion, $idPaciente)) {
            responderErrorApi(403, 'No tienes permiso para realizar esta acción.');
        }
    }

    /** Varias acciones a la vez sobre un paciente: ['accion' => true|false] (para decidir que botones mostrar) */
    public static function enPaciente(array $acciones, $idPaciente) {
        $resultado = [];
        foreach ($acciones as $accion) {
            $resultado[$accion] = self::puede($accion, $idPaciente);
        }
        return $resultado;
    }

    /** Lo que el usuario puede hacer con los documentos de un paciente (para mostrar u ocultar botones) */
    public static function documentosEnPaciente($idPaciente) {
        $p = self::enPaciente(['documentos.subir', 'documentos.crear_carpeta', 'documentos.descargar',
                               'documentos.renombrar', 'documentos.mover', 'documentos.eliminar'], $idPaciente);
        return [
            'subir'         => $p['documentos.subir'],
            'crear_carpeta' => $p['documentos.crear_carpeta'],
            'descargar'     => $p['documentos.descargar'],
            'renombrar'     => $p['documentos.renombrar'],
            'mover'         => $p['documentos.mover'],
            'eliminar'      => $p['documentos.eliminar'],
            'papelera'      => self::puede('documentos.papelera'),
        ];
    }

    /** Alcance por accion del usuario actual (para que la pantalla sepa que ofrecer) */
    public static function mapaDelUsuario() {
        $mapa = [];
        foreach (self::catalogo() as $modulo) {
            foreach ($modulo['acciones'] as $accion => $def) {
                $mapa[$accion] = self::alcance($accion);
            }
        }
        return $mapa;
    }

    // =====================================================
    // Administracion de permisos (solo Administrador)
    // =====================================================
    public static function puedeGestionarPermisos() {
        return self::idRol() === self::ROL_ADMINISTRADOR;
    }

    /** Datos para la pantalla de Configuracion: roles, catalogo y valores actuales */
    public static function matriz() {
        if (!self::puedeGestionarPermisos()) {
            return ['success' => false, 'mensaje' => 'Solo el administrador puede ver los permisos'];
        }
        try {
            $roles = self::pdo()->query("SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol")->fetchAll(PDO::FETCH_ASSOC);
            $valores = [];
            foreach ($roles as $r) {
                $valores[$r['id_rol']] = self::delRol($r['id_rol']);
            }
            return ['success' => true, 'roles' => $roles, 'catalogo' => self::catalogo(), 'valores' => $valores];
        } catch (Throwable $e) {
            error_log('Permisos::matriz: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudieron leer los permisos'];
        }
    }

    /**
     * Guarda los permisos de un rol. $cambios = ['accion' => 'alcance', ...]
     */
    public static function guardarDelRol($idRol, array $cambios) {
        if (empty($cambios)) return ['success' => false, 'mensaje' => 'No hay cambios para guardar'];
        return self::guardarVarios([(int)$idRol => $cambios]);
    }

    /**
     * Guarda cambios de varios roles a la vez: $porRol = [ idRol => ['accion' => 'alcance', ...], ... ]
     * Todo o nada: si una accion, un alcance o un rol no es valido, no se guarda nada.
     * Cada rol modificado queda en la bitacora (antes y despues).
     */
    public static function guardarVarios(array $porRol) {
        if (!self::puedeGestionarPermisos()) {
            return ['success' => false, 'mensaje' => 'Solo el administrador puede cambiar permisos'];
        }
        if (empty($porRol)) {
            return ['success' => false, 'mensaje' => 'No hay cambios para guardar'];
        }

        try {
            $pdo = self::pdo();
            $rolesValidos = array_map('intval', $pdo->query("SELECT id_rol FROM roles")->fetchAll(PDO::FETCH_COLUMN));

            // 1) Validar TODO antes de escribir nada
            foreach ($porRol as $idRol => $cambios) {
                if (!in_array((int)$idRol, $rolesValidos, true)) {
                    return ['success' => false, 'mensaje' => 'El rol no existe'];
                }
                if (!is_array($cambios) || empty($cambios)) {
                    return ['success' => false, 'mensaje' => 'No hay cambios para guardar'];
                }
                foreach ($cambios as $accion => $alcance) {
                    $validos = self::alcancesPermitidos($accion);
                    if ($validos === null) {
                        return ['success' => false, 'mensaje' => 'Acción desconocida: ' . htmlspecialchars((string)$accion, ENT_QUOTES, 'UTF-8')];
                    }
                    if (!in_array($alcance, $validos, true)) {
                        return ['success' => false, 'mensaje' => 'El alcance indicado no es válido para ' . $accion];
                    }
                }
            }

            // 2) Aplicar en una sola transaccion
            $pdo->beginTransaction();
            $ups = $pdo->prepare(
                "INSERT INTO permisos_accion (id_rol, accion, alcance) VALUES (:r, :a, :al)
                 ON DUPLICATE KEY UPDATE alcance = VALUES(alcance)"
            );
            $bitacora = [];
            $total = 0;
            foreach ($porRol as $idRol => $cambios) {
                $idRol = (int)$idRol;
                $antes = self::delRol($idRol);
                $diff = [];
                foreach ($cambios as $accion => $alcance) {
                    $anterior = $antes[$accion] ?? 'ninguno';
                    if ($anterior === $alcance && isset($antes[$accion])) continue;
                    $ups->execute([':r' => $idRol, ':a' => $accion, ':al' => $alcance]);
                    $diff[] = ['accion' => $accion, 'antes' => $anterior, 'despues' => $alcance];
                }
                if (!empty($diff)) { $bitacora[$idRol] = $diff; $total += count($diff); }
            }
            $pdo->commit();
            self::limpiarCache();

            foreach ($bitacora as $idRol => $diff) {
                registrarBitacora('permisos.modificar', 'rol', $idRol, null, ['cambios' => $diff]);
            }
            return ['success' => true, 'cambios' => $total];
        } catch (Throwable $e) {
            if (self::$pdo && self::$pdo->inTransaction()) self::$pdo->rollBack();
            error_log('Permisos::guardarVarios: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudieron guardar los permisos'];
        }
    }

    // =====================================================
    // Excepciones por usuario (solo Administrador)
    // =====================================================

    /** Usuarios para elegir en la pantalla, con cuantas excepciones tiene cada uno */
    public static function usuariosParaPermisos() {
        if (!self::puedeGestionarPermisos()) return ['success' => false, 'mensaje' => 'Solo el administrador puede ver los permisos'];
        try {
            $filas = self::pdo()->query(
                "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellidos) AS nombre, u.id_rol, r.nombre_rol, u.id_estado,
                        (SELECT COUNT(*) FROM permisos_usuario_accion e WHERE e.id_usuario = u.id_usuario) AS excepciones
                 FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
                 ORDER BY (u.id_estado = 1) DESC, r.id_rol, u.nombre, u.apellidos"
            )->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'usuarios' => $filas];
        } catch (Throwable $e) {
            error_log('Permisos::usuariosParaPermisos: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo cargar la lista de usuarios. ¿Se ejecutó el paso F3?'];
        }
    }

    /** Valores del rol, excepciones y resultado final de UN usuario, para la matriz de la pantalla */
    public static function matrizUsuario($idUsuario) {
        if (!self::puedeGestionarPermisos()) return ['success' => false, 'mensaje' => 'Solo el administrador puede ver los permisos'];
        try {
            $s = self::pdo()->prepare("SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellidos) AS nombre, u.id_rol, r.nombre_rol, u.id_estado
                                       FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = :u");
            $s->execute([':u' => (int)$idUsuario]);
            $u = $s->fetch(PDO::FETCH_ASSOC);
            if (!$u) return ['success' => false, 'mensaje' => 'El usuario no existe'];
            $rol = self::delRol($u['id_rol']);
            $exc = self::delUsuario($u['id_usuario']);
            if ($exc === null) return ['success' => false, 'mensaje' => 'No se pudieron leer las excepciones'];
            $efectivo = [];
            foreach (self::catalogo() as $modulo) {
                foreach ($modulo['acciones'] as $accion => $def) {
                    $efectivo[$accion] = $exc[$accion] ?? ($rol[$accion] ?? 'ninguno');
                }
            }
            return ['success' => true, 'usuario' => $u, 'catalogo' => self::catalogo(), 'rol' => $rol ?: (object)[], 'excepciones' => $exc ?: (object)[], 'efectivo' => $efectivo];
        } catch (Throwable $e) {
            error_log('Permisos::matrizUsuario: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudieron leer los permisos del usuario'];
        }
    }

    /**
     * Guarda excepciones de un usuario. $cambios = ['accion' => 'ninguno'|'asignados'|'todos'|'heredar']
     * 'heredar' = quitar la excepcion (vuelve a valer el rol). Todo o nada; queda en la bitacora (antes y despues).
     */
    public static function guardarUsuario($idUsuario, array $cambios) {
        if (!self::puedeGestionarPermisos()) return ['success' => false, 'mensaje' => 'Solo el administrador puede cambiar permisos'];
        if (empty($cambios)) return ['success' => false, 'mensaje' => 'No hay cambios para guardar'];
        try {
            $pdo = self::pdo();
            $s = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :u");
            $s->execute([':u' => (int)$idUsuario]);
            $idRol = $s->fetchColumn();
            if ($idRol === false) return ['success' => false, 'mensaje' => 'El usuario no existe'];

            // 1) Validar todo antes de escribir
            foreach ($cambios as $accion => $alcance) {
                $validos = self::alcancesPermitidos($accion);
                if ($validos === null) return ['success' => false, 'mensaje' => 'Acción desconocida: ' . htmlspecialchars((string)$accion, ENT_QUOTES, 'UTF-8')];
                if ($alcance !== 'heredar' && !in_array($alcance, $validos, true)) {
                    return ['success' => false, 'mensaje' => 'El alcance indicado no es válido para ' . $accion];
                }
            }

            // 2) Aplicar en una transaccion
            $antesExc = self::delUsuario($idUsuario) ?? [];
            $rol = self::delRol((int)$idRol);
            $pdo->beginTransaction();
            $ups = $pdo->prepare("INSERT INTO permisos_usuario_accion (id_usuario, accion, alcance, otorgado_por) VALUES (:u, :a, :al, :o)
                                  ON DUPLICATE KEY UPDATE alcance = VALUES(alcance), otorgado_por = VALUES(otorgado_por), fecha = CURRENT_TIMESTAMP");
            $del = $pdo->prepare("DELETE FROM permisos_usuario_accion WHERE id_usuario = :u AND accion = :a");
            $diff = [];
            foreach ($cambios as $accion => $alcance) {
                $antes = $antesExc[$accion] ?? ($rol[$accion] ?? 'ninguno');        // lo que valia antes (excepcion o rol)
                if ($alcance === 'heredar') {
                    if (!isset($antesExc[$accion])) continue;                        // no habia excepcion: nada que quitar
                    $del->execute([':u' => (int)$idUsuario, ':a' => $accion]);
                    $despues = $rol[$accion] ?? 'ninguno';
                } else {
                    if (isset($antesExc[$accion]) && $antesExc[$accion] === $alcance) continue;
                    $ups->execute([':u' => (int)$idUsuario, ':a' => $accion, ':al' => $alcance, ':o' => self::idUsuario() ?: null]);
                    $despues = $alcance;
                }
                $diff[] = ['accion' => $accion, 'antes' => $antes, 'despues' => $despues, 'excepcion' => $alcance];
            }
            $pdo->commit();
            self::limpiarCache();
            if (!empty($diff)) registrarBitacora('permisos.usuario', 'usuario', (int)$idUsuario, null, ['cambios' => $diff]);
            return ['success' => true, 'cambios' => count($diff)];
        } catch (Throwable $e) {
            if (self::$pdo && self::$pdo->inTransaction()) self::$pdo->rollBack();
            error_log('Permisos::guardarUsuario: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudieron guardar los permisos del usuario'];
        }
    }

    /** Todas las excepciones vigentes, para auditar de un vistazo quien tiene permisos especiales */
    public static function resumenExcepciones() {
        if (!self::puedeGestionarPermisos()) return ['success' => false, 'mensaje' => 'Solo el administrador puede ver los permisos'];
        try {
            $filas = self::pdo()->query(
                "SELECT e.id_usuario, CONCAT(u.nombre, ' ', u.apellidos) AS nombre, r.nombre_rol, e.accion, e.alcance, e.fecha,
                        CONCAT(o.nombre, ' ', o.apellidos) AS otorgado_por
                 FROM permisos_usuario_accion e
                 INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
                 INNER JOIN roles r    ON r.id_rol = u.id_rol
                 LEFT  JOIN usuarios o ON o.id_usuario = e.otorgado_por
                 ORDER BY u.nombre, u.apellidos, e.accion"
            )->fetchAll(PDO::FETCH_ASSOC);
            $titulos = [];
            foreach (self::catalogo() as $m) foreach ($m['acciones'] as $a => $d) $titulos[$a] = $d['titulo'];
            foreach ($filas as &$f) $f['accion_titulo'] = $titulos[$f['accion']] ?? $f['accion'];
            return ['success' => true, 'excepciones' => $filas];
        } catch (Throwable $e) {
            error_log('Permisos::resumenExcepciones: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo cargar el resumen'];
        }
    }
}
?>
