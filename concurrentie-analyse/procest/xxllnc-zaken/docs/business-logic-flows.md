# xxllnc Zaken -- Business Logic Flows

Mermaid diagrams documenting the key business logic flows observed in the codebase.

## 1. Case Lifecycle State Machine

```mermaid
stateDiagram-v2
    [*] --> new: create_case
    new --> open: automatic (on registration)
    open --> stalled: pause (reason + term)
    stalled --> open: resume (recalculate dates)
    open --> resolved: set_result + set_status
    resolved --> [*]: destruction_date reached
    open --> deleted: delete_case (admin)
    new --> deleted: delete_case (admin)

    note right of stalled
        Suspension types:
        - weeks
        - work_days
        - calendar_days
        - fixed_date
        - indefinite
    end note

    note right of resolved
        Completion date validated:
        - Not before registration
        - Not in the future
    end note
```

## 2. Case Phase & Rule Engine Flow

```mermaid
sequenceDiagram
    participant API as HTTP View
    participant Cmd as Command (CaseCommandBase)
    participant Case as Case Entity
    participant Dec as @apply_case_rules
    participant RE as RuleEngine
    participant AMQP as RabbitMQ

    API->>Cmd: Execute command
    Cmd->>Cmd: Set rule_engine in context
    Cmd->>Case: Call entity method
    Case->>Dec: case_event_decorator wraps method
    Dec->>Case: Execute method body
    Dec->>AMQP: Emit named event (fire_always=True)
    Dec->>RE: Get rule_engine from context_vars
    Dec->>Case: Get phases[milestone-1].rules
    RE->>RE: execute_rules(rules, case)
    Note over RE: Rules may modify case state,<br/>triggering further events
```

## 3. Document Processing Pipeline

```mermaid
flowchart TD
    Upload[File Upload] --> Create[DocumentCreated event]
    Create --> VS{Virus Scan}
    VS -->|clean| MIME{MIME Type Check}
    VS -->|infected| Quarantine[Quarantined]
    MIME -->|previewable + <200MB| Preview[Generate PDF Preview]
    MIME -->|not previewable| Skip[Skip Preview]
    Preview --> Thumb{Thumbnailable?}
    Skip --> Search{Search Indexable?}
    Thumb -->|direct| ThumbDirect[Generate Thumbnail from File]
    Thumb -->|from_preview| ThumbPreview[Generate Thumbnail from Preview]
    Thumb -->|no| Search
    ThumbDirect --> Search
    ThumbPreview --> Search
    Search -->|yes| Tika[Extract Text via Tika]
    Search -->|no| Done[Processing Complete]
    Tika --> Done
```

## 4. Document Intake Workflow

```mermaid
flowchart TD
    Upload[Document Uploaded] --> Intake{Intake Required?}
    Intake -->|skip_intake=true| AutoAccept[Auto Accept]
    Intake -->|auto_accept=true| AutoAccept
    Intake -->|no| Unassigned[Unassigned Pool]

    Unassigned --> AssignUser[Assign to User]
    Unassigned --> AssignRole[Assign to Role/Group]

    AssignUser --> Review{Review}
    AssignRole --> Review

    Review -->|accept| LinkCase[Link to Case + Accept]
    Review -->|reject| Reject[Record Rejection Reason]
    Reject --> Unassigned

    AutoAccept --> LinkCase
    LinkCase --> Processing[Document Processing Pipeline]
```

## 5. Communication Thread Flow

```mermaid
flowchart TD
    subgraph Inbound
        Email[Inbound Email] --> Import[import_email_message]
        Import --> Thread1[Create/Find Thread]
    end

    subgraph Caseworker Actions
        Note[Create Note] --> Thread2[Add to Thread]
        CM[Create Contact Moment] --> Thread2
        ExtMsg[Create External Message] --> Thread2
    end

    Thread1 --> Link{Linked to Case?}
    Thread2 --> Link

    Link -->|yes| Case[Case Timeline]
    Link -->|no| Orphan[Orphan Thread]
    Orphan --> ManualLink[link_thread_to_case]
    ManualLink --> Case

    Case --> Attach[Attachment → Document]
    Attach --> DocIntake[Document Intake]
```

