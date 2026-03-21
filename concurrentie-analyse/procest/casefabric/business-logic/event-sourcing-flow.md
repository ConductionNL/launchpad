# Event Sourcing and CQRS Flow

## Command Processing and Event Persistence

```mermaid
sequenceDiagram
    participant Client
    participant HTTP as Akka HTTP
    participant Router as CaseMessageRouter
    participant Actor as Case Actor
    participant Journal as Event Journal
    participant Projector as ProjectionWriter
    participant QueryDB as Query Database

    Client->>HTTP: REST Request
    HTTP->>HTTP: JWT Validation
    HTTP->>HTTP: Create Command
    HTTP->>Router: Route command to actor

    alt Actor not in memory
        Router->>Journal: Replay events for case ID
        Journal-->>Router: Event stream
        Router->>Actor: Create actor, apply events
    end

    Router->>Actor: Deliver command
    Actor->>Actor: Validate command
    Actor->>Actor: Process business logic
    Actor->>Actor: Generate events

    loop For each generated event
        Actor->>Journal: Persist event
        Journal-->>Actor: Persistence confirmed
        Actor->>Actor: Apply event to in-memory state
    end

    Actor-->>HTTP: Command response
    HTTP-->>Client: HTTP response

    Note over Journal,QueryDB: Asynchronous projection
    Journal->>Projector: Tagged event stream (polling)
    Projector->>Projector: Transform event to record
    Projector->>QueryDB: Upsert/Insert records
    Projector->>Projector: Update offset
```

## Actor Recovery

```mermaid
flowchart TD
    A[Command arrives for Case X] --> B{Actor in memory?}
    B -->|Yes| C[Deliver command]
    B -->|No| D[Create new actor instance]
    D --> E[Replay events from journal]
    E --> F{More events?}
    F -->|Yes| G[Apply event to state]
    G --> H[Skip sentry evaluation during recovery]
    H --> F
    F -->|No| I[Recovery complete]
    I --> J[Apply optional snapshot]
    J --> C
    C --> K[Process command normally]

    L[Idle timeout reached] --> M[Actor receives PoisonPill]
    M --> N[Actor removed from memory]
    N --> O[Next command triggers recovery]
```

## CQRS Projection Architecture

```mermaid
flowchart LR
    subgraph "Write Side"
        A[Case Actor 1]
        B[Case Actor 2]
        C[Case Actor N]
        D[(Event Journal)]
    end

    subgraph "Projection Layer"
        E[CaseProjectionsWriter]
        F[TenantProjectionsWriter]
        G[OffsetStorage]
    end

    subgraph "Read Side"
        H[(Query Database)]
        I[case_instance]
        J[plan_item]
        K[plan_item_history]
        L[case_file]
        M[task]
        N[case_business_identifier]
        O[case_instance_team_member]
    end

    A --> D
    B --> D
    C --> D
    D --> E
    D --> F
    E --> G
    F --> G
    E --> H
    F --> H
    H --> I
    H --> J
    H --> K
    H --> L
    H --> M
    H --> N
    H --> O
```

## Event Stream with Backoff Restart

```mermaid
flowchart TD
    A[Start event consumer] --> B[Read events from journal]
    B --> C{Process event}
    C -->|Success| D[Update offset]
    D --> B
    C -->|Failure| E[Backoff restart]
    E --> F{Restart count < max?}
    F -->|Yes| G[Wait random backoff]
    G --> H[Increment restart count]
    H --> B
    F -->|No| I[Stop consumer]
    I --> J[Health monitor reports unhealthy]

    style I fill:#f66
    style J fill:#f66
```
