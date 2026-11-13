-- ══════════════════════════════════════════════════════════════
-- PASO A2: el codigo de los catalogos lo define la clinica
-- Amplia la columna codigo a VARCHAR(10) (antes 2 o 3 caracteres).
-- Los valores ya cargados ('01', '001'...) se conservan tal cual.
--
-- No se tocan:
--   * estado_cita   -> lo usa la logica del sistema; sus codigos son fijos.
--   * tipo_documento -> codigos oficiales de SUNAT; no deben cambiar.
-- ══════════════════════════════════════════════════════════════

ALTER TABLE roles              MODIFY codigo VARCHAR(10) NULL;
ALTER TABLE sedes              MODIFY codigo VARCHAR(10) NULL;
ALTER TABLE tipos_atencion     MODIFY codigo VARCHAR(10) NULL;
ALTER TABLE tipo_servicio      MODIFY codigo VARCHAR(10) NULL;
ALTER TABLE planes_tratamiento MODIFY codigo VARCHAR(10) NULL;
ALTER TABLE aparatologia       MODIFY codigo VARCHAR(10) NULL;
ALTER TABLE tipo_paciente      MODIFY codigo VARCHAR(10) NOT NULL;
