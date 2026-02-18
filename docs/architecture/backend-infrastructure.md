# Backend Infrastructure Architecture

## Overview

The Business Sytesbook backend is a **PHP-based monorepo** with centralized Docker orchestration for microservices. The architecture supports both fast local development with live code reloading and secure, immutable production deployments.

## Monorepo Structure

```
backend/
├── composer.json                # Root orchestrator with monorepo scripts
├── scripts/                     # PHP orchestration scripts
│   ├── composer-foreach.php    # Execute commands across workspaces
│   ├── composer-clean.php      # Clean vendor/ and caches
│   └── composer-list.php       # List all workspaces
├── services/                    # Independent microservices (applications)
│   ├── wp-home-site/           # Bedrock WordPress (home site)
│   └── wp-customer-sites/      # Bedrock WordPress (customer sites)
│       ├── vendor/             # Isolated WordPress dependencies
│       ├── web/                # Public web root
│       ├── config/             # Environment-based config
│       ├── Dockerfile          # Multi-stage PHP 8.3-FPM
│       └── composer.json       # Service dependencies
├── packages/                    # Shared libraries (future)
│   └── (e.g., logging, utilities)
├── tools/                       # Development utilities (future)
├── infrastructure/
│   └── mysql/init/             # Database init scripts
├── docker-compose.yml           # Base services (production-ready)
└── docker-compose.override.example.yml  # Local dev template
```

## Key Design Principles

### 1. Dependency Isolation

Each service maintains complete independence:
- **Own `composer.json`**: Services define only their dependencies
- **Own `vendor/` directory**: No shared dependencies between services
- **No inheritance**: Services don't inherit root dependencies
- **Independent deployment**: Each service can be deployed separately

**Example:**
```
services/wp-home-site/vendor/     # ~200 WordPress packages
services/laravel-api/vendor/      # ~150 Laravel packages (future)
```

Dependencies are duplicated across services, ensuring isolation.

### 2. Path Repositories (Monorepo Sharing)

