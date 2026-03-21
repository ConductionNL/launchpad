---
competitor: twenty
analyzed_date: 2026-03-14
feature: Custom Data Model
category: platform
maturity: stable
---

# Custom Data Model

## Summary

Twenty allows full data model customization with custom objects, 18 field types, and relationship management. The API and UI auto-adapt to custom data model changes.

## Key Capabilities

### Custom Objects
- Create objects with singular/plural names, icon, description
- Deactivate/reactivate objects (preserves data)
- Objects appear in navigation, views, API, and workflows automatically
- GraphQL and REST endpoints auto-generated for custom objects

### Field Types (18)
Text, Long Text, Number, Boolean, Currency, Date, Date & Time, Email, Phone, Domain, Address, Links, Array, JSON, Select, Multi-Select, Rating, Relation

### Field Configuration
- Uniqueness constraints
- Default values (select fields, currency, country codes)
- Main display field designation
- Standard fields: cannot delete, can deactivate
- Deactivated fields remain accessible via API

### Relationships
- One-to-many via relation fields
- Many-to-many via junction objects (manual setup)
- Deactivated relations retain existing links

### API Auto-Generation
- Both REST and GraphQL endpoints automatically reflect custom objects and fields
- API playground updates with workspace-specific schema
- GraphQL introspection includes custom entities

## Relevance to Pipelinq

Twenty's data model is more flexible than OpenRegister's schema approach in some ways:
- **UI-first object creation:** No JSON schema required
- **18 field types** vs OpenRegister's JSON Schema-based fields
- **Auto-generated API:** Changes immediately reflected in REST/GraphQL

However, OpenRegister/Pipelinq has advantages:
- **JSON Schema standard:** Interoperable, portable schemas
- **Register-based isolation:** Multi-tenant data separation
- **Validation rules:** JSON Schema validation is more expressive
- **Schema versioning:** Built-in schema evolution support
