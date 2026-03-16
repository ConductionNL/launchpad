# KISS (Klantinteractie Servicesysteem) - Complete Browser Walkthrough

## Executive Summary

KISS is a **Customer Contact Center (KCC) application** for Dutch municipalities, built to support Klantcontactmedewerkers (KCMs - customer contact workers) in their daily work: informing and helping citizens and businesses who contact the municipality. It was developed by **ICATT** for **Gemeente Utrecht** and **Dimpact** as a Common Ground component.

**Key finding: KISS could NOT be spun up locally.** The system requires extensive external infrastructure (OIDC identity provider, Elasticsearch + Enterprise Search, multiple ZGW API backends, KVK API, Haal Centraal API) that cannot be mocked without significant effort. See "Why Docker Setup Failed" section below.

Instead, this walkthrough is based on:
- Complete source code analysis (router, views, features, components)
- Official documentation from ReadTheDocs (https://kiss-klantinteractie-servicesysteem.readthedocs.io/nl/latest/)
- Documentation screenshots from the GitHub repos
- Architecture diagrams

---

## Why Docker Setup Failed

### Required External Services (ALL mandatory)

KISS requires ALL of the following services to be configured before it will start:

1. **OIDC Identity Provider** (Azure AD or similar) - `OIDC_CLIENT_SECRET`, `OIDC_CLIENT_ID`, `OIDC_AUTHORITY`
   - Required for ALL authentication; no local auth fallback exists
   - The app redirects to OIDC login immediately; without it, you cannot see any UI

2. **PostgreSQL** - included in docker-compose, BUT needs credentials
   - `POSTGRES_PASSWORD`, `POSTGRES_USER`, `POSTGRES_DB`

3. **Elasticsearch + Enterprise Search** - NOT included in docker-compose
   - `ELASTIC_BASE_URL`, `ELASTIC_USERNAME`, `ELASTIC_PASSWORD`
   - `ENTERPRISE_SEARCH_BASE_URL`, `ENTERPRISE_SEARCH_PUBLIC_API_KEY`, `ENTERPRISE_SEARCH_PRIVATE_API_KEY`
   - Required for ALL search functionality (the core feature)

4. **OpenKlant 2 API** (Klantinteracties) - `KLANTINTERACTIES_BASE_URL`, `KLANTINTERACTIES_TOKEN`
   - Required for customer management, contact moments

5. **Zaaksysteem API** (Open Zaak or e-Suite) - `ZAAKSYSTEEM_BASE_URL`, `ZAAKSYSTEEM_API_KEY`, `ZAAKSYSTEEM_API_CLIENT_ID`
   - Required for case (zaak) management

6. **KVK API** (Kamer van Koophandel) - `KVK_BASE_URL`, `KVK_API_KEY`
   - Required for company lookups

7. **Haal Centraal BRP API** - `HAAL_CENTRAAL_BASE_URL`, `HAAL_CENTRAAL_API_KEY`
   - Required for citizen (persoon) lookups

8. **Objectenregister** (for Interne Taken, Afdelingen, Groepen, VACs)
   - Multiple base URLs and tokens needed

9. **KISS-Elastic-Sync** - separate service (different repo) needed to sync content into Elasticsearch

10. **Email/SMTP** - for feedback functionality

### What Would Be Needed to Run Locally

To get KISS running locally, you would need to:
1. Set up a mock OIDC provider (e.g., Keycloak) - ~2 hours setup
2. Set up Elasticsearch + Enterprise Search cluster - ~1 hour
3. Set up Open Zaak + Open Klant instances - ~2 hours each
4. Set up an Objectenregister - ~1 hour
5. Mock KVK and Haal Centraal APIs - ~2 hours
6. Run KISS-Elastic-Sync to populate search index - ~30 min
7. Configure all ~50+ environment variables correctly

**Total estimated effort: 10+ hours** for a complete local environment.

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Frontend | **Vue 3** + TypeScript + Vite |
| BFF (Backend-for-Frontend) | **ASP.NET Core 8** (C#) |
| Database | PostgreSQL 15.2 |
| Search | Elasticsearch + Enterprise Search (App Search) |
| Auth | OIDC (Azure AD typically) |
| Design System | **NL Design System** (Utrecht component library) |
| Styling | SCSS with CSS custom properties |
| Deployment | Docker, Kubernetes (Helm charts) |
| CI/CD | GitHub Actions |
| Documentation | ReadTheDocs (Sphinx) |
| Testing | Vitest (frontend), xUnit (.NET), Playwright (E2E) |

---

## Application Architecture

### Multi-Service Architecture

```
[Browser] --> [KISS BFF (ASP.NET)] --> [PostgreSQL]
                    |
                    +--> [OIDC Provider (Azure AD)]
                    +--> [Elasticsearch / Enterprise Search]
                    +--> [OpenKlant API (Klantinteracties)]
                    +--> [Zaaksysteem API (Open Zaak / e-Suite)]
                    +--> [KVK API]
                    +--> [Haal Centraal BRP API]
                    +--> [Objectenregister (Interne Taken, Afdelingen, Groepen)]
                    +--> [SDG API (Product catalogus)]

[KISS-Elastic-Sync] --> [Elasticsearch]
    (separate service, syncs content sources hourly)
```

The BFF acts as a reverse proxy and authentication gateway. The Vue frontend calls `/api/*` endpoints on the BFF, which then proxies to the appropriate backend services. The BFF handles:
- OIDC authentication flow
- JWT token management for backend services
- PostgreSQL storage for KISS-specific data (contactmomentdetails, beheer items)
- Proxying API calls to external services

### Two Architecture Variants

1. **Open Zaak + Open Klant** - using VNG open-source ZGW components
2. **e-Suite** - using Atos e-Suite proprietary system

See screenshots: `25-architectuur-openzaak-openklant.png`, `26-architectuur-esuite.png`, `27-architectuur-archimate.jpg`

---

## User Roles and Permissions

KISS has three distinct user roles:

| Role | Dutch Name | Permissions |
|------|-----------|------------|
| **KCM** (Klantcontactmedewerker) | Klantcontactmedewerker | Start/manage contact moments, search, view persons/companies/cases, create contactverzoeken, view links |
| **Redacteur** (Editor) | Redacteur | Manage news/work instructions, manage links, manage VACs |
| **Beheerder** (Admin) | Beheerder | Manage skills, conversation results, contact request forms, channels |

Permissions are configured per-beheer-tab:
- `berichtenbeheer` - News and work instructions management
- `skillsbeheer` - Skills management
- `linksbeheer` - Links management
- `gespreksresultatenbeheer` - Conversation results management
- `contactformulierenbeheer` - Contact request form management
- `kanalenbeheer` - Channel management
- `vacsbeheer` - VAC management
- `linksread` - View links page

---

## Complete Page-by-Page Walkthrough

### 1. Home / Start Screen (`/`)

**Purpose:** Landing page for KCMs showing news and work instructions.

**Layout:**
- Header with KISS logo, user info, and navigation
- Sidebar (left) with contact moment controls
- Main content area with news and work instructions

**Features:**
- **Search bar** for searching news/work instructions with type filter dropdown ("Alle", "Nieuws", "Werkinstructie")
- **Skills filter** (multi-select) to filter messages by category (e.g., "burgerzaken", "werk en inkomen")
- **News section** - paginated list of news items
- **Work instructions section** - paginated list of work instructions
- Both sections show items filtered by selected skills
- Important items are highlighted and shown at top

**Form Fields:**
- Type filter: dropdown (Alle / Nieuws / Werkinstructie)
- Search input: text field with search icon
- Skills filter: multi-select checkbox list

**Screenshot references:** `05-nieuws-beheer-overzicht.png` (shows the beheer side)

---

### 2. Contact Moment Sidebar (always visible during active contact)

**Purpose:** The core workflow element - a sidebar that appears when a KCM starts handling a customer contact.

**Sidebar Components:**

#### 2a. Contact Moment Starter
- **"Nieuw contactmoment" button** - starts a new contact moment
- Shows list of active contact moments (can switch between them)

#### 2b. Contact Moment Info
- Shows current contact moment details
- **Vragen menu** - ability to add multiple questions/topics to one contact moment

#### 2c. Notitie Tabs (two tabs)
**Tab 1: "Notitieblok" (Notepad)**
- Free-text textarea for notes during the conversation
- Placeholder: "Schrijf een notitie..."

**Tab 2: "Contactverzoek" (Contact Request)**
- Full contact request form (see Contactverzoek Form below)
- Auto-syncs note content to internal description field

#### 2d. Contact Moment Controls
- **Cancel button** - cancel current contact moment
- **Finish button** - complete the contact moment (goes to Afhandeling view)

**Screenshot reference:** `21-contactmoment-sidebar.png`

---

### 3. Contactverzoek (Contact Request) Form

**Purpose:** Create a contact request to be handled by a department, group, or specific employee.

**Fixed Fields:**
1. **Voor wie** (For whom) - radio: Afdeling (department) / Groep (group) / Medewerker (employee)
2. **Afdeling/Groep/Medewerker selector** - dropdown/search depending on selection above
3. **Omschrijving** (Description) - textarea for internal notes (the citizen never sees this)
4. **Contact details of the person to call back:**
   - Naam (Name) - text input
   - Telefoonnummer (Phone) - text input
   - E-mailadres (Email) - text input

**Dynamic Fields (from Contactverzoek Formulieren):**
When a department/group is selected that has a configured form, additional fields appear:
- **Open vraag kort** - single-line text input
- **Open vraag lang** - multi-line textarea
- **Dropdown** - single-select dropdown
- **Checkbox** - multi-select checkboxes

**Screenshot references:** `13-contactverzoek-checkbox.png`, `14-contactverzoek-dropdown.png`, `15-contactverzoek-open-kort.png`, `16-contactverzoek-open-lang.png`

---

### 4. Afhandeling (Completion) View (`/afhandeling`)

**Purpose:** Final step when completing a contact moment. Hidden sidebar, no navigation.

**Form Fields:**
- **Gespreksresultaat** (Conversation result) - dropdown (e.g., "Zelfstandig afgehandeld", "Doorverbonden")
- **Kanaal** (Channel) - dropdown (e.g., "Telefoon", "E-mail", "Contactformulier", "WhatsApp", etc.)
- **Save button** to complete the contact moment

**Behavior:**
- Cannot navigate away without completing or canceling
- Sidebar is hidden (`hideSidebar: true`)
- Route guard: requires active contact moment

**Screenshot reference:** `23-contactmoment-afhandeling.png`

---

### 5. Personen (Persons) Search (`/personen`)

**Purpose:** Search for citizens (natural persons) in the BRP (Basisregistratie Personen).

**Search Options:**
- Search by BSN (Burgerservicenummer)
- Search by name + date of birth
- Search by postal code + house number

**Results:**
- List of matching persons with basic info
- Click to navigate to person detail

**Route guard:** Requires active contact moment.

---

### 6. Persoon Detail (`/personen/:internalKlantId`)

**Purpose:** View detailed information about a citizen, including their cases and contact history.

**Displayed Information:**
- Personal details (from Haal Centraal BRP)
- Address information
- Contact history (contactmomenten)
- Active cases (zaken) linked to this person
- Previous contact requests (contactverzoeken)

**Actions:**
- Link person to current contact moment
- View linked cases
- Navigate to case details

---

### 7. Bedrijven (Companies) Search (`/bedrijven`)

**Purpose:** Search for companies in the KVK (Kamer van Koophandel) register.

**Search Options:**
- Search by KVK number
- Search by company name
- Search by RSIN

**Results:**
- List of matching companies with basic info
- Click to navigate to company detail

**Route guard:** Requires active contact moment.

---

### 8. Bedrijf Detail (`/bedrijven/:internalKlantId`)

**Purpose:** View detailed information about a company.

**Displayed Information:**
- Company name, KVK number, RSIN
- Address information
- Contact history
- Active cases linked to this company

**Actions:**
- Link company to current contact moment
- View linked cases

---

### 9. Zaken (Cases) Search (`/zaken`)

**Purpose:** Search for cases (zaken) in the connected Zaaksysteem.

**Search Options:**
- Search by zaak number (identificatie)
- Filtered by zaaktype

**Results:**
- List of matching cases with status, type, date
- Click to navigate to case detail

**Route guard:** Requires active contact moment.

---

### 10. Zaak Detail (`/zaken/:zaakId`)

**Purpose:** View detailed case information from the Zaaksysteem.

**Displayed Information:**
- Case number (identificatie)
- Case type (zaaktype)
- Status and status history
- Start date, expected end date
- Description (omschrijving)
- Result (resultaat)
- Related documents
- Contact moments linked to this case

**Actions:**
- Link case to current contact moment
- Deep link to the zaaksysteem for full case management

**Screenshot reference:** `20-zaakdetail-mapping.png`

---

### 11. Contactverzoeken (Contact Requests) Search (`/contactverzoeken`)

**Purpose:** Search and browse existing contact requests.

**Search/Filter Options:**
- Filter by status
- Filter by department/group
- Text search

**Results:**
- List of contact requests with assignee, status, date
- Click to view details

**Route guard:** Requires active contact moment.

---

### 12. Links Page (`/links`)

**Purpose:** Quick-access page showing frequently used links for KCMs.

**Layout:**
- Links organized by category
- Each link shows title and opens URL in new tab

**Permission:** Requires `linksread` permission.

---

### 13. Search (integrated in all views)

**Purpose:** Global search across multiple sources using Elasticsearch.

**Search Sources (configured per installation):**
- Municipal website pages
- Knowledge articles (Product format)
- Internal phone book (smoelenboek)
- VACs (Vraag-Antwoord-Combinaties)
- SharePoint pages

**Search Process:**
1. Enterprise Search builds query template via search-explain endpoint
2. Elasticsearch executes the query
3. Results displayed with source indication and relevance ranking

**Search is synced hourly** by the KISS-Elastic-Sync service.

**Screenshot reference:** `22-contactmoment-search.png`

---

## Beheer (Administration) Pages

All beheer pages are under `/beheer` with tab navigation. Access requires appropriate role (Redacteur or Beheerder).

### 14. Nieuws en Werkinstructies Beheer (`/beheer/NieuwsEnWerkinstructies`)

**Purpose:** Manage news items and work instructions shown on the KCM home page.

**List View:**
- Table with title, type, publication date, end date, importance flag
- Edit (arrow) and delete (trash) buttons per row
- "Toevoegen" (Add) button top-right

**Detail Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| Type | Dropdown | "Nieuws" or "Werkinstructie" |
| Titel | Text input | Title of the message |
| Inhoud | Rich text / textarea | Content of the message |
| Belangrijk | Checkbox | Mark as important (shows at top, highlighted) |
| Publicatiedatum | Date+time picker | When to publish |
| Publicatie-einddatum | Date+time picker | When to auto-hide (default: 1 year) |
| Skills | Multi-select | Link to skills for filtering |

**CRUD Operations:**
- Create: "Toevoegen" button -> fill form -> "Opslaan"
- Read: List view with all items
- Update: Click arrow -> edit form -> "Opslaan"
- Delete: Click trash icon -> confirmation popup -> "OK"

**Screenshot references:** `05-nieuws-beheer-overzicht.png`, `06-nieuws-beheer-detail.png`

---

### 15. Skills Beheer (`/beheer/Skills`)

**Purpose:** Manage skill categories used to filter news/work instructions.

**List View:**
- Simple list of skill names
- Click name to edit, trash icon to delete

**Detail Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| Naam | Text input | Name of the skill (e.g., "Burgerzaken", "Werk en inkomen") |

**CRUD:** Same pattern as news (add, edit via name click, delete via trash + confirmation).

**Screenshot references:** `07-skills-beheer-overzicht.png`, `08-skills-beheer-detail.png`

---

### 16. Links Beheer (`/beheer/Links`)

**Purpose:** Manage quick-access links shown on the Links page.

**List View:**
- Table with title, URL, category
- Click name to edit, trash icon to delete

**Detail Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| Titel | Text input | Display name of the link |
| URL | URL input | Must start with https:// |
| Categorie | Autocomplete text | Category name (existing or new) |

**Autocomplete behavior:** When typing a category name, existing categories are suggested. Typing a new name creates a new category.

**Screenshot references:** `09-links-beheer-overzicht.png`, `10-links-beheer-detail.png`

---

### 17. Gespreksresultaten Beheer (`/beheer/gespreksresultaten`)

**Purpose:** Manage conversation outcome options available at contact moment completion.

**List View:**
- Simple list of result names
- Click name to edit, trash icon to delete

**Detail Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| Naam | Text input | Result name (e.g., "Zelfstandig afgehandeld", "Doorverbonden") |

**Screenshot references:** `11-gespreksresultaten-beheer.png`, `12-gespreksresultaten-detail.png`

---

### 18. Formulieren Contactverzoek Afdelingen (`/beheer/formulieren-contactverzoek-afdeling`)

**Purpose:** Create custom question forms for contact requests by department.

**List View:**
- Table with form title and linked department
- Edit (arrow) and delete (trash) buttons per row
- "Toevoegen" button top-right

**Detail Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| Titel | Text input | Form title (describes the topic, e.g., "WMO", "Parkeerbon") |
| Afdeling | Dropdown | Department this form is for |
| Vragen | Dynamic list | Questions to add (see question types below) |

**Question Types:**
| Type | Description | Additional Config |
|------|------------|-------------------|
| Open vraag kort | Single-line text input | Question text only |
| Open vraag lang | Multi-line textarea | Question text only |
| Dropdown | Single-select list | Question text + min. 2 answer options |
| Checkbox | Multi-select list | Question text + min. 2 answer options |

**Adding Questions:**
- Click "Vraag toevoegen" dropdown
- Select question type
- Fill in question text
- For dropdown/checkbox: add answer options with "Antwoordoptie Toevoegen"
- Reorder by dragging
- Delete with trash icon (appears on hover)

**Screenshot references:** `02-contactverzoek-beheer-overzicht.png`, `03-contactverzoek-beheer-toevoegen.png`, `04-contactverzoek-beheer-vragen.png`, `17-contactverzoek-beheer-opslaan.png`

---

### 19. Formulieren Contactverzoek Groepen (`/beheer/formulieren-contactverzoek-groep`)

**Purpose:** Same as above but for groups instead of departments. Identical form structure.

---

### 20. Kanalen Beheer (`/beheer/kanalen`)

**Purpose:** Manage contact channels available at contact moment completion.

**Default Channels (if none configured):**
- Telefoon
- E-mail
- Contactformulier
- Twitter
- Facebook
- LinkedIn
- Live chat
- Instagram
- WhatsApp

**Detail Form Fields:**
| Field | Type | Description |
|-------|------|-------------|
| Naam | Text input | Channel name |

**Note:** When using KISS with a backend register that also has channels, the spelling must match exactly (case-sensitive, including spaces and hyphens).

---

### 21. VACs Beheer (`/beheer/vacs`)

**Purpose:** Manage Vraag-Antwoord-Combinaties (Question-Answer Combinations) stored in the connected Objectenregister.

**Availability:** Only shown if `USE_VACS` environment variable is set to `true`.

**List View:**
- Full list of all VACs
- Use Ctrl+F to search in the browser
- Same CRUD pattern as other beheer pages

---

## API Endpoints

### Management Information API

KISS exposes a dedicated API for extracting management information:

**Endpoint:** `GET /api/contactmomentendetails`

**Authentication:** JWT Bearer Token (HS256, signed with `MANAGEMENTINFORMATIE_API_KEY`)

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `from` | string (ISO 8601) | Yes | - | Start date |
| `to` | string (ISO 8601) | Yes | - | End date |
| `pageSize` | int | No | 5000 | Results per page (max 5000) |
| `page` | int | No | 1 | Page number |

**Data stored per contact moment:**
- Gespreksresultaat (conversation result)
- Kanaal (channel)
- Duration
- KCM identifier
- Linked customer, case references

---

## Key Business Logic Observations

### 1. Contact Moment is Central
Everything revolves around the "contactmoment" concept. Most pages (personen, bedrijven, zaken, contactverzoeken) are only accessible DURING an active contact moment. The sidebar persists across all these pages.

### 2. Multi-Question Support
A single contact moment can have multiple "vragen" (questions/topics). Each question has its own notes and optional contact request.

### 3. Contact Moment Switching
KCMs can have multiple active contact moments and switch between them (e.g., if a customer calls back while handling another call).

### 4. NL Design System Compliance
The entire UI is built with NL Design System components (Utrecht variant), ensuring government accessibility standards (WCAG AA).

### 5. Multi-Register Support
KISS supports connecting to multiple "registers" simultaneously:
- OpenKlant 2 (new standard)
- OpenKlant 1 (legacy, via contactmomenten API)
- Each register has its own configuration for klantinteractie, zaaksysteem, and interne taken

### 6. Kennisbank Role
There is a special "kennisbank" (knowledge base) user type that sees a different home view (not the standard KCM view).

### 7. Feedback via Email
KISS includes a feedback mechanism that sends emails (`FEEDBACK_EMAIL_FROM`, `FEEDBACK_EMAIL_TO`).

---

## Comparison Notes for Pipelinq

### Relevant Features for Pipelinq Competition Analysis:

1. **Contact moment workflow** - KISS's core strength: structured conversation handling with notes, customer linking, and case association
2. **Dynamic form builder** - The contactverzoek formulieren system allows admins to create custom question forms per department
3. **Multi-source search** - Elasticsearch-powered search across website, knowledge articles, VACs, and SharePoint
4. **Role-based access** - Three-tier role system (KCM, Redacteur, Beheerder)
5. **Management information API** - Dedicated API for extracting KPI data
6. **Multi-register support** - Can connect to multiple backend systems simultaneously
7. **NL Design System** - Government theming compliance built-in

### Architecture Differences from Pipelinq:
- KISS uses ASP.NET Core BFF (not PHP/Nextcloud)
- KISS requires extensive external infrastructure
- KISS is Kubernetes-native (Helm charts)
- KISS delegates all data storage to external APIs (OpenKlant, Open Zaak, Objectenregister)
- KISS's own DB only stores management info and beheer configuration

---

## Screenshots Index

| # | File | Description |
|---|------|-------------|
| 01 | `01-readthedocs-home.png` | KISS documentation homepage on ReadTheDocs |
| 02 | `02-contactverzoek-beheer-overzicht.png` | Contact request form management - list view |
| 03 | `03-contactverzoek-beheer-toevoegen.png` | Contact request form - create new |
| 04 | `04-contactverzoek-beheer-vragen.png` | Contact request form - with questions added |
| 05 | `05-nieuws-beheer-overzicht.png` | News and work instructions management - list |
| 06 | `06-nieuws-beheer-detail.png` | News item - create/edit form |
| 07 | `07-skills-beheer-overzicht.png` | Skills management - list view |
| 08 | `08-skills-beheer-detail.png` | Skill - create/edit form |
| 09 | `09-links-beheer-overzicht.png` | Links management - list view |
| 10 | `10-links-beheer-detail.png` | Link - create/edit form |
| 11 | `11-gespreksresultaten-beheer.png` | Conversation results management - list |
| 12 | `12-gespreksresultaten-detail.png` | Conversation result - create/edit form |
| 13 | `13-contactverzoek-checkbox.png` | Contact request form field: checkbox type |
| 14 | `14-contactverzoek-dropdown.png` | Contact request form field: dropdown type |
| 15 | `15-contactverzoek-open-kort.png` | Contact request form field: short text |
| 16 | `16-contactverzoek-open-lang.png` | Contact request form field: long text |
| 17 | `17-contactverzoek-beheer-opslaan.png` | Contact request form - after save |
| 18 | `18-contactverzoek-beheer-wijzigen.png` | Contact request form management - edit mode |
| 19 | `19-contactverzoek-beheer-verwijderen.png` | Contact request form - delete confirmation |
| 20 | `20-zaakdetail-mapping.png` | Case detail view - field mapping diagram |
| 21 | `21-contactmoment-sidebar.png` | Sidebar during active contact moment |
| 22 | `22-contactmoment-search.png` | Search results during contact moment |
| 23 | `23-contactmoment-afhandeling.png` | Contact moment completion (afhandeling) view |
| 24 | `24-readthedocs-handleiding-beheer.png` | ReadTheDocs beheer manual page |
| 25 | `25-architectuur-openzaak-openklant.png` | Architecture: Open Zaak + Open Klant variant |
| 26 | `26-architectuur-esuite.png` | Architecture: e-Suite variant |
| 27 | `27-architectuur-archimate.jpg` | Architecture: ArchiMate overview |

---

## Source Files Analyzed

### Frontend Routes (src/router/index.ts)
- `/` - HomeView (start screen with news/work instructions)
- `/afhandeling` - AfhandelingView (contact moment completion)
- `/contactverzoeken` - ContactenverzoekenView (contact request search)
- `/personen` - PersonenView (person search)
- `/personen/:internalKlantId` - PersoonDetailView
- `/bedrijven` - BedrijvenView (company search)
- `/bedrijven/:internalKlantId` - BedrijfDetailView
- `/zaken` - ZakenView (case search)
- `/zaken/:zaakId` - ZaakDetailView
- `/links` - LinksView (quick links)
- `/beheer` - BeheerLayout (admin parent)
  - `/beheer/NieuwsEnWerkinstructies` - News/work instructions list
  - `/beheer/NieuwsEnWerkinstructie/:id?` - News/work instruction edit
  - `/beheer/Skills` - Skills list
  - `/beheer/Skill/:id?` - Skill edit
  - `/beheer/Links` - Links list
  - `/beheer/Link/:id?` - Link edit
  - `/beheer/gespreksresultaten` - Conversation results list
  - `/beheer/gespreksresultaat/:id?` - Conversation result edit
  - `/beheer/formulieren-contactverzoek-afdeling` - Department forms list
  - `/beheer/formulier-contactverzoek-afdeling/:id?` - Department form edit
  - `/beheer/formulieren-contactverzoek-groep` - Group forms list
  - `/beheer/formulier-contactverzoek-groep/:id?` - Group form edit
  - `/beheer/kanalen` - Channels list
  - `/beheer/kanaal/:id?` - Channel edit
  - `/beheer/vacs` - VACs list
  - `/beheer/vac/:uuid?` - VAC edit

### Feature Modules (src/features/)
- `Kanalen/` - Channel management
- `bedrijf/` - Company search and detail
- `beheer/` - Admin/management features
- `contact/` - Contact moment and contact request handling
- `feedback/` - Feedback mechanism
- `klant/` - Customer management
- `links/` - Quick links
- `login/` - OIDC authentication
- `persoon/` - Person search and detail
- `search/` - Global search
- `shared/` - Shared components
- `werkbericht/` - News and work instructions
- `zaaksysteem/` - Case system integration

### GitHub Repositories
- `Klantinteractie-Servicesysteem/KISS-frontend` - Main repo (Vue 3 + ASP.NET BFF)
- `Klantinteractie-Servicesysteem/KISS-Elastic-Sync` - Elasticsearch sync service
- `Klantinteractie-Servicesysteem/.github` - Org profile + shared docs/images
- `Klantinteractie-Servicesysteem/KissBundle` - Symfony bundle (archived)
- `Klantinteractie-Servicesysteem/pdc-component` - PDC/SDG API component
- `Klantinteractie-Servicesysteem/Openpub` - WordPress publishing
- `Klantinteractie-Servicesysteem/pub-plugin` - WordPress PUB plugin
