-- =====================================================================
-- PASO F5: ficha del plan de tratamiento (permisos para editarlo)
--
--   planes.editar          notas, estado y fecha estimada de fin del plan
--                          (Administrador y Odontologos; se puede restringir en Configuracion)
--   planes.editar_costos   tipo de cobro, costo, cuotas, cuota inicial y "corregir costos"
--                          (por defecto SOLO el Administrador; se puede dar a una persona en Permisos por usuario)
--
-- EJECUTAR UNA SENTENCIA A LA VEZ en phpMyAdmin (con SU base seleccionada).
-- Requisito: paso_d1 (permisos_accion).
-- =====================================================================

INSERT IGNORE INTO permisos_accion (id_rol, accion, alcance)
SELECT id_rol, 'planes.editar', CASE id_rol WHEN 1 THEN 'todos' WHEN 2 THEN 'todos' ELSE 'ninguno' END FROM roles;

INSERT IGNORE INTO permisos_accion (id_rol, accion, alcance)
SELECT id_rol, 'planes.editar_costos', IF(id_rol = 1, 'todos', 'ninguno') FROM roles;

-- Verificacion (esperado: 8 = 2 permisos x 4 roles)
SELECT COUNT(*) AS permisos_de_planes FROM permisos_accion WHERE accion IN ('planes.editar', 'planes.editar_costos');
