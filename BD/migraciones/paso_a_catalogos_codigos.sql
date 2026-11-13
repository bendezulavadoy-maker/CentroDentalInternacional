-- ══════════════════════════════════════════════════════════════
-- PASO A: catalogos con codigo (solo agrega, no rompe nada existente)
--
-- Convencion:
--   * Catalogos pequenos y estables  -> codigo de 2 digitos (01, 02...)
--   * Catalogos que crecen           -> codigo de 3 digitos (001, 002...)
--   * El codigo es TEXTO (CHAR), para conservar el cero a la izquierda.
--   * La llave primaria numerica (id) se mantiene; el codigo es adicional.
--   * La columna codigo queda NULL-able por ahora: se vuelve NOT NULL en el
--     paso B, cuando la aplicacion ya genere el codigo al crear registros.
-- ══════════════════════════════════════════════════════════════


-- ──────────────────────────────────────────────────────────────
-- 1. NUEVOS CATALOGOS
-- ──────────────────────────────────────────────────────────────

-- Tipo de documento de identidad.
-- Codigos segun el catalogo de SUNAT (referencial: confirmar la lista vigente
-- y las longitudes con el proveedor de facturacion electronica).
CREATE TABLE IF NOT EXISTS tipo_documento (
  id_tipo_documento INT(11) NOT NULL AUTO_INCREMENT,
  codigo CHAR(1) NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  abreviatura VARCHAR(10) NOT NULL,
  longitud_min TINYINT UNSIGNED NOT NULL,
  longitud_max TINYINT UNSIGNED NOT NULL,
  solo_numeros TINYINT(1) NOT NULL DEFAULT 1,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_tipo_documento),
  UNIQUE KEY uq_tipo_documento_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO tipo_documento (codigo, nombre, abreviatura, longitud_min, longitud_max, solo_numeros) VALUES
  ('1', 'Documento Nacional de Identidad', 'DNI', 8, 8, 1),
  ('4', 'Carnet de extranjeria', 'CE', 6, 12, 0),
  ('6', 'Registro Unico de Contribuyentes', 'RUC', 11, 11, 1),
  ('7', 'Pasaporte', 'PAS', 6, 12, 0);

-- Tipo de paciente (clasificacion clinica/comercial; no es lo mismo que el
-- cliente al que se factura).
CREATE TABLE IF NOT EXISTS tipo_paciente (
  id_tipo_paciente INT(11) NOT NULL AUTO_INCREMENT,
  codigo CHAR(2) NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_tipo_paciente),
  UNIQUE KEY uq_tipo_paciente_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO tipo_paciente (codigo, nombre) VALUES
  ('01', 'Particular'),
  ('02', 'Convenio con empresa'),
  ('03', 'Seguro'),
  ('04', 'Campana / cortesia');


-- ──────────────────────────────────────────────────────────────
-- 2. COLUMNA codigo EN CATALOGOS EXISTENTES (2 digitos)
--    Los codigos iniciales se toman del id actual (1 -> '01').
--    Importante: estos catalogos deben tener ids menores a 100.
-- ──────────────────────────────────────────────────────────────

ALTER TABLE roles ADD COLUMN codigo CHAR(2) NULL AFTER id_rol;
UPDATE roles SET codigo = LPAD(id_rol, 2, '0') WHERE codigo IS NULL;
ALTER TABLE roles ADD UNIQUE KEY uq_roles_codigo (codigo);

ALTER TABLE sedes ADD COLUMN codigo CHAR(2) NULL AFTER id_sede_atencion;
UPDATE sedes SET codigo = LPAD(id_sede_atencion, 2, '0') WHERE codigo IS NULL;
ALTER TABLE sedes ADD UNIQUE KEY uq_sedes_codigo (codigo);

ALTER TABLE tipos_atencion ADD COLUMN codigo CHAR(2) NULL AFTER id_tipo_atencion;
UPDATE tipos_atencion SET codigo = LPAD(id_tipo_atencion, 2, '0') WHERE codigo IS NULL;
ALTER TABLE tipos_atencion ADD UNIQUE KEY uq_tipos_atencion_codigo (codigo);

ALTER TABLE estado_cita ADD COLUMN codigo CHAR(2) NULL AFTER id_estado_cita;
UPDATE estado_cita SET codigo = LPAD(id_estado_cita, 2, '0') WHERE codigo IS NULL;
ALTER TABLE estado_cita ADD UNIQUE KEY uq_estado_cita_codigo (codigo);


-- ──────────────────────────────────────────────────────────────
-- 3. COLUMNA codigo EN CATALOGOS QUE CRECEN (3 digitos)
--    Un servicio, plan o aparato nuevo se agrega con frecuencia; 2 digitos
--    se quedarian cortos (maximo 99).
-- ──────────────────────────────────────────────────────────────

ALTER TABLE tipo_servicio ADD COLUMN codigo CHAR(3) NULL AFTER id_tipo_servicio;
UPDATE tipo_servicio SET codigo = LPAD(id_tipo_servicio, 3, '0') WHERE codigo IS NULL;
ALTER TABLE tipo_servicio ADD UNIQUE KEY uq_tipo_servicio_codigo (codigo);

ALTER TABLE planes_tratamiento ADD COLUMN codigo CHAR(3) NULL AFTER id_plan;
UPDATE planes_tratamiento SET codigo = LPAD(id_plan, 3, '0') WHERE codigo IS NULL;
ALTER TABLE planes_tratamiento ADD UNIQUE KEY uq_planes_tratamiento_codigo (codigo);

ALTER TABLE aparatologia ADD COLUMN codigo CHAR(3) NULL AFTER id_aparatologia;
UPDATE aparatologia SET codigo = LPAD(id_aparatologia, 3, '0') WHERE codigo IS NULL;
ALTER TABLE aparatologia ADD UNIQUE KEY uq_aparatologia_codigo (codigo);


-- ──────────────────────────────────────────────────────────────
-- Verificacion (opcional, ejecutar despues):
--   SELECT id_sede_atencion, codigo, nombre_sede FROM sedes;
--   SELECT id_tipo_servicio, codigo, nombre_servicio FROM tipo_servicio;
--   SELECT * FROM tipo_documento;
--   SELECT * FROM tipo_paciente;
-- ──────────────────────────────────────────────────────────────
