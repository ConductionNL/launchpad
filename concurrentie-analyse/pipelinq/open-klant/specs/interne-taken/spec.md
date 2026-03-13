---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Interne Taken (Internal Tasks) -- Open Klant

## Purpose

An InterneTaak represents a follow-up task that arises from a client contact. It is assigned to one or more Actors and tracks what needs to be done (gevraagde_handeling), additional context (toelichting), and progress (status: te_verwerken / verwerkt).

- **Product**: Open Klant
- **Category**: Task Management
- **Relevance to Pipelinq**: Bridges contact interactions to case handling workflows.

## Data Model

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| nummer | CharField(10, unique, deprecated) | Human-readable task number |
| referentienummer | CharField(10, unique) | Reference number |
| klantcontact | FK -> Klantcontact | The originating contact moment |
| actoren | M2M -> Actor (through InterneTakenActorenThoughModel) | Assigned actors |
| gevraagde_handeling | CharField(200) | Required action to complete the task |
| toelichting | TextField(1000) | Additional context |
| status | CharField(12) | `te_verwerken` (pending) or `verwerkt` (processed) |
| toegewezen_op | DateTimeField (auto_now_add) | When assigned |
| afgehandeld_op | DateTimeField (nullable, EXPERIMENTAL) | When completed |

### Auto-set afgehandeld_op

In the `save()` method:
- When status changes to `verwerkt` and `afgehandeld_op` is None -> sets `afgehandeld_op = now()`
- When status changes back to `te_verwerken` and `afgehandeld_op` is set -> sets `afgehandeld_op = None`

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET/POST | `/klantinteracties/api/v1/internetaken/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/internetaken/{uuid}/` | Detail CRUD | Token |

### Filters

- `nummer` (deprecated), `referentienummer`
- `klantcontact__uuid`, `klantcontact__url`, `klantcontact__nummer` (deprecated), `klantcontact__referentienummer`
- `actoren__uuid`, `actoren__url`, `actoren__naam`
- `status`
- `toegewezen_op`, `afgehandeld_op`

### Notifications

InterneTaak changes are sent to the `internetaken` notification channel (if notifications are enabled).

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Task creation | From klantcontact, assigned to actors | Pipeline-based task management (more advanced) |
| Task status | Simple: te_verwerken / verwerkt | Multi-stage pipeline with custom statuses |
| Assignment | M2M to Actor model | N/A (pipeline stages) |
| Completion tracking | afgehandeld_op timestamp (experimental) | Pipeline completion |
| Task content | gevraagde_handeling + toelichting | Pipeline task descriptions |
| Notifications | VNG Notificaties API | N/A |

**Already in Pipelinq**: Pipeline-based task management (more sophisticated than InterneTaak)
**Not yet in Pipelinq**: Contact-to-task linkage, VNG notification integration, actor assignment model
