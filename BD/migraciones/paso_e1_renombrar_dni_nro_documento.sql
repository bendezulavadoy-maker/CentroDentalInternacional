-- =====================================================================
-- PASO E1: la columna `dni` pasa a llamarse `nro_documento`
-- (el sistema ya maneja DNI, CE, RUC, PAS...: el campo guarda el NUMERO del documento)
--
-- Tablas: pacientes, usuarios, apoderados.
-- Los indices unicos (tipo + numero) y los datos se conservan.
-- Seguro de repetir: si la columna ya se renombro, no hace nada (IF EXISTS).
--
-- ORDEN: ejecutar este script ANTES de subir el codigo nuevo (git pull).
-- Hacer copia de seguridad de la base antes de ejecutarlo.
-- Antes de ejecutar: en phpMyAdmin haga clic en SU base de datos (panel izquierdo).
-- =====================================================================

-- Comprobacion: debe mostrar cuantos pacientes hay. Si da el error
-- "Table '...pacientes' doesn't exist", la base seleccionada NO es la del sistema.
SELECT COUNT(*) AS pacientes_en_esta_base FROM pacientes;

ALTER TABLE pacientes  CHANGE COLUMN IF EXISTS dni nro_documento VARCHAR(20) NOT NULL;
ALTER TABLE usuarios   CHANGE COLUMN IF EXISTS dni nro_documento VARCHAR(20) NOT NULL;
ALTER TABLE apoderados CHANGE COLUMN IF EXISTS dni nro_documento VARCHAR(20) NOT NULL;

-- Verificacion: deben aparecer las 3 tablas con la columna nro_documento (y ninguna con dni)
SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna, COLUMN_TYPE AS tipo
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN ('dni', 'nro_documento')
ORDER BY TABLE_NAME;
