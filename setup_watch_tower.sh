#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="${APP_ROOT:-/var/www/watch_tower}"
SERVER_NAME="${SERVER_NAME:-watchtower.local}"
DB_NAME="${DB_NAME:-watch}"
DB_USER="${DB_USER:-postgres}"
DB_PASSWORD="${DB_PASSWORD:-mahdi3276}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DISCORD_NOTIFICATIONS_ENABLED="${DISCORD_NOTIFICATIONS_ENABLED:-false}"
WEBHOOK_URL="${WEBHOOK_URL:-}"
PDCP_API_KEY="${PDCP_API_KEY:-}"
GO_VERSION="${GO_VERSION:-1.23.5}"
CONFIGURE_APACHE="${CONFIGURE_APACHE:-true}"

log() {
    echo -e "\033[1;34m[watch_tower]\033[0m $*"
}

require_sudo() {
    if [[ $EUID -ne 0 ]]; then
        if ! sudo -v; then
            echo "This script needs sudo privileges. Abort."
            exit 1
        fi
    fi
}

ensure_packages() {
    log "Updating apt cache and installing system packages..."
    sudo apt-get update -y
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
        apt-transport-https \
        ca-certificates \
        curl \
        git \
        gnupg \
        unzip \
        build-essential \
        pkg-config \
        libpq-dev \
        libpcap-dev \
        apache2 \
        postgresql \
        postgresql-client \
        software-properties-common \
        php \
        php-cli \
        php-common \
        php-curl \
        php-fpm \
        php-gd \
        php-intl \
        php-json \
        php-mbstring \
        php-pgsql \
        php-sqlite3 \
        php-xml \
        php-zip \
        php-bcmath \
        composer
}

install_go() {
    local target_version="go${GO_VERSION}"
    if command -v go >/dev/null 2>&1; then
        local current
        current="$(go version | awk '{print $3}')"
        if [[ "$current" == "$target_version" ]]; then
            log "Go ${GO_VERSION} already installed."
            return
        fi
        log "Updating Go from ${current} to ${target_version}..."
    else
        log "Installing Go ${GO_VERSION}..."
    fi

    local tarball="/tmp/${target_version}.linux-amd64.tar.gz"
    curl -fsSL "https://go.dev/dl/${target_version}.linux-amd64.tar.gz" -o "$tarball"
    sudo rm -rf /usr/local/go
    sudo tar -C /usr/local -xzf "$tarball"
    rm -f "$tarball"
}

configure_go_path() {
    if ! grep -q '/usr/local/go/bin' "${HOME}/.profile" 2>/dev/null; then
        echo 'export PATH=$PATH:/usr/local/go/bin' >> "${HOME}/.profile"
    fi
    if ! grep -q '$HOME/go/bin' "${HOME}/.profile" 2>/dev/null; then
        echo 'export PATH=$PATH:$HOME/go/bin' >> "${HOME}/.profile"
    fi

    export PATH="$PATH:/usr/local/go/bin:${HOME}/go/bin"
}

install_go_tools() {
    log "Installing Go-based reconnaissance tools..."
    local tools=(
        "github.com/projectdiscovery/dnsx/cmd/dnsx@latest"
        "github.com/projectdiscovery/httpx/cmd/httpx@latest"
        "github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest"
        "github.com/projectdiscovery/nuclei/v3/cmd/nuclei@latest"
        "github.com/projectdiscovery/chaos-client/cmd/chaos@latest"
        "github.com/samogod/samoscout@latest"
        "github.com/tomnomnom/waybackurls@latest"
        "github.com/projectdiscovery/naabu/v2/cmd/naabu@latest"
    )

    for tool in "${tools[@]}"; do
        log "Installing ${tool}..."
        GO111MODULE=on go install -v "${tool}"
    done
}

configure_postgresql() {
    log "Configuring PostgreSQL..."
    sudo systemctl enable --now postgresql

    if [[ "${DB_USER}" == "postgres" ]]; then
        sudo -u postgres psql -c "ALTER USER postgres WITH PASSWORD '${DB_PASSWORD}';" >/dev/null
    else
        if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1; then
            sudo -u postgres createuser --no-superuser --no-createdb --no-createrole "${DB_USER}"
        fi
        sudo -u postgres psql -c "ALTER USER ${DB_USER} WITH PASSWORD '${DB_PASSWORD}';" >/dev/null
    fi

    if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1; then
        sudo -u postgres createdb "${DB_NAME}"
        if [[ "${DB_USER}" != "postgres" ]]; then
            sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};" >/dev/null
        fi
    fi
}

install_php_dependencies() {
    log "Installing Composer dependencies..."
    cd "${PROJECT_ROOT}"
    composer install --no-interaction --prefer-dist
}

ensure_env_file() {
    local env_path="${PROJECT_ROOT}/.env"
    if [[ -f "${env_path}" ]]; then
        log ".env already exists. Skipping creation."
        return
    fi

    log "Creating default .env file..."
    cat <<EOF > "${env_path}"
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_NAME=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
WEBHOOK_URL=${WEBHOOK_URL}
DISCORD_NOTIFICATIONS_ENABLED=${DISCORD_NOTIFICATIONS_ENABLED}
PDCP_API_KEY=${PDCP_API_KEY}
EOF
}

sync_application_files() {
    if [[ "${CONFIGURE_APACHE}" != "true" ]]; then
        log "Skipping Apache deployment (CONFIGURE_APACHE=false)."
        return
    fi

    log "Syncing project files to ${APP_ROOT}..."
    sudo mkdir -p "${APP_ROOT}"
    sudo rsync -a --delete "${PROJECT_ROOT}/" "${APP_ROOT}/"
    sudo chown -R www-data:www-data "${APP_ROOT}"
}

configure_apache() {
    if [[ "${CONFIGURE_APACHE}" != "true" ]]; then
        return
    fi

    local vhost="/etc/apache2/sites-available/watch_tower.conf"
    log "Configuring Apache virtual host..."
    sudo tee "${vhost}" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    DocumentRoot ${APP_ROOT}/public

    <Directory ${APP_ROOT}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/watch_tower_error.log
    CustomLog \${APACHE_LOG_DIR}/watch_tower_access.log combined
</VirtualHost>
EOF

    sudo a2dissite 000-default.conf >/dev/null 2>&1 || true
    sudo a2ensite watch_tower.conf >/dev/null
    sudo systemctl reload apache2
}

run_database_migrations() {
    log "Initializing database schema..."
    php -r "require '${PROJECT_ROOT}/bootstrap.php'; Database::createTables();"
}

main() {
    require_sudo
    ensure_packages
    install_go
    configure_go_path
    install_go_tools
    configure_postgresql
    install_php_dependencies
    ensure_env_file
    sync_application_files
    configure_apache
    run_database_migrations

    log "Setup complete!"
    log "Restart your shell or 'source ~/.profile' to load PATH updates."
    log "Apache serves Watch Tower from ${APP_ROOT}/public (http://${SERVER_NAME}/)."
}

main "$@"

