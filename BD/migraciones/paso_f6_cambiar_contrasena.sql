-- =====================================================================
-- PASO F6: cambiar y restablecer la contrasena del personal
--
-- Hasta ahora toda persona nueva recibia la misma contrasena inicial y no existia
-- ninguna forma de cambiarla. Este paso prepara la base para:
--   * obligar a cambiar la contrasena (cuentas actuales, nuevas o restablecidas)
--   * registrar cuando se cambio por ultima vez
--   * el permiso de "restablecer la contrasena de otra persona" (solo Administrador)
--
-- Es aditivo: el codigo anterior sigue funcionando con estas columnas nuevas.
-- ORDEN: este SQL primero; despues el codigo (git pull).
-- EJECUTAR UNA SENTENCIA A LA VEZ en phpMyAdmin (con SU base seleccionada).
-- =====================================================================

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS debe_cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS contrasena_cambiada DATETIME NULL;

-- Todas las cuentas que nunca cambiaron su contrasena deberan hacerlo en su proximo ingreso.
-- (Repetirlo es inofensivo: no toca a quien ya la cambio.)
UPDATE usuarios SET debe_cambiar_contrasena = 1 WHERE contrasena_cambiada IS NULL;

-- Permiso para restablecer la contrasena de otra persona: solo el Administrador.
INSERT IGNORE INTO permisos_accion (id_rol, accion, alcance)
SELECT id_rol, 'personal.restablecer_contrasena', IF(id_rol = 1, 'todos', 'ninguno') FROM roles;

-- Verificacion (esperado: 2 columnas, y el permiso = n.o de roles)
SELECT (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
          AND COLUMN_NAME IN ('debe_cambiar_contrasena', 'contrasena_cambiada')) AS columnas_nuevas,
       (SELECT COUNT(*) FROM permisos_accion WHERE accion = 'personal.restablecer_contrasena') AS permisos,
       (SELECT COUNT(*) FROM roles) AS roles_en_la_base;
