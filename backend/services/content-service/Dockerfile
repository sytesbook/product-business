# Multi-stage Dockerfile for content-service Laravel application
# Stages: builder, production, development

# ==============================================================================
# Stage 1: Builder - Install dependencies and build application
# ==============================================================================
FROM php:8.4-fpm-alpine AS builder

# Install build dependencies
RUN apk add --no-cache \
    git \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    mbstring \
    zip \
    opcache \
    bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer.json first for better layer caching
# Note: commit composer.lock after first build for reproducible installs
COPY services/content-service/composer.json ./

# Install production dependencies (skip scripts; artisan is not available yet)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Copy application code
COPY services/content-service/ .

# Dump optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# ==============================================================================
# Stage 2: Production - Minimal runtime image
# ==============================================================================
FROM php:8.4-fpm-alpine AS production

# Install runtime dependencies only (smaller image)
RUN apk add --no-cache \
    libzip \
    oniguruma \
    libxml2

# Install PHP extensions (same as builder)
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    mbstring \
    zip \
    opcache \
    bcmath

# Configure PHP for production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP settings
RUN { \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=32M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=60'; \
    } > /usr/local/etc/php/conf.d/settings.ini

# Configure PHP-FPM
RUN { \
        echo '[www]'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 50'; \
        echo 'pm.start_servers = 5'; \
        echo 'pm.min_spare_servers = 5'; \
        echo 'pm.max_spare_servers = 35'; \
        echo 'catch_workers_output = yes'; \
        echo 'decorate_workers_output = no'; \
    } > /usr/local/etc/php-fpm.d/zz-docker.conf

# Set working directory
WORKDIR /var/www/html

# Copy built application from builder stage
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Copy entrypoint script
COPY scripts/docker-laravel-start.sh /usr/local/bin/docker-laravel-start.sh
RUN chmod +x /usr/local/bin/docker-laravel-start.sh

# Create writable directories with correct permissions
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose PHP-FPM port
EXPOSE 9000

ENTRYPOINT ["docker-laravel-start.sh"]

# ==============================================================================
# Stage 3: Development - Includes dev dependencies and Composer
# ==============================================================================
FROM builder AS development

# Install additional dev tools
RUN apk add --no-cache mysql-client

# Keep Composer in development
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install all dependencies (including dev)
RUN composer install \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# Configure PHP for development (validate timestamps for live reloading)
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP settings
RUN { \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=32M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=60'; \
    } > /usr/local/etc/php/conf.d/settings.ini

# Configure PHP-FPM
RUN { \
        echo '[www]'; \
        echo 'catch_workers_output = yes'; \
        echo 'decorate_workers_output = no'; \
    } > /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy entrypoint script
COPY scripts/docker-laravel-start.sh /usr/local/bin/docker-laravel-start.sh
RUN chmod +x /usr/local/bin/docker-laravel-start.sh

WORKDIR /var/www/html

# In development, source code is volume-mounted — no COPY needed here

EXPOSE 9000

ENTRYPOINT ["docker-laravel-start.sh"]
