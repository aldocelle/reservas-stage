#!/usr/bin/env bash
# URL pública temporal del stack local (demo para el cliente) usando un túnel rápido de Cloudflare.
# No requiere cuenta. La URL vive mientras este Mac esté encendido, Docker arriba y el túnel activo.
#
#   ./scripts/demo-publica.sh          # levanta docker compose + túnel y muestra la URL
set -euo pipefail
cd "$(dirname "$0")/.."
PORT="${PORT:-8090}"

command -v cloudflared >/dev/null 2>&1 || { echo "Falta cloudflared (brew install cloudflared)"; exit 1; }

echo "== levantando stack local (docker compose)"
docker compose up -d --build

echo "== esperando la app en http://localhost:$PORT"
for i in $(seq 1 30); do
  if curl -fsS "http://localhost:$PORT/api/health.php" >/dev/null 2>&1; then break; fi
  sleep 2
done
curl -fsS "http://localhost:$PORT/api/health.php" >/dev/null || { echo "La app no respondió; revisa: docker compose logs app"; exit 1; }
echo "   ok"

echo "== abriendo túnel público"
LOG=/tmp/vs-tunnel.log
: > "$LOG"
nohup cloudflared tunnel --no-autoupdate --url "http://localhost:$PORT" > "$LOG" 2>&1 &
for i in $(seq 1 30); do
  URL=$(grep -Eo 'https://[a-z0-9-]+\.trycloudflare\.com' "$LOG" | head -1 || true)
  [ -n "${URL:-}" ] && break
  sleep 2
done
[ -n "${URL:-}" ] || { echo "No se pudo obtener la URL; ver $LOG"; exit 1; }

echo
echo "URL pública: $URL"
echo "Admin:       $URL/#/admin"
echo "Para cortar: pkill -f 'cloudflared tunnel --no-autoupdate --url'"
echo "Log del túnel: $LOG"
