#!/usr/bin/env bash
# Deploy completo del MVP de reservas en Railway (1 servicio = web + API + MySQL).
# Requiere: railway CLI logueado y plan con recursos disponibles (Hobby o Trial con cupo libre).
#
#   ./scripts/railway-deploy.sh
#
# Variables opcionales:
#   PROJECT=reservas-stage  SERVICE=app  WORKSPACE="Aldo Celle's Projects"
#   ADMIN_EMAIL=demo@vinastage.cl  ADMIN_PASS=demo1234  SEED_DEMO=1  PORT=8080
set -euo pipefail
cd "$(dirname "$0")/.."

PROJECT="${PROJECT:-reservas-stage}"
SERVICE="${SERVICE:-app}"
PORT="${PORT:-8080}"
ADMIN_EMAIL="${ADMIN_EMAIL:-demo@vinastage.cl}"
ADMIN_PASS="${ADMIN_PASS:-demo1234}"
SEED_DEMO="${SEED_DEMO:-1}"
DB_SERVICE="${DB_SERVICE:-MySQL}"
WORKSPACE_ARG=()
if [ -n "${WORKSPACE:-}" ]; then WORKSPACE_ARG=(--workspace "$WORKSPACE"); fi

echo "== 1/6 proyecto $PROJECT"
if railway status >/dev/null 2>&1; then
  echo "   ya hay un proyecto linkeado en este directorio, se reutiliza"
else
  railway init --name "$PROJECT" "${WORKSPACE_ARG[@]}"
fi

echo "== 2/6 base de datos MySQL"
if railway status --json 2>/dev/null | grep -q "\"name\": \"$DB_SERVICE\""; then
  echo "   $DB_SERVICE ya existe"
else
  railway add --database mysql
fi

echo "== 3/6 servicio $SERVICE"
if railway status --json 2>/dev/null | grep -q "\"name\": \"$SERVICE\""; then
  echo "   $SERVICE ya existe"
else
  railway add --service "$SERVICE"
fi

echo "== 4/6 variables (referencias al plugin MySQL + admin inicial)"
railway variable set --service "$SERVICE" --skip-deploys \
  "DB_HOST=\${{$DB_SERVICE.MYSQLHOST}}" \
  "DB_PORT=\${{$DB_SERVICE.MYSQLPORT}}" \
  "DB_NAME=\${{$DB_SERVICE.MYSQLDATABASE}}" \
  "DB_USER=\${{$DB_SERVICE.MYSQLUSER}}" \
  "DB_PASS=\${{$DB_SERVICE.MYSQLPASSWORD}}" \
  "ADMIN_EMAIL=$ADMIN_EMAIL" \
  "ADMIN_PASS=$ADMIN_PASS" \
  "SEED_DEMO=$SEED_DEMO" \
  "PORT=$PORT"

echo "== 5/6 dominio público (puerto $PORT)"
railway domain --service "$SERVICE" --port "$PORT" || echo "   (el dominio ya existía)"

echo "== 6/6 build + deploy (Dockerfile)"
railway up --service "$SERVICE" --ci

echo
echo "Listo. Revisa el estado con:  railway status"
echo "URL pública:                  railway domain list --service $SERVICE"
echo "Logs:                         railway logs --service $SERVICE | head -30"
echo "Admin:                        <URL>/  →  footer → Admin  ($ADMIN_EMAIL)"
