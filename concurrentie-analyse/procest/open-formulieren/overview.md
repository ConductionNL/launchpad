# Open Formulieren (Open Forms) -- Competitive Analysis for Procest

## What is Open Forms?

Open Forms (open-formulieren) is Maykin Media's smart e-forms platform for Dutch government organizations. It provides **form intake** -- the citizen-facing front door that collects structured data and routes it to case management backends. It is explicitly **complementary to case management**, not a replacement: Open Forms creates cases, it does not process them.

**GitHub:** https://github.com/open-formulieren/open-forms
**License:** EUPL 1.2
**Stack:** Python/Django backend, React frontend (SDK), Celery for async processing, PostgreSQL

## Architecture Overview

```
                    Citizen Browser
                         |
                    [Open Forms SDK]  (React, NL Design System themed)
                         |
                    [REST API v2]
                         |
              +----------+-----------+
              |    Open Forms Core   |
              |                      |
              |  Forms Engine        |  <-- Form.io JSON schemas
              |  Logic Engine        |  <-- JSON Logic evaluation
              |  Submission Manager  |  <-- State machine + Celery chain
              |  Plugin System       |  <-- Auth, Prefill, Registration, Payment, Validation
              |                      |
              +--+----+----+----+---+
                 |    |    |    |
    DigiD/eH  Prefill  |  Payment   Registration
    (SAML/OIDC) APIs   |  (Ogone)   Backends
                       |              |
                  DMN Engine    +-----+------+-----+
                  (Camunda)     |     |      |     |
                            ZGW APIs  Objects StUF  Email
                            (OpenZaak) API   ZDS
                                |
                           Zaken API
                           Documenten API
                           Catalogi API
```

## Technology Decisions

| Aspect | Open Forms | Procest |
|--------|-----------|---------|
| Platform | Standalone Django app | Nextcloud app (PHP) |
| Form engine | Form.io JSON schema | None (no form builder) |
| Logic engine | JSON Logic (Python) | n8n workflows |
| Task queue | Celery + Redis | n8n / Nextcloud cron |
| Auth | DigiD/eHerkenning (SAML/OIDC) | Nextcloud auth (LDAP/SAML) |
| Storage | PostgreSQL + file storage | OpenRegister + Nextcloud files |
| Frontend | React SDK (embeddable) | Vue.js (Nextcloud integrated) |
| Theming | NL Design System tokens | NL Design System tokens |
| API style | DRF REST API | Nextcloud OCS API |

## Integration with Case Management

This is the critical relationship: **Open Forms is the intake layer; case management (OpenZaak, Procest) is the processing layer.**

### How Open Forms Creates Cases

1. **Form submission completed** -- Citizen fills out multi-step form
2. **Pre-registration** -- Open Forms creates a Zaak (case) via ZGW Zaken API, obtaining a case number
3. **Registration** -- Open Forms attaches: confirmation PDF, file uploads, initiator role (BSN/KvK), case properties (eigenschappen), initial status
4. **Result** -- A fully initialized case exists in OpenZaak/case management with all submitted data and documents

### ZGW API Integration (Primary)

Open Forms is the most complete ZGW API client in the Dutch government ecosystem:
- **Zaken API**: Creates zaak, rol, status, zaakeigenschap, zaakinformatieobject
- **Documenten API**: Uploads PDF reports and file attachments as informatieobjecten
- **Catalogi API**: Resolves zaaktype, informatieobjecttype, statustype, eigenschap by catalogue + identification + validity date
- Supports confidentiality levels, organisation RSIN, generated vs ZGW-assigned case numbers

### Objects API Integration (Alternative)

For municipalities using the Objects API instead of (or alongside) ZGW APIs:
- Creates/updates objects with structured form data
- Two mapping versions: template-based (v1) and variable-mapped (v2)
- Supports ownership validation for updating existing objects
- Attaches documents via Documenten API

### What This Means for Procest

Open Forms fills a gap that Procest currently has: **there is no citizen-facing form intake**. Procest handles case processing (pipeline stages, object management) but has no way for citizens to submit structured data that automatically creates cases.

