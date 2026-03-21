# CaseFabric (Cafienne) -- Merged Competitive Analysis

**Analyzed:** 2026-03-14
**Analyst method:** Codebase review, documentation scraping, live Docker walkthrough with screenshots

---

## 1. Sources Summary

### Codebase Files Analyzed

| File | Category |
|------|----------|
| `overview.md` | Product overview and architecture |
| `casefabric.md` | High-level competitor summary |
| `docs/architecture.md` | Engine architecture documentation |
| `docs/api-reference.md` | REST API reference |
| `docs/cmmn-modeling.md` | CMMN modeling concepts |
| `docs/extensions.md` | CaseFabric extensions beyond CMMN |
| `business-logic/browser-walkthrough-notes.md` | Live UI/IDE/API walkthrough |
| `business-logic/case-lifecycle.md` | Case and plan item state machines (Mermaid) |
| `business-logic/case-migration-flow.md` | Live definition migration flow (Mermaid) |
| `business-logic/event-sourcing-flow.md` | Event sourcing and CQRS flow (Mermaid) |
| `business-logic/sentry-evaluation.md` | Reactive criteria evaluation flow (Mermaid) |
| `business-logic/task-workflow.md` | Human task workflow sub-states (Mermaid) |
| `specs/api-layer/spec.md` | REST API architecture spec |
| `specs/business-identifiers/spec.md` | Custom case indexing spec |
| `specs/case-file-management/spec.md` | Case file data handling spec |
| `specs/case-migration/spec.md` | Live definition migration spec |
| `specs/case-modeling/spec.md` | Visual CMMN designer spec |
| `specs/case-team-authorization/spec.md` | Team and authorization spec |
| `specs/cmmn-engine/spec.md` | Core CMMN interpreter spec |
| `specs/event-sourcing-cqrs/spec.md` | Persistence architecture spec |
| `specs/human-task-workflow/spec.md` | Extended task lifecycle spec |
| `specs/mendix-integration/spec.md` | Mendix DCM add-on spec |
| `specs/multi-tenancy/spec.md` | Tenant isolation spec |
| `specs/multitenancy-authorization/spec.md` | Multi-level authorization spec |
| `specs/process-tasks/spec.md` | HTTP/Mail/PDF/Calculation integrations |
| `specs/task-workflow/spec.md` | Task workflow extensions spec |
| `specs/timer-service/spec.md` | Timer event scheduling spec |

### Documentation Sources Fetched

- https://casefabric.com (product website)
- https://guide.cafienne.io (reference guide -- architecture, API, CMMN, extensions)
- https://github.com/casefabric (GitHub organization -- 6 repositories)
- Swagger API at localhost:2027 (live instance)

### Screenshots Captured

**20 screenshots** covering the full product surface:

| # | Description |
|---|-------------|
| 01 | Cafienne homepage |
| 02 | Reference guide home |
| 03 | Case UI login page |
| 04 | Dex IDP login screen |
| 05 | Cases list (empty) |
| 06 | Dashboard (empty) |
| 07 | Tasks list (empty) |
| 08 | Start case -- definition selector |
| 09 | Travel request start form (multi-section) |
| 10 | Case detail -- plan view with stages/tasks |
| 11 | Case detail -- team tab |
| 12 | Case detail -- file tab (case file editor) |
| 13 | Task detail view with revoke/submit/save |
| 14 | Case Designer IDE -- repository browser |
| 15 | Case Designer -- travel request model open |
| 16 | Case Designer -- CMMN visual diagram |
| 17 | Swagger API documentation |
| 18 | "Why CaseFabric" guide page |
| 19 | Product overview page |
| 20 | MailCatcher -- travel approval email |

---

## 2. Product Overview

### What It Is

CaseFabric is a **Dynamic Case Management (DCM) platform** built on the CMMN 1.1 standard (Case Management Model and Notation). It is the most complete open-source CMMN 1.1 engine available -- the only platform claiming 100% compliance with the OMG specification. The product is a pure-play standards-based case management runtime, not a low-code platform.

