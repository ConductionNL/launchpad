---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Process Automation — Dimpact ZAC

## Purpose

Process automation for case handling using CMMN and BPMN standards. Determines how cases progress through states, which tasks are available, and how workflows are configured per zaaktype.

## Architecture Overview

- **Flowable** open-source process engine embedded in the WildFly application
- Flowable stores its state in a dedicated `flowable` PostgreSQL schema
- Process definitions (CMMN XML / BPMN XML) packaged with ZAC or imported via admin
- Forms for BPMN user tasks use the **Form.io** framework
- Each zaaktype is configured to use either the generic CMMN model or a custom BPMN process

## Data Model

### Process Configuration
- `zaakafhandelparameters.caseDefinitionKey` — links zaaktype to CMMN model
- `zaakafhandelparameters.processDefinitionKey` — links zaaktype to BPMN process
- `humanTaskParameters` — per-task configuration (forms, groups, deadlines)

### Flowable Schema
- Process instance state
- Task assignments and variables
- Case variables (taakdata key-value pairs)
- Process/case history

## Business Logic

### Generic CMMN Model
- Single model for all "simple" zaaktypes (80-90% of cases)
- Two main states: **Intake** and **In behandeling**
- File: `src/main/resources/cmmn/Generiek_zaakafhandelmodel.cmmn.xml`
- Tightly integrated with ZAC code — cannot be modified without code changes
- Defines plan items (human tasks) that become available based on case state:
  - Aanvullende informatie, Intern advies, Extern advies, Goedkeuring, Document verzenden
- End users cannot edit the CMMN model

### Custom BPMN Processes
- For complex zaaktypes needing custom workflows
- Created externally (Flowable Designer or other BPMN tools)
- Imported via ZAC admin interface
- User task forms modeled with Form.io
- Can define arbitrary task sequences, gateways, events

### Process Selection Logic
When a case is created (e.g., from productaanvraag):
1. If CMMN mapping exists for the zaaktype -> start CMMN Case
2. If only BPMN mapping exists -> start BPMN process
3. If both exist -> CMMN takes precedence (BPMN ignored, warning logged)
4. If neither -> register as inbox item (no process started)

## Requirements (as observed)

1. Every zaaktype MUST be configured with either CMMN or BPMN before cases can be handled
2. Generic CMMN model is fixed — extensibility only via BPMN
3. BPMN processes and Form.io forms must be created outside ZAC
4. Flowable manages task lifecycle, assignments, and process variables
5. Process state is local to ZAC (not in Open Zaak)
6. Admin can enable/disable individual tasks per zaaktype configuration

## Comparison Notes

**vs. Procest:**
- ZAC uses an embedded Java process engine (Flowable); Procest could use n8n for workflow orchestration
- CMMN is well-suited for adaptive case management but the generic model is rigid
- BPMN provides extensibility but requires external tooling expertise
- The 80/90% generic coverage claim is practical — most municipal zaaktypes are simple
- Process state being local (not in Open Zaak) means ZAC is the only consumer of this data
- Form.io for BPMN task forms is a standard choice but adds another dependency
- Procest's approach of using configurable state machines + n8n workflows could be more accessible to non-developers
