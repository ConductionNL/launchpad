# ADR-008: Backend Layering — Controller → Service → Mapper

**Status:** accepted
**Scope:** company-wide
**Applies to:** design, tasks
**Last updated:** 2026-03-19

## Context

Conduction apps follow a three-layer backend architecture. When business logic leaks into controllers or mappers, it becomes untestable, unreusable, and tightly coupled to HTTP or database concerns. Consistent layering makes code predictable across all 10+ apps.

Additionally, Nextcloud Entities use PHP's `__call` magic method for getters and setters. Named arguments break this mechanism because `__call` receives `['name' => val]` as `$args` but Entity's internal `setter()` method accesses `$args[0]` — which becomes the key name, not the value.

## Decision

### Three-Layer Architecture
- **Controllers** MUST be thin: routing, request validation, and response formatting only. Each method SHOULD be under 10 lines of logic.
- **Services** MUST contain all business logic. Services MUST be stateless — no instance state between requests.
- **Mappers** MUST handle database CRUD only. No business logic, validation, or transformation in mappers.
- Controllers MUST NOT call mappers directly — always go through a service.
- Services MAY call other services. Services MAY call mappers.

### Entity Setter Rule
- Entity setters MUST use positional arguments only: `$entity->setName('value')`.
- Named arguments on Entity setters are PROHIBITED: `$entity->setName(name: 'value')` breaks `__call` magic.
- This applies to all classes extending `\OCP\AppFramework\Db\Entity`.

### Database Migrations
- Existing migrations MUST NEVER be modified — they may have already run in production.
- Schema changes MUST be implemented as new migration files.
- Migration naming MUST follow: `Version{N}Date{YYYYMMDDhhmmss}.php`.
- All migrations MUST extend `SimpleMigrationStep` and use `ISchemaWrapper`.

## Consequences

- Design documents MUST specify which layer handles each operation.
- Tasks that create new endpoints MUST create corresponding service methods — not inline logic in controllers.
- Code reviews MUST flag business logic in controllers or mappers.

## Exceptions

- `nldesign` has no services or mappers — it reads config and injects CSS. Layering does not apply.
- ExApp sidecar wrappers may have different architecture per ExApp SDK conventions.
