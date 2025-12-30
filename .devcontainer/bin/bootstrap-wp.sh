#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

set -euo pipefail

log() { printf "[bootstrap] %s\n" "$*"; }
die() { printf "[bootstrap:ERROR] %s\n" "$*" >&2; exit 1; }


DB_NAME="${WP_DB_NAME:-wordpress}"
DB_USER="${WP_DB_USER:-wordpress}"
DB_PASS="${WP_DB_PASS:-wordpress}"
DB_HOST="${WP_DB_HOST:-127.0.0.1}"

TITLE="${WP_TITLE:-Codespace Dev}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASS="${WP_ADMIN_PASS:-admin}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

PLUGIN_SLUG="${PLUGIN_SLUG:-local-hello-world}"
DOCROOT="/var/www/html"
WORKSPACE="/workspaces/${GITHUB_REPOSITORY##*/}"
WORKSPACE_DOCROOT="${WORKSPACE}/.devcontainer/var/www/html"
CODESPACE="${CODESPACE_NAME:-}"
if [ -n "$CODESPACE" ]; then
  WP_URL="https://${CODESPACE}-80.app.github.dev"
else
  WP_URL="http://localhost"  # forwardPorts: [80] in devcontainer.json
fi

# Add a wp function and make it available to terminals
echo 'wp() { sudo -u www-data /usr/local/bin/wp --path="$DOCROOT" "$@"; }' >> ~/.bash_aliases
source ~/.bash_aliases
# Add install | update aliases to be used in codespace terminals
echo 'alias cs_install="${CODESPACE_VSCODE_FOLDER}/.devcontainer/bin/install.sh"' >> ~/.bash_aliases
echo 'alias cs_update="${CODESPACE_VSCODE_FOLDER}/.devcontainer/bin/install.sh"' >> ~/.bash_aliases

# --- MariaDB startup (robust) ---
log "Preparing MariaDB directories..."
# Ensure runtime socket dir exists with correct ownership
sudo install -o mysql -g mysql -m 0755 -d /run/mysqld

# Initialize data dir if missing
if [ ! -d /var/lib/mysql/mysql ]; then
  log "Initializing MariaDB data directory..."
  if command -v mariadb-install-db >/dev/null 2>&1; then
    sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
  else
    sudo mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql >/dev/null
  fi
fi

log "Starting MariaDB via service..."
if ! sudo service mariadb start; then
  log "Service start failed, trying to launch mariadbd directly..."
  # Fallback to direct daemon start
  sudo /usr/sbin/mariadbd \
    --datadir=/var/lib/mysql \
    --socket=/run/mysqld/mysqld.sock \
    --bind-address=127.0.0.1 \
    --skip-networking=0 \
    >/var/log/mysql/error.log 2>&1 &
fi

log "Waiting for MariaDB to accept connections..."
# Bounded wait: up to ~30 seconds
WAIT_OK=0
for i in $(seq 1 60); do
  if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
    WAIT_OK=1
    break
  fi
  sleep 0.5
done

if [ "$WAIT_OK" -ne 1 ]; then
  log "MariaDB did not become ready; collecting diagnostics..."
  ps aux | grep -E 'mariadb[d]?|mysqld' || true
  ls -la /run/mysqld || true
  [ -f /var/log/mysql/error.log ] && tail -n 200 /var/log/mysql/error.log || true
  die "MariaDB failed to start within the timeout."
fi

log "Ensuring database and user..."
sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

# Silence Apache ServerName notice
echo 'ServerName localhost' | sudo tee /etc/apache2/conf-available/servername.conf >/dev/null
sudo a2enconf servername >/dev/null 2>&1 || true

# Trust the proxy
sudo tee /etc/apache2/conf-available/codespaces-https.conf >/dev/null <<'APACHE'
<IfModule mod_setenvif.c>
    SetEnvIfNoCase X-Forwarded-Proto "^https$" HTTPS=on
</IfModule>
APACHE
sudo a2enconf codespaces-https >/dev/null 2>&1 || true

# Symlink $WORKSPACE_DOCROOT to $DOCROOT
sudo mkdir -p "${WORKSPACE_DOCROOT}"
sudo chgrp -R www-data "${WORKSPACE_DOCROOT}"
sudo chmod -R g+w "${WORKSPACE_DOCROOT}"
sudo rm -rf $DOCROOT 2>/dev/null || true 
sudo ln -s $WORKSPACE_DOCROOT $DOCROOT

# Allow to sudo as www-data for wp cli
echo 'vscode ALL=(www-data) NOPASSWD: /usr/local/bin/wp *' | sudo tee /etc/sudoers.d/99-wp-cli

# Prefer WordPress at /
sudo rm -f /var/www/html/index.html || true
echo 'DirectoryIndex index.php index.html' | sudo tee /etc/apache2/conf-available/dev-index.conf >/dev/null
sudo a2enconf dev-index >/dev/null 2>&1 || true

# --- WordPress setup ---
log "Preparing WordPress docroot at ${DOCROOT}..."
sudo mkdir -p "$DOCROOT"
sudo chown -R www-data:www-data "$DOCROOT"

