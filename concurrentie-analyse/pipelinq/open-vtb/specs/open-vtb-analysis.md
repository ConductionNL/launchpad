# Open VTB (Verzoeken, Taken en Berichten) - Competitive Analysis

**Repository:** https://github.com/maykinmedia/open-vtb
**Version:** 0.1.0 (pre-release, no official stable yet)
**Developer:** Maykin Media B.V. (commissioned by Platform Dienstverlening werkgroep)
**License:** EUPL 1.2
**Stack:** Python 3.12, Django, Django REST Framework, PostgreSQL/PostGIS, Redis, uWSGI

---

## 1. Architecture Overview

Open VTB is a **pure API-first backend** -- there is NO end-user frontend. It provides three separate REST APIs for government service delivery:

- **Verzoeken API** (Requests) -- `/verzoeken/api/v1/`
- **Taken API** (Tasks) -- `/taken/api/v1/`
- **Berichten API** (Messages) -- `/berichten/api/v1/`

Each component has its own OpenAPI specification, separate ReDoc documentation, and independent URL namespace. The only UI is the **Django admin panel** for data management and a minimal landing page linking to the API docs.

### Key Architectural Decisions
- **Separate API namespaces per component** -- each with its own versioning (`v1`)
- **URN-based linking** -- uses Uniform Resource Names (e.g., `urn:nld:brp:bsn:111222333`) instead of foreign keys for cross-system references
- **JSON Schema validation** -- VerzoekType definitions include JSON schemas that validate submitted data
- **CamelCase API output** -- uses `djangorestframework-camel-case` for camelCase serialization
- **VNG API Common** -- follows Dutch government API standards (pagination, error format, etc.)
- **PostGIS** -- supports geospatial data on Verzoeken (Point, LineString, Polygon)
- **OIDC authentication** -- built-in OpenID Connect support for SSO
- **2FA support** -- maykin-2fa with TOTP and WebAuthn hardware tokens

---

## 2. Data Models

### 2.1 Verzoeken (Requests)

#### VerzoekType
| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID4 | Unique identifier |
| naam | CharField(100) | Name |
| omschrijving | TextField(4000) | Internal description |
| aangemaakt_op | DateField | Created date (auto) |
| gewijzigd_op | DateField | Modified date (auto) |

#### VerzoekTypeVersion
| Field | Type | Description |
|-------|------|-------------|
| verzoek_type | FK(VerzoekType) | Parent type |
| versie | PositiveSmallInteger | Version number (auto-incremented) |
| status | CharField(20) | draft / published / deprecated |
| aanvraag_gegevens_schema | JSONField | JSON Schema for validation |
| begin_geldigheid | DateField | Publication date (auto-set on publish) |
| einde_geldigheid | DateField | Expiry date (auto-set when new version published) |
| bijlage_typen | Reverse FK | Allowed attachment types |

#### Verzoek
| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID4 | Unique identifier |
| verzoek_type | FK(VerzoekType) | Type reference |
| versie | PositiveSmallInteger | Schema version used |
| geometrie | GeometryField | GIS location (Point/LineString/Polygon) |
| aanvraag_gegevens | JSONField | Submitted data (validated against schema) |
| initiator | URNField | Person/organization URN (BSN, KvK, etc.) |
| is_gerelateerd_aan | JSONField | List of URNs to ZAAKen or PRODUCTen |
| kanaal | CharField(200) | Intake channel |
| verzoek_informatie_object | URNField | URN to document |
| verzoek_taal | CharField(2) | Language code (default: "nl") |

**Related inline objects:**
- **VerzoekBron** (1:1) -- source application name + identifier
- **VerzoekBetaling** (1:1) -- payment provider, amount, currency, status
- **Bijlage** (1:N) -- attachments via informatie_object URN

#### BijlageType
| Field | Type | Description |
|-------|------|-------------|
| verzoek_type_versie | FK(VerzoekTypeVersion) | Parent version |
| informatie_objecttype | URNField | Allowed document type URN |
| omschrijving | TextField | Description for end users |

