# Field Metadata System

## Feature Summary

Open Beheer's BFF generates rich field metadata (OBField) for every API response.
This metadata tells the frontend how to render each field: its label, type, whether
it's required, editable, and what choices are available. The frontend dynamically
builds forms from this metadata rather than hardcoding field definitions.

## How It Works in Open Beheer

### OBField Structure

Each field in a detail response includes metadata like:
- `name`: Field identifier (camelCase)
- `label`: Human-readable label (Dutch)
- `type`: Field type (string, number, boolean, date, choice, etc.)
- `required`: Whether the field is mandatory
- `editable`: Whether the field can be modified (some fields are read-only after publish)
- `choices`: For choice fields, an array of {label, value} options
- `helpText`: Tooltip/description text

### Fieldsets

Fields are grouped into fieldsets for the detail view layout:
- Each fieldset has a label and a list of field names
- The frontend renders fieldsets as visual sections
- Examples: "Algemeen", "Behandeling en proces", "Bronnen en relaties", "Publicatie"

### Dynamic Choice Loading

Some choice fields have their options loaded from external APIs:
- Resultaattype.selectielijstklasse: Options from Selectielijst API
- ZaakObjectType.objecttype: Options from Objecttypen API
- ZaakTypeInformatieObjectType.informatieobjecttype: Options from Catalogi API

The BFF fetches these options and embeds them in the field metadata.

### Conditional Editability

Field editability depends on state:
- Concept zaaktypen: most fields editable
- Published zaaktypen: most fields read-only
- Some fields always read-only (URL, UUID)

### Response Envelope

```json
{
  "data": { ... },
  "fields": [
    { "name": "identificatie", "label": "Identificatie", "type": "string", "required": true, "editable": true },
    { "name": "omschrijving", "label": "Omschrijving", "type": "string", "required": true, "editable": true },
    ...
  ],
  "fieldsets": [
    { "label": "Algemeen", "fields": ["identificatie", "omschrijving", "doel"] },
    ...
  ]
}
```

### Implementation

Field metadata is generated from Python msgspec Struct type annotations:
- `get_fields()`: Introspects the Struct type to generate OBField array
- `get_fieldsets()`: Returns named field groups from constants
- `parse_ob_fields()`: Processes raw data into OBField-compatible format

## Already in OpenRegister

- **JSON Schema-driven forms**: OpenRegister generates admin forms from JSON Schema properties (type, format, enum, required, description)
- **Dynamic field types**: Schema properties determine field rendering
- **Required field enforcement**: JSON Schema `required` array enforced on forms

## Not Yet in OpenRegister

- **Explicit field metadata API**: OpenRegister derives form structure from JSON Schema at render time. It does not send a separate `fields[]` array with computed editability and pre-loaded choices alongside data.
- **Backend-driven fieldsets**: No server-side grouping of fields into named sections. The frontend determines layout.
- **Conditional editability based on object state**: No field-level read-only based on publish state. All editable fields are always editable.
- **Pre-loaded external choice options**: OpenRegister does not pre-fetch options from external APIs and embed them in field metadata. Dropdowns are populated from schema enum values or relations.
- **Field-level help text from backend**: JSON Schema `description` exists but is not consistently surfaced as tooltip help text in the admin UI.
