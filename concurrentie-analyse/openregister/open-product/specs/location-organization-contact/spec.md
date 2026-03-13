# Location, Organisation & Contact Management

## Summary

Open Product manages physical locations, organisations, and contact persons as shared entities that can be linked to product types. Locations and organisations share a base address model, and contacts are associated with organisations. When a contact is linked to a product type, its organisation is automatically added too.

## Data Model

### BaseLocatie (abstract)
- `naam` -- CharField
- `email` -- EmailField
- `telefoonnummer` -- CharField (validated: `^\+?[0-9\s]+$`)
- `straat` -- CharField
- `huisnummer` -- CharField (max 10)
- `postcode` -- CharField (max 7, validated: `^[1-9][0-9]{3}\s?[a-zA-Z]{2}$`, auto-uppercased and formatted with space)
- `stad` -- CharField
- Computed `address` property: `{straat} {huisnummer}, {postcode} {stad}`

### Locatie (BaseLocatie)
Physical location where products are available.

### Organisatie (BaseLocatie)
Organisation that offers products.
- `code` -- CharField, unique

### Contact (BaseModel)
- `organisatie` -- FK to Organisatie (nullable, SET_NULL)
- `naam` -- CharField (person name, department, etc.)
- `email` -- EmailField
- `telefoonnummer` -- CharField
- `rol` -- CharField (role/function)
- Fallback methods: `get_email()` and `get_phone_number()` fall back to organisation values

## API Endpoints
All under `/producttypen/api/v1/`:
- `locaties` -- CRUD for locations
- `organisaties` -- CRUD for organisations
- `contacten` -- CRUD for contacts

### Filters
- Locatie: naam (contains), uuid
- Organisatie: naam (contains), code (exact), uuid
- Contact: naam (contains), organisatie__uuid, uuid

## ProductType Linking
- ProductType has M2M to all three (locaties, organisaties, contacten)
- Linked via UUID arrays on create/update (`locatie_uuids`, `organisatie_uuids`, `contact_uuids`)
- **Auto-linking**: When a contact is added to a ProductType, the contact's organisation is automatically added to the ProductType's organisations (signal + post-save)

## Already in OpenRegister
- Object properties for address data
- Relations between objects

## Not yet in OpenRegister
- **Shared location/organisation entities** reusable across product types
- **Address formatting and validation** (Dutch postcode format, auto-uppercasing)
- **Contact-to-organisation fallback** for email/phone
- **Automatic organisation linking** when contacts are added
- **Separate entity management** (locations/orgs exist independently, linked to many product types)
