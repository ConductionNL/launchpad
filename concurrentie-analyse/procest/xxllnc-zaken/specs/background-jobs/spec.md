---
status: draft
source: competitive-analysis
competitor: xxllnc-zaken
analyzed_date: 2026-03-14
---
# Background Jobs & Event System -- xxllnc Zaken

## Purpose

Asynchronous job execution and event-driven architecture using RabbitMQ (AMQP). Handles document processing, notifications, geo sync, case events, and batch operations.

## Architecture Overview

- **HTTP Service:** `zsnl_jobs_http` (path `/api/v2/jobs/`)
- **Domain:** `zsnl_domains/jobs/`
- **Message Broker:** RabbitMQ 3 (Management Alpine)
- **Framework:** `minty_amqp` + `minty_infra_amqp`
- **Consumer Runner:** `consumer-queue_runner` (Python + legacy Perl)
- **Background Tasks:** `background-tasks` service

## Data Model

### Job Entity

```
Job:
  uuid: UUID
  type: str
  status: str
  result: ...      # downloadable result
  errors: ...      # downloadable error report
  created_date: datetime
```

## Event Consumers

| Consumer | Events Handled | Purpose |
|----------|---------------|---------|
| `zsnl_amqp_consumers` | ALL events | Audit logging to database |
| `zsnl_case_events_consumer` | Case* events | Case lifecycle processing, admin integration sync |
| `zsnl_documents_events_consumer` | Document* events | Document state management |
| `zsnl_document_processing_consumer` | DocumentCreated, VirusScanComplete | Preview, thumbnail, search index |
| `zsnl_communication_consumer` | Message*, Thread* events | Communication processing |
| `zsnl_consumer_geo` | Case/Contact geo changes | Geo feature synchronization |
| `zsnl_consumer_notification` | *Notification events | Email/notification dispatch |
| `zsnl_jobs_consumer` | Job* events | Background job execution |
| `consumer-queue_runner` | Legacy queue items | Perl-era background tasks |
| `timed_events` | Timer-based | Scheduled background operations |

## Business Logic

### Event Flow

```mermaid
flowchart TD
    Command[Command Execution] --> Entity[Entity Method]
    Entity --> |@event decorator| Event[AMQP Event Published]
    Event --> Exchange[RabbitMQ Exchange: minty_exchange]
    Exchange --> |fanout| Logger[Logging Consumer<br/>writes to audit table]
    Exchange --> |routing| Case[Case Events Consumer]
    Exchange --> |routing| Doc[Document Processing Consumer]
    Exchange --> |routing| Comm[Communication Consumer]
    Exchange --> |routing| Geo[Geo Consumer]
    Exchange --> |routing| Notify[Notification Consumer]
    Exchange --> |routing| Jobs[Jobs Consumer]
```

### Job Management API

| Endpoint | Method | Purpose |
|----------|--------|---------|
| /api/v2/jobs/get_jobs | GET | List all jobs |
| /api/v2/jobs/create_job | POST | Create new background job |
| /api/v2/jobs/cancel_job | POST | Cancel running job |
| /api/v2/jobs/delete_job | POST | Delete completed job |
| /api/v2/jobs/download_result | GET | Download job output |
| /api/v2/jobs/download_errors | GET | Download error report |

### Event Types (observed)

Case: CaseRegistrationDateSet, CaseTargetCompletionDateSet, CaseCompletionDateSet, CasePaused, CaseResumed, CaseSubjectSynchronized, UnacceptedDocumentCounterSynced

Document: DocumentCreated, DocumentFromAttachmentCreated, DocumentAddedToCase, DocumentUpdated, DocumentDeleted, DocumentAccepted, DocumentAssignedToUser, DocumentAssignedToRole, DocumentAssignmentRejected, PreviewCreated, ThumbnailCreated, SearchIndexSet, SearchIndexSetDelayed, LockAcquired, LockReleased, LockExtended, LabelsApplied, LabelsRemoved, DocumentMoved

Task: TaskCreated, TaskUpdated, TaskDeleted, TaskCompletionSet, TaskAssigneeNotified

Message: MessageDeleted, MessageRead, MessageMarkedUnread

Geo: GeoFeatureCreated, GeoFeatureDeleted

Style: StyleConfigurationCreated

Payment: WebhookTestSuccessful

## Requirements (as observed)

1. All entity mutations emit AMQP events
2. Logging consumer subscribes to ALL events for audit trail
3. Domain-specific consumers handle business logic reactions
4. Jobs API provides visibility into background task status
5. Job results and error reports are downloadable
6. RabbitMQ exchange-based routing for event distribution
7. Legacy Perl queue runner for backward compatibility
8. Timed events for scheduled operations

## Comparison Notes

**vs Procest:**
- xxllnc uses RabbitMQ for event-driven architecture; Procest uses n8n workflows
- The automatic audit logging (all events -> logging table) is built into the architecture
- n8n provides more flexibility for workflow design but less transactional guarantee
- xxllnc's event system is synchronous within the command but async for side effects
- Procest could achieve similar patterns with n8n webhook triggers on OpenRegister events
