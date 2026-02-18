# Frontend - TypeScript Monorepo

## Overview

The frontend is a **TypeScript-based monorepo** managed by PNPM workspaces, with applications, shared packages, and development tools using a centralized version catalog.

## Directory Structure

```
frontend/
├── apps/           # Frontend applications
│   └── [app-name]/
│       ├── src/
│       ├── public/
│       ├── tests/
│       ├── package.json
│       └── README.md
├── packages/       # Shared packages
│   └── [package-name]/
│       ├── src/
│       ├── tests/
│       └── package.json
├── tools/          # Development tools
│   └── [tool-name]/
│       ├── src/
│       └── package.json
├── package.json           # Root package with version catalog
└── pnpm-workspace.yaml   # Workspace configuration
```

## Technology Stack

- **TypeScript**: 5.6+
- **PNPM**: Package management with workspaces
- **React**: 18.3+ (for UI applications)
- **Vite**: Build tool and dev server
- **Vitest**: Testing framework
- **ESLint**: Code linting
- **Prettier**: Code formatting

## Development Conventions

### TypeScript Configuration

All packages use strict TypeScript configuration:

```json
{
  "compilerOptions": {
    "strict": true,
    "noUncheckedIndexedAccess": true,
    "noImplicitOverride": true,
    "verbatimModuleSyntax": true,
    "esModuleInterop": false,
    "skipLibCheck": true
  }
}
```

### Naming Conventions

- **Files**: kebab-case (e.g., `user-profile.tsx`, `api-client.ts`)
- **Components**: PascalCase (e.g., `UserProfile`, `NavigationBar`)
- **Functions**: camelCase (e.g., `getUserData`, `formatDate`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `API_BASE_URL`)
- **Types/Interfaces**: PascalCase (e.g., `User`, `ApiResponse`)

### Code Organization

```
src/
├── components/     # React components
├── hooks/          # Custom React hooks
├── utils/          # Utility functions
├── types/          # TypeScript type definitions
├── lib/            # Third-party library configurations
├── features/       # Feature-based modules
└── pages/          # Page components (for routing)
```

## PNPM Commands

### Installation

```bash
# Install all dependencies
pnpm install

# Install for specific workspace
pnpm --filter @sytesbook/business-web install

# Add dependency to a specific workspace
pnpm --filter @sytesbook/business-web add react-query

# Add dependency to root
pnpm add -w typescript
```

### Version Catalog

Dependencies are managed in the [pnpm-workspace.yaml](pnpm-workspace.yaml) catalog with concrete version numbers:

```yaml
catalog:
  react: 18.3.1
  react-dom: 18.3.1
  typescript: 5.6.3
  vite: 5.4.11
  '@types/react': 18.3.12
  '@types/react-dom': 18.3.1
  '@types/node': 22.10.2
  vitest: 2.1.8
  eslint: 9.17.0
  prettier: 3.4.2
```

Use catalog versions in workspace packages:

```json
{
  "dependencies": {
    "react": "catalog:",
    "typescript": "catalog:"
  }
}
```

### Running Scripts

```bash
# Run script in all workspaces
pnpm -r build

# Run script in parallel
pnpm -r --parallel dev

# Run script in specific workspace
pnpm --filter @sytesbook/business-web dev

# Run script in workspace and dependencies
pnpm --filter @sytesbook/business-web... build
```

### Common Commands

```bash
# Development
pnpm dev              # Start all apps in dev mode
pnpm build            # Build all packages and apps
pnpm test             # Run all tests
pnpm lint             # Lint all code
pnpm type-check       # Type check all TypeScript
pnpm clean            # Clean all build artifacts

# Specific workspace
pnpm --filter @sytesbook/business-web dev
pnpm --filter @sytesbook/business-ui test
```

## Workspace Management

### Creating a New Package

```bash
cd packages
mkdir my-package
cd my-package

# Create package.json
cat > package.json << 'EOF'
{
  "name": "@sytesbook/business-my-package",
  "version": "0.0.0",
  "private": true,
  "type": "module",
  "main": "./src/index.ts",
  "types": "./src/index.ts",
  "scripts": {
    "build": "tsc",
    "test": "vitest",
    "lint": "eslint src"
  },
  "dependencies": {
    "typescript": "catalog:"
  },
  "devDependencies": {
    "vitest": "catalog:"
  }
}
EOF

# Create directory structure
mkdir -p src tests
```

### Creating a New App

```bash
cd apps
pnpm create vite@latest my-app --template react-ts
cd my-app

# Update package.json name
# "name": "@sytesbook/business-my-app"
```

### Package Naming

- **Apps**: `@sytesbook/business-[name]` (e.g., `@sytesbook/business-web`, `@sytesbook/business-mobile`)
- **Packages**: `@sytesbook/business-[name]` (e.g., `@sytesbook/business-ui`, `@sytesbook/business-utils`)
- **Tools**: `@sytesbook/business-[name]` (e.g., `@sytesbook/business-eslint-config`)

## Testing Conventions

### Test Organization

```
tests/
├── unit/           # Unit tests
├── integration/    # Integration tests
└── e2e/            # End-to-end tests
```

### Writing Tests

```typescript
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { UserProfile } from './user-profile';

describe('UserProfile', () => {
  it('renders user name', () => {
    render(<UserProfile name="John Doe" />);
    expect(screen.getByText('John Doe')).toBeInTheDocument();
  });
});
```

