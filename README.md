# Viña Stage · Sistema de Reservas

Landing + reservas. Frontend Vue 3 + Vite, backend PHP + MySQL.

## Stack real

- Frontend: Vue 3 + Vite (`src/App.vue`, `src/styles.css`), build a `dist/`.
- Backend: PHP 8 + MySQL (`api/config.php`, `api/availability.php`, `api/reservations.php`, `api/auth.php`, `api/dashboard.php`, `api/schedules.php`, `api/admin_reservations.php`, `api/settings.php`, `api/public_settings.php`).
- Base de datos: `database/schema.sql` (tablas + horarios Lun–Vie 14–20h) + `database/settings.sql` (tabla `settings` + defaults). Admin inicial: `ADMIN_EMAIL=... ADMIN_PASS=... php database/create_admin.php`.
- Deploy: Railway en 1 servicio (web + API + MySQL, ver **Deploy en Railway**); alternativa legacy: GitHub Pages (solo estático) + hosting PHP aparte.

## Estado del MVP de reservas (funcionando end-to-end)

- **Reserva pública** (`#reservas`): día (próximos 5 hábiles Lun–Vie) → horario con cupos reales desde MySQL → datos → código `VS-XXXXXXXX` + detalle de la reserva en pantalla.
- **Panel admin** (`#/admin`, link en el footer): resumen, días/horarios/cupos, reservas (filtros, cambio de estado, WhatsApp clickeable) y configuración del sitio. Login por sesión PHP.
- **Validaciones backend**: fecha real/no pasada/máx 60 días, `weekday` del slot = día pedido, sin duplicados (mismo whatsapp+slot+fecha), sin sobrecupo (transacción + `FOR UPDATE`), whatsapp 8–15 dígitos, email opcional válido, reintento de código ante colisión. Todo cambio admin queda en `audit_logs`.

## Horarios oficiales (lunes a viernes · 3 bloques)

| Bloque | Horario | Cupos por bloque |
| --- | --- | --- |
| Bloque 1 | 14:00 a 16:00 Hrs. | 14 |
| Bloque 2 | 16:00 a 18:00 Hrs. | 14 |
| Bloque 3 | 18:00 a 20:00 Hrs. | 12 |
| **Total del día** | 14:00 – 20:00 Hrs. | **40** |

- Los bloques viven en `schedule_templates` (Lun–Vie → `weekday` 1–5) + `time_slots` (14:00 / 16:00 / 18:00) y se cargan con `database/schema.sql`.
- Los nombres ("Bloque 1/2/3") y los rangos se derivan de la hora de inicio en `src/blocks.js`: web pública, panel admin y confirmación usan la misma fuente. Un horario fuera de esos 3 se muestra solo con su rango.
- **Calendario**: se muestra el mes completo en 5 columnas (lun–vie) con los cupos de cada día (usados/total + barra). Arranca en `booking_start_date` (por defecto **2026-10-01**, "a partir de octubre") y llega 60 días adelante, con navegación ‹ › por mes. Al elegir un día se cargan sus 3 bloques. El backend rechaza cualquier fecha anterior al inicio (`422 Las reservas comienzan el 01-10-2026`).
- **Responsive**: en móvil (≤760px) se ven siempre **los 5 días de la semana y los 3 bloques en pantalla** (grilla de 5 y de 3 columnas, sin scroll horizontal), con calendario compacto; ≤400px reduce tipografía y celdas. Verificado a 360, 390 y 1440 px.
- Cambiar cupos, horarios o apagar un bloque: panel admin → **Días y horarios** (sin deploy). Pausar todo: **Config → Reservas habilitadas** (`booking_enabled`).

## Arranque rápido (local, idéntico a producción)

Requiere Docker. Un contenedor sirve web + API + MySQL 8:

```bash
docker compose up --build     # http://localhost:8090 · admin: demo@vinastage.cl / demo1234
```

