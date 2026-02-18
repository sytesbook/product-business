# Business Sytesbook Monorepo

## Overview

This is a monorepo containing all the backend, frontend, infrastructure code and documentation of the Business Sytesbook product.

## Architecture

The repository is structured as a **multi-ecosystem monorepo** where backend and frontend operate as independent sub-monorepos, each with their own package management and tooling.

### Directory Structure

```
.
├── docs/                    # Documentation
├── infrastructure/          # Terraform and infrastructure code
├── backend/                 # PHP/Composer ecosystem
│   ├── services/           # Containerized microservices
│   ├── packages/           # Shared packages
│   └── tools/              # Development tools
└── frontend/                # TypeScript/PNPM ecosystem
    ├── apps/               # Frontend applications
    ├── packages/           # Shared packages
    └── tools/              # Development tools
```

## Working with the Monorepo

### Backend Development

The backend uses **PHP with Composer** for dependency management. All backend services are containerized and follows microservices architecture.

```bash
cd backend/
# See backend/claude.md for PHP-specific conventions and commands
```

### Frontend Development

The frontend uses **TypeScript with PNPM workspaces** and a centralized version catalog for consistent dependency versions.

```bash
cd frontend/
# See frontend/claude.md for TypeScript-specific conventions and commands
```

### Infrastructure

Infrastructure is managed with **Terraform** and includes all cloud resources, networking, and deployment configurations.

```bash
cd infrastructure/
# See infrastructure/claude.md for Terraform workflow and conventions
```

### Documentation

All technical documentation lives in the [docs](docs/) directory.

## Conventions

### Branching Strategy

- `development` - Integration branch (default)
- `stable/staging` - Stable code for staging
- `stable/production` - Production-ready code
- `feat/*` - Feature branches
- `fix/*` - Bug fix branches
- `test/*` - Testing related branches
