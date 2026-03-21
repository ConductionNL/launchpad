# CMMN Case Instance Lifecycle

## Case Instance State Machine

```mermaid
stateDiagram-v2
    [*] --> Active: createCaseInstance()
    Active --> Completed: completeCaseInstance() / auto-complete
    Active --> Terminated: terminateCaseInstance() / exit criteria
    Active --> Suspended: (via plan items suspending)
    Suspended --> Active: (via plan items resuming)
    Completed --> Active: reactivateCaseInstance()
    Completed --> [*]
    Terminated --> [*]
```

## Plan Item Instance State Machine (CMMN 1.1 + Flowable Extensions)

```mermaid
stateDiagram-v2
    [*] --> Available: create
    [*] --> Unavailable: create (with available condition)
    Unavailable --> Available: initiate (condition met)
    Available --> Unavailable: dismiss (condition unmet)
    Available --> Active: start (auto-activation)
    Available --> Enabled: enable (manual activation)
    Available --> Disabled: disable
    Available --> WaitingForRepetition: (repetition waiting)
    Enabled --> Active: manualStart
    Enabled --> Disabled: disable
    Disabled --> Enabled: reenable
    Active --> Completed: complete
    Active --> Terminated: terminate / exit
    Active --> Failed: fault
    Active --> Suspended: suspend / parentSuspend
    Active --> AsyncActiveLeave: async-leave-active
    Suspended --> Active: resume / parentResume
    AsyncActiveLeave --> Completed: complete (async)
    AsyncActiveLeave --> Terminated: terminate (async)
    WaitingForRepetition --> Available: (sentry + repetition rule)
    WaitingForRepetition --> Terminated: terminate
    Completed --> Active: reactivate
    Completed --> [*]
    Terminated --> [*]
    Failed --> [*]

    state "async-active" as AsyncActive
    [*] --> AsyncActive: async-activate
    AsyncActive --> Active: async-activate (completion)
    state "async-active-leave" as AsyncActiveLeave
```

## Case Instance Creation Flow

```mermaid
sequenceDiagram
    participant Client
    participant RuntimeService
    participant CaseInstanceBuilder
    participant Agenda
    participant CriteriaEvaluator

    Client->>RuntimeService: createCaseInstanceBuilder()
    RuntimeService-->>Client: CaseInstanceBuilder

    Client->>CaseInstanceBuilder: caseDefinitionKey("myCase")
    Client->>CaseInstanceBuilder: variables(map)
    Client->>CaseInstanceBuilder: businessKey("BK-001")
    Client->>CaseInstanceBuilder: start()

    CaseInstanceBuilder->>Agenda: planInitPlanModelOperation()
    Agenda->>Agenda: Create case instance entity
    Agenda->>Agenda: Initialize plan model (root stage)
    Agenda->>Agenda: Create plan item instances for all items
    Agenda->>CriteriaEvaluator: evaluateCriteria()
    CriteriaEvaluator->>CriteriaEvaluator: Evaluate entry sentries
    CriteriaEvaluator->>Agenda: planActivatePlanItemInstance (for matching items)
    Agenda->>Agenda: Start auto-start items
    Agenda-->>Client: CaseInstance
```

## Sentry Evaluation Flow

```mermaid
flowchart TD
    A[Plan Item Transition Occurs] --> B[Queue PlanItemLifeCycleEvent]
    B --> C[planEvaluateCriteriaOperation]
    C --> D{For each plan item}
    D --> E{Has entry criteria?}
    E -->|Yes| F[Evaluate OnParts]
    E -->|No| G{Auto-start?}
    F --> H{OnParts satisfied?}
    H -->|Yes| I[Evaluate IfPart condition]
    H -->|No| D
    I -->|True| J[Entry criterion satisfied]
    I -->|False| D
    J --> K{Plan item type?}
    K -->|Manual activation| L[planEnablePlanItemInstance]
    K -->|Auto activation| M[planActivatePlanItemInstance]
    K -->|Milestone| N[planOccurPlanItemInstance]
    G -->|Yes| M
    G -->|No| D

    D --> O{Has exit criteria?}
    O -->|Yes| P[Evaluate exit OnParts + IfPart]
    P -->|Satisfied| Q[planExitPlanItemInstance]
    O -->|No| D

    D --> R{Check case completeness}
    R --> S{All required items complete?}
    S -->|Yes| T{Auto-complete enabled?}
    T -->|Yes| U[planCompleteCaseInstance]
    T -->|No| V[Set isCompletable=true]
    S -->|No| W[Continue execution]
```

## Task Lifecycle Flow

```mermaid
sequenceDiagram
    participant CasePlanItem
    participant HumanTaskBehavior
    participant TaskService
    participant User

    CasePlanItem->>HumanTaskBehavior: execute(planItemInstance)
    HumanTaskBehavior->>TaskService: createTask()
    HumanTaskBehavior->>TaskService: setAssignee/setCandidates
    HumanTaskBehavior->>TaskService: saveTask()
    Note over TaskService: Task state: CREATED

    User->>TaskService: claim(taskId, userId)
    Note over TaskService: Task state: CLAIMED

    User->>TaskService: startProgress(taskId, userId)
    Note over TaskService: Task state: IN_PROGRESS

    alt Task suspended
        User->>TaskService: suspendTask(taskId, userId)
        Note over TaskService: Task state: SUSPENDED
        User->>TaskService: activateTask(taskId, userId)
        Note over TaskService: Task state: IN_PROGRESS
    end

    alt Task delegated
        User->>TaskService: delegateTask(taskId, newUserId)
        Note over TaskService: DelegationState: PENDING
        User->>TaskService: resolveTask(taskId)
        Note over TaskService: DelegationState: RESOLVED
    end

    User->>TaskService: complete(taskId, variables)
    TaskService->>CasePlanItem: triggerPlanItemInstance()
    Note over CasePlanItem: Plan item -> COMPLETED
    CasePlanItem->>CasePlanItem: evaluateCriteria()
```