Al arrancar el contenedor (`docker/entrypoint.sh`):

1. espera a MySQL y aplica `database/schema.sql` + `database/settings.sql` (`database/migrate.php`, idempotente);
2. crea/actualiza el admin con `ADMIN_EMAIL` / `ADMIN_PASS`;
3. con `SEED_DEMO=1` carga reservas de ejemplo si `reservations` está vacía;
4. sirve `dist/` + `/api/*.php` en el mismo origen (`router.php`), sin CORS y con cookies `httpOnly`/`SameSite`.

## Demo pública temporal (sin costo, sin cuenta)

```bash
./scripts/demo-publica.sh     # imprime una URL https://…trycloudflare.com
```

Túnel rápido de Cloudflare hacia tu máquina: vive mientras el Mac esté encendido y el contenedor arriba. Ideal para mostrar el MVP antes de publicar.

## Deploy en Railway

Un servicio (Dockerfile multi-stage: build de Vue + PHP 8.2 con `pdo_mysql`) y el plugin MySQL del proyecto:

```bash
./scripts/railway-deploy.sh   # proyecto + MySQL + servicio + variables + dominio + deploy
```

Qué hace el script: `railway init` → `railway add --database mysql` → `railway add --service app` → setea `DB_HOST=${{MySQL.MYSQLHOST}}`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (referencias al plugin), `ADMIN_EMAIL`, `ADMIN_PASS`, `SEED_DEMO`, `PORT=8080` → crea el dominio público en el puerto 8080 → `railway up` (build con el Dockerfile).

- Healthcheck: `railway.json` apunta a `/api/health.php` (verifica app + DB) con 300 s de gracia para la primera migración.
- El contenedor se auto-inicializa: migraciones + admin + seed en cada arranque (todo idempotente).
- Logs: `railway logs --service app` · URL: `railway domain list --service app`.
- **Nota de plan**: si Railway responde `Free plan resource provision limit exceeded`, hay que liberar recursos del workspace o pasar a un plan con cupo (Hobby). El deploy en sí no cambia.

Variables de entorno del contenedor (ver `.env.example`):

| Variable | Uso |
| --- | --- |
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS` | MySQL propio |
| `MYSQLHOST` `MYSQLPORT` `MYSQLDATABASE` `MYSQLUSER` `MYSQLPASSWORD` / `MYSQL_URL` | las lee `api/db_env.php` automáticamente (Railway/otros) |
| `ADMIN_EMAIL` `ADMIN_PASS` | crea/actualiza el admin al arrancar |
| `SEED_DEMO=1` | reservas de ejemplo la primera vez |
| `booking_start_date` (setting en DB) | primera fecha reservable del calendario (por defecto `2026-10-01`); editable en Admin → Config |
| `ADMIN_ORIGIN` | solo si el frontend vive en otro dominio (cookies cross-site) |
| `PORT` | puerto del contenedor (Railway lo inyecta) |

> Cambia `ADMIN_PASS` antes de publicar: `railway variable set ADMIN_PASS=... --service app` y redeploy.

## Deploy en Vercel

Vercel sirve **solo el frontend** (SPA estática): `vercel.json` construye con Vite y publica `dist/`. Vercel no ejecuta PHP, así que `api/` + MySQL siguen viviendo en Railway (o en un hosting PHP aparte).

```bash
./scripts/vercel-deploy.sh        # link del proyecto + variables de build + deploy de producción
```

Dos modos:

| Modo | Variables | Qué pasa |
| --- | --- | --- |
| Demo (por defecto) | `VITE_DEMO_MODE=1` | La web usa los datos locales de `src/demo.js` y muestra el aviso «modo demostración». Perfecto para revisar diseño en un link (incluye el login demo del panel). Las reservas no se guardan. |
| Frontend + API real | `VITE_DEMO_MODE=0` y `VITE_API_BASE_URL=https://<app>.up.railway.app/api` | Las reservas y el panel admin hablan con el backend PHP/MySQL. |

