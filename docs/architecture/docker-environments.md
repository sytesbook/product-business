# Docker Environments: Local Development vs Production

This document provides detailed architectural diagrams comparing the local development environment with the production/staging environment on EC2.

## Local Development Environment

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Developer Machine                             │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │              Backend Directory (Monorepo)                      │ │
│  │                                                                │ │
│  │  ├── composer.json (root orchestrator)                        │ │
│  │  ├── services/                                                 │ │
│  │  │   ├── wp-home-site/ (Bedrock WordPress - home site)      │ │
│  │  │   │   ├── web/, config/, vendor/, composer.json         │ │
│  │  │   └── wp-customer-sites/ (Bedrock WordPress - customers) │ │
│  │  │       ├── web/, config/, vendor/, composer.json         │ │
│  │  └── packages/ (shared packages - symlinked)                 │ │
│  │                                                                │ │
│  │  docker-compose.yml + docker-compose.override.yml            │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                    Docker Environment                          │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐│ │
│  │  │  Browser:                                                ││ │
│  │  │    - Home site: http://localhost:8080                   ││ │
│  │  │    - Customer sites: http://localhost:8090              ││ │
│  │  └─────────────────┬──────────────────┬─────────────────────┘│ │
│  │                    │                  │                       │ │
│  │          HTTP (8080)                  │ HTTP (8090)           │ │
│  │                    ▼                  ▼                       │ │
│  │  ┌────────────────────────────┐  ┌────────────────────────┐ │ │
│  │  │ wp-home-site-nginx         │  │ wp-customer-sites-     │ │ │
│  │  │ (nginx:alpine)             │  │ nginx (nginx:alpine)   │ │ │
│  │  │ Port: 8080→80              │  │ Port: 8090→80          │ │ │
│  │  │ Volumes: ./wp-home-site:ro │  │ Volumes: ./wp-customer-│ │ │
│  │  │          wp_home_uploads:ro│  │ sites:ro, wp_customers_│ │ │
│  │  └──────────┬─────────────────┘  │ uploads:ro             │ │ │
│  │             │                    └──────────┬─────────────┘ │ │
│  │             │ FastCGI (9000)                │ FastCGI (9000)│ │
│  │             ▼                               ▼               │ │
│  │  ┌────────────────────────────┐  ┌────────────────────────┐ │ │
│  │  │ wp-home-site-php           │  │ wp-customer-sites-php  │ │ │
│  │  │ (PHP 8.3-FPM)              │  │ (PHP 8.3-FPM)          │ │ │
│  │  │ Build: target=development  │  │ Build: target=dev      │ │ │
│  │  │ Volumes:                   │  │ Volumes:               │ │ │
│  │  │  - ./wp-home-site (MOUNTED)│  │  - ./wp-customer-sites │ │ │
│  │  │  - wp_home_uploads         │  │    (MOUNTED)           │ │ │
│  │  │ Env: WP_ENV=development    │  │  - wp_customers_uploads│ │ │
│  │  │      WP_DEBUG=true         │  │ Env: WP_ENV=dev        │ │ │
│  │  │      Opcache validates     │  │      WP_DEBUG=true     │ │ │
│  │  │ Has: Composer, mysql-client│  │      Opcache validates │ │ │
│  │  └──────────┬─────────────────┘  └──────────┬─────────────┘ │ │
│  │             │                               │               │ │
│  │             │ TCP 3306                      │ TCP 3306      │ │
│  │             └───────────────┬───────────────┘               │ │
│  │                             ▼                               │ │
│  │  ┌──────────────────────────────────────────────────────────┐│ │
│  │  │  backend-db (mysql:8.4)                                  ││ │
│  │  │  Port: 3306 (exposed to host)                           ││ │
│  │  │  Volume: db_data:/var/lib/mysql (persisted)             ││ │
│  │  │  Databases: wp_home_site_db, wp_customer_sites_db             ││ │
│  │  │  Init scripts: ./infrastructure/mysql/init              ││ │
│  │  └──────────────────────────────────────────────────────────┘│ │
│  │                                                                │ │
│  │  Network: backend-network (bridge)                            │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘

