---
competitor: krayin
analyzed_date: 2026-03-14
feature: contacts
priority: high
---

# Contact Management (Persons + Organizations)

## Overview

Krayin separates contacts into two entities: **Persons** (individuals) and **Organizations** (companies). A person optionally belongs to one organization. Persons are linked to leads as the primary contact.

## Data Model

### Person (`persons` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | string | Full name |
| emails | JSON | Array of {value, label} objects |
| contact_numbers | JSON | Array of {value, label} objects |
| job_title | string | Job title |
| organization_id | FK | Organization (nullable) |
| user_id | FK | Assigned sales user |
| unique_id | string | Unique external identifier |
| created_at / updated_at | timestamps | |

### Organization (`organizations` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | string | Organization name (unique index) |
| address | JSON | Structured address object |
| user_id | FK | Assigned user |

## Key Design Decisions

### Multi-value Contact Fields
- Emails and phone numbers stored as JSON arrays: `[{"value": "john@acme.com", "label": "work"}, ...]`
- Labels allow categorization: work, home, mobile, etc.
- Person factory generates test data with this structure

### Custom Attributes (EAV)
- Both Person and Organization use `CustomAttribute` trait
- Allows admin-defined fields beyond the base schema
- Entity types: `persons` and `organizations`

### Activity Logging
- Person uses `LogsActivity` trait for automatic activity tracking
- Activities linked via `person_activities` pivot table
- Tags linked via `person_tags` pivot table

### Organization-Person Relationship
- One organization has many persons
- Organization `name` has a unique index
- Person eagerly loads organization (`protected $with = 'organization'`)

## Routes

### Persons
```
GET    /contacts/persons              -- List
GET    /contacts/persons/create       -- Create form
POST   /contacts/persons/create       -- Store
GET    /contacts/persons/view/{id}    -- Detail view
GET    /contacts/persons/edit/{id}    -- Edit form
PUT    /contacts/persons/edit/{id}    -- Update
GET    /contacts/persons/search       -- Autocomplete search
DELETE /contacts/persons/{id}         -- Delete (rate-limited: 100/60s)
POST   /contacts/persons/mass-destroy -- Mass delete
POST   /contacts/persons/{id}/tags    -- Attach tag
DELETE /contacts/persons/{id}/tags    -- Detach tag
GET    /contacts/persons/{id}/activities -- List activities
```

### Organizations
```
GET    /contacts/organizations           -- List
GET    /contacts/organizations/create    -- Create form
POST   /contacts/organizations/create    -- Store
GET    /contacts/organizations/edit/{id} -- Edit form
PUT    /contacts/organizations/edit/{id} -- Update
DELETE /contacts/organizations/{id}      -- Delete
PUT    /contacts/organizations/mass-destroy -- Mass delete
```

## Pipelinq Comparison Notes

- Simple but effective contact model -- person-to-organization is the right abstraction
- JSON multi-value fields for emails/phones is pragmatic (avoids separate tables)
- No contact deduplication logic beyond unique organization name
- No contact import from external sources (LinkedIn, etc.)
- Person delete is rate-limited (100/60s) -- good abuse prevention pattern
- The `unique_id` field on persons suggests external system integration capability
