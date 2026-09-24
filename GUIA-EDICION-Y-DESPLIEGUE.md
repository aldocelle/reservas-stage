# Guía de edición, puesta en marcha local y despliegue en Vercel

Esta guía explica cómo mantener el contenido de Viña Stage, ejecutar el proyecto localmente y publicar el frontend en Vercel.

> **Importante:** Vercel publica solamente el frontend Vue/Vite. No ejecuta PHP ni MySQL. La API y la base de datos deben estar desplegadas en Railway u otro hosting PHP/MySQL.

## 1. Dónde se edita el contenido

### Textos y contenido visual

Los textos principales de la landing están en:

- `/Users/aldocelle/Documents/aldocelleweb/WEBSITES/EXPRESSWEB/ExpressWeb/reservas stage/reservas-stage/src/App.vue`
  - Nombre del lugar, textos del hero, textos de reservas, cartelera, contacto y pie de página.
  - El array `events` contiene los textos, tipos, imágenes y colores de las tarjetas de eventos.
  - Los enlaces a Instagram, Facebook, compras de entradas y correo están en el mismo archivo.

Para cambiar textos visibles, busca una frase en `src/App.vue` y reemplázala respetando el HTML/Vue existente. No cambies llaves, etiquetas `<section>`, atributos `class` ni expresiones `v-if` salvo que quieras modificar comportamiento.

### Configuración editable desde el panel

En `#/admin` → **Config** se pueden cambiar, sin desplegar:

- Nombre del sitio.
- Dirección.
- WhatsApp de contacto.
- Instagram.
- Fecha inicial del calendario.
- Activar o desactivar reservas.
- Aviso visible en reservas.

La configuración se guarda en la base de datos, no en el código. El backend expone esos datos mediante `api/public_settings.php` y `api/settings.php`.

### Imágenes y flyers

Las imágenes públicas se encuentran en:

- `/Users/aldocelle/Documents/aldocelleweb/WEBSITES/EXPRESSWEB/ExpressWeb/reservas stage/reservas-stage/public/hero/`
- `/Users/aldocelle/Documents/aldocelleweb/WEBSITES/EXPRESSWEB/ExpressWeb/reservas stage/reservas-stage/public/flyers/`

Recomendaciones:

1. Usa WebP para imágenes web.
2. Mantén el nombre del archivo o actualiza también la referencia en `src/App.vue`.
3. Conserva `width` y `height` para evitar saltos de layout.
4. No subas Secrets, `.env` ni archivos que no sean públicos.
5. La imagen principal del hero debe seguir siendo prioritaria; las imágenes inferiores pueden usar `loading="lazy"`.

### Horarios y capacidad

Los bloques oficiales y sus nombres están en:

- `/Users/aldocelle/Documents/aldocelleweb/WEBSITES/EXPRESSWEB/ExpressWeb/reservas stage/reservas-stage/src/blocks.js`
- `/Users/aldocelle/Documents/aldocelleweb/WEBSITES/EXPRESSWEB/ExpressWeb/reservas stage/reservas-stage/database/schema.sql`

La capacidad real de los horarios y la disponibilidad se administran desde el panel admin → **Días y horarios**. Si cambias la estructura de la base de datos, usa las migraciones y prueba localmente antes de desplegar.

## 2. Puesta en marcha local

### Opción recomendada: Docker

Requiere Docker Desktop o Docker Engine con Compose. Desde la raíz del proyecto:

```bash
docker compose up --build
```

Abre:

- Web: `http://localhost:8090`
- Panel admin: `http://localhost:8090/#/admin`

El arranque del contenedor espera MySQL, aplica el esquema y migraciones, crea o actualiza el administrador y sirve el frontend y la API PHP desde el mismo origen.

Para detener el stack:

```bash
docker compose down
```

Para detenerlo y borrar también los volúmenes locales:

```bash
docker compose down -v
```

La segunda opción elimina los datos locales de MySQL.

### Opción sin Docker

Requiere Node.js, PHP 8 con `pdo_mysql` y MySQL 8.

1. Crear las variables locales:

```bash
cp .env.example .env
```

2. Completar `.env` con las credenciales de MySQL, `ADMIN_EMAIL` y `ADMIN_PASS`.

