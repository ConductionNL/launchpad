---
competitor: krayin
analyzed_date: 2026-03-14
feature: pipeline-management
priority: critical
---

# Pipeline & Stage Management

## Overview

Krayin implements a multi-pipeline system where each pipeline contains ordered stages with probability percentages. Pipelines serve as the backbone for the Kanban lead view and drive the entire sales workflow.

## Data Model

### Pipeline (`lead_pipelines` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | string | Pipeline name (e.g., "Default Pipeline") |
| rotten_days | int | Days after which idle leads are marked "rotten" |
| is_default | boolean | Only one pipeline can be default |
| created_at | timestamp | |
| updated_at | timestamp | |

### Stage (`lead_pipeline_stages` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| code | string | Unique identifier (e.g., "new", "won", "lost") |
| name | string | Display name |
| probability | int | Win probability 0-100% |
| sort_order | int | Column order in Kanban view |
| lead_pipeline_id | FK | Parent pipeline |

## Business Logic

### Pipeline CRUD
- Creating a pipeline with `is_default=true` clears default from all other pipelines
- Updating pipeline syncs stages: new stages created, existing updated, removed stages cascade -- their leads move to the first stage
- Deleting a pipeline's stage reassigns affected leads before deletion
- The default pipeline is used when creating leads without specifying a pipeline

### Stage Behavior
- Stages with code `won` or `lost` are terminal -- moving a lead there sets `closed_at` timestamp
- Moving a lead out of won/lost clears `closed_at`
- `probability` is metadata only (not used in calculations currently)
- Stages are ordered by `sort_order` ASC for Kanban columns

### Rotten Lead Detection
- Each pipeline defines `rotten_days` (e.g., 30)
- `Lead.getRottenDaysAttribute()` computes: `created_at + rotten_days - now()`
- Positive = rotten (overdue), negative = still fresh
- Won/lost leads never show as rotten
- Used visually in Kanban to highlight stale leads

## Routes

```
GET    /settings/pipelines           -- List all pipelines
GET    /settings/pipelines/create    -- Create form
POST   /settings/pipelines/create    -- Store
GET    /settings/pipelines/edit/{id} -- Edit form
POST   /settings/pipelines/edit/{id} -- Update (POST, not PUT)
DELETE /settings/pipelines/{id}      -- Delete
```

## Key Files

- `packages/Webkul/Lead/src/Models/Pipeline.php`
- `packages/Webkul/Lead/src/Models/Stage.php`
- `packages/Webkul/Lead/src/Repositories/PipelineRepository.php`
- `packages/Webkul/Lead/src/Repositories/StageRepository.php`
- `packages/Webkul/Admin/src/Http/Controllers/Settings/PipelineController.php`

## Pipelinq Comparison Notes

- Krayin pipelines are simpler (name + rotten_days only) vs Pipelinq's richer pipeline metadata
- Rotten lead detection is a useful feature Pipelinq could adopt
- Stage probability percentages exist but are unused in forecasting -- opportunity for Pipelinq
- Pipeline update with stage cascade (reassign leads when deleting stages) is well-handled
- No pipeline templates or cloning capability
- No pipeline-level permissions (all users see all pipelines)
