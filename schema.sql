CREATE TABLE IF NOT EXISTS admins (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  email TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('admin','staff')),
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS schedule_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  weekday SMALLINT NOT NULL CHECK (weekday BETWEEN 0 AND 6),
  label TEXT NOT NULL,
  capacity INTEGER NOT NULL DEFAULT 40 CHECK (capacity > 0),
  active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (weekday, label)
);

CREATE TABLE IF NOT EXISTS time_slots (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  template_id UUID NOT NULL REFERENCES schedule_templates(id) ON DELETE CASCADE,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INTEGER NOT NULL DEFAULT 40 CHECK (capacity > 0),
  active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (template_id, start_time, end_time)
);

CREATE TABLE IF NOT EXISTS reservations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  reservation_code TEXT UNIQUE NOT NULL,
  reservation_date DATE NOT NULL,
  slot_id UUID NOT NULL REFERENCES time_slots(id),
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  whatsapp TEXT NOT NULL,
  email TEXT,
  whatsapp_consent BOOLEAN NOT NULL DEFAULT FALSE,
  status TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed','cancelled','attended','no_show')),
  source TEXT NOT NULL DEFAULT 'web',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  cancelled_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_reservations_date_slot ON reservations(reservation_date, slot_id);
CREATE INDEX IF NOT EXISTS idx_reservations_whatsapp ON reservations(whatsapp);
CREATE INDEX IF NOT EXISTS idx_reservations_status ON reservations(status);

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGSERIAL PRIMARY KEY,
  admin_id UUID REFERENCES admins(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  entity TEXT NOT NULL,
  entity_id TEXT,
  metadata JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO schedule_templates (weekday,label,capacity)
VALUES
(1,'LUNES',40),(2,'MARTES',40),(3,'MIÉRCOLES',40),(4,'JUEVES',40),(5,'VIERNES',40)
ON CONFLICT DO NOTHING;

INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'14:00','16:00',14 FROM schedule_templates WHERE label='LUNES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'16:00','18:00',14 FROM schedule_templates WHERE label='LUNES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'18:00','20:00',12 FROM schedule_templates WHERE label='LUNES'
ON CONFLICT DO NOTHING;

INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'14:00','16:00',14 FROM schedule_templates WHERE label='MARTES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'16:00','18:00',14 FROM schedule_templates WHERE label='MARTES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'18:00','20:00',12 FROM schedule_templates WHERE label='MARTES'
ON CONFLICT DO NOTHING;

INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'14:00','16:00',14 FROM schedule_templates WHERE label='MIÉRCOLES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'16:00','18:00',14 FROM schedule_templates WHERE label='MIÉRCOLES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'18:00','20:00',12 FROM schedule_templates WHERE label='MIÉRCOLES'
ON CONFLICT DO NOTHING;

INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'14:00','16:00',14 FROM schedule_templates WHERE label='JUEVES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'16:00','18:00',14 FROM schedule_templates WHERE label='JUEVES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'18:00','20:00',12 FROM schedule_templates WHERE label='JUEVES'
ON CONFLICT DO NOTHING;

INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'14:00','16:00',14 FROM schedule_templates WHERE label='VIERNES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'16:00','18:00',14 FROM schedule_templates WHERE label='VIERNES'
ON CONFLICT DO NOTHING;
INSERT INTO time_slots (template_id,start_time,end_time,capacity)
SELECT id,'18:00','20:00',12 FROM schedule_templates WHERE label='VIERNES'
ON CONFLICT DO NOTHING;
