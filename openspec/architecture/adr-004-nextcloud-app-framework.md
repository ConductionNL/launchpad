# ADR-004: Nextcloud App Framework Patterns

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design, tasks
**Last updated:** 2026-03-19

## Context

All Conduction PHP apps run inside the Nextcloud server environment. Nextcloud provides an app framework with dependency injection, OCP interfaces, route annotations, and lifecycle hooks. Apps that bypass these patterns break during Nextcloud upgrades, cannot be distributed via the App Store, and create security vulnerabilities.

## Decision

### Dependency Injection
- Apps MUST use Nextcloud's DI container for service resolution.
- Controllers and services MUST declare dependencies via constructor injection.
- Apps MUST NOT use `\OC::$server` or static service locators — use `\OCP` interfaces instead.

### Routing
- Routes MUST be defined in `appinfo/routes.php`.
- Controllers MUST use OCS/API annotations (`#[PublicPage]`, `#[NoCSRFRequired]`, `#[NoAdminRequired]`).
- Specific routes MUST be declared before wildcard/catch-all routes (Symfony router matches first-defined).

### Configuration
- App settings MUST be stored via `IAppConfig` (Nextcloud's configuration API).
- Apps MUST NOT read/write configuration directly from the database.
- Sensitive settings (API keys, credentials) MUST use `IAppConfig` with the sensitive flag.

### Lifecycle
- Schema and data initialization MUST use repair steps (`IRepairStep`), not `install.php` or manual SQL.
- Background processing MUST use Nextcloud's job queue (`IJob`, `TimedJob`, `QueuedJob`).
- Event handling MUST use Nextcloud's event dispatcher (`IEventDispatcher`).

### Code Quality
- PHP code MUST follow PSR-12 coding standard.
- All PHP apps MUST pass `composer check:strict` (PHPCS, PHPMD, Psalm, PHPStan).
- Type hints MUST be used on all method signatures (parameters and return types).

## Consequences

- Spec authors do not need to specify Nextcloud framework details — those are implied by this ADR.
- Design documents SHOULD reference specific OCP interfaces when relevant but MUST NOT propose custom alternatives.
- Tasks MUST include `composer check:strict` as a pass criterion.

## Exceptions

- Python ExApps (openklant, opentalk, etc.) follow ExApp SDK patterns instead of PHP app framework patterns.
- React-based standalone UIs (tilburg-woo-ui) are not Nextcloud apps and follow their own framework patterns.