### 2.2 Taken (Tasks)

#### ExterneTaak
| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID4 | Unique identifier |
| titel | CharField(100) | User-facing title |
| status | CharField(20) | open / uitgevoerd / niet_uitgevoerd / afgebroken / verwerkt |
| startdatum | DateField | Start date (default: today) |
| handelings_perspectief | CharField(100) | Action type: "lezen", "naleveren", "invullen" |
| einddatum_handelings_termijn | DateField | Deadline |
| datum_herinnering | DateField | Auto-calculated reminder (N days before deadline) |
| toelichting | TextField(4000) | User-facing description |
| taak_soort | CharField(20) | betaaltaak / gegevensuitvraagtaak / formuliertaak |
| details | JSONField | Polymorphic data validated per taak_soort |
| is_toegewezen_aan | URNField | Assigned person/org URN |
| wordt_behandeld_door | URNField | Handler employee URN |
| hoort_bij | URNField | Related ZAAK URN |
| heeft_betrekking_op | URNField | Related PRODUCT URN |

**Task types (polymorphic via `details` JSON):**

1. **BetaalTaak** -- payment link
   - bedrag, valuta (EUR only), transactieomschrijving, doelrekening (naam/code/iban)

2. **GegevensUitvraagTaak** -- external form link
   - uitvraagLink (URL), voorinvullenGegevens (key-value), ontvangenGegevens (key-value)

3. **FormulierTaak** -- embedded form definition
   - formulierDefinitie (FormIO-compatible JSON with components: label/key/type)
   - voorinvullenGegevens, ontvangenGegevens

### 2.3 Berichten (Messages)

#### Bericht
| Field | Type | Description |
|-------|------|-------------|
| uuid | UUID4 | Unique identifier |
| onderwerp | CharField(50) | Subject line |
| bericht_tekst | TextField(4000) | Message body (Markdown for portals, newlines for MijnOverheid) |
| publicatiedatum | DateTimeField | Scheduled publication (default: now) |
| referentie | CharField(25) | Internal reference |
| ontvanger | URNField | Recipient person/org URN |
| geopend_op | DateTimeField | When recipient opened it (nullable) |
| bericht_type | CharField(8) | MijnOverheid template code (if set, forwards to MijnOverheid) |
| handelings_perspectief | CharField(50) | Expected action |
| einddatum_handelings_termijn | DateTimeField | Action deadline |

#### Bijlage (Message attachment)
| Field | Type | Description |
|-------|------|-------------|
| bericht | FK(Bericht) | Parent message |
| informatie_object | URNField | Document URN |
| omschrijving | CharField(40) | Short description |
| is_bericht_type_bijlage | BooleanField | If true, part of MijnOverheid template |

---

## 3. API Endpoints

### Verzoeken API (`/verzoeken/api/v1/`)
| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/verzoeken` | GET, POST | List/create requests |
| `/verzoeken/{uuid}` | GET, PUT, PATCH, DELETE | CRUD on single request |
| `/verzoektypen` | GET, POST | List/create request types |
| `/verzoektypen/{uuid}` | GET, PUT, PATCH, DELETE | CRUD on single type |
| `/verzoektypen/{uuid}/versies` | GET, POST | List/create versions |
| `/verzoektypen/{uuid}/versies/{versie}` | GET, PUT, PATCH, DELETE | CRUD on version (DELETE only for draft) |

### Taken API (`/taken/api/v1/`)
| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/externetaken` | GET, POST | List/create all task types |
| `/externetaken/{uuid}` | GET, PUT, PATCH, DELETE | CRUD on single task |
| `/betaaltaken` | GET, POST | Filtered: payment tasks only |
| `/betaaltaken/{uuid}` | GET, PUT, PATCH, DELETE | CRUD on payment task |
| `/gegevensuitvraagtaken` | GET, POST | Filtered: data request tasks only |
| `/gegevensuitvraagtaken/{uuid}` | GET, PUT, PATCH, DELETE | CRUD on data request task |
| `/formuliertaken` | GET, POST | Filtered: form tasks only |
| `/formuliertaken/{uuid}` | GET, PUT, PATCH, DELETE | CRUD on form task |

