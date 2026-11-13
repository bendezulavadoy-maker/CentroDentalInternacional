-- ══════════════════════════════════════════════════════════════
-- PASO C2 (portal): registro de intentos para limitar la fuerza bruta
--
-- El portal es publico: sin un limite, alguien podria probar contrasenas
-- o numeros de telefono sin parar. Esta tabla guarda cada intento fallido
-- (y cada consulta de documento) con una "clave" y la hora; el portal
-- bloquea temporalmente cuando se superan los maximos.
--
-- Si esta tabla no existe, el portal NO se rompe: simplemente no limita
-- (queda un aviso en el log de errores de PHP).
-- ══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS portal_intentos (
  id INT(11) NOT NULL AUTO_INCREMENT,
  clave VARCHAR(120) NOT NULL,
  fecha_hora DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_clave_fecha (clave, fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
