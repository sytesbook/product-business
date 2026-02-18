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
│   ├── wp-home-site/           # Bedrock WordPress (home site)
│   └── wp-customer-sites/      # Bedrock WordPress (customer sites)
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
│    - Home site: http://localhost:8080                    │
│    - Customer sites: http://localhost:8090               │
└────────────┬────────────────────┬────────────────────────┘
             │                    │
   HTTP (8080)                    │ HTTP (8090)
             ▼                    ▼
┌────────────────────┐ ┌────────────────────┐
│ wp-home-site-nginx │ │wp-customer-sites-  │
│ (nginx:alpine)     │ │nginx (nginx:alpine)│
│ - Static files     │ │ - Static files     │
│ - Routes PHP to FPM│ │ - Routes PHP to FPM│
└─────────┬──────────┘ └─────────┬──────────┘
          │ FastCGI (9000)        │ FastCGI (9000)
          ▼                       ▼
┌────────────────────┐ ┌────────────────────┐
│ wp-home-site-php   │ │wp-customer-sites-  │
│ (PHP 8.3-FPM)      │ │php (PHP 8.3-FPM)   │
│ - Bedrock WP       │ │ - Bedrock WP       │
│ - Multi-stage build│ │ - Multi-stage build│
│ - Dev/Production   │ │ - Dev/Production   │
└─────────┬──────────┘ └─────────┬──────────┘
          │ MySQL (3306)          │ MySQL (3306)
          └───────────┬───────────┘
                      ▼
          ┌────────────────────────┐
          │   backend-db           │
          │   (MySQL 8.4 LTS)      │
          │   - Shared database    │
          │   - wp_home_site_db       │
          │   - wp_customer_sites_db  │
          │   - Persisted data     │
          └────────────────────────┘

Network: backend-network (bridge)
```

**Note:** Traefik reverse proxy planned for future multi-subdomain routing (home.${DOMAIN}, customers.${DOMAIN}) and SSL termination.

### Current Services

- **backend-db**: Shared MySQL 8.4 LTS instance with `wp_home_site_db` and `wp_customer_sites_db` databases
- **wp-home-site-php**: PHP 8.3-FPM running Bedrock WordPress (home site)
- **wp-home-site-nginx**: Nginx web server for home site static files and FastCGI proxy
- **wp-customer-sites-php**: PHP 8.3-FPM running Bedrock WordPress (customer sites)
- **wp-customer-sites-nginx**: Nginx web server for customer sites static files and FastCGI proxy

### Future Services

- **Traefik**: Reverse proxy with automatic HTTPS via Let's Encrypt
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
   # Create .env file for Docker Compose variable interpolation
   cp .env.example .env
   # Edit .env with your values (or keep defaults for local dev)

   # Create docker-compose override for container configuration
   cp docker-compose.override.example.yml docker-compose.override.yml
   # Edit docker-compose.override.yml with your local values
   ```

5. **Start services**
   ```bash
   docker-compose up -d
   ```

