---
status: competitor-analysis
source: https://github.com/maykinmedia/open-vtb
competitor: Maykin Media / Open VTB
date: 2026-03-13
analyst: Claude Opus 4.6
---

# Open VTB (Verzoeken, Taken en Berichten) - Competitive Analysis

## Executive Summary

Open VTB is a **v0.1.0 API-only registry** by Maykin Media for the Platform Dienstverlening werkgroep. It provides three independent REST APIs for Verzoeken (Requests), Taken (Tasks), and Berichten (Messages). The project is **early-stage** -- version 0.1.0, empty changelog, initial migrations dated March 2026, no releases yet on Docker Hub. Despite being early, the data models and API design are mature and well-structured, following VNG/Common Ground patterns.

**Key competitive threat to Pipelinq:** Open VTB directly addresses the same government intake/task management space with a standardized, VNG-backed API specification. Its strength is **standards compliance** (VNG API standards, URN addressing, OIDC) and **ecosystem integration** (Mijn Overheid, Open Formulieren, ZGW APIs).

## Project Maturity

| Indicator | Status |
|---|---|
| Version | 0.1.0 (unreleased, no release date set) |
| Changelog | Empty ("1.X.X" placeholder) |
| Docker Hub | Image exists but no semver tags |
| Migrations | Initial only, dated March 2026 |
| Tests | Comprehensive (API, auth, admin, validators, OIDC) |
| Documentation | ReadTheDocs setup, installation guides, API docs |
| CI/CD | GitHub Actions (CI, OAS checks, CodeQL, code quality) |
| License | EUPL 1.2 |

## Technology Stack

| Component | Technology |
|---|---|
| Language | Python 3.12+ |
| Framework | Django 5.2 + Django REST Framework |
| Database | PostGIS (PostgreSQL 17 + spatial) |
| Cache/Queue | Redis (cache + Celery broker) |
| Auth | OIDC (mozilla-django-oidc-db) + Token Auth |
| API spec | OpenAPI 3.x via drf-spectacular |
| API style | REST, camelCase JSON, VNG API standards |
| Observability | OpenTelemetry, Prometheus, Grafana, Promtail |
| 2FA | maykin-2fa with WebAuthn |
| Containerization | Docker + docker-compose |

## Three Components Overview

### 1. Verzoeken (Requests) - Primary Competitor to Pipelinq
The intake registry that decouples form submissions from case management. Key features:
- **VerzoekType with versioned JSON schemas** (draft/published/deprecated lifecycle)
- **Verzoek** with typed data validated against the schema
- **Payment tracking** (VerzoekBetaling)
- **Geo-location** (PostGIS geometry)
- **Source tracking** (VerzoekBron)
- **Attachments** via URN references
- Full CRUD API with immutability rules

### 2. Taken (Tasks) - Secondary Competitor
External tasks assigned to citizens by case handlers. Key features:
- **3 polymorphic task types**: Betaaltaak (payment), Gegevensuitvraagtaak (external form), Formuliertaak (embedded FormIO form)
- **5-state lifecycle**: open -> uitgevoerd/niet_uitgevoerd/afgebroken -> verwerkt
- **Automatic reminder calculation** (configurable days before deadline)
- **URN-based assignment** to persons, employees, cases, products
- Type-specific endpoints with dedicated validation schemas

### 3. Berichten (Messages) - New Territory
Citizen-government communication registry. Key features:
- **Create + read-only** (immutable audit trail)
- **Mijn Overheid integration** (berichtType routing)
- **Scheduled publication** (publicatiedatum)
- **Read tracking** (geopend_op)
- **Action perspective and deadline** on messages

## File-by-File Inventory

### Source Code (src/openvtb/)

