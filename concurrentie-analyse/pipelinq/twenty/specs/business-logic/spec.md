---
competitor: twenty
analyzed_date: 2026-03-14
feature: business-logic-flows
---

# Business Logic Flows

## Overview

This spec documents the key business logic flows in Twenty CRM, visualized as Mermaid diagrams.

## 1. Deal Pipeline Flow

```mermaid
graph TD
    subgraph "Lead Capture"
        WEB[Website Form] --> CREATE_PERSON[Create Person]
        EMAIL_SYNC[Email Sync] --> MATCH[Match Participant]
        MATCH -->|New| CREATE_PERSON
        MATCH -->|Existing| LINK_PERSON[Link to Person]
        MANUAL[Manual Entry] --> CREATE_PERSON
    end

    subgraph "Qualification"
        CREATE_PERSON --> CREATE_OPP[Create Opportunity]
        CREATE_OPP --> ASSIGN[Assign Owner]
        ASSIGN --> QUALIFY{Qualify Lead}
        QUALIFY -->|Qualified| MEETING[Schedule Meeting]
        QUALIFY -->|Unqualified| DISCARD[Mark Lost]
    end

    subgraph "Pipeline Progression"
        MEETING --> PROPOSAL[Create Proposal]
        PROPOSAL --> NEGOTIATE[Negotiation]
        NEGOTIATE --> DECISION{Decision}
        DECISION -->|Won| WON[Mark Won]
        DECISION -->|Lost| LOST[Mark Lost]
    end

    subgraph "Post-Close"
        WON --> WF_TRIGGER[Workflow: Record Updated]
        WF_TRIGGER --> NOTIFY[Send Notification]
        WF_TRIGGER --> UPDATE_COMPANY[Update Company ARR]
        WF_TRIGGER --> CREATE_TASKS[Create Onboarding Tasks]
    end
```

## 2. Email Sync & Contact Creation Flow

```mermaid
sequenceDiagram
    participant Provider as Gmail/Microsoft
    participant Sync as Message Import Manager
    participant DB as Database
    participant Match as Contact Matcher
    participant Timeline as Timeline Activity

    loop Every sync interval
        Sync->>Provider: Fetch new messages (via API/IMAP)
        Provider-->>Sync: Message list + content
        Sync->>DB: Store messages + threads
        Sync->>Match: Match participants to contacts

        alt Participant matches existing Person
            Match->>DB: Link MessageParticipant to Person
        else Unknown participant + auto-create enabled
            Match->>DB: Create new Person record
            Match->>DB: Link MessageParticipant to new Person
            Match->>Timeline: Log contact creation
        else Unknown + auto-create disabled
            Match->>DB: Store participant email only
        end

        Sync->>Timeline: Log email activity on related records
    end
```

## 3. Workflow Execution Flow

```mermaid
graph TD
    subgraph "Trigger Phase"
        DB_EVENT[Database Event] --> CHECK_TRIGGER{Active Workflow?}
        CRON[Cron Schedule] --> CHECK_TRIGGER
        WEBHOOK[Webhook Call] --> CHECK_TRIGGER
        MANUAL[User Click] --> CHECK_TRIGGER
    end

    CHECK_TRIGGER -->|Yes| CREATE_RUN[Create WorkflowRun]
    CHECK_TRIGGER -->|No| IGNORE[Ignore]

    CREATE_RUN --> ENQUEUE[Enqueue Job]
    ENQUEUE --> EXECUTE[Start Execution]

    subgraph "Execution Phase"
        EXECUTE --> STEP{Next Step?}
        STEP -->|Yes| GET_TYPE{Step Type}

        GET_TYPE -->|RECORD_CRUD| CRUD[Execute CRUD Operation]
        GET_TYPE -->|SEND_EMAIL| EMAIL[Send Email]
        GET_TYPE -->|CODE| SANDBOX[Run in E2B Sandbox]
        GET_TYPE -->|HTTP_REQUEST| HTTP[Make HTTP Call]
        GET_TYPE -->|IF_ELSE| BRANCH{Evaluate Condition}
        GET_TYPE -->|ITERATOR| LOOP[Iterate Collection]
        GET_TYPE -->|DELAY| WAIT[Schedule Resume]
        GET_TYPE -->|FILTER| GATE{Pass Filter?}
        GET_TYPE -->|AI_AGENT| AI[Call LLM]
        GET_TYPE -->|FORM| FORM[Wait for User Input]

        CRUD --> STORE_OUTPUT[Store Step Output]
        EMAIL --> STORE_OUTPUT
        SANDBOX --> STORE_OUTPUT
        HTTP --> STORE_OUTPUT
        AI --> STORE_OUTPUT
        FORM --> STORE_OUTPUT

        BRANCH -->|True| TRUE_STEPS[Execute True Branch]
        BRANCH -->|False| FALSE_STEPS[Execute False Branch]
        TRUE_STEPS --> STORE_OUTPUT
        FALSE_STEPS --> STORE_OUTPUT

        LOOP --> LOOP_BODY[Execute Loop Body per Item]
        LOOP_BODY --> STORE_OUTPUT

        GATE -->|Pass| STORE_OUTPUT
        GATE -->|Fail| SKIP_REST[Skip Remaining Steps]

        WAIT --> RESUME_JOB[Resume via Job Queue]
        RESUME_JOB --> STORE_OUTPUT

        STORE_OUTPUT --> STEP
    end

    STEP -->|No| COMPLETE[Mark Completed]
    CRUD -->|Error| FAIL[Mark Failed]
    EMAIL -->|Error| FAIL
    SANDBOX -->|Error| FAIL
    HTTP -->|Error| FAIL
```

