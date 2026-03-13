# Zaaktype (Case Type) Management

## Feature Summary

Full CRUD lifecycle management for Zaaktypen (case types) in the ZGW Catalogi API. This is the primary feature of Open Beheer -- a form-driven interface for creating, viewing, editing, publishing, and deleting case type definitions that previously required direct Open Zaak admin access.

## How It Works in Open Beheer

### List View
- Paginated data grid showing zaaktypen from a selected catalogus
- Columns: URL, identificatie, omschrijving, vertrouwelijkheidaanduiding, versiedatum, actief, eindeGeldigheid, concept
- Filters: status (concept/definitief/alles), search on identificatie and omschrijving (icontains)
- Page size: 10 items (hardcoded in BFF)
- "Nieuw zaaktype" button links to template-based creation flow

### Detail View
- Tabbed interface with configurable tabs from backend fieldsets:
  - **Overzicht** (Overview): identificatie, omschrijving, doel, related object counts
  - **Algemeen** (General): All zaaktype scalar fields organized in sections (Algemeen, Behandeling en proces, Bronnen en relaties, Publicatie)
  - **Statustypen**: Editable data grid (volgnummer, omschrijving, informeren)
  - **ZaaktypeInformatieobjecttypen**: Editable data grid (volgnummer, informatieobjecttype, richting)
  - **Roltypen**: Editable data grid (omschrijving, omschrijvingGeneriek)
  - **Resultaattypen**: Editable data grid (omschrijving, resultaattypeomschrijving, selectielijstklasse, brondatumArchiefprocedure.afleidingswijze)
  - **Eigenschappen**: Editable data grid (naam, definitie, specificatie.formaat)
- Version selector component showing all versions of this zaaktype
- "Bewerk in Open Zaak admin" deep link

### Create Flow
- Template selector (currently only "Basis" template)
- Template provides pre-filled default values for all required ZGW fields
- Modal form for identificatie + omschrijving
- POST to Open Zaak creates zaaktype + all related objects from _expand in a single transaction

### Edit Flow
- Creates a new concept version (POST) or edits existing concept
- Scalar field changes tracked as `pendingUpdatesState`
- Related object changes tracked as actions (ADD/EDIT/DELETE)
- Save: batched actions -- first deletions, then PATCH zaaktype + creates/updates
- Cancel: discard all changes, navigate back to current published version

### Publish Flow
- PATCH old version's eindeGeldigheid to yesterday
- PATCH new version's beginGeldigheid to today (if not set or past)
- POST to `/zaaktypen/{uuid}/publish/` endpoint

### Delete
- DELETE to Open Zaak, works on both concept and published zaaktypen (despite ZGW spec saying concept-only)

## Technical Implementation

### Backend (BFF)
- `ZaakTypeListView`: ListView[ZaaktypenGetParametersQuery, ZaakTypeSummary, ZaakType]
- `ZaakTypeDetailView`: DetailWithVersions + DetailView[ExpandableZaakType]
- `ZaakTypePublishView`: POST proxy to Open Zaak publish endpoint
- `ZaakTypeTemplateListView` / `ZaakTypeTemplateView`: Serve hardcoded templates
- Expansions on detail: besluittypen, statustypen, resultaattypen, eigenschappen, informatieobjecttypen, roltypen, zaakobjecttypen, selectielijst_procestype, zaaktypeinformatieobjecttypen
- `create_related()`: After creating zaaktype, creates all expanded related objects and patches M2M relations back

### Frontend
- `ZaaktypenPage`: List with filters and create button
- `ZaaktypePage`: Detail with tabs, version selector, edit/save/publish toolbar
- `ZaaktypeCreatePage`: Template selector + creation modal
- Actions: CREATE_VERSION, UPDATE_VERSION, SAVE_AS, PUBLISH_VERSION, EDIT_VERSION, EDIT_CANCEL, SELECT_VERSION, SET_TAB, EDIT_RELATED_OBJECT, ADD_RELATED_OBJECT, DELETE_RELATED_OBJECT, BATCH

## Already in OpenRegister

- **Object CRUD**: OpenRegister provides full CRUD for any object type via registers + schemas
- **List views with pagination**: Built into the Vue.js admin
- **Detail views with editing**: Object detail forms generated from JSON Schema
- **Search and filtering**: Full-text search + faceted filtering (more powerful than Open Beheer's basic icontains)
- **Related objects**: Schema references allow linking between objects
- **Tabbed interface**: The Vue.js admin has tabbed layouts

## Not Yet in OpenRegister

- **Publishing/draft workflow**: No concept of draft vs published objects. All objects are immediately live. A concept/publish workflow would be valuable for governance scenarios.
- **Template-based creation**: No pre-filled templates for creating objects. Schema defaults exist but no curated "starter templates" with example values.
- **Version management with geldigheid dates**: No begin/eindeGeldigheid-based version timeline. OpenRegister has audit logging but not ZGW-style versioning.
- **Inline related object editing in data grids**: OpenRegister shows related objects but doesn't allow inline CRUD within a parent's detail view.
- **Deep linking to underlying API admin**: No "edit in original system" type links.
- **Field metadata from backend**: OpenRegister derives fields from JSON Schema; Open Beheer generates OBField with type/options/editability from Python msgspec types. The concepts are similar but OpenRegister could expose richer metadata.
- **Selectielijst/archival integration**: No integration with national selectielijst for archival classification.
