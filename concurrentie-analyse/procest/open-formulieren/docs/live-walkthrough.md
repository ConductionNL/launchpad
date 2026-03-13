# Open Formulieren - Live Browser Walkthrough (2026-03-13)

## Setup

- **Version:** 3.5.0 (latest, Docker image `openformulieren/open-forms:latest`)
- **Git SHA:** 628ae31ad629dcea45c1d400a9c3b121645aadd0
- **Stack:** PostgreSQL 15 + Redis 8 + Celery + nginx + ClamAV + SMTP
- **Port mapping:** nginx on 9007, Django on 8000
- **API version:** Open Forms API v3.5.0

## Admin Navigation Structure

Top-level menu items:
1. **Dashboard** — Overview of all admin modules with recent actions
2. **Accounts** — Groups, Static devices, TOTP devices, Tokens, User preferences, Users
3. **Forms** — Categories, Form submission statistics, Forms
4. **Submissions** — Submission list with filters
5. **Appointments** — Appointment configuration (JCC, JCC Rest, Qmatic plugins)
6. **Products** — Product catalog for payment linkage
7. **Configuration** — 30+ config pages (see below)
8. **Logs** — Outgoing request logs, Timeline log entries
9. **Useful links** — API documentation, GitHub
10. **Miscellaneous** — Domains, Flag states, Services, etc.

## Form Builder (Admin)

### Form-Level Tabs (11 tabs)

| Tab | Purpose | Key Elements |
|-----|---------|--------------|
| **Form** | Basic info, features, auth, availability | Name (NL/EN), Explanation (TinyMCE), Slug, Category, Features (Appointments, Translation, Suspension), Authentication, Presentation/appearance, Availability, Submission settings, Experimental features |
| **Steps and fields** | Multi-step form builder (form.io based) | Step list with reorder/delete, Form definition (reusable or new), Drag-and-drop field builder |
| **Confirmation** | Post-submission page + email | Page content (TinyMCE, templated variables), Display main website link, Include in PDF, Email subject/content, Cosign subject/content |
| **Registration** | Backend for storing submissions | Pluggable backends: ZGW API's, Email, StUF-ZDS, Objects API, MS Graph, Camunda, Generic JSON |
| **Submission** | Submission limits | Maximum allowed submissions, Reset counter |
| **Literals** | Button/navigation text overrides | Begin text, Previous text, Change text, Confirm text (NL/EN) |
| **Product & payment** | Payment integration | Product selector, Payment backend (Ogone legacy, Worldline), Pricing logic (fixed or variable) |
| **Data removal** | GDPR data retention | Removal limits and methods per status: Successful, Incomplete, Errored, All (days + delete vs. anonymize) |
| **Logic** | Conditional rules engine | Ordered rule list, manual link to docs |
| **Variables** | Form variable management | Component variables, User-defined variables, 24 static variables, Registration mapping |
| **Advanced configuration** | Extra options | Currently empty for basic forms |

### Field Component Library (Form.io based)

**Formuliervelden (Form fields):**
- Tekstveld (Text field), E-mail, Datum (Date), Datum & tijd (Date & time), Tijd (Time)
- Telefoonnummer (Phone), Postcode, Bestandsupload (File upload)
- Tekstvlak (Textarea), Getal (Number), Selectievakje (Checkbox)
- Selectievakjes (Checkboxes), Keuzelijst (Select/Dropdown), Bedrag (Currency), Radio

**Speciale velden (Special fields):** (collapsed section)
**Opmaak (Layout):** (collapsed section)
**Voorgedefinieerd (Predefined):** (collapsed section)
**Verouderd (Deprecated):** (collapsed section)

### Field Editor Dialog

Each field component has a modal editor with tabs:
- **Basic** — Label, Property Name, Description, Tooltip, Display options (Show in summary/email/PDF), Multiple values, Hidden, Clear on hide, Is sensitive data, Default value, Autocomplete, Read only, Placeholder, Show character counter
- **Advanced** — Extended configuration options
- **Validation** — Required, Plugin(s), Maximum length, Regex pattern, Custom error messages
- **Registration** — Registration attribute mapping
- **Prefill** — Plugin, Plugin attribute, Identifier role (Main/...)
- **Location** — Geolocation settings
- **Translations** — NL/EN translations for labels

