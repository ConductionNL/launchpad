# Custom Municipal Schemas

This directory is for municipality-specific schema extensions. Custom schemas
can extend the base ZGW schemas using JSON Schema `allOf` composition.

## How to Add a Custom Schema

1. Create a JSON Schema file in this directory, e.g. `gemeente-x-zaak.json`
2. Use `allOf` to compose the base schema with your custom constraints:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "allOf": [
    { "$ref": "../zgw/zrc/1.5.1/zaak.json" },
    {
      "required": ["eigenschappen"],
      "properties": {
        "eigenschappen": {
          "minItems": 1
        }
      }
    }
  ]
}
```

3. Set the corresponding environment variable to activate the custom schema:

```bash
# Override the zaak schema for collection 'zaken'
ZRC_ZAAK_SCHEMA=custom/gemeente-x-zaak
```

## Environment Variable Names

Each ZGW collection has a corresponding environment variable:

| Collection | Environment Variable |
|-----------|---------------------|
| zaken | `ZRC_ZAAK_SCHEMA` |
| statussen | `ZRC_STATUS_SCHEMA` |
| resultaten | `ZRC_RESULTAAT_SCHEMA` |
| rollen | `ZRC_ROL_SCHEMA` |
| zaakobjecten | `ZRC_ZAAKOBJECT_SCHEMA` |
| zaakinformatieobjecten | `ZRC_ZAAKINFORMATIEOBJECT_SCHEMA` |
| zaakeigenschappen | `ZRC_ZAAKEIGENSCHAP_SCHEMA` |
| catalogussen | `ZTC_CATALOGUS_SCHEMA` |
| zaaktypen | `ZTC_ZAAKTYPE_SCHEMA` |
| statustypen | `ZTC_STATUSTYPE_SCHEMA` |
| resultaattypen | `ZTC_RESULTAATTYPE_SCHEMA` |
| roltypen | `ZTC_ROLTYPE_SCHEMA` |
| eigenschappen | `ZTC_EIGENSCHAP_SCHEMA` |
| informatieobjecttypen | `ZTC_INFORMATIEOBJECTTYPE_SCHEMA` |
| besluittypen | `ZTC_BESLUITTYPE_SCHEMA` |
| zaaktypeinformatieobjecttypen | `ZTC_ZAAKTYPEINFORMATIEOBJECTTYPE_SCHEMA` |
| documenten | `DRC_ENKELVOUDIGINFORMATIEOBJECT_SCHEMA` |
| gebruiksrechten | `DRC_GEBRUIKSRECHTEN_SCHEMA` |
| objectinformatieobjecten | `DRC_OBJECTINFORMATIEOBJECT_SCHEMA` |
| besluiten | `BRC_BESLUIT_SCHEMA` |
| besluitinformatieobjecten | `BRC_BESLUITINFORMATIEOBJECT_SCHEMA` |
| notificaties | `NRC_NOTIFICATIE_SCHEMA` |

## Rules

- Custom schemas EXTEND base schemas; they do not replace them
- Use `allOf` composition to combine base + custom constraints
- All `$ref` paths must be local (relative file paths); HTTP references are rejected
- The CI pipeline validates all schema files including custom ones
- Custom schemas must be valid JSON Schema Draft 7
