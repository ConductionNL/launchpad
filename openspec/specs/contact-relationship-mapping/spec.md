# Contact Relationship Mapping Specification (Cross-App)

## Purpose

Model bidirectional typed relationships between contacts, organizations, and entities across all Conduction apps. Auto-create inverse relationships. For government: family relationships for social domain, company structures for permits, organizational hierarchies for case management. For CRM: employer/employee, colleague, partner relationships to understand networks.

This capability is consumed by multiple apps: Pipelinq uses it for CRM relationship management, Procest uses it for case participant relationships (klager, vergunninghouder, belanghebbende), and OpenRegister provides the storage and query layer for relationship objects.

**Consuming apps**: Pipelinq (CRM contacts), Procest (case participants), OpenRegister (storage layer)
**Tender frequency**: 83% require klantbeeld-360 (relationships are a key component); 65% require klantinteractie/CRM
**Standards**: VNG Klantinteracties `PartijRelatie`, Schema.org `Person.knows`/`Person.relatedTo`, Haal Centraal BRP API (family relationships), Common Ground

---

## Requirements

### Requirement 1: Relationship entity with bidirectional linking

The system MUST provide a Relationship entity connecting two entities with a typed, bidirectional link. Inverse relationships MUST be auto-created.

#### Scenario 1.1: Create a partner relationship
- GIVEN contacts "Jan Bakker" and "Maria Bakker"
- WHEN the user creates a relationship of type "partner" between them
- THEN a relationship record MUST be created from Jan to Maria with type "partner"
- AND an inverse relationship MUST be automatically created from Maria to Jan with type "partner"
- AND both contacts' detail views MUST show the relationship

#### Scenario 1.2: Parent-child relationship with inverse
- GIVEN contacts "Pieter de Vries" (parent) and "Sophie de Vries" (child)
- WHEN the user creates a relationship of type "ouder" from Pieter to Sophie
- THEN the inverse relationship "kind" MUST be automatically created from Sophie to Pieter
- AND Pieter's detail view MUST show "Sophie de Vries -- kind"
- AND Sophie's detail view MUST show "Pieter de Vries -- ouder"

#### Scenario 1.3: Employer-employee relationship
- GIVEN contact (organization) "Gemeente Utrecht" and contact (person) "Jan Bakker"
- WHEN the user creates relationship "werkgever" from Gemeente Utrecht to Jan
- THEN the inverse "werknemer" MUST be created from Jan to Gemeente Utrecht
- AND Jan's detail view MUST show his employer

#### Scenario 1.4: Case participant relationship in Procest
- GIVEN case "Bouwvergunning #2024-001" in Procest and contact "Architectenbureau BV"
- WHEN a case handler adds "Architectenbureau BV" as a participant with role "gemachtigde"
- THEN a relationship MUST be created between the case and the organization
- AND the case detail MUST show the participant with role label
- AND the organization's cross-app timeline MUST show the case linkage

#### Scenario 1.5: Relationship between organizations (parent/subsidiary)
- GIVEN organizations "Holding BV" and "Dochter BV"
- WHEN the user creates relationship "moederorganisatie" from Holding to Dochter
- THEN the inverse "dochterorganisatie" MUST be created from Dochter to Holding
- AND organizational hierarchy MUST be traversable via the relationships

---

### Requirement 2: Configurable relationship types with defined inverses

The system MUST provide configurable relationship types with defined inverse labels, organized by category.

#### Scenario 2.1: Default relationship types
- GIVEN the system is freshly installed
- THEN the following relationship types MUST be available:

| Type | Inverse | Category |
|------|---------|----------|
| partner | partner | Familie |
| ouder | kind | Familie |
| kind | ouder | Familie |
| broer/zus | broer/zus | Familie |
| werkgever | werknemer | Professioneel |
| werknemer | werkgever | Professioneel |
| collega | collega | Professioneel |
| contactpersoon | organisatie | Professioneel |
| moederorganisatie | dochterorganisatie | Organisatie |
| dochterorganisatie | moederorganisatie | Organisatie |
| gemachtigde | opdrachtgever | Juridisch |
| opdrachtgever | gemachtigde | Juridisch |

#### Scenario 2.2: Custom relationship types
- GIVEN an admin user
- WHEN they create a new relationship type "mentor" with inverse "mentee" in category "Professioneel"
- THEN the type MUST be available when creating relationships in all consuming apps
- AND both the type and its inverse MUST appear in the type picker

#### Scenario 2.3: App-specific relationship categories
- GIVEN Procest has case-specific relationship types (aanvrager, belanghebbende, gemachtigde)
- WHEN a Procest admin configures these types
- THEN the types MUST be available in Procest's case participant views
- AND the types MUST also be queryable via the shared OpenRegister relationship schema

---

### Requirement 3: Relationship management on entity detail views

Entity detail views in all consuming apps MUST display and manage relationships.