Live preview is shown in the dialog (Form view or JSON view).

### Step Settings

Per-step configuration:
- Step name / Internal step name / Step slug (NL/EN)
- Previous text / Save text / Next text overrides
- Is applicable? / Login required? / Is reusable?

## Static Variables (24)

| Variable | Key | Type |
|----------|-----|------|
| Now | `now` | datetime |
| Today | `today` | date |
| Current year | `current_year` | int |
| Environment | `environment` | string |
| Form name | `form_name` | string |
| Form ID | `form_id` | string |
| Internal ID | `submission_id` | string |
| Language code | `language_code` | string |
| Authentication | `auth` | object |
| Authentication type | `auth_type` | string |
| Authentication BSN | `auth_bsn` | string |
| Authentication KvK | `auth_kvk` | string |
| Authentication pseudo | `auth_pseudo` | string |
| Auth additional claims | `auth_additional_claims` | object |
| Auth context data | `auth_context` | object |
| Auth context: source | `auth_context_source` | string |
| Auth context: LoA | `auth_context_loa` | string |
| ...plus 7 more auth context fields | | string |

## Registration Backends

Available options from the dropdown:
1. **ZGW API's** — Creates Zaken in Open Zaak
2. **Email registration** — Sends submission data via email
3. **StUF-ZDS** — Legacy SOAP integration
4. **Objects API registration** — Stores in Objecten API with JSON template
5. **Microsoft Graph (OneDrive/SharePoint)** — Document storage
6. **Camunda** — Process engine integration
7. **Generic JSON registration** — Webhook/generic HTTP POST

Each backend has: Name, Configure options button, Form JSON schema generator.

## Configuration Pages (30+)

- Analytics tools configuration
- Application groups
- Appointment configuration (JCC, JCC Rest, Qmatic)
- Blacklisted emails
- Camunda configuration
- Certificates
- Configuration overview
- Cookie Groups / Cookies
- CSP settings
- DigiD/eHerkenning certificates
- DigiD configuration
- Domains
- eHerkenning/eIDAS configuration
- Flag states
- **General configuration** (massive page: organization info, form defaults, confirmation templates, submission removal, admin notifications, OIDC settings, submission reference, etc.)
- Global prefill configuration
- Map tile layers / WMS layers
- NLX configuration
- OIDC Providers / OIDC clients
- Outgoing request log configuration
- SOAP services
- Service fetch configurations
- Services (ZGW consumers)
- Signing requests
- Test email backend
- **Themes** (NL Design System support)
- Worldline webhook configurations
- Yivi attribute groups

## Theme Editor

Supports NL Design System integration:
- Organization name, Main website link, Favicon
- Theme logo (150x75px, SVG allowed), Email logo
- Theme CSS class name
- Theme stylesheet URL (e.g., `@utrecht/design-tokens`)
- Theme stylesheet file upload
- **Design token values** — JSON editor for NL Design System tokens (border radii, colors, etc.)

## Submissions Management

List view with filters:
- By type (All / Submissions)
- By registration time (All / Past 24 hours)
- By registration backend status (Pending / In progress / Success / Failed)
- By needs on_completion retry
- By form

Actions: Search, Add submission manually.

## Public Form View (SDK)

The public-facing form uses the `@open-formulieren/sdk` JavaScript package:
- Cookie consent banner (NL: "We gebruiken cookies...")
- Form rendering via form.io engine
- Footer: "Pagina afdrukken", "Privacybeleid", "Beheer cookies", "API documentatie"
- SDK fetches form definition via REST API: `GET /api/v2/forms/{slug}`

Note: The SDK requires the API to be accessible through the same host/port as the page (or properly proxied). In our Docker setup on port 9007, the SDK tried to reach `http://localhost/api/v2/...` (port 80) which failed.