KEY FEATURES - Local Development:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Code MOUNTED as volumes → live reloading (edit files, see changes)
✅ Composer available → can run `composer require` inside container
✅ MySQL exposed on localhost:3306 → can connect with GUI tools
✅ Opcache validates timestamps → picks up code changes
✅ Development build target → includes dev dependencies
✅ Direct port access (8080, 8090) → no reverse proxy needed
✅ http://localhost:8080 and :8090 → no SSL needed
✅ WP_DEBUG enabled → see errors immediately
✅ Two WordPress services running in parallel
```

### Local Development Workflow

1. **Initial Setup**:
   ```bash
   cd backend
   cp docker-compose.override.example.yml docker-compose.override.yml
   # Edit with local credentials
   docker-compose up -d
   ```

2. **Development Cycle**:
   - Edit code in `services/wp-home-site/` or `services/wp-customer-sites/`
   - Changes immediately visible (no rebuild required)
   - View logs: `docker-compose logs -f wp-home-site-php` or `wp-customer-sites-php`
   - Access sites: http://localhost:8080 (home) or http://localhost:8090 (customers)

3. **Managing Dependencies**:
   ```bash
   # For home site
   cd services/wp-home-site
   composer require wpackagist-plugin/wordpress-seo
   # OR: docker-compose exec wp-home-site-php composer require wpackagist-plugin/wordpress-seo

   # For customer sites
   cd services/wp-customer-sites
   composer require wpackagist-plugin/wordpress-seo
   # OR: docker-compose exec wp-customer-sites-php composer require wpackagist-plugin/wordpress-seo
   ```

4. **Database Access**:
   - MySQL exposed on `localhost:3306`
   - Connect with GUI tools (Sequel Pro, DBeaver, etc.)
   - Credentials from `docker-compose.override.yml`

## Production/Staging Environment (EC2 Instance)

```
┌─────────────────────────────────────────────────────────────────────┐
│                         AWS EC2 Instance                             │
│                      (Ubuntu, Docker installed)                      │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │              /opt/product-business/backend/                    │ │
│  │              (Git repository cloned)                           │ │
│  │                                                                │ │
│  │  Only contains:                                                │ │
│  │  ├── docker-compose.yml                                        │ │
│  │  ├── services/                                                 │ │
│  │  │   ├── wp-home-site/ (Dockerfile, nginx.conf, composer.json)│ │
│  │  │   └── wp-customer-sites/ (Dockerfile, nginx.conf, ...)    │ │
│  │  └── infrastructure/mysql/init/                               │ │
│  │                                                                │ │
│  │  NO source code! NO vendor! (built into Docker images)        │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  Environment Variables: Injected from GitHub Secrets               │
│  (No .env files, no docker-compose.override.yml)                   │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                    Docker Environment                          │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐│ │
│  │  │  Internet:                                               ││ │
│  │  │    - Home site: https://home.sytesbook.com              ││ │
│  │  │    - Customer sites: https://customers.sytesbook.com    ││ │
│  │  └────────────────┬──────────────────┬──────────────────────┘│ │
│  │                   │                  │                        │ │
│  │             HTTPS (443)        HTTPS (443)                   │ │
│  │                   ▼                  ▼                        │ │
│  │  ┌──────────────────────────────────────────────────────────┐│ │
│  │  │  [Future: Traefik Reverse Proxy]                         ││ │
│  │  │  - Automatic SSL via Let's Encrypt                       ││ │
│  │  │  - Routes: home.${DOMAIN} → wp-home-site                 ││ │
│  │  │  - Routes: customers.${DOMAIN} → wp-customer-sites       ││ │
│  │  │  - Currently not configured                              ││ │
│  │  └───────────────┬──────────────────┬───────────────────────┘│ │
│  │                  │                  │                         │ │
│  │        HTTP (8080)                  │ HTTP (8090)             │ │
│  │                  ▼                  ▼                         │ │
│  │  ┌──────────────────────────┐  ┌───────────────────────────┐│ │
│  │  │ wp-home-site-nginx       │  │ wp-customer-sites-nginx   ││ │
│  │  │ (nginx:alpine)           │  │ (nginx:alpine)            ││ │
│  │  │ Port: 80                 │  │ Port: 80                  ││ │
│  │  │ Volumes:                 │  │ Volumes:                  ││ │
│  │  │  - nginx.conf (config)   │  │  - nginx.conf (config)    ││ │
│  │  │  - wp_home_uploads:ro    │  │  - wp_customers_uploads:ro││ │
│  │  │ NO source code volume!   │  │ NO source code volume!    ││ │
│  │  └─────────┬────────────────┘  └─────────┬─────────────────┘│ │
│  │            │                              │                   │ │
│  │            │ FastCGI (9000)               │ FastCGI (9000)    │ │
│  │            ▼                              ▼                   │ │
│  │  ┌──────────────────────────┐  ┌───────────────────────────┐│ │
│  │  │ wp-home-site-php         │  │ wp-customer-sites-php     ││ │
│  │  │ (PHP 8.3-FPM)            │  │ (PHP 8.3-FPM)             ││ │
│  │  │ Build: target=production │  │ Build: target=production  ││ │
│  │  │ Image contains:          │  │ Image contains:           ││ │
│  │  │  - Bedrock code (COPIED) │  │  - Bedrock code (COPIED)  ││ │
│  │  │  - WordPress core        │  │  - WordPress core         ││ │
│  │  │  - All vendor/ deps      │  │  - All vendor/ deps       ││ │
│  │  │ Volumes:                 │  │ Volumes:                  ││ │
│  │  │  - wp_home_uploads       │  │  - wp_customers_uploads   ││ │
│  │  │ Env: From GitHub Secrets │  │ Env: From GitHub Secrets  ││ │
│  │  │      WP_ENV=production   │  │      WP_ENV=production    ││ │
│  │  │      WP_DEBUG=false      │  │      WP_DEBUG=false       ││ │
│  │  │      Opcache: no checks  │  │      Opcache: no checks   ││ │
│  │  │ NO Composer! Minimal img │  │ NO Composer! Minimal img  ││ │
│  │  │ Runs as: www-data        │  │ Runs as: www-data         ││ │
│  │  └─────────┬────────────────┘  └─────────┬─────────────────┘│ │
│  │            │                              │                   │ │
│  │            │ TCP 3306                     │ TCP 3306          │ │
│  │            └──────────────┬───────────────┘                   │ │
│  │                           ▼                                   │ │
│  │  ┌──────────────────────────────────────────────────────────┐│ │
│  │  │  backend-db (mysql:8.4)                                  ││ │
│  │  │  Port: 3306 (internal only)                             ││ │
│  │  │  Volume: db_data:/var/lib/mysql (EBS-backed)            ││ │
│  │  │  Databases: wp_home_site_db, wp_customer_sites_db             ││ │
│  │  │  Environment: From GitHub Secrets                        ││ │
│  │  │  Backups: Automated via AWS or cron                     ││ │
│  │  └──────────────────────────────────────────────────────────┘│ │
│  │                                                                │ │
│  │  Network: backend-network (bridge)                            │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │               GitHub Actions CI/CD Pipeline                    │ │
│  │                                                                │ │
│  │  1. Push to stable/production branch                          │ │
│  │  2. Build Docker images (code baked in)                       │ │
│  │  3. SSH to EC2 instance                                       │ │
│  │  4. Export environment variables from GitHub Secrets          │ │
│  │  5. docker-compose build && docker-compose up -d              │ │
│  │  6. Zero-downtime deployment (containers replaced)            │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘

