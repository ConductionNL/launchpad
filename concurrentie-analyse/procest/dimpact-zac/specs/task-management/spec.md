---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Task Management -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements task (taak) management.
- **Product**: Dimpact ZAC
- **Category**: Workflow Task Management
- **Relevance to Procest**: Tasks are the primary unit of work within cases -- critical for user productivity

## Architecture Overview
Tasks are managed through Flowable (CMMN human tasks or BPMN user tasks). ZAC provides a REST API layer (`TaskRestService`) and business logic layer (`TaskService`) on top of Flowable's task engine. Tasks are indexed in Solr for search.

Key paths:
- REST: `/rest/taken`
- Backend: `TaskRestService` -> `TaskService` -> `FlowableTaskService`
- Forms: `FormulierRuntimeService` handles form rendering and submission

## Data Model

### RestTask
| Field | Type | Description |
|-------|------|-------------|
| id | String | Flowable task ID |
| naam | String | Task name |
| toelichting | String | Description |
| status | TaakStatus | OPEN or COMPLETED |
| zaakUuid | UUID | Parent case UUID |
| behandelaar | RestUser | Assigned user |
| groep | RestGroup | Assigned group |
| creatiedatum | String | Creation date |
| toekenningsdatum | String | Assignment date |
| fataledatum | LocalDate | Due date |
| formulierDefinitie | FormulierDefinitie | Custom form |
| formioFormulier | FormioFormulier | Form.io definition |
| taakdata | Map<String, Any> | Form data key-value pairs |
| taakinformatie | Map<String, String> | Task metadata |
| tabellen | Map<String, List<String>> | Reference table data |

### TaakStatus
- `OPEN` -- task is active and can be worked on
- `AFGEROND` -- task has been completed

### TaakSortering
Sort options: CREATIEDATUM, TOEKENNINGSDATUM, FATALEDATUM, ID

## Business Logic

### Task Views
1. **Werkvoorraad** (Work pool) -- tasks assigned to user's group, not to specific user
2. **Mijn taken** (My tasks) -- tasks assigned to logged-in user
3. **Zaak taken** -- tasks within a specific case

### Task Assignment
- Single task: check `taak.toekennen` policy, then assign via Flowable
- Bulk distribute: check `werklijst.zakenTakenVerdelen`, then async assign
- Self-assign: "toekennen aan mij" (claim)
- Release: remove individual assignment, keep group assignment

### Task Completion
1. Validate task is open and user has `taak.wijzigen` permission
2. If task not assigned to user, auto-assign with reason "Afgesloten"
3. Two form paths:
   - **Form.io / custom formulier**: delegate to `formulierRuntimeService.submit()`
   - **Hardcoded form**: process documents, handle zaak resume, handle document sending/signing
4. Complete task in Flowable
5. Re-index zaak in Solr
6. Send screen events for task and case

### Form Processing
- **File upload**: Files stored in HTTP session during editing, linked to task via `_FILE__{taskId}__{fieldName}` key
- **Document creation**: Creates informatieobject in DRC, links to zaak
- **Document signing**: Signs specified documents as part of task completion
- **Document sending**: Sets verzenddatum on specified documents
- **Zaak resume**: If task data contains "zaak hervatten" flag, automatically resumes suspended case

### Task History
- `GET /rest/taken/{taskId}/historie` returns audit trail from Flowable

## Requirements (as observed)

1. Tasks MUST belong to a zaak (case) -- no standalone tasks
2. Task form data is stored as Flowable process/case variables
3. Bulk operations (distribute/release) run asynchronously
4. Task completion can trigger side effects: document creation, zaak resume, document sending
5. Form rendering supports two engines: custom FormulierDefinitie and Form.io
6. All task state changes trigger Solr indexing and WebSocket events

## CMMN Task Types (from User Manual)

The generic CMMN model provides these predefined task types:

1. **Aanvullende informatie** (Additional Information)
   - Request additional info during intake phase
   - Can trigger email notification to initiator
   - Option to suspend case while waiting for response
   - Integrates with email system

2. **Intern advies** (Internal Advisory)
   - Solicit advice from internal staff
   - Select documents for advisor review
   - Advisor provides structured response

3. **Extern advies** (External Advisory)
   - Track external advice requests
   - Identify external advisor
   - Manual tracking (no direct external system integration)

4. **Goedkeuring** (Approval)
   - Route items for internal approval
   - Multi-document support
   - Approval/rejection outcome

5. **Document verzenden** (Document Transmission)
   - Assign document sending responsibility to handlers
   - Track transmission status with date recording

## Task Configuration (Admin)

Per zaaktype, admins configure:
- Enable/disable individual tasks within workflows
- Assign form definitions to tasks
- Set default assignee groups and throughput times
- Link reference tables to task choice lists

## Comparison Notes
- ZAC tasks are tightly coupled to Flowable -- task state lives entirely in the workflow engine
- The dual form engine (Form.io + custom) adds complexity but flexibility
- File upload via HTTP session is unusual -- Procest could use a more standard approach
- Task completion with side effects (resume, sign, send) is powerful but makes the complete flow complex
- Bulk operations via coroutines is a good async pattern
- CMMN task types are fixed (5 types) -- covers basic patterns but not extensible without code changes
- BPMN tasks offer more flexibility but require Form.io expertise and external tooling
- The "Additional Information" task with email + suspension is a well-integrated pattern
- No concept of automated/system tasks in the CMMN model -- all tasks are human tasks