En el modo real web y API quedan en dominios distintos, así que en el backend hay que setear:

- `ADMIN_ORIGIN=https://<proyecto>.vercel.app` (CORS con credenciales). `api/config.php` detecta el cross-site y emite la cookie de sesión con `SameSite=None; Secure`; con `Lax` el navegador no enviaría la cookie en el `fetch` con `credentials:'include'` y el login del admin no persistiría.
- Aun así, con cookies de terceros bloqueadas (Safari/iOS, modo estricto) la sesión del admin puede no mantenerse. Si el admin es crítico, deja web + API en el mismo dominio (Railway) y usa Vercel para previews del frontend.

Qué hace `vercel.json` además del build: caché inmutable (`max-age=31536000, immutable`) para `/assets/*`, porque cada build de Vite genera hashes nuevos; `index.html` siempre revalidado (un deploy nuevo se ve al instante); `/flyers/*` con 1 día + `stale-while-revalidate`; headers de seguridad (`nosniff`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`, HSTS); y el rewrite `/(.*)` → `/index.html` al final, que es el fallback del SPA (usa hash routing). No se define `Content-Security-Policy` porque `VITE_API_BASE_URL` es configurable y el mapa de contacto es un iframe externo: agrégala con `connect-src`/`frame-src` explícitos si la quieres.

Imágenes: los flyers de `public/flyers/` se publican en WebP ajustados al tamaño real en que se ven (los originales quedan solo en `design/flyers-origen/originales/`, fuera de git y fuera del deploy). El logo se convierte a RGB porque se renderiza sobre el fondo negro de la cabecera; no usa transparencia. Para regenerarlos o añadir flyers nuevos:

```bash
cd public/flyers
# tarjetas del carrusel (se ven a 300×425 px): 820 px de ancho alcanza para pantallas retina
cwebp -q 80 -m 6 -metadata none -resize 820 0 evento.jpg -o evento.webp
# logo del header (se ve a 50 px de alto; se renderiza sobre fondo negro)
cwebp -q 90 -m 6 -metadata none -resize 400 0 logo.png -o logo.webp
# banner de la promo (tiene texto: calidad alta y sin resize)
cwebp -q 85 -m 6 -sharp_yuv -metadata none banner.png -o banner.webp
```

Usa nombres sin espacios (así se evita el `logo%20stage.png` que complicaba la URL) y cambia la extensión a `.webp` en `src/App.vue`. `favicon.png` sigue en PNG: son 41 KB y cuantizarlo solo ahorraba 8 KB degradando el logo.

> Opción «todo en Vercel»: los endpoints PHP pueden correr como funciones con el runtime comunitario `vercel-php@0.9.0` (`"functions": { "api/*.php": { "runtime": "vercel-php@0.9.0" } }`). Requiere MySQL externo, quitar `api` de `.vercelignore` y pasar las sesiones en fichero a sesiones en base de datos (en serverless no hay disco compartido), así que no es la ruta recomendada para este MVP.

## Estructura

```
api/                    endpoints PHP (config, db_env, availability, availability_range, reservations, auth, dashboard, schedules, admin_reservations, settings, public_settings, health)
database/               schema.sql, settings.sql, migrate.php, seed_demo.php, create_admin.php
src/                    App.vue (landing + reservas), AdminPanel.vue, demo.js, styles.css
docker/entrypoint.sh    migra + crea admin + seed + sirve
router.php              router del servidor embebido (estáticos de dist/, /api whitelist, fallback SPA)
Dockerfile              imagen única web + api
docker-compose.yml      stack local (app + MySQL 8)
railway.json            build DOCKERFILE + healthcheck + restarts
vercel.json             frontend estático en Vercel (SPA + caché + headers)
.vercelignore           qué no se sube a Vercel (api/, database/, docker/, design/)
scripts/                railway-deploy.sh, vercel-deploy.sh, demo-publica.sh
```


## API

- `GET /api/availability.php?date=YYYY-MM-DD` → `{ date, slots: [{ slot_id, start_time, end_time, capacity, reserved, available }] }`
- `GET /api/availability_range.php?from=YYYY-MM-DD&to=YYYY-MM-DD` (máx 62 días) → `{ today, starts_at, max_date, days: [{ date, weekday, slots, total, used, available, bookable }] }` — alimenta el calendario con 1 request por mes.
- `POST /api/reservations.php` body `{ date, slotId, firstName, lastName, whatsapp, email?, whatsappConsent? }` → `201 { reservation: { id, reservation_code, status } }`
- Panel admin en `#/admin` (`src/AdminPanel.vue`, link en footer): login por sesión PHP (`POST /api/auth.php?action=login`), dashboard, días/horarios/cupos (`/api/schedules.php`), reservas y estados (`/api/admin_reservations.php`), config del sitio (`/api/settings.php`, público en `/api/public_settings.php`). Todo cambio admin queda en `audit_logs`.

Validaciones backend: fecha `YYYY-MM-DD` real, no pasada, máx 60 días, `weekday` del slot = día pedido (1=Lun…7=Dom), sin duplicados (mismo whatsapp+slot+fecha), sin sobrecupo (transacción + `FOR UPDATE`), whatsapp `8–15 dígitos`, email opcional válido, reintento de código `VS-XXXXXXXX` ante colisión.

## Desarrollo local

1. `npm install`
2. `npm run dev` (Vite en `:5173`, `/api` proxea a `http://localhost:8080` — ver `vite.config.js`)
3. Servir el PHP: `php -S localhost:8080 -t dist router.php` (web + API) o solo la API con `php -S localhost:8080 -t api`; prepara la DB con `php database/migrate.php` (equivale a importar `database/schema.sql` + `database/settings.sql`)
4. Crear/actualizar admin: `ADMIN_EMAIL=demo@vinastage.cl ADMIN_PASS=demo1234 php database/create_admin.php`, entrar en `http://localhost:5173/#/admin`
4. Variables: copiar `.env.example` → `.env` (`DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS`, `VITE_API_BASE_URL`)
5. Verificar la cookie de sesión (Lax en mismo dominio, None en cross-site): `php scripts/test-session-cookie.php`

Sin backend la UI cae a datos `demo` y muestra el aviso “modo demostración”.

## Producción

Opción recomendada (1 deploy, 1 dominio): **Railway** con el `Dockerfile` + `scripts/railway-deploy.sh` (ver arriba). El contenedor sirve la web y la API juntas, así que `VITE_API_BASE_URL=/api` y la sesión del admin funcionan sin CORS.

Alternativa **Vercel** (frontend estático, ideal para mostrar el diseño): ver «Deploy en Vercel». En modo demo no necesita backend; con API real apunta a la URL de Railway y requiere `ADMIN_ORIGIN` + cookies `SameSite=None`.

Opción legacy (frontend estático + hosting PHP aparte):

1. `npm run build` → `dist/`
2. GitHub Pages publica `dist` (`.github/workflows/pages.yml` usa `npm ci`, requiere `package-lock.json` trackeado)
3. Subir `api/` + `database/` a hosting PHP + MySQL y ejecutar `php database/migrate.php` + `php database/create_admin.php`
4. Setear `VITE_API_BASE_URL=https://tu-dominio.cl/api` en el build de Pages (secret/env del workflow o `.env.production`)
5. Para que el login admin funcione cross-domain: `ADMIN_ORIGIN=https://tu-usuario.github.io` en el backend PHP (cookies `credentials:include`). Si frontend y API comparten dominio, no se necesita.

> Nota: Pages es estático, no ejecuta PHP. Si `VITE_API_BASE_URL=/api` en Pages, las reservas quedan en modo demo.