### Berichten API (`/berichten/api/v1/`)
| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/berichten` | GET, POST | List/create messages |
| `/berichten/{uuid}` | GET | Read-only retrieve (no update/delete!) |

**Notable:** Berichten is CREATE + READ only -- no update or delete via API. This is intentional for audit trail integrity.

---

## 4. Admin Panel Features

### Navigation Tabs
- Dashboard, Accounts, API Autorisaties, Verzoeken, Taken, Berichten, Configuratie, Logging, Overige

### VerzoekType Admin
- **List view:** naam, uuid, aangemaakt_op, gewijzigd_op, last_versie
- **Detail view:** Inline VerzoekTypeVersion (shows only latest version)
- **Custom buttons:** "Publish" (changes status to published, sets begin_geldigheid), "New version" (clones current version with incremented number)
- **Inline BijlageType management**
- **JSON Schema editor** with Suit/Raw toggle (syntax-highlighted JSON editor)

### Verzoek Admin
- **List view:** uuid, verzoek_type
- **List filter:** by verzoek_type
- **Search:** uuid, verzoek_type naam, bron naam
- **Inline sections:** VerzoekBron, VerzoekBetaling, Bijlagen
- **JSON fields:** Suit editor for aanvraag_gegevens and is_gerelateerd_aan
- **Geometry field:** rendered as textarea (no map widget)

### ExterneTaak Admin
- **List view:** titel, uuid, taak_soort, status, startdatum
- **List filters:** taak_soort (Betaallink/Extern formulier/Standaard formulier), status (Open/Uitgevoerd/Niet uitgevoerd/Afgebroken/Verwerkt)
- **Search:** uuid, titel, startdatum
- **JSON Suit editor** for details field

### Bericht Admin
- **List view:** uuid, onderwerp, publicatiedatum, ontvanger, geopend_op
- **Search:** uuid, onderwerp
- **Inline Bijlagen** (attachments)

---

## 5. Authentication & Authorization

- **Token-based authentication** (DRF TokenAuth) for API access
- **OIDC integration** (mozilla-django-oidc-db) for SSO admin login
- **2FA** (maykin-2fa) with TOTP and WebAuthn support
- **Axes** brute-force protection (access attempts/logs)
- **All API endpoints require `IsAuthenticated`** -- no public access
- **No granular permissions** -- any authenticated user has full CRUD (no role-based access control)

---

## 6. Comparison with Pipelinq

### What Open VTB Does That Pipelinq Does Not
| Feature | Open VTB | Pipelinq |
|---------|----------|----------|
| **Verzoeken (Request intake)** | Full typed request system with JSON Schema validation per type, versioning, payment integration, geospatial data | No request intake concept |
| **Polymorphic task types** | BetaalTaak, GegevensUitvraagTaak, FormulierTaak with JSON Schema validation per type | Single task concept |
| **MijnOverheid integration** | Berichten can forward to national MijnOverheid berichtenbox via bericht_type codes | No national portal integration |
| **URN-based linking** | Standard URN patterns for BSN, KvK, ZAAK, PRODUCT references | Uses internal IDs and register references |
| **Geospatial data** | PostGIS geometry on requests | No GIS support |
| **JSON Schema validation** | Server-side validation of request data against typed schemas | Client-side validation only |
| **API-first architecture** | No frontend at all -- pure API | Full Nextcloud frontend app |
| **Dutch gov API standards** | VNG API Common compliance (pagination, error format, audit trails) | Custom API patterns |
| **OpenAPI per component** | Three separate OAS specs with ReDoc | Single unified API |
| **Automatic reminder dates** | Configurable N-days-before-deadline auto-calculation | No auto-reminders |

### What Pipelinq Does That Open VTB Does Not
| Feature | Pipelinq | Open VTB |
|---------|----------|----------|
| **End-user frontend** | Full Nextcloud Vue.js UI with views, lists, detail pages | No frontend -- admin only |
| **Pipeline/workflow engine** | Visual pipeline builder, step-by-step flows, conditional logic | No workflow concept |
| **User-facing portal** | Citizens/employees can interact directly | API consumers need their own frontend |
| **Rich search/filtering** | Faceted search with configurable facets | Basic list + filter in admin |
| **NL Design System theming** | Government-compliant theming via CSS variables | No theming (headless) |
| **Nextcloud integration** | Files, users, sharing, notifications, calendar | Standalone application |
| **Multi-register support** | Store objects in configurable registers with schemas | Fixed model structure |
| **Real-time collaboration** | Nextcloud real-time features | No real-time features |

### Key Insight
Open VTB and Pipelinq serve **fundamentally different roles** in the government software landscape:

- **Open VTB** = **Register/API layer** for storing and exchanging Verzoeken, Taken, and Berichten between systems. It is a data store with standardized APIs that other applications (portals, case management systems) consume.

- **Pipelinq** = **Process management application** with a user-facing interface for managing workflows, cases, and client interactions within Nextcloud.

They are **complementary rather than competitive**. Pipelinq could potentially consume Open VTB's APIs to:
1. Create Verzoeken when citizens submit requests through Pipelinq
2. Assign Taken to citizens/employees as part of pipeline steps
3. Send Berichten as notifications during process execution

---

## 7. Maturity Assessment

| Aspect | Rating | Notes |
|--------|--------|-------|
| **Code quality** | High | Clean Django patterns, proper serializers, validators, factories, tests |
| **API design** | High | VNG-compliant, OpenAPI specs, proper versioning, URN patterns |
| **Documentation** | Medium | ReadTheDocs site exists but sparse; API docs via ReDoc are complete |
| **Test coverage** | Medium | Unit tests for API endpoints, model validation, admin; no E2E tests |
| **Frontend** | None | No end-user UI -- intentional design choice |
| **Release maturity** | Low | Version 0.1.0, no stable release yet, CHANGELOG.rst is empty |
| **Deployment** | High | Docker-ready, setup_configuration automation, health checks, OTEL observability |
| **Security** | High | OIDC, 2FA, TOTP, WebAuthn, brute-force protection, CSP headers |

### Codebase Size
- ~70 Python source files
- 3 Django apps (verzoeken, taken, berichten) + accounts + utils
- 3 OpenAPI specifications
- Well-structured with clear separation of concerns

---

## 8. Screenshots Index

| # | File | Description |
|---|------|-------------|
| 01 | `01-homepage.png` | Landing page with NL/EN tabs, concept descriptions, component links |
| 02 | `02-verzoeken-landing.png` | Verzoeken component page with API docs + OAS links |
| 03 | `03-verzoeken-api-redoc.png` | ReDoc API documentation for Verzoeken |
| 04 | `04-taken-landing.png` | Taken component page |
| 05 | `05-berichten-landing.png` | Berichten component page |
| 06 | `06-admin-dashboard.png` | Full admin dashboard showing all sections |
| 07 | `07-admin-verzoektype-add.png` | VerzoekType creation form with JSON Schema editor |
| 08 | `08-admin-verzoektype-created.png` | Created VerzoekType with version, schema, and Publish button |
| 09 | `09-admin-verzoek-add.png` | Verzoek creation form with type selector, JSON data, URN fields |
| 10 | `10-admin-verzoek-created.png` | Created Verzoek with all fields populated |
| 11 | `11-admin-taak-add.png` | ExterneTaak creation form with all task fields |
| 12 | `12-admin-taak-created.png` | Created task with auto-calculated reminder date |
| 13 | `13-admin-bericht-add.png` | Bericht creation form with message text, recipient, dates |
| 14 | `14-admin-bericht-created.png` | Created message with all fields |
| 15 | `15-admin-taken-list.png` | Task list view with filters (taak soort, status) |
