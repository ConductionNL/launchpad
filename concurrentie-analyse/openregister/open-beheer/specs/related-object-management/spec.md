# Related Object Management

## Feature Summary

Open Beheer provides inline management of related objects nested under a parent
zaaktype. For each zaaktype, users can create, edit, and delete statustypen,
resultaattypen, roltypen, besluittypen, eigenschappen, zaakobjecttypen, and
zaaktypeinformatieobjecttypen -- all within the same detail view.

## How It Works in Open Beheer

### Nested Resource Types

Each zaaktype has tabs for these related object types:

| Related Type | Key Fields | External API |
|-------------|-----------|-------------|
| Statustypen | volgnummer, omschrijving, informeren | Catalogi API |
| Resultaattypen | omschrijving, resultaattypeomschrijving, selectielijstklasse, brondatumArchiefprocedure | Catalogi API + Selectielijst |
| Roltypen | omschrijving, omschrijvingGeneriek | Catalogi API |
| Besluittypen | omschrijving, omschrijvingGeneriek, besluitcategorie | Catalogi API |
| Eigenschappen | naam, definitie, specificatie.formaat | Catalogi API |
| ZaakObjectTypen | objecttype, relatieOmschrijving | Catalogi + Objecttypen API |
| ZaakTypeInformatieObjectTypen | volgnummer, informatieobjecttype, richting | Catalogi API |

### Inline Editing Pattern

1. User opens zaaktype detail view
2. Navigates to a related object tab (e.g., "Statustypen")
3. Sees an editable data grid of existing related objects
4. Can add new rows, edit existing rows, or delete rows
5. Changes are tracked as pending actions (ADD/EDIT/DELETE)
6. On save, the BFF executes all actions in batch: deletions first, then
   PATCH parent + creates/updates

### Expansion Pattern

The BFF fetches related objects via the `_expand` mechanism:
- Detail request for zaaktype triggers additional API calls for each related type
- Related objects are nested in the response under their type key
- The frontend receives a complete zaaktype with all relations in one request

### API Endpoints (nested under zaaktype)

All at `/api/v1/service/{slug}/zaaktypen/{uuid}/`:
- `GET/POST .../statustypen/`
- `GET/POST .../resultaattypen/`
- `GET/POST .../roltypen/`
- `GET/POST .../besluittypen/`
- `GET/POST .../eigenschappen/`
- `GET/POST .../zaakobjecttypen/`
- `GET/POST .../zaaktypeinformatieobjecttypen/`

### Selectielijst Integration

Resultaattypen require references to the national Selectielijst:
- `selectielijstklasse` links to a procestype in the Selectielijst API
- The BFF fetches procestypen from Selectielijst and presents them as dropdown options
- Archival rules (bewaartermijn, archiefactietermijn) are derived from the selected klasse

### Objecttypen Integration

ZaakObjectTypen link zaaktypen to object types from the Objecttypen API:
- The BFF fetches available objecttypen from the configured Objecttypen service
- Presents them as selectable options
- The relation stores objecttype URL + relatieOmschrijving

## Already in OpenRegister

- **Object relations**: OpenRegister supports schema-to-schema relations (oneOf, anyOf references)
- **Nested object display**: Related objects shown in detail views
- **Multiple relation types**: Relations can be defined in JSON Schema using $ref

## Not Yet in OpenRegister

- **Inline editable data grids for related objects**: OpenRegister shows related objects but does not provide inline CRUD within editable grid rows on a parent detail page
- **Batch save with multiple related object changes**: No mechanism to accumulate multiple ADD/EDIT/DELETE actions and execute them atomically on save
- **Typed relation categories**: OpenRegister has generic relations. Open Beheer has domain-specific typed relations (statustype, resultaattype, etc.) each with their own schema and validation rules
- **National reference data integration**: No Selectielijst or similar national registry integration for pre-populating field choices
- **Volgnummer (sequence number) management**: No automatic ordering/sequencing of related objects