### Who Makes It

**CaseFabric** (formerly Cafienne B.V., also known as SpinQ), based in **Urk, The Netherlands**. Dutch company, small team. Known customer: Visionplanner (Dutch accounting platform) for fiscal dossier automation.

### Product Suite

| Component | Description | Technology |
|-----------|-------------|-----------|
| **Case Engine** (`cafienne-engine`) | Core CMMN 1.1 interpreter | Java 11 + Scala 2.12, Apache Pekko (formerly Akka) |
| **Case Designer** (`cafienne-ide`) | Browser-based visual CMMN model editor | Browser-based, drag-and-drop |
| **CaseFabric UI** (`cafienne-ui`) | Generic React-based case/task interface | React, Material UI, JSON Schema Forms |
| **CMMN Test Framework** | TypeScript integration test suite | TypeScript |
| **Bounded Framework** | Scala/Akka DDD foundation library | Scala |
| **Getting Started** | Docker Compose demo environment | Docker |
| **DCM for Mendix** | Marketplace add-on embedding the engine in Mendix | Java actions |

### Licensing

- **Engine:** Mozilla Public License 2.0 (MPL) / AGPL-3.0 (dual license)
- **Commercial:** Separate commercial license (Batav Cafienne SLA) -- pricing not public
- AGPL effectively forces commercial users to either open-source modifications or buy a license

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | Apache Pekko persistent actors (formerly Akka), cluster sharding |
| Languages | Java 11 + Scala 2.12 |
| Build | SBT with Docker plugin |
| Write DB (Event Journal) | Cassandra or PostgreSQL (JDBC) |
| Read DB (Projections) | PostgreSQL via Slick ORM |
| API | Akka HTTP with Swagger 3.x / OAS 3.0 |
| Authentication | OpenID Connect (JWT) -- external IDP (ships with Dex) |
| Expressions | Spring Expression Language (SpEL), XPath |
| Serialization | Jackson + custom CafienneSerializer |
| Container | Docker (openjdk-11-buster) |
| UI | React + Material UI + React JSON Schema Forms |
| Ports | 2027 (API), 2081 (IDE), 3317 (UI), 5556 (IDP), 5431 (DB) |

### Codebase Metrics

- ~754 source files (559 Java + 195 Scala)
- ~54,000 lines of code
- 6 GitHub repositories, very few contributors
- Latest engine version: 1.1.35
- DB migrations: 6 schema versions (1.0.0 through 1.1.11)

---

## 3. Architecture Summary

### Core Design Patterns

1. **Event Sourcing** -- All case state changes stored as immutable events in a journal. Current state reconstructed by replaying events. Complete audit trail.

2. **CQRS (Command Query Responsibility Segregation)** -- Separate write side (event journal in Cassandra or PostgreSQL) and read side (denormalized projections in PostgreSQL). Commands go to actors; queries go to projection database.

3. **Persistent Actors** -- Each case instance is an independent Pekko persistent actor with its own event-sourced lifecycle, providing concurrent execution, fault isolation, and location transparency.

4. **Sentry Network** -- Reactive event propagation engine. Plan item transitions and case file changes trigger criteria evaluation, which cascades to activate/exit other plan items. Uses a TransitionCallStack to manage cascading evaluations.

5. **Declarative State Machines** -- Three state machine configurations (EventMilestone, TaskStage, CasePlan) defined as static lookup tables of `(State, Transition) -> (TargetState, Action)`.

### Module Structure

