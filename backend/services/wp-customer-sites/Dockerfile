# Multi-stage Dockerfile for wp-home-site Bedrock WordPress
# Stages: builder, production, development

# ==============================================================================
# Stage 1: Builder - Install dependencies and build application
# ==============================================================================
FROM php:8.3-fpm-alpine AS builder

# Install build dependencies
RUN apk add --no-cache \
    git \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        opcache \
        intl \
        mbstring \
        exif

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better layer caching)
COPY services/wp-customer-sites/composer.json services/wp-customer-sites/composer.lock ./

# Install dependencies (without scripts to avoid errors)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Copy application code
COPY services/wp-customer-sites/ .

# Run composer scripts and dump autoloader
RUN composer dump-autoload --optimize --no-dev

# ==============================================================================
# Stage 2: Production - Minimal runtime image
# ==============================================================================
FROM php:8.3-fpm-alpine AS production

# Install runtime dependencies only (smaller image)
RUN apk add --no-cache \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    icu-libs \
    oniguruma \
    su-exec \
    mysql-client

# Install PHP extensions (same as builder)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        opcache \
        intl \
        mbstring \
        exif

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp \
    && wp --info

# Configure PHP for production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Configure upload limits
RUN { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=300'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Configure PHP-FPM
RUN { \
        echo '[www]'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 50'; \
        echo 'pm.start_servers = 5'; \
        echo 'pm.min_spare_servers = 5'; \
        echo 'pm.max_spare_servers = 35'; \
    } > /usr/local/etc/php-fpm.d/zz-docker.conf

# Set working directory
WORKDIR /var/www/html

# Copy built application from builder stage
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Copy entrypoint script
COPY scripts/docker-wp-install.sh /usr/local/bin/docker-wp-install.sh
RUN chmod +x /usr/local/bin/docker-wp-install.sh

# Create uploads directory with correct permissions
RUN mkdir -p /var/www/html/web/app/uploads \
    && chown -R www-data:www-data /var/www/html/web/app/uploads \
    && chmod -R 775 /var/www/html/web/app/uploads

# Expose PHP-FPM port
EXPOSE 9000

# Use entrypoint script (starts PHP-FPM directly)
ENTRYPOINT ["docker-wp-install.sh"]

# ==============================================================================
# Stage 3: Development - Includes dev dependencies and Composer
# ==============================================================================
FROM builder AS development

# Install additional dev tools
RUN apk add --no-cache mysql-client su-exec

# Keep Composer in development
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Install all dependencies (including dev)
RUN composer install \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# Configure PHP for development
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Configure upload limits (same as production)
RUN { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=300'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Copy entrypoint script
COPY scripts/docker-wp-install.sh /usr/local/bin/docker-wp-install.sh
RUN chmod +x /usr/local/bin/docker-wp-install.sh

WORKDIR /var/www/html

# In development, we mount the code as a volume, so don't copy it
# COPY . . is not needed here

EXPOSE 9000

# Use entrypoint script (starts PHP-FPM directly)
ENTRYPOINT ["docker-wp-install.sh"]
