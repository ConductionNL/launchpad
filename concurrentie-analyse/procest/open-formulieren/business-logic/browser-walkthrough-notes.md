# Open Formulieren - Complete Browser Walkthrough Notes

## Setup & Deployment

- **Source**: https://github.com/open-formulieren/open-forms
- **Image**: `openformulieren/open-forms:latest`
- **Stack**: Docker Compose with PostgreSQL 15, Redis 8, Nginx (reverse proxy with OpenTelemetry module), uWSGI (Django), Celery worker, Celery Beat, SMTP relay
- **Port**: 9015 (mapped through nginx -> uwsgi:8000)
- **Network**: Isolated Docker network (`of-isolated-net`)
- **Technology**: Django 4.x + React (admin form builder), Form.io (form rendering engine), SDK (frontend form renderer)

### Key deployment observations
- MFA (TOTP) is **enforced** on all admin accounts via `maykin_2fa.middleware.OTPMiddleware` - there is no way to disable this without code changes
- OpenTelemetry is baked into the nginx and uwsgi configs; without an OTEL collector it produces noisy but harmless error logs
- The `RUN_SETUP_CONFIG=False` env var skips auto-configuration of external services (ZGW, OIDC, etc.)
- Django uses `X-Forwarded-Host` header to determine the base URL for the SDK frontend; nginx must forward the external host:port correctly
- The `CSRF_TRUSTED_ORIGINS` setting must include the external URL with port

## Admin Interface Overview

### Navigation Structure (Top Menu Bar)
1. **Dashboard** - Home page with quick links to all model admin pages
2. **Accounts** - Groups, Static devices, TOTP devices, Tokens, User preferences, Users
3. **Forms** - Form definitions, Form categories, Form steps, Form versions, Reusable form definitions
4. **Submissions** - Submission list with filtering by state, form, date
5. **Appointments** - Appointment configuration
6. **Products** - Product management (linked to payments)
7. **Configuration** - Extensive configuration system (see below)
8. **Logs** - Audit logging, outgoing request logs, timeline logging
9. **Useful links** - External links
10. **Miscellaneous** - Additional admin models

### Configuration Sub-items (30+ items)
- Analytics tools configuration
- Application groups
- Appointment configuration (JCC, JCC Rest, Qmatic)
- Blacklisted emails
- Camunda configuration
- Certificates (simple-certmanager)
- Configuration overview (plugin status dashboard)
- Cookie Groups / Cookies
- CSP settings
- DigiD/eHerkenning certificates and configuration
- Domains (multi-domain support)
- Flag states (feature flags)
- General configuration (massive settings page)
- Global prefill configuration
- Map tile layers / WMS layers
- NLX configuration
- OIDC Providers / OIDC clients
- Outgoing request log configuration
- SOAP services
- Service fetch configurations
- Services (ZGW consumers - ZRC, ZTC, DRC, BRC, NRC, etc.)
- Signing requests
- Test email backend
- Themes
- Worldline webhook configurations (payments)
- Yivi attribute groups

## Form Builder (React-based)

### Form-level Configuration Tabs
The form builder has 10 configuration tabs:

1. **Form** (Formulier) - Basic metadata: name, slug, internal name, active/inactive, maintenance mode
2. **Steps & fields** (Stappen en velden) - The core form builder with drag-and-drop Form.io components
3. **Confirmation** (Bevestiging) - TinyMCE rich text editor for confirmation page content with template variables
4. **Registration** (Registratie) - Backend registration configuration (multiple backends supported)
5. **Submission** - Processing settings (submission_allowed, suspended submissions, cosigning)
6. **Literals** (Teksten) - Customizable button labels and text snippets
7. **Product & payment** (Product en betaling) - Product linking and payment configuration
8. **Data removal** (Gegevensverwijdering) - GDPR data retention settings with configurable removal periods
9. **Logic** (Logica) - Conditional logic rules (simple/advanced/DMN)
10. **Variables** (Variabelen) - Form variables management (user-defined + static variables)
11. **Advanced configuration** - Statement of truth, ask privacy consent, send confirmation email, show progress indicator, etc.

### Form.io Component Categories
The drag-and-drop form builder organizes components into categories:

1. **Basisvelden** (Basic fields): Tekstveld, Tekstvlak, E-mail, Getal, Selectielijst, Selectievakjes, Radioknoppen, Postcode, Datum, Wachtwoord
2. **Speciale velden** (Special fields): IBAN, Ondertekening (signature), Licentieplaatwaarde (license plate), BSN, KvK-nummer, Kaart (map), Cosign V2, AddressNL
3. **Opmaak** (Layout): Vrije tekst (free text/HTML), Kolommen, Veldset
4. **Bestandsupload** (File upload): Bestandsupload component
5. **Herbruikbare formulierdefinities** (Reusable form definitions): Import shared form definitions

### Component Configuration (per field)
Each component has multiple configuration tabs:
- **Basic**: Label, description, placeholder, default value, key
- **Validation**: Required, min/max length, regex pattern, custom error messages
- **Registration**: Map to registration backend fields
- **Prefill**: Link to prefill data sources (BRP, KVK, Objects API, etc.)