```
cafienne-engine/
  case-engine/          # Core CMMN interpreter (Java + Scala)
    actormodel/         # Pekko persistent actor infrastructure
    cmmn/               # CMMN implementation (definition + instance layers)
    humantask/          # Extended workflow task lifecycle
    processtask/        # Process task implementations (HTTP, Mail, PDF, Calculation)
    tenant/             # Multi-tenant actor model
    timerservice/       # Timer event scheduling
    json/               # JSON value handling
    infrastructure/     # Config, CQRS, JDBC, serialization
  case-service/         # REST API layer (Scala + Akka HTTP)
    api/                # Route definitions (cases, tasks, tenant, platform, repository)
    db/                 # Event materializers, query implementations, schema migrations
```

### CQRS Read-Side Tables

| Table | Purpose |
|-------|---------|
| `case_instance` | Case instance metadata (state, dates, tenant, parent/root) |
| `case_instance_definition` | Case definition snapshots (XML content) |
| `plan_item` | Current plan item state (all types) |
| `plan_item_history` | Audit trail of every plan item transition |
| `case_file` | Case file data (JSON blob) |
| `case_business_identifier` | Indexed business keys for cross-case search |
| `case_instance_role` | Defined roles per case instance |
| `case_instance_team_member` | Team members with roles and ownership |
| `task` | Human task inbox (state, assignee, owner, due date, I/O) |

### Authentication and Authorization

Four-level authorization model:
1. **Platform** -- Platform owners manage tenants (configured in local.conf)
2. **Tenant** -- Tenant owners manage users and roles within a tenant
3. **Case** -- Per-case teams with role-based access; case owners can override
4. **CMMN** -- Task-level authorization via performer roles

Authentication delegated to external OpenID Connect IDP (no internal user store).

---

## 4. Feature Inventory

### Core Features

| Spec | Description |
|------|-------------|
| **cmmn-engine** | Full CMMN 1.1 interpreter -- parses XML definitions, executes plan items, stages, sentries, and case file operations at runtime using persistent actors |
| **event-sourcing-cqrs** | Immutable event journal for all state changes with CQRS read projections; supports Cassandra and PostgreSQL backends with backoff-supervised async projection |
| **human-task-workflow** | Extended task lifecycle with sub-states (Unassigned/Assigned/Delegated) within CMMN Active, plus claim/assign/delegate/revoke operations |
| **task-workflow** | Workflow extensions including Four-Eyes pattern, Rendez-Vous pattern, mandatory output validation, partial save, dynamic assignment via SpEL, and due date expressions |
| **case-file-management** | Hierarchical typed data container with transitions (create/update/replace/delete) that trigger sentry evaluation; includes parameter mapping and business identifiers |
| **case-team-authorization** | Three-tier identity model (platform/tenant/case) with per-case team composition, role-based task authorization, and auto-team-add on delegation |
| **case-migration** | Live migration of running case instances to updated definitions without stopping; handles team, case file, and plan item changes with event-sourced audit |
| **multi-tenancy** | Built-in tenant isolation at every level with strict data separation, cross-tenant user registration, and tenant-scoped queries |
| **timer-service** | CMMN timer events with date/time, duration (ISO 8601), and cron (iCal4j) support; persisted to survive restarts (JDBC, Cassandra, or in-memory) |
| **process-tasks** | Automated task implementations: HTTPCall (REST integration), Mail/SMTP (email with templates/attachments/calendar), PDFReport (JasperReports), Calculation (map/filter/expression transforms) |
| **api-layer** | REST API via Akka HTTP with Swagger 3.x, command/query separation, JWT auth, Case-Last-Modified header for eventual consistency, anonymous access option |
| **business-identifiers** | Custom indexing of case file properties for cross-case/cross-type querying with equality, inequality, existence, and combination operators |
| **case-modeling** | Browser-based Case Designer (IDE) with drag-and-drop CMMN modeling, properties palette, expression editor, and direct deployment to engine |
| **multitenancy-authorization** | Comprehensive multi-level authorization with consent groups, cross-organization team composition, and platform update propagation |
| **mendix-integration** | DCM add-on embedding the case engine inside Mendix applications; entity-to-case-file mapping, execution queue, hot-reload of definitions |

