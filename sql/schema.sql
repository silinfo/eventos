-- Gestor de eventos del SUAP
-- MySQL 5.7+ / MariaDB 10.3+

CREATE TABLE IF NOT EXISTS eventos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo         VARCHAR(20)  NOT NULL,             -- formacion | organizacion | convivencia
  titulo       VARCHAR(200) NOT NULL,
  descripcion  TEXT         NULL,
  fecha        DATE         NOT NULL,             -- fecha de inicio
  fecha_fin    DATE         NULL,                 -- solo si dura varios días
  hora_inicio  TIME         NULL,
  hora_fin     TIME         NULL,
  duracion     VARCHAR(60)  NULL,                 -- texto libre: "2 h", "20 horas lectivas"...
  lugar        VARCHAR(200) NULL,
  url          VARCHAR(500) NULL,                 -- enlace a más información
  publicado    TINYINT(1)   NOT NULL DEFAULT 1,   -- 0 = borrador (no se ve en el portal)
  creado       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fecha (fecha),
  KEY idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
