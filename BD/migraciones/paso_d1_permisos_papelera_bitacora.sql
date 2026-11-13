-- ══════════════════════════════════════════════════════════════
-- PASO D1: odontologo responsable, papelera, permisos finos,
--          parametros del sistema y bitacora
--
-- Es aditivo: no cambia ni borra datos existentes. Ejecutarlo UNA sola vez.
-- Requiere haber corrido antes los pasos A, A2 y C1.
-- ══════════════════════════════════════════════════════════════


-- ── 1. ODONTOLOGO RESPONSABLE DEL PACIENTE ───────────────────
-- Define quien "esta a cargo" del paciente. Se usa para el alcance
-- "Solo asignados" de los permisos.
ALTER TABLE pacientes
  ADD COLUMN id_doctor_responsable INT(11) NULL AFTER id_apoderado,
  ADD KEY idx_pacientes_doctor_resp (id_doctor_responsable),
  ADD CONSTRAINT fk_pacientes_doctor_resp
      FOREIGN KEY (id_doctor_responsable) REFERENCES usuarios (id_usuario)
      ON DELETE SET NULL;


-- ── 2. PAPELERA DE DOCUMENTOS ────────────────────────────────
-- Eliminar = marcar activo=0 y anotar cuando, quien y en que "lote"
-- (todo lo que se elimina junto, por ejemplo una carpeta con su contenido,
-- comparte el lote, para poder restaurarlo completo).
ALTER TABLE carpetas_documentos
  ADD COLUMN fecha_eliminacion DATETIME NULL AFTER activo,
  ADD COLUMN eliminado_por INT(11) NULL AFTER fecha_eliminacion,
  ADD COLUMN lote_eliminacion CHAR(32) NULL AFTER eliminado_por,
  ADD KEY idx_carpetas_papelera (activo, fecha_eliminacion),
  ADD KEY idx_carpetas_lote (lote_eliminacion);

ALTER TABLE documentos_paciente
  ADD COLUMN fecha_eliminacion DATETIME NULL AFTER activo,
  ADD COLUMN eliminado_por INT(11) NULL AFTER fecha_eliminacion,
  ADD COLUMN lote_eliminacion CHAR(32) NULL AFTER eliminado_por,
  ADD KEY idx_documentos_papelera (activo, fecha_eliminacion),
  ADD KEY idx_documentos_lote (lote_eliminacion);