KEY FEATURES - Production:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Code BAKED IN → immutable deployments
✅ NO Composer → minimal attack surface
✅ NO source volumes → can't be tampered with
✅ Opcache never checks timestamps → maximum performance
✅ Production build target → only runtime dependencies
✅ GitHub Secrets → environment variables (no files)
✅ Runs as www-data → security best practice
✅ MySQL internal only → not exposed to internet
✅ Future Traefik → SSL termination, multi-service routing
```

### Production Deployment Workflow

1. **Build Process** (Multi-stage Dockerfile):
   ```dockerfile
   # Stage 1: Builder
   FROM php:8.3-fpm-alpine AS builder
   COPY composer.json composer.lock ./
   RUN composer install --no-dev --optimize-autoloader
   COPY . .

   # Stage 2: Production (final)
   FROM php:8.3-fpm-alpine AS production
   COPY --from=builder /var/www/html /var/www/html
   # Minimal runtime, no Composer
   ```

2. **Deployment** (via GitHub Actions):
   ```bash
   # On EC2 instance
   cd /opt/product-business/backend

   # Environment from GitHub Secrets
   export DOMAIN="sytesbook.com"
   export MYSQL_ROOT_PASSWORD="<secret>"
   export WP_HOME_DB_USER="<secret>"
   # ... all other secrets

   # Deploy
   docker-compose build
   docker-compose up -d
   ```

3. **Zero-Downtime Updates**:
   ```bash
   # Replace home site
   docker-compose up -d --no-deps --build wp-home-site-php
   docker-compose up -d --no-deps --build wp-home-site-nginx

   # Replace customer sites
   docker-compose up -d --no-deps --build wp-customer-sites-php
   docker-compose up -d --no-deps --build wp-customer-sites-nginx
   # Old containers stop, new ones start immediately
   ```

## Key Differences Summary

| Aspect | Local Development | Production (EC2) |
|--------|------------------|------------------|
| **Code Location** | Mounted from host filesystem | Copied into Docker image |
| **Live Reload** | ✅ Yes (edit → see changes) | ❌ No (rebuild required) |
| **Composer** | ✅ Available in container | ❌ Not included |
| **Opcache** | Validates timestamps (slower, detects changes) | Never validates (faster, immutable) |
| **Build Target** | `development` | `production` |
| **Port Access** | 8080, 8090 → 80 (direct) | 80 (future: Traefik 443) |
| **Protocol** | HTTP | HTTPS (future) |
| **Domain** | http://localhost:8080, :8090 | https://home.${DOMAIN}, customers.${DOMAIN} |
| **MySQL Access** | Exposed on 3306 | Internal only |
| **Debug Mode** | WP_DEBUG=true | WP_DEBUG=false |
| **Environment Config** | docker-compose.override.yml | GitHub Secrets → env vars |
| **Deployment** | Manual `docker-compose up` | GitHub Actions CI/CD |
| **Image Size** | Larger (dev dependencies) | Smaller (runtime only) |
| **Security** | Runs as root | Runs as www-data |
| **Packages (monorepo)** | Symlinked from packages/ | Copied during build |

## Docker Build Process Comparison

### Local Development Build

```bash
cd backend

