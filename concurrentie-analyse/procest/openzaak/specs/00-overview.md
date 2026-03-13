# OpenZaak Competitive Analysis Overview

## Product Summary

**OpenZaak** is the VNG-certified reference implementation of the ZGW (Zaakgericht Werken) API standards. It is a Django/Python application providing five REST APIs for Dutch municipal case management:

| API | Component | Purpose |
|-----|-----------|---------|
| Zaken API (ZRC) | v1.5.1 | Case lifecycle management |
| Catalogi API (ZTC) | v1.3.1 | Type definitions (zaaktypen, statustypen, etc.) |
| Documenten API (DRC) | v1.4.2 | Document storage, versioning, locking |
| Besluiten API (BRC) | v1.1.0 | Decision management |
| Autorisaties API (AC) | v1.0.0 | Application authorization |

**Version tested:** latest (GIT SHA 54ad853edd387ec765fd7e05acd89a5049bbfa45)
**Architecture:** Django 4.x, PostgreSQL/PostGIS, Redis, Celery, nginx
**UI:** Django Admin (no custom frontend -- admin-only configuration interface)
**Auth model:** JWT M2M (machine-to-machine), not user-based
**License:** EUPL-1.2

## Key Architectural Differences from Procest

### 1. No End-User UI
OpenZaak has **no citizen or case worker frontend**. It is purely a backend API layer. The Django Admin is used only for system configuration (types, services, authorizations). Any user-facing functionality requires a separate frontend application (e.g., Open Formulieren, Open Inwoner). **This is Procest's biggest advantage** -- it provides both backend and frontend within Nextcloud.

### 2. Service-Oriented Architecture vs. Nextcloud App
OpenZaak runs as a standalone Docker deployment with its own database, Redis, and Celery workers. Procest runs as a Nextcloud app, inheriting Nextcloud's user management, file storage, and collaboration features. OpenZaak requires integration with 3-5 other components (Open Notificaties, Open Formulieren, etc.) for a complete solution.

### 3. JWT vs. User Sessions
OpenZaak authenticates API consumers (other services) via JWT tokens with shared secrets. It does not manage end users. Procest inherits Nextcloud's user/group system with OAuth, SAML, and LDAP support.

### 4. Strict VNG Compliance vs. Practical Flexibility
OpenZaak strictly implements every VNG API specification field and validation rule. Procest takes a pragmatic approach, implementing the most-used ZGW patterns while keeping the flexibility of OpenRegister's schema-based data model.

## Admin Interface Overview (Django Admin)

The admin is organized into 6 sections visible on the dashboard:

### Accounts
- **Gebruikers** (Users) -- Django users with groups (Admin, API admin, per-component admin/read roles)
- **Groepen** (Groups) -- Pre-configured: Admin, API admin, Autorisaties/Besluiten/Catalogi/Documenten/Zaken admin+read, User admin
- **Session profiles** -- Read-only session tracking
- **TOTP devices** -- TOTP 2FA device management
- **Webauthn devices** -- WebAuthn/FIDO2 device management

### API Autorisaties
- **Applicaties** -- Client registration (client_ids, secret, autorisaties)
- **Services** -- External service connections (zgw-consumers) with auth config

### Gegevens (Data)
- **Besluiten** -- Decision records
- **Catalogi** -- Catalogue management (domein, RSIN, contact person)
- **Documenten** -- Document (EnkelvoudigInformatieObject) records
- **Imports** -- Bulk document import with status tracking (pending/active/finished/error)
- **Zaken** -- Case records

### Configuratie
- **Applicatiegroepen** -- Admin dashboard grouping
- **Feature flags** -- Single flag: "Laat gebruik van niet-gepubliceerde typen toe"
- **NLX configuration** -- NLX network directory + outway + certificates
- **Notificatiescomponentconfiguratie** -- Notification service URL + retry settings
- **OIDC Providers** -- OpenID Connect provider configuration
- **OIDC clients** -- OIDC client registration
- **Selectielijstconfiguratie** -- National archiving selection list API connection
- **Service configuration** -- Overview dashboard showing all 5 APIs active/inactive + external services
- **Uitgaande request-logging configuratie** -- Outgoing HTTP request logging settings
- **Webhook-abonnementen** -- Webhook subscription management
- **Websites** -- Django Sites framework (domain configuration)

### Logs
- **Access attempts** -- django-axes brute force tracking
- **Access logs** -- Login audit log
- **Audit trails** -- VNG-compliant audit trail per API component (filterable by bron, resource, actie, applicatie, resultaat)
- **Gefaalde notificaties** -- Failed notification delivery log
- **Logging** -- Application-level logging (django-db-logger, levels: NotSet/Debug/Info/Warning/Error/Fatal)
- **Uitgaande request-logs** -- Outgoing HTTP request log

### Handige links
External links to: Common Ground community, documentation, GitHub, mailing list, security contact, Slack, VNG standard docs, website.

