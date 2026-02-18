# Backend - PHP Monorepo

## Overview

The backend is a **PHP-based microservices architecture** managed by Composer, with shared packages and development tools organized in a monorepo structure.

## Directory Structure

```
backend/
├── services/       # Containerized microservices
│   └── [service-name]/
│       ├── src/
│       ├── tests/
│       ├── composer.json
│       ├── Dockerfile
│       └── README.md
├── packages/       # Shared packages
│   └── [package-name]/
│       ├── src/
│       ├── tests/
│       └── composer.json
└── tools/          # Development tools
    └── [tool-name]/
        ├── src/
        └── composer.json
```

## Technology Stack

- **PHP**: 8.3+
- **Composer**: Dependency management
- **Docker**: Containerization
- **PHPUnit**: Testing framework
- **PHP-CS-Fixer**: Code style
- **PHPStan**: Static analysis

## Development Conventions

### PHP Version

All code must be compatible with **PHP 8.3+**. Use modern PHP features:
- Typed properties
- Constructor property promotion
- Named arguments
- Match expressions
- Enums

### Coding Standards

Follow **PSR-12** coding standards with additional rules:
- Use strict types: `declare(strict_types=1);`
- Type hint everything (parameters, return types, properties)
- Use final classes by default (open for extension only when needed)
- Prefer composition over inheritance
- Keep classes focused (Single Responsibility Principle)

### Naming Conventions

- **Classes**: PascalCase (e.g., `UserService`, `ProductRepository`)
- **Methods**: camelCase (e.g., `getUserById`, `createProduct`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `MAX_RETRY_COUNT`)
- **Interfaces**: PascalCase with `Interface` suffix (e.g., `UserRepositoryInterface`)
- **Traits**: PascalCase with `Trait` suffix (e.g., `TimestampableTrait`)

### Namespaces

- Services: `Sytesbook\Business\Services\[ServiceName]\`
- Packages: `Sytesbook\Business\Packages\[PackageName]\`
- Tools: `Sytesbook\Business\Tools\[ToolName]\`

## Monorepo Structure

The backend uses a **Composer-based monorepo** with path repositories for sharing code between services and packages. Each service maintains complete dependency isolation with its own `vendor/` directory.

### Directory Structure

```
backend/
├── composer.json       # Root orchestrator with monorepo scripts
├── scripts/            # Orchestration scripts for managing workspaces
├── services/           # Independent microservices (applications)
├── packages/           # Shared libraries (consumed by services)
└── tools/              # Development utilities
```

### Dependency Isolation

**IMPORTANT:** Each service has isolated dependencies:
- Each service has its **own** `composer.json` with only its dependencies
- Each service has its **own** `vendor/` directory
- Services do NOT inherit root dependencies
- Root `composer.json` is only for orchestration scripts

When a service requires a local package:
- The package **code** is symlinked from `packages/`
- The package's **dependencies** are installed in the service's `vendor/`
- This means dependencies may be duplicated across services

### Root-Level Commands

Execute operations across all workspaces (services, packages, tools):

```bash
# Install dependencies in all workspaces
composer install:all

# Update dependencies in all workspaces
composer update:all

# Run tests across all workspaces
composer test

# Lint code across all workspaces
composer lint
composer lint:fix

# Static analysis across all workspaces
composer analyse

# Run all quality checks (test, lint, analyse)
composer quality

# Clean vendor directories and caches
composer clean

# List all workspaces
composer list:workspaces

# Validate all composer.json files
composer validate:all
```

These commands use orchestration scripts in `scripts/` to execute operations sequentially across all discovered workspaces.

## Composer Commands

### Initial Setup

```bash
# From backend/ directory
composer install        # Install root orchestration dependencies
composer install:all    # Install dependencies in all workspaces
```

### Managing Dependencies

**Root-level operations** (affects all workspaces):
```bash
composer update:all     # Update all workspaces
composer test          # Test all workspaces
composer quality       # Run all quality checks
```

**Service-level operations** (affects single workspace):
```bash
cd services/wp-home-site
composer require vendor/package           # Add dependency
composer require --dev vendor/package     # Add dev dependency
composer update                          # Update this service only
composer test                           # Test this service only
```

### Working with Local Packages

Services can consume local packages via path repositories. The package code is symlinked but dependencies are isolated.

**1. Create a package:**
```bash
cd packages
mkdir logging
cd logging

# Create composer.json
cat > composer.json << 'EOF'
{
  "name": "sytesbook/business-logging",
  "type": "library",
  "require": {
    "php": "^8.3",
    "monolog/monolog": "^3.5"
  },
  "autoload": {
    "psr-4": {
      "Sytesbook\\Business\\Packages\\Logging\\": "src/"
    }
  }
}
EOF

mkdir -p src
composer install
```

**2. Use package in a service:**
```bash
cd ../../services/wp-home-site

# Add path repository to composer.json
# (or use wildcard: ../../packages/*)
composer config repositories.logging path ../../packages/logging

