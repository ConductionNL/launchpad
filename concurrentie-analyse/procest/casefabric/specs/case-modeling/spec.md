---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Visual Case Modeling (Case Designer)
category: tooling
relevance: medium
---

# Visual Case Modeling (Case Designer)

## Summary

CaseFabric includes a browser-based Case Designer (IDE) for creating CMMN case models. The designer provides visual drag-and-drop modeling with a properties palette, allowing business analysts and developers to create executable case definitions.

## Key Capabilities

### Visual Modeling
- Drag-and-drop interface for all CMMN elements
- Canvas-based case plan modeling
- Properties palette for configuring element properties
- Visual sentry connections between plan items

### Supported Elements
- Stages (grouping containers)
- Human Tasks, Process Tasks, Case Tasks
- Milestones
- Timer Event Listeners, User Event Listeners
- Entry/Exit Criteria (sentries)
- Case File Items with hierarchical structure
- Roles

### Task Implementation Design
- Human Task Models: define input/output parameters, UI rendering
- Process Task Implementations: configure external service URIs
- Case Task references to other case definitions
- Parameter mapping between tasks and Case File Items

### Expression Editor
- SpEL expressions for sentry conditions
- Repetition rule expressions
- Required rule expressions
- Due date expressions
- Dynamic assignment expressions

### Deployment
- Deploy models directly to the case engine
- Case definitions stored in engine repository
- Models are CMMN 1.1 XML files
- Version management of case definitions

### Generic UI Integration
- Task forms via React JSON Schema Forms
- Rapid prototyping without custom UI development
- Shows cases, tasks, and case file data
- Bridges communication between IT, analysts, and business users

## Technical Details

- Browser-based (no installation required)
- Produces standard CMMN 1.1 XML
- CaseFabric extension elements for non-CMMN features
- Integration with repository API for storage/retrieval

## Relevance to Procest

**Medium relevance.** A visual case modeling tool is valuable for business users, but Procest targets a different audience within the Nextcloud ecosystem. The approach of using familiar Nextcloud UI patterns and configuration may be more accessible than a dedicated CMMN modeler.

### What to learn:
- Visual case design is important for business adoption
- React JSON Schema Forms for rapid task form creation
- Separation of model design from execution is clean architecture
- Generic UI for prototyping accelerates feedback cycles

### What to avoid:
- CMMN-specific visual language is a learning curve
- Separate designer tool adds deployment complexity
- Tight coupling to CMMN standard limits flexibility
