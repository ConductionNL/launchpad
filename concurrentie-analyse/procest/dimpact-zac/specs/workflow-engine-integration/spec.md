---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Workflow Engine Integration -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC uses Flowable for case workflow management.
- **Product**: Dimpact ZAC
- **Category**: Workflow Engine
- **Relevance to Procest**: Workflow orchestration is at the heart of case management

## Architecture Overview
ZAC uses Flowable as its embedded workflow engine supporting both CMMN (Case Management Model and Notation) and BPMN (Business Process Model and Notation). The engine runs inside the WildFly application server.

Key services:
- `CMMNService` -- CMMN case lifecycle management
- `BpmnService` -- BPMN process management
- `FlowableTaskService` -- task operations
- `ZaakVariabelenService` -- Flowable variable management
- `TaakVariabelenService` -- task variable management

## Data Model

### CMMN Model: Generiek Zaakafhandelmodel
The generic case handling model defines:

**Stages:**
1. **INTAKE** (Stage)
   - `AANVULLENDE_INFORMATIE` -- Human task for requesting additional info (repeatable)
   - `INTAKE_AFRONDEN` -- User event listener to complete intake
   - `INTAKE_GEREED` -- Milestone marking intake completion

2. **IN_BEHANDELING** (Stage)
   - `GOEDKEUREN` -- Human task for approval
   - `ADVIES_INTERN` -- Human task for internal advice
   - `ADVIES_EXTERN` -- Human task for external advice
   - `DOCUMENT_VERZENDEN_POST` -- Human task for sending documents
   - `ZAAK_AFHANDELEN` -- User event listener to complete case

**Lifecycle Listeners:**
- `UpdateZaakLifecycleListener` -- updates ZRC status when stages activate

**Sentries (conditions):**
- Intake -> In behandeling: `${var:getOrDefault(ontvankelijk,false)}` (admissible = true)

### Flowable Variables (Zaak)
| Variable | Description |
|----------|-------------|
| VAR_ZAAK_UUID | Case UUID |
| VAR_ZAAKTYPE_UUID | Case type UUID |
| VAR_ZAAK_GROUP | Assigned group name |
| VAR_ZAAK_USER | Assigned user name |
| VAR_ZAAK_COMMUNICATIEKANAAL | Communication channel |
| ontvankelijk | Admissibility decision (intake result) |

### Flowable Variables (Task)
| Variable | Description |
|----------|-------------|
| taakdata | JSON map of form field values |
| taakinformatie | Task metadata |
| TAAK_DATA_DOCUMENTEN_VERZENDEN_POST | Documents to send |
| TAAK_DATA_VERZENDDATUM | Send date |
| TAAK_DATA_TOELICHTING | Explanation |

### BPMN Support
- Custom BPMN process definitions can be uploaded
- Mapped to zaaktypes via `ZaaktypeBpmnProcessDefinition` configuration
- Process definitions deployed to Flowable at runtime
- Process diagram visualization available via `BpmnService.getProcessDiagram()`

## Business Logic

### Case Start (CMMN)
1. Start CMMN case with zaak variables
2. Intake stage auto-activates
3. Status set to "Intake" via lifecycle listener
4. Human tasks available based on CMMN plan items

### Case Start (BPMN)
1. Look up process definition by zaaktype + productaanvraagtype
2. Start process instance with zaak variables
3. Process drives task creation automatically

### Plan Item Interactions
- Human tasks: start, complete, assign
- User event listeners: trigger stage transitions (intake afronden, zaak afhandelen)
- Process tasks: execute service tasks within the case

### Variable Management
- Case-level variables: group, user, communication channel, admissibility
- Task-level variables: form data, document references, dates
- Variables bridged between Flowable and ZGW APIs

### Dual-mode Detection
`BpmnService.isZaakProcessDriven(zaakUUID)` checks whether a case has a BPMN process instance. Used to route operations to the correct workflow engine.

## Requirements (as observed)

1. Default CMMN model handles all zaaktypes unless a BPMN process is configured
2. CMMN model has fixed two-stage structure (Intake -> In behandeling)
3. BPMN processes can be custom per zaaktype
4. Flowable variables bridge workflow state and ZGW API state
5. Plan items are manually activated (human tasks with manual activation rule)
6. Stage transitions driven by user events and sentry conditions
7. Process diagram rendering available for BPMN

## Comparison Notes
- ZAC uses embedded Flowable; Procest uses n8n for workflow automation
- CMMN provides flexible case management; BPMN provides structured processes
- The dual CMMN/BPMN approach gives flexibility but adds complexity
- Procest's n8n approach may be more accessible to non-developers
- ZAC's CMMN model is a single generic model; custom models are not easily created
