-- =====================================================================
-- PASO F0: el correo del paciente pasa a ser OPCIONAL
--
-- Muchos pacientes (sobre todo ninos) no tienen correo. Sin este paso, registrar
-- o importar un paciente sin correo da error. Va ANTES de paso_f1 (la importacion
-- de pacientes admite correos vacios).
--
-- EJECUTAR UNA SENTENCIA A LA VEZ en phpMyAdmin (con SU base seleccionada).
-- =====================================================================

ALTER TABLE pacientes
  MODIFY correo VARCHAR(150) NULL DEFAULT NULL;

-- Verificacion (debe devolver: correo | YES)
SELECT COLUMN_NAME AS columna, IS_NULLABLE AS acepta_vacios
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacientes' AND COLUMN_NAME = 'correo';
