# Informe QA · Viña Stage

**Fecha:** 24 de septiembre de 2026
**Frontend:** https://reservas-stage.vercel.app
**Backend:** https://app-production-4011.up.railway.app
**Alcance:** frontend Vue/Vite, API PHP, MySQL, despliegue, integración, seguridad básica, rendimiento estático y flujos públicos.

## Resumen ejecutivo

**Estado general: APROBADO CON OBSERVACIONES**

La integración principal está funcionando: Vercel sirve el frontend y Railway sirve PHP/MySQL. Los endpoints públicos responden JSON y la disponibilidad se carga desde la URL real de Railway. El healthcheck de Railway confirma aplicación y base de datos.

Hay dos acciones importantes antes de considerar el sistema totalmente listo para producción:

1. Confirmar la capacidad comercial real por bloque: la base actual responde 20 cupos por bloque y 60 por día, pero parte de la documentación indica 14/14/12 y 40 diarios.
2. Hacer una prueba de navegador real de la reserva y del login admin desde el dominio Vercel, incluyendo la persistencia de la cookie cross-site. Los endpoints y CORS fueron verificados por HTTP, pero no se usó un navegador gráfico en esta QA.

## Entornos verificados

| Componente | URL/estado | Resultado |
|---|---|---|
| Vercel producción | https://reservas-stage.vercel.app | HTTP 200, Ready |
| Railway servicio | https://app-production-4011.up.railway.app | Online |
| Railway MySQL | Railway volume | Online |
| API healthcheck | `/api/health.php` | 200, `ok: true`, `db: true` |
| Build frontend | Vite | Correcto |
| Tests frontend | Node test runner | 8/8 passing |
| Diff check | Git | Sin errores |

## Pruebas ejecutadas

### Build y tests

```text
npm run build     PASS
npm test          PASS · 8 tests
git diff --check  PASS
```

Bundle generado:

- JavaScript inicial: aproximadamente 104 kB sin comprimir.
- JavaScript inicial: aproximadamente 40.6 kB gzip.
- CSS: aproximadamente 81.4 kB sin comprimir.
- CSS: aproximadamente 16.1 kB gzip.
- Admin: chunk separado de aproximadamente 23.4 kB.

El panel admin se carga como chunk dinámico y no se incluye en el bundle inicial de la landing.

### Railway / API

| Endpoint | Resultado |
|---|---|
| `GET /api/health.php` | 200 JSON; DB conectada |
| `GET /api/public_settings.php` | 200 JSON; configuración pública disponible |
| `GET /api/availability_range.php` | 200 JSON; devuelve días, cupos y `bookable` |
| `GET /api/availability.php?date=2026-10-08` | 200 JSON; devuelve 3 bloques |
| `GET /api/auth.php?action=status` | 200 JSON; no autenticado correctamente |
| `POST /api/reservations.php` sin datos | 422 JSON; validación rechaza request |
| `OPTIONS /api/auth.php?action=login` | 200; CORS preflight correcto |

### CORS y cookies

- `Access-Control-Allow-Origin: https://reservas-stage.vercel.app` confirmado.
- `Access-Control-Allow-Credentials: true` confirmado.
- La cookie de sesión usa `Secure`, `HttpOnly` y `SameSite=None`, adecuado para el escenario cross-site actual.
- El origen permitido está limitado a Vercel; no se observó `*` junto con credenciales en Railway.

### Vercel

- HTML raíz: 200.
- JavaScript publicado: 200.
- CSS publicado: 200.
- Hero: 200 `image/jpeg`.
- Logo WebP: 200 `image/webp`.
- Favicon: 200 `image/png`.
- El bundle publicado contiene `https://app-production-4011.up.railway.app/api`.
- El bundle ya no utiliza `/api` como URL real del backend.

### Seguridad estática

Headers confirmados en Vercel:

- `Strict-Transport-Security`.
- `X-Content-Type-Options: nosniff`.
- `X-Frame-Options: SAMEORIGIN`.
- `Referrer-Policy: strict-origin-when-cross-origin`.
- `Permissions-Policy` restringiendo geolocalización, micrófono y cámara.

No se observó `innerHTML`, `eval`, `localStorage` ni uso evidente de HTML sin escapar en el frontend auditado.

## Hallazgos

### QA-001 · P2 · Capacidad comercial inconsistente

**Evidencia:**

- `GET /api/availability.php?date=2026-10-08` devuelve 20 cupos por cada bloque.
- El rango devuelve `total: 60` por día.


- README y textos históricos mencionan 14/14/12 y 40 diarios.

**Impacto:** el usuario puede ver una disponibilidad y una capacidad distinta a la regla comercial definida. Puede provocar reservas rechazadas o una promesa comercial incorrecta.

**Acción recomendada:** confirmar la capacidad real con el negocio, actualizar documentación y aplicar la capacidad definitiva en la base de datos.

**Estado:** pendiente de confirmación funcional.

## Accesibilidad y performance

### Accesibilidad estática positiva

- Imágenes con `alt` y dimensiones.
- Labels asociados a campos.
- Calendario con botones, estados y `aria-pressed`.
- Confirmaciones con `role="status"` y errores con `role="alert"`.
- Enlaces externos con `rel="noreferrer"`.
- CSS contempla `prefers-reduced-motion`.

### Pendiente de prueba manual

- Contraste y foco visible.
- Lectores de pantalla.
- Zoom 200% y viewports de 320/360/390 px.
- Menú móvil, orientación y Safari/iOS.

### Performance positiva

- Hero prioritario y con dimensiones reservadas.
- Imágenes inferiores lazy y decodificación asíncrona.
- Admin separado mediante import dinámico.
- Fuentes de Google reducidas a pesos usados.
- CSS con `contain` en regiones largas.
- Caché de assets y flyers definida.

### Pendiente

Ejecutar Lighthouse y medir LCP, INP y CLS en móvil y desktop con navegador real.

## Flujo de despliegue actual

```text
Vercel
  └── frontend Vue/Vite → dist/
        │
        └── fetch HTTPS
              ↓
Railway
  └── PHP API + router
        │
        └── MySQL
```

Para el futuro hosting con PHP/MySQL, cambiar `VITE_API_BASE_URL` a la URL pública de la API y volver a desplegar el frontend.

## Checklist de salida

- [x] Build Vite correcto.
- [x] Tests frontend 8/8.
- [x] Deploy Vercel Ready.
- [x] Railway servicio Online.
- [x] MySQL Online.
- [x] Healthcheck correcto.
- [x] API devuelve JSON.
- [x] CORS correcto.
- [x] Cookies cross-site configuradas.
- [x] Frontend publicado usa Railway.
- [x] Assets principales responden 200.
- [x] Headers básicos de seguridad presentes.
- [ ] Confirmar capacidad comercial 40 vs 60.
- [ ] Ejecutar prueba manual de reserva real.
- [ ] Ejecutar prueba manual de login admin en navegador.
- [ ] Ejecutar Lighthouse/responsive/accessibility real.
- [ ] Agregar CSP en hardening.

## Conclusión

El sistema está desplegado y la causa del error JSON de disponibilidad fue corregida: Vercel ya no es el backend; el frontend consume la API PHP de Railway. API, MySQL, CORS, healthcheck, build y tests están operativos.

Antes de entregarlo como producción comercial, confirmaría la capacidad real de cupos y completaría pruebas manuales de reserva/login en navegador, especialmente Safari/iOS por el uso actual de cookies cross-site.
