# Spec: Pricing System & DMN Integration

## Feature Summary

Open Product has a built-in pricing system with date-activated prices, simple price options, and complex pricing via DMN (Decision Model and Notation) decision tables in external engines.

## Capabilities

### Date-Based Price Activation
- Each ProductType can have multiple Prijs records
- Each Prijs has an `actief_vanaf` (active from) date
- The system returns the most recent price that has passed its activation date
- Bulk endpoint `/producttypen/actuele-prijzen` returns current prices for all product types
- Per-type endpoint `/producttypen/{uuid}/actuele-prijs`

### Simple Pricing (PrijsOptie)
- Each price can have multiple options (e.g., "normal" and "urgent" pricing)
- Each option: `bedrag` (amount as decimal) + `beschrijving` (description)
- Suitable for products with straightforward fee structures

### Complex Pricing (PrijsRegel / DMN)
- Each price can alternatively have pricing rules linked to external DMN tables
- PrijsRegel fields:
  - `url` -- URL to the DMN decision table
  - `beschrijving` -- Description of the rule
  - `mapping` -- JSON mapping between Open Product fields and DMN input variables
- The mapping supports:
  - `product` variables: extracted from product data using JSONPath-like expressions
  - `static` variables: fixed values passed to the DMN engine

### Product-Level Pricing
- Individual products also have `prijs` (decimal) and `frequentie` (eenmalig/maandelijks/jaarlijks) fields
- These represent the actual price charged for a specific product instance
- Optional since v1.5.0

### DMN Mapping Example
```json
{
  "product": [
    {"name": "pid", "regex": "$.uuid", "classType": "String"},
    {"name": "geldigheideinddatum", "regex": "$.eindDatum", "classType": "String"},
    {"name": "aantaluren", "regex": "$.verbruiksobject.uren", "classType": "String"}
  ],
  "static": [
    {"name": "formulieren", "classType": "String", "value": "https://openformulieren-gemeente-a.nl"}
  ]
}
```

## OpenRegister Equivalent

OpenRegister has no built-in pricing system. To replicate:

1. **Simple pricing:** Define a `Prijs` schema with `actief_vanaf`, and nested `opties` array with `bedrag` and `beschrijving`. Link to ProductType via relation.
2. **Date activation:** Implement via n8n workflow that checks dates and sets "current price" flag.
3. **DMN integration:** Use n8n or OpenConnector to call external DMN endpoints with mapped data.
4. **Bulk price endpoint:** Would need a custom API endpoint or n8n workflow.

**Open Product advantage:** Pricing is a first-class citizen with dedicated endpoints, date activation logic, and DMN mapping -- all built-in, no workflow configuration needed.

**OpenRegister advantage:** Pricing model is not constrained -- can model any pricing structure (volume discounts, tiered pricing, subscriptions) via flexible schemas, whereas Open Product is limited to options or DMN rules.