### CMMN Implementation Coverage

**Plan Item Types:** Stage, HumanTask, ProcessTask, CaseTask, Milestone, TimerEvent, UserEvent, EventListener

**State Machines:** 3 variants -- EventMilestone (7 states), TaskStage (11 states with 19 transitions), CasePlan (7 states with reactivation)

**Sentries:** Entry criteria, exit criteria, reactivation criteria (extension); PlanItemOnPart and CaseFileItemOnPart; IfPart with SpEL/XPath guard expressions

**Case File:** Hierarchical items with 8 transitions; business identifier indexing; parameter mapping for task I/O

**Planning Table:** Discretionary items with applicability rules for runtime plan modification

**Expressions:** SpEL (primary) with rich context API (case, task, file, team, user); XPath (legacy)

---

## 5. Key Strengths

1. **Full CMMN 1.1 compliance** -- The only open-source engine claiming 100% coverage of the CMMN standard. This is a genuine differentiator for organizations mandating standards compliance.

2. **Event sourcing architecture** -- Complete, immutable audit trail of every state change. Enables replay, time-travel debugging, and compliance-grade history. Pekko actors provide fault isolation and horizontal scalability.

3. **Visual CMMN Designer** -- Browser-based IDE for designing case models with drag-and-drop. Includes shapes palette, properties editor, expression editor, and direct deployment to the engine. No equivalent in most competitors.

4. **Live case definition migration** -- Running cases can be updated to new definition versions without stopping. Handles team, case file, and plan item changes gracefully with full event-sourced audit. Critical for long-running government processes.

5. **Sentry network** -- Reactive event propagation engine with TransitionCallStack for cascading criteria evaluation. Clean architecture for complex dependencies between plan items and data changes.

6. **Workflow extensions** -- Practical task lifecycle (claim/assign/delegate/revoke) with Four-Eyes and Rendez-Vous authorization patterns. Due dates and dynamic assignment via SpEL expressions.

7. **Business identifiers** -- Cross-case, cross-type queryable metadata with equality, inequality, existence, and combination operators. Efficient domain-specific filtering.

8. **Multi-tenancy** -- Built-in at every level (platform/tenant/case) with strict data isolation and cross-tenant user registration.

9. **Process task integrations** -- Built-in HTTP, SMTP, PDF (JasperReports), and Calculation tasks as CMMN process tasks. Declarative parameter mapping between case file and integrations.

10. **Declarative state machines** -- Static lookup tables for state transitions are cleaner and more maintainable than embedded conditional logic. Three well-defined state machine variants cover all CMMN element types.

---

## 6. Key Weaknesses

1. **Complex technology stack** -- Java + Scala + Pekko/Akka + SBT is a high barrier for contributors and operators. Aging dependencies (Scala 2.12, Java 11, Dex v2.23.0).

2. **Tiny community** -- Only 6 GitHub repositories, very few contributors. No visible community activity. Last blog post from 2020. 0-1 GitHub stars on most repos.

3. **No document management** -- Case file is purely data-driven (JSON). No file attachments, no document preview, no document templates, no document versioning.

4. **Limited UI** -- Generic React UI (v0.7.3) is functional but basic. Material UI components without custom styling. No dashboard visualizations, charts, or KPIs. Not responsive, no mobile support.

5. **CMMN-only** -- No BPMN, DMN, or mixed process/case support. CMMN has lower market adoption and fewer practitioners than BPMN. Learning curve for the CMMN visual language.

6. **No Dutch government focus** -- No ZGW API support, no zaakgericht werken, no VNG/Common Ground integration. No confidentiality levels, no document checklists, no besluiten support.

7. **No low-code form builder** -- Forms generated from JSON Schema only (React JSON Schema Forms). No visual form designer. No custom UI components.

