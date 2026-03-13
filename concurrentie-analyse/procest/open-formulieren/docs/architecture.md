# Open Formulieren — Architecture

## Component Overview

Open Formulieren follows a decoupled frontend/backend architecture built on Common Ground principles.

### Backend (Python/Django)

- **Framework:** Python 3.12, Django
- **Database:** PostgreSQL (backing store for form definitions, submissions, configuration)
- **Task Queue:** Celery with automatic retry for asynchronous background processing (registration, emails, cleanup)
- **Admin Interface:** Django admin for content editors and system administrators
- **REST API:** Powers the SDK and third-party clients; divided into public and private endpoints

### Frontend (SDK)

- **Technology:** JavaScript, built on top of form.io JS SDK (`@open-formulieren/formiojs`)
- **Package:** Published as NPM library (`@open-formulieren/sdk`) and pre-built Docker image
- **Embedding:** JavaScript snippet + stylesheet loaded into any CMS page
- **Initialization:** Constructor with DOM node and options object; backend must allow CORS from embedding domains

### Plugin System

The architecture is fully plugin-based across four categories:

1. **Authentication plugins** — DigiD, eHerkenning, eIDAS, SAML, OIDC
2. **Prefill plugins** — Haal Centraal BRP, StUF-BG, KvK/Handelsregister
3. **Registration plugins** — ZGW APIs, StUF-ZDS, Objects API, Email
4. **Payment plugins** — Ogone/Ingenico (Worldline replacement in 3.3+)

Each plugin is a Python callable registered with the framework, making it available to content editors in the admin interface.

### API Structure

- **Public endpoints:** Form categories, available forms — subject to semantic versioning
- **Private endpoints:** Admin, submission processing — not subject to SemVer
- **OpenAPI 3 spec:** Available in project root and at `/api/docs/` on running instances
- **SDK communication:** All form rendering, submission, and step navigation happens via REST API calls

### Processing Flow

```
[Citizen Browser] → [SDK (JS)] → [REST API] → [Django Backend]
                                                      ↓
                                              [Celery Queue]
                                                      ↓
                                    [Registration Plugin (ZGW/Objects/StUF/Email)]
                                                      ↓
                                        [External System (OpenZaak, etc.)]
```

### Infrastructure

- **Docker:** Official images at `openformulieren/open-forms`
- **Docker Compose:** Development setup included
- **Deployment:** SaaS via OpenGem/Maykin or self-hosted
- **NL Design System:** CSS custom properties for government theming

## Comparison with Procest Architecture

| Aspect | Open Formulieren | Procest |
|--------|-----------------|---------|
| Runtime | Standalone Django + Celery | Nextcloud app (PHP) |
| Frontend | Decoupled JS SDK, embeddable | Integrated Vue.js SPA in Nextcloud |
| Database | Own PostgreSQL | Nextcloud database + OpenRegister |
| Auth | Own DigiD/eHerkenning stack | Nextcloud auth + ZGW JWT |
| API style | REST (OpenAPI 3) | Nextcloud REST + ZGW proxy |
| Plugin system | Python callables | PHP services + OpenRegister schemas |
| Task processing | Celery async queue | Synchronous + n8n workflows |
| Deployment | Docker standalone | Nextcloud app store |

### Key Architectural Difference

Open Formulieren is a **standalone web application** requiring its own infrastructure, database, task queue, and authentication stack. Procest is a **Nextcloud app** that inherits user management, file storage, collaboration, and document management from the Nextcloud platform. This means Procest can offer integrated document handling, team collaboration, and case management without requiring additional middleware.
