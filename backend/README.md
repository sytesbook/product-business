# Business Sytesbook Backend

A Composer-based monorepo containing all backend services, shared packages, and infrastructure for the Business Sytesbook platform.

## Overview

The backend is structured as a PHP monorepo with:
- **Centralized Docker orchestration** for all microservices
- **Path repositories** for sharing packages between services
- **Complete dependency isolation** - each service has its own `vendor/` directory
- **Multi-stage Docker builds** for development and production

## Monorepo Structure

```
backend/
├── composer.json                # Root orchestrator with monorepo scripts
├── scripts/                     # Orchestration scripts (composer-foreach, composer-clean, etc.)
├── services/                    # Independent microservices (applications)
│   └── wp-home-site/           # Bedrock WordPress site
├── packages/                    # Shared libraries (consumed by services)
│   └── (future shared packages)
├── tools/                       # Development utilities
├── infrastructure/              # Infrastructure configuration
│   └── mysql/init/             # MySQL init scripts
├── docker-compose.yml           # Base Docker services
└── docker-compose.override.example.yml  # Local dev template
```

## Current Architecture

```
┌──────────────────────────────────────────────────────────┐
│                   Browser / Client                        │
└────────────────────────┬─────────────────────────────────┘
                         │
                         │ HTTP (Local: 8080, Prod: 80)
                         ▼
            ┌────────────────────────────┐
            │   wp-home-site-nginx       │
            │   (nginx:alpine)           │
            │   - Serves static files    │
            │   - Routes PHP to FPM      │
            └────────────┬───────────────┘
                         │ FastCGI (9000)
                         ▼
            ┌────────────────────────────┐
            │   wp-home-site-php         │
            │   (PHP 8.3-FPM)            │
            │   - Bedrock WordPress      │
            │   - Multi-stage build      │
            │   - Development/Production │
            └────────────┬───────────────┘
                         │ MySQL (3306)
                         ▼
            ┌────────────────────────────┐
            │   backend-mysql            │
            │   (MySQL 8.4 LTS)          │
            │   - Shared database        │
            │   - Persisted data         │
            └────────────────────────────┘

Network: backend-network (bridge)
```

**Note:** Traefik reverse proxy planned for future multi-service routing and SSL termination.

### Current Services

- **backend-mysql**: Shared MySQL 8.4 LTS instance for all services
- **wp-home-site-php**: PHP 8.3-FPM running Bedrock WordPress
- **wp-home-site-nginx**: Nginx web server for static files and FastCGI proxy

### Future Services

- **Traefik**: Reverse proxy with automatic HTTPS via Let's Encrypt
- **wp-admin-site**: Additional Bedrock WordPress site for admin portal
- **Laravel API**: Laravel-based API service
- More services as needed...

## Quick Start

### Prerequisites

- Docker 20.10+
- Docker Compose 2.0+
- Composer 2.0+ (optional, for local development)

### Initial Setup

1. **Clone and navigate**
   ```bash
   git clone <repository-url>
   cd product-business/backend
   ```

2. **Install root dependencies (monorepo tools)**
   ```bash
   composer install
   ```

3. **Install service dependencies**
   ```bash
   composer install:all
   ```

4. **Configure local environment**
   ```bash
   cp docker-compose.override.example.yml docker-compose.override.yml
   # Edit docker-compose.override.yml with your local values
   ```

5. **Start services**
   ```bash
   docker-compose up -d
   ```

6. **Access WordPress**
   - Local: http://localhost:8080
   - Complete WordPress installation wizard

### Monorepo Commands

Execute operations across all workspaces (services, packages, tools):

```bash
composer list:workspaces      # List all workspaces
composer install:all          # Install dependencies in all workspaces
composer update:all           # Update all workspaces
composer test                 # Run tests across all workspaces
composer lint                 # Lint all workspaces
composer quality              # Run all quality checks
composer clean                # Clean vendor/ and caches
```

See [claude.md](claude.md) for detailed monorepo workflows and conventions.

## Architecture: Local vs Production

The backend uses different configurations for local development and production deployments:

| Aspect | Local Development | Production (EC2) |
|--------|------------------|------------------|
| **Code Location** | Mounted from host | Baked into Docker image |
| **Live Reload** | ✅ Yes (edit files, see changes) | ❌ No (rebuild required) |
| **Build Target** | `development` | `production` |
| **Composer** | Available in container | Not included (removed after build) |
| **Opcache** | Validates timestamps | Never validates (immutable) |
| **Port** | 8080 → 80 | 80 (future: Traefik 443) |
| **MySQL Access** | Exposed on 3306 | Internal only |
| **Debug Mode** | WP_DEBUG=true | WP_DEBUG=false |
| **Config** | docker-compose.override.yml | GitHub Secrets → env vars |

**Key Differences:**
- **Local**: Source code volume-mounted for instant changes, includes dev tools
- **Production**: Code copied during build (immutable deployment), minimal runtime image

For detailed architecture diagrams, see [docs/architecture/docker-environments.md](../docs/architecture/docker-environments.md).

## Local Development Setup

### Prerequisites

- Docker 20.10+
- Docker Compose 2.0+
- Git

### Initial Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd product-business/backend
   ```

2. **Create local environment configuration**
   ```bash
   cp docker-compose.override.example.yml docker-compose.override.yml
   ```

3. **Update `docker-compose.override.yml` with your local values**
   - Set database credentials
   - Configure local domain (e.g., `home.localhost`)
   - Generate WordPress security keys at https://roots.io/salts.html
   - Set `WP_ENV=development`

4. **Build and start services**
   ```bash
   docker-compose up -d
   ```

5. **Check service status**
   ```bash
   docker-compose ps
   ```

6. **View logs**
   ```bash
   docker-compose logs -f wp-home-site-php
   ```

### Accessing Services Locally

- **WordPress Site**: http://localhost:8080 (direct nginx access)
- **MySQL**: localhost:3306 (if port exposed in override file)
- **Traefik Dashboard**: Not yet configured

### Local Development Workflow

#### Code Changes

Code is mounted as a volume in development mode, so changes are reflected immediately:
```yaml
# In docker-compose.override.yml
services:
  wp-home-site-php:
    volumes:
      - ./services/wp-home-site:/var/www/html
```

#### Installing WordPress Plugins/Themes

```bash
cd services/wp-home-site
composer require wpackagist-plugin/wordpress-seo
composer require wpackagist-theme/astra
```

#### Running Composer Commands

You can run Composer either locally or inside the container:

**Local (if you have Composer installed):**
```bash
cd services/wp-home-site
composer install
composer require wpackagist-plugin/akismet
```

**Inside Container:**
```bash
docker-compose exec wp-home-site-php composer install
docker-compose exec wp-home-site-php composer require wpackagist-plugin/akismet
```

Both approaches work identically and produce the same result.

#### Database Access

```bash
# Connect to MySQL
docker-compose exec mysql mysql -u wp_home_user -p wp_home_site

# Import database dump
docker-compose exec -T mysql mysql -u root -p"$MYSQL_ROOT_PASSWORD" wp_home_site < backup.sql
```

### Stopping Services

```bash
# Stop services (keep containers)
docker-compose stop

# Stop and remove containers (keeps volumes/data)
docker-compose down

# Remove everything including volumes (DESTRUCTIVE)
docker-compose down -v
```

## Production Deployment

### Environment Variables

Production deployments use **GitHub Secrets** to inject environment variables. No `.env` or `docker-compose.override.yml` files are needed on the server.

### Required GitHub Secrets

Add these secrets to your GitHub repository (Settings → Secrets and variables → Actions):

#### Infrastructure Secrets
- `DOMAIN` - Your domain (e.g., `sytesbook.com`)
- `ACME_EMAIL` - Email for Let's Encrypt SSL certificates
- `MYSQL_ROOT_PASSWORD` - MySQL root password

#### wp-home-site Secrets
- `WP_HOME_DB_NAME` - Database name (e.g., `wp_home_site`)
- `WP_HOME_DB_USER` - Database user
- `WP_HOME_DB_PASSWORD` - Database password
- `WP_HOME_ENV` - WordPress environment (`production`)
- `WP_HOME_AUTH_KEY` - WordPress auth key
- `WP_HOME_SECURE_AUTH_KEY` - WordPress secure auth key
- `WP_HOME_LOGGED_IN_KEY` - WordPress logged in key
- `WP_HOME_NONCE_KEY` - WordPress nonce key
- `WP_HOME_AUTH_SALT` - WordPress auth salt
- `WP_HOME_SECURE_AUTH_SALT` - WordPress secure auth salt
- `WP_HOME_LOGGED_IN_SALT` - WordPress logged in salt
- `WP_HOME_NONCE_SALT` - WordPress nonce salt

Generate WordPress security keys at https://roots.io/salts.html

### Deployment Workflow

Example GitHub Actions workflow (`.github/workflows/deploy-backend.yml`):

```yaml
name: Deploy Backend