8. **No full-text search** -- Cases and tasks can be filtered but there is no full-text search capability. Business identifiers provide some filtering but are limited.

9. **Engine-only positioning** -- CaseFabric is fundamentally an engine, not a complete application. The Mendix integration confirms their strategy as an embeddable component, not a standalone product.

10. **No SaaS offering** -- Self-hosted only. No managed cloud service. Docker/Kubernetes deployment required.

11. **External IDP dependency** -- Requires separate identity provider setup (Dex, Keycloak). No built-in user management.

12. **Due dates are informational only** -- No automatic escalation, reminders, or SLA enforcement when deadlines pass. Timer events exist separately but are not integrated with task due dates.

---

## 7. Relevance to Procest

### Direct Competition

CaseFabric targets the **same problem space** as Procest -- dynamic case management for knowledge workers in organizations handling complex, unpredictable processes. Both are:
- Dutch companies with potential overlap in the NL government market
- Open-source with dual licensing models
- CMMN-aware for case lifecycle management
- Focused on adaptive case management (not rigid workflow)

### Where Procest Has Advantages

| Advantage | Details |
|-----------|---------|
| **Nextcloud integration** | Native file management, sharing, calendar, contacts, collaboration -- CaseFabric has none of this |
| **Document management** | Real file handling through Nextcloud; CaseFabric's case file is data-only (JSON) |
| **ZGW/Common Ground compliance** | Zaakgericht werken, VNG standards alignment -- CaseFabric has zero Dutch government specifics |
| **Lower barrier** | PHP-based, familiar to government developers; no Scala/Pekko/SBT complexity |
| **Workflow automation** | n8n provides BPMN-like visual workflow capabilities far exceeding CaseFabric's process tasks |
| **Complete application** | Full end-user application vs. engine-only; no separate tools needed |
| **Simpler deployment** | Single Nextcloud app install vs. multi-container microservices architecture |
| **Active ecosystem** | Part of larger Conduction app suite (OpenRegister, OpenCatalogi, OpenConnector, etc.) |
| **Accessible UI** | WCAG AA compliant with NL Design System theming; CaseFabric UI is basic and not accessible |
| **Confidentiality levels** | Built-in support; CaseFabric has none |

### Where CaseFabric Has Advantages

| Advantage | Details |
|-----------|---------|
| **CMMN 1.1 completeness** | 100% standard compliance vs. Procest's custom CMMN-inspired implementation |
| **Visual case designer** | Drag-and-drop IDE for case models; Procest has no equivalent |
| **Event sourcing** | Immutable audit trail with replay/time-travel; architecturally more robust than traditional CRUD |
| **Live case migration** | Update running cases to new definitions; Procest would need to build this |
| **Sentry network** | Reactive criteria propagation is more sophisticated than polling-based dependency checks |
| **Four-Eyes / Rendez-Vous** | Built-in authorization patterns for compliance; Procest would need to implement |
| **Horizontal scalability** | Pekko cluster sharding designed for distributed deployment; Nextcloud is single-instance by default |
| **Generic UI capability** | Can render any case model without custom UI development; useful for rapid prototyping |

### Strategic Assessment

CaseFabric is a **niche engine** with deep CMMN expertise but limited market traction. Its value lies in the architecture and patterns rather than as a competitive threat. Procest's integration with Nextcloud gives it a fundamentally different value proposition -- a complete, accessible case management application rather than a developer-oriented engine.

CaseFabric's strongest competitive angle would be in organizations that specifically mandate CMMN compliance and need an embeddable engine. For Dutch government case management (zaakafhandeling), Procest's ZGW compliance and Nextcloud integration are far more relevant.

---

## 8. Feature Gap Analysis

Features CaseFabric has that Procest should consider adopting or learning from:

### High Priority (adopt)