## MFA/Security

- Mandatory MFA setup (TOTP) on first login
- Environment banner (docker-compose label: blue banner)
- Session timeout display (60 minutes)
- Dark/light theme toggle in admin

## Source Code Architecture

```
src/openforms/
  authentication/contrib/  — DigiD, eHerkenning, OIDC, Yivi, demo, mock
  prefill/contrib/         — BRP (Haal Centraal + StUF-BG), KvK, eIDAS, Suwinet, Objects API, family members, Yivi
  registrations/contrib/   — ZGW APIs, Objects API, StUF-ZDS, Email, MS Graph, Camunda, Generic JSON
  payments/contrib/        — Ogone (legacy), Worldline
  formio/                  — Form.io component handling
  forms/models/            — Form, FormStep, FormDefinition, FormVariable, FormVersion, Logic, Category
  submissions/             — Submission processing
  appointments/            — JCC, JCC Rest, Qmatic
```

## Key Observations for Procest Comparison

1. **Form builder is form.io-based** — Drag-and-drop with live preview, extensive field types. Procest would need something comparable for intake forms.

2. **Plugin architecture is the core differentiator** — Every integration (auth, prefill, registration, payment) is a pluggable backend. This makes it highly extensible.

3. **Django admin as the management interface** — The entire admin is built on Django's admin framework with custom React widgets. Not a modern SPA.

4. **NL Design System integration is first-class** — Theme editor with design token JSON, stylesheet URL/upload, CSS class names.

5. **24 static variables** — Rich context available during form filling (auth data, form metadata, timestamps).

6. **No case management** — After registration, the submission is "done" from Open Forms' perspective. No tracking, tasks, or workflow.

7. **Complementary positioning confirmed** — Open Forms handles citizen intake; Procest handles what happens after. The ZGW APIs are the integration seam.

## Screenshots Index

| # | File | Description |
|---|------|-------------|
| 01 | login-page.png | Admin login page |
| 02 | mfa-setup.png | MFA setup wizard |
| 03 | admin-dashboard.png | Full admin dashboard overview |
| 04 | forms-list.png | Empty forms list with filters |
| 05 | add-form-page.png | Form creation page with all tabs |
| 06 | forms-list-with-forms.png | Forms list showing created forms |
| 07 | steps-and-fields-empty.png | Steps and fields tab (empty) |
| 08 | add-step-options.png | Add step: select/create definition |
| 09 | form-builder-fields.png | Full form builder with field palette |
| 10 | text-field-editor-basic.png | Text field component editor (Basic tab) |
| 11 | text-field-validation.png | Text field validation settings |
| 12 | text-field-registration.png | Text field registration mapping |
| 13 | text-field-prefill.png | Text field prefill configuration |
| 14 | form-with-naam-field.png | Form with Naam field added |
| 15 | confirmation-tab.png | Confirmation page + email settings |
| 16 | registration-tab.png | Registration backend (empty) |
| 17 | registration-backends.png | Registration backend dropdown options |
| 18 | objects-api-registration.png | Objects API registration selected |
| 19 | submission-tab.png | Submission limits |
| 20 | literals-tab.png | Button text overrides |
| 21 | product-payment-tab.png | Product & payment settings |
| 22 | data-removal-tab.png | GDPR data removal settings |
| 23 | logic-tab.png | Logic rules (empty) |
| 24 | variables-tab.png | Variables overview |
| 25 | static-variables.png | All 24 static variables |
| 26 | advanced-config-tab.png | Advanced configuration |
| 27 | public-form-view.png | Public form SDK rendering |
| 28 | configuration-menu.png | Configuration dropdown menu |
| 29 | general-configuration.png | Full general configuration page |
| 30 | theme-editor.png | Theme/NL Design System editor |
| 31 | submissions-list.png | Submissions list with filters |
| 32 | appointments-config.png | Appointment plugin configuration |
| 33 | api-docs.png | API documentation (Swagger/ReDoc) |
