# CaseFabric (Cafienne) -- Competitive Analysis

**Analyzed:** 2026-03-14
**Product:** CaseFabric / Cafienne Engine
**Type:** Dynamic Case Management (DCM) platform -- pure CMMN 1.1 interpreter
**Website:** https://casefabric.com
**Documentation:** https://guide.cafienne.io
**GitHub:** https://github.com/casefabric (org), https://github.com/mcginkel/cafienne-engine (engine mirror)
**License:** Mozilla Public License 2.0 (engine code), AGPL-3.0 + commercial (product)
**Company:** CaseFabric (formerly Cafienne B.V.), Urk, The Netherlands
**Latest engine version:** 1.1.35 (Docker image), codebase targets JVM 11

---

## Product Summary

CaseFabric is the most complete open-source CMMN 1.1 Case Management Engine available. Built on Java/Scala with Akka actors and backed by event-sourced persistence (Cassandra or PostgreSQL), it is a pure-play standards-based case management runtime -- not a low-code platform. The engine interprets CMMN XML definitions at runtime, managing case lifecycle, plan items, sentries, case files, and team authorization.

## Product Suite

1. **Case Engine** (`cafienne-engine`) -- Core CMMN 1.1 interpreter. Java + Scala on Akka. Event-sourced persistent actors with CQRS read projections.
2. **Case Designer** (`cafienne-ide`) -- Browser-based visual CMMN model designer. Drag-and-drop plan items, sentries, case file structures.
3. **CaseFabric UI** (`cafienne-ui`) -- Generic React-based case/task interface. Uses JSON Schema Forms for task rendering.
4. **CMMN Test Framework** (`cmmn-test-framework`) -- TypeScript integration test suite with test cases for all CMMN constructs.
5. **Bounded Framework** (`bounded-framework`) -- Scala/Akka DDD foundation library.
6. **Getting Started** (`getting-started`) -- Docker Compose demo environment (engine + PostgreSQL + Dex IDP + IDE + UI).
7. **DCM for Mendix** -- Marketplace add-on embedding the engine inside Mendix applications.

---

## Architecture Overview

### Module Structure

The engine codebase (`cafienne-engine`) is organized as two SBT sub-projects:

```
cafienne-engine/
  case-engine/          # Core CMMN interpreter (Java + Scala)
    src/main/java/org/cafienne/
      actormodel/       # Akka persistent actor infrastructure
      cmmn/             # CMMN implementation
        definition/     # XML definition parsing (CaseDefinition, StageDefinition, etc.)
        instance/       # Runtime instances (Case, PlanItem, Stage, Task, etc.)
        actorapi/       # Commands and events for actor communication
        expression/     # SpEL and XPath expression engines
        repository/     # Definition loading (file-based, classpath)
        test/           # In-process test helpers
      humantask/        # Extended workflow task lifecycle
      processtask/      # Process task implementations (HTTP, Mail, PDF, etc.)
      tenant/           # Multi-tenant actor model
      timerservice/     # Timer event scheduling
      platform/         # Platform-level administration
      json/             # JSON value handling
      infrastructure/   # Config, CQRS, JDBC, serialization
    src/main/scala/org/cafienne/
      system/           # CaseSystem (Akka actor system bootstrap)
      authentication/   # JWT token verification
      infrastructure/   # Config readers, CQRS providers
      timerservice/     # Timer persistence (JDBC, Cassandra, in-memory)
  case-service/         # REST API layer (Scala + Akka HTTP)
    src/main/scala/org/cafienne/service/
      Main.scala        # Application entry point (HTTP server)
      api/              # REST route definitions
        cases/          # /cases endpoints
        tasks/          # /tasks endpoints
        tenant/         # /tenant endpoints
        platform/       # /platform endpoints
        repository/     # /repository endpoints
        anonymous/      # Anonymous access endpoints
        debug/          # Debug/events endpoints
        swagger/        # Swagger documentation
      db/
        materializer/   # Event -> projection writers (CQRS read side)
        query/          # Query implementations
        record/         # Slick table record classes
        schema/         # Database schema + migrations
```

### Technology Stack

| Layer | Technology |
|-------|-----------|
| Runtime | Akka Actor System (persistent actors, cluster sharding) |
| Language | Java 11 + Scala 2.12 |
| Build | SBT with Docker plugin |
| Write DB (Event Journal) | Akka Persistence with Cassandra or PostgreSQL (JDBC) |
| Read DB (Projections) | Slick ORM to PostgreSQL / HSQLDB / SQL Server |
| API Framework | Akka HTTP with Swagger 3.x annotations |
| Authentication | OpenID Connect (JWT validation) with configurable IDP |
| Expressions | Spring Expression Language (SpEL), XPath |
| Serialization | Jackson + custom CafienneSerializer for Akka |
| CI | CircleCI |
| Container | Docker (base: openjdk-11-buster) |
| Ports | 2027 (API), 9999 (JMX) |