on:
  push:
    branches: [stable/production]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Set up SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.SSH_PRIVATE_KEY }}" > ~/.ssh/id_rsa
          chmod 600 ~/.ssh/id_rsa

      - name: Deploy to EC2
        env:
          DOMAIN: ${{ secrets.DOMAIN }}
          ACME_EMAIL: ${{ secrets.ACME_EMAIL }}
          MYSQL_ROOT_PASSWORD: ${{ secrets.MYSQL_ROOT_PASSWORD }}
          WP_HOME_DB_NAME: ${{ secrets.WP_HOME_DB_NAME }}
          WP_HOME_DB_USER: ${{ secrets.WP_HOME_DB_USER }}
          WP_HOME_DB_PASSWORD: ${{ secrets.WP_HOME_DB_PASSWORD }}
          WP_HOME_ENV: ${{ secrets.WP_HOME_ENV }}
          WP_HOME_AUTH_KEY: ${{ secrets.WP_HOME_AUTH_KEY }}
          WP_HOME_SECURE_AUTH_KEY: ${{ secrets.WP_HOME_SECURE_AUTH_KEY }}
          WP_HOME_LOGGED_IN_KEY: ${{ secrets.WP_HOME_LOGGED_IN_KEY }}
          WP_HOME_NONCE_KEY: ${{ secrets.WP_HOME_NONCE_KEY }}
          WP_HOME_AUTH_SALT: ${{ secrets.WP_HOME_AUTH_SALT }}
          WP_HOME_SECURE_AUTH_SALT: ${{ secrets.WP_HOME_SECURE_AUTH_SALT }}
          WP_HOME_LOGGED_IN_SALT: ${{ secrets.WP_HOME_LOGGED_IN_SALT }}
          WP_HOME_NONCE_SALT: ${{ secrets.WP_HOME_NONCE_SALT }}
        run: |
          ssh -o StrictHostKeyChecking=no ubuntu@${{ secrets.SERVER_IP }} << 'EOF'
            cd /opt/product-business/backend
            git pull origin stable/production

            # Export environment variables from GitHub Secrets
            export DOMAIN="${{ secrets.DOMAIN }}"
            export ACME_EMAIL="${{ secrets.ACME_EMAIL }}"
            # ... export all other variables

            # Build and deploy
            docker-compose build
            docker-compose up -d
          EOF
```

### Production Deployment Steps

1. **Server Setup** (one-time)
   ```bash
   # SSH into EC2 instance
   ssh ubuntu@your-server-ip

   # Install Docker
   curl -fsSL https://get.docker.com -o get-docker.sh
   sh get-docker.sh
   sudo usermod -aG docker ubuntu

   # Install Docker Compose
   sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
   sudo chmod +x /usr/local/bin/docker-compose

   # Clone repository
   sudo mkdir -p /opt/product-business
   sudo chown ubuntu:ubuntu /opt/product-business
   cd /opt/product-business
   git clone <repository-url> .
   ```

2. **Deploy via GitHub Actions**
   - Push to `stable/production` branch
   - GitHub Actions will automatically deploy to EC2
   - Environment variables injected from GitHub Secrets

3. **Manual Deployment** (if needed)
   ```bash
   ssh ubuntu@your-server-ip
   cd /opt/product-business/backend

   # Export environment variables (from secure location)
   export DOMAIN="sytesbook.com"
   export ACME_EMAIL="admin@sytesbook.com"
   # ... export all other variables

   # Build and deploy
   docker-compose build
   docker-compose up -d
   ```

### Production Build Strategy

Production images are built with **code baked in** (immutable):
- Dockerfile uses `production` target by default
- All dependencies installed during build
- No volume mounts for code (code is copied into image)
- Opcache configured for maximum performance (`validate_timestamps=0`)

```dockerfile
# In Dockerfile - production stage
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html
```

### Zero-Downtime Deployments

```bash
# Build new images
docker-compose build

