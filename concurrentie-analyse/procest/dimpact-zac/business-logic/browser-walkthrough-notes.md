# Dimpact ZAC - Browser Walkthrough Notes

**Date:** 2026-03-14
**Source:** https://github.com/infonl/dimpact-zaakafhandelcomponent
**Version analyzed:** 4.4.38 (build main, 13-03-2026 11:23)
**License:** EUPL-1.2+
**Developer:** INFO.nl (previously Atos, taken over July 2023 by Lifely/INFO.nl)
**Client:** Dimpact cooperative (used by Dutch municipalities)

## Docker Setup

**Result: SUCCESS -- running locally on port 9016**

The docker-compose.yaml defines 17 required services (see docker-setup-notes.md for details). Successfully started by:
1. Disabling PABC integration (`FEATURE_FLAG_PABC_INTEGRATION=false`)
2. Remapping ports to avoid conflicts (ZAC: 9016, Keycloak: 8081)
3. Adding `host.docker.internal` extra_hosts for WSL2
4. Manually running OpenZaak database init scripts
5. Updating Keycloak redirect URIs for port 9016

Logged in as `testuser1` / `testuser1` (old IAM mode).

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | Java 21, WildFly application server, Kotlin (build scripts) |
| Frontend | Angular 19+ (TypeScript), Angular Material |
| Build | Gradle (backend), npm/webpack (frontend) |
| Database | PostgreSQL (ZAC data), PostGIS (OpenZaak/OpenKlant) |
| Search | Apache Solr 9.x |
| Auth | Keycloak (OIDC/OAuth2) |
| Authorization | OPA (Open Policy Agent) + PABC |
| Process engine | CMMN and BPMN process definitions |
| Document creation | SmartDocuments integration (Word templates via WebDAV) |
| APIs consumed | ZGW APIs (Open Zaak), BRP (HaalCentraal), BAG (Kadaster), KVK, Open Klant |
| Observability | OpenTelemetry, Grafana, Prometheus, Tempo |
| Container | Docker, Helm charts for Kubernetes |
| i18n | Dutch (primary), English |

---

## Live Walkthrough Screenshots

| # | File | Page / View |
|---|------|-------------|
| 01 | 01-zac-no-permission.png | Initial access without login -- redirected to error page |
| 02 | 02-keycloak-login.png | Keycloak OIDC login screen |
| 03 | 03-zac-server-error-testuser1.png | Server error page (PABC 500 before fix) |
| 04 | 04-zac-dashboard.png | Dashboard (empty, customizable) |
| 05 | 05-cases-menu.png | Cases dropdown menu |
| 06 | 06-cases-queue.png | Cases work queue (Mijn zaken / My cases) |
| 07 | 07-tasks-menu.png | Tasks dropdown menu |
| 08 | 08-tasks-queue.png | Tasks work queue (Mijn taken / My tasks) |
| 09 | 09-inbox-menu.png | Inbox menu |
| 10 | 10-admin-config-check.png | Admin > Configuration check (casetype sync, version info) |
| 11 | 11-admin-group-alerts.png | Admin > Group alert settings |
| 12 | 12-admin-reference-tables.png | Admin > Reference tables (ADVIES, AFZENDER, BRP tables, etc.) |
| 13 | 13-admin-mailtemplates.png | Admin > Mailtemplates (15 templates for alerts, receipts, etc.) |
| 14 | 14-admin-case-handling-params.png | Admin > Case handling parameters (ZAPS) |
| 15 | 15-admin-bpmn-definitions.png | Admin > BPMN process definitions (empty) |
| 16 | 16-admin-form-definitions.png | Admin > Form definitions (empty) |
| 17 | 17-admin-formio-forms.png | Admin > Form.io forms (empty) |
| 18 | 18-create-case-form.png | Create case form (Zaak toevoegen) |
| 19 | 19-user-profile-menu.png | User profile dropdown menu |
| 20 | 20-user-alert-settings.png | User alert settings (signaleringen) |
| 21 | 21-search-results.png | Search: cases, tasks, documents |
| 22 | 22-search-person.png | Search: person (BRP) |
| 23 | 23-search-company.png | Search: company (KvK) |
| 24 | 24-search-bag-object.png | Search: BAG object (address) |

