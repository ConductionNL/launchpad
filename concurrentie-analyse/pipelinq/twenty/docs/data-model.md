# Twenty CRM - Data Model

**Analyzed:** 2026-03-14

## Standard Objects

| Object | Purpose |
|--------|---------|
| **People** | Contacts with details and interaction history |
| **Companies** | Business accounts with industry, size, location; linked to People and Opportunities |
| **Opportunities** | Sales pipeline tracking with stages, deal values, expected close dates; kanban visualization |
| **Notes** | Free-form information attachable to multiple object types |
| **Tasks** | Action items with due dates, assignees, completion tracking |

## Custom Objects

Users can create custom objects for organization-specific entities via Settings > Data Model > New Object.

**Creation requirements:**
- Singular and plural names (e.g., "listing"/"listings")
- Icon selection
- Description field

**When to use custom objects vs. fields:**
- Custom objects: unique business entities, complex multi-entity relationships, scalable data
- Fields: simple attributes, categories/labels, single values without independent lifecycles

**Object management:**
- Objects can be deactivated (preserves data, hides from UI)
- Deactivated objects can be reactivated at any time

## Field Types (18 types)

| Type | Description |
|------|-------------|
| Address | Structured: street, city, state, country, postal code |
| Array | List of text values |
| Boolean | True/false checkbox |
| Currency | Monetary values with currency designation |
| Date | Calendar date |
| Date & Time | Combined date and timestamp |
| Domain | Website domains |
| Email | Email addresses (primary + additional) |
| JSON | Structured data in JSON format |
| Links | URLs with labels (primary + secondary) |
| Long Text | Multi-line text |
| Multi-Select | Multiple choices from predefined list |
| Number | Integers or decimals |
| Phone | Phone numbers with country code |
| Rating | Star ratings (1-5) |
| Relation | Links between object records |
| Select | Single choice from options |
| Text | Single-line text |

## Field Configuration

- **Uniqueness constraints** can be enforced (duplicates including soft-deleted records prevent activation)
- **Default values** configurable for select fields, currency, country codes
- **Main display field:** One text field per object appears in leftmost column
- **Standard fields** cannot be deleted but can be deactivated
- **Deactivated fields** remain accessible via API
- **Field names** must have different singular/plural forms (GraphQL requirement)

## Relationships

- **One-to-many:** Via relation fields
- **Many-to-many:** Via junction objects (manually created custom objects linking two other objects)
- Deactivated relation fields retain existing connections but prevent new ones