### Codebase Metrics

- **Total source files:** ~754 (559 Java + 195 Scala)
- **Total lines of code:** ~54,000
- **Test coverage:** JUnit (engine), ScalaTest (service), TypeScript integration tests
- **DB migrations:** 6 schema versions (1.0.0 through 1.1.11)

---

## Database Schema (CQRS Read Side)

The engine uses event sourcing for the write side (Akka persistence journal) and maintains a denormalized query database for reads.

### Core Tables

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `case_instance` | id, case_name, state, tenant, parent_case_id, root_case_id, failures, case_input, case_output, created_on/by, last_modified/modified_by | Case instance records |
| `case_instance_definition` | caseInstanceId, name, element_id, content (XML), description | Case definition snapshots |
| `plan_item` | id, definition_id, stage_id, name, index, case_instance_id, current_state, history_state, transition, plan_item_type, repeating, required, task_input/output, mapped_input, raw_output | Current plan item state |
| `plan_item_history` | id, plan_item_id, eventType, sequenceNr, + same columns as plan_item | Audit trail (every transition) |
| `case_file` | case_instance_id, data (JSON) | Case file data blob |
| `case_business_identifier` | case_instance_id, name, value, active, path | Searchable business keys |
| `case_instance_role` | case_instance_id, role_name, assigned (boolean) | Defined roles per case |
| `case_instance_team_member` | case_instance_id, member_id, case_role, isTenantUser, isOwner, active | Team membership |
| `task` | id, case_instance_id, task_name, task_state, role, assignee, owner, due_date, task_input/output, task_model (JSON) | Human task inbox |

### Schema Migration
- Flyway-based through Slick migration DSL
- Versions: `QueryDB_1_0_0` through `QueryDB_1_1_11`
- Supports PostgreSQL, HSQLDB, SQL Server profiles

---

## CMMN 1.1 Implementation Depth

CaseFabric claims and demonstrates the most complete CMMN 1.1 implementation available. The depth is evident in the codebase:

### Plan Item Types Implemented

| Type | Definition Class | Instance Class | Notes |
|------|-----------------|----------------|-------|
| Stage | `StageDefinition` | `Stage` | Auto-complete, nesting, planning table |
| HumanTask | `HumanTaskDefinition` | `HumanTask` | Extended with workflow sub-states |
| ProcessTask | `ProcessTaskDefinition` | `ProcessTask` | HTTP, SMTP, PDF, Calculation |
| CaseTask | `CaseTaskDefinition` | `CaseTask` | Sub-case invocation |
| Milestone | `MilestoneDefinition` | `Milestone` | Entry criteria only |
| TimerEvent | `TimerEventDefinition` | `TimerEvent` | Cron, duration, date |
| UserEvent | `UserEventDefinition` | `UserEvent` | Manual event raising |
| EventListener | `EventListenerDefinition` | `EventListener` | Generic event |

### State Machines (3 variants in `StateMachine.java`)

**1. EventMilestone** (for events and milestones):
```
Null --(Create)--> Available
Available --(Occur)--> Completed
Available --(Suspend/ParentSuspend)--> Suspended
Available --(Terminate/ParentTerminate)--> Terminated
Suspended --(Resume/ParentResume)--> Available
```

**2. TaskStage** (for tasks and stages):
```
Null --(Create)--> Available
Available --(Enable)--> Enabled
Available --(Start)--> Active
Enabled --(ManualStart)--> Active
Enabled --(Disable)--> Disabled
Active --(Complete)--> Completed
Active --(Fault)--> Failed
Active --(Terminate)--> Terminated
Active --(Suspend)--> Suspended
Suspended --(Resume)--> Active
Failed --(Reactivate)--> Active
Disabled --(Reenable)--> Enabled
Any alive state --(Exit)--> Terminated
Completed/Terminated --(repeat if no entry criteria)--> new instance
```

**3. CasePlan** (top-level):
```
Null --(Create)--> Active
Active --(Complete)--> Completed
Active --(Terminate)--> Terminated
Active --(Fault)--> Failed
Active --(Suspend)--> Suspended
Completed/Terminated/Failed/Suspended --(Reactivate)--> Active
Completed/Terminated/Failed/Suspended --(Close)--> Closed
```

