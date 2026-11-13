-- =====================================================================
-- PASO E2: el codigo de los catalogos pasa a ser OBLIGATORIO (NOT NULL)
-- Tablas: roles, sedes, tipo_servicio, tipos_atencion, aparatologia,
--         planes_tratamiento, estado_cita
--
-- Requisito: haber ejecutado antes paso_a_catalogos_codigos.sql (asigna los codigos).
-- Si algun comando da error "Invalid use of NULL value" (o "Data truncated"), esa tabla
-- tiene filas sin codigo: avisar antes de continuar.
-- Seguro de repetir.
-- Antes de ejecutar: en phpMyAdmin haga clic en SU base de datos (panel izquierdo).
-- =====================================================================

-- Comprobacion: debe mostrar cuantos roles hay. Si da el error
-- "Table '...roles' doesn't exist", la base seleccionada NO es la del sistema.
SELECT COUNT(*) AS roles_en_esta_base FROM roles;

ALTER TABLE roles              MODIFY codigo VARCHAR(10) NOT NULL;
ALTER TABLE sedes              MODIFY codigo VARCHAR(10) NOT NULL;
ALTER TABLE tipo_servicio      MODIFY codigo VARCHAR(10) NOT NULL;
ALTER TABLE tipos_atencion     MODIFY codigo VARCHAR(10) NOT NULL;
ALTER TABLE aparatologia       MODIFY codigo VARCHAR(10) NOT NULL;
ALTER TABLE planes_tratamiento MODIFY codigo VARCHAR(10) NOT NULL;
ALTER TABLE estado_cita        MODIFY codigo CHAR(2)     NOT NULL;

-- Verificacion: la columna acepta_vacio debe decir NO en todas
SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna, IS_NULLABLE AS acepta_vacio
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'codigo'
ORDER BY TABLE_NAME;
