# Open Beheer — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (642 files), Documentation (16 files + ReadTheDocs), Browser walkthrough (8 screenshots)
**Verdict**: UI-only BFF proxy — narrow scope, but validates the need for admin UX over raw APIs

---

## Executive Summary

Open Beheer (v0.9.0) is Maykin Media's unified functional management UI for Dutch government registrations. It's a pure **Backend-for-Frontend (BFF) proxy** — stores zero domain data, proxies everything to Open Zaak's Catalogi API. Originally commissioned by Gemeente Rotterdam, now expanding via Dimpact (30+ municipalities).

**Scope is very narrow**: only manages zaaktypen (case types) and informatieobjecttypen (document types) — configuration/type definitions, NOT instance data (cases, documents, objects).

**Not a direct competitor to OpenRegister** — OpenRegister is a full data platform (storage + APIs + search + admin UI), while Open Beheer is just a UI layer on top of existing APIs.

## Architecture

```
React SPA (Vite) <-> Django BFF (msgspec) <-> Open Zaak Catalogi API
                                           <-> Selectielijst API (VNG)
                                           <-> Objecttypen API
```

| Layer | Technology |
|-------|-----------|
| Frontend | React 19, TypeScript, Vite, @maykin-ui/admin-ui |
| Backend | Python 3.12, Django 5.2, DRF, msgspec |
| Data | PostgreSQL 17 (users/config only), Redis |
| Auth | Django sessions + OIDC + 2FA (WebAuthn/TOTP) |

## Key Comparison

| Aspect | Open Beheer | OpenRegister |
|--------|-------------|--------------|
| Architecture | BFF proxy (stores nothing) | Self-contained data platform |
| Data storage | None — pure proxy | PostgreSQL/MySQL via Nextcloud |
| Scope | Zaaktypen + IOT config only | Any register, any schema, any data |
| Frontend | React 19 + Maykin admin-ui | Vue.js + @nextcloud/vue |
| Search | Basic contains filter | Full-text + faceted + semantic |
| Auth | Django sessions + OIDC + 2FA | Nextcloud auth ecosystem |
| Multi-tenancy | Via catalogi selection | Native registers |
| Versioning | ZGW geldigheid dates | Audit log + object versioning |
| Publishing | Draft/Published workflow | No publishing concept |
| Templates | Hardcoded Python templates | Schema-driven |
| RBAC | Admin-level only | Granular permissions |
| AI/MCP | No | Yes |
| Deployment | Standalone Docker (4+ containers) | Nextcloud app (zero-infra) |

## Features Open Beheer Has That OpenRegister DOES NOT

| Feature | Description | Priority |
|---------|-------------|----------|
| **Draft/publish workflow** | concept=true/false with publish endpoint | MEDIUM |
| **Geldigheid-based versioning** | beginGeldigheid/eindeGeldigheid date ranges | MEDIUM |
| **Template-based creation** | Pre-filled templates for common patterns | LOW |
| **Inline related object editing** | DataGrid with add/edit/delete in-place | LOW |
| **Dynamic field metadata (OBField)** | Backend-driven form rendering with type/options/editable | MEDIUM |
| **Selectielijst integration** | National archival reference data | LOW (ZGW-specific) |

## Features OpenRegister Already Has That Open Beheer LACKS

| Feature | OpenRegister | Open Beheer |
|---------|-------------|-------------|
| **Own data storage** | Full database with registers | None (pure proxy) |
| **Generic schema system** | Any JSON Schema, any domain | Only ZGW Catalogi types |
| **Full-text search** | Elasticsearch/Solr integration | No search |
| **Faceted search** | Configurable facets | No facets |
| **RBAC** | Granular per-register/schema | Admin-level only |
| **Audit trails** | Built-in versioning | Delegates to Open Zaak |
| **REST API** | Full CRUD API for any data | BFF proxy endpoints only |
| **Multi-tenancy** | Native via registers | Via external catalogi |
| **File handling** | Nextcloud native files | No file management |
| **AI/MCP** | LLM-friendly API + vector search | Not available |
| **NL Design theming** | Government design system | Maykin proprietary admin-ui |
| **n8n automation** | Built-in workflow triggers | Not available |
| **Nextcloud integration** | Users, files, sharing, collaboration | Not available |
| **Import/Export** | CSV, JSON, bulk operations | Not available |

## Specs Created

### From Codebase (10 specs)
zaaktype-management, informatieobjecttype-management, bff-proxy-architecture, authentication-session, catalogi-service-selection, template-based-creation, version-management, related-object-management, field-metadata-system, health-checks

### From Documentation (5 docs + architecture)
architecture, ecosystem-integrations, api-reference, gemeente-rotterdam-context, pdf-links

### From Browser (8 screenshots)
initial-load, login-no-service-error, main-layout-no-catalogus, login-page, main-layout-authenticated, profile-dropdown, sidebar-collapsed, api-docs

### Business Logic Diagrams (3)
request-flow, data-model, state-management

## Strategic Assessment

**Low competitive threat.** Open Beheer:
1. Only manages type definitions, not data — fundamentally different scope
2. Depends entirely on Open Zaak — no standalone value
3. Still pre-1.0 with many features TODO (besluittypen, archivering, zaakobjecttypen)
4. Narrow ZGW focus vs OpenRegister's generic flexibility

**Risk to watch**: Dimpact backing (30+ municipalities) could make it the default admin tool in Common Ground, creating ecosystem lock-in around Maykin's stack.

**Patterns worth borrowing**:
1. Draft/publish workflow for schema/object lifecycle
2. Dynamic field metadata API (OBField concept) for richer form rendering
3. Template-based creation UX for common object patterns
