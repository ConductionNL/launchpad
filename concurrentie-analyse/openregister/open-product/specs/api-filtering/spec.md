# API Filtering

## Summary

Open Product provides extensive filtering capabilities on both ProductType and Product endpoints using django-filter. Filters support exact match, contains, range comparisons, array overlap, JSON attribute queries, and cross-relation filtering.

## ProductType Filters

### Basic
- `code` -- exact
- `naam` -- exact (NL translation)
- `letter` -- starts with (NL translation)
- `doelgroep` -- exact
- `uniforme_product_naam` -- exact (by naam)
- `keywords` -- overlap (array)
- `toegestane_statussen` -- overlap (array)
- `gepubliceerd` -- computed boolean (date-range based)
- `publicatie_start_datum` / `publicatie_eind_datum` -- exact, gte, lte
- `aanmaak_datum` / `update_datum` -- exact, gte, lte

### Relation Filters
- `themas__naam` -- exact
- `themas__naam__in` -- array
- `themas__uuid` / `themas__uuid__in`
- `contacten__naam__contains` / `contacten__uuid__in`
- `locaties__naam__contains` / `locaties__uuid__in`
- `organisaties__naam__contains` / `organisaties__uuid__in` / `organisaties__code`
- `zaaktypen__urn` / `zaaktypen__url` -- exact, contains
- `verzoektypen__urn` / `verzoektypen__url` -- exact, contains
- `processen__urn` / `processen__url` -- exact, contains

### Special Filters
- `externe_code` -- format `[naam:code]`, supports multiple values
- `parameter` -- format `[naam:waarde]`, supports multiple values
- `verbruiksobject_schema__naam` -- exact

## Product Filters

### Basic
- `naam` -- exact
- `status` -- exact
- `gepubliceerd` -- exact
- `frequentie` -- exact
- `prijs` -- exact, gte, lte
- `start_datum` / `eind_datum` -- exact, gte, lte
- `aanmaak_datum` / `update_datum` -- exact, gte, lte

### Owner Filters
- `eigenaren__bsn` -- exact
- `eigenaren__kvk_nummer` -- exact
- `eigenaren__vestigingsnummer` -- exact
- `eigenaren__klantnummer` -- exact
- `eigenaren__uuid` -- exact

### ProductType Cross-Filters
- `producttype__code` -- exact, in
- `producttype__uuid` -- exact, in
- `producttype__naam` / `producttype__naam__in` (NL translation)
- `producttype__themas__naam` / `producttype__themas__uuid`
- `producttype__organisaties__code` / `producttype__organisaties__uuid`
- `producttype__locaties__uuid`
- `producttype__gepubliceerd` -- computed boolean
- `uniforme_product_naam` -- cross-relation to UPL

### JSON Attribute Filters
- `dataobject_attr` -- `key__operator__value` format, supports nested keys
- `verbruiksobject_attr` -- same format
- Operators: exact, icontains, gt, gte, lt, lte, in, isnull
- Multiple filters combined via repeated query params

### External Reference Filters
- `aanvraag_zaak_urn` / `aanvraag_zaak_url` -- exact, contains
- `documenten__urn` / `documenten__url` -- exact, contains
- `zaken__urn` / `zaken__url` -- exact, contains
- `taken__urn` / `taken__url` -- exact, contains

## Already in OpenRegister
- Basic filtering on object properties
- Search across registers and schemas

## Not yet in OpenRegister
- **JSON attribute filtering** with operator syntax (key__operator__value)
- **Array overlap filtering** (keywords, statussen)
- **Computed property filtering** (gepubliceerd from date range)
- **Key-value pair filtering** with bracket syntax (`[naam:code]`)
- **Cross-relation filtering** through FK chains (producttype__themas__naam)
- **Translation-aware filtering** (queries NL translation table)