| Path | Lines | Purpose |
|---|---|---|
| **Components: Verzoeken** | | |
| `components/verzoeken/models.py` | 472 | 7 models: VerzoekType, VerzoekTypeVersion, Verzoek, VerzoekBron, VerzoekBetaling, Bijlage, BijlageType |
| `components/verzoeken/constants.py` | 9 | VerzoekTypeVersionStatus enum (published/draft/deprecated) |
| `components/verzoeken/admin.py` | 158 | Django admin with publish/new-version workflow |
| `components/verzoeken/forms.py` | - | Custom admin forms for VerzoekTypeVersion |
| `components/verzoeken/api/serializers.py` | 400 | 9 serializers including nested bijlagen, betaling, bron |
| `components/verzoeken/api/viewsets.py` | 142 | 3 viewsets (Verzoek, VerzoekType, VerzoekTypeVersion) |
| `components/verzoeken/api/validators.py` | 137 | 5 validators (VersionStatus, JsonSchema, CheckVersion, Immutable, AanvraagGegevens) |
| `components/verzoeken/api/urls.py` | 59 | URL routing with nested versies under verzoektypen |
| `components/verzoeken/api/utils.py` | 29 | NestedViewSetMixin + OpenAPI param |
| `components/verzoeken/api/schema.py` | - | drf-spectacular custom settings |
| `components/verzoeken/openapi.yaml` | - | Generated OpenAPI spec |
| **Components: Taken** | | |
| `components/taken/models.py` | 176 | 1 model: ExterneTaak with polymorphic details |
| `components/taken/constants.py` | 17 | StatusTaak (5 states) + SoortTaak (3 types) |
| `components/taken/schemas.py` | 209 | 4 JSON schemas (betaal, gegevens, formulier, formulierDefinitie) |
| `components/taken/admin.py` | 37 | Django admin for ExterneTaak |
| `components/taken/api/serializers.py` | 250 | Polymorphic serializer with 3 detail serializers |
| `components/taken/api/viewsets.py` | 322 | 4 viewsets (ExterneTaak + 3 type-specific) |
| `components/taken/api/validators.py` | 37 | FormulierDefinitieValidator |
| `components/taken/api/urls.py` | 68 | 4 route registrations |
| `components/taken/api/utils.py` | 42 | SoortTaakMixin + inline_serializer helper |
| **Components: Berichten** | | |
| `components/berichten/models.py` | 139 | 2 models: Bericht, Bijlage |
| `components/berichten/admin.py` | 18 | Django admin for Bericht |
| `components/berichten/api/serializers.py` | 79 | BerichtSerializer + BijlageSerializer |
| `components/berichten/api/viewsets.py` | 32 | Read-only + Create viewset |
| `components/berichten/api/urls.py` | 50 | Single route registration |
| **Shared Utilities** | | |
| `components/schemas.py` | 15 | IS_GERELATEERD_AAN_SCHEMA |
| `components/views.py` | - | ComponentIndexView |
| `components/widgets.py` | - | JSONSuit widget |
| `utils/validators.py` | 316 | URNValidator (RFC 8141), date validators, IBAN, phone, postal code |
| `utils/serializers.py` | 275 | URNField, URNRelatedField, URNIdentityField, URNModelSerializer, IBANField |
| `utils/fields.py` | 25 | URNField model field |
| `utils/constants.py` | 7 | Valuta enum (EUR only) |
| `utils/middleware.py` | 55 | API version header middleware |
| `utils/api_mixins.py` | 8 | CamelToUnderscoreMixin |
| `utils/api_utils.py` | - | get_from_serializer_data_or_instance helper |
| `utils/schema.py` | - | Custom AutoSchema |
| `utils/json_utils.py` | - | JSON Schema validation helpers |
| **Accounts** | | |
| `accounts/models.py` | 72 | Custom User model (AbstractBaseUser + PermissionsMixin) |
| `accounts/managers.py` | - | Custom UserManager |
| `accounts/backends.py` | - | Auth backends |
| `accounts/signals.py` | - | User signals |
| **Configuration** | | |
| `conf/base.py` | 118 | Base settings (apps, middleware, PostGIS, URN namespace, reminders) |
| `conf/api.py` | 59 | REST framework + spectacular settings |
| `conf/docker.py` | - | Docker-specific settings |
| `conf/dev.py` | - | Development settings |
| `conf/ci.py` | - | CI settings |
| `conf/test.py` | - | Test settings |
| `conf/production.py` | - | Production settings |
| `urls.py` | 106 | Root URL configuration |
| **Fixtures** | | |
| `fixtures/verzoeken.json` | - | Demo verzoeken data |
| `fixtures/taken.json` | - | Demo taken data |
| `fixtures/berichten.json` | - | Demo berichten data |

### Tests

| Path | Coverage |
|---|---|
| `tests/api_strategy/test_api_versioning.py` | API versioning strategy |
| `tests/api_strategy/test_error_format.py` | Error response format |
| `tests/api_strategy/test_json_output.py` | JSON output format |
| `tests/test_cors_configuration.py` | CORS settings |
| `components/verzoeken/api/tests/test_verzoek.py` | Verzoek CRUD |
| `components/verzoeken/api/tests/test_verzoektype.py` | VerzoekType CRUD |
| `components/verzoeken/api/tests/test_verzoektypeversion.py` | Version lifecycle |
| `components/verzoeken/api/tests/test_verzoek_validators.py` | Validation rules |
| `components/verzoeken/api/tests/test_auth.py` | OIDC auth for verzoeken |
| `components/verzoeken/tests/test_models.py` | Model logic |
| `components/verzoeken/tests/test_admin.py` | Admin actions |
| `components/taken/api/tests/test_externetaak.py` | ExterneTaak CRUD |
| `components/taken/api/tests/test_betaaltaak.py` | Payment task specifics |
| `components/taken/api/tests/test_gegevensuitvraagtaak.py` | Data request task specifics |
| `components/taken/api/tests/test_formuliertaak.py` | Form task specifics |
| `components/taken/api/tests/test_externetaak_validators.py` | Task validators |
| `components/taken/api/tests/test_auth.py` | OIDC auth for taken |
| `components/taken/tests/test_models.py` | Model logic |
| `components/taken/tests/test_json_schemas.py` | Schema validation |
| `components/taken/tests/test_admin.py` | Admin |
| `components/berichten/api/tests/test_bericht.py` | Bericht CRUD |
| `components/berichten/api/tests/test_auth.py` | OIDC auth for berichten |
| `components/berichten/tests/test_admin.py` | Admin |
| `accounts/tests/test_oidc.py` | OIDC flow |
| `accounts/tests/test_permission_limit.py` | Permission limits |
| `accounts/tests/test_createinitialsuperuser.py` | Initial setup |
| `accounts/tests/test_user_manager.py` | User management |
| `utils/tests/test_serializers.py` | URN serializer tests |
| `utils/tests/test_validators.py` | Validator tests |
| `utils/tests/test_celery_beat.py` | Celery config |

