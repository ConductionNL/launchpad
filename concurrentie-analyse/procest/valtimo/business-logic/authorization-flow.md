# Authorization Flow -- Valtimo PBAC

## Permission Evaluation Flow

```mermaid
flowchart TD
    REQUEST([User makes API request]) --> EXTRACT[Extract roles from\nKeycloak JWT token]
    EXTRACT --> RESOURCE[Determine resource type\ne.g. JsonSchemaDocument]
    RESOURCE --> ACTION[Determine action\ne.g. VIEW, MODIFY]

    ACTION --> FIND[Find all Permission records\nmatching roles + resource + action]
    FIND --> HAS_PERMS{Any matching\npermissions?}

    HAS_PERMS -->|None| DENY([ACCESS DENIED\ndefault deny])

    HAS_PERMS -->|One or more| EVAL_OR[Evaluate permissions\nwith OR logic]

    EVAL_OR --> PERM1{Permission 1}
    EVAL_OR --> PERM2{Permission 2}
    EVAL_OR --> PERMN{Permission N}

    PERM1 --> HAS_COND1{Has conditions?}
    PERM2 --> HAS_COND2{Has conditions?}
    PERMN --> HAS_CONDN{Has conditions?}

    HAS_COND1 -->|No conditions| PASS1([PASS])
    HAS_COND1 -->|Has conditions| EVAL_AND1

    HAS_COND2 -->|No conditions| PASS2([PASS])
    HAS_COND2 -->|Has conditions| EVAL_AND2

    HAS_CONDN -->|No conditions| PASSN([PASS])
    HAS_CONDN -->|Has conditions| EVAL_ANDN

    subgraph EVAL_AND1 [AND evaluation]
        direction TB
        C1A{Condition A} --> C1B{Condition B}
        C1B --> C1C{Condition C}
    end

    subgraph EVAL_AND2 [AND evaluation]
        direction TB
        C2A{Condition A} --> C2B{Condition B}
    end

    subgraph EVAL_ANDN [AND evaluation]
        direction TB
        CNA{Condition A}
    end

    EVAL_AND1 -->|All pass| PASS1
    EVAL_AND1 -->|Any fail| FAIL1([FAIL])
    EVAL_AND2 -->|All pass| PASS2
    EVAL_AND2 -->|Any fail| FAIL2([FAIL])
    EVAL_ANDN -->|All pass| PASSN
    EVAL_ANDN -->|Any fail| FAILN([FAIL])

    PASS1 --> GRANTED([ACCESS GRANTED\nany single OR match suffices])
    PASS2 --> GRANTED
    PASSN --> GRANTED

    FAIL1 --> CHECK_OTHERS{Other permissions\nstill passing?}
    FAIL2 --> CHECK_OTHERS
    FAILN --> CHECK_OTHERS
    CHECK_OTHERS -->|Yes| GRANTED
    CHECK_OTHERS -->|No remaining| DENY

    style REQUEST fill:#e1f5fe
    style GRANTED fill:#c8e6c9
    style DENY fill:#ffcdd2
    style PASS1 fill:#c8e6c9
    style PASS2 fill:#c8e6c9
    style PASSN fill:#c8e6c9
    style FAIL1 fill:#ffcdd2
    style FAIL2 fill:#ffcdd2
    style FAILN fill:#ffcdd2
```

## Query-Level Filtering (List Endpoints)

```mermaid
flowchart TD
    LIST_REQ([User requests case list]) --> ROLES[Extract user roles from JWT]
    ROLES --> FIND_PERMS[Find all VIEW_LIST permissions\nfor JsonSchemaDocument + user roles]

    FIND_PERMS --> BUILD[Build JPA Criteria predicates\nfrom permission conditions]

    BUILD --> PRED_EXAMPLE

    subgraph PRED_EXAMPLE [Example: Two permissions, OR-combined]
        direction TB
        PRED1["Permission 1 (role: caseworker)\nassigneeId == currentUser\nAND status != CLOSED"]
        PRED2["Permission 2 (role: manager)\n(no conditions - sees all)"]
        COMBINED["WHERE (assigneeId = 'user123' AND status != 'CLOSED')\n   OR (1=1)"]
        PRED1 --> COMBINED
        PRED2 --> COMBINED
    end

    PRED_EXAMPLE --> QUERY[Add predicates to JPA query\nWHERE clause]
    QUERY --> DB[(Database)]
    DB --> RESULTS[Only matching records returned]
    RESULTS --> PAGE[Paginated response\nwith correct totals]

    style LIST_REQ fill:#e1f5fe
    style PAGE fill:#c8e6c9
```

## Condition Types

```mermaid
flowchart LR
    subgraph FIELD [Field Condition]
        direction TB
        F1["field: assigneeId\noperator: ==\nvalue: \${currentUserIs}"]
    end

    subgraph EXPR [Expression Condition]
        direction TB
        E1["expression:\n\${document.content.department == 'HR'}"]
    end

    subgraph CONTAINER [Container Condition]
        direction TB
        CT1["resourceType: CaseDefinition\nconditions:\n  - field: name, == 'permits'"]
        CT2["Joins to related entity\nand checks conditions there"]
        CT1 --> CT2
    end

    FIELD --- COMBINED{AND logic\nwithin one permission}
    EXPR --- COMBINED
    CONTAINER --- COMBINED
```

## BPMN Bypass Rule

```mermaid
flowchart LR
    subgraph USER_CTX [User Context Present]
        U1[API Request] --> U2[Authorization Check] --> U3{Permitted?}
        U3 -->|Yes| U4[Execute]
        U3 -->|No| U5[Deny]
    end

    subgraph NO_CTX [No User Context - BPMN Automation]
        B1[Service Task executes] --> B2[No authorization check] --> B3[Execute directly]
    end

    style U5 fill:#ffcdd2
    style U4 fill:#c8e6c9
    style B3 fill:#c8e6c9
```

## Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| Deny-by-default | Security-first: no access without explicit permission |
| OR between permissions | Any valid permission is sufficient (least-restrictive wins) |
| AND within conditions | All conditions on a single permission must be met |
| Query-level enforcement | Performance: filter at DB level, not in application |
| BPMN bypass | Automated tasks have no user context; blocking would break processes |
| Keycloak role sync | Single source of truth for identity; Valtimo mirrors roles |
