---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Process Engine -- Valtimo

## Purpose
Provides full BPMN 2.0 process execution via the Operaton engine (community fork of Camunda 7). This is the fundamental automation backbone of Valtimo -- all case workflows, task assignments, timer events, and service task integrations flow through the process engine.

## Architecture Overview
- **Engine**: Operaton (migrated from Camunda 7 in Valtimo v12.0)
- **Backend module**: `core/` (Spring Boot integration with Operaton)
- **Bridge module**: `process-document/` links process instances to `JsonSchemaDocument` cases
- **Frontend**: `process/` Angular library with BPMN viewer/modeler, `process-management/` for admin
- **Standards**: BPMN 2.0 (primary), DMN (decision tables), partial CMMN support

## Data Model

### ProcessDocumentInstance
Links a running Operaton process instance to a Valtimo case document.

| Field | Type | Description |
|-------|------|-------------|
| processInstanceId | String | Operaton process instance ID |
| documentId | UUID | Reference to `JsonSchemaDocument` |
| active | Boolean | Whether the process is still running |
| createdOn | LocalDateTime | Link creation timestamp |

### ProcessLink
Associates BPMN activities with executable actions (plugin actions, forms, form flows, building blocks).

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique link identifier |
| processDefinitionId | String | BPMN process definition reference |
| activityId | String | BPMN activity element ID |
| activityType | Enum | SERVICE_TASK, USER_TASK, CALL_ACTIVITY |
| linkType | Enum | FORM, FORM_FLOW, PLUGIN, BUILDING_BLOCK |
| actionProperties | JSON | Configuration for the linked action |

### ProcessDefinition (Operaton-managed)
Standard BPMN process definitions deployed to the engine.

| Field | Type | Description |
|-------|------|-------------|
| id | String | Operaton definition ID (includes version) |
| key | String | Process definition key |
| version | Integer | Auto-incrementing version |
| deploymentId | String | Deployment bundle reference |
| bpmnXml | Text | BPMN 2.0 XML definition |

## Business Logic

### Process Deployment
1. BPMN XML uploaded via admin UI or auto-deployed from classpath (`*.bpmn`)
2. Operaton parses and validates BPMN 2.0 XML
3. New version created (versions are immutable)
4. Process links JSON (`<process-id>.process-link.json`) auto-deployed alongside

### Process Execution
1. Process started for a case (manually or on case creation)
2. Operaton creates a process instance, linked via `ProcessDocumentInstance`
3. Engine evaluates BPMN flow: sequence flows, gateways, events
4. **Service tasks**: Execute synchronously via Java delegates or plugin actions (via ProcessLink)
5. **User tasks**: Create task entries, assigned to users/groups, completed via forms
6. **Timer events**: Scheduled execution via Operaton job executor
7. **Message events**: Correlated via message name and business key
8. Process variables stored in Operaton; case data stored in `JsonSchemaDocument`

### Process Migration
- Batch migration of running process instances between definition versions
- Admin selects source/target versions and maps activities
- Useful when fixing live workflows without losing in-flight cases

### Process Analytics
- **Heatmaps**: Overlay on BPMN diagram showing execution counts per activity
- **Duration analysis**: Average time spent at each activity
- **Instance tracking**: List of active/completed instances with variable inspection

### DMN Decision Tables
- Deployed alongside BPMN definitions
- Called from BPMN via business rule tasks
- Input/output columns define decision logic
- Results injected as process variables

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- Uses **n8n workflows** for automation (visual low-code, HTTP-based)
- No dedicated process engine -- n8n is triggered via webhooks/schedules
- Simpler learning curve, more accessible to non-technical users
- No native BPMN modeling or execution

### Valtimo advantages
- Full BPMN 2.0 execution with timers, gateways, subprocesses, compensation
- Visual process modeler integrated in the platform
- Process instance migration between versions
- DMN decision tables for business rules
- Process-level analytics and heatmaps
- Standards-compliant process definitions (portable BPMN XML)

### Valtimo disadvantages
- Requires JVM (Operaton engine) -- heavier runtime
- BPMN complexity: steep learning curve for business users
- Tight coupling to Operaton -- migration between engines is non-trivial
- Operaton is a community fork (Camunda 7 EOL) -- long-term viability uncertain
