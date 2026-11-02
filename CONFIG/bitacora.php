<?php
/**
 * BITACORA: registro de quien hizo que y cuando.
 *
 * Uso:
 *   registrarBitacora('documento.eliminar', 'documento', $idDocumento, $idPaciente, ['titulo' => 'Radiografia.pdf']);
 *
 * Nunca interrumpe la operacion principal: si la tabla no existe o falla la escritura,
 * devuelve false y deja el motivo en el log de errores de PHP.
 */
require_once __DIR__ . '/conexion.php';

function registrarBitacora($accion, $entidad = null, $idEntidad = null, $idPaciente = null,
                           $detalle = null, $actorTipo = 'personal', $idUsuario = null) {
    static $pdo = null;
    try {
        if ($pdo === null) {
            $pdo = (new Conexion())->getConexion();
        }
        if ($idUsuario === null && $actorTipo === 'personal') {
            $idUsuario = isset($_SESSION['usuario']['id_usuario']) ? (int)$_SESSION['usuario']['id_usuario'] : null;
        }
        if ($detalle !== null && !is_string($detalle)) {
            $detalle = json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $stmt = $pdo->prepare(
            "INSERT INTO bitacora (actor_tipo, id_usuario, accion, entidad, id_entidad, id_paciente, detalle, ip)
             VALUES (:actor, :usuario, :accion, :entidad, :id_entidad, :id_paciente, :detalle, :ip)"
        );
        $stmt->execute([
            ':actor'       => $actorTipo,
            ':usuario'     => $idUsuario,
            ':accion'      => substr((string)$accion, 0, 60),
            ':entidad'     => $entidad !== null ? substr((string)$entidad, 0, 40) : null,
            ':id_entidad'  => $idEntidad,
            ':id_paciente' => $idPaciente,
            ':detalle'     => $detalle,
            ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('bitacora: ' . $e->getMessage());
        return false;
    }
}
?>