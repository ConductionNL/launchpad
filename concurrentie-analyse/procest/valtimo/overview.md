# Valtimo (GZAC) -- Competitive Analysis Overview

## Executive Summary

Valtimo is a **process automation and case management platform** built on top of **Operaton** (formerly Camunda 7), developed by **Ritense BV** (Netherlands). It targets Dutch government organizations for *zaakgericht werken* (case-oriented work) under the Common Ground/ZGW API ecosystem. The platform is licensed under **EUPL 1.2**.

The codebase comprises two main repositories:
- **valtimo-backend-libraries** -- ~4,100 files, Kotlin/Java (Spring Boot + JPA + Operaton/Camunda)
- **valtimo-frontend-libraries** -- ~2,600 files, Angular + TypeScript + Carbon Design System

---

## Architecture

### Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend Language** | Kotlin + Java (mixed, trending toward Kotlin) |
| **Backend Framework** | Spring Boot 3.x + Spring Security + Spring Data JPA |
| **Process Engine** | Operaton (Camunda 7 fork) -- BPMN 2.0 + DMN |
| **Database** | MySQL / PostgreSQL (via Liquibase migrations) |
| **ORM** | Hibernate 6 + Hypersistence JSON type |
| **Auth** | Keycloak (OIDC) -- dedicated `keycloak-iam` module |
| **Frontend Framework** | Angular 16+ |
| **UI Components** | IBM Carbon Design System |
| **Build System** | Gradle (backend), Angular CLI + Webpack (frontend) |
| **Messaging** | RabbitMQ (outbox pattern) |
| **Document Generation** | SmartDocuments + local PDF generation |
| **Mail** | Mandrill, Flowmailer, WordPress Mail, local SMTP |

### Module Organization (Backend -- 50+ modules)

```
valtimo-backend-libraries/
  app/gzac/                    -- Application bootstrap
  case/                        -- Case/Document management (core)
  core/                        -- Process engine integration, tasks, users
  authorization/               -- RBAC permission system
  process-document/            -- Process-Document association bridge
  process-link/                -- Links BPMN activities to actions
  plugin/                      -- Plugin system (annotation-based)
  plugin-valtimo/              -- Valtimo-specific plugin extensions
  form/                        -- Form.io form management
  form-flow/                   -- Multi-step wizard forms
  form-view-model/             -- Dynamic form view models
  dashboard/                   -- Configurable dashboards with widgets
  search/                      -- Search fields + list columns
  notes/                       -- Case notes/annotations
  milestones/                  -- Process milestone tracking
  audit/                       -- Audit trail (event sourcing)
  logging/                     -- Structured logging
  localization/                -- i18n translations
  data-provider/               -- External data providers
  value-resolver/              -- Dynamic value resolution (doc:, pv:, etc.)
  document-generation/         -- PDF + SmartDocuments generation
  resource/                    -- File storage (local + S3 + temp)
  inbox/                       -- CloudEvent-based inbox
  outbox/                      -- Outbox pattern (RabbitMQ)
  mail/                        -- Email (Mandrill, Flowmailer, etc.)
  keycloak-iam/                -- Keycloak IAM integration
  command-handling/            -- Command pattern infrastructure
  contract/                    -- Shared contracts/interfaces
  changelog/                   -- Database migration management
  exporter/                    -- Case definition export
  importer/                    -- Case definition import
  web/                         -- SSE + shared web infrastructure
  exact-plugin/                -- Exact Online integration
  zgw/                         -- ZGW API ecosystem:
    catalogi-api/              --   Zaaktype catalog
    documenten-api/            --   Document management
    zaken-api/                 --   Case management
    objecten-api/              --   Objects API
    objecttypen-api/           --   Object types API
    notificaties-api/          --   Notifications
    besluiten-api/             --   Decisions API
    verzoek/                   --   Request handling
    portaaltaak/               --   Portal tasks
    zaakdetails/               --   Case details
    object-management/         --   Object management config
```

### Module Organization (Frontend -- 39 libraries)

```
projects/valtimo/
  case/                  -- Case list, detail, tabs, search
  case-management/       -- Case definition management
  case-migration/        -- Case migration tools
  task/                  -- Task list, detail, assignment
  task-management/       -- Task management config
  process/               -- BPMN process viewer/modeler
  process-link/          -- Process link configuration
  process-management/    -- Process definition management
  dashboard/             -- Dashboard display
  dashboard-management/  -- Dashboard configuration
  form/                  -- Form.io rendering
  form-flow-management/  -- Form flow configuration
  form-management/       -- Form management
  form-view-model/       -- Dynamic form view models
  plugin/                -- Plugin configuration UI
  plugin-management/     -- Plugin management
  document/              -- Document handling
  object/                -- Object list/detail
  object-management/     -- Object type management
  access-control/        -- Permission UI
  access-control-management/ -- Role/permission management
  zgw/                   -- ZGW plugin UIs
  milestone/             -- Milestone tracking
  decision/              -- DMN decision tables
  logging/               -- Audit log viewer
  analyse/               -- Process analytics
  account/               -- User account
  keycloak/              -- Keycloak integration
  resource/              -- File management
  shared/                -- Shared components
  components/            -- Carbon-based UI components
  layout/                -- App layout/shell
  bootstrap/             -- App initialization
  security/              -- Auth guards
  sse/                   -- Server-Sent Events
  swagger/               -- API docs
  iko/                   -- IKO integration
  migration/             -- Data migration
  choice-field/          -- Choice field management
```

