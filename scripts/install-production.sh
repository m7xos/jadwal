#!/usr/bin/env bash
set -euo pipefail

# Lightweight Laravel installer (NO PHP/APACHE/MARIADB install)
# Run with:
#   sudo env DEPLOY_USER=deploy APP_USER=www-data APP_DIR=/var/www/jw-stable DOMAIN=example.com DB_NAME=... DB_USER=... DB_PASS='...' bash ./scripts/install-laravel-only.sh

APP_DIR="${APP_DIR:-/var/www/jadwal}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"

DOMAIN="${DOMAIN:-localhost}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"
CACHE_OPTIMIZE="${CACHE_OPTIMIZE:-1}"

if [[ $EUID -eq 0 ]]; then
  SUDO=""
else
  SUDO="sudo"
fi

command_exists() { command -v "$1" >/dev/null 2>&1; }

need_cmd() {
  if ! command_exists "$1"; then
    echo "ERROR: command not found: $1" >&2
    exit 1
  fi
}

echo "[0/6] Checking required commands..."
need_cmd php
need_cmd composer
need_cmd git

echo "[1/6] Fixing repository ownership (deploy) + git safe.directory..."
$SUDO chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}" || true
if [[ -d "${APP_DIR}/.git" ]]; then
  $SUDO -u "${DEPLOY_USER}" git config --global --add safe.directory "${APP_DIR}" >/dev/null 2>&1 || true
fi

echo "[2/6] Preparing Laravel writable folders..."
$SUDO mkdir -p "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# storage/cache MUST be writable by web user
$SUDO chown -R "${APP_USER}:${APP_GROUP}" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
$SUDO chmod -R 2775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
$SUDO find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type f -exec chmod 664 {} \;

# Optional ACL so deploy + www-data both can write
if command_exists setfacl; then
  $SUDO setfacl -R -m "u:${APP_USER}:rwX" -m "u:${DEPLOY_USER}:rwX" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  $SUDO setfacl -dR -m "u:${APP_USER}:rwX" -m "u:${DEPLOY_USER}:rwX" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
fi

echo "[3/6] Creating .env (if missing) and injecting basic settings..."
if [[ ! -f "${APP_DIR}/.env" && -f "${APP_DIR}/.env.example" ]]; then
  cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
fi

$SUDO chown "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}/.env" 2>/dev/null || true
$SUDO chmod 664 "${APP_DIR}/.env" 2>/dev/null || true

set_env() {
  local key="$1"; local val="$2"
  [[ -z "$val" ]] && return 0
  if grep -qE "^${key}=" "${APP_DIR}/.env"; then
    sed -i "s|^${key}=.*|${key}=${val}|g" "${APP_DIR}/.env"
  else
    printf "\n%s=%s\n" "${key}" "${val}" >> "${APP_DIR}/.env"
  fi
}

set_env "APP_URL" "http://${DOMAIN}"
set_env "DB_HOST" "${DB_HOST}"
set_env "DB_PORT" "${DB_PORT}"
set_env "DB_DATABASE" "${DB_NAME}"
set_env "DB_USERNAME" "${DB_USER}"
if [[ -n "${DB_PASS}" ]]; then
  set_env "DB_PASSWORD" "\"${DB_PASS}\""
fi

echo "[4/6] Installing Composer dependencies (as ${DEPLOY_USER})..."
cd "${APP_DIR}"
$SUDO -u "${DEPLOY_USER}" composer install --no-dev --prefer-dist --optimize-autoloader

echo "[5/6] Running artisan (key + caches)..."
$SUDO -u "${DEPLOY_USER}" php artisan key:generate --force

if [[ "${RUN_MIGRATIONS}" == "1" ]]; then
  $SUDO -u "${DEPLOY_USER}" php artisan migrate --force || true
fi

if [[ "${CACHE_OPTIMIZE}" == "1" ]]; then
  $SUDO -u "${DEPLOY_USER}" php artisan config:cache || true
  $SUDO -u "${DEPLOY_USER}" php artisan route:cache || true
  $SUDO -u "${DEPLOY_USER}" php artisan view:cache  || true
fi

echo "[6/6] Final permission check..."
$SUDO -u "${APP_USER}" test -w "${APP_DIR}/storage" && echo "OK: ${APP_USER} can write storage"
$SUDO -u "${APP_USER}" test -w "${APP_DIR}/bootstrap/cache" && echo "OK: ${APP_USER} can write bootstrap/cache"

echo "Done. (No PHP/Apache/MariaDB installation performed.)"
