---
competitor: krayin
analyzed_date: 2026-03-14
feature: custom-attributes
priority: high
---

# Custom Attributes (EAV System)

## Overview

Krayin implements an Entity-Attribute-Value (EAV) pattern allowing administrators to define custom fields for leads, persons, organizations, products, quotes, and warehouses without schema changes.

## Data Model

### Attribute (`attributes` table)
| Field | Type | Description |
|-------|------|-------------|
| code | string | Machine-readable identifier |
| name | string | Display name |
| type | string | Field type |
| entity_type | string | Target entity (leads, persons, etc.) |
| lookup_type | string | For lookup fields |
| is_required | boolean | Validation flag |
| is_unique | boolean | Uniqueness constraint |
| quick_add | boolean | Show in quick-add forms |
| validation | string | Validation rules |
| is_user_defined | boolean | True for admin-created attributes |

### Supported Field Types

text, textarea, email, phone, price, boolean, select, multiselect, radio, checkbox, date, datetime, image, file, address, lookup

### Attribute Values (`attribute_values` table)
Stores values with typed columns: text_value, boolean_value, integer_value, float_value, datetime_value, date_value, json_value. Links via entity_id + attribute_id.

## CustomAttribute Trait

Applied to: Lead, Person, Organization, Product, Quote, Warehouse. Provides attribute_values relationship and dynamic attribute accessors.

## Pipelinq Comparison Notes

- EAV is the standard pattern for CRM custom fields
- OpenRegister already has a dynamic schema system that serves similar purposes
- The quick_add flag controlling which fields show in compact forms is good UX
- is_user_defined distinguishes system fields from custom ones
- No field grouping/sections or conditional visibility
