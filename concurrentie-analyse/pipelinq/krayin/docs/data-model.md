# Krayin CRM - Data Model Reference

## Core Entity Relationships

```
Organizations (1) --< Persons (1) --< Leads (N)
                                        |-- belongsTo Pipeline
                                        |-- belongsTo Stage
                                        |-- belongsTo Source
                                        |-- belongsTo Type
                                        |-- belongsTo User (sales owner)
                                        |-- hasMany LeadProducts
                                        |-- belongsToMany Activities
                                        |-- belongsToMany Tags
                                        |-- belongsToMany Quotes
                                        |-- hasMany Emails

Pipeline (1) --< Stages (ordered by sort_order)
                   |-- code: 'new', 'won', 'lost', etc.
                   |-- probability: 0-100%

Quote (1) --< QuoteItems
             |-- belongs to Person
             |-- belongs to User
             |-- billing/shipping address (JSON)

Product (catalog) --< ProductInventory --< Warehouse/Location

Activity --< Participants (User or Person)
          |-- Files (attachments)
          |-- types: call, meeting, lunch, email, note, file

Workflow -- conditions (JSON) + actions (JSON)
         -- triggers on entity events

Campaign --> Event --> EmailTemplate
```

## Key Tables

### Lead Model (`leads` table)
| Field | Type | Description |
|-------|------|-------------|
| title | string | Lead title |
| description | text | Description |
| lead_value | decimal | Monetary value |
| status | int | Status code |
| lost_reason | string | Reason for loss |
| expected_close_date | date | Expected closing date |
| closed_at | datetime | When lead was won/lost |
| user_id | FK | Assigned sales rep |
| person_id | FK | Contact person |
| lead_source_id | FK | Source (web, email, referral, etc.) |
| lead_type_id | FK | Type (new business, existing, etc.) |
| lead_pipeline_id | FK | Pipeline |
| lead_pipeline_stage_id | FK | Current stage |

### Pipeline Model (`lead_pipelines` table)
| Field | Type | Description |
|-------|------|-------------|
| name | string | Pipeline name |
| rotten_days | int | Inactivity threshold |
| is_default | boolean | Default pipeline flag |

### Stage Model (`lead_pipeline_stages` table)
| Field | Type | Description |
|-------|------|-------------|
| code | string | Stage identifier |
| name | string | Display name |
| probability | int | Win probability 0-100% |
| sort_order | int | Kanban column order |
| lead_pipeline_id | FK | Parent pipeline |

### Person Model (`persons` table)
- Custom attributes via EAV
- Emails stored as JSON array with labels
- Phone numbers stored as JSON array with labels

### Organization Model (`organizations` table)
- Custom attributes via EAV
- Linked to persons

### Attribute Value Storage (EAV)
- `attribute_values` table stores all custom field values
- Keyed by entity_type + entity_id + attribute_id
- Supports typed columns for different attribute types