3. Instalar dependencias:

```bash
npm install
```

4. Preparar la base de datos con las migraciones o importando los SQL de `database/`.

5. En una terminal, servir la aplicación PHP y el frontend construido:

```bash
npm run build
php -S localhost:8080 -t dist router.php
```

6. En otra terminal, iniciar Vite para desarrollo:

```bash
npm run dev
```

La URL de desarrollo es `http://localhost:5173`. El proxy `/api` apunta a `http://localhost:8080`.

### Variables importantes

Copia `.env.example` a `.env`; nunca subas `.env` a GitHub ni a Vercel.

| Variable | Uso |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexión MySQL del backend. |
| `ADMIN_EMAIL`, `ADMIN_PASS` | Administrador inicial. Cambia la contraseña antes de publicar. |
| `ADMIN_ORIGIN` | Origen exacto del frontend si la API está en otro dominio. |
| `VITE_API_BASE_URL` | URL pública de la API. En Vercel debe ser la URL de Railway terminada en `/api`. |
| `VITE_DEMO_MODE` | `0` para backend real. Vercel no puede ejecutar PHP. |

## 3. Validar antes de publicar

```bash
npm test
npm run build
git diff --check
```

Prueba además el login, calendario, creación de reserva, cambio de estado, guardado de configuración y navegación móvil.

## 4. Publicar el frontend en Vercel

### Requisitos

- Cuenta de Vercel.
- CLI instalada o disponible con `npx vercel`.
- Sesión iniciada en Vercel.
- Proyecto Vercel vinculado. Este repositorio ya está vinculado a `reservas-stage`.

Comprueba la sesión:

```bash
vercel whoami
```

### Despliegue de producción

El script existente configura las variables de Vercel, descarga la configuración y publica la versión de producción:

```bash
VITE_DEMO_MODE=1 VITE_API_BASE_URL=/api ./scripts/vercel-deploy.sh
```

`VITE_DEMO_MODE=1` publica la interfaz para revisión visual, pero las reservas no se guardan porque no hay backend PHP en Vercel.

### Conectar la API real de Railway

La URL debe ser la del servicio PHP, no la URL de Vercel:

```bash
VITE_DEMO_MODE=0 \
VITE_API_BASE_URL=https://TU-SERVICIO.up.railway.app/api \
ADMIN_ORIGIN=https://reservas-stage.vercel.app \
./scripts/vercel-deploy.sh
```

En el backend Railway también debe existir `ADMIN_ORIGIN=https://reservas-stage.vercel.app`. Después de cambiar una variable, Vite debe volver a construir la aplicación.

### Deploy manual alternativo

```bash
vercel pull --yes --environment=production
vercel deploy --prod --yes
```

Si el proyecto no está vinculado, ejecuta `vercel link --yes`. Usa el mismo nombre de proyecto para evitar crear uno nuevo por error.

### Verificar el deploy

```bash
vercel ls reservas-stage
```

Abre:

- Producción: `https://reservas-stage.vercel.app`
- Admin: `https://reservas-stage.vercel.app/#/admin`
- Preview: la URL que entrega `vercel deploy` sin `--prod`

## 5. Problemas frecuentes

### La web carga, pero no hay reservas

Vercel debe apuntar a la URL de Railway y usar `VITE_DEMO_MODE=0`. Vercel no ejecuta los archivos PHP; `/api` en Vercel no es un backend válido.

### El login admin no se mantiene

La web y la API están en dominios diferentes. Configura `ADMIN_ORIGIN` exactamente con el dominio de Vercel, incluyendo `https://`, y verifica que la API tenga HTTPS. Para producción crítica, conviene servir web y API en el mismo dominio.

### La imagen no aparece

Comprueba que el archivo exista en `public/`, que la ruta empiece con `/` y que el nombre coincida exactamente.

### La aplicación muestra cambios antiguos

Ejecuta `npm run build` y despliega otra vez; Vercel sirve `dist/`, no `src/` directamente.

### Ver variables y logs

```bash
vercel env ls
vercel logs reservas-stage
```

No compartas capturas ni salidas que muestren tokens, contraseñas, `.env` o valores cifrados de secretos.