---

## Core Domain Model

### Key Entities

| Entity | Table | Description |
|--------|-------|-------------|
| `JsonSchemaDocument` | `json_schema_document` | The "case" -- stores JSON content validated against a schema |
| `JsonSchemaDocumentDefinition` | `json_schema_document_definition` | Case type definition with JSON Schema |
| `InternalCaseStatus` | `internal_case_status` | Status labels with color and ordering |
| `CaseTag` | `case_tag` | Colored tags for cases |
| `CaseTab` | `case_tab` | Tab configuration per case type (STANDARD, FORMIO, CUSTOM, WIDGETS) |
| `Note` | `note` | Free-text notes on cases |
| `Permission` | `permission` | RBAC permissions with conditions |
| `Role` | `role` | Named roles (linked to Keycloak groups) |
| `PluginDefinition` | `plugin_definition` | Plugin type definitions |
| `PluginConfiguration` | `plugin_configuration` | Plugin instances with encrypted properties |
| `PluginActionDefinition` | N/A | Available actions per plugin |
| `ProcessLink` | N/A | Links BPMN activities to plugin actions / forms |
| `FormFlowDefinition` | `form_flow_definition` | Multi-step form wizard definitions |
| `FormFlowInstance` | N/A | Running form flow instances |
| `Dashboard` | `dashboard` | Dashboard containers |
| `WidgetConfiguration` | `dashboard_widget_configuration` | Widget configs with data source + display type |
| `AuditRecord` | N/A | Event-sourced audit trail |
| `Milestone` / `MilestoneSet` | N/A | Process milestone tracking |
| `ZaakInstanceLink` | N/A | Links documents to external ZGW zaak instances |

### Document (Case) Entity Fields

The central entity `JsonSchemaDocument` contains:
- `id` (UUID) -- unique identifier
- `content` (JSON) -- flexible JSON document validated against schema
- `documentDefinitionId` -- reference to case type
- `version` (optimistic locking)
- `createdOn`, `modifiedOn`, `createdBy`
- `sequence` (auto-incrementing per definition)
- `internalStatus` -- case status reference
- `caseTags` (many-to-many) -- colored labels
- `assigneeId`, `assigneeFullName` -- case-level assignment
- `documentRelations` (JSON) -- parent/child/related document links
- `relatedFiles` (JSON) -- attached files with metadata

---

## Key Feature Analysis

### 1. Process Engine (Operaton/Camunda 7)
Valtimo deeply integrates with the Operaton BPMN engine. This is the fundamental architectural difference from Procest -- processes are modeled as BPMN 2.0 XML and executed by the Operaton engine, with service tasks, user tasks, timers, gateways, event listeners, etc. This provides:
- Visual BPMN process modeler (integrated in frontend)
- BPMN execution with task assignment
- Process migration between versions
- Process heatmaps (count + duration)
- DMN decision tables

### 2. Plugin System
Annotation-based (`@Plugin`, `@PluginAction`, `@PluginProperty`) system for extending functionality. Plugins are discovered at startup, stored in DB. Properties can be encrypted. Plugins expose actions that can be linked to BPMN activities via ProcessLinks. ZGW APIs are implemented as plugins.

### 3. Authorization (PBAC)
Policy-Based Access Control with `Role`, `Permission`, and `ConditionContainer`. Permissions specify resource type, action, and conditions. Conditions can be field-based, expression-based, or container-based. The system generates JPA Criteria predicates for query-level filtering.

### 4. ZGW Integration
Deep integration with the Dutch government ZGW API standard via plugins:
- **Zaken API** -- create/update zaak, set status, resultaat, eigenschappen, rollen
- **Documenten API** -- upload/download documents, link to cases
- **Catalogi API** -- zaaktype, statustype, resultaattype lookups
- **Objecten/Objecttypen API** -- generic object storage
- **Notificaties API** -- webhook notifications
- **Besluiten API** -- decision/ruling management
- **Portaaltaak** -- citizen portal task management

### 5. Form System
- **Form.io** -- primary form rendering engine
- **Form Flow** -- multi-step wizard forms with step navigation, back, save, breadcrumbs
- **Form View Model** -- dynamic form data binding via value resolvers
- **Intermediate Save** -- save form progress before completing