# Require the package
composer require sytesbook/business-logging:@dev
```

**Result:**
- Package code is symlinked to `vendor/sytesbook/business-logging/`
- Package dependencies (monolog) are installed in service's `vendor/`
- Changes to package code are immediately available (no reinstall needed)

**Path Repository Pattern in composer.json:**
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../packages/*",
      "options": {"symlink": false}
    }
  ],
  "require": {
    "sytesbook/business-logging": "@dev"
  }
}
```

### Running Tests

```bash
# Run all tests in a service
cd services/user-service
composer test

# Run tests with coverage
composer test:coverage

# Run specific test file
vendor/bin/phpunit tests/Unit/UserServiceTest.php
```

### Code Quality

```bash
# Run PHP-CS-Fixer
composer cs:fix

# Check code style without fixing
composer cs:check

# Run PHPStan static analysis
composer analyse

# Run all quality checks
composer quality
```

## Docker Conventions

### Dockerfile Structure

Each service has a Dockerfile with multi-stage builds:

```dockerfile
# Development stage
FROM php:8.3-fpm-alpine AS development
# ... development dependencies

# Production stage
FROM php:8.3-fpm-alpine AS production
# ... minimal production image
```

### Docker Compose

Local development uses Docker Compose:

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f [service-name]

# Run commands in a service
docker-compose exec [service-name] composer install

# Stop all services
docker-compose down
```

## Testing Conventions

### Test Organization

```
tests/
├── Unit/           # Unit tests (isolated, no external dependencies)
├── Integration/    # Integration tests (database, external services)
└── Functional/     # Functional tests (HTTP requests, full flow)
```

### Writing Tests

- Use descriptive test method names: `test_it_creates_user_with_valid_data()`
- One assertion per test when possible
- Use data providers for testing multiple scenarios
- Mock external dependencies in unit tests
- Use test databases for integration tests

### Test Commands

```bash
# Run all tests
composer test

# Run specific test suite
composer test -- --testsuite=unit

# Run with coverage
composer test:coverage

# Run in watch mode (requires phpunit-watcher)
composer test:watch
```

## Package Development

### Creating a New Package

```bash
cd packages
mkdir my-package
cd my-package

# Create composer.json
cat > composer.json << 'EOF'
{
  "name": "sytesbook/business-my-package",
  "description": "Description of the package",
  "type": "library",
  "require": {
    "php": "^8.3"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0"
  },
  "autoload": {
    "psr-4": {
      "Sytesbook\\Business\\Packages\\MyPackage\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Sytesbook\\Business\\Packages\\MyPackage\\Tests\\": "tests/"
    }
  }
}
EOF

# Create directory structure
mkdir -p src tests
```

### Package Guidelines

- Keep packages focused on a single domain
- Document all public APIs
- Include tests with the package
- Semantic versioning for releases
- Minimize external dependencies

## Service Development

### Creating a New Service

```bash
cd services
mkdir my-service
cd my-service

# Set up composer.json, Dockerfile, docker-compose.yml
# Follow existing service patterns
```

### Service Structure

```
my-service/
├── src/
│   ├── Controller/      # HTTP controllers
│   ├── Service/         # Business logic
│   ├── Repository/      # Data access
│   ├── Model/          # Domain models
│   └── Infrastructure/ # Framework-specific code
├── tests/
├── config/             # Configuration files
├── public/             # Web root
├── composer.json
├── Dockerfile
└── docker-compose.yml
```

### Service Guidelines

- Services should be independently deployable
- Use environment variables for configuration
- Implement health check endpoints
- Include OpenAPI/Swagger documentation
- Follow 12-factor app principles

## Environment Configuration

### Local Development

```bash
# Copy environment template
cp .env.example .env

# Edit with your local settings
vim .env
```

### Environment Variables

- `APP_ENV`: application environment (local, staging, production)
- `APP_DEBUG`: debug mode (true/false)
- `DATABASE_URL`: database connection string
- `REDIS_URL`: Redis connection string
- `LOG_LEVEL`: logging level (debug, info, warning, error)

## Database Migrations

```bash
# Create migration
composer migration:create CreateUsersTable

# Run migrations
composer migration:migrate

# Rollback last migration
composer migration:rollback

# Reset database
composer migration:reset
```

## Common Issues

### Composer Memory Limit

If you encounter memory issues:
```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

### Docker Permission Issues

If you have file permission issues with Docker:
```bash
# Add user to docker group
sudo usermod -aG docker $USER

# Or use docker-compose with user mapping
docker-compose run --user $(id -u):$(id -g) [service-name]
```

## Best Practices

1. **Dependency Injection**: Use dependency injection containers
2. **Interface Segregation**: Depend on interfaces, not implementations
3. **Immutability**: Prefer immutable objects where possible
4. **Error Handling**: Use exceptions for exceptional cases, return types for expected failures
5. **Logging**: Use structured logging with context
6. **Security**: Validate all inputs, escape all outputs, use parameterized queries
7. **Performance**: Profile before optimizing, cache appropriately

## Resources

- [PHP Documentation](https://www.php.net/docs.php)
- [PSR Standards](https://www.php-fig.org/psr/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