### Test Commands

```bash
# Run all tests
pnpm test

# Run tests in watch mode
pnpm test --watch

# Run tests with coverage
pnpm test --coverage

# Run tests in specific workspace
pnpm --filter @sytesbook/business-web test
```

## Building and Bundling

### Development Build

```bash
# Start dev server with hot reload
pnpm dev

# Start specific app
pnpm --filter @sytesbook/business-web dev
```

### Production Build

```bash
# Build all packages and apps
pnpm build

# Build specific app
pnpm --filter @sytesbook/business-web build

# Build with dependencies
pnpm --filter @sytesbook/business-web... build
```

### Build Output

- Apps: `dist/` directory with optimized bundles
- Packages: `dist/` directory with compiled TypeScript

## Code Quality

### Linting

```bash
# Lint all code
pnpm lint

# Auto-fix issues
pnpm lint:fix

# Lint specific workspace
pnpm --filter @sytesbook/business-web lint
```

### Formatting

```bash
# Format all code
pnpm format

# Check formatting
pnpm format:check

# Format specific files
pnpm format src/**/*.ts
```

### Type Checking

```bash
# Type check all workspaces
pnpm type-check

# Type check specific workspace
pnpm --filter @sytesbook/business-web type-check
```

## React Conventions

### Component Structure

```typescript
import type { ReactNode } from 'react';

interface UserProfileProps {
  name: string;
  avatar?: string;
  children?: ReactNode;
}

export function UserProfile({ name, avatar, children }: UserProfileProps) {
  return (
    <div className="user-profile">
      {avatar && <img src={avatar} alt={name} />}
      <h2>{name}</h2>
      {children}
    </div>
  );
}
```

### Hooks

- Use built-in hooks when possible
- Create custom hooks for reusable logic
- Prefix custom hooks with `use` (e.g., `useUserData`)
- Keep hooks pure and side-effect free when possible

### State Management

- Use React's built-in state for simple cases
- Use Context API for shared state
- Consider external libraries (Zustand, Jotai) for complex state

## Import Conventions

### Path Aliases

Configure path aliases in `tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["./src/*"],
      "@components/*": ["./src/components/*"],
      "@utils/*": ["./src/utils/*"]
    }
  }
}
```

### Import Order

1. External dependencies
2. Internal packages
3. Relative imports
4. Type imports

```typescript
import { useState } from 'react';
import { Button } from '@sytesbook/business-ui';
import { formatDate } from './utils';
import type { User } from './types';
```

## Environment Variables

### Configuration

Create `.env` files:

```bash
# .env.local (for local development, not committed)
VITE_API_URL=http://localhost:3000
VITE_API_KEY=your-key-here

# .env.production (for production)
VITE_API_URL=https://api.production.com
```

### Usage

```typescript
const apiUrl = import.meta.env.VITE_API_URL;
```

**Important**: Prefix environment variables with `VITE_` to expose them to the client.

## Performance Optimization

### Code Splitting

```typescript
import { lazy, Suspense } from 'react';

const UserProfile = lazy(() => import('./components/user-profile'));

function App() {
  return (
    <Suspense fallback={<div>Loading...</div>}>
      <UserProfile />
    </Suspense>
  );
}
```

### Memoization

```typescript
import { memo, useMemo, useCallback } from 'react';

const ExpensiveComponent = memo(({ data }) => {
  const processed = useMemo(() => processData(data), [data]);
  const handler = useCallback(() => handleClick(processed), [processed]);

  return <button onClick={handler}>{processed}</button>;
});
```

## Common Issues

### PNPM Link Issues

```bash
# Clear PNPM cache
pnpm store prune

# Reinstall dependencies
rm -rf node_modules
pnpm install
```

### Type Errors

```bash
# Clear TypeScript cache
rm -rf tsconfig.tsbuildinfo
pnpm type-check
```

### Build Errors

```bash
# Clean and rebuild
pnpm clean
pnpm install
pnpm build
```

## Best Practices

1. **Type Safety**: Use TypeScript strictly, avoid `any`
2. **Component Size**: Keep components small and focused
3. **Prop Drilling**: Avoid excessive prop drilling, use composition
4. **Performance**: Profile before optimizing, use React DevTools
5. **Accessibility**: Use semantic HTML, add ARIA labels
6. **Testing**: Test user behavior, not implementation details
7. **Dependencies**: Keep dependencies up to date, audit regularly
8. **Bundle Size**: Monitor bundle size, lazy load when appropriate

## Updating Dependencies

### Update Catalog Versions

```bash
# Check for outdated packages
pnpm outdated

# Update catalog in pnpm-workspace.yaml
vim pnpm-workspace.yaml
# Update version numbers in the catalog section

# Reinstall to use new versions
pnpm install
```

### Update Individual Workspace

```bash
# Update specific package in workspace
pnpm --filter @sytesbook/business-web update react
```

## Resources

- [TypeScript Documentation](https://www.typescriptlang.org/docs/)
- [PNPM Documentation](https://pnpm.io/)
- [React Documentation](https://react.dev/)
- [Vite Documentation](https://vite.dev/)
- [Vitest Documentation](https://vitest.dev/)
- [PNPM Workspace Catalog](https://pnpm.io/catalogs)
