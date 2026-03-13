# Informatieobjecttype (Document Type) Management

## Feature Summary

CRUD management for Informatieobjecttypen (document types) in the ZGW Catalogi API. This is the second entity type managed by Open Beheer, providing a simpler interface than zaaktypen since informatieobjecttypen have no related child objects.

## How It Works in Open Beheer

### List View
- Paginated data grid: omschrijving, vertrouwelijkheidaanduiding, versiedatum, actief, eindeGeldigheid, concept
- Filter by catalogus (mandatory, part of URL path) and optionally by zaaktype
- No version management on the list view (unlike zaaktypen)

### Detail View
- Single AttributeGrid (no tabs, no related objects)
- Fieldsets defined in `INFORMATIEOBJECTTYPE_FIELDSETS` (backend constant)
- Fields derived from `InformatieObjectType` ZGW type
- Edit mode toggle: concept IOTs show "Bewerken" + "Publiceren" buttons; editing shows "Annuleren" + "Opslaan en publiceren" + "Opslaan"
- No version selector (DetailViewWithoutVersions)

### Create Flow
- Simple form (no template system)
- POST directly to Open Zaak informatieobjecttypen endpoint

### Publish Flow
- POST to `/informatieobjecttypen/{uuid}/publish/`
- "Opslaan en publiceren" saves first, then publishes

## Technical Implementation

### Backend
- `InformatieObjectTypeListView`: ListView with catalogus + zaaktype query params
- `InformatieObjectTypeDetailView`: DetailViewWithoutVersions (no version timeline)
- `InformatieObjectTypePublishView`: POST proxy for publishing
- No expansions (informatieobjecttypen have no child relationships in ZGW)
- `concept` field is not directly editable -- changed via publish action

### Frontend
- `InformatieObjectTypenPage`: Simple list page
- `InformatieObjectTypePage`: Detail with AttributeGrid, edit/publish buttons
- `InformatieObjectTypeCreatePage`: Create form
- Actions: SET_EDIT_MODE_ON, SET_EDIT_MODE_OFF, UPDATE, PUBLISH, UPDATE_AND_PUBLISH

## Already in OpenRegister

- **Object CRUD for any schema**: OpenRegister can manage any object type, including document type definitions
- **List and detail views**: Standard in the Vue.js admin
- **Form-based editing**: Generated from JSON Schema

## Not Yet in OpenRegister

- **Concept/publish workflow**: No draft/published state management. All objects are immediately live.
- **"Save and publish" combined action**: No multi-step save+publish workflow
- **Fieldset-driven layout from backend**: OpenRegister generates forms from JSON Schema but doesn't have backend-driven fieldset groupings for the detail view layout