# Copy example override file
cp docker-compose.override.example.yml docker-compose.override.yml

# Edit with local credentials
vim docker-compose.override.yml

# Start services
docker-compose up -d
```

**What happens:**
1. Builds `development` target from Dockerfile
2. Includes Composer and dev tools
3. Mounts source code as volumes
4. Opcache configured to check for file changes
5. Can edit code and see changes immediately
6. MySQL exposed for GUI access

### Production Build

```bash
# On EC2 instance (triggered by GitHub Actions)
cd /opt/product-business/backend

# Environment variables from GitHub Secrets
export DOMAIN="sytesbook.com"
export MYSQL_ROOT_PASSWORD="<from-secrets>"
export WP_HOME_DB_USER="<from-secrets>"
export WP_HOME_DB_PASSWORD="<from-secrets>"
# ... all other secrets

# Build production images
docker-compose build

# Deploy
docker-compose up -d
```

**What happens:**
1. Multi-stage build: `builder` → `production`
2. Installs Composer dependencies during build
3. Copies all code into image
4. Optimizes autoloader (`--no-dev --optimize-autoloader`)
5. Removes Composer from final image
6. Configures opcache for production (no timestamp validation)
7. Final image is immutable and self-contained

## Monorepo Impact

### Local Development with Shared Packages

```
Developer Machine:
  backend/
    ├── services/wp-home-site/
    │   └── vendor/
    │       └── sytesbook/business-logging/ → SYMLINK to ../../packages/logging
    └── packages/logging/
        ├── src/Logger.php
        └── vendor/ (monolog, etc.)

When you edit packages/logging/src/Logger.php:
→ Changes immediately visible in wp-home-site (symlinked)
→ No composer update needed
→ Fast iteration!
```

**Workflow:**
```bash
# Install package in service
cd services/wp-home-site
composer require sytesbook/business-logging:@dev

# Edit package code
vim ../../packages/logging/src/Logger.php

# Changes immediately available in service
# (symlink: vendor/sytesbook/business-logging/ → packages/logging/)
```

### Production with Shared Packages

```
Docker Build Process:
  1. COPY packages/ into build context
  2. COPY services/wp-home-site/ into build context
  3. composer install resolves packages/ via path repositories
  4. Final image contains:
     - services/wp-home-site/vendor/sytesbook/business-logging/ (COPIED, not symlinked)
     - All resolved dependencies
  5. packages/ directory removed from final image