---

## UI Architecture & Navigation

### Top Navigation Bar (Purple/Blue Toolbar)
- **Home button** (house icon) -- returns to Dashboard
- **Zaak toevoegen** (pink/red folder icon) -- create new case
- **Cases** dropdown:
  - Zaken-werkvoorraad (all open cases)
  - Mijn zaken (my cases)
  - Afgehandelde zaken (completed cases)
- **Tasks** dropdown:
  - Taken-werkvoorraad (all open tasks)
  - Mijn taken (my tasks)
- **Inbox** dropdown (coordinator role only):
  - Ontkoppelde documenten (unlinked documents)
  - Inbox documenten (documents needing linking)
  - Inbox productaanvragen (product requests without cases)
- **Search** bar (right side) -- opens side panel with 4 search modes
- **Admin settings** (gear icon) -- admin pages
- **User profile** (avatar icon) -- dropdown with alert settings, sign out, version

---

## Page-by-Page Live Walkthrough

### 1. Authentication Flow
- **Unauthenticated access** redirects to Keycloak OIDC login
- Keycloak presents standard login form with realm "zaakafhandelcomponent"
- After login, redirected back to ZAC dashboard
- Session managed via OIDC tokens
- WebSocket connection established immediately for real-time signaling

### 2. Dashboard (`/`)
- Customizable dashboard with card-based layout
- Toggle "Dashboard aanpassen" to enter edit mode
- Available cards include: My cases, My tasks, Recently assigned cases/tasks/documents, Expiring cases
- Empty by default; user must configure which cards to show
- Version info visible in user menu (4.4.38)

### 3. Cases (`/zaken/werkvoorraad`)
- Work queue view with table layout
- Columns: Case ID, Casetype, Status, Applicant, Group, Handler, Start date, Due date, Days remaining
- Filter and sort capabilities per column
- Pagination with configurable items per page (10/25/50/100)
- Empty in fresh setup (no cases created)

### 4. Tasks (`/taken/werkvoorraad`)
- Similar work queue layout as cases
- Columns: Task name, Status, Case ID, Casetype, Created date, Due date, Group, Handler
- Filter/sort/pagination same as cases
- Empty in fresh setup

### 5. Inbox
- Document inbox for incoming documents
- Three sub-sections: Inbox documenten, Ontkoppelde documenten, Inbox productaanvragen
- Empty in fresh setup

### 6. Create Case Form (`/zaken/create`)
- Structured form with three sections:
  - **Create case:** Casetype (required, searchable dropdown), Applicant (person selector), Start date (required, defaults to today), BAG objects (address lookup)
  - **Case assignment:** Group (required, auto-filled based on casetype), Employee (optional)
  - **Other information:** Communication channel (required dropdown), Confidentiality notice (required dropdown), Description (required, max 80 chars), Explanation (optional, max 1000 chars)
- Create button disabled until required fields filled
- Casetype selection drives which groups/employees are available

### 7. Search (side panel overlay)
Four search modes accessed via icon tabs:
- **Cases/Tasks/Documents:** Free-text search with "Zoeken in" filter (All/Cases/Tasks/Documents), paginated results table
- **Person (BRP):** Search by CSN, date of birth, first names, prefix, geslachtsnaam, zip code, house number, street, municipality code
- **Company (KvK):** Search by CoC number, branch code, RSIN, name, type dropdown, zip code, house number
- **BAG Object:** Search by address/zip code/place of residence (free text, max 255 chars)

### 8. Admin Settings

#### 8a. Group Alert Settings (`/admin/groepen`)
- Select a Keycloak group from dropdown
- Configure alert notifications per group
- Table: Type, Alert, Dashboard card, E-Mail columns
- Empty until a group is selected

#### 8b. Reference Tables (`/admin/referentietabellen`)
- System-managed lookup tables with CRUD
- Pre-loaded tables: ADVIES (5 rows), AFZENDER (0), BRP_DOELBINDING_RAADPLEEG_WAARDE (15), BRP_DOELBINDING_ZOEK_WAARDE (4), BRP_VERWERKINGSREGISTER_WAARDE (1), COMMUNICATIEKANAAL (8), DOMEIN (0), SERVER_ERROR_ERROR_PAGINA_TEKST (0)
- Each row shows: Table name, System (Yes/No), Display name, Row count
- Add button (+) for new tables; eye icon to view/edit