### All Transitions (19)
Close, Complete, Create, Disable, Enable, Exit, Fault, ManualStart, None, Occur, ParentResume, ParentSuspend, Reactivate, Reenable, Resume, Start, Suspend, Terminate

### All States (11)
Null, Active, Available, Closed, Completed, Disabled, Discarded, Enabled, Failed, Suspended, Terminated

### Sentries / Criteria
- **Entry criteria** with PlanItemOnPart + CaseFileItemOnPart + IfPart
- **Exit criteria**
- **Reactivation criteria** (CaseFabric extension for fault handling)
- `SentryNetwork` -- reactive event propagation across all criteria in a case
- `TransitionCallStack` -- manages cascading transitions

### Case File
- `CaseFileDefinition` -> `CaseFile` (hierarchical item tree)
- `CaseFileItemDefinition` with typed properties (JSON, XML, complex types)
- Transitions: Create, Replace, Update, Delete, AddChild, RemoveChild, AddReference, RemoveReference
- `BusinessIdentifier` -- properties marked for cross-case querying

### Planning Table
- `PlanningTableDefinition` -> discretionary items
- `ApplicabilityRuleDefinition` -- dynamic evaluation
- Runtime planning via API

### Expressions
- **SpEL** (primary) -- `spel/api/cmmn/` provides contexts for constraint, file, mapping, plan, team, workflow
- **XPath** (legacy)
- Used in: guard conditions, repetition rules, required rules, parameter mappings, task assignment, due dates

---

## REST API Endpoints

### Cases API (`/cases`)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/cases` | List cases (filter: tenant, state, caseName, identifiers, pagination, sorting) |
| GET | `/cases/stats` | Case statistics per definition |
| POST | `/cases` | Start case (definition, inputs, team, debug mode) |
| GET | `/cases/{id}` | Get full case instance |
| GET | `/cases/{id}/definition` | Get case definition XML |
| PUT | `/cases/{id}/debug/{mode}` | Toggle debug mode |
| GET/PUT/POST | `/cases/{id}/casefile/*` | Case file CRUD |
| GET | `/cases/{id}/caseplan` | Plan items |
| POST | `/cases/{id}/caseplan/{planItemId}/{transition}` | Trigger transition |
| GET | `/cases/{id}/discretionaryitems` | List discretionary items |
| POST | `/cases/{id}/discretionaryitems/plan` | Plan discretionary item |
| GET/PUT | `/cases/{id}/team` | Case team management |
| GET | `/cases/{id}/history/planitems` | Plan item history |
| POST | `/cases/{id}/migration` | Live definition migration |

### Tasks API (`/tasks`)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/tasks` | List tasks (filter: state, assignee, owner, caseName, tenant) |
| GET | `/tasks/user/count` | Assigned task count |
| POST | `/tasks/{id}` | Validate output |
| PUT | `/tasks/{id}` | Save output |
| PUT | `/tasks/{id}/claim` | Claim |
| PUT | `/tasks/{id}/revoke` | Revoke |
| PUT | `/tasks/{id}/assign` | Assign to user |
| PUT | `/tasks/{id}/delegate` | Delegate |
| POST | `/tasks/{id}/complete` | Complete with output |

### Other APIs
| Path | Description |
|------|-------------|
| `/tenant/*` | Tenant user management (owners, users, roles) |
| `/platform/*` | Platform administration |
| `/repository/*` | Definition deployment and listing |
| `/identifiers/*` | Business identifier queries |
| `/debug/*` | Raw event stream access |
| `/status` | Health check |
| `/anonymous/*` | Anonymous case requests (configurable) |

---

## Key Extension Points

### CaseFabric Extensions Beyond CMMN

1. **Workflow sub-states** -- HumanTask gets Unassigned/Assigned/Delegated within CMMN Active state
2. **Claim/Assign/Delegate/Revoke** -- task lifecycle operations with owner tracking
3. **Four-Eyes principle** -- enforce different users for related tasks
4. **Rendez-Vous principle** -- enforce same user for related tasks
5. **Fault bubbling** -- failures propagate up the stage hierarchy (configurable)
6. **Reactivation criterion** -- model-driven recovery from Failed state
7. **Business identifiers** -- case file properties indexed for cross-case search
8. **Dynamic assignment** -- SpEL expressions for automatic task assignment
9. **Due date expressions** -- calculated due dates on human tasks
10. **Case definition migration** -- live migration of running cases to updated definitions
11. **Anonymous requests** -- configurable unauthenticated case creation
12. **Debug mode** -- per-case debug event generation