# WP-CLI cache in /tmp (writable)
export WP_CLI_VAR_DIR="/var/www/.wp-cli/"
sudo mkdir -p "$WP_CLI_VAR_DIR"
sudo chown -R www-data:www-data "$WP_CLI_VAR_DIR"

if [ ! -f "$DOCROOT/wp-load.php" ]; then
  log "Downloading WordPress core..."
  wp core download --path="$DOCROOT" --force
fi

if [ ! -f "$DOCROOT/wp-config.php" ]; then
  log "Creating wp-config.php..."
  wp config create \
    --path="$DOCROOT" \
    --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" \
    --skip-check
fi

if ! wp core is-installed --path="$DOCROOT" >/dev/null 2>&1; then
  log "Installing WordPress..."
  wp core install \
    --path="$DOCROOT" \
    --url="$WP_URL" \
    --title="$TITLE" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL"
fi

wp option update home "$WP_URL" \
  --path="$DOCROOT" 

wp option update siteurl "$WP_URL" \
  --path="$DOCROOT" 

log "Linking workspace plugin and mu-plugins..."
sudo mkdir -p "$DOCROOT/wp-content/plugins" "$DOCROOT/wp-content"
if [ -d "$WORKSPACE/$PLUGIN_DIR" ]; then
  sudo ln -sfn "$WORKSPACE/$PLUGIN_DIR" "$DOCROOT/wp-content/plugins/$PLUGIN_SLUG"
fi
if [ -d "$WORKSPACE/.devcontainer/wp-content/mu-plugins" ]; then
  sudo rm -rf "$DOCROOT/wp-content/mu-plugins" || true
  sudo ln -sfn "$WORKSPACE/.devcontainer/wp-content/mu-plugins" "$DOCROOT/wp-content/mu-plugins"
fi

# --- Apache startup ---
log "Starting Apache..."
sudo a2enmod rewrite >/dev/null 2>&1 || true
sudo service apache2 restart || true

# Pretty permalinks best-effort
wp rewrite structure '/%postname%/' --path="$DOCROOT" >/dev/null 2>&1 || true
wp rewrite flush --path="$DOCROOT" >/dev/null 2>&1 || true

# Activate the plugin to test
wp plugin activate $PLUGIN_SLUG

# Example: WP_PLUGINS="akismet, jetpack@12.4, https://downloads.wordpress.org/plugin/wp-mail-smtp.latest-stable.zip"
if [ -n "${WP_PLUGINS:-}" ]; then
  IFS=',' read -r -a _plugins <<< "$WP_PLUGINS"
  for raw in "${_plugins[@]}"; do
    plugin="$(echo "$raw" | xargs)"   # trim
    [ -z "$plugin" ] && continue

    # Version support: slug@x.y.z (skip if it's a URL/ZIP)
    version=""
    if [[ "$plugin" != http*://* && "$plugin" != *.zip && "$plugin" == *@* ]]; then
      version="${plugin##*@}"
      plugin="${plugin%%@*}"
    fi

    if [[ "$plugin" == http*://* || "$plugin" == *.zip ]]; then
      # URL or ZIP install
      wp plugin install "$plugin" --activate || echo "Failed to install from $plugin"
    else
      if wp plugin is-installed "$plugin"; then
        # Already installed: activate, and optionally align to requested version
        wp plugin activate "$plugin" || echo "Failed to activate $plugin"
        if [ -n "$version" ]; then
          wp plugin update "$plugin" --version="$version" || echo "Failed to update $plugin to $version"
        fi
      else
        # Fresh install (with version if provided)
        if [ -n "$version" ]; then
          wp plugin install "$plugin" --version="$version" --activate || echo "Failed to install $plugin@$version"
        else
          wp plugin install "$plugin" --activate || echo "Failed to install $plugin"
        fi
      fi
    fi
  done
fi

# --- Local bootstrap hook (optional) ---
# LOCALBOOTSTRAP: path relative to $WORKSPACE (e.g., "scripts/bootstrap-local.sh")
if [ -n "${LOCALBOOTSTRAP:-}" ]; then
  # Allow absolute path too; otherwise treat as relative to $WORKSPACE
  case "$LOCALBOOTSTRAP" in
    /*) LOCALBOOTSTRAP_PATH="$LOCALBOOTSTRAP" ;;
    *)  LOCALBOOTSTRAP_PATH="$WORKSPACE/$LOCALBOOTSTRAP" ;;
  esac

  if [ ! -f "$LOCALBOOTSTRAP_PATH" ]; then
    log "LOCALBOOTSTRAP script not found at: ${LOCALBOOTSTRAP_PATH} (skipping)"
  else
    log "Executing LOCALBOOTSTRAP: ${LOCALBOOTSTRAP_PATH}"

    # Source the script so it runs in the current shell: inherits all variables and functions
    # Be tolerant of errors: don't abort the whole bootstrap
    set +e
    . "$LOCALBOOTSTRAP_PATH"
    status=$?
    set -e

    if [ "$status" -ne 0 ]; then
      log "LOCALBOOTSTRAP exited with status $status; continuing"
    fi
  fi
fi

log "Done. Visit your WP blog following the link in the Ports tab."