#### 8c. Mailtemplates (`/admin/mailtemplates`)
- 15 pre-configured email templates with filter and sort
- Templates organized by purpose:
  - General e-mail (Algemene e-mail)
  - Receipt process (Ontvangstbevestiging)
  - Alert templates (task assigned, task expired, case document added, case assigned, case expired by due date, case expired by deadline)
  - Task form templates (additional information, external advice with email)
  - Case status templates (closed, admissible, not admissible)
- Columns: Mailtemplate name, Mail for (purpose), Default (checkmark), Edit button
- All 15 templates marked as Default

#### 8d. Case Handling Parameters (`/admin/parameters`)
- Configure per-casetype handling parameters (ZAPS)
- Table columns: Casetype, Purpose, CMMN model, Date created, Valid, Start validity, End validity
- Filterable by casetype dropdown, CMMN model dropdown, valid status, date ranges
- Empty in fresh setup (no zaakafhandelparameters configured beyond the 3 test casetypes)

#### 8e. BPMN Process Definitions (`/admin/processdefinitions`)
- Upload and manage BPMN process definitions (Flowable)
- Table: Name, Version, Key
- Add button (+) for uploading new definitions
- Empty in fresh setup

#### 8f. Form Definitions (`/admin/formulierdefinities`)
- Manage custom form definitions
- Table: System name, Name, Description, Date created, Date changed, # Field definitions
- Add button (+) for creating new form definitions
- Empty in fresh setup

#### 8g. Form.io Forms (`/admin/formioformulieren`)
- Manage Form.io form definitions
- Table: Name, Title
- Add button (+) for adding new Form.io forms
- Empty in fresh setup

#### 8h. Configuration Check (`/admin/check`)
- System health and configuration validation:
  - **Case type catalog synchronization:** Shows last sync date (14 mrt. 2026 13:01), resync button (locked/disabled)
  - **Casetype configuration check:** Lists test casetypes (BPMN test zaaktype 1, BPMN test zaaktype 2, Test zaaktype 1) with error indicators
  - **Referentielijsten & Selectielijst check:** Validates communication channel "E-formulier" (shows as available with warning)
  - **Component information:** Version 4.4.38, Build: main 13-03-2026 11:23, Last commit hash

### 9. User Profile
- Dropdown menu from avatar showing:
  - Display name: "Test User1 Special Characters"
  - Alert settings link
  - Sign out button
  - Version: 4.4.38

#### User Alert Settings (`/signaleringen/settings`)
- Personal notification preferences in table format
- Case alerts (3 types):
  - "A document has been added to my case" -- Dashboard card + E-Mail toggles
  - "A case has been assigned to me" -- Dashboard card + E-Mail toggles
  - "My case is approaching the due- or fatal date" -- Dashboard card + E-Mail toggles
- Task alerts (2 types):
  - "A task has been assigned to me" -- Dashboard card + E-Mail toggles
  - "My task has reached the fatal date" -- E-Mail toggle only (no dashboard card)

---

## Process Flow (CMMN Generic)

Two-phase process:

**Phase 1: Intake**
- Available actions: Ontvangstbevestiging (receipt confirmation)
- Available task: Aanvullende informatie (request additional info)
- Transition: "Intake afronden" -- ontvankelijk (admissible) moves to Phase 2, niet-ontvankelijk (inadmissible) ends case

**Phase 2: In behandeling (Processing)**
- Available tasks: Advies intern, Advies extern, Goedkeuren
- Available action: Besluit vastleggen
- Transition: "Zaak afhandelen" completes the case

**Always available (both phases):**
- Document maken, Document toevoegen, Document verzenden
- E-mail versturen
- Initiator toekennen, Betrokkene toevoegen
- BAG-object toevoegen
- Zaak koppelen

---

## Roles & Permissions (RBAC)

