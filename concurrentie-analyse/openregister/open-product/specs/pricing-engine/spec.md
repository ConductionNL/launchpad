# Pricing Engine

## Summary

Open Product has a sophisticated pricing system where product types can have multiple prices scheduled for different dates, each with either simple options (flat amounts) or complex rules (DMN table references). The system supports "current price" queries and prevents mixing options and rules on a single price entry.

## Data Model

### Prijs (BaseModel)
- `producttype` -- FK to ProductType (CASCADE)
- `actief_vanaf` -- DateField (activation date, must be >= today at creation)
- Unique together: (producttype, actief_vanaf)

### PrijsOptie (BaseModel)
- `prijs` -- FK to Prijs (CASCADE)
- `bedrag` -- DecimalField (2 decimal places, 8 digits max, min 0.01)
- `beschrijving` -- CharField (e.g., "normaal", "spoed")

### PrijsRegel (BaseModel)
- `prijs` -- FK to Prijs (CASCADE)
- `beschrijving` -- CharField
- `dmn_config` -- FK to DmnConfig (CASCADE)
- `dmn_tabel_id` -- CharField (table ID within DMN instance)
- `mapping` -- JSONField (validated against mapping schema)
- Computed `url` property: `{dmn_config.tabel_endpoint}/{dmn_tabel_id}`

## Business Rules

1. **Mutual Exclusion**: A Prijs must have EITHER one or more PrijsOpties OR one or more PrijsRegels -- never both, never neither
2. **Date Scheduling**: Multiple prices can exist per product type with different `actief_vanaf` dates, enabling future price changes
3. **Current Price**: `actuele_prijs` is the Prijs with the most recent `actief_vanaf` <= today

## DMN Mapping Schema
The `mapping` field on PrijsRegel is validated against a JSON schema that defines:
- `static` entries: fixed name/classType/value tuples
- Dynamic entries: name/classType/regex tuples (keyed by field name)
- Supported classTypes: String, Integer, Double, Boolean, Date, Long

## API Endpoints
- `GET/POST /producttypen/api/v1/prijzen` -- list/create
- `GET/PUT/PATCH/DELETE /producttypen/api/v1/prijzen/{uuid}` -- detail CRUD
- `GET /producttypen/api/v1/producttypen/actuele-prijzen` -- all current prices
- `GET /producttypen/api/v1/producttypen/{uuid}/actuele-prijs` -- single current price

### Nested Update Behavior
Prijsopties/prijsregels support UUID-based upsert: include existing UUID to keep/update, omit to delete, no UUID means create new.

## Product-Level Pricing
Products also have their own `prijs` (DecimalField) and `frequentie` (eenmalig/maandelijks/jaarlijks), separate from the type-level pricing. This supports instance-specific pricing.

## Already in OpenRegister
- Numeric properties on objects
- Date properties on objects

## Not yet in OpenRegister
- **Date-scheduled price management** with future price activation
- **Price options** (multiple named amounts per price entry)
- **DMN-based price rules** with external decision table integration
- **Mutual exclusion validation** (options XOR rules)
- **Current price computation** from date-ordered price history
- **Separate type-level and instance-level pricing**
- **Payment frequency tracking** on instances
