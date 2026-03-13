# Product Instance Lifecycle

## Summary

A Product is an instance of a ProductType -- representing an actual product held by a citizen or organisation (e.g., a specific parking permit for a specific person). Products have a state machine, date-driven automatic transitions, JSON data validated against type-level schemas, and ownership tracking.

## Data Model

### Product (BasePublishableModel)
- `producttype` -- FK to ProductType (PROTECT)
- `naam` -- optional name
- `gepubliceerd` -- boolean (unlike ProductType which computes this from dates)
- `status` -- enum: initieel (default), in_aanvraag, gereed, actief, ingetrokken, geweigerd, verlopen
- `start_datum` / `eind_datum` -- DateField (trigger automatic status transitions)
- `prijs` -- DecimalField (instance-level price)
- `frequentie` -- enum: eenmalig, maandelijks, jaarlijks
- `verbruiksobject` -- JSONField (validated against ProductType.verbruiksobject_schema)
- `dataobject` -- JSONField (validated against ProductType.dataobject_schema)
- `aanvraag_zaak_urn` / `aanvraag_zaak_url` -- reference to originating case

### Child Entities
- **Eigenaar** (owner) -- BSN, KVK nummer, vestigingsnummer, klantnummer; min 1 required per product
- **Document** -- URN/URL reference to external document (Documenten API)
- **Zaak** -- URN/URL reference to external case (Zaken API)
- **Taak** -- URN/URL reference to external task

## State Machine

States: INITIEEL -> IN_AANVRAAG -> GEREED -> ACTIEF -> VERLOPEN | INGETROKKEN | GEWEIGERD

### Status Constraints
- Status can only be set to values listed in `producttype.toegestane_statussen`
- INITIEEL is always allowed (default state)
- Transitions are not path-restricted (any allowed status can be set from any other)

### Automatic Transitions (via `save()`)
- **start_datum reached**: If status is INITIEEL/IN_AANVRAAG/GEREED and start_datum <= today, status auto-sets to ACTIEF
- **eind_datum reached**: If status is INITIEEL/IN_AANVRAAG/GEREED/ACTIEF and eind_datum <= today, status auto-sets to VERLOPEN
- Both transitions create an audit log entry with "Automation" as the acting user

### Date Validation
- Setting `start_datum` requires ACTIEF to be in `toegestane_statussen`
- Setting `eind_datum` requires VERLOPEN to be in `toegestane_statussen`
- `eind_datum` must be after `start_datum`

## Eigenaar (Owner) Validation
- Must have BSN (and/or klantnummer) OR KVK nummer (with optional vestigingsnummer)
- Cannot have both BSN/klantnummer and KVK at the same time
- Vestigingsnummer requires KVK nummer
- BSN validated with 11-check algorithm (9 digits)

## JSON Schema Validation
- `verbruiksobject` validated against `producttype.verbruiksobject_schema` if both are present
- `dataobject` validated against `producttype.dataobject_schema` if both are present

## API
- `GET/POST /producten/api/v1/producten` -- list/create
- `GET/PUT/PATCH/DELETE /producten/api/v1/producten/{uuid}` -- detail CRUD
- Eigenaren are nested with UUID-based upsert on update (include uuid to update, omit to create, absent ones deleted)
- Documenten/Zaken/Taken replaced entirely on PUT/PATCH if included

## Already in OpenRegister
- Schema-validated JSON objects
- CRUD API with UUID lookup
- Object-to-schema relationship

## Not yet in OpenRegister
- **Product lifecycle state machine** with configurable allowed statuses per type
- **Date-driven automatic status transitions** (Celery + save-time checks)
- **BSN/KVK owner identification** with 11-check validation
- **Payment frequency tracking** (eenmalig/maandelijks/jaarlijks)
- **Consumption object / data object split** with separate schema validation
- **Case reference (aanvraag_zaak)** linking product to originating case
- **Nested owner upsert** (UUID-based create/update/delete in single request)
- **Type-based access control** (users can only see products for types they have permission on)
- **Notifications** on product CRUD operations (via Notificaties API)
- **OpenTelemetry metrics** (create/update/delete counters)
