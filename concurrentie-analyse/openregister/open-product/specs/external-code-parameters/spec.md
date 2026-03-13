# External Codes & Parameters

## Summary

Product types can have external codes (identifiers from other systems) and parameters (key-value metadata). Both are uniquely keyed per product type and support bracket-syntax filtering.

## Data Model

### ExterneCode (BaseModel)
- `naam` -- CharField (system name, e.g., "ISO", "CBS"; regex: no colons/brackets)
- `code` -- CharField (code in external system; regex: no colons/brackets)
- `producttype` -- FK to ProductType (CASCADE)
- Unique together: (producttype, naam)

### Parameter (BaseModel)
- `naam` -- CharField (parameter name; regex: no colons/brackets)
- `waarde` -- CharField (parameter value; regex: no colons/brackets)
- `producttype` -- FK to ProductType (CASCADE)
- Unique together: (producttype, naam)

## API Behavior
- Created/updated inline with ProductType (nested serializer)
- On PUT: entire list replaced
- On PATCH: list only replaced if included in request

## Filtering
Both support bracket-syntax filtering on the ProductType endpoint:
- `?externe_code=[ISO:123]` -- find product types with ISO code 123
- `?parameter=[betalingskenmerk:12345AB]`
- Multiple values supported (AND logic)

## Use Cases
- **External codes**: Map product types across systems (e.g., CBS classification, municipality-specific codes, SDG identifiers)
- **Parameters**: Store type-level attributes applicable to all products of this type (e.g., payment reference patterns, zone identifiers)

## Already in OpenRegister
- Object properties for key-value data
- Custom fields on schemas

## Not yet in OpenRegister
- **Uniquely-keyed external code mapping** per product type
- **Uniquely-keyed parameter storage** per product type
- **Bracket-syntax API filtering** for key-value pairs
- **Character restriction validation** (no colons/brackets to prevent filter syntax conflicts)
