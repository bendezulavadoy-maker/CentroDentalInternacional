-- =====================================================================
-- PASO F2: pagos con metodo de pago, comprobante, anulacion y anticipos
--
-- Idea (como en un ERP): un pago NUNCA se borra ni se edita. Anular un pago registra
-- un movimiento INVERSO (monto negativo) que lo compensa, con su motivo. Asi los saldos
-- existentes se corrigen solos y todo queda auditado.
-- Un pago mayor a la deuda deja el excedente como "anticipo" (saldo a favor del paciente).
--
-- EJECUTAR UNA SENTENCIA A LA VEZ en phpMyAdmin (con SU base seleccionada).
-- Requisitos: paso_d1 (permisos_accion) y paso_f1 (cargas_masivas).
-- =====================================================================

-- ---- 1) Catalogo de metodos de pago (editable en Configuracion; el codigo lo define la clinica) ----
CREATE TABLE IF NOT EXISTS metodo_pago (
    id_metodo_pago      INT         NOT NULL AUTO_INCREMENT,
    codigo              VARCHAR(10) NOT NULL,
    nombre              VARCHAR(60) NOT NULL,
    requiere_referencia TINYINT(1)  NOT NULL DEFAULT 0,   -- 1 = exige numero de operacion/comprobante
    es_sistema          TINYINT(1)  NOT NULL DEFAULT 0,   -- 1 = lo usa el sistema (no se elige a mano ni se edita)
    activo              TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (id_metodo_pago),
    UNIQUE KEY uq_metodo_pago_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---- 2) Metodos iniciales ----
INSERT IGNORE INTO metodo_pago (codigo, nombre, requiere_referencia, es_sistema) VALUES
    ('01',    'Efectivo',                        0, 0),
    ('02',    'Yape',                            0, 0),
    ('03',    'Plin',                            0, 0),
    ('04',    'Tarjeta (POS)',                   1, 0),
    ('05',    'Transferencia bancaria',          1, 0),
    ('SALDO', 'Saldo a favor (anticipo)',        0, 1);

-- ---- 3) Nuevas columnas en pagos ----
ALTER TABLE pagos
    ADD COLUMN id_metodo_pago   INT          NULL,
    ADD COLUMN nro_comprobante  VARCHAR(40)  NULL,
    ADD COLUMN fecha_registro   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN origen           ENUM('sistema','retroactivo','importacion') NOT NULL DEFAULT 'sistema',
    ADD COLUMN id_operacion     INT          NULL,
    ADD COLUMN id_pago_anulado  INT          NULL,
    ADD COLUMN anulado          TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN motivo_anulacion VARCHAR(255) NULL,
    ADD COLUMN id_par           INT          NULL,
    ADD COLUMN id_carga_masiva  INT          NULL,
    ADD KEY idx_pagos_operacion (id_operacion),
    ADD KEY idx_pagos_metodo (id_metodo_pago),
    ADD KEY idx_pagos_anula_a (id_pago_anulado),
    ADD CONSTRAINT fk_pagos_metodo FOREIGN KEY (id_metodo_pago) REFERENCES metodo_pago (id_metodo_pago),
    ADD CONSTRAINT fk_pagos_anula_a FOREIGN KEY (id_pago_anulado) REFERENCES pagos (id_pago),
    ADD CONSTRAINT fk_pagos_carga FOREIGN KEY (id_carga_masiva) REFERENCES cargas_masivas (id_carga) ON DELETE SET NULL;

-- ---- 4) Nuevo tipo de pago: anticipo (saldo a favor, sin servicio asignado) ----
ALTER TABLE pagos MODIFY tipo_pago ENUM('aparatologia','cuota_inicial','sesion','total','anticipo') DEFAULT 'total';

-- ---- 5) Pagos que ya existen: se agrupan en "operaciones" (los de un mismo paciente y momento) ----
UPDATE pagos p
JOIN (SELECT id_paciente, fecha_pago, MIN(id_pago) AS primero FROM pagos GROUP BY id_paciente, fecha_pago) g
  ON g.id_paciente = p.id_paciente AND g.fecha_pago <=> p.fecha_pago
SET p.id_operacion = g.primero, p.fecha_registro = IFNULL(p.fecha_pago, p.fecha_registro)
WHERE p.id_operacion IS NULL;

-- ---- 6) Permisos de Cobros: quien tiene el modulo registra; solo el Administrador anula y registra con fecha pasada ----
INSERT IGNORE INTO permisos_accion (id_rol, accion, alcance)
SELECT r.id_rol, a.accion,
       IF(r.id_rol = 1 OR (a.accion = 'cobros.registrar' AND EXISTS (SELECT 1 FROM permisos_rol pr WHERE pr.id_rol = r.id_rol AND pr.modulo = 'cobros')), 'todos', 'ninguno')
FROM roles r
CROSS JOIN (SELECT 'cobros.registrar' AS accion UNION ALL SELECT 'cobros.anular' UNION ALL SELECT 'cobros.registrar_retroactivo') a;

-- ---- Verificacion ----
SELECT 'metodos de pago (esperado 6)' AS elemento, COUNT(*) AS cantidad FROM metodo_pago
UNION ALL SELECT 'columna nueva en pagos: id_metodo_pago', COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos' AND COLUMN_NAME = 'id_metodo_pago'
UNION ALL SELECT 'pagos sin operacion (esperado 0)', COUNT(*) FROM pagos WHERE id_operacion IS NULL
UNION ALL SELECT 'permisos de cobros (esperado 12 = 3 acciones x 4 roles)', COUNT(*) FROM permisos_accion WHERE accion LIKE 'cobros.%';