| Role | Description |
|------|-------------|
| Raadpleger | Read-only access to cases, tasks, notes, documents |
| Behandelaar | Full case treatment rights, some restrictions on bulk distribution and final documents |
| Coordinator | Work distribution from werkvoorraad lists, read access to cases/tasks |
| Recordmanager | Read access + additional document rights + reopen completed cases |
| Beheerder | Full administrative access |

New IAM architecture (PABC) supports:
- Functional roles in Keycloak (high-level)
- Application roles mapped via PABC (low-level, per-domain)
- Domain-based authorization (groups of zaaktypes)
- Multi-group membership with merged permissions

---

## System Architecture

ZAC sits in the **interaction layer** of the Common Ground 5-layer model. Key integrations:

| System | Protocol | Purpose |
|--------|----------|---------|
| Open Zaak | ZGW APIs (Zaken, Documenten, Catalogi, Besluiten) | Case & document registry |
| Open Klant | Klantinteracties API | Customer contact moments |
| Objecten API | Objecten API | Product requests (productaanvragen) |
| BAG | HaalCentraal BAG API | Address/building data |
| BRP | HaalCentraal BRP API | Citizen personal data |
| KVK | KVK Zoeken & Vestigingsprofielen API | Company data |
| Keycloak | OIDC/OAuth2 | Authentication, user/group management |
| PABC | REST API | Authorization mappings |
| OPA | REST API | Policy-based authorization |
| Solr | HTTP | Full-text search and indexing |
| SmartDocuments | REST + WebDAV | Document template creation |
| Office Converter | REST | Office-to-PDF conversion |
| SMTP | SMTP | Email sending |
| Open Notificaties | ZGW Notificaties API | Event notifications (optional) |
| Open Archiefbeheer | REST | Archive management (optional) |
| OpenTelemetry | OTLP gRPC | Observability (optional) |

---

## Key Differentiators vs Procest

### Strengths of ZAC
1. **Mature case management** -- comprehensive zaak lifecycle from intake through archival
2. **ZGW API compliance** -- full integration with Dutch government standard APIs
3. **Rich task workflow** -- 5 task types with email integration, suspension, and approval flows
4. **Document management** -- SmartDocuments integration, WebDAV editing, PDF conversion, digital signing
5. **Solr-powered search** -- fast indexed search across all entities with 4 search modes
6. **Customizable dashboard** -- drag-and-drop card arrangement with signaling cards
7. **Notification system** -- configurable per-user and per-group signals via dashboard and email
8. **Extensive admin tooling** -- zaaktype parameter configuration, mail templates, reference tables, configuration check
9. **Policy-based authorization** -- OPA + PABC for fine-grained, domain-based access control
10. **BAG/BRP/KVK integration** -- direct links to national base registrations
11. **Map/location support** -- geographic data linking with map visualization
12. **Contactmomenten** -- Open Klant integration for customer interaction history
13. **Multi-language** -- Dutch + English UI

### Weaknesses / Complexity
1. **Massive infrastructure** -- 17+ Docker services required, complex deployment
2. **External dependency heavy** -- requires Keycloak, OpenZaak, OPA, Solr, Redis, PABC, etc.
3. **Proprietary document creation** -- SmartDocuments is commercial software
4. **No standalone mode** -- cannot function without the full ZGW ecosystem
5. **Angular monolith** -- large frontend codebase, not component-library based
6. **Process model complexity** -- CMMN/BPMN requires Flowable expertise for customization
7. **Limited configurability without code** -- process changes require CMMN/BPMN model updates
8. **Memory hungry** -- ZAC alone needs 4GB, full stack needs 8-16GB RAM
9. **Slow startup** -- WildFly boot takes 30-300 seconds depending on resources

### Features Procest Should Consider
- Configurable dashboard cards (personal workspace)
- Saved search queries (persistent filters)
- Bulk work distribution (verdelen/vrijgeven)
- Task-based workflow with interim saves
- Document sending tracking with date stamps
- Zaak suspension/extension with automatic date recalculation
- Configuration check / validation tool
- Signaling system (user-configurable notifications per alert type)
- Person/company detail pages with cross-case overview
- Reference table management (admin-configurable lookup values)
- Mail template editor with variable insertion