Shared packages symlinked during development, copied during production build:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../packages/*",
      "options": {"symlink": true}
    }
  ]
}
```

**Development:**
```
services/wp-home-site/vendor/sytesbook/business-logging/ → symlink to packages/logging/
```

**Production:**
```
services/wp-home-site/vendor/sytesbook/business-logging/ → copied code
```

### 3. Multi-Stage Docker Builds

Each service has three build targets:

```dockerfile
FROM php:8.3-fpm-alpine AS builder
# Install build dependencies, Composer, compile assets

FROM builder AS development
# Keep Composer, dev tools, validate timestamps

FROM php:8.3-fpm-alpine AS production
# Minimal runtime, no Composer, immutable code
```

## Current Services

### 1. backend-db (MySQL 8.4 LTS)

**Purpose**: Shared database for all backend services

```yaml
services:
  db:
    image: mysql:8.4
    ports:
      - "3306:3306"  # Exposed in dev, internal in prod
    volumes:
      - db_data:/var/lib/mysql  # Persistent data
      - ./infrastructure/mysql/init:/docker-entrypoint-initdb.d:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping"]
```

**Features:**
- Health checks ensure services wait for database readiness
- Init scripts create databases and users on first startup
- Shared across all services (WordPress sites, future Laravel API, etc.)

**Databases:**
- `wp_home_site_db` - WordPress home site
- `wp_customer_sites_db` - WordPress customer sites

### 2. wp-home-site-php (PHP 8.3-FPM)

**Purpose**: WordPress home site application via Bedrock

```yaml
services:
  wp-home-site-php:
    build:
      context: ./services/wp-home-site
      target: production  # or development in override
    environment:
      DB_HOST: db:3306
      WP_ENV: production
      # ... WordPress config
    volumes:
      - wp_home_uploads:/var/www/html/web/app/uploads
```

**Configuration:**
- **Bedrock structure**: Modern WordPress with Composer
- **Environment-based**: Reads from env vars, not .env files
- **Multi-stage builds**: Development vs production images
- **Opcache tuning**: Production never validates timestamps

**Development vs Production:**
| Aspect | Development | Production |
|--------|-------------|------------|
| Code | Volume-mounted | Baked in image |
| Composer | Included | Removed |
| Opcache | Validates timestamps | Never validates |
| User | root | www-data |

### 3. wp-home-site-nginx (Nginx)

**Purpose**: Web server for home site static files and FastCGI proxy

```yaml
services:
  wp-home-site-nginx:
    image: nginx:alpine
    ports:
      - "8080:80"  # Local: 8080, Production: 80 via Traefik
    volumes:
      - ./services/wp-home-site/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - wp_home_uploads:/var/www/html/web/app/uploads:ro
```

**nginx.conf highlights:**
- Document root: `/var/www/html/web` (Bedrock structure)
- FastCGI to `wp-home-site-php:9000`
- Denies access to sensitive files (`.env`, `composer.json`, etc.)
- Prevents PHP execution in uploads directory
- Security headers (X-Frame-Options, X-Content-Type-Options, etc.)

### 4. wp-customer-sites-php (PHP 8.3-FPM)

**Purpose**: WordPress customer sites application via Bedrock

```yaml
services:
  wp-customer-sites-php:
    build:
      context: ./services/wp-customer-sites
      target: production  # or development in override
    environment:
      DB_HOST: db:3306
      WP_ENV: production
      # ... WordPress config
    volumes:
      - wp_customers_uploads:/var/www/html/web/app/uploads
```

**Configuration:**
- **Bedrock structure**: Modern WordPress with Composer
- **Environment-based**: Reads from env vars, not .env files
- **Multi-stage builds**: Development vs production images
- **Opcache tuning**: Production never validates timestamps

**Development vs Production:**
| Aspect | Development | Production |
|--------|-------------|------------|
| Code | Volume-mounted | Baked in image |
| Composer | Included | Removed |
| Opcache | Validates timestamps | Never validates |
| User | root | www-data |

### 5. wp-customer-sites-nginx (Nginx)

**Purpose**: Web server for customer sites static files and FastCGI proxy

```yaml
services:
  wp-customer-sites-nginx:
    image: nginx:alpine
    ports:
      - "8090:80"  # Local: 8090, Production: 80 via Traefik
    volumes:
      - ./services/wp-customer-sites/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - wp_customers_uploads:/var/www/html/web/app/uploads:ro
```

**nginx.conf highlights:**
- Document root: `/var/www/html/web` (Bedrock structure)
- FastCGI to `wp-customer-sites-php:9000`
- Denies access to sensitive files (`.env`, `composer.json`, etc.)
- Prevents PHP execution in uploads directory
- Security headers (X-Frame-Options, X-Content-Type-Options, etc.)

## Networking

All services communicate via Docker bridge network:

```yaml
networks:
  backend-network:
    driver: bridge
```

**Internal DNS:**
- Services reference each other by container name
- Example: `db:3306`, `wp-home-site-php:9000`

**Port Mapping:**
- **Local dev**: Host `8080` → wp-home-site-nginx `80`, Host `8090` → wp-customer-sites-nginx `80`
- **Production**: Traefik `443` → services `80` (future)

## Storage Volumes

### Persistent Volumes

```yaml
volumes:
  db_data:             # Database files
  wp_home_uploads:     # WordPress home site media uploads
  wp_customers_uploads: # WordPress customer sites media uploads
```

**Volume Sharing:**
- `wp_home_uploads` mounted in both wp-home-site-php (read-write) and wp-home-site-nginx (read-only)
- `wp_customers_uploads` mounted in both wp-customer-sites-php (read-write) and wp-customer-sites-nginx (read-only)
- Ensures uploaded files are served by Nginx but managed by WordPress

**Backup Strategy:**
- MySQL: Export via `mysqldump` or AWS RDS snapshots
- Uploads: Sync to S3 or backup volume snapshot

## Monorepo Orchestration

### Root Commands

Execute operations across all workspaces:

```bash
composer list:workspaces   # Discover all services/packages/tools
composer install:all       # Install dependencies everywhere
composer test              # Run tests in all workspaces
composer lint              # Lint all code
composer quality           # Test + lint + analyse
composer clean             # Remove vendor/ and caches
```

### Orchestration Scripts

**composer-foreach.php**: Sequential execution across workspaces
```bash
php scripts/composer-foreach.php install
# → Discovers services/*, packages/*, tools/*
# → Executes `composer install` in each
# → Aggregates exit codes
```

**composer-clean.php**: Clean all workspaces
```bash
php scripts/composer-clean.php
# → Removes vendor/ directories
# → Removes cache files (.phpunit.cache, .php-cs-fixer.cache)
# → Reports disk space freed
```

**composer-list.php**: Workspace inventory
```bash
php scripts/composer-list.php
# → Lists all services, packages, tools
# → Shows local dependencies (sytesbook/business-*)
# → Displays dependency counts
```

## Future Architecture

### Planned Services

**Traefik (Reverse Proxy):**
```yaml
services:
  traefik:
    image: traefik:v3.0
    ports:
      - "443:443"
    command:
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
    # Automatic HTTPS, routes to multiple services
```

**Additional Services:**
- `laravel-api`: Laravel REST API
- `redis`: Caching layer
- `elasticsearch`: Search service

### Shared Packages

**Example: Logging Package**
```
packages/logging/
├── src/Logger.php
├── composer.json  # Requires: monolog/monolog
└── vendor/        # Isolated dependencies
```

Services consume via path repositories:
```bash
cd services/wp-home-site
composer require sytesbook/business-logging:@dev
```

Result: Package code symlinked, dependencies installed locally.

## Deployment Strategies

### Local Development

**Characteristics:**
- Code volume-mounted from host
- Live reloading (edit → see changes)
- Composer available
- MySQL exposed on localhost:3306
- Direct port access (no Traefik)

**Startup:**
```bash
cd backend
cp docker-compose.override.example.yml docker-compose.override.yml
# Edit with local credentials
docker-compose up -d
```

### Production (EC2)

**Characteristics:**
- Code baked into Docker images (immutable)
- No Composer in final images
- Environment variables from GitHub Secrets
- MySQL internal-only
- Future: Traefik for SSL/routing

**Deployment:**
```bash
# Via GitHub Actions CI/CD
export $(cat github-secrets.env)
docker-compose build
docker-compose up -d
```

**Zero-downtime:**
```bash
# Deploy home site
docker-compose up -d --no-deps --build wp-home-site-php
docker-compose up -d --no-deps --build wp-home-site-nginx

# Deploy customer sites
docker-compose up -d --no-deps --build wp-customer-sites-php
docker-compose up -d --no-deps --build wp-customer-sites-nginx
# Old containers replaced, new ones start
```

## Security Considerations

### Development

- Local credentials in `.gitignored` override file
- MySQL exposed for GUI tools (optional)
- Debug mode enabled
- Runs as root (acceptable for local)

### Production

- GitHub Secrets → environment variables (no config files)
- MySQL internal-only (not exposed to internet)
- Debug mode disabled
- Runs as www-data (non-root)
- Opcache never validates (immutable code)
- Sensitive files outside web root (Bedrock structure)

## Monitoring & Logging

### Container Logs

```bash
docker-compose logs -f wp-home-site-php
docker-compose logs -f backend-db
```

### Health Checks

```yaml
healthcheck:
  test: ["CMD", "mysqladmin", "ping"]
  interval: 10s
  timeout: 20s
  retries: 10
```

Services wait for dependencies via `depends_on` with `condition: service_healthy`.

### Future: Centralized Logging

- Aggregate logs from all containers
- Send to ELK stack or CloudWatch
- Structured logging with context

## Scaling Strategies

### Horizontal Scaling

**Stateless Services** (PHP-FPM, Nginx):
```bash
docker-compose up -d --scale wp-home-site-php=3
```

Requires:
- Load balancer (Traefik with multiple backends)
- Shared session storage (Redis)
- Shared uploads volume (NFS, S3)

**Stateful Services** (MySQL):
- Primary-replica replication
- Read replicas for scaling reads
- AWS RDS for managed solution

### Vertical Scaling

Adjust container resources:
```yaml
services:
  wp-home-site-php:
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
```

## Performance Optimization

### PHP-FPM Tuning

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### Opcache Configuration

**Production:**
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  # Never check for changes
opcache.revalidate_freq=0
```

**Development:**
```ini
opcache.enable=1
opcache.validate_timestamps=1  # Check for changes
opcache.revalidate_freq=2      # Every 2 seconds
```

### MySQL Optimization

```ini
innodb_buffer_pool_size=1G
max_connections=200
query_cache_size=0  # Disabled in MySQL 8.0+
```

## Resources

- [Backend README](../../backend/README.md) - Setup and deployment guide
- [Backend claude.md](../../backend/claude.md) - Development conventions
- [Docker Environments](./docker-environments.md) - Detailed environment comparison
- [wp-home-site README](../../backend/services/wp-home-site/README.md) - Service documentation

## Change Log

- **2026-02-18**: Initial architecture with Composer monorepo, centralized Docker orchestration
- **Future**: Traefik integration, additional services, shared packages