### 6. Dashboard System
Configurable dashboards with widgets. Each widget has:
- Data source (pluggable via `WidgetDataSourceResolver`)
- Display type (chart, table, etc.)
- Properties for both data source and display
- Optional URL link

### 7. Case Management
- Case list with configurable columns, search, filtering
- Case detail with configurable tabs (summary, progress, audit, documents, notes, formio, custom, widgets)
- Case status management with colors
- Case tags with colors and ordering
- Case assignee (at document level)
- Case import/export (ZIP archives)
- Case migration between definition versions
- Case CSV export
- Quick search / stored searches

---

## Key Technical Insights

1. **Event-Driven Architecture** -- Uses Spring's `AbstractAggregateRoot` for domain events (document created/modified, file added/removed), plus a separate audit event system, outbox pattern (RabbitMQ), and inbox (CloudEvents).

2. **JSON Schema Validation** -- Document content is validated against JSON Schema (using `org.everit.json.schema`). Schema is deployed as part of the case definition.

3. **Value Resolver Pattern** -- Pluggable value resolvers (`doc:`, `pv:`, etc.) enable dynamic data binding between forms, process variables, and document fields.

4. **Optimistic Concurrency** -- Uses JPA `@Version` for optimistic locking on documents, with explicit deadlock prevention tests.

5. **Auto-Deployment** -- Case definitions, process definitions, plugins, permissions, roles, dashboards, form flows, and search fields can all be auto-deployed from configuration files on startup.

6. **Process-Document Bridge** -- The `process-document` module bridges Operaton process instances with JSON documents, maintaining associations and enabling document-aware process execution.

---

## Comparison: Valtimo vs Procest

### What Valtimo Has That Procest Also Has
- Case list with search/filtering
- Case detail views
- Task management and assignment
- Document/file attachments
- Case status tracking
- n8n automation (Procest) vs BPMN automation (Valtimo)
- NL Design theming (Procest) vs Carbon Design (Valtimo)
- Sub-case/related document management
- Audit trail
- Dashboard capabilities
- Notes/annotations on cases
- Import/export functionality
- Deadline/due date tracking on tasks

### What Valtimo Has That Procest Does NOT Have
1. **Full BPMN 2.0 Process Engine** -- Visual process modeler, service tasks, timers, gateways, events, process migration, process heatmaps
2. **DMN Decision Tables** -- Business rule engine for automated decisions
3. **Plugin System** -- Annotation-based extensibility framework with encrypted properties
4. **Deep ZGW Integration** -- 10+ ZGW API plugins (Zaken, Documenten, Catalogi, Objecten, Besluiten, Notificaties, etc.)
5. **Form.io Forms** -- Full Form.io integration with drag-and-drop form builder
6. **Form Flow (Multi-Step Wizards)** -- Step-by-step form completion with breadcrumbs and back navigation
7. **Configurable Case Tabs** -- Admin-configurable tabs per case type (standard, formio, custom, widgets)
8. **Case Tags with Colors** -- Colored label system for cases
9. **Milestone Tracking** -- Process milestones linked to BPMN flow nodes with sets
10. **Value Resolvers** -- Pluggable dynamic data binding (`doc:`, `pv:`, etc.)
11. **Policy-Based Access Control (PBAC)** -- Fine-grained permissions with conditions on entity fields
12. **SmartDocuments Integration** -- Template-based document generation
13. **Outbox Pattern (RabbitMQ)** -- Reliable event distribution
14. **Process Instance Migration** -- Batch migration between process versions
15. **Keycloak IAM** -- Full Keycloak integration for identity management
16. **Case Widgets** -- Configurable widget tabs on case detail
17. **CSV Case Export** -- Export case lists to CSV
18. **Localization Module** -- Server-side i18n translations
19. **Data Providers** -- External data source integration
20. **Document Snapshots** -- Point-in-time document snapshots
21. **Case Migration** -- Migrate cases between definition versions
22. **Structured Logging** -- Dedicated logging module with resource context
23. **Quick Search** -- Stored search queries per user per case type
24. **Portal Tasks (Portaaltaak)** -- Citizen-facing portal task system

### What Procest Has That Valtimo Does NOT Have
1. **Nextcloud Integration** -- Native file management via Nextcloud, user management via Nextcloud
2. **MCP/AI Integration** -- AI-powered case management via MCP protocol
3. **n8n Visual Automation** -- Low-code automation via n8n (more accessible than BPMN)
4. **NL Design System Theming** -- Government design system with token-based theming
5. **Pipeline Views** -- Kanban-style pipeline visualization of cases
6. **Lightweight Architecture** -- PHP + Vue.js, runs as Nextcloud app (no JVM, no Keycloak needed)
7. **Document Checklists** -- Checklist-based document requirements
8. **ExApp Architecture** -- Extensible via Nextcloud ExApps (Python sidecars)
