#!/bin/sh
# Arranque del contenedor: prepara la base de datos y sirve web + api.
set -eu
PORT="${PORT:-8080}"
export PORT
echo "[entrypoint] DB=${DB_HOST:-${MYSQLHOST:-localhost}}:${DB_PORT:-${MYSQLPORT:-3306}}/${DB_NAME:-${MYSQLDATABASE:-reservas_stage}} usuario=${DB_USER:-${MYSQLUSER:-root}}"
echo "[entrypoint] esperando la base de datos y aplicando esquema…"
if ! php /app/database/migrate.php; then
  echo "[entrypoint] ERROR: no fue posible preparar la base de datos" >&2
  exit 1
fi
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASS:-}" ]; then
  echo "[entrypoint] creando/actualizando admin ${ADMIN_EMAIL}…"
  ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASS="$ADMIN_PASS" php /app/database/create_admin.php || echo "[entrypoint] aviso: admin no creado"
fi
if [ "${SEED_DEMO:-0}" = "1" ]; then
  echo "[entrypoint] cargando reservas de ejemplo (SEED_DEMO=1)…"
  php /app/database/seed_demo.php || echo "[entrypoint] aviso: seed demo no aplicado"
fi
echo "[entrypoint] escuchando en 0.0.0.0:${PORT}"
exec php \
  -d session.cookie_httponly=1 \
  -d session.cookie_samesite=Lax \
  -d session.save_path=/tmp \
  -d expose_php=0 \
  -d display_errors=0 \
  -d log_errors=1 \
  -d error_log=/dev/stderr \
  -S "0.0.0.0:${PORT}" -t /app/dist /app/router.php
