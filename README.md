# Viña Stage · Sistema de Reservas

Landing + reservas. Frontend Vue 3 + Vite, backend PHP + MySQL.

## Stack real

- Frontend: Vue 3 + Vite (`src/App.vue`, `src/styles.css`), build a `dist/`.
- Backend: PHP 8 + MySQL (`api/config.php`, `api/availability.php`, `api/reservations.php`).
- Base de datos: `database/schema.sql` (MySQL 8 / MariaDB 10.5+, crea tablas + horarios Lun–Vie 14–20h).
- Deploy: GitHub Pages sirve el `dist` estático; el PHP debe vivir en un hosting con MySQL.

## API

- `GET /api/availability.php?date=YYYY-MM-DD` → `{ date, slots: [{ slot_id, start_time, end_time, capacity, reserved, available }] }`
- `POST /api/reservations.php` body `{ date, slotId, firstName, lastName, whatsapp, email?, whatsappConsent? }` → `201 { reservation: { id, reservation_code, status } }`

Validaciones backend: fecha `YYYY-MM-DD` real, no pasada, máx 60 días, `weekday` del slot = día pedido (1=Lun…7=Dom), sin duplicados (mismo whatsapp+slot+fecha), sin sobrecupo (transacción + `FOR UPDATE`), whatsapp `8–15 dígitos`, email opcional válido, reintento de código `VS-XXXXXXXX` ante colisión.

## Desarrollo local

1. `npm install`
2. `npm run dev` (Vite en `:5173`, `/api` proxea a `http://localhost:8080` — ver `vite.config.js`)
3. Servir el PHP: `php -S localhost:8080 -t .` (o apuntar el DocumentRoot a `api/`) y crear la DB con `database/schema.sql`
4. Variables: copiar `.env.example` → `.env` (`DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS`, `VITE_API_BASE_URL`)

Sin backend la UI cae a datos `demo` y muestra el aviso “modo demostración”.

## Producción

1. `npm run build` → `dist/`
2. GitHub Pages publica `dist` (`.github/workflows/pages.yml` usa `npm ci`, requiere `package-lock.json` trackeado)
3. Subir `api/` a hosting PHP + importar `database/schema.sql`
4. Setear `VITE_API_BASE_URL=https://tu-dominio.cl/api` en el build de Pages (secret/env del workflow o `.env.production`)

> Nota: Pages es estático, no ejecuta PHP. Si `VITE_API_BASE_URL=/api` en Pages, las reservas quedan en modo demo.

