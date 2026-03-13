# Open Beheer - Competitive Analysis Overview

## What is Open Beheer?

Open Beheer (v0.9.0) is a **unified functional management UI** developed by [Maykin Media](https://www.maykinmedia.nl), originally commissioned by **Gemeente Rotterdam**. It provides functional administrators a user-friendly interface on top of technical ZGW (Zaakgericht Werken) API registrations -- primarily the **Catalogi API** in Open Zaak.

The core value proposition: instead of forcing functional administrators to use the raw Open Zaak Django admin (which exposes URLs, foreign keys, and technical ZGW concepts), Open Beheer provides a purpose-built UI that abstracts away the complexity.

**Stakeholders:** Dimpact, Gemeente Den Haag, Gemeente Rotterdam, Gemeente Utrecht, Maykin Media.

**License:** EUPL (European Union Public License).

## Architecture

### Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | React 19.2, TypeScript, Vite, react-router 7, `@maykin-ui/admin-ui` (Maykin's own component library) |
| **Backend (BFF)** | Python 3.12, Django 5.2, Django REST Framework, msgspec (binary serialization), drf-spectacular (OpenAPI) |
| **External APIs consumed** | Open Zaak (Catalogi API / ZTC), Objecttypen API, Selectielijst API |
| **Auth** | Django session auth + CSRF tokens, OIDC (mozilla-django-oidc-db), 2FA (maykin-2fa with WebAuthn/TOTP) |
| **Data layer** | PostgreSQL 17, Redis (sessions + caching + axes rate limiting) |
| **Deployment** | Docker (multi-stage build), nginx reverse proxy, uwsgi |

### BFF (Backend-for-Frontend) Pattern

Open Beheer does **NOT** store its own domain data. It is a pure proxy/BFF:

1. The React frontend calls the Open Beheer BFF API (`/api/v1/...`)
2. The BFF translates these calls into Open Zaak Catalogi API requests
3. The BFF transforms responses: URL-to-UUID conversion, field metadata injection, pagination relay, expansion of related objects
4. The frontend never talks directly to Open Zaak

This is the **critical architectural difference** from OpenRegister, which stores its own data in its own database.

### APIs Consumed

| API | Purpose | Client in code |
|-----|---------|---------------|
| **Open Zaak Catalogi API (ZTC)** | Zaaktypen, statustypen, roltypen, resultaattypen, eigenschappen, besluittypen, informatieobjecttypen, zaakobjecttypen, zaaktypeinformatieobjecttypen, catalogi | `ztc_client()` via zgw-consumers |
| **Objecttypen API** | Object types for zaakobjecttypen expansion | `objecttypen_client()` via zgw-consumers |
| **Selectielijst API** | Selectielijst process types for archival classification | `selectielijst_client()` via zgw-consumers |

The system uses `zgw-consumers` library for service management (Service model with slug, API type, credentials). Services are configured in the Django admin.

## What Open Beheer Manages

### Currently Implemented (v0.9.0)

1. **Zaaktypen** (Case Types) - Full CRUD + publish/unpublish + versioning
   - List view with filters (status, search on identificatie/omschrijving)
   - Detail view with tabbed interface (Overview, General, Status types, IOT types, Role types, Result types, Properties)
   - Inline editing of related objects (statustypen, roltypen, resultaattypen, eigenschappen, zaaktypeinformatieobjecttypen)
   - Template-based creation ("Basis" template with pre-filled values)
   - Version management with "Save as new version" / "Publish" / "Create new version" workflows
   - Deep link to Open Zaak admin for advanced editing

2. **Informatieobjecttypen** (Document Types) - Full CRUD + publish
   - List view
   - Detail view with attribute grid
   - Edit/Publish workflow

### NOT Yet Implemented (commented out / TODO in code)

- Besluittypen tab (many-to-many complexity noted as needing UX design)
- Deelzaaktypen (sub-case types, "Droste-effect" noted)
- Archivering tab (selectielijst process type expansion)
- Zaakobjecttypen tab (commented out, needs design)
- **No Open Klant integration**
- **No Open Product integration**
- **No Zaken (cases) management** -- only Zaaktypen (case type definitions)
- **No document management** -- only Informatieobjecttypen (document type definitions)

## UI Patterns

### Component Library: @maykin-ui/admin-ui

All UI is built on Maykin's proprietary admin UI library which provides:
- `BaseTemplate` -- Main layout with sidebar + primary navigation
- `ListTemplate` -- Paginated data grid with toolbar
- `CardBaseTemplate` -- Detail view with breadcrumbs and actions
- `AttributeGrid` -- Form-like display of object attributes with edit mode
- `DataGrid` -- Editable table for related objects
- `Tabs` / `Tab` -- Tabbed interface
- `Modal` / `Form` -- Form dialogs
- `Select`, `Button`, `Toolbar`, `Outline` icons

### Navigation Structure

```
/ (root)
  /:serviceSlug                    -- Service selection (auto-redirect)
    /:catalogusId                  -- Catalogus selection
      /zaaktypen                   -- Zaaktypen list
        /create                    -- Create zaaktype (template selector + form)
        /:zaaktypeUUID             -- Zaaktype detail (tabbed)
      /informatieobjecttypen       -- IOT list
        /create                    -- Create IOT
        /:iotUUID                  -- IOT detail
  /login                           -- Login page
  /logout                          -- Logout
```

### State Management

- **No Redux/Zustand/MobX** -- uses React Router's loader/action pattern
- Loaders fetch data before rendering (like Remix)
- Actions dispatch mutations via `useSubmitAction` hook
- Local component state for edit mode, pending changes, filter state
- `@maykin-ui/client-common` for caching (cacheMemo, cacheGet, cacheSet)

### Edit Workflow (Zaaktype)

1. User clicks "Bewerken" (Edit) -> navigates to concept version with `?editing=true`
2. Changes accumulate as `pendingUpdatesState` (for scalar fields) and `actionsState` (for related objects)
3. User clicks "Opslaan" (Save) -> batched actions: first deletions, then PATCH zaaktype + creates/updates
4. "Publiceren" (Publish) -> PATCH old version's eindeGeldigheid to yesterday, set new version's beginGeldigheid to today, POST publish endpoint

## Comparison with OpenRegister

| Aspect | Open Beheer | OpenRegister |
|--------|-------------|-------------|
| **Architecture** | BFF proxy over Open Zaak | Self-contained data store with its own database |
| **Data storage** | None (pure proxy) | PostgreSQL/MySQL via Nextcloud, with registers, schemas, objects |
| **Frontend** | React 19 + custom admin-ui lib | Vue.js 2 + @nextcloud/vue within Nextcloud |
| **Scope** | Zaaktypen + IOT catalog management only | Generic register/schema/object management for any domain |
| **API consumed** | Open Zaak Catalogi API, Objecttypen API, Selectielijst | Own internal API + optional external sources |
| **Auth** | Django sessions + OIDC + 2FA | Nextcloud auth (LDAP/SAML/OIDC via Nextcloud) |
| **Multi-tenancy** | Via catalogi + service selection | Via Nextcloud groups/organizations |
| **Versioning** | ZGW versiedatum + beginGeldigheid/eindeGeldigheid | Audit log + object versioning via register configuration |
| **Templates** | Hard-coded Python templates for zaaktype creation | Schema-driven, no templates needed |
| **Related objects** | Expanded inline via _expand pattern (statustypen, roltypen, etc.) | Schema relationships + faceted search |
| **Publishing workflow** | Draft/Published (concept=true/false) | No publishing concept (all objects are live) |
| **Search/Filter** | Basic identificatie/omschrijving contains filter | Full-text search + faceted filtering + Elasticsearch/Solr |
| **Field metadata** | Backend generates OBField array with types, options, editability | Schema-driven (JSON Schema properties) |
| **i18n** | Dutch-only UI, nl-nl locale | Dutch UI, i18n-ready |
| **Deployment** | Standalone Docker container | Nextcloud app (ExApp or custom_apps) |

## Rotterdam-Specific Features

The codebase does not contain Rotterdam-specific features per se, but several design decisions reflect Rotterdam's use case:

1. **Focus on Catalogi API only** -- Rotterdam uses Open Zaak as their ZGW backend and needed a way for functional administrators (not developers) to manage their zaaktypen catalog
2. **Template system** -- The "Basis" template with pre-filled Dutch government defaults (vertrouwelijkheidaanduiding: openbaar, indicatie_intern_of_extern: extern, etc.)
3. **Open Zaak admin deep link** -- The "Bewerk in Open Zaak admin" link on zaaktype detail acknowledges that Open Beheer doesn't yet cover all Open Zaak functionality
4. **Selectielijst integration** -- Links to the national selectielijst for archival classification (Dutch government requirement)
5. **OPEN_ZAAK_ADMIN_BASE_URL setting** -- Configurable base URL for Open Zaak admin links

## Code Quality & Testing

- **Backend:** ruff (linting), pyright (type checking), pytest with VCR cassettes for API mocking
- **Frontend:** ESLint, Prettier, Vitest, Storybook, Playwright (E2E via backend), commitlint (conventional commits)
- **E2E tests:** Backend-driven Playwright tests that test full flows (login, zaaktype create/list/detail)
- **CI:** GitHub Actions for CI, code quality, CodeQL security analysis

## Key Insights for OpenRegister

1. **Open Beheer validates the need** for a functional admin UI over raw API registrations -- this is exactly what OpenRegister already provides within Nextcloud
2. **The BFF proxy pattern is both a strength and limitation** -- no data lock-in, but also no offline capability, no caching layer, and every request hits Open Zaak
3. **Open Beheer's scope is narrow** -- only Catalogi API management. OpenRegister already handles a broader scope (any register, any schema)
4. **The @maykin-ui/admin-ui library is proprietary** -- not reusable outside Maykin's ecosystem, whereas OpenRegister uses the standard @nextcloud/vue library
5. **Field metadata system is interesting** -- the `OBField` concept (name, type, options, editable) generated from msgspec types is similar to how OpenRegister could expose richer field metadata from JSON Schema
6. **The publishing/versioning workflow** is specific to ZGW Catalogi API concepts (concept=true/false, beginGeldigheid/eindeGeldigheid) -- OpenRegister would need to implement these differently
7. **Template-based creation** is a useful UX pattern that OpenRegister could adopt for schema-driven object creation
