---
competitor: krayin
analyzed_date: 2026-03-14
feature: leads
priority: critical
---

# Lead Management

## Overview

Leads are the central entity in Krayin CRM. Each lead moves through pipeline stages, is assigned to a sales person, linked to a contact person, and can have products, quotes, activities, emails, and tags attached. Leads support both Kanban (board) and DataGrid (table) views with full filtering.

## Data Model

### Lead (`leads` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| title | string | Lead title |
| description | text | Description |
| lead_value | decimal | Monetary value |
| status | int | 1 = active |
| lost_reason | text | Reason when moved to "lost" stage |
| expected_close_date | date | Expected closing date |
| closed_at | datetime | Auto-set when won/lost |
| user_id | FK | Assigned sales person |
| person_id | FK | Contact person |
| lead_source_id | FK | Source (web, email, phone, etc.) |
| lead_type_id | FK | Type (new business, existing) |
| lead_pipeline_id | FK | Pipeline |
| lead_pipeline_stage_id | FK | Current stage |

### Lead Products (`lead_products` table)
| Field | Type | Description |
|-------|------|-------------|
| lead_id | FK | Lead |
| product_id | FK | Product from catalog |
| quantity | int | Quantity |
| price | decimal | Unit price |
| amount | decimal | Computed: price x quantity |

### Pivot Tables
- `lead_activities` -- M:N with activities
- `lead_tags` -- M:N with tags
- `lead_quotes` -- M:N with quotes

## Business Logic

### Lead Creation
1. If `person` data provided, creates or links existing person (lookup by ID)
2. If no pipeline specified, uses default pipeline
3. If no stage specified, uses first stage of pipeline
4. Sets `status = 1` (active)
5. If stage code is 'won' or 'lost', sets `closed_at = now()`
6. Saves custom attribute values via EAV system
7. Creates lead_products if products provided
8. Dispatches `lead.create.before` and `lead.create.after` events

### Lead Update
- Person can be created/linked during update
- Stage change to won/lost auto-sets `closed_at`; moving away clears it
- Product management: new products added, existing updated, removed ones deleted
- Supports partial attribute updates (e.g., stage-only update from Kanban drag)

### Kanban View
- Groups leads by pipeline stages (columns)
- Each column shows aggregate `lead_value` sum
- Paginated (10 leads per stage per page) with infinite scroll
- Supports filtering by: ID, lead_value, sales person, contact, lead type, source, tags
- Stage transitions via `updateStage()` endpoint
- Rotten days calculation highlights stale leads

### Table View (DataGrid)
- Full-featured data grid with sorting, filtering, pagination
- Supports saved filters
- Mass operations: update stage, delete

### Authorization
- `bouncer()->getAuthorizedUserIds()` restricts lead visibility based on user's role
- Leads can only be viewed if the current user is authorized for the lead's `user_id`

## Routes

```
GET    /leads                    -- Index (table/kanban)
GET    /leads/create             -- Create form
POST   /leads/create             -- Store
POST   /leads/create-by-ai       -- AI-based creation from file uploads
GET    /leads/view/{id}          -- Detail view
GET    /leads/edit/{id}          -- Edit form
PUT    /leads/edit/{id}          -- Update
PUT    /leads/attributes/edit/{id} -- Update specific attributes
PUT    /leads/stage/edit/{id}    -- Update stage (Kanban drag)
GET    /leads/search             -- Search/autocomplete
DELETE /leads/{id}               -- Delete
POST   /leads/mass-update        -- Mass stage update
POST   /leads/mass-destroy       -- Mass delete
GET    /leads/get/{pipeline_id?} -- Kanban data API
PUT    /leads/product/{lead_id}  -- Attach product
DELETE /leads/product/{lead_id}  -- Remove product
GET    /leads/kanban/look-up     -- Kanban filter lookups
```

## Key Files

- `packages/Webkul/Lead/src/Models/Lead.php`
- `packages/Webkul/Lead/src/Repositories/LeadRepository.php`
- `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`
- `packages/Webkul/Admin/src/DataGrids/Lead/LeadDataGrid.php`
- `packages/Webkul/Admin/src/Http/Resources/LeadResource.php`

## Pipelinq Comparison Notes

- Krayin's Kanban implementation is production-ready with pagination, filtering, and rotten lead detection
- Lead-product relationship (quantity/price/amount per lead) is useful for deal value tracking
- The "create-by-AI" feature (upload PDF/image, extract lead data) is innovative but basic
- No pipeline stage history/audit trail -- only current stage is tracked
- No lead scoring or weighted pipeline forecasting
- Lost reason tracking on stage change is a good UX pattern
- Mass stage updates work but dispatch events individually (potential performance issue)
