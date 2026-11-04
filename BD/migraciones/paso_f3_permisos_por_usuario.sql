-- =====================================================================
-- PASO F3: permisos por usuario (excepciones sobre los permisos del rol)
--
-- El rol sigue dando los permisos base. Esta tabla guarda EXCEPCIONES de un usuario concreto
-- sobre una accion: se le puede dar algo que su rol no tiene, o quitarle algo que si tiene.
-- Si un usuario no tiene excepcion para una accion, vale lo de su rol (como hasta ahora).
-- Se administra en Configuracion > Permisos por usuario (solo Administrador).
--
-- EJECUTAR UNA SENTENCIA A LA VEZ en phpMyAdmin (con SU base seleccionada).
-- Requisito: paso_d1 (permisos_accion).
-- =====================================================================

CREATE TABLE IF NOT EXISTS permisos_usuario_accion (
    id_usuario   INT         NOT NULL,
    accion       VARCHAR(60) NOT NULL,
    alcance      ENUM('ninguno','asignados','todos') NOT NULL,
    otorgado_por INT         NULL,
    fecha        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, accion),
    KEY idx_pua_accion (accion),
    CONSTRAINT fk_pua_usuario  FOREIGN KEY (id_usuario)   REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_pua_otorgado FOREIGN KEY (otorgado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Verificacion (debe devolver 1 fila: la tabla existe y esta vacia)
SELECT TABLE_NAME AS tabla, (SELECT COUNT(*) FROM permisos_usuario_accion) AS excepciones
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permisos_usuario_accion';