Result:
→ Immutable deployment
→ No external dependencies
→ Self-contained image
```

**Build context:**
```dockerfile
# In Dockerfile
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Composer resolves path repositories:
# - Finds packages/ in build context
# - Copies (not symlinks) into vendor/
# - Final image has everything it needs
```

## Environment Configuration

### Local Development

Configuration via `docker-compose.override.yml` (gitignored):

```yaml
services:
  db:
    environment:
      MYSQL_ROOT_PASSWORD: root_db_password_local
      WP_HOME_DB_USER: wp_home_db_user
      WP_HOME_DB_PASSWORD: local_db_password
      WP_CUSTOMERS_DB_USER: wp_customers_user
      WP_CUSTOMERS_DB_PASSWORD: local_db_password
    ports:
      - "3306:3306"  # Exposed for GUI tools

  wp-home-site-php:
    build:
      target: development  # Use dev build stage
    volumes:
      - ./services/wp-home-site:/var/www/html  # Mount source
    environment:
      DB_NAME: wp_home_site_db
      DB_USER: wp_home_db_user
      DB_PASSWORD: local_db_password
      WP_ENV: development
      WP_HOME: http://localhost:8080
      WP_DEBUG: "true"
      # ... security keys (generate at roots.io/salts.html)

  wp-home-site-nginx:
    volumes:
      - ./services/wp-home-site:/var/www/html:ro  # Mount for static files
    ports:
      - "8080:80"  # Direct access

  wp-customer-sites-php:
    build:
      target: development  # Use dev build stage
    volumes:
      - ./services/wp-customer-sites:/var/www/html  # Mount source
    environment:
      DB_NAME: wp_customer_sites_db
      DB_USER: wp_customers_user
      DB_PASSWORD: local_db_password
      WP_ENV: development
      WP_HOME: http://localhost:8090
      WP_DEBUG: "true"
      # ... security keys (generate at roots.io/salts.html)

  wp-customer-sites-nginx:
    volumes:
      - ./services/wp-customer-sites:/var/www/html:ro  # Mount for static files
    ports:
      - "8090:80"  # Direct access
```

### Production/Staging

Configuration via environment variables (GitHub Secrets):

```yaml
services:
  db:
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      WP_HOME_DB_USER: ${WP_HOME_DB_USER}
      WP_HOME_DB_PASSWORD: ${WP_HOME_DB_PASSWORD}
      WP_CUSTOMERS_DB_USER: ${WP_CUSTOMERS_DB_USER}
      WP_CUSTOMERS_DB_PASSWORD: ${WP_CUSTOMERS_DB_PASSWORD}
    # No ports exposed (internal only)

  wp-home-site-php:
    build:
      target: production  # Use production build stage
    # No volumes! Code is in the image
    environment:
      DB_NAME: ${WP_HOME_DB_NAME}
      DB_USER: ${WP_HOME_DB_USER}
      DB_PASSWORD: ${WP_HOME_DB_PASSWORD}
      WP_ENV: ${WP_HOME_ENV}
      WP_HOME: https://home.${DOMAIN}
      # ... security keys from secrets

  wp-home-site-nginx:
    ports:
      - "8080:80"  # Local: 8080, Production: 80 via Traefik
    # No source code volume

  wp-customer-sites-php:
    build:
      target: production  # Use production build stage
    # No volumes! Code is in the image
    environment:
      DB_NAME: ${WP_CUSTOMERS_DB_NAME}
      DB_USER: ${WP_CUSTOMERS_DB_USER}
      DB_PASSWORD: ${WP_CUSTOMERS_DB_PASSWORD}
      WP_ENV: ${WP_CUSTOMERS_ENV}
      WP_HOME: https://customers.${DOMAIN}
      # ... security keys from secrets

  wp-customer-sites-nginx:
    ports:
      - "8090:80"  # Local: 8090, Production: 80 via Traefik
    # No source code volume
```

**GitHub Actions deployment:**
```bash
#!/bin/bash
# Inject secrets as environment variables
export DOMAIN="${{ secrets.DOMAIN }}"
export MYSQL_ROOT_PASSWORD="${{ secrets.MYSQL_ROOT_PASSWORD }}"