# Start new containers without stopping old ones
docker-compose up -d --no-deps --build wp-home-site-php
docker-compose up -d --no-deps --build wp-home-site-nginx

# Old containers automatically replaced
```

## Adding New Services

### Adding a New WordPress Site

1. **Create service directory**
   ```bash
   cd services/
   composer create-project roots/bedrock wp-admin-site
   cd wp-admin-site
   ```

2. **Update service configuration**
   - Create `Dockerfile` (copy from wp-home-site and adjust)
   - Create `nginx.conf` (copy from wp-home-site and adjust)
   - Update `composer.json` with correct namespace

3. **Add to `docker-compose.yml`**
   ```yaml
   wp-admin-site-php:
     build:
       context: ./services/wp-admin-site
       dockerfile: Dockerfile
       target: production
     container_name: wp-admin-site-php
     restart: unless-stopped
     environment:
       DB_NAME: ${WP_ADMIN_DB_NAME}
       DB_USER: ${WP_ADMIN_DB_USER}
       DB_PASSWORD: ${WP_ADMIN_DB_PASSWORD}
       DB_HOST: mysql:3306
       WP_ENV: ${WP_ADMIN_ENV}
       WP_HOME: https://admin.${DOMAIN}
       WP_SITEURL: https://admin.${DOMAIN}/wp
       # ... security keys
     volumes:
       - wp_admin_uploads:/var/www/html/web/app/uploads
     depends_on:
       mysql:
         condition: service_healthy
     networks:
       - backend-network

   wp-admin-site-nginx:
     image: nginx:alpine
     container_name: wp-admin-site-nginx
     restart: unless-stopped
     volumes:
       - ./services/wp-admin-site/nginx.conf:/etc/nginx/conf.d/default.conf:ro
       - wp_admin_uploads:/var/www/html/web/app/uploads:ro
     depends_on:
       - wp-admin-site-php
     networks:
       - backend-network
     labels:
       - "traefik.enable=true"
       - "traefik.http.routers.wp-admin.rule=Host(`admin.${DOMAIN}`)"
       - "traefik.http.routers.wp-admin.entrypoints=websecure"
       - "traefik.http.routers.wp-admin.tls.certresolver=letsencrypt"
       - "traefik.http.services.wp-admin.loadbalancer.server.port=80"
   ```

4. **Add database initialization**
   Update `infrastructure/mysql/init/01-create-databases.sql`:
   ```sql
   CREATE DATABASE IF NOT EXISTS `wp_admin_site` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   Update `infrastructure/mysql/init/02-create-users.sh` to create user from env vars.

5. **Add to `docker-compose.override.example.yml`**
   ```yaml
   wp-admin-site-php:
     build:
       target: development
     volumes:
       - ./services/wp-admin-site:/var/www/html
     environment:
       WP_ENV: development
       WP_HOME: http://admin.localhost
       WP_SITEURL: http://admin.localhost/wp
       # ... local config
   ```

6. **Update volumes section**
   ```yaml
   volumes:
     wp_admin_uploads:
       driver: local
   ```

### Adding a Laravel Service

Similar process, but:
- Use Laravel's official Docker image or create custom Dockerfile
- Configure Laravel environment variables
- Update MySQL init scripts for Laravel database
- Configure Traefik routing for API endpoints

## Traefik Configuration

### Domain Routing

Traefik uses **labels** on nginx containers to route traffic:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.wp-home.rule=Host(`home.${DOMAIN}`)"
  - "traefik.http.routers.wp-home.entrypoints=websecure"
  - "traefik.http.routers.wp-home.tls.certresolver=letsencrypt"
  - "traefik.http.services.wp-home.loadbalancer.server.port=80"
