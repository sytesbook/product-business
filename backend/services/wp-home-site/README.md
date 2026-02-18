# wp-home-site

WordPress home site service for Business Sytesbook, built with [Bedrock](https://roots.io/bedrock/) - a modern WordPress boilerplate with Composer, improved security, and better project structure.

**Part of the [Business Sytesbook Backend Monorepo](../../README.md)**

## Architecture

This service uses:
- **Bedrock** - Modern WordPress project structure
- **PHP 8.3+** - Latest PHP with strict typing
- **Composer** - Dependency management for WordPress core, plugins, and themes
- **Docker** - Containerized environment (PHP-FPM, shared MySQL, Nginx)
- **Environment-based configuration** - Following 12-factor methodology
- **Monorepo structure** - Part of backend workspace with path repositories for shared packages

## Directory Structure

```
backend/services/wp-home-site/
├── config/                      # Application configuration
│   ├── application.php          # Main configuration
│   └── environments/            # Environment-specific overrides
│       ├── development.php
│       ├── staging.php
│       └── production.php
├── src/                         # Custom PHP code (Sytesbook\Business\Services\WpHomeSite namespace)
├── vendor/                      # Composer dependencies (isolated, not in git)
│   └── sytesbook/              # May include symlinks to ../../packages/* (monorepo packages)
├── web/                         # Public web root
│   ├── app/                     # WordPress content directory
│   │   ├── mu-plugins/         # Must-use plugins (via Composer)
│   │   ├── plugins/            # Regular plugins (via Composer)
│   │   ├── themes/             # Themes (via Composer or custom)
│   │   └── uploads/            # Media uploads (persisted in Docker volume)
│   └── wp/                      # WordPress core (via Composer - roots/wordpress)
├── composer.json                # Service dependencies (roots/wordpress, plugins, themes)
├── composer.lock                # Locked dependency versions (in git)
├── Dockerfile                   # Multi-stage build (builder, production, development)
├── nginx.conf                   # Nginx web server configuration
└── README.md                    # This file

Docker orchestration managed at: ../../docker-compose.yml
```

**Monorepo Structure:**
- This service is part of `/backend` monorepo
- Root orchestrator: `/backend/composer.json`
- Shared packages: `/backend/packages/` (can be consumed via path repositories)
- Docker config: `/backend/docker-compose.yml` (centralized)

## Prerequisites

- Docker 20.10+ & Docker Compose 2.0+
- Composer 2.0+ (for local development and monorepo management)

## Local Development Setup

**Note:** This service uses centralized Docker orchestration. All commands run from `/backend` directory.

### 1. Initial Monorepo Setup

```bash
# From backend/ directory
cd ../../
composer install        # Install root monorepo dependencies
composer install:all    # Install dependencies in all workspaces (including this service)
```

### 2. Configure Environment

```bash
# From backend/ directory
cp docker-compose.override.example.yml docker-compose.override.yml
```

Edit `docker-compose.override.yml` and update:
- Database credentials
- WordPress security keys (generate at https://roots.io/salts.html)
- `WP_HOME` URL (default: http://localhost:8080)

### 3. Start Services

```bash
# From backend/ directory
docker-compose up -d
```

This starts:
- **backend-mysql** - Shared MySQL 8.4 LTS (port 3306)
- **wp-home-site-php** - PHP 8.3-FPM (development build)
- **wp-home-site-nginx** - Nginx web server (port 8080)

### 4. Access WordPress

Navigate to http://localhost:8080 and complete the WordPress installation wizard.

### 5. Stop Services

```bash
# From backend/ directory
docker-compose down

# Remove all data including database:
docker-compose down -v
```

## Managing Plugins and Themes via Composer

Bedrock uses [WordPress Packagist](https://wpackagist.org/) for managing plugins and themes as Composer dependencies.

You can use Composer in two ways:

### Option 1: Local Composer (Recommended)

If you have Composer installed locally, simply run commands from the service directory:

```bash
cd backend/services/wp-home-site

# Install plugins
composer require wpackagist-plugin/wordpress-seo
composer require wpackagist-plugin/contact-form-7
composer require wpackagist-plugin/woocommerce

# Install themes
composer require wpackagist-theme/astra
composer require wpackagist-theme/generatepress
```

### Option 2: Docker Composer

If you don't have Composer installed locally or prefer to use the containerized version:

```bash
# From backend/ directory (not service directory)
cd ../../

# Install plugins
docker-compose exec wp-home-site-php composer require wpackagist-plugin/wordpress-seo

# Install themes
docker-compose exec wp-home-site-php composer require wpackagist-theme/astra
```

**Both approaches work identically** since the service directory is volume-mounted into the container during development.

Plugins are automatically installed to `web/app/plugins/` and themes to `web/app/themes/`. Activate them in WordPress admin.

### Updating WordPress Core, Plugins, and Themes

```bash
# Using local Composer (recommended)
cd backend/services/wp-home-site
composer update                                    # Update all dependencies
composer update roots/wordpress                    # Update WordPress core
composer update wpackagist-plugin/wordpress-seo    # Update specific plugin

# OR using Docker Composer (from backend/ directory)
cd ../../
docker-compose exec wp-home-site-php composer update
docker-compose exec wp-home-site-php composer update roots/wordpress
```

### Private or Custom Plugins/Themes

For custom themes or private plugins not available via WordPress Packagist:

1. **Custom theme**: Create in `web/app/themes/custom-theme-name/`
2. **Custom plugin**: Create in `web/app/plugins/custom-plugin-name/`

These directories are kept in version control (see `.gitignore`).

## Environment Variables

All sensitive configuration is managed via Docker Compose environment variables:

| Variable | Description | Example |
|----------|-------------|---------|
| `DB_NAME` | Database name | `wp_home_site` |
| `DB_USER` | Database user | `wordpress` |
| `DB_PASSWORD` | Database password | `secure_password` |
| `DB_HOST` | Database host | `mysql:3306` |
| `WP_ENV` | Environment type | `development`, `staging`, `production` |
| `WP_HOME` | Site URL | `http://localhost:8080` |
| `WP_SITEURL` | WordPress core URL | `${WP_HOME}/wp` |
| `AUTH_KEY` | WordPress auth key | Generate at https://roots.io/salts.html |
| `SECURE_AUTH_KEY` | WordPress secure auth key | Generate at https://roots.io/salts.html |
| `LOGGED_IN_KEY` | WordPress logged in key | Generate at https://roots.io/salts.html |
| `NONCE_KEY` | WordPress nonce key | Generate at https://roots.io/salts.html |
| `AUTH_SALT` | WordPress auth salt | Generate at https://roots.io/salts.html |
| `SECURE_AUTH_SALT` | WordPress secure auth salt | Generate at https://roots.io/salts.html |
| `LOGGED_IN_SALT` | WordPress logged in salt | Generate at https://roots.io/salts.html |
| `NONCE_SALT` | WordPress nonce salt | Generate at https://roots.io/salts.html |

## Development Workflow

### Adding New Features

1. Install required plugins/themes via Composer
2. Develop custom code in `src/` directory (PSR-4 autoloaded)
3. Create custom themes in `web/app/themes/custom-*/`
4. Test locally with Docker

### Code Standards

Follow Business Sytesbook backend conventions:
- **PHP Version**: 8.3+
- **Code Style**: PSR-12
- **Namespace**: `Sytesbook\Business\Services\WpHomeSite\`
- **Typing**: Strict types, type hints for all parameters and return values
- **Classes**: Final by default unless designed for extension

### Linting

Bedrock includes Laravel Pint for code formatting:

```bash
# Using local Composer
cd backend/services/wp-home-site
composer lint         # Check code style
composer lint:fix     # Fix code style

# OR using Docker Composer (from backend/ directory)
cd ../../
docker-compose exec wp-home-site-php composer lint
docker-compose exec wp-home-site-php composer lint:fix

# Run lint across all monorepo workspaces
composer lint        # From backend/ root
```

## Deployment

### Building for Production

```bash
# Build production Docker image
docker build -t wp-home-site:latest .

# The Dockerfile handles:
# - Installing Composer dependencies (--no-dev)
# - Optimizing autoloader
# - Setting proper permissions
```

### Environment Configuration

For staging/production:

1. Set `WP_ENV` to `staging` or `production`
2. Update `WP_HOME` to production URL
3. Generate new security keys for each environment
4. Use secure, randomly generated database passwords
5. Configure persistent storage for `web/app/uploads/`

## Security Considerations

- **Sensitive files outside web root**: `config/`, `vendor/`, `.env` are not publicly accessible
- **Environment variables**: Never commit `docker-compose.override.yml` (it's gitignored)
- **Security keys**: Generate unique keys per environment
- **WordPress updates**: Regularly run `composer update` to get security patches
- **File uploads**: The Nginx config denies execution of PHP files in uploads directory

## Troubleshooting

**Note:** All docker-compose commands run from `/backend` directory.

### Cannot connect to database

Check that MySQL container is healthy:
```bash
cd ../../
docker-compose ps
docker-compose logs backend-mysql
```

### Permission errors

Reset permissions on uploads directory:
```bash
cd ../../
docker-compose exec wp-home-site-php chown -R www-data:www-data /var/www/html/web/app/uploads
docker-compose exec wp-home-site-php chmod -R 775 /var/www/html/web/app/uploads
```

### WordPress installation loop

Verify environment variables are set correctly in `/backend/docker-compose.override.yml` and restart:
```bash
cd ../../
docker-compose down
docker-compose up -d
```

### View service logs

```bash
cd ../../
docker-compose logs -f wp-home-site-php
docker-compose logs -f wp-home-site-nginx
```

## Resources

- [Bedrock Documentation](https://roots.io/bedrock/docs/)
- [WordPress Packagist](https://wpackagist.org/)
- [Bedrock Security Guide](https://roots.io/bedrock/docs/security/)
- [Business Sytesbook Backend Conventions](../../claude.md)

## License

MIT (for Bedrock-specific code)
