# Conduction Workspace — Generic Project Guidelines

## Organization

This workspace (`apps-extra/`) contains multiple Nextcloud apps and frontends, all developed by Conduction. Each project is a separate Git repository cloned into this directory.

### Projects

| Project | Type | GitHub Repo | Description |
|---------|------|-------------|-------------|
| openregister | NC App (PHP) | ConductionNL/openregister | Core data registration platform |
| opencatalogi | NC App (PHP) | ConductionNL/opencatalogi | Open catalogi publication system |
| softwarecatalog | NC App (PHP) | ConductionNL/softwarecatalog | Software catalogus for gemeenten |
| openconnector | NC App (PHP) | ConductionNL/openconnector | API connector/integration layer |
| docudesk | NC App (PHP) | ConductionNL/docudesk | Document management |
| nldesign | NC App (PHP) | ConductionNL/nldesign | NL Design System theming |
| mydash | NC App (PHP) | ConductionNL/mydash | Dashboard app |
| openklant | NC App (PHP) | ConductionNL/openklant | Klantinteractie platform |
| opentalk | NC App (PHP) | ConductionNL/opentalk | Communication platform |
| openzaak | NC App (PHP) | ConductionNL/openzaak | Zaakgericht werken |
| valtimo | NC App (PHP) | ConductionNL/valtimo | Process automation |
| pipelinq | NC App (PHP) | ConductionNL/pipelinq | CRM — client and request management |
| procest | NC App (PHP) | ConductionNL/procest | Case management (zaakgericht werken) |
| timetracking | NC App (PHP) | ConductionNL/timetracking | Time tracking |
| tilburg-woo-ui | Frontend (React) | ConductionNL/tilburg-woo-ui | Tilburg WOO publication UI |

## Tech Stack

### Backend (Nextcloud Apps)
- **Language**: PHP 8.1+
- **Framework**: Nextcloud App Framework (OCP)
- **Database**: PostgreSQL with pgvector
- **Container**: Docker (Apache + mod_php)
- **Key patterns**: Controllers, Services, Mappers (ORM), Entities

### Frontend (React UIs)
- **Language**: JavaScript/TypeScript
- **Framework**: React with MobX state management
- **Bundler**: Webpack
- **Styling**: NL Design System tokens (Utrecht, Rijkshuisstijl)
- **Package manager**: Yarn 4.x

## Coding Standards

### PHP (Nextcloud Apps)
- Follow PSR-12 coding style
- Use type hints on all method signatures
- Use dependency injection via constructor (Nextcloud DI container)
- Controllers extend `OCA\...\Controller` or `ApiController`
- Services contain business logic, controllers are thin
- Use `IAppConfig` for app configuration, NOT direct database queries
- Route registration in `appinfo/routes.php`
- Specific routes MUST come before wildcard routes (Symfony router)

### JavaScript/React (Frontends)
- Use functional components with hooks
- State management via MobX stores
- Use NL Design System components where available
- Environment config via `process.env.*` variables
- API calls via axios with centralized config

### General
- No hardcoded colors — use CSS variables / design tokens
- WCAG AA accessibility compliance required
- All public API endpoints need CORS headers
- Use RFC 2119 keywords in specs (MUST, SHALL, SHOULD, MAY)

## Docker Environment

- **Compose file**: `openregister/docker-compose.yml` (primary)
- **Nextcloud container**: `nextcloud` on port 8080 (host) → 80 (container)
- **Database**: PostgreSQL on port 5432
- **File ownership**: `www-data` in container — use `docker exec -u root nextcloud chown -R 1000:1000 /path/` for host editing
- **OPcache**: Run `docker exec nextcloud apache2ctl graceful` after PHP changes
- **Auth**: Basic auth `admin:admin` for local development

## NL Design System Compliance

All apps MUST support the nldesign theming app:
- Use standard Nextcloud components and CSS classes
- Use CSS variables for all colors, spacing, typography
- Test with nldesign app enabled
- Support token sets: Rijkshuisstijl, Utrecht, Amsterdam, Den Haag, Rotterdam
- Ensure proper contrast ratios (WCAG AA minimum)

## API Conventions

- URL pattern: `/index.php/apps/{appname}/api/...`
- Use JSON request/response bodies
- Include proper CORS headers on public endpoints
- Register OPTIONS routes for CORS preflight
- Use Nextcloud's `@CORS` and `@NoCSRFRequired` annotations for public APIs
- Version APIs where breaking changes occur

## Spec-Driven Development

This workspace uses **OpenSpec** for spec-driven development. See `openspec/` for shared schemas and cross-project specifications. Each project has its own `openspec/` directory for project-specific specs and changes.

### Workflow
1. Write specs before code (`/opsx:new`, `/opsx:ff`)
2. Generate GitHub issues from tasks (`/opsx:plan-to-issues`)
3. Implement via Ralph Wiggum loops (`/opsx:ralph-start`)
4. Verify against specs (`/opsx:verify`)
5. Review and archive (`/opsx:archive`)

See `openspec/docs/workflow.md` for the full workflow documentation.