| Feature | CaseFabric Approach | Procest Recommendation |
|---------|---------------------|----------------------|
| **Formal state machines** | Three declarative state machine variants (EventMilestone, TaskStage, CasePlan) as static lookup tables | Implement declarative state transition tables instead of embedded conditional logic; cleaner and more testable |
| **Task workflow sub-states** | Unassigned/Assigned/Delegated within CMMN Active state, with claim/assign/delegate/revoke | Add sub-states to task Active state for proper assignment tracking; implement claim/revoke pattern for team-based work |
| **Business identifiers** | Case file properties indexed for cross-case/cross-type querying | Leverage OpenRegister faceting to provide cross-case search on key properties; ensure efficient indexing |
| **Four-Eyes / Rendez-Vous** | Engine-enforced authorization patterns for related tasks | Implement as configurable task relationships in Procest; essential for government compliance scenarios |
| **Partial save (store)** | Users can save task output without completing | Allow users to save intermediate work on tasks; improves UX for complex forms |
| **Mandatory output validation** | Required output parameters validated before task completion | Prevent incomplete task completion by validating required fields |

### Medium Priority (learn from)

| Feature | CaseFabric Approach | Procest Recommendation |
|---------|---------------------|----------------------|
| **Sentry pattern** | Reactive criteria evaluation with cascading transitions via TransitionCallStack | Consider reactive dependency evaluation for stage/task activation instead of polling |
| **Case definition migration** | Live update of running cases with team/file/plan migration | Build definition versioning and graceful migration for long-running government cases |
| **Dynamic assignment** | SpEL expressions for auto-assignment on task activation | Implement rule-based auto-assignment using Nextcloud user/group system |
| **Due date expressions** | Calculated due dates via SpEL at task activation | Add configurable due date calculation; integrate with Nextcloud calendar/notifications and add actual escalation behavior |
| **Fault handling / bubbling** | Failures propagate up stage hierarchy; reactivation criterion for recovery | Implement failure awareness at case/stage level; prevent premature completion when tasks fail |
| **Discretionary items** | Runtime plan modification by authorized case workers | Allow case workers to add optional tasks at their discretion; valuable for adaptive case management |
| **Anonymous access** | Configurable unauthenticated case creation | Support citizen-facing portals for case submission without Nextcloud login |

### Low Priority (note for future)

| Feature | CaseFabric Approach | Procest Recommendation |
|---------|---------------------|----------------------|
| **Visual case designer** | Browser-based drag-and-drop CMMN modeling | Consider a simplified visual case designer for business users, but using Nextcloud UI patterns rather than CMMN notation |
| **Event sourcing** | Immutable event journal with CQRS projections | Not needed as full architecture, but adopt the audit trail pattern (record every state change) for compliance |
| **Generic UI rendering** | Render any case model without custom UI | Less relevant; Procest's strength is purpose-built, accessible UI |
| **Mendix embedding pattern** | Case engine embedded in existing platform | Already parallel with Procest-in-Nextcloud; confirms the approach is valid |
| **Case-Last-Modified header** | Eventual consistency coordination for CQRS | Useful pattern if Procest adopts async processing; simple to implement |

### Features Procest Already Has That CaseFabric Lacks

| Feature | Procest | CaseFabric |
|---------|---------|-----------|
| Document management | Nextcloud files, versioning, sharing, preview | None (JSON data only) |
| Document checklists | Built-in | None |
| ZGW API compatibility | Yes | No |
| Confidentiality levels | Yes | No |
| WCAG AA accessibility | Yes (NL Design System) | No |
| Visual workflow automation | n8n integration | Limited process tasks only |
| Full-text search | OpenCatalogi/OpenRegister | No search capability |
| Calendar integration | Nextcloud Calendar | None |
| Email/notification integration | Nextcloud + n8n | Basic SMTP only |
| Mobile support | Nextcloud mobile apps | No mobile UI |
| User management | Nextcloud built-in | External IDP required |
| Besluiten (decisions) | Yes | Partial (CMMN decisions only) |
