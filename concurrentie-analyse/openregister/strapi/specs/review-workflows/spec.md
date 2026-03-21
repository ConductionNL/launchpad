---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Review Workflows

## Overview

Strapi's Review Workflows (`@strapi/review-workflows`) is an Enterprise Edition feature that adds multi-stage content approval workflows. It enables organizations to define review stages, assign reviewers, and enforce approval processes before content can be published. This is tightly integrated with the Document Service and content management UI.

## Core Concepts

### Workflows
- A workflow is a named sequence of stages
- Each content type can be assigned to one workflow
- Multiple content types can share the same workflow
- A default workflow is created on bootstrap

### Stages
- Stages represent steps in the review process (e.g., "Draft", "In Review", "Approved")
- Each stage has a name and color for visual identification
- Stages are ordered within a workflow
- Content entries are assigned to a stage

### Assignees
- Entries can be assigned to specific admin users at each stage
- Assignees are responsible for reviewing and advancing the content
- Assignment is optional and per-entry

## Services

| Service | Purpose |
|---------|---------|
| `workflows` | CRUD for workflow definitions |
| `stages` | CRUD for stage definitions within workflows |
| `stage-permissions` | Permission rules per stage (who can transition) |
| `assignees` | Manage entry-to-user assignments |
| `validation` | Validate workflow structure and transitions |
| `document-service-middlewares` | Inject workflow data into Document Service operations |
| `workflow-metrics` | Usage tracking |

## Workflow Lifecycle

```
Content Created
  |
  v
Stage 1: "Draft" (assigned to Author)
  |
  v [Author completes, advances]
Stage 2: "In Review" (assigned to Editor)
  |
  v [Editor approves, advances]
Stage 3: "Approved" (assigned to Publisher)
  |
  v [Publisher publishes]
Published
```

## Document Service Integration

The review workflow registers as a Document Service middleware:
- Intercepts content operations to enforce stage-based rules
- Adds stage information to content responses
- Validates stage transitions based on permissions
- Updates stage assignments on content changes

## Admin Panel Integration

The review workflow adds to the content editing UI:
- Stage indicator badge on content entries
- Stage selector dropdown in the edit view
- Assignee selector for the current stage
- Workflow visualization in settings
- Stage-based filtering in content lists

## Permission Model

Stage permissions control who can:
- View content at a specific stage
- Transition content to the next/previous stage
- Assign reviewers
- Override workflow stages

## Relevance to OpenRegister

**Key differences:**
- This is an EE-only feature in Strapi; no OpenRegister equivalent
- Strapi workflows are admin-panel focused; OpenRegister could implement via n8n

**Features OpenRegister could consider:**
- Multi-stage approval workflows as a premium/advanced feature
- Integration with Nextcloud's existing approval/workflow system
- n8n-based workflow definition (more flexible than Strapi's fixed stage model)
- Stage-based content filtering in API responses
- Assignee management for content review tasks
