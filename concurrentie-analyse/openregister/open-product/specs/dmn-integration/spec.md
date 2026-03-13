# DMN (Decision Model & Notation) Integration

## Summary

Open Product integrates with external DMN engines (like Flowable) for dynamic pricing rules and product type actions. DMN configuration is managed centrally, and individual price rules or actions reference specific decision tables within those engines.

## Data Model

### DmnConfig (BaseModel)
- `naam` -- CharField (name of the DMN instance)
- `tabel_endpoint` -- URLField, unique (base URL for DMN tables, e.g., `https://gemeente.flowable-dmn.nl/flowable-rest/dmn-api/dmn-repository/`)

### Usage in PrijsRegel
- `dmn_config` -- FK to DmnConfig
- `dmn_tabel_id` -- specific table identifier
- `mapping` -- JSON mapping between Open Product fields and DMN variables
- Computed `url`: `{tabel_endpoint}/{dmn_tabel_id}`

### Usage in Actie
- `dmn_config` -- FK to DmnConfig (optional, PROTECT)
- `dmn_tabel_id` -- specific table identifier
- `direct_url` -- alternative: direct URL instead of DMN reference
- Validation: must have EITHER (direct_url) OR (dmn_config + dmn_tabel_id), not both

### DMN Mapping Schema
Validated JSON structure defining variable mappings:
- `static` array: fixed values (name + classType + value)
- Dynamic entries: field-based extraction (name + classType + regex)
- ClassType enum: String, Integer, Double, Boolean, Date, Long

## Setup Configuration
DmnConfig can be provisioned via `django-setup-configuration`:
```yaml
dmn_config_enable: true
dmn_config:
  configs:
    - naam: "main repository"
      tabel_endpoint: "https://gemeente.flowable-dmn.nl/..."
```

## Already in OpenRegister
- External service connections via OpenConnector
- n8n workflow integration for business logic

## Not yet in OpenRegister
- **Centralized DMN engine configuration** with named instances
- **Decision table references** on pricing rules and actions
- **Variable mapping schema** for DMN input/output mapping
- **URL XOR DMN validation** on actions (one or the other, not both)
- **Setup-configuration-based provisioning** for DMN endpoints
