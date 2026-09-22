# Viña Stage · Sistema de Reservas

Sistema full-stack para Viña Stage. La interfaz mantiene el diseño de la referencia y ahora consume una API real con PostgreSQL.

## Arquitectura

- Frontend: HTML + CSS + JavaScript.
- Backend: Node.js + Express.
- Base de datos: PostgreSQL.
- Autenticación: JWT + bcrypt.
- Seguridad: Helmet, CORS, rate limiting y validación Zod.
- Concurrencia: transacciones y bloqueo del horario para evitar sobre-reservas.
- Administración: dashboard, reservas, estados, cupos y horarios.
- Auditoría: registro de cambios realizados por administradores.

## API pública

- GET /api/health
- GET /api/availability?date=YYYY-MM-DD
- POST /api/reservations
- GET /api/reservations/code/:code
- PATCH /api/reservations/:id/cancel

## API administrativa

- POST /api/auth/login
- GET /api/admin/dashboard
- GET /api/admin/schedules
- PATCH /api/admin/schedules/:id
- PATCH /api/admin/slots/:id
- GET /api/reservations
- PATCH /api/reservations/:id/status

## Instalación

1. Crear una base PostgreSQL.
2. Copiar .env.example a .env.
3. Configurar DATABASE_URL y JWT_SECRET.
4. Ejecutar npm install.
5. Ejecutar npm run db:init.
6. Ejecutar npm run admin:create.
7. Iniciar con npm run dev o npm start.

La aplicación Express sirve simultáneamente el frontend y la API.

## Integraciones preparadas

El archivo .env.example contempla WhatsApp Cloud API y Google Calendar. Las credenciales no se guardan en GitHub. La integración efectiva de esos servicios requiere sus credenciales y configuración de producción.