## 4. Custom Object Lifecycle

```mermaid
graph LR
    subgraph "Creation"
        INPUT[CreateObjectInput] --> VALIDATE[Validate Metadata]
        VALIDATE --> STORE_META[Store in objectMetadata]
        STORE_META --> CREATE_TABLE[Create DB Table]
        CREATE_TABLE --> DEFAULT_FIELDS[Add Default Fields]
        DEFAULT_FIELDS --> DEFAULT_RELATIONS[Add Default Relations]
        DEFAULT_RELATIONS --> REGEN_SCHEMA[Regenerate GraphQL Schema]
        REGEN_SCHEMA --> READY[Object Ready]
    end

    subgraph "Usage"
        READY --> GQL[GraphQL API Available]
        READY --> REST[REST API Available]
        READY --> MCP[MCP Tools Available]
        READY --> VIEWS[Views Configurable]
        READY --> WORKFLOWS[Workflow Triggers Available]
        READY --> SEARCH[Search Indexed]
        READY --> TIMELINE_ACT[Timeline Tracking Active]
    end

    subgraph "Customization"
        READY --> ADD_FIELDS[Add Custom Fields]
        READY --> ADD_RELATIONS[Add Relations to Other Objects]
        READY --> CONFIGURE_VIEWS[Configure Views]
        READY --> SET_PERMISSIONS[Set Permissions]
    end
```

## 5. Permission Evaluation Flow

```mermaid
graph TD
    REQUEST[API Request] --> AUTH[Extract Auth Token]
    AUTH --> USER[Identify User/Agent/API Key]
    USER --> ROLES[Load Assigned Roles]

    ROLES --> GLOBAL{Has Global Permission?}
    GLOBAL -->|canReadAll / canUpdateAll| BYPASS[Bypass Object Check]
    GLOBAL -->|No| OBJ_PERM[Check Object Permission]

    OBJ_PERM --> OBJ_ALLOWED{Object Access?}
    OBJ_ALLOWED -->|Denied| DENY_403[403 Forbidden]
    OBJ_ALLOWED -->|Allowed| FIELD_PERM[Apply Field Permissions]

    BYPASS --> FIELD_PERM

    FIELD_PERM --> FILTER_FIELDS[Filter Visible Fields]
    FILTER_FIELDS --> ROW_PERM[Apply Row-Level Predicates]
    ROW_PERM --> ADD_WHERE[Add WHERE Clauses]
    ADD_WHERE --> EXECUTE[Execute Query]
    EXECUTE --> MASK[Mask Hidden Fields in Response]
    MASK --> RESPONSE[Return Results]
```

## 6. Calendar Sync Flow

```mermaid
sequenceDiagram
    participant Provider as Google Calendar / CalDAV
    participant Sync as Calendar Import Manager
    participant DB as Database
    participant Match as Participant Matcher

    Sync->>Provider: Fetch calendar events
    Provider-->>Sync: Event list

    loop For each event
        Sync->>DB: Upsert CalendarEvent (by iCalUid)
        Sync->>DB: Store CalendarChannelEventAssociation

        loop For each participant
            Sync->>Match: Match email to Person
            alt Match found
                Match->>DB: Link CalendarEventParticipant to Person
            else No match + auto-create
                Match->>DB: Create Person from participant
                Match->>DB: Link CalendarEventParticipant
            end
        end
    end
```
