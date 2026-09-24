# Viña Stage · Reservas — imagen única: frontend estático + API PHP + MySQL
# syntax=docker/dockerfile:1

# ---------- 1) build del frontend (Vue 3 + Vite) ----------
FROM node:20-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY index.html vite.config.js ./
COPY src ./src
COPY public ./public
# VITE_DEMO_MODE=0 => la web habla con el backend real (usa 1 para build offline con datos locales)
ARG VITE_DEMO_MODE=0
ARG VITE_API_BASE_URL=/api
ENV VITE_DEMO_MODE=$VITE_DEMO_MODE VITE_API_BASE_URL=$VITE_API_BASE_URL
RUN npm run build

# ---------- 2) runtime: PHP 8 + PDO MySQL + estáticos ----------
FROM php:8.2-cli-alpine AS runtime
WORKDIR /app
RUN apk add --no-cache $PHPIZE_DEPS \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql \
 && apk del $PHPIZE_DEPS
COPY --from=build /app/dist ./dist
COPY api ./api
COPY database ./database
COPY router.php ./router.php
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && addgroup -S app && adduser -S app -G app \
 && chown -R app:app /app
ENV PORT=8080 \
    PHP_CLI_SERVER_WORKERS=4
EXPOSE 8080
USER app
CMD ["/usr/local/bin/entrypoint.sh"]
