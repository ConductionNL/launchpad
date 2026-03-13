---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Audit Trail -- Valtimo

## Purpose
Records all significant actions performed on cases, documents, and processes for compliance, traceability, and debugging. Uses an event-sourcing approach where domain events are captured and stored as immutable audit records, providing a complete history of what happened, when, and by whom.

## Architecture Overview
- **Backend module**: `audit/` (event capture and storage)
- **Frontend module**: `logging/` Angular library for audit log viewer
- **Pattern**: Event sourcing via Spring's `AbstractAggregateRoot` domain events
- **Storage**: Audit records stored in dedicated audit tables
- **Integration**: Case detail view includes an "Audit" tab showing chronological event history

## Data Model

### AuditRecord
| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique audit record ID |
| origin | String | Source module/system |
| occurredOn | LocalDateTime | When the event happened |
| user | String | User who triggered the event |
| eventType | String | Fully qualified event class name |
| eventData | JSON | Serialized event payload |
| documentId | UUID | Related case document (if applicable) |

### Captured Event Types
| Event | Description |
|-------|-------------|
| `JsonSchemaDocumentCreatedEvent` | New case created |
| `JsonSchemaDocumentModifiedEvent` | Case content changed (with field-level diff) |
| `DocumentAssigneeChangedEvent` | Case reassigned |
| `DocumentStatusChangedEvent` | Case status updated |
| `DocumentRelatedFileAddedEvent` | File attached to case |
| `DocumentRelatedFileRemovedEvent` | File removed from case |
| `TaskCreatedEvent` | User task created by process |
| `TaskCompletedEvent` | User task completed |
| `TaskAssignedEvent` | Task assigned/reassigned |
| `ProcessStartedEvent` | Process instance started |
| `ProcessEndedEvent` | Process instance completed |
| `NoteCreatedEvent` | Note added to case |

## Business Logic

### Event Capture Flow
1. Domain action occurs (e.g., document modified)
2. Entity (using `AbstractAggregateRoot`) registers a domain event
3. Spring's event infrastructure publishes the event after transaction commit
4. Audit module's event listener captures the event
5. Event serialized to JSON and stored as `AuditRecord`
6. Audit record includes user context, timestamp, and full event payload

### Field-Level Change Tracking
- `JsonSchemaDocumentModifiedEvent` includes a list of `JsonSchemaDocumentFieldChangedEvent`
- Each field change records: JSON path, old value, new value
- Enables precise "what changed" audit queries

### Audit Log Viewing
- Case detail "Audit" tab shows chronological event list
- Events displayed with user, timestamp, and description
- Filterable by event type
- Paginated for cases with long histories

### Structured Logging Module
- Separate `logging/` module for operational logging
- Adds resource context to log entries (document ID, process ID)
- Complements audit trail with technical debugging information

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- OpenRegister provides basic audit logging via entity change tracking
- Nextcloud has native activity logging for file operations
- Less structured event capture -- more generic change logs

### Valtimo advantages
- Event-sourced audit trail with domain-specific event types
- Field-level change tracking on case documents
- Structured event payloads (queryable, analyzable)
- Dedicated audit viewer in case detail UI
- Separation of audit events from operational logs

### Valtimo disadvantages
- Event-sourcing approach increases storage requirements
- No built-in audit report generation or export
- No retention/archival policy management in the UI
- Audit records cannot be used for state reconstruction (not full event sourcing)