**Options for Procest:**
1. **Integrate with Open Forms** -- Use Open Forms as the intake layer, configure it to register submissions via Objects API into OpenRegister
2. **Build form intake** -- Create a form builder within Procest/Nextcloud (large effort, replicating years of Open Forms development)
3. **Complement** -- Position Procest as case processing that receives cases from Open Forms or other intake channels

## Feature Gap Summary

### Open Forms Has, Procest Does Not Have

| Category | Features | Effort to Build |
|----------|----------|----------------|
| **Form Engine** | Form.io builder, drag-and-drop, 20+ component types, reusable definitions | Very High |
| **Form Logic** | JSON Logic engine, conditional visibility, calculated values, DMN, service fetch | Very High |
| **Authentication** | DigiD, eHerkenning, OIDC, Level of Assurance, auto-login | High |
| **Prefill** | 10 plugins (BRP, KvK, StUF-BG, Suwinet, Objects API, etc.) | High |
| **Registration** | 7 backends (ZGW, Objects API, StUF-ZDS, Email, Camunda, SharePoint, JSON) | High |
| **Payment** | Ogone/Worldline, price calculation, payment-gated registration | Medium |
| **Co-signing** | Out-of-band approval flow with authentication | Medium |
| **Appointments** | JCC/Qmatic integration, product/location/timeslot booking | Medium |
| **Submissions** | Suspension/resume, PDF reports, retry mechanism, status polling | Medium |
| **Data Lifecycle** | Retention periods, anonymization, sensitive data marking, BSN hashing | Medium |
| **Multi-step Wizard** | Progress indicator, step skipping, overview page | Medium |
| **Email** | Template-based confirmations, co-sign requests, admin digests | Low-Medium |
| **Validation** | BSN/IBAN/KvK/postcode validators, server-side re-validation | Low |
| **Analytics** | GA/Matomo/Piwik/SiteImprove/GovMetric plugins | Low |
| **Multi-domain** | Single instance serving multiple domains with per-domain theming | Low |

### Procest Has, Open Forms Does Not Have

| Category | Features |
|----------|----------|
| **Case Processing** | Pipeline stages, stage transitions, case status workflow |
| **Object Storage** | OpenRegister with schemas, faceted search, CRUD API |
| **Nextcloud Integration** | File management, user management, app ecosystem |
| **n8n Workflows** | Visual workflow builder for business logic and integrations |
| **Dashboard** | MyDash for case overview and management |

## Codebase Statistics

- **Python source files**: ~800+ in `src/openforms/`
- **Django apps**: 25+ (forms, submissions, authentication, registrations, payments, prefill, appointments, config, emails, formio, dmn, validations, variables, analytics_tools, etc.)
- **Plugins**: 40+ across all registries (auth: 8, prefill: 11, registration: 8, payment: 3, appointment: 4, validation: 5+, analytics: 8, DMN: 1)
- **Frontend**: React admin builder + embeddable SDK
- **API**: Comprehensive DRF REST API (v2 and v3 endpoints)
- **Tests**: Extensive test coverage with VCR cassettes for external service mocking

## Key Files Reference

| File | Purpose |
|------|---------|
| `src/openforms/forms/models/form.py` | Core Form model |
| `src/openforms/forms/models/form_definition.py` | Form.io schema storage |
| `src/openforms/forms/models/logic.py` | Form logic rules |
| `src/openforms/submissions/models/submission.py` | Submission state machine |
| `src/openforms/submissions/tasks/__init__.py` | Post-completion Celery chain |
| `src/openforms/registrations/tasks.py` | Registration task logic |
| `src/openforms/registrations/contrib/zgw_apis/plugin.py` | ZGW API registration |
| `src/openforms/registrations/contrib/objects_api/plugin.py` | Objects API registration |
| `src/openforms/authentication/contrib/digid/plugin.py` | DigiD auth plugin |
| `src/openforms/prefill/base.py` | Prefill plugin base class |
| `src/openforms/payments/base.py` | Payment plugin base class |
| `src/openforms/config/models/theme.py` | NL Design System theming |