### Process Task Implementations
- **HTTPCall** -- REST/HTTP integration
- **Mail/SMTP** -- Email sending with templates, attachments, calendar invites
- **PDFReport** -- JasperReports PDF generation
- **Calculation** -- Map/Filter/Expression-based data transformation
- **InlineSubProcess** -- embedded sub-process execution

---

## Docker Deployment Architecture

From `getting-started/docker-compose.yml`:

```
Services:
  case-engine         (cafienne/engine:1.1.35)     -- port 2027
  case-engine-db      (postgres:16)                -- port 5431
  case-designer       (cafienne/ide:latest)        -- port 2081
  case-user-interface (cafienne/cafienne-ui:0.7.3) -- port 3317
  idp                 (dex:v2.23.0)                -- port 5556
  mailcatcher         (mailcatcher:latest)         -- port 1080
```

Environment variables for configuration:
- `EVENT_DB_*` -- Event journal database connection
- `QUERY_DB_*` -- Query projection database connection
- `CAFIENNE_OIDC_*` -- OpenID Connect IDP configuration
- `CAFIENNE_PLATFORM_OWNERS` -- Platform admin user IDs
- `CAFIENNE_CMMN_DEFINITIONS_PATH` -- Definition files location

---

## Competitive Comparison: CaseFabric vs Procest

### Strengths of CaseFabric
1. **Full CMMN 1.1 compliance** -- only engine claiming 100% coverage
2. **Event sourcing + CQRS** -- complete audit trail, replay, time-travel
3. **Case definition migration** -- live update of running cases
4. **Sentry network** -- reactive event propagation for complex dependencies
5. **Multi-tenancy** -- built-in tenant isolation at every level
6. **Process tasks** -- HTTP/SMTP/PDF as CMMN process tasks
7. **Workflow extensions** -- claim/assign/delegate with Four-Eyes/Rendez-Vous
8. **Business identifiers** -- cross-case queryable metadata
9. **Visual IDE** -- drag-and-drop CMMN designer
10. **Cluster support** -- Akka cluster sharding for horizontal scaling

### Weaknesses of CaseFabric
1. **Complex tech stack** -- Java + Scala + Akka + SBT = high barrier
2. **No low-code** -- purely model-driven, no form builder beyond JSON Schema
3. **Tiny community** -- 0-1 GitHub stars, few contributors
4. **CMMN-only** -- no BPMN, DMN, or mixed process/case
5. **Aging dependencies** -- Akka 2.x, Scala 2.12, Java 11
6. **No document management** -- case file is data-only
7. **Limited UI** -- generic React UI with JSON Schema forms
8. **No Dutch government focus** -- no VNG/Common Ground/ZGW integration
9. **No SaaS offering** -- self-hosted only

### Where Procest Has Advantages
1. **Nextcloud integration** -- files, calendar, contacts, n8n workflows built in
2. **Document management** -- native file handling through Nextcloud
3. **Lower barrier** -- PHP-based, familiar to Dutch government developers
4. **Standards alignment** -- VNG/Common Ground/ZGW compliance potential
5. **Low-code** -- OpenRegister schema-driven development
6. **Workflow automation** -- n8n provides BPMN-like capabilities
7. **Simpler deployment** -- single Nextcloud app install
8. **Active ecosystem** -- part of larger Conduction app suite

### Features to Consider Adopting from CaseFabric
1. **Formal state machines** -- 3-tier state machine design is clean and well-tested
2. **Sentry pattern** -- reactive criteria evaluation for stage/task activation
3. **Business identifiers** -- cross-case metadata indexing for search
4. **Task workflow sub-states** -- Unassigned/Assigned/Delegated within Active
5. **Case migration** -- ability to update definitions for running cases
6. **Four-Eyes / Rendez-Vous** -- workflow authorization patterns
7. **Fault handling** -- failure bubbling and reactivation criteria

---

## Documentation Structure

- `overview.md` -- This file
- `specs/` -- Feature specifications (OpenSpec format)
  - `cmmn-engine/` -- Core CMMN interpreter
  - `event-sourcing-cqrs/` -- Persistence architecture
  - `human-task-workflow/` -- Extended task lifecycle
  - `case-file-management/` -- Case file data handling
  - `case-team-authorization/` -- Team and role management
  - `process-tasks/` -- HTTP/Mail/PDF/Calculation integrations
  - `case-migration/` -- Live definition migration
  - `multi-tenancy/` -- Tenant isolation
  - `timer-service/` -- Timer event scheduling
  - `api-layer/` -- REST API design
- `business-logic/` -- Mermaid diagrams for key flows
