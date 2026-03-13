# Open VTB Documentation

## Overview

**Open VTB** (Open Verzoeken, Taken en Berichten) is an open-source registration component developed by **Maykin Media B.V.** for the "Platform Dienstverlening werkgroep." It provides APIs and registries for managing citizen requests (Verzoeken), government-assigned tasks (Taken), and messages (Berichten) between citizens/businesses and municipalities.

- **Version**: 0.1.0
- **License**: EUPL 1.2
- **Language**: Python 89.6% (Django)
- **Repository**: https://github.com/maykinmedia/open-vtb
- **Documentation**: https://open-vtb.readthedocs.io/ (referenced in README but may not be live)
- **Docker Hub**: https://hub.docker.com/r/maykinmedia/open-vtb (referenced but may not be published)
- **Status**: Early-stage (v0.1.0, 169 commits, 2 stars, 0 forks)

## Maturity Assessment

Open VTB is a **pre-release product** at version 0.1.0. Key maturity indicators:

| Indicator | Value | Assessment |
|-----------|-------|------------|
| Version | 0.1.0 | Pre-release, not production-ready |
| GitHub Stars | 2 | Minimal community adoption |
| GitHub Forks | 0 | No external contributors |
| Commits | 169 | Active development but limited history |
| Open Issues | 22 | Some backlog of work |
| Last Activity | January 2025 | Potentially stalled |
| Docker Hub | Referenced but 404 | Image may not be published |
| ReadTheDocs | Referenced but may be empty | Documentation may be incomplete |
| Production Users | None known | No known deployments |

### VNG API Standards Context

Open VTB positions itself as an implementation of VNG API standards, but the standards themselves are in flux:

- **Verzoeken API** (VNG-Realisatie): **Archived** (June 2023), moved to Klantinteracties
- **Klantinteracties API** (VNG-Realisatie): **Not a standard**, described as a "half-product" and explicitly "not recommended for use"
- **Taken API**: No formal VNG standard exists; Open VTB defines its own
- **Berichten API**: No formal VNG standard exists; Open VTB defines its own

This means Open VTB is building on deprecated and unstable specifications, creating its own de facto standards for Taken and Berichten.

## Architecture

### Technology Stack

- **Python 3.12+** with Django 5.2
- **Django REST Framework** for APIs
- **PostGIS** (PostgreSQL with spatial extensions)
- **vng-api-common** for VNG API compliance patterns
- **drf-spectacular** for OpenAPI 3.x generation
- **mozilla-django-oidc-db** for OIDC authentication
- **djangorestframework-camel-case** for JSON naming conventions
- **Ruff** for code quality/linting
- **Docker** for deployment

### Repository Structure

```
open-vtb/
  .github/           # CI/CD workflows
  bin/                # Scripts
  build/              # Build artifacts
  docker/             # Docker config
  docs/               # Documentation source
  log/                # Logs
  requirements/       # Python dependencies
  src/
    manage.py
    openvtb/
      accounts/       # User management
      components/
        __init__.py
        drf_spectacular.py
        schemas.py
        views.py
        widgets.py
        verzoeken/    # Requests component
          api/        # REST API views/serializers
          migrations/ # DB migrations
          tests/      # Tests
          admin.py    # Django admin
          constants.py # Enums (VerzoekTypeVersionStatus)
          forms.py    # Admin forms
          models.py   # 7 models
          openapi.yaml # OpenAPI spec
        taken/        # Tasks component
          api/        # REST API views/serializers
          migrations/ # DB migrations
          tests/      # Tests
          admin.py    # Django admin
          constants.py # Enums (StatusTaak, SoortTaak)
          models.py   # ExterneTaak model
          schemas.py  # JSON schemas for task types
          openapi.yaml # OpenAPI spec
        berichten/    # Messages component
          api/        # REST API views/serializers
          migrations/ # DB migrations
          tests/      # Tests
          admin.py    # Django admin
          models.py   # Bericht + Bijlage models
          openapi.yaml # OpenAPI spec
      conf/           # Settings
      fixtures/       # Demo data
      management/     # Management commands
      js/             # Frontend JS
      scss/           # Styles
      static/         # Static assets
      templates/      # HTML templates
      tests/          # Project-level tests
      utils/          # Shared utilities (URNField, validators)
```

