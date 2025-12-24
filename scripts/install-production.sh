#!/usr/bin/env bash
set -euo pipefail

# Simple production installer for this Laravel app (Apache + PHP-FPM 8.4 + MariaDB).
# Tested on Ubuntu/Debian family with apt.
# Usage (run as root): APP_DIR=/var/www/jadwal DOMAIN=example.com DB_NAME=jadwal DB_USER=jadwal DB_PASS=secret ./scripts/install-production.sh

APP_DIR="${APP_DIR:-/var/www/jadwal}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
PHP_VERSION="${PHP_VERSION:-8.4}"
DOMAIN="${DOMAIN:-jadwal.local}"
DB_NAME="${DB_NAME:-jadwal}"
DB_USER="${DB_USER:-jadwal}"
DB_PASS="${DB_PASS:-jadwalpass}"
DB_ROOT_PASS="${DB_ROOT_PASS:-}"

if [[ $EUID -eq 0 ]]; then
  SUDO=""
else
  if ! command -v sudo >/dev/null 2>&1; then
    echo "sudo is required when not running as root." >&2
    exit 1
  fi
  SUDO="sudo"
fi

command_exists() { command -v "$1" >/dev/null 2>&1; }

ensure_sury_repo() {
  if ! apt-cache policy | grep -q "packages.sury.org/php"; then
    apt-get update -y
    apt-get install -y ca-certificates apt-transport-https lsb-release software-properties-common wget gnupg
    wget -qO- https://packages.sury.org/php/apt.gpg | tee /etc/apt/trusted.gpg.d/php.gpg >/dev/null
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" >/etc/apt/sources.list.d/php.list
  fi
}

install_packages() {
  ensure_sury_repo
  $SUDO apt-get update -y
  $SUDO apt-get install -y \
    apache2 \
    mariadb-server \
    php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-gd php${PHP_VERSION}-intl php${PHP_VERSION}-bcmath \
    unzip git curl acl
}

configure_apache() {
  $SUDO a2enmod proxy_fcgi setenvif rewrite headers >/dev/null
  $SUDO a2enconf "php${PHP_VERSION}-fpm" >/dev/null || true

  local vhost="/etc/apache2/sites-available/${DOMAIN}.conf"
  $SUDO tee "$vhost" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \\.php$>
        SetHandler "proxy:unix:/run/php/php${PHP_VERSION}-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN}-access.log combined
</VirtualHost>
EOF

  $SUDO a2ensite "${DOMAIN}.conf" >/dev/null
  $SUDO systemctl reload apache2
}

configure_db() {
  $SUDO systemctl enable --now mariadb
  local mysql_cmd="mysql"
  if [[ -n "$DB_ROOT_PASS" ]]; then
    mysql_cmd="mysql -uroot -p${DB_ROOT_PASS}"
  fi

  if [[ -n "$SUDO" ]]; then
    mysql_cmd="$SUDO $mysql_cmd"
  fi

  $mysql_cmd -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  $mysql_cmd -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
  $mysql_cmd -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
}

prepare_app() {
  $SUDO mkdir -p "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  $SUDO chown -R "${APP_USER}:${APP_GROUP}" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  $SUDO chmod -R ug+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

  if [[ ! -f "${APP_DIR}/.env" && -f "${APP_DIR}/.env.example" ]]; then
    $SUDO cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    $SUDO chmod 664 "${APP_DIR}/.env"
  fi

  # ACL: grant rwx to APP_USER (www-data) and deploy user on storage/cache, and rw on .env.
  if command_exists setfacl; then
    $SUDO setfacl -R -m "u:${APP_USER}:rwX" -m "u:${DEPLOY_USER}:rwX" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
    $SUDO setfacl -dR -m "u:${APP_USER}:rwX" -m "u:${DEPLOY_USER}:rwX" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
    if [[ -f "${APP_DIR}/.env" ]]; then
      $SUDO setfacl -m "u:${APP_USER}:rw" -m "u:${DEPLOY_USER}:rw" "${APP_DIR}/.env"
    fi
  fi
}

install_composer_deps() {
  if ! command_exists composer; then
    EXPECTED_SIGNATURE="$(curl -s https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
      >&2 echo 'ERROR: Invalid Composer installer signature'
      rm composer-setup.php
      exit 1
    fi
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm composer-setup.php
  fi

  cd "$APP_DIR"
  $SUDO -u "${APP_USER}" COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader
}

run_artisan() {
  cd "$APP_DIR"
  $SUDO -u "${APP_USER}" php artisan key:generate --force
  $SUDO -u "${APP_USER}" php artisan migrate --force || true
  $SUDO -u "${APP_USER}" php artisan config:cache
  $SUDO -u "${APP_USER}" php artisan route:cache
  $SUDO -u "${APP_USER}" php artisan view:cache
}

echo "[1/6] Installing packages..."
install_packages

echo "[2/6] Configuring Apache..."
configure_apache

echo "[3/6] Configuring MariaDB..."
configure_db

echo "[4/6] Preparing app folders/permissions..."
prepare_app

echo "[5/6] Installing Composer dependencies..."
install_composer_deps

echo "[6/6] Running artisan optimizations..."
run_artisan

echo "Done. Please ensure APP_URL/DB creds in ${APP_DIR}/.env and restart php${PHP_VERSION}-fpm/apache if needed."