# Home site secrets
export WP_HOME_DB_USER="${{ secrets.WP_HOME_DB_USER }}"
export WP_HOME_DB_PASSWORD="${{ secrets.WP_HOME_DB_PASSWORD }}"
# ... all other WP_HOME_* secrets

# Customer sites secrets
export WP_CUSTOMERS_DB_USER="${{ secrets.WP_CUSTOMERS_DB_USER }}"
export WP_CUSTOMERS_DB_PASSWORD="${{ secrets.WP_CUSTOMERS_DB_PASSWORD }}"
# ... all other WP_CUSTOMERS_* secrets

# Deploy
docker-compose build
docker-compose up -d
```

## Security Considerations

### Local Development

- ⚠️ Credentials in gitignored `docker-compose.override.yml`
- ⚠️ MySQL exposed on localhost:3306 (optional, for GUI tools)
- ⚠️ Debug mode enabled (verbose errors)
- ⚠️ Runs as root (acceptable for local)
- ✅ Private network (not exposed to internet)

### Production

- ✅ GitHub Secrets → environment variables (no files)
- ✅ MySQL internal-only (not exposed to internet)
- ✅ Debug mode disabled (no information leakage)
- ✅ Runs as www-data (non-root user)
- ✅ Opcache never validates (immutable code)
- ✅ Sensitive files outside web root (Bedrock structure)
- ✅ Minimal attack surface (no Composer, only runtime deps)

## Current State vs Future State

### Current Implementation ✅

- Local development with volume mounts
- Production with baked-in code
- Multi-stage Dockerfile (builder/production/development)
- MySQL shared between services
- Direct port access (80/8080)
- HTTP only (SSL future)
- Composer monorepo with path repositories
- Environment-based configuration

### Future Enhancements ⏳

- **Traefik reverse proxy**:
  - Automatic SSL via Let's Encrypt
  - Route to multiple services (home, admin, api)
  - Domain-based routing

- **Additional services**:
  - `laravel-api`: Laravel REST API
  - `redis`: Caching layer
  - `elasticsearch`: Search service

- **Shared packages**:
  - `packages/logging`: Centralized logging
  - `packages/utils`: Common utilities
  - Consumed via path repositories

- **CI/CD automation**:
  - Automated testing before deployment
  - Database migrations
  - Rollback capabilities
  - Health checks

- **Monitoring & alerting**:
  - Centralized log aggregation (ELK)
  - Application performance monitoring
  - Database query analysis
  - Uptime monitoring

## Troubleshooting

### Local Development Issues

**Cannot connect to MySQL:**
```bash
cd backend
docker-compose ps
docker-compose logs backend-db

# Verify health check
docker inspect backend-db | grep Health
```

**Changes not reflected:**
```bash
# Verify volume mount
docker-compose exec wp-home-site-php ls -la /var/www/html

# Check opcache settings
docker-compose exec wp-home-site-php php -i | grep opcache
```

**Permission errors:**
```bash
# Fix uploads permissions
docker-compose exec wp-home-site-php chown -R www-data:www-data /var/www/html/web/app/uploads
docker-compose exec wp-home-site-php chmod -R 775 /var/www/html/web/app/uploads
```

### Production Issues

**Service won't start:**
```bash
# Check logs
docker-compose logs -f wp-home-site-php

# Verify environment variables
docker-compose exec wp-home-site-php env | grep WP_

# Check disk space
df -h
```

**Database connection errors:**
```bash
# Verify MySQL is healthy
docker-compose ps

# Test connection from PHP container
docker-compose exec wp-home-site-php mysql -h mysql -u $WP_HOME_DB_USER -p
```

**Deployment fails:**
```bash
# Clean build (remove old images)
docker-compose down
docker system prune -a --volumes
docker-compose build --no-cache
docker-compose up -d
```

## Resources

- [Backend Infrastructure Documentation](./backend-infrastructure.md)
- [Backend README](../../backend/README.md)
- [wp-home-site Service README](../../backend/services/wp-home-site/README.md)
- [Backend Development Conventions](../../backend/claude.md)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Bedrock Documentation](https://roots.io/bedrock/docs/)

## Change Log

- **2026-02-18**: Initial documentation of local vs production environments
