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

# Extract hostname and port from DB_HOST (handles format like "db:3306")
DB_HOSTNAME="${DB_HOST%:*}"
DB_PORT="${DB_HOST##*:}"
# If no port specified, default to 3306
if [ "$DB_PORT" = "$DB_HOSTNAME" ]; then
    DB_PORT=3306
fi

# Use PHP mysqli to check database connectivity (natively supports caching_sha2_password)
# This bypasses Alpine's MariaDB client which has compatibility issues with MySQL 8.4
#
# mysqli_report(MYSQLI_REPORT_OFF) is required for PHP 8.1+ where mysqli throws exceptions
# by default. Without it, connection failures (wrong credentials, host not ready, etc.)
# throw an uncaught mysqli_sql_exception instead of populating $mysqli->connect_error,
# which crashes the script rather than triggering the retry loop.
until php -r "
mysqli_report(MYSQLI_REPORT_OFF);
\$mysqli = new mysqli('$DB_HOSTNAME', '$DB_USER', '$DB_PASSWORD', '$DB_NAME', $DB_PORT);
if (\$mysqli->connect_error) {
    exit(1);
}
\$mysqli->close();
exit(0);
" 2>/dev/null; do
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
# 2. Ensure Uploads Directory Exists with Correct Permissions
# ============================================================================

log_info "Ensuring uploads directory exists with correct permissions..."

# Create uploads directory structure if it doesn't exist
UPLOADS_DIR="/var/www/html/web/app/uploads"
mkdir -p "$UPLOADS_DIR"

# Set ownership to www-data (PHP-FPM user)
# This is critical for development where host volumes override container permissions
chown -R www-data:www-data "$UPLOADS_DIR"

# Set permissions: 775 (rwxrwxr-x)
# - Owner (www-data): read, write, execute
# - Group (www-data): read, write, execute
# - Others: read, execute
chmod -R 775 "$UPLOADS_DIR"

# Also ensure parent directory is writable (WordPress needs to create year-based subdirectories)
APP_DIR="/var/www/html/web/app"
chown www-data:www-data "$APP_DIR"
chmod 775 "$APP_DIR"

log_info "Uploads directory permissions configured successfully."

# ============================================================================
# 3. WordPress Installation Check
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
# 4. WordPress Installation (if needed)
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

        # Remove default content created by WordPress during installation
        log_info "Removing default WordPress content..."
        su-exec www-data wp post delete \
            $(su-exec www-data wp post list --post_type=page --name=sample-page --post_status=any --field=ID 2>/dev/null) \
            $(su-exec www-data wp post list --post_type=post --name=hello-world --post_status=any --field=ID 2>/dev/null) \
            --force 2>/dev/null || true
        log_info "Default content removed."
    else
        log_error "WordPress installation failed!"
        exit 1
    fi
else
    log_info "Skipping WordPress installation."
fi

# ============================================================================
# 4.5. Configure Permalink Structure
# ============================================================================

log_info "Configuring WordPress permalink structure..."

# Always apply permalink structure and flush rewrite rules on every startup so
# that custom rewrite rules registered by MU plugins (e.g. sytesbook-site-taxonomy,
# which provides /{site-uid}/{page-uid}/ routing for pages) are always up to date
# without requiring manual intervention after deployments.
#
# Note: --hard is intentionally omitted because this service runs behind Nginx
# and does not use .htaccess.
if su-exec www-data wp core is-installed 2>/dev/null; then
    if su-exec www-data wp rewrite structure '/%postname%/' 2>/dev/null; then
        log_info "Permalink structure set to post name format."
    else
        log_warn "Failed to set permalink structure, REST API may use query string format"
    fi

    su-exec www-data wp rewrite flush 2>/dev/null
    log_info "Rewrite rules flushed."
else
    log_warn "WordPress not installed, skipping permalink configuration"
fi

# ============================================================================
# 4.6. Activate Theme
# ============================================================================

log_info "Activating theme..."

if su-exec www-data wp core is-installed 2>/dev/null; then
    if su-exec www-data wp theme activate custom-sytesbook 2>/dev/null; then
        log_info "Theme custom-sytesbook activated."
    else
        log_warn "Failed to activate theme custom-sytesbook."
    fi
else
    log_warn "WordPress not installed, skipping theme activation"
fi

# ============================================================================
# 5. Start PHP-FPM
# ============================================================================

log_info "Starting PHP-FPM..."
# Start PHP-FPM (master runs as root, worker processes run as www-data via php-fpm.d/www.conf)
# Use exec to replace the shell process with php-fpm (proper signal handling)
exec php-fpm --nodaemonize --fpm-config /usr/local/etc/php-fpm.conf
