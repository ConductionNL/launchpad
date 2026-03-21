# Krayin CRM - Architecture & API Documentation

## Tech Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vue.js (inline in Blade) + Tailwind CSS
- **Database**: MySQL 5.7.23+ / MariaDB 10.2.7+
- **Build**: Vite
- **ORM**: Eloquent + Repository Pattern (Prettus/l5-repository)
- **Package system**: Webkul Concord (modular package architecture)
- **Queue**: Laravel Queue (database/redis)
- **Authentication**: Laravel Sanctum (for API)

## System Requirements

- Server: Apache 2 or NGINX
- RAM: 3 GB minimum
- PHP: 8.1+
- MySQL 5.7.23+ or MariaDB 10.2.7+
- Node: 8.11.3 LTS+
- Composer: 2.5+

## Modular Architecture

Krayin is organized into 18+ packages under `packages/Webkul/`:

Each package follows a standard structure:
- `Config/` - Package configuration
- `Database/` - Migrations, seeders, factories
- `Http/` - Controllers, middleware, requests
- `Models/` - Eloquent models with Proxy pattern
- `Repositories/` - Data access layer
- `Resources/` - Views, lang files, assets
- `Routes/` - Package-specific routes
- `Providers/` - Service providers

### Key Design Patterns

1. **Proxy Pattern**: Every model has a `*Proxy` class for substitution
2. **Repository Pattern**: All data access via repository classes
3. **Contract/Interface**: Models implement contracts for loose coupling
4. **EAV**: Dynamic custom attributes via `CustomAttribute` trait
5. **Event-Driven**: Laravel events on all CRUD operations
6. **Bouncer Authorization**: Data-level access control

## REST API

The REST API is a **separate package** (`krayin/rest-api`), not built into core.

### Installation
```bash
composer require krayin/rest-api
```

### Configuration
```env
SANCTUM_STATEFUL_DOMAINS=http://localhost/public
```

### Setup
```bash
php artisan krayin-rest-api:install
```

### Documentation
- Swagger/OpenAPI docs at: `/api/admin/documentation`
- Uses L5-Swagger for interactive API docs
- Demo API docs: https://apidoc.krayincrm.com/api/documentation

### Authentication
- Laravel Sanctum (token-based)
- Stateful domain configuration for SPA usage

## Docker Deployment

### Quick Start (Docker Hub)
```bash
docker pull webkul/krayin:2.0.1
docker run -it -d -p 9005:80 webkul/krayin:2.0.1
```

### Docker Compose (GitHub repo)
Services: krayin-php-apache, krayin-mysql, krayin-phpmyadmin

### Default Credentials
- Email: `admin@example.com`
- Password: `admin123`

## Extension System

Krayin supports custom packages via:
1. **Controller overriding**: Extend base controllers, bind via service provider
2. **Model overriding**: Override models via Concord contract registration
3. **Blade view overriding**: Publish and customize view files
4. **View Render Events**: Inject content at specific DOM points without overriding entire views

## Sources

- Developer docs: https://devdocs.krayincrm.com
- User docs: https://docs.krayincrm.com
- API docs: https://apidoc.krayincrm.com/api/documentation
- GitHub: https://github.com/krayin/laravel-crm
- Docs repo: https://github.com/krayin/krayin-docs
