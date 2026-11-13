-- =====================================================================
-- PASO F1: importacion masiva de pacientes (desde Excel/CSV)
--
--  1) Tabla `cargas_masivas`: un registro por cada importacion (quien, cuando, cuantos).
--  2) Columna `id_carga_masiva` en `pacientes` y `apoderados`: marca que registros nacieron
--     en una carga, para poder DESHACERLA completa si algo salio mal.
--  3) Permiso `pacientes.importar` (por defecto SOLO el Administrador; se cambia en
--     Configuracion > Permisos por Rol > Acciones permitidas).
--
-- Requisitos: haber ejecutado antes paso_d1 (tabla permisos_accion) y paso_e1.
-- Seguro de repetir: no duplica nada (IF NOT EXISTS / INSERT IGNORE).
-- Antes de ejecutar: en phpMyAdmin haga clic en SU base de datos (panel izquierdo).
-- =====================================================================

-- Comprobacion: debe mostrar cuantos pacientes hay. Si da el error
-- "Table '...pacientes' doesn't exist", la base seleccionada NO es la del sistema.
SELECT COUNT(*) AS pacientes_en_esta_base FROM pacientes;

-- ---- 1) Registro de cargas ----
CREATE TABLE IF NOT EXISTS cargas_masivas (
    id_carga             INT          NOT NULL AUTO_INCREMENT,
    tipo                 VARCHAR(30)  NOT NULL DEFAULT 'pacientes',
    nombre_archivo       VARCHAR(150) NOT NULL,
    total_filas          INT          NOT NULL DEFAULT 0,
    importados           INT          NOT NULL DEFAULT 0,
    omitidos             INT          NOT NULL DEFAULT 0,   -- ya existian en el sistema
    con_error            INT          NOT NULL DEFAULT 0,
    deshechos            INT          NOT NULL DEFAULT 0,   -- registros eliminados al deshacer la carga
    estado               ENUM('activa','parcial','revertida') NOT NULL DEFAULT 'activa',
    id_usuario           INT          NULL,
    fecha_carga          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario_reversion INT          NULL,
    fecha_reversion      DATETIME     NULL,
    PRIMARY KEY (id_carga),
    KEY idx_cargas_tipo_fecha (tipo, fecha_carga),
    CONSTRAINT fk_cargas_usuario     FOREIGN KEY (id_usuario)           REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
    CONSTRAINT fk_cargas_usuario_rev FOREIGN KEY (id_usuario_reversion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---- 2) Marca de carga en pacientes y apoderados ----
ALTER TABLE pacientes
    ADD COLUMN IF NOT EXISTS id_carga_masiva INT NULL,
    ADD INDEX IF NOT EXISTS idx_pacientes_carga (id_carga_masiva),
    ADD CONSTRAINT fk_pacientes_carga FOREIGN KEY IF NOT EXISTS (id_carga_masiva) REFERENCES cargas_masivas (id_carga) ON DELETE SET NULL;

ALTER TABLE apoderados
    ADD COLUMN IF NOT EXISTS id_carga_masiva INT NULL,
    ADD INDEX IF NOT EXISTS idx_apoderados_carga (id_carga_masiva),
    ADD CONSTRAINT fk_apoderados_carga FOREIGN KEY IF NOT EXISTS (id_carga_masiva) REFERENCES cargas_masivas (id_carga) ON DELETE SET NULL;

-- ---- 3) Permiso: solo el Administrador por defecto ----
INSERT IGNORE INTO permisos_accion (id_rol, accion, alcance)
SELECT id_rol, 'pacientes.importar', IF(id_rol = 1, 'todos', 'ninguno') FROM roles;

-- Verificacion
SELECT 'cargas_masivas (tabla creada)' AS elemento, COUNT(*) AS cantidad FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cargas_masivas'
UNION ALL SELECT 'roles con el permiso pacientes.importar', COUNT(*) FROM permisos_accion WHERE accion = 'pacientes.importar'
UNION ALL SELECT CONCAT('columna ', TABLE_NAME, '.id_carga_masiva'), 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'id_carga_masiva';
