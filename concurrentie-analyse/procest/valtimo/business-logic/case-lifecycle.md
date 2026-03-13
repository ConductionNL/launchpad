# Case Lifecycle -- Valtimo

## Case State Flow

```mermaid
flowchart TD
    START([Case Creation Request]) --> VALIDATE{Validate content\nagainst JSON Schema}
    VALIDATE -->|Invalid| REJECT([Validation Error])
    VALIDATE -->|Valid| CREATE[Create JsonSchemaDocument]
    CREATE --> SEQ[Generate sequence number]
    SEQ --> EVENT_CREATED[Publish DocumentCreatedEvent]
    EVENT_CREATED --> AUDIT_CREATE[Audit: case created]

    EVENT_CREATED --> HAS_PROCESS{Process linked\nto case type?}
    HAS_PROCESS -->|Yes| START_PROCESS[Start BPMN process instance]
    HAS_PROCESS -->|No| ACTIVE

    START_PROCESS --> LINK[Create ProcessDocumentInstance link]
    LINK --> ACTIVE

    ACTIVE[ACTIVE CASE]
    ACTIVE --> MODIFY{Modify case?}
    ACTIVE --> ASSIGN{Assign user?}
    ACTIVE --> STATUS{Change status?}
    ACTIVE --> TAG{Add/remove tags?}
    ACTIVE --> FILE{Add/remove files?}
    ACTIVE --> NOTE{Add note?}
    ACTIVE --> SUBPROCESS{Start sub-process?}

    MODIFY -->|Content update| DIFF[Diff old vs new content]
    DIFF --> REVALIDATE{Re-validate\nagainst schema}
    REVALIDATE -->|Invalid| MODIFY_FAIL([Validation Error])
    REVALIDATE -->|Valid| VERSION_CHECK{Version match?\n(optimistic lock)}
    VERSION_CHECK -->|Mismatch| CONFLICT([Concurrent Modification Error])
    VERSION_CHECK -->|Match| SAVE[Save + increment version]
    SAVE --> EVENT_MODIFIED[Publish DocumentModifiedEvent\nwith field-level changes]
    EVENT_MODIFIED --> AUDIT_MODIFY[Audit: fields changed]
    EVENT_MODIFIED --> ACTIVE

    ASSIGN --> EVENT_ASSIGN[Publish AssigneeChangedEvent]
    EVENT_ASSIGN --> AUDIT_ASSIGN[Audit: assignee changed]
    EVENT_ASSIGN --> ACTIVE

    STATUS --> EVENT_STATUS[Publish StatusChangedEvent]
    EVENT_STATUS --> AUDIT_STATUS[Audit: status changed]
    EVENT_STATUS --> ACTIVE

    TAG --> ACTIVE
    FILE --> EVENT_FILE[Publish FileAdded/RemovedEvent]
    EVENT_FILE --> ACTIVE
    NOTE --> EVENT_NOTE[Publish NoteCreatedEvent]
    EVENT_NOTE --> ACTIVE

    SUBPROCESS --> START_SUB[Start supporting BPMN process]
    START_SUB --> ACTIVE

    ACTIVE --> CLOSE{Close case?}
    CLOSE --> SET_RESULT[Set resultaat via ZGW]
    SET_RESULT --> SET_END_STATUS[Set end status]
    SET_END_STATUS --> CLOSED([CLOSED CASE])

    style START fill:#e1f5fe
    style ACTIVE fill:#c8e6c9
    style CLOSED fill:#ffcdd2
    style REJECT fill:#ffcdd2
    style MODIFY_FAIL fill:#ffcdd2
    style CONFLICT fill:#ffcdd2
```

## Key State Transitions

| From | To | Trigger | Events |
|------|----|---------|--------|
| (none) | Active | Case creation request | `DocumentCreatedEvent`, optional `ProcessStartedEvent` |
| Active | Active | Content modification | `DocumentModifiedEvent` + field change events |
| Active | Active | Assignment change | `AssigneeChangedEvent` |
| Active | Active | Status change | `StatusChangedEvent` |
| Active | Active | File attachment | `FileAddedEvent` / `FileRemovedEvent` |
| Active | Closed | Process completion + result set | `ProcessEndedEvent`, `StatusChangedEvent` |

## Concurrency Control

- JPA `@Version` field provides optimistic locking
- Concurrent modifications result in `OptimisticLockException`
- Client must re-read and retry on conflict
- Deadlock prevention tested explicitly in the codebase