6. **WordPress is automatically installed!**
   - WordPress automatically installs on first startup using WP CLI
   - No installation wizard needed - sites are immediately ready to use
   - Default admin credentials (from docker-compose.override.yml):
     - Username: `admin`
     - Password: `local_wp_password` (both home site and customer sites)
   - Access sites:
     - Home site: http://localhost:8080
     - Customer sites: http://localhost:8090
   - Admin dashboards:
     - Home site: http://localhost:8080/wp/wp-admin
     - Customer sites: http://localhost:8090/wp/wp-admin

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
   # Create .env file for Docker Compose variable interpolation
   cp .env.example .env

   # Create docker-compose override for container configuration
   cp docker-compose.override.example.yml docker-compose.override.yml
   ```

3. **Update `docker-compose.override.yml` with your local values**
   - Set database credentials
   - Configure local domain (e.g., `home.localhost`)
   - Generate WordPress security keys at https://roots.io/salts.html (use only alphanumeric characters)
   - Set WordPress admin credentials (WP_ADMIN_USER, WP_ADMIN_PASSWORD, WP_ADMIN_EMAIL)
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

- **WordPress Home Site**: http://localhost:8080 (direct nginx access)
- **WordPress Customer Sites**: http://localhost:8090 (direct nginx access)
- **MySQL**: localhost:3306 (if port exposed in override file)
- **Traefik Dashboard**: Not yet configured

### Automatic WordPress Installation

WordPress is automatically installed on first container startup using WP CLI. This eliminates the need to manually complete the installation wizard.

**How it works:**
1. Container starts and waits for database to be ready
2. Checks if WordPress is already installed (using `wp core is-installed`)
3. If not installed, runs `wp core install` with credentials from environment variables
4. Starts PHP-FPM

**Configuration:**
WordPress installation is configured via environment variables:
- `WP_ADMIN_USER` - Admin username (default: `admin`)
- `WP_ADMIN_PASSWORD` - Admin password
- `WP_ADMIN_EMAIL` - Admin email
- `WP_SITE_TITLE` - Site title

**Default credentials for local development:**
- Home site: `admin` / `local_wp_password`
- Customer sites: `admin` / `local_wp_password`

**Idempotency:**
The installation is idempotent - restarting containers will NOT reinstall WordPress if it's already installed. You can safely restart services without losing data.

**Troubleshooting:**
- View installation logs: `docker-compose logs wp-home-site-php`
- Check WordPress status: `docker-compose exec wp-home-site-php wp core is-installed`
- Verify database connection: `docker-compose exec wp-home-site-php wp db check`

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
# Connect to home site database
docker-compose exec db mysql -u wp_home_db_user -p wp_home_site_db

# Connect to customer sites database
docker-compose exec db mysql -u wp_customers_user -p wp_customer_sites_db

# Import database dump (home site)
docker-compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" wp_home_site_db < backup.sql

# Import database dump (customer sites)
docker-compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" wp_customer_sites_db < backup.sql
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
- `WP_HOME_DB_NAME` - Database name (e.g., `wp_home_site_db`)
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

#### wp-customer-sites Secrets
- `WP_CUSTOMERS_DB_NAME` - Database name (e.g., `wp_customer_sites_db`)
- `WP_CUSTOMERS_DB_USER` - Database user
- `WP_CUSTOMERS_DB_PASSWORD` - Database password
- `WP_CUSTOMERS_ENV` - WordPress environment (`production`)
- `WP_CUSTOMERS_AUTH_KEY` - WordPress auth key
- `WP_CUSTOMERS_SECURE_AUTH_KEY` - WordPress secure auth key
- `WP_CUSTOMERS_LOGGED_IN_KEY` - WordPress logged in key
- `WP_CUSTOMERS_NONCE_KEY` - WordPress nonce key
- `WP_CUSTOMERS_AUTH_SALT` - WordPress auth salt
- `WP_CUSTOMERS_SECURE_AUTH_SALT` - WordPress secure auth salt
- `WP_CUSTOMERS_LOGGED_IN_SALT` - WordPress logged in salt
- `WP_CUSTOMERS_NONCE_SALT` - WordPress nonce salt

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
          # wp-home-site
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
          # wp-customer-sites
          WP_CUSTOMERS_DB_NAME: ${{ secrets.WP_CUSTOMERS_DB_NAME }}
          WP_CUSTOMERS_DB_USER: ${{ secrets.WP_CUSTOMERS_DB_USER }}
          WP_CUSTOMERS_DB_PASSWORD: ${{ secrets.WP_CUSTOMERS_DB_PASSWORD }}
          WP_CUSTOMERS_ENV: ${{ secrets.WP_CUSTOMERS_ENV }}
          WP_CUSTOMERS_AUTH_KEY: ${{ secrets.WP_CUSTOMERS_AUTH_KEY }}
          WP_CUSTOMERS_SECURE_AUTH_KEY: ${{ secrets.WP_CUSTOMERS_SECURE_AUTH_KEY }}
          WP_CUSTOMERS_LOGGED_IN_KEY: ${{ secrets.WP_CUSTOMERS_LOGGED_IN_KEY }}
          WP_CUSTOMERS_NONCE_KEY: ${{ secrets.WP_CUSTOMERS_NONCE_KEY }}
          WP_CUSTOMERS_AUTH_SALT: ${{ secrets.WP_CUSTOMERS_AUTH_SALT }}
          WP_CUSTOMERS_SECURE_AUTH_SALT: ${{ secrets.WP_CUSTOMERS_SECURE_AUTH_SALT }}
          WP_CUSTOMERS_LOGGED_IN_SALT: ${{ secrets.WP_CUSTOMERS_LOGGED_IN_SALT }}
          WP_CUSTOMERS_NONCE_SALT: ${{ secrets.WP_CUSTOMERS_NONCE_SALT }}
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

**Example**: Adding a third WordPress site (following the pattern of existing wp-home-site and wp-customer-sites)

1. **Create service directory**
   ```bash
   cd services/
   composer create-project roots/bedrock wp-additional-site
   cd wp-additional-site
   ```

2. **Update service configuration**
   - Create `Dockerfile` (copy from wp-home-site or wp-customer-sites and adjust)
   - Create `nginx.conf` (copy from existing service and adjust)
   - Update `composer.json` with correct namespace

3. **Add to `docker-compose.yml`**
   ```yaml
   wp-additional-site-php:
     build:
       context: ./services/wp-additional-site
       dockerfile: Dockerfile
       target: production
     container_name: wp-additional-site-php
     restart: unless-stopped
     environment:
       DB_NAME: ${WP_ADDITIONAL_DB_NAME}
       DB_USER: ${WP_ADDITIONAL_DB_USER}
       DB_PASSWORD: ${WP_ADDITIONAL_DB_PASSWORD}
       DB_HOST: db:3306
       WP_ENV: ${WP_ADDITIONAL_ENV}
       WP_HOME: https://additional.${DOMAIN}
       WP_SITEURL: https://additional.${DOMAIN}/wp
       # ... security keys
     volumes:
       - wp_additional_uploads:/var/www/html/web/app/uploads
     depends_on:
       db:
         condition: service_healthy
     networks:
       - backend-network

   wp-additional-site-nginx:
     image: nginx:alpine
     container_name: wp-additional-site-nginx
     restart: unless-stopped
     volumes:
       - ./services/wp-additional-site/nginx.conf:/etc/nginx/conf.d/default.conf:ro
       - wp_additional_uploads:/var/www/html/web/app/uploads:ro
     depends_on:
       - wp-additional-site-php
     networks:
       - backend-network
     labels:
       - "traefik.enable=true"
       - "traefik.http.routers.wp-additional.rule=Host(`additional.${DOMAIN}`)"
       - "traefik.http.routers.wp-additional.entrypoints=websecure"
       - "traefik.http.routers.wp-additional.tls.certresolver=letsencrypt"
       - "traefik.http.services.wp-additional.loadbalancer.server.port=80"
   ```

4. **Add database initialization**
   Update `infrastructure/mysql/init/01-create-databases.sql`:
   ```sql
   CREATE DATABASE IF NOT EXISTS `wp_additional_site` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   Update `infrastructure/mysql/init/02-create-users.sh` to create user from env vars.