## Comprehensive Gap Analysis: Pipelinq vs Open VTB

### What Pipelinq Already Has (Overlap)
1. Request/lead intake and management (pipeline stages)
2. Schema-based data validation (via OpenRegister)
3. Object CRUD API
4. UUID-based identification
5. Attachment handling (file storage in OpenRegister)
6. User assignment on objects
7. Status tracking
8. Nextcloud-based authentication

### What Pipelinq Does NOT Have (Gaps)

#### High Priority (Core competitive features)
1. **Payment tracking** - VerzoekBetaling model with provider, amount, currency, transaction reference, completion status
2. **Versioned request type schemas** - Draft/published/deprecated lifecycle with validity periods
3. **Task types with polymorphic details** - Payment tasks (IBAN/amount), form tasks (FormIO), data request tasks
4. **Automatic reminder/deadline calculation** - Configurable days-before-deadline auto-reminders
5. **URN-based cross-system referencing** - RFC 8141 compliant, connects to BRP/HR/ZGW registries

#### Medium Priority (Useful differentiators)
6. **Geo-location on requests** - PostGIS geometry (Point/LineString/Polygon)
7. **Source tracking** - VerzoekBron (which app/form submitted, submission ID)
8. **Channel tracking** - Which intake channel was used
9. **FormIO-compatible form definitions** - Embedded form schemas in tasks
10. **Immutable message audit trail** - Create + read-only berichten API
11. **OIDC authentication for API** - Standards-based identity federation
12. **Action perspective** - What the citizen should do (lezen, naleveren, invullen)

#### Lower Priority (Nice to have)
13. **Language field** on requests (verzoek_taal)
14. **Mijn Overheid integration** for messages
15. **Scheduled publication** for messages
16. **Read tracking** (geopend_op) for messages
17. **Immutable field validators** (prevent verzoek_type changes after creation)
18. **IBAN validation** with RFC-compliant regex
19. **CamelCase JSON API** with VNG standards compliance

### What Pipelinq Has That Open VTB Does NOT
1. **Frontend UI** - Open VTB is API-only (no citizen portal, no case handler UI)
2. **Pipeline views** - Visual pipeline management (Kanban-style)
3. **Nextcloud integration** - File management, user management, app ecosystem
4. **n8n workflow automation** - Business process orchestration
5. **OpenCatalogi integration** - Catalog/search functionality
6. **Multi-register architecture** - Flexible data storage across registers
7. **NL Design theming** - Government design system support
8. **Real-time notifications** - Nextcloud notification system
9. **MCP protocol** - AI/LLM integration via Model Context Protocol

## Strategic Assessment

### Open VTB Strengths
- **VNG-backed standard** -- adoption by municipalities likely if it becomes an official standard
- **Clean API design** -- well-structured, well-documented, standards-compliant
- **Ecosystem integration** -- designed to work with Open Formulieren, ZGW APIs, Mijn Overheid
- **URN addressing** -- enables loose coupling with government registries
- **Comprehensive validation** -- JSON Schema, URN, date, IBAN validation

### Open VTB Weaknesses
- **API-only** -- no frontend, no admin UI beyond Django admin
- **Early stage** -- v0.1.0, no production deployments visible
- **No workflow engine** -- no automation, no process orchestration
- **Limited scope** -- only verzoeken/taken/berichten, no broader case management
- **No search/filter** -- basic pagination only, no faceted search
- **Django monolith** -- traditional server-side architecture, not cloud-native
- **Single database** -- all components share one PostGIS instance

### Recommendation for Pipelinq
1. **Adopt the versioned schema lifecycle** pattern (draft/published/deprecated) -- this is a strong pattern for managing evolving intake forms
2. **Consider payment task type** -- if government clients need payment tracking in pipelines
3. **Evaluate URN addressing** -- useful for interoperability with other VNG/ZGW systems
4. **Monitor VTB adoption** -- if it becomes a VNG standard, Pipelinq may need to implement VTB-compatible APIs as an interoperability layer
