---
competitor: twenty
analyzed_date: 2026-03-14
feature: Pipeline Management
category: core-crm
maturity: stable
---

# Pipeline Management

## Summary

Twenty provides visual pipeline management via Kanban board views on the Opportunities object. Deals move through configurable stages with drag-and-drop, weighted value calculations, and stage velocity tracking.

## Key Capabilities

### Kanban Pipeline View
- Cards represent individual opportunities/deals
- Columns represent stages (configurable via Select field in data model settings)
- Drag-and-drop between stages
- Compact view mode (titles only) for high-level overview
- Recommended limit: 5-7 stages

### Column Aggregations
- Count (number of records)
- Sum (total of numeric field)
- Average (mean of numeric field)
- Min/Max values
- Configurable per column

### Deal Tracking
- Expected amount display with weighted values based on stage probability
- Stage duration tracking (deal velocity monitoring)
- Close date tracking
- Stage history

### View Configuration
- Show/hide specific fields on cards
- Reorder fields via drag
- Filter and sort records
- Named views with icons
- Access control on views (restrict visibility)

## Data Model

The pipeline is built on the standard **Opportunities** object with fields:
- Stage (Select field powering Kanban columns)
- Deal value / expected amount
- Expected close date
- Linked Company
- Linked People (contacts)

Stages are managed in Settings > Data Model by editing the Select field options.

## Relevance to Pipelinq

Twenty's pipeline approach is simpler than a dedicated pipeline/workflow tool:
- **Fixed to Opportunities:** Kanban is tied to a single object's Select field, not a generic pipeline engine
- **No multi-object pipelines:** Cannot create pipelines spanning multiple object types
- **No pipeline templates:** Each pipeline is manually configured
- **No conditional stage transitions:** Drag-and-drop is unconditional; workflows must handle validation
- **Limited stage actions:** No built-in stage-entry/exit actions (must use workflow triggers)

Pipelinq could differentiate by offering:
- Generic pipeline definitions applicable to any object type
- Conditional transitions with validation rules
- Stage-entry/exit hooks with automatic actions
- Pipeline templates for common use cases
- Multi-lane / parallel stage support
- SLA tracking per stage