## 6. Payment Flow

```mermaid
sequenceDiagram
    participant C as Citizen
    participant FE as Frontend
    participant API as Payment API
    participant WL as Worldline
    participant Case as Case Entity

    C->>FE: Click "Pay"
    FE->>API: GET /payments/initiate_payment
    API->>WL: Create payment session
    WL-->>API: Payment URL + session ID
    API-->>FE: Redirect URL
    FE->>C: Redirect to Worldline hosted page
    C->>WL: Complete payment (iDEAL, card, etc.)
    WL->>API: POST /payments/callback (async webhook)
    API->>Case: set_payment_status (amount, status)
    WL->>FE: GET /payments/redirect (sync return)
    FE->>C: Payment confirmation page
```

## 7. Case Assignment & Allocation

```mermaid
flowchart TD
    NewCase[New Case Created] --> AutoAssign{Auto-assignment Rules?}
    AutoAssign -->|yes| DeptAssign[Assign to Department]
    AutoAssign -->|no| Unassigned[Unassigned]

    DeptAssign --> PhaseAlloc[Phase Allocation]
    Unassigned --> ManualAssign[Manual Assignment]

    ManualAssign --> AssignUser[assign_case_to_user]
    ManualAssign --> AssignSelf[assign_case_to_self]
    ManualAssign --> AssignDept[assign_case_to_department]

    PhaseAlloc --> AllocUser[set_allocation_to_user]
    PhaseAlloc --> AllocSelf[set_allocation_to_self]
    PhaseAlloc --> AllocDept[set_allocation_to_department]

    subgraph Roles
        Coordinator[Coordinator - overall responsibility]
        Assignee[Assignee - current handler]
        Allocation[Allocation - phase-specific]
    end

    AssignUser --> Coordinator
    AssignUser --> Assignee
    AllocUser --> Allocation
```

## 8. Event Sourcing & Audit Trail

```mermaid
flowchart TD
    subgraph "Command Execution"
        Cmd[Command] --> Entity[Entity Method]
        Entity --> |@event decorator| Event[Named Event]
    end

    Event --> Exchange[RabbitMQ Exchange: minty_exchange]

    subgraph "Fan-out to Consumers"
        Exchange --> Logger[Logging Consumer<br/>→ audit_log table]
        Exchange --> CaseConsumer[Case Events Consumer<br/>→ integration sync]
        Exchange --> DocConsumer[Document Consumer<br/>→ preview/thumbnail/search]
        Exchange --> CommConsumer[Communication Consumer<br/>→ message processing]
        Exchange --> GeoConsumer[Geo Consumer<br/>→ feature sync]
        Exchange --> NotifyConsumer[Notification Consumer<br/>→ email dispatch]
        Exchange --> JobsConsumer[Jobs Consumer<br/>→ batch processing]
    end
```

## 9. Case Type Version Management

```mermaid
flowchart TD
    Create[Create Case Type] --> V1[Version 1 - Draft]
    V1 --> Config1[Configure:]
    Config1 --> Phases[Define Phases + Milestones]
    Config1 --> Rules[Add Rules per Phase]
    Config1 --> Attrs[Add Attributes]
    Config1 --> Terms[Set Lead Times]
    Config1 --> Results[Define Result Types]
    Config1 --> Activate1[Activate Version 1]

    Activate1 --> Active1[V1 Active - Cases use V1]
    Active1 --> NewVersion[Create Version 2]
    NewVersion --> Config2[Configure V2]
    Config2 --> Activate2[Activate Version 2]
    Activate2 --> Active2[V2 Active]
    Active2 --> |Existing cases keep V1| History[Version History]
    Active2 --> |New cases get V2| NewCases[New Cases]
```