### Registration Backends
Available registration backends (shown in dropdown):
- **ZGW API's** - Dutch government API suite (Zaken, Documenten, Catalogi)
- **Objects API** - Generic objects storage
- **StUF-ZDS** - Dutch government legacy standard
- **Email registration** - Simple email-based registration
- **Microsoft Graph** - OneDrive/SharePoint document storage
- **Camunda** - BPM process engine
- **Generic JSON** - Generic webhook-style registration

### Logic Rule Types
- **Simple** - Basic if/then rules
- **Advanced** (JSON Logic) - Full JSON Logic expressions
- **DMN** - Decision Model and Notation (via Camunda)

### Variables System
Two types of variables:
1. **User-defined variables** - Created by the form designer, linked to form components
2. **Static variables** - System-provided variables (e.g., `now`, `environment`, `auth`, `auth_bsn`, `auth_kvk`, `form_name`, `form_id`)

## Plugin System (from Configuration Overview)

### Address Lookup Plugins
- Kadaster API: BAG
- Kadaster API: locatieserver (working out of box)

### Validator Plugins
- KVK numbers validation
- BRK - Zakelijk gerechtigd validation

### Appointment Plugins
- JCC
- JCC Rest
- Qmatic

### Prefill Plugins
- Communication preferences (customer interactions API)
- Family members (BRP)
- eIDAS (citizen)
- eIDAS (company)
- KvK Company by KvK number
- StUF-BG
- Haal Centraal: BRP Personen Bevragen
- Suwinet
- Objects API
- Yivi

### Payment Plugins
- Worldline merchant (Ingenico/Ogone)

### DMN Plugins
- Camunda DMN engine

### Anti-virus
- ClamAV integration

## Frontend SDK

### Architecture
- The form frontend is rendered by a JavaScript SDK (`sdk-wrapper.mjs`)
- The SDK reads `data-base-url` from the HTML to determine the API endpoint
- Django generates the HTML with the base URL derived from the `X-Forwarded-Host` / `Host` headers
- The SDK uses Form.io under the hood for form rendering
- Cookie consent banner is built-in (GDPR compliance)

### Form Flow
1. **Start page** (Startpagina) - Shows form title with "Begin form" button
2. **Form steps** - Each step shows form fields with progress sidebar
3. **Overview** (Overzicht) - Summary of all entered data before submission
4. **Confirmation** - Post-submission confirmation page

### Accessibility
- Skip-to-content link ("Direct naar de inhoud")
- Back-to-top navigation
- Print page functionality
- Cookie management
- Privacy policy link
- API documentation link

## General Configuration Highlights

The General Configuration page is extremely extensive with sections for:
- Email security configuration (allowed email domain names)
- Submission settings
- Form display options
- Organization settings (name, KvK, etc.)
- Privacy and data handling
- Button text customization
- Feature flags/toggles
- Map/GIS configuration
- Statement of truth settings
- Cosigning configuration

## Key Observations for Competitive Analysis

### Strengths
1. **Very mature form builder** - Comprehensive Form.io-based editor with 20+ component types
2. **Deep Dutch government integration** - Built-in support for DigiD, eHerkenning, BSN, KvK, BRP, Suwinet, ZGW APIs, StUF
3. **Extensive plugin architecture** - Pluggable registration backends, prefill sources, validators, payment providers, appointment systems
4. **Multi-step forms** with progress tracking and conditional logic (including DMN)
5. **GDPR compliance built-in** - Data removal schedules, cookie consent, privacy settings
6. **Theming support** - Customizable themes per form or global
7. **Multi-domain support** - Can serve forms on multiple domains
8. **Reusable form definitions** - Share form steps across forms
9. **Rich confirmation templates** - TinyMCE editor with template variables
10. **API-first design** - Full REST API (v2) with OpenAPI documentation

### Weaknesses / Areas of note
1. **MFA enforcement is aggressive** - No opt-out, requires TOTP setup even for development
2. **Heavy Docker stack** - 7 containers minimum (db, redis, smtp, web, nginx, celery, celery-beat)
3. **Django admin UI** - The admin interface is functional but uses standard Django admin styling, not a modern SPA
4. **OpenTelemetry dependency** - Causes noisy logs when no collector is available
5. **Complex configuration** - Over 30 configuration pages; steep learning curve
6. **Form.io dependency** - Tied to Form.io component library and rendering engine
7. **Limited to form workflows** - Not a general-purpose case management system; focused specifically on form submission and registration

### Comparison to Procest
- Open Formulieren is a **form builder and submission engine**, not a case management system
- It could serve as the **intake frontend** for a case management system like Procest
- The ZGW API integration (Zaken/Documenten) is the bridge between form submission and case handling
- Procest would handle the **post-submission workflow** (case tracking, task assignment, status updates)
- The two are complementary rather than competitive

## Technical Details

- **Version**: latest (Git SHA: 84933e2629d9f84e4b5831d81cb4c749818ce059)
- **Python**: 3.12
- **Database**: PostgreSQL 15
- **Cache/Broker**: Redis 8
- **Web server**: Nginx + uWSGI
- **Task queue**: Celery with Redis backend
- **Frontend**: React (admin form builder) + Form.io SDK (public form renderer)
- **Authentication**: Django sessions + TOTP MFA (django-otp + maykin-2fa)
- **API**: Django REST Framework with OpenAPI/Swagger docs at `/api/v2/docs/`
