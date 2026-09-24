#!/usr/bin/env bash
# Deploy del frontend (Vue 3 + Vite) en Vercel — SPA estática.
# Vercel NO ejecuta PHP: aquí viven solo la web y sus assets.
#
#   ./scripts/vercel-deploy.sh                      # demo (datos locales, sin backend)
#   VITE_DEMO_MODE=0 VITE_API_BASE_URL=https://<app>.up.railway.app/api \
#     ./scripts/vercel-deploy.sh                    # frontend en Vercel + API en Railway
#
# Variables opcionales:
#   PROJECT=reservas-stage  VITE_DEMO_MODE=1  VITE_API_BASE_URL=/api
#   PREVIEW=1            → deploy de preview (no toca producción ni sus variables)
#   ENVIRONMENT=preview  → fuerza el entorno de Vercel a leer/escribir variables
#   ADMIN_ORIGIN=https://reservas-stage.vercel.app   # solo informativo (va en el backend PHP)
set -euo pipefail
cd "$(dirname "$0")/.."

PROJECT="${PROJECT:-reservas-stage}"
DEMO="${VITE_DEMO_MODE:-1}"
API_URL="${VITE_API_BASE_URL:-/api}"
PREVIEW="${PREVIEW:-0}"
# Un preview no debe escribir variables de producción: cada cosa en su entorno.
if [ -n "${ENVIRONMENT:-}" ]; then :; elif [ "$PREVIEW" = "1" ]; then ENVIRONMENT=preview; else ENVIRONMENT=production; fi

case "$DEMO" in
  0|1) ;;
  *) echo "VITE_DEMO_MODE debe ser 0 o 1 (recibido: $DEMO)"; exit 1 ;;
esac
if [ "$DEMO" = "0" ] && [ "$API_URL" = "/api" ]; then
  echo "VITE_DEMO_MODE=0 pide un backend real, pero VITE_API_BASE_URL=/api apunta a Vercel (no hay PHP ahí)."
  echo "Usa la URL de la API (p. ej. https://<app>.up.railway.app/api) o deja VITE_DEMO_MODE=1."
  exit 1
fi

echo "== 1/4 proyecto $PROJECT"
if [ -f .vercel/project.json ]; then
  echo "   ya hay un proyecto linkeado en este directorio, se reutiliza"
else
  vercel link --yes --project "$PROJECT"
fi

echo "== 2/4 variables de build del entorno ${ENVIRONMENT} (se inyectan en el build de Vite)"
if [ "$ENVIRONMENT" = "production" ]; then
  vercel env rm VITE_DEMO_MODE production --yes >/dev/null 2>&1 || true
  vercel env rm VITE_API_BASE_URL production --yes >/dev/null 2>&1 || true
  printf '%s' "$DEMO"     | vercel env add VITE_DEMO_MODE production
  printf '%s' "$API_URL"  | vercel env add VITE_API_BASE_URL production
else
  # En preview la CLI pide el branch de git con un prompt interactivo (no se puede dejar
  # "todos los branches" por pipe), así que el deploy usa los defaults del build: modo demo.
  echo "   preview sin VITE_* propias: el build queda en modo demo con VITE_API_BASE_URL=/api"
  echo "   ¿necesitas valores propios en preview? agrégalos en el dashboard (Environment=Preview) y redeploya"
fi
if [ "$DEMO" = "1" ]; then
  echo "   modo demo: la web muestra datos locales y el aviso «modo demostración»"
elif [ "$ENVIRONMENT" = "preview" ]; then
  echo "   ATENCIÓN: este preview se construirá igual en modo demo (sin VITE_* de preview)"
else
  echo "   modo real: API en $API_URL"
  echo "   recuerda en el backend PHP: ADMIN_ORIGIN=${ADMIN_ORIGIN:-} (cookies cross-site, SameSite=None)"
fi

echo "== 3/4 pull de la config del proyecto (${ENVIRONMENT})"
vercel pull --yes --environment="$ENVIRONMENT"

echo "== 4/4 deploy ($([ "$PREVIEW" = "1" ] && echo preview || echo producción))"
if [ "$PREVIEW" = "1" ]; then
  vercel deploy --yes
else
  vercel deploy --prod --yes
fi

echo
echo "Listo. URL y estado:"
vercel ls "$PROJECT"
echo "Dominios:        vercel domains ls  ·  vercel alias ls"
echo "Variables:       vercel env ls"
echo "Admin (si hay API real): <URL>/  →  footer → Admin"