5. **Add to `docker-compose.override.example.yml`**
   ```yaml
   wp-additional-site-php:
     build:
       target: development
     volumes:
       - ./services/wp-additional-site:/var/www/html
     environment:
       WP_ENV: development
       WP_HOME: http://additional.localhost
       WP_SITEURL: http://additional.localhost/wp
       # ... local config
   ```

6. **Update volumes section**
   ```yaml
   volumes:
     wp_additional_uploads:
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
# Home site
wp-home-site-nginx:
  labels:
    - "traefik.enable=true"
    - "traefik.http.routers.wp-home.rule=Host(`home.${DOMAIN}`)"
    - "traefik.http.routers.wp-home.entrypoints=websecure"
    - "traefik.http.routers.wp-home.tls.certresolver=letsencrypt"
    - "traefik.http.services.wp-home.loadbalancer.server.port=80"

# Customer sites
wp-customer-sites-nginx:
  labels:
    - "traefik.enable=true"
    - "traefik.http.routers.wp-customers.rule=Host(`customers.${DOMAIN}`)"
    - "traefik.http.routers.wp-customers.entrypoints=websecure"
    - "traefik.http.routers.wp-customers.tls.certresolver=letsencrypt"
    - "traefik.http.services.wp-customers.loadbalancer.server.port=80"
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
# Backup home site database
docker-compose exec db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" wp_home_site_db > wp_home_site_backup.sql

# Backup customer sites database
docker-compose exec db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" wp_customer_sites_db > wp_customer_sites_backup.sql

# Backup all databases
docker-compose exec db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --all-databases > all_databases_backup.sql
```

### Restore Database

```bash
docker-compose exec -T db mysql -u root -p"$MYSQL_ROOT_PASSWORD" wp_home_site_db < wp_home_site_backup.sql
```

### MySQL Configuration

MySQL is configured with:
- Character set: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Health check with automatic retries
- Data persistence via `db_data` volume

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
docker inspect backend-db | grep -A 10 Health
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
docker-compose ps db

# Check MySQL health
docker inspect backend-db | grep -A 10 Health

# Test connection
docker-compose exec wp-home-site-php php -r "mysqli_connect('mysql', 'wp_home_db_user', 'password', 'wp_home_site_db') or die(mysqli_connect_error());"
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

### Upload Permission Issues in Development

**Symptom**: "Unable to create directory uploads/2026/02. Is its parent directory writable by the server?" when uploading media in WordPress.

**Cause**: In development mode, the entire codebase is mounted from the host filesystem. Host permissions (owned by your local user) override container permissions (expected to be `www-data`). PHP-FPM workers run as `www-data` and cannot write to directories owned by your host user.

**Solution**: The entrypoint script (`scripts/docker-wp-install.sh`) automatically fixes permissions on container startup. Simply restart the container:

```bash
docker-compose restart wp-home-site-php
# or
docker-compose restart wp-customer-sites-php
```

**Note**: This is only an issue in development. In production, code is baked into the Docker image with correct permissions, and uploads are stored in a dedicated Docker volume (`wp_home_uploads` or `wp_customers_uploads`).

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
- [services/wp-customer-sites/README.md](services/wp-customer-sites/README.md)
