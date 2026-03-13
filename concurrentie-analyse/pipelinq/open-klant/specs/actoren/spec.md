---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Actoren (Municipal Actors) -- Open Klant

## Purpose

An Actor represents anyone or anything that performs work for the municipality in the context of client interactions. It uses a polymorphic pattern with three subtypes: Medewerker (employee), GeautomatiseerdeActor (automated system), and OrganisatorischeEenheid (organisational unit).

- **Product**: Open Klant
- **Category**: Actor/Employee Management
- **Relevance to Pipelinq**: Represents the municipality side of client interactions -- who handled the contact.

## Data Model

### Actor (base)

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| naam | CharField(200) | Actor name |
| soort_actor | CharField(24) | `medewerker` / `geautomatiseerde_actor` / `organisatorische_eenheid` |
| indicatie_actief | BooleanField (default True) | Whether actor may still be involved in new contacts |
| actoridentificator | GegevensGroepType | External register identifier (object_id, code_objecttype, code_register, code_soort_object_id) |

### Medewerker (1:1 -> Actor)

| Field | Type | Description |
|-------|------|-------------|
| functie | CharField(40) | Job function |
| emailadres | EmailField | |
| telefoonnummer | CharField(20) | Validated phone number |

### GeautomatiseerdeActor (1:1 -> Actor)

| Field | Type | Description |
|-------|------|-------------|
| functie | CharField(40) | Function description |
| omschrijving | CharField(200) | Description |

### OrganisatorischeEenheid (1:1 -> Actor)

| Field | Type | Description |
|-------|------|-------------|
| omschrijving | CharField(200) | |
| emailadres | EmailField | |
| faxnummer | CharField(20) | |
| telefoonnummer | CharField(20) | |

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET/POST | `/klantinteracties/api/v1/actoren/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/actoren/{uuid}/` | Detail CRUD | Token |

### Filters

- `naam` (exact)
- `soort_actor`
- `indicatie_actief`
- `actoridentificator_*` fields

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Employee tracking | Medewerker subtype with function/contact info | Nextcloud users, no structured actor model |
| Automated systems | GeautomatiseerdeActor subtype | n8n workflows (different paradigm) |
| Org units | OrganisatorischeEenheid subtype | Not available |
| Active flag | indicatie_actief | User enabled/disabled in Nextcloud |
| External ID | actoridentificator for BRP/register linking | Not available |

**Already in Pipelinq**: Nextcloud user management (basic)
**Not yet in Pipelinq**: Structured actor model with polymorphic types, external register identifiers for actors, active/inactive tracking for contact assignment
