-- Ajustes del sitio + admin inicial
CREATE TABLE IF NOT EXISTS settings(
  `key` VARCHAR(80) PRIMARY KEY,
  `value` VARCHAR(500) NOT NULL DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings(`key`,`value`) VALUES
 ('site_name','Viña Stage'),
 ('hero_title','VIÑA STAGE'),
 ('hero_subtitle','Música, eventos y noches para vivir Viña desde el centro de la ciudad.'),
 ('address','Av. Valparaíso 65, Viña del Mar'),
 ('whatsapp',''),
 ('instagram',''),
 ('booking_enabled','1'),
 ('booking_notice','');
