-- ══════════════════════════════════════════════════════════════
-- PASO C1: tipo de documento en pacientes, usuarios y apoderados
--
-- Requiere haber corrido antes paso_a_catalogos_codigos.sql
-- (crea la tabla tipo_documento; el id 1 debe ser DNI).
--
-- Que hace, por cada tabla:
--   1. Agrega id_tipo_documento (por defecto 1 = DNI, que es lo que
--      tienen todos los registros actuales).
--   2. Amplia la columna dni a VARCHAR(20) para aceptar carnet de
--      extranjeria, pasaporte, etc. (el NOMBRE de la columna no cambia,
--      asi el codigo actual sigue funcionando; el renombre a
--      nro_documento se hace al final, en un paso aparte).
--   3. Cambia la unicidad: ya no es solo el numero, sino la pareja
--      tipo + numero.
-- ══════════════════════════════════════════════════════════════

-- Comprobacion previa (debe devolver: 1 | DNI)
-- SELECT id_tipo_documento, abreviatura FROM tipo_documento WHERE id_tipo_documento = 1;


-- ── PACIENTES ─────────────────────────────────────────────────
ALTER TABLE pacientes
  ADD COLUMN id_tipo_documento INT(11) NOT NULL DEFAULT 1 AFTER apellido,
  MODIFY dni VARCHAR(20) NOT NULL,
  DROP INDEX dni,
  ADD UNIQUE KEY uq_pacientes_documento (id_tipo_documento, dni),
  ADD CONSTRAINT fk_pacientes_tipo_documento
      FOREIGN KEY (id_tipo_documento) REFERENCES tipo_documento (id_tipo_documento);

-- ── USUARIOS (personal) ───────────────────────────────────────
ALTER TABLE usuarios
  ADD COLUMN id_tipo_documento INT(11) NOT NULL DEFAULT 1 AFTER apellidos,
  MODIFY dni VARCHAR(20) NOT NULL,
  DROP INDEX dni,
  ADD UNIQUE KEY uq_usuarios_documento (id_tipo_documento, dni),
  ADD CONSTRAINT fk_usuarios_tipo_documento
      FOREIGN KEY (id_tipo_documento) REFERENCES tipo_documento (id_tipo_documento);

-- ── APODERADOS ────────────────────────────────────────────────
ALTER TABLE apoderados
  ADD COLUMN id_tipo_documento INT(11) NOT NULL DEFAULT 1 AFTER apellido,
  MODIFY dni VARCHAR(20) NOT NULL,
  DROP INDEX unique_dni_apoderado,
  ADD UNIQUE KEY uq_apoderados_documento (id_tipo_documento, dni),
  ADD CONSTRAINT fk_apoderados_tipo_documento
      FOREIGN KEY (id_tipo_documento) REFERENCES tipo_documento (id_tipo_documento);


-- ══════════════════════════════════════════════════════════════
-- Verificacion (opcional):
--   SELECT id_paciente, id_tipo_documento, dni FROM pacientes;
--   SELECT id_usuario,  id_tipo_documento, dni FROM usuarios;
--   SHOW COLUMNS FROM pacientes LIKE 'dni';   -- debe decir varchar(20)
--
-- Reversa (solo es segura mientras todos los registros sean tipo DNI):
--   ALTER TABLE pacientes  DROP FOREIGN KEY fk_pacientes_tipo_documento,
--     DROP INDEX uq_pacientes_documento, DROP COLUMN id_tipo_documento,
--     MODIFY dni CHAR(8) NOT NULL, ADD UNIQUE KEY dni (dni);
--   (igual para usuarios y apoderados)
-- ══════════════════════════════════════════════════════════════
