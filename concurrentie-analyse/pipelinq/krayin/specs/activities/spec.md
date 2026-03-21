---
competitor: krayin
analyzed_date: 2026-03-14
feature: activities
priority: high
---

# Activity Tracking

## Overview

Activities in Krayin represent any interaction or task: calls, meetings, lunches, emails, notes, and file uploads. Activities can be linked to leads, persons, products, and warehouses via pivot tables. The system includes scheduling (from/to dates), completion tracking, participant management, and file attachments.

## Data Model

### Activity (`activities` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| title | string | Activity title |
| type | string | call, meeting, lunch, email, note, file |
| location | string | Meeting location |
| comment | text | Notes/description |
| additional | text | Additional data |
| schedule_from | datetime | Start time |
| schedule_to | datetime | End time |
| is_done | boolean | Completion flag |
| user_id | FK | Activity owner |

### Participants (`activity_participants` table)
| Field | Type | Description |
|-------|------|-------------|
| activity_id | FK | Activity |
| user_id | FK | Participant (nullable) |
| person_id | FK | External participant (nullable) |

### Files (`activity_files` table)
- File attachments per activity
- Supports download endpoint

### Pivot Tables
- `lead_activities` -- Activity linked to leads
- `person_activities` -- Activity linked to persons
- `product_activities` -- Activity linked to products
- `warehouse_activities` -- Activity linked to warehouses

## Business Logic

### LogsActivity Trait
- Used by Lead, Person, Product, Warehouse models
- Enables automatic activity logging on these entities
- Activities are polymorphically linked via pivot tables

### Activity Types
1. **call** -- Phone call log
2. **meeting** -- Scheduled meeting with location
3. **lunch** -- Business lunch
4. **email** -- Email activity
5. **note** -- Text note
6. **file** -- File upload

### Calendar View
- Frontend uses `vue-cal` plugin for calendar visualization
- Activities with schedule_from/to rendered on calendar
- Supports day/week/month views

## Routes

```
GET    /activities              -- Index (DataGrid + Calendar)
GET    /activities/get          -- Calendar data API
POST   /activities/create       -- Store
GET    /activities/edit/{id}    -- Edit form
PUT    /activities/edit/{id}    -- Update
GET    /activities/download/{id} -- Download attachment
DELETE /activities/{id}         -- Delete
POST   /activities/mass-update  -- Mass update
POST   /activities/mass-destroy -- Mass delete
```

## Pipelinq Comparison Notes

- Activity system is well-structured with clear types and scheduling
- Participant model (internal users + external persons) is a good pattern
- Calendar integration via vue-cal is basic but functional -- no CalDAV sync
- The polymorphic linking via multiple pivot tables works but creates many join paths
- No recurring activities or templates
- No activity reminders/notifications built-in
