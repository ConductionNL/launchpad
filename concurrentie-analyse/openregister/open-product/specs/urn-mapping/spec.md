# URN Mapping System

## Summary

Open Product implements a bidirectional URN-URL mapping system for cross-system resource identification. External references (zaaktypen, verzoektypen, processen, documenten, zaken, taken) can be stored as URN, URL, or both, with automatic resolution between the two via configurable mapping tables.

## URN Format

Pattern: `<organisatie>:<systeem>:<component>:<resource>:<uuid>`
Regex: `([a-z-]+):([a-z-]+):([a-z-]+):([a-z-]+):([UUID])`

Example: `maykin:abc:ztc:zaaktype:99a8bd4f-4144-4105-9850-e477628852fc`

The base URN (without UUID): `maykin:abc:ztc:zaaktype`

## Data Model

### UrnMappingConfig
- `urn` -- BaseUrnField, unique (base URN without UUID: `organisatie:systeem:component:resource`)
- `url` -- URLField, unique (base URL, e.g., `https://gemeente-a.zgw.nl/zaaktypen`)

### UrnAbstractModel (abstract, used by ZaakType, VerzoekType, Proces, Document, Zaak, Taak)
- `urn` -- UrnField (nullable)
- `url` -- URLField (nullable)
- At least one required (validated at serializer level)

### UrnField
CharField with regex validation: `^([a-z-]+):([a-z-]+):([a-z-]+):([a-z-]+):([UUID])$`

### UrlField
URLField with validation that URL must end with a UUID.

## Auto-Resolution Logic (UrnMappingMixin)

When saving a resource with URN and/or URL:

1. **URL only provided**: Look up URL base in UrnMappingConfig -> auto-fill URN
2. **URN only provided**: Look up URN base in UrnMappingConfig -> auto-fill URL
3. **Both provided**: Validate they map to the same config entry; if no mapping exists, auto-create one
4. **UUID consistency**: If both URN and URL contain UUIDs, they must match

### Configurable Strictness
- `REQUIRE_URN_URL_MAPPING` (default: True) -- URN must have a known URL mapping
- `REQUIRE_URL_URN_MAPPING` (default: False) -- URL must have a known URN mapping

## Setup Configuration
URN mappings can be provisioned via YAML:
```yaml
urn_mapping_config_enable: true
urn_mapping_config:
  configs:
    - urn: "maykin:abc:ztc:zaaktype"
      url: "https://gemeente-a.zgw.nl/zaaktypen"
```

## Already in OpenRegister
- URL-based references to external resources
- UUID-based identification

## Not yet in OpenRegister
- **Structured URN format** (organisatie:systeem:component:resource:uuid)
- **Bidirectional URN-URL mapping** with auto-resolution
- **Mapping configuration table** for base URN/URL pairs
- **Auto-creation of mappings** when both URN and URL are first provided together
- **Configurable strictness** (require mappings or allow partial references)
- **UUID consistency validation** between URN and URL