## Feature Gap Analysis: What Procest Needs

### Critical (Required for VNG compliance)
1. **Catalogus grouping** -- Organizing zaaktypen under named catalogs with domein+RSIN
2. **Concept/publish workflow** -- Draft vs. published zaaktypen with immutability after publish
3. **Full StatusType ordering** -- volgnummer-based status progression with eindstatus detection
4. **ResultaatType with archiving** -- selectielijst integration, archiefnominatie, archiefactietermijn
5. **Case closure rules** -- Requiring Resultaat before final status, auto-calculating archiefactiedatum
6. **Audit trail** -- Recording every write action per VNG spec
7. **Scope-based API authorization** -- Fine-grained per-component, per-type, per-confidentiality scopes

### High Priority (Common in production use)
8. **Document versioning** -- Canonical + version model with lock/unlock
9. **Zaaktype versioning** -- Validity periods (beginGeldigheid/eindeGeldigheid)
10. **ZaakTypeInformatieObjectType** -- Formal document-case type associations
11. **BesluitType lifecycle** -- Concept/publish + reactietermijn
12. **Notification integration** -- Cloud Events or webhook-based event routing
13. **ETag concurrency** -- Optimistic locking on resources

### Medium Priority (Advanced features)
14. **Case suspension/extension** -- opschorting + verlenging with duration tracking
15. **Convenience endpoints** -- Batch operations (zaak_registreren, zaak_afsluiten)
16. **Chunked upload** -- BestandsDelen for large documents
17. **Cross-system references** -- FkOrServiceUrl for federated deployments
18. **Dispatch tracking** -- Verzending model for document correspondence
19. **NLX integration** -- Government service bus connectivity

### Low Priority (Nice to have)
20. **Mandate authentication** -- DigiD/eHerkenning context on roles
21. **Catalog export/import** -- ZIP archive of type definitions
22. **Payment tracking** -- betalingsindicatie on cases
23. **Geometry support** -- PostGIS spatial data on cases

## Procest Competitive Advantages

1. **Integrated frontend** -- Procest provides Vue-based case management UI; OpenZaak has none
2. **Nextcloud ecosystem** -- File storage, collaboration, notifications, user management built in
3. **Lower operational complexity** -- Single Nextcloud app vs. 5+ Docker containers
4. **Flexible data model** -- OpenRegister schemas vs. rigid Django models
5. **Modern stack** -- Vue 3 / Nextcloud design vs. Django Admin (no custom UI)
6. **n8n workflow engine** -- Business process automation built in
7. **Plugin architecture** -- Nextcloud app ecosystem for extensions

## Screenshots Index

40 screenshots captured in `screenshots/` directory:
- 01-10: Login, dashboard, API landing pages, scopes
- 11-16: Catalogi, selectielijst, zaaktype detail, services
- 17-21: Applicaties, autorisaties, services
- 22-24: Zaken list/detail, documenten
- 25-40: Besluiten, audit trails, feature flags, notifications config, service config, imports, OIDC, webhooks, access attempts, logging, users, MFA, outgoing request config, NLX config, websites

## Spec Files Index

| File | Topic |
|------|-------|
| 01-zaken-api-compliance.md | Zaken API feature coverage |
| 02-catalogi-api-compliance.md | Catalogi API feature coverage |
| 03-documenten-api-compliance.md | Documenten API feature coverage |
| 04-besluiten-api-compliance.md | Besluiten API feature coverage |
| 05-autorisaties-api-compliance.md | Autorisaties API feature coverage |
| 06-notificaties-integration.md | Notification routing model |
| 07-archiving-selectielijst.md | Archiving + selectielijst integration |
| 08-confidentiality-model.md | Vertrouwelijkheidaanduiding model |
| 09-zaaktype-configuration.md | ZaakType configuration hierarchy |
| 10-mandate-authentication.md | DigiD/eHerkenning mandate support |
| 11-audit-trail.md | VNG audit trail requirements |
| 12-document-lifecycle.md | Document versioning/locking |
| 13-case-closure-archiving.md | Case closure + archive date calculation |
| 14-external-api-interop.md | Federated ZGW interoperability |
| 15-convenience-endpoints.md | Batch/composite API endpoints |
| zaak-lifecycle/spec.md | Full zaak lifecycle data model |
| catalogi-zaaktypen/spec.md | Full catalogi type hierarchy |
| documenten-api/spec.md | Full documenten data model |
| autorisaties-model/spec.md | Full authorization architecture |
| besluiten-api/spec.md | Full besluiten data model |
| status-resultaat/spec.md | Status + resultaat progression |
| rollen-betrokkenen/spec.md | Role + participant model |
| archivering/spec.md | Archiving rules |
| zaak-relaties/spec.md | Case-to-case relations |
| zaakeigenschappen/spec.md | Custom properties |
| zaakobjecten/spec.md | External object references |