-- ── 3. PERMISOS FINOS (rol + accion + alcance) ───────────────
-- permisos_rol (existente) sigue controlando que MODULOS ve cada rol.
-- permisos_accion controla QUE PUEDE HACER dentro de cada modulo:
--   alcance = 'ninguno' | 'asignados' (solo pacientes donde es responsable) | 'todos'
-- El catalogo de acciones (nombres y textos) vive en CONFIG/permisos.php.
CREATE TABLE IF NOT EXISTS permisos_accion (
  id_rol INT(11) NOT NULL,
  accion VARCHAR(60) NOT NULL,
  alcance ENUM('ninguno','asignados','todos') NOT NULL DEFAULT 'ninguno',
  PRIMARY KEY (id_rol, accion),
  CONSTRAINT fk_permisos_accion_rol FOREIGN KEY (id_rol) REFERENCES roles (id_rol) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Valores iniciales (1=Administrador, 2=Odontologo, 3=Asistente). El administrador
-- los cambia despues desde Configuracion. Un rol nuevo empieza sin permisos.
INSERT IGNORE INTO permisos_accion (id_rol, accion, alcance) VALUES
  -- Historia clinica
  (1,'historia_clinica.editar','ninguno'), (2,'historia_clinica.editar','todos'), (3,'historia_clinica.editar','ninguno'),
  -- Documentos
  (1,'documentos.subir','todos'),          (2,'documentos.subir','todos'),          (3,'documentos.subir','todos'),
  (1,'documentos.crear_carpeta','ninguno'),(2,'documentos.crear_carpeta','todos'),  (3,'documentos.crear_carpeta','ninguno'),
  (1,'documentos.descargar','todos'),      (2,'documentos.descargar','todos'),      (3,'documentos.descargar','todos'),
  (1,'documentos.renombrar','todos'),      (2,'documentos.renombrar','asignados'),  (3,'documentos.renombrar','ninguno'),
  (1,'documentos.mover','todos'),          (2,'documentos.mover','asignados'),      (3,'documentos.mover','ninguno'),
  (1,'documentos.eliminar','todos'),       (2,'documentos.eliminar','asignados'),   (3,'documentos.eliminar','ninguno'),
  (1,'documentos.papelera','todos'),       (2,'documentos.papelera','ninguno'),     (3,'documentos.papelera','ninguno'),
  -- Pacientes
  (1,'pacientes.asignar_responsable','todos'), (2,'pacientes.asignar_responsable','ninguno'), (3,'pacientes.asignar_responsable','todos'),
  (1,'pacientes.restablecer_portal','todos'),  (2,'pacientes.restablecer_portal','ninguno'),  (3,'pacientes.restablecer_portal','todos');


-- ── 4. PARAMETROS DEL SISTEMA ────────────────────────────────
-- Ajustes que el administrador cambia sin tocar codigo.
CREATE TABLE IF NOT EXISTS configuracion_sistema (
  clave VARCHAR(60) NOT NULL,
  valor VARCHAR(255) NOT NULL,
  descripcion VARCHAR(255) NULL,
  fecha_modificacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('papelera_dias', '15', 'Dias que un documento o carpeta permanece en la papelera antes de borrarse definitivamente'),
  ('documentos_max_mb', '10', 'Tamano maximo por archivo subido, en MB'),
  ('documentos_extensiones', 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'Extensiones de archivo permitidas, separadas por coma');


-- ── 5. BITACORA ──────────────────────────────────────────────
-- Registro de quien hizo que y cuando. Sin llaves foraneas a proposito: el registro
-- debe conservarse aunque despues se borre el usuario o el paciente.
CREATE TABLE IF NOT EXISTS bitacora (
  id_bitacora BIGINT(20) NOT NULL AUTO_INCREMENT,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actor_tipo ENUM('personal','paciente','sistema') NOT NULL DEFAULT 'personal',
  id_usuario INT(11) NULL,
  accion VARCHAR(60) NOT NULL,
  entidad VARCHAR(40) NULL,
  id_entidad INT(11) NULL,
  id_paciente INT(11) NULL,
  detalle TEXT NULL,
  ip VARCHAR(45) NULL,
  PRIMARY KEY (id_bitacora),
  KEY idx_bitacora_fecha (fecha_hora),
  KEY idx_bitacora_usuario (id_usuario, fecha_hora),
  KEY idx_bitacora_entidad (entidad, id_entidad),
  KEY idx_bitacora_paciente (id_paciente, fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ══════════════════════════════════════════════════════════════
-- OPCIONAL: asignar a cada paciente que aun no tiene responsable el
-- odontologo de su cita mas reciente. Quita los "--" de las lineas para correrlo.
-- (Mientras un paciente no tenga responsable, solo el administrador podra
--  modificar sus documentos.)
-- ══════════════════════════════════════════════════════════════
-- UPDATE pacientes p
-- JOIN (
--     SELECT c.id_paciente,
--            SUBSTRING_INDEX(GROUP_CONCAT(c.id_doctor ORDER BY c.fecha DESC, c.hora DESC), ',', 1) AS id_doc
--     FROM citas c
--     GROUP BY c.id_paciente
-- ) ult ON ult.id_paciente = p.id_paciente
-- JOIN usuarios u ON u.id_usuario = ult.id_doc AND u.id_rol = 2
-- SET p.id_doctor_responsable = u.id_usuario
-- WHERE p.id_doctor_responsable IS NULL;