#### Scenario 3.1: View relationships on contact detail
- GIVEN contact "Jan Bakker" with relationships: partner (Maria), werkgever (Gemeente Utrecht), collega (Pieter)
- WHEN the user views Jan's detail page in Pipelinq
- THEN a "Relaties" section MUST display all relationships grouped by category (Familie, Professioneel, Organisatie)
- AND each relationship MUST show: entity name, relationship type, and a link to the related entity

#### Scenario 3.2: Add relationship from detail view
- GIVEN the entity detail view for any entity in any consuming app
- WHEN the user clicks "Relatie toevoegen"
- THEN a dialog MUST appear with: entity search (across registers), relationship type selector
- AND selecting an entity and type MUST create both the relationship and its inverse

#### Scenario 3.3: Remove relationship with cascade
- GIVEN a relationship between Jan and Maria
- WHEN the user removes the relationship from Jan's detail view
- THEN both the relationship AND its inverse MUST be deleted
- AND Maria's detail view MUST no longer show the relationship to Jan

#### Scenario 3.4: View case participants in Procest
- GIVEN case "Omgevingsvergunning #2024-001" with 3 participants (aanvrager, gemachtigde, belanghebbende)
- WHEN the case handler views the case detail in Procest
- THEN a "Betrokkenen" section MUST display all participants with their roles
- AND each participant MUST be linked to their entity (click to navigate)

#### Scenario 3.5: Relationship count badge on entity cards
- GIVEN an entity list view showing contacts
- THEN each contact card SHOULD display a relationship count badge (e.g., "5 relaties")
- AND hovering over the badge SHOULD show a summary tooltip

---

### Requirement 4: Relationship search and filtering

The system MUST support searching entities by their relationships across apps.

#### Scenario 4.1: Find all employees of an organization
- GIVEN organization "Gemeente Utrecht" with 5 employee relationships
- WHEN the user searches for entities with relationship "werknemer" of "Gemeente Utrecht"
- THEN all 5 employees MUST be returned

#### Scenario 4.2: Filter entities by relationship existence
- GIVEN an entity list in any consuming app
- WHEN the user filters by "heeft relatie: werkgever"
- THEN only entities with an active employer relationship MUST be shown

#### Scenario 4.3: Find all cases linked to a person
- GIVEN person "Jan de Vries" is a participant in 3 cases across Procest
- WHEN the user queries relationships of type "aanvrager" or "belanghebbende" for Jan
- THEN all 3 cases MUST be returned with their roles

---

### Requirement 5: Relationship data model (OpenRegister)

Relationships MUST be stored as OpenRegister objects in a shared schema accessible to all consuming apps.

#### Scenario 5.1: Relationship object structure
- GIVEN a relationship between two entities
- THEN the object MUST store:
  - `fromEntity`: UUID reference to the source entity
  - `fromEntityType`: entity type identifier (contact, organization, case, etc.)
  - `toEntity`: UUID reference to the target entity
  - `toEntityType`: entity type identifier
  - `type`: relationship type identifier
  - `inverseType`: the inverse relationship type identifier
  - `notes`: optional free text
  - `startDate`: optional date when relationship started
  - `endDate`: optional date when relationship ended
  - `sourceApp`: originating app (pipelinq, procest, etc.)

#### Scenario 5.2: Relationship API queryable by entity
- GIVEN entity "contact-1" has 10 relationships
- WHEN `GET /api/openregister/objects/{registerId}/{schemaId}?fromEntity={contact-1-uuid}` is called
- THEN all 10 relationships MUST be returned
- AND filtering by type MUST be supported

#### Scenario 5.3: Cascade delete protection
- GIVEN entity "Jan de Vries" has 5 relationships
- WHEN "Jan de Vries" is deleted
- THEN all 5 relationships AND their inverses MUST be deleted
- AND a warning dialog MUST show "Dit contact heeft 5 relaties die ook verwijderd worden"

---

### Requirement 6: BRP integration for family relationships

The system MUST support importing family relationships from BRP (Basisregistratie Personen) data.

#### Scenario 6.1: Import partner from BRP
- GIVEN a client with BSN "999995376" and BRP data showing partner "Jean Roussaex"
- WHEN the system processes BRP enrichment data
- THEN a partner relationship MUST be suggested (not auto-created without confirmation)
- AND the user MUST confirm before the relationship is created

#### Scenario 6.2: Import parent-child from BRP
- GIVEN a client with BSN "999990627" and BRP data showing 2 children
- WHEN the system processes BRP enrichment data
- THEN parent-child relationships MUST be suggested for both children
- AND the system MUST check if the children already exist as contacts before creating duplicates

#### Scenario 6.3: BRP relationship data shown in klantbeeld
- GIVEN BRP data shows family relationships for an identified citizen
- WHEN the klantbeeld-360 view is displayed
- THEN family relationships from BRP MUST be shown in a "Familie (BRP)" section
- AND relationships that also exist as Pipelinq/Procest relationships MUST be marked as "Geverifieerd"

---

### Requirement 7: Relationship visualization

The system SHOULD support visual representation of relationship networks.

#### Scenario 7.1: Family tree view
- GIVEN a contact with partner and 3 children relationships
- WHEN the user clicks "Toon familieboom"
- THEN a simple tree diagram MUST display the family structure
- AND each node MUST be clickable to navigate to the entity

