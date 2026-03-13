# Open Beheer -- Competitive Analysis vs OpenRegister

**Repository:** https://github.com/maykinmedia/open-beheer
**Version:** 0.9.0 (active development)
**Commissioned by:** Gemeente Rotterdam
**Developed by:** Maykin B.V.
**License:** EUPL

---

## 1. Executive Summary

Open Beheer is a **unified admin dashboard** for managing ZGW (Zaakgericht Werken) catalog data -- specifically zaaktypen (case types) and informatieobjecttypen (document types). It acts as a **Backend-for-Frontend (BFF)** that sits between a React frontend and one or more Open Zaak Catalogi API instances. The app does NOT store data itself; it proxies all CRUD operations to external ZGW APIs.

This is fundamentally different from OpenRegister, which is a **generic object register** that stores data in its own database and exposes its own API. Open Beheer is domain-specific (ZGW catalog management), while OpenRegister is domain-agnostic.

---

## 2. Architecture

### 2.1 Stack

| Layer | Open Beheer | OpenRegister |
|-------|------------|--------------|
| **Frontend** | React 19.2 + TypeScript + Vite | Vue.js 2 (Nextcloud) |
| **Design System** | `@maykin-ui/admin-ui` (Maykin's own) | Nextcloud Vue components + NL Design System |
| **Backend** | Django/Python (BFF/proxy) | PHP/Nextcloud (native data store) |
| **Data Storage** | None (proxies to Open Zaak) | PostgreSQL/MySQL via Nextcloud |
| **API Style** | REST BFF (session auth + CSRF) | REST + MCP (Basic Auth / NC auth) |
| **Build** | Vite + Storybook 8.6 | Webpack |
| **Testing** | Vitest + Playwright + MSW | PHPUnit + Jest |
| **Router** | React Router v7 | vue-router |

### 2.2 BFF Architecture

Open Beheer's backend is a thin proxy layer:

```
Browser --> React SPA --> Django BFF --> Open Zaak Catalogi API
                                    --> Selectielijst API
                                    --> Objecttypen API
```

The BFF handles:
- Session-based authentication (cookie + CSRF)
- OIDC integration (via mozilla-django-oidc)
- 2FA enforcement (maykin-2fa)
- API aggregation and data enrichment ("expansions")
- Field metadata generation (fieldsets, typed fields, options)
- Zaaktype template management (stored in Python constants, not DB)
- Version management for zaaktypen

### 2.3 Service Configuration

Services (API backends) are configured via `zgw_consumers.Service` model in Django admin. Each service has:
- Label, slug, API root URL
- Auth type (ZGW JWT, API key, etc.)
- Client credentials

The frontend discovers services via `/api/v1/service/choices/` and uses the slug in URL routing.

---

## 3. Frontend UI Analysis

### 3.1 Layout Structure

The app uses a **three-panel layout** from `@maykin-ui/admin-ui`:

1. **Primary Navigation** (left icon strip, ~40px wide):
   - Maykin logo (top)
   - "Catalogi" button (grid icon)
   - User profile (avatar initials, bottom)

2. **Sidebar** (~300px wide, collapsible):
   - "Open Beheer" heading
   - Catalogus selector (dropdown)
   - Navigation items: "Zaaktypen", "Informatieobjecttypen"
   - Collapse/expand toggle

3. **Content Area** (remaining space):
   - Breadcrumb navigation
   - Data grid or detail view
   - Bottom toolbar with action buttons

### 3.2 Pages

#### Login Page (`/login`)
- Centered card with Maykin branding
- Username + password fields
- Optional OIDC "Organisatie login" button
- Uses `LoginTemplate` from admin-ui
- Dutch labels ("Gebruikersnaam", "Wachtwoord", "Inloggen")

#### Zaaktypen List (`/:service/:catalogus/zaaktypen`)
- `ListTemplate` with data grid
- Columns: url, identificatie, omschrijving, vertrouwelijkheidaanduiding, versiedatum, actief, eindeGeldigheid, concept
- Toolbar: search input (by identificatie) + filter dropdown (status: Alles/Concept/Definitief)
- "Nieuw zaaktype" button (blue primary)
- Pagination
- Click row to navigate to detail

#### Zaaktype Detail (`/:service/:catalogus/zaaktypen/:uuid`)
- `CardBaseTemplate` with breadcrumbs
- Title: `{identificatie} ({omschrijving})`
- "Bewerk in Open Zaak admin" link (external)
- **Version selector**: buttons for "Concept", "Actueel", historical versions with expand/collapse
- **Tabbed interface** (horizontal tabs auto-generated from fieldsets):
  - **Overzicht**: identificatie, omschrijving, doel, statustypen count, informatieobjecttypen count, resultaattypen count, etc.
  - **Algemeen**: 4 vertical subsections (icons + labels in a side Toolbar):
    - Algemeen: identificatie, doel, aanleiding, toelichting, vertrouwelijkheidaanduiding, etc.
    - Behandeling en proces: referentieproces, handelingInitiator, doorlooptijd, opschortingEnAanhoudingMogelijk, etc.
    - Bronnen en relaties: catalogus, deelzaaktypen
    - Publicatie: publicatieIndicatie, publicatietekst
  - **Statustypen** (DataGrid tab): editable table of status types
  - **Resultaattypen** (DataGrid tab): with archive form hook dialog
  - **Besluittypen**, **Eigenschappen**, **Roltypen**, **Informatieobjecttypen**, **Zaakobjecttypen** (DataGrid tabs)

- **Bottom toolbar** (sticky):
  - If no concept version: "Nieuwe Versie" button
  - If concept exists (not editing): "Bewerken" button
  - If editing: "Opslaan als" | "Annuleren" | "Publiceren" | "Opslaan"

#### Zaaktype Create (`/:service/:catalogus/zaaktypen/create`)
- Template selection grid (cards with radio buttons)
- Templates: "Basis" (blank), "Met statustypen" (pre-configured with Ingediend/In behandeling/Afgerond)
- "Gebruik dit sjabloon" button
- Modal form for base fields (identificatie, omschrijving)
- Template-based creation with pre-filled values

#### InformatieObjectTypen List (`/:service/:catalogus/informatieobjecttypen`)
- Same pattern as Zaaktypen list
- "Nieuw informatieobjecttype" button

#### InformatieObjectType Detail (`/:service/:catalogus/informatieobjecttypen/:uuid`)
- `AttributeGrid` display
- Edit mode toggle
- Bottom toolbar: "Bewerken" + "Publiceren" (concept) or "Annuleren" + "Opslaan en publiceren" + "Opslaan" (editing)

### 3.3 Key UI Components

| Component | Purpose |
|-----------|---------|
| `ListView` | Generic paginated list with DataGrid |
| `CreateView` | Template selection + modal form creation |
| `VersionSelector` | Version timeline with Concept/Actueel/Historical buttons |
| `ZaaktypeTabs` | Horizontal tabbed interface from fieldset config |
| `ZaaktypeAttributeGridTab` | AttributeGrid with vertical subsections |
| `ZaaktypeDataGridTab` | Editable DataGrid for related objects |
| `RelatedObjectDataGrid` | CRUD DataGrid for child objects (add/edit/delete rows) |
| `ZaaktypeFilter` | Search + status filter dropdown |
| `ArchiveForm` | Specialized form for resultaattype archive settings |
| `Profile` | User dropdown with avatar initials + logout |
| `ZaaktypeToolbar` | Context-sensitive bottom action bar |
| `RelatedObjectBadge` | Badge display for linked objects |

### 3.4 Design System

Uses `@maykin-ui/admin-ui` v2.0.0-alpha.31 with the `blue-suede-shoes` theme:
- Blue primary color palette
- Light blue/grey backgrounds
- Card-based layouts
- Heroicons (Outline + Solid variants)
- No NL Design System tokens or components
- No CSS custom properties for government theming
- Dutch UI labels throughout

---

## 4. Data Model

Open Beheer manages these ZGW Catalogi API resources:

### Primary Resources
- **ZaakType**: Case type with full lifecycle (concept -> published -> versioned)
- **InformatieObjectType**: Document type definition

### Related Resources (managed as sub-objects of ZaakType)
- **StatusType**: Case status definitions (volgnummer ordering)
- **ResultaatType**: Case result types (with archiving configuration)
- **RolType**: Case role definitions
- **EigenschapType**: Case property definitions
- **BesluitType**: Decision type definitions
- **ZaakObjectType**: Case-object type relations
- **ZaakTypeInformatieObjectType**: M2M between zaaktype and informatieobjecttype

### External References
- **Catalogus**: Container for all types (from Open Zaak)
- **SelectielijstProcestype**: Archival classification (from VNG Selectielijst API)
- **ObjectType**: From Objecttypen API

---

## 5. CRUD Capabilities

### Create
- Zaaktypen via template-based creation (2 built-in templates)
- Related objects (statustypen, resultaattypen, etc.) created inline during zaaktype creation
- InformatieObjectTypen via simple form

### Read
- Paginated list views with field-level metadata from backend
- Detail views with expandable related objects
- Version history with version selector
- Search by identificatie, filter by status (concept/definitief/alles)

### Update
- Version-based editing: create new concept version, edit, then publish
- Inline editing of all zaaktype fields
- Inline CRUD of related objects in DataGrid tabs
- Batch save: deletions run first, then updates (separate transactions)
- "Save as new zaaktype" (clone) functionality
- Field-level validation with error display per tab

### Delete
- Related objects can be deleted from DataGrid tabs
- Zaaktype versions can be deleted (concept only per ZGW spec)

### Publish
- Concept versions can be published (makes them "definitief")
- "Opslaan en publiceren" combined action for informatieobjecttypen

---

## 6. Key Differentiators vs OpenRegister

### 6.1 What Open Beheer Does Better

1. **Domain-specific UX**: Purpose-built for ZGW zaaktype management with deep understanding of the domain (versioning, concept/published lifecycle, archival configuration)

2. **Version management**: First-class version selector with concept/actueel/historical states, visual timeline

3. **Template-based creation**: Pre-configured templates for common zaaktype patterns

4. **Inline related object editing**: DataGrid tabs with add/edit/delete for child objects, pre-commit hooks (e.g., archive form for resultaattypen)

5. **Field metadata from backend**: Backend generates typed fields, options, fieldsets -- frontend renders dynamically

6. **Professional component library**: `@maykin-ui/admin-ui` with Storybook, consistent theming

7. **Multi-backend support**: Can connect to multiple Open Zaak instances via service configuration

### 6.2 What OpenRegister Does Better

1. **Generic**: Works with any data model, not limited to ZGW catalog types

2. **Self-contained**: Stores data in its own database -- no external dependencies

3. **Nextcloud integration**: File attachments, user management, app ecosystem, notifications

4. **NL Design System**: Government theming support via CSS custom properties

5. **MCP protocol**: AI-friendly data access via standard MCP

6. **Schema-driven**: JSON Schema-based data modeling with validation

7. **Pipeline processing**: Data transformation pipelines (via pipelinq)

8. **Search/faceting**: Built-in search with configurable facets

9. **Multi-register**: Can host many different registers in one instance

### 6.3 Feature Comparison

| Feature | Open Beheer | OpenRegister |
|---------|------------|--------------|
| Data storage | External (Open Zaak) | Internal (DB) |
| Schema definition | Fixed (ZGW spec) | Dynamic (JSON Schema) |
| CRUD operations | Yes (via BFF proxy) | Yes (native) |
| Versioning | ZGW-style (concept/published) | Object-level audit trail |
| Search | Identificatie + status filter | Full-text + faceted search |
| File handling | Via informatieobjecttypen | Nextcloud file system |
| Auth | Session + CSRF + OIDC + 2FA | Nextcloud auth |
| Multi-tenancy | Via service/catalogus | Via register separation |
| API documentation | OpenAPI (drf-spectacular) | OpenAPI + MCP discovery |
| Theming | Fixed (blue-suede-shoes) | NL Design System tokens |
| Templates | Zaaktype templates | No equivalent |
| Bulk operations | Batch save with actions | Individual operations |
| Design system | @maykin-ui/admin-ui | Nextcloud Vue + nldesign |
| Framework | React 19 + Django | Vue 2 + PHP/Nextcloud |
| Storybook | Yes (v8.6) | No |
| Test strategy | Vitest + Playwright + MSW | PHPUnit + Jest |

---

## 7. UI Patterns Worth Adopting

### 7.1 Version Selector
The version timeline with Concept/Actueel/Historical buttons is excellent UX for managing object versions. OpenRegister could adopt a similar pattern for schema version management.

### 7.2 Dynamic Fieldsets from Backend
The backend generates `fields` (typed field definitions with options) and `fieldsets` (grouped field layouts) that the frontend renders. This approach keeps the frontend generic while the backend controls the layout. OpenRegister's schema-driven approach could benefit from similar backend-controlled fieldset generation.

### 7.3 DataGrid Tabs with Related Objects
The tabbed interface where each tab is either an AttributeGrid (key-value pairs) or a DataGrid (table of related objects) is a clean pattern for managing parent-child relationships.

### 7.4 Template-Based Creation
The template card selection flow for creating new zaaktypen is good UX. OpenRegister could offer schema templates with pre-filled structures.

### 7.5 Batch Action System
The action accumulation pattern (recording create/update/delete actions during edit mode, then submitting all at once) prevents partial saves and allows proper transaction management.

### 7.6 Pre-commit Hooks on Related Objects
The `hook` function on `RelatedObjectDataGrid` that can open a dialog before committing a change (e.g., the archive form for resultaattypen) is a powerful pattern for complex business rules.

---

## 8. Weaknesses Observed

1. **No error boundary**: The React Router default error page is shown when services are misconfigured -- poor UX for production

2. **Tight coupling to Open Zaak**: Cannot work without a running Open Zaak instance; no offline/demo mode

3. **Limited scope**: Only manages zaaktypen and informatieobjecttypen -- no zaken, documenten, besluiten management

4. **No NL Design System**: Despite being a government project, uses Maykin's proprietary design system

5. **MFA complexity**: The 2FA setup for Django admin is overly complex for a development setup

6. **Single-service per session**: The service selector is a simple redirect, not a true multi-backend workspace

7. **No bulk operations**: Cannot bulk-publish, bulk-delete, or bulk-import zaaktypen

8. **No export/import**: No way to export zaaktype configurations between environments

---

## 9. Screenshots

| File | Description |
|------|-------------|
| `01-initial-load.png` | First load redirects to login |
| `02-login-no-service-error.png` | Error when no services configured |
| `03-main-layout-no-catalogus.png` | Main layout with sidebar (no catalogus data) |
| `04-login-page.png` | Login page with Maykin branding |
| `05-main-layout-authenticated.png` | Authenticated main layout with profile avatar |
| `06-profile-dropdown.png` | User profile dropdown with logout |
| `07-sidebar-collapsed.png` | Collapsed sidebar state |
| `08-api-docs.png` | Redoc API documentation page |

---

## 10. Conclusion

Open Beheer is a well-engineered, domain-specific admin tool for ZGW catalog management. Its BFF architecture, template system, and version management are mature patterns. However, its tight coupling to Open Zaak, limited scope (only catalog types), and lack of NL Design System support limit its applicability.

For OpenRegister, the main takeaways are:
1. **Adopt the dynamic fieldset pattern** -- let the backend define field layouts
2. **Add version selectors** for schema/object version management
3. **Implement DataGrid tabs** for managing related objects inline
4. **Consider template-based creation** for common register patterns
5. **Add batch action support** for complex multi-object saves

OpenRegister's generic approach (any data model, self-contained storage, Nextcloud ecosystem) remains a significant advantage over Open Beheer's narrow ZGW focus.