### Three Separate APIs

Open VTB runs as a single Django application but exposes three separate API endpoints:

1. **Verzoeken API** (`/verzoeken/api/v1/`) - Request management
2. **Taken API** (`/taken/api/v1/`) - Task management
3. **Berichten API** (`/berichten/api/v1/`) - Message management

Each has its own OpenAPI specification, serializers, and view sets.

## Quick Start

```bash
wget https://raw.githubusercontent.com/maykinmedia/open-vtb/main/docker-compose.yml
docker-compose up -d --no-build
docker-compose exec web src/manage.py loaddata verzoeken taken berichten
docker-compose exec web src/manage.py createsuperuser
```

Access at `http://localhost:8000/`

## Core Concepts

### Verzoeken (Requests)

Decouples citizen request intake from case management. A Verzoek captures structured form data (validated against a versioned JSON Schema), links to the initiating person/org via URN, and references related cases/products.

**Models**: VerzoekType, VerzoekTypeVersion, Verzoek, VerzoekBron, VerzoekBetaling, Bijlage, BijlageType

### Taken (Tasks)

External tasks assigned by case handlers (ZAC) to citizens/businesses. Three task types:
- **Betaaltaak**: Payment requests with IBAN, amount, currency
- **Formuliertaak**: Embedded FormIO-compatible forms
- **Gegevensuitvraagtaak**: Links to external data collection forms

**Model**: ExterneTaak (single table, polymorphic via taak_soort)

### Berichten (Messages)

One-way government-to-citizen messages with optional Mijn Overheid forwarding. Create + Read only (no update/delete for audit trail).

**Models**: Bericht, Bijlage

## Ecosystem Position

### Related Maykin Products

| Product | Purpose | Relationship to Open VTB |
|---------|---------|-------------------------|
| **Open Zaak** | Case management (zaakregistratie) | VTB references cases via URN |
| **Open Klant** | Customer registry | VTB references persons via URN |
| **Open Formulieren** | Form builder | Submits verzoeken to VTB |
| **Open Inwoner** | Citizen portal | Displays taken + berichten from VTB |
| **Open Notificaties** | Event notifications | Can notify on VTB state changes |
| **Open Product** | Product catalog | VTB references products via URN |

### Common Ground Ecosystem

Open VTB fills the gap between:
1. **Citizen submits request** (via Open Formulieren / portal)
2. **Request becomes a case** (in Open Zaak)
3. **Tasks are assigned back** to the citizen (displayed in Open Inwoner)
4. **Messages are sent** to the citizen (via portal or Mijn Overheid)

### VNG API Standards Status

| API | VNG Status | Open VTB Status |
|-----|-----------|-----------------|
| Verzoeken API | Archived (2023), moved to Klantinteracties | Implements own v0.1.0 |
| Taken API | No formal standard | Defines own v0.1.0 |
| Berichten API | No formal standard | Defines own v0.1.0 |
| Klantinteracties | "Half-product", not recommended | Not implemented |
| Zaken API | Active (v1.5.1) | Referenced via URN |
| Documenten API | Active (v1.5.0) | Referenced via URN |

## Authentication

- **OpenID Connect**: Primary production auth via mozilla-django-oidc-db
- **Token Authentication**: API key-based auth for service-to-service calls
- **Django Admin**: Session-based auth for admin interface

## Known Limitations

1. No user-facing interface (API-only, requires a front-end like Open Inwoner)
2. No search/filter capabilities documented (basic list endpoints only)
3. No notification/webhook support built-in
4. No multi-tenancy support
5. No audit logging beyond Berichten immutability
6. EUR-only currency support for payment tasks
7. Single deployment model (no SaaS offering discovered)
8. No known production deployments
9. Building on deprecated/unstable VNG standards