```

### SSL/TLS Certificates

Traefik automatically obtains Let's Encrypt certificates using HTTP challenge:
- Certificates stored in `traefik_letsencrypt` volume
- Automatically renewed before expiration
- Email notifications sent to `ACME_EMAIL`

### Accessing Traefik Dashboard

For debugging (disabled in production by default):

```yaml
# In docker-compose.override.yml
traefik:
  command:
    - "--api.dashboard=true"
    - "--api.insecure=true"  # Remove for production!
  ports:
    - "8080:8080"  # Dashboard port
```

Access at: http://localhost:8080

## Database Management

### Backup Database

```bash
# Backup single database
docker-compose exec mysql mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" wp_home_site > wp_home_site_backup.sql

# Backup all databases
docker-compose exec mysql mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --all-databases > all_databases_backup.sql
```

### Restore Database

```bash
docker-compose exec -T mysql mysql -u root -p"$MYSQL_ROOT_PASSWORD" wp_home_site < wp_home_site_backup.sql
```

### MySQL Configuration

MySQL is configured with:
- Character set: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Health check with automatic retries
- Data persistence via `mysql_data` volume

## Monitoring

### View Container Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f wp-home-site-php

# Last 100 lines
docker-compose logs --tail=100 wp-home-site-nginx
```

### Container Resource Usage

```bash
docker stats
```

### Check Service Health

```bash
docker-compose ps
docker inspect backend-mysql | grep -A 10 Health
```

## Troubleshooting

### Service Won't Start

```bash
# Check logs
docker-compose logs wp-home-site-php

# Rebuild service
docker-compose up -d --build wp-home-site-php

# Check configuration syntax
docker-compose config
```

### Database Connection Issues

```bash
# Verify MySQL is running
docker-compose ps mysql

# Check MySQL health
docker inspect backend-mysql | grep -A 10 Health

# Test connection
docker-compose exec wp-home-site-php php -r "mysqli_connect('mysql', 'wp_home_user', 'password', 'wp_home_site') or die(mysqli_connect_error());"
```

### SSL Certificate Issues

```bash
# Check Traefik logs
docker-compose logs traefik

# Verify certificate storage
docker volume inspect backend_traefik_letsencrypt

# Use Let's Encrypt staging for testing
# Add to Traefik command:
- "--certificatesresolvers.letsencrypt.acme.caserver=https://acme-staging-v02.api.letsencrypt.org/directory"
```

### Permission Issues

```bash
# Fix uploads directory permissions
docker-compose exec wp-home-site-php chown -R www-data:www-data /var/www/html/web/app/uploads
docker-compose exec wp-home-site-php chmod -R 775 /var/www/html/web/app/uploads
```

## Performance Optimization

### Production PHP Configuration

Opcache is configured for maximum performance:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  # Never check for file changes
opcache.revalidate_freq=0
```

### MySQL Optimization

Consider tuning MySQL configuration for your workload:
```yaml
mysql:
  command:
    - --innodb-buffer-pool-size=1G
    - --max-connections=200
```

### Nginx Caching

Static assets are cached with maximum expiration:
```nginx
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires max;
    log_not_found off;
    access_log off;
}
```

## Security Best Practices

1. **Never commit secrets**
   - Keep `docker-compose.override.yml` gitignored
   - Use GitHub Secrets for production
   - Rotate credentials regularly

2. **Use strong passwords**
   - Generate random passwords (32+ characters)
   - Different passwords for each database user
   - Use password manager for credential storage

3. **Keep dependencies updated**
   ```bash
   cd services/wp-home-site
   composer update
   ```

4. **Regular backups**
   - Daily automated database backups
   - Weekly full volume backups
   - Test restore procedures

5. **Monitor logs**
   - Regular security log reviews
   - Set up log aggregation (e.g., ELK stack)
   - Alert on suspicious activity

## Additional Resources

- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Traefik v3 Documentation](https://doc.traefik.io/traefik/)
- [Bedrock Documentation](https://roots.io/bedrock/)
- [WordPress Packagist](https://wpackagist.org/)
- [MySQL 8.4 Documentation](https://dev.mysql.com/doc/refman/8.4/en/)

## Support

For service-specific documentation, see:
- [services/wp-home-site/README.md](services/wp-home-site/README.md)
