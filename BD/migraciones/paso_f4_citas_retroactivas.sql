-- =====================================================================
-- PASO F4: citas retroactivas (registrar atenciones de fechas pasadas)
--
--  1) Columna `origen` en citas: 'sistema' (agenda normal), 'retroactivo' (cargada a mano
--     para fechas pasadas) o 'importacion' (carga masiva, mas adelante). Asi se distingue
--     siempre el historial cargado de lo agendado normalmente.
--  2) Columna `id_carga_masiva` (reservada para deshacer cargas masivas de citas).
--  3) Permiso `citas.registrar_retroactiva` (por defecto SOLO el Administrador; se puede
--     dar a una persona concreta en Configuracion > Permisos por usuario).
--
-- EJECUTAR UNA SENTENCIA A LA VEZ en phpMyAdmin (con SU base seleccionada).
-- Requisitos: paso_f1 (cargas_masivas) y paso_d1 (permisos_accion).
-- =====================================================================

ALTER TABLE citas
    ADD COLUMN origen          ENUM('sistema','retroactivo','importacion') NOT NULL DEFAULT 'sistema',
    ADD COLUMN id_carga_masiva INT NULL,
    ADD KEY idx_citas_origen (origen),
    ADD KEY idx_citas_carga (id_carga_masiva),
    ADD CONSTRAINT fk_citas_carga FOREIGN KEY (id_carga_masiva) REFERENCES cargas_masivas (id_carga) ON DELETE SET NULL;

INSERT IGNORE INTO permisos_accion (id_rol, accion, alcance)
SELECT id_rol, 'citas.registrar_retroactiva', IF(id_rol = 1, 'todos', 'ninguno') FROM roles;

-- Verificacion (esperado: 1 columna nueva y un permiso por cada rol de la base)
SELECT 'columna citas.origen (esperado 1)' AS elemento, COUNT(*) AS cantidad
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'origen'
UNION ALL SELECT 'permisos citas.registrar_retroactiva (esperado = n.º de roles; vea la fila siguiente)', COUNT(*) FROM permisos_accion WHERE accion = 'citas.registrar_retroactiva'
UNION ALL SELECT 'roles en esta base', COUNT(*) FROM roles;
