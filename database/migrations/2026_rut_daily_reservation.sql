-- Migración de producción para instalaciones existentes.
-- Ejecutar después de database/schema.sql.
-- La columna es nullable para conservar reservas históricas; el endpoint exige RUT para nuevas reservas.
ALTER TABLE reservations
  ADD COLUMN rut_normalizado VARCHAR(9) NULL AFTER last_name,
  ADD COLUMN active_rut VARCHAR(9)
    GENERATED ALWAYS AS (CASE WHEN status <> 'cancelled' THEN rut_normalizado ELSE NULL END) STORED,
  ADD UNIQUE KEY uq_active_rut_date(active_rut, reservation_date);