#### Scenario 7.2: Organization chart view
- GIVEN an organization with parent/subsidiary and employee relationships
- WHEN the user clicks "Toon organogram"
- THEN a hierarchical chart MUST display the organizational structure
- AND each node MUST show entity name and relationship type

#### Scenario 7.3: Network graph for complex relationships
- GIVEN a contact with 15+ relationships across multiple categories
- WHEN the user clicks "Toon netwerk"
- THEN an interactive network graph MUST display all relationships
- AND nodes MUST be color-coded by entity type and edges by relationship category

---

### Requirement 8: Relationship permissions and audit

Relationship creation and deletion MUST be auditable and respect entity-level permissions.

#### Scenario 8.1: Audit trail for relationship changes
- GIVEN a relationship is created between Jan and Maria
- THEN an audit entry MUST be recorded with: who created it, when, relationship type, and both entity references

#### Scenario 8.2: Relationship respects entity visibility
- GIVEN user A has access to contact "Jan" but not to contact "Maria"
- WHEN user A views Jan's relationships
- THEN the relationship to Maria MUST show the relationship type but mask Maria's details as "Beperkt zichtbaar"

#### Scenario 8.3: AVG-compliant relationship access
- GIVEN family relationship data from BRP
- WHEN an agent views these relationships
- THEN access MUST be logged in the doelbinding audit trail
- AND the access purpose MUST be recorded

---

### Requirement 9: Temporal relationships with date ranges

Relationships MUST support start and end dates for time-bounded associations.

#### Scenario 9.1: Employment with start date
- GIVEN Jan Bakker started working at Gemeente Utrecht on 2024-01-15
- WHEN the employer relationship is created with startDate "2024-01-15"
- THEN the relationship MUST show "Werknemer sinds 15 januari 2024"

#### Scenario 9.2: Ended relationship
- GIVEN Jan Bakker left Gemeente Utrecht on 2025-12-31
- WHEN the relationship is updated with endDate "2025-12-31"
- THEN the relationship MUST show "Werknemer (15-01-2024 t/m 31-12-2025)"
- AND the relationship MUST be filtered out of "active relationships" by default
- AND a "Toon beeindigd" toggle MUST reveal ended relationships

#### Scenario 9.3: Relationship duration calculation
- GIVEN an active employment relationship started 18 months ago
- THEN the relationship detail MUST show calculated duration "1 jaar, 6 maanden"

---

### Requirement 10: Relationship import and export

The system MUST support importing and exporting relationships for data migration and compliance.

#### Scenario 10.1: Export relationships as CSV
- GIVEN an entity with 20 relationships
- WHEN the user exports relationships
- THEN a CSV file MUST be generated with columns: Van, Naar, Type, Categorie, Startdatum, Einddatum, Notities

#### Scenario 10.2: Import relationships from CSV
- GIVEN a CSV file with relationship data from a legacy system
- WHEN an admin uploads the CSV and maps columns
- THEN relationships MUST be created with validation (both entities must exist)
- AND a report MUST show successful imports and failures with reasons

#### Scenario 10.3: VNG PartijRelatie export
- GIVEN relationships between government entities
- WHEN the system exports in VNG format
- THEN the export MUST conform to the VNG Klantinteracties `PartijRelatie` schema

---

## Data Model

### Relationship Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `fromEntity` | string (uuid) | YES | UUID of the source entity |
| `fromEntityType` | string | YES | Type identifier (contact, organization, case, etc.) |
| `toEntity` | string (uuid) | YES | UUID of the target entity |
| `toEntityType` | string | YES | Type identifier |
| `type` | string | YES | Relationship type identifier |
| `inverseType` | string | YES | Inverse relationship type identifier |
| `category` | string | no | Category: Familie, Professioneel, Organisatie, Juridisch |
| `notes` | string | no | Optional free text |
| `startDate` | date | no | When relationship started |
| `endDate` | date | no | When relationship ended |
| `sourceApp` | string | YES | Originating app identifier |

### Relationship Type Configuration

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `type` | string | YES | Relationship type identifier |
| `label` | string | YES | Human-readable label (translatable) |
| `inverseType` | string | YES | Identifier of the inverse type |
| `inverseLabel` | string | YES | Human-readable inverse label |
| `category` | string | YES | Category grouping |
| `applicableEntityTypes` | array | no | Which entity types this relationship can connect |

---

## Dependencies

- OpenRegister (relationship storage and query API)
- Pipelinq contact entities (for CRM relationships)
- Procest case entities (for case participant relationships)
- BRP mock register / Haal Centraal API (for family relationship data)
- KVK register (for company structure data)

## Standards & References

- VNG Klantinteracties `PartijRelatie` -- relationship concepts between `Partij` entities
- Schema.org `Person.knows`, `Person.relatedTo`, `Organization.member` -- relationship predicates
- Common Ground -- relationship modeling between subjects
- Haal Centraal BRP API -- family relationship data (partner, kinderen, ouders)
- AVG -- privacy requirements for relationship data access
