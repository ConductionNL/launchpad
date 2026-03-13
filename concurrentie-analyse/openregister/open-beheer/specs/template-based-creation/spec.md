# Template-Based Creation

## Feature Summary

Open Beheer provides a template system for creating new zaaktypen. Instead of filling
in all required fields from scratch, users select a template that provides pre-filled
default values for the zaaktype and all its related objects. Currently only a "Basis"
(basic) template exists.

## How It Works in Open Beheer

### Template Flow

1. User clicks "Nieuw zaaktype" on the zaaktypen list page
2. Frontend navigates to `/zaaktypen/create`
3. `ZaaktypeCreatePage` fetches available templates from `/api/v1/template/zaaktype/`
4. User selects a template (currently only "Basis")
5. Template detail is fetched: `/api/v1/template/zaaktype/{uuid}/`
6. Modal form appears for user to enter identificatie + omschrijving
7. On submit, the BFF:
   a. Creates the zaaktype in Open Zaak with template defaults + user input
   b. Creates all related objects defined in the template (_expand data)
   c. Patches M2M relations back to the zaaktype
8. User is redirected to the new zaaktype detail page

### Template Structure

A template contains:
- Default zaaktype field values (doel, aanleiding, indicatieInternOfExtern, etc.)
- Pre-configured statustypen (e.g., "Intake", "In behandeling", "Afgerond")
- Pre-configured resultaattypen with selectielijst references
- Pre-configured roltypen (e.g., "Initiator", "Behandelaar")
- Pre-configured eigenschappen

### API Endpoints

- `GET /api/v1/template/zaaktype/` -- list available templates
- `GET /api/v1/template/zaaktype/{uuid}/` -- template detail with expanded related objects

### Technical Note

Templates are currently **hardcoded in Python** (not stored in a database or external
service). The `ZaakTypeTemplateListView` and `ZaakTypeTemplateView` serve static
template data. This means adding new templates requires code changes.

## Already in OpenRegister

- **Schema-based defaults**: JSON Schema `default` values are applied when creating objects
- **JSON Schema import**: Users can import complete schemas including nested definitions

## Not Yet in OpenRegister

- **Curated starter templates**: No pre-configured templates with example values for creating objects. Users start from a blank form or import a schema definition.
- **Template gallery/selector UI**: No UI for browsing and selecting from templates
- **Template-based creation with related objects**: No mechanism to create a parent + multiple related objects from a single template in one step
- **Process-oriented templates**: Templates in Open Beheer represent common government processes (e.g., "vergunningsaanvraag"). OpenRegister has no concept of process templates.
