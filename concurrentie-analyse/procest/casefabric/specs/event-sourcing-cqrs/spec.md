---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Event Sourcing and CQRS Architecture
category: architecture
---

# Event Sourcing and CQRS

## Overview

CaseFabric uses event sourcing as its primary persistence pattern. Every state change produces an immutable event persisted to a journal. The current state is reconstructed by replaying events. A separate CQRS read side projects events into denormalized query tables for fast retrieval.

## Implementation Details

### Write Side (Event Journal)

Each case is an Akka `AbstractPersistentActor` (via `ModelActor` base class):
- Commands (`ModelCommand` subclasses) are validated and processed
- Events (`ModelEvent` subclasses) are generated and persisted atomically
- Events applied to in-memory state after persistence confirmation
- Actor recovery replays events from journal on startup
- Optional snapshots reduce recovery time (`SnapshotMetadata`)

Supported journal backends:
- **Cassandra** (`akka-persistence-cassandra`) -- distributed, production-grade
- **PostgreSQL JDBC** (`akka-persistence-jdbc`) -- simpler deployment
- **In-memory** (`inmemory-journal` from Dennis Vriend) -- testing only

Serialization:
- `CafienneSerializer` extends Akka serialization for `CafienneSerializable` types
- Jackson databind for JSON serialization of events
- `CaseTaggingEventAdapter` tags events for efficient stream consumption

### Read Side (Query Projections)

Two projection writers consume tagged event streams:

**CaseProjectionsWriter:**
- `CaseProjection` -- case instance lifecycle -> `case_instance` table
- `CasePlanProjection` -- plan item transitions -> `plan_item` + `plan_item_history`
  - `PlanItemMerger` -- upserts current state
  - `PlanItemHistoryMerger` -- appends history records
  - `TaskMerger` -- updates human task inbox
- `CaseFileProjection` -- case file changes -> `case_file` + `case_business_identifier`
  - `CaseFileMerger` -- updates JSON data blob
  - `CaseIdentifierMerger` -- indexes business identifiers
- `CaseTeamProjection` -- team changes -> `case_instance_team_member` + `case_instance_role`

**TenantProjectionsWriter:**
- `TenantProjection` -- tenant events -> tenant tables
- `UserProjection` -- user events -> user tables

Infrastructure:
- `SlickRecordsPersistence` -- Slick ORM for database writes
- `SlickEventMaterializer` -- event stream consumer with Akka Streams
- `QueryDBOffsetStorage` -- persists consumer offset for exactly-once projection
- Backoff supervision: configurable min/max backoff (default 500ms/30s), random factor 0.20, max 20 restarts in 5 minutes

### Event Hierarchy

```
ModelEvent
  +-- CaseEvent
  |     +-- CaseStarted, CaseCompleted, CaseTerminated, CaseFailed
  |     +-- CaseAppliedPlatformUpdate
  |     +-- DebugEnabled, DebugDisabled
  |     +-- CaseDefinitionMigrated
  +-- PlanItemEvent
  |     +-- PlanItemCreated, PlanItemTransitioned
  |     +-- PlanItemMigrated, PlanItemDropped
  +-- CaseFileEvent
  |     +-- CaseFileItemCreated, CaseFileItemUpdated, CaseFileItemReplaced, CaseFileItemDeleted
  |     +-- CaseFileItemMigrated, CaseFileItemDropped
  +-- CaseTeamEvent
  |     +-- CaseTeamMemberAdded, CaseTeamMemberRemoved
  +-- HumanTaskEvent
  |     +-- HumanTaskActivated, HumanTaskClaimed, HumanTaskAssigned
  |     +-- HumanTaskDelegated, HumanTaskRevoked, HumanTaskCompleted
  |     +-- HumanTaskSuspended, HumanTaskResumed, HumanTaskTerminated
  |     +-- HumanTaskInputSaved, HumanTaskOutputSaved, HumanTaskDueDateFilled
  |     +-- HumanTaskOwnerChanged
  |     +-- HumanTaskMigrated, HumanTaskDropped
  +-- ProcessInstanceEvent
  |     +-- ProcessStarted, ProcessCompleted, ProcessFailed
  |     +-- ProcessSuspended, ProcessResumed, ProcessReactivated, ProcessTerminated
  +-- TenantEvent
        +-- TenantCreated, TenantModified
        +-- TenantUserAdded, TenantUserChanged, TenantUserRemoved
```

### Configuration

```hocon
cafienne {
  read-journal = "inmemory-read-journal"  # or jdbc-read-journal, cassandra-query-journal
  query-db {
    profile = "slick.jdbc.PostgresProfile$"
    db { url, user, password, numThreads, connectionTimeout }
    restart-stream {
      min-back-off = 500ms
      max-back-off = 30s
      random-factor = 0.20
      max-restarts = 20
      max-restarts-within = 5m
    }
  }
}
```

## Relevance for Procest

Even without full event sourcing, several patterns are valuable:

1. **Audit trail table** -- record every state change (like `plan_item_history`) for compliance
2. **Projection pattern** -- maintain denormalized search tables separately from canonical data
3. **Business identifier indexing** -- separate index table for cross-entity search
4. **Offset-tracked processing** -- ensure exactly-once processing of change streams
5. **Backoff supervision** -- resilient async processing with configurable retry
