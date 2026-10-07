-- Viña Stage · Reservas (MySQL 8 / MariaDB 10.5+)
CREATE DATABASE IF NOT EXISTS reservas_stage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reservas_stage;

CREATE TABLE IF NOT EXISTS admins(
  id CHAR(36) PRIMARY KEY,
  email VARCHAR(160) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- weekday: 1=Lunes ... 7=Domingo (compatible con WEEKDAY()+1 y DATE N)
CREATE TABLE IF NOT EXISTS schedule_templates(
  id CHAR(36) PRIMARY KEY,
  weekday TINYINT NOT NULL CHECK (weekday BETWEEN 1 AND 7),
  label VARCHAR(30) NOT NULL,
  capacity INT NOT NULL DEFAULT 40,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_day_label(weekday,label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS time_slots(
  id CHAR(36) PRIMARY KEY,
  template_id CHAR(36) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT NOT NULL DEFAULT 40,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_slot(template_id,start_time,end_time),
  CONSTRAINT fk_slot_template FOREIGN KEY(template_id) REFERENCES schedule_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_code VARCHAR(30) UNIQUE NOT NULL,
  reservation_date DATE NOT NULL,
  slot_id CHAR(36) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  rut_normalizado VARCHAR(9) NULL,
  whatsapp VARCHAR(30) NOT NULL,
  email VARCHAR(160),
  whatsapp_consent TINYINT(1) DEFAULT 0,
  status ENUM('confirmed','cancelled','attended','no_show') DEFAULT 'confirmed',
  source VARCHAR(30) DEFAULT 'web',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  cancelled_at TIMESTAMP NULL,
  active_rut VARCHAR(9) GENERATED ALWAYS AS (CASE WHEN status <> 'cancelled' THEN rut_normalizado ELSE NULL END) STORED,
  INDEX idx_date_slot(reservation_date,slot_id),
  INDEX idx_status(status),
  INDEX idx_whatsapp(whatsapp),
  UNIQUE KEY uq_active_rut_date(active_rut,reservation_date),
  CONSTRAINT fk_res_slot FOREIGN KEY(slot_id) REFERENCES time_slots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id CHAR(36) NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(80) NOT NULL,
  entity_id VARCHAR(80),
  metadata JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_admin FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horario oficial: lunes a viernes, 2 bloques de 3 horas (14:00–17:00 y 17:00–20:00), 20 cupos cada uno = 40 diarios.
INSERT IGNORE INTO schedule_templates(id,weekday,label,capacity,active) VALUES
 ('11111111-1111-1111-1111-111111111111',1,'Lunes',40,1),
 ('22222222-2222-2222-2222-222222222222',2,'Martes',40,1),
 ('33333333-3333-3333-3333-333333333333',3,'Miércoles',40,1),
 ('44444444-4444-4444-4444-444444444444',4,'Jueves',40,1),
 ('55555555-5555-5555-5555-555555555555',5,'Viernes',40,1);

INSERT IGNORE INTO time_slots(id,template_id,start_time,end_time,capacity,active) VALUES
 ('a1111111-1111-1111-1111-111111111111','11111111-1111-1111-1111-111111111111','14:00','17:00',20,1),
 ('a1111111-1111-1111-1111-111111111112','11111111-1111-1111-1111-111111111111','17:00','20:00',20,1),
 ('a2222222-2222-2222-2222-222222222221','22222222-2222-2222-2222-222222222222','14:00','17:00',20,1),
 ('a2222222-2222-2222-2222-222222222222','22222222-2222-2222-2222-222222222222','17:00','20:00',20,1),
 ('a3333333-3333-3333-3333-333333333331','33333333-3333-3333-3333-333333333333','14:00','17:00',20,1),
 ('a3333333-3333-3333-3333-333333333332','33333333-3333-3333-3333-333333333333','17:00','20:00',20,1),
 ('a4444444-4444-4444-4444-444444444441','44444444-4444-4444-4444-444444444444','14:00','17:00',20,1),
 ('a4444444-4444-4444-4444-444444444442','44444444-4444-4444-4444-444444444444','17:00','20:00',20,1),
 ('a5555555-5555-5555-5555-555555555551','55555555-5555-5555-5555-555555555555','14:00','17:00',20,1),
 ('a5555555-5555-5555-5555-555555555552','55555555-5555-5555-5555-555555555555','17:00','20:00',20,1);

-- Conversión de instalaciones existentes: 3 bloques de 2 horas (14-16 / 16-18 / 18-20, 20 cupos) -> 2 bloques de 3 horas.
-- Cada sentencia exige la forma antigua exacta del día, así que tras la primera corrida no hace nada y no pisa
-- ediciones posteriores del admin. Las reservas se reasignan por hora de inicio: 16:00 cae en 14-17 y 18:00 en 17-20.
UPDATE schedule_templates st
  JOIN time_slots a ON a.template_id=st.id AND a.start_time='14:00:00' AND a.end_time='16:00:00'
  JOIN time_slots b ON b.template_id=st.id AND b.start_time='16:00:00' AND b.end_time='18:00:00'
  JOIN time_slots c ON c.template_id=st.id AND c.start_time='18:00:00' AND c.end_time='20:00:00'
   SET st.capacity=40;

UPDATE reservations r
  JOIN time_slots b ON b.id=r.slot_id AND b.start_time='16:00:00' AND b.end_time='18:00:00'
  JOIN time_slots a ON a.template_id=b.template_id AND a.start_time='14:00:00' AND a.end_time='16:00:00'
  JOIN time_slots c ON c.template_id=b.template_id AND c.start_time='18:00:00' AND c.end_time='20:00:00'
   SET r.slot_id=a.id;

UPDATE reservations r
  JOIN time_slots c ON c.id=r.slot_id AND c.start_time='18:00:00' AND c.end_time='20:00:00'
  JOIN time_slots a ON a.template_id=c.template_id AND a.start_time='14:00:00' AND a.end_time='16:00:00'
  JOIN time_slots b ON b.template_id=c.template_id AND b.start_time='16:00:00' AND b.end_time='18:00:00'
   SET r.slot_id=b.id;

UPDATE time_slots a
  JOIN time_slots b ON b.template_id=a.template_id AND b.start_time='16:00:00' AND b.end_time='18:00:00'
  JOIN time_slots c ON c.template_id=a.template_id AND c.start_time='18:00:00' AND c.end_time='20:00:00'
   SET a.end_time='17:00:00', a.capacity=20
 WHERE a.start_time='14:00:00' AND a.end_time='16:00:00';

UPDATE time_slots b
  JOIN time_slots a ON a.template_id=b.template_id AND a.start_time='14:00:00' AND a.end_time='17:00:00'
  JOIN time_slots c ON c.template_id=b.template_id AND c.start_time='18:00:00' AND c.end_time='20:00:00'
   SET b.start_time='17:00:00', b.end_time='20:00:00', b.capacity=20
 WHERE b.start_time='16:00:00' AND b.end_time='18:00:00';

DELETE c FROM time_slots c
  JOIN time_slots a ON a.template_id=c.template_id AND a.start_time='14:00:00' AND a.end_time='17:00:00'
  JOIN time_slots b ON b.template_id=c.template_id AND b.start_time='17:00:00' AND b.end_time='20:00:00'
 WHERE c.start_time='18:00:00' AND c.end_time='20:00:00'
   AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.slot_id=c.id);
