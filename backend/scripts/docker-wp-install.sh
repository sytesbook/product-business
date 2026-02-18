#!/bin/sh
set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo "${RED}[ERROR]${NC} $1"
}

# ============================================================================
# 1. Database Readiness Check
# ============================================================================

log_info "Waiting for database to be ready..."

MAX_RETRIES=30
RETRY_COUNT=0

# Extract hostname from DB_HOST (handles format like "db:3306")
DB_HOSTNAME="${DB_HOST%:*}"

until mysql -h"${DB_HOSTNAME}" -u"${DB_USER}" -p"${DB_PASSWORD}" -e "USE ${DB_NAME};" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))

    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        log_error "Database not ready after ${MAX_RETRIES} attempts. Exiting."
        exit 1
    fi

    log_warn "Database not ready yet. Attempt ${RETRY_COUNT}/${MAX_RETRIES}. Waiting 2 seconds..."
    sleep 2
done

log_info "Database is ready!"

# ============================================================================
# 2. WordPress Installation Check
# ============================================================================

log_info "Checking if WordPress is installed..."

# Run as www-data user (matching runtime user)
if su-exec www-data wp core is-installed 2>/dev/null; then
    log_info "WordPress is already installed. Skipping installation."
    WP_INSTALLED=true
else
    log_warn "WordPress is not installed. Will install now."
    WP_INSTALLED=false
fi

# ============================================================================
# 3. WordPress Installation (if needed)
# ============================================================================

if [ "$WP_INSTALLED" = false ]; then
    log_info "Installing WordPress..."

    # Validate required environment variables
    if [ -z "$WP_ADMIN_USER" ] || [ -z "$WP_ADMIN_PASSWORD" ] || [ -z "$WP_ADMIN_EMAIL" ]; then
        log_error "Missing required environment variables:"
        log_error "  WP_ADMIN_USER, WP_ADMIN_PASSWORD, WP_ADMIN_EMAIL"
        log_error "Cannot install WordPress without these variables."
        exit 1
    fi

    # Use WP_SITE_TITLE or default
    SITE_TITLE="${WP_SITE_TITLE:-WordPress Site}"

    # Run installation as www-data
    if su-exec www-data wp core install \
        --url="${WP_HOME}" \
        --title="${SITE_TITLE}" \
        --admin_user="${WP_ADMIN_USER}" \
        --admin_password="${WP_ADMIN_PASSWORD}" \
        --admin_email="${WP_ADMIN_EMAIL}" \
        --skip-email; then

        log_info "WordPress installed successfully!"
        log_info "Admin user: ${WP_ADMIN_USER}"
        log_info "Admin email: ${WP_ADMIN_EMAIL}"
        log_info "Site URL: ${WP_HOME}"
    else
        log_error "WordPress installation failed!"
        exit 1
    fi
else
    log_info "Skipping WordPress installation."
fi

# ============================================================================
# 4. Start PHP-FPM
# ============================================================================

log_info "Starting PHP-FPM..."
exec "$@"
