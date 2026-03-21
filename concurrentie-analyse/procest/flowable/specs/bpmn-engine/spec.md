---
competitor: flowable
analyzed_date: 2026-03-14
feature: BPMN Process Engine
category: core-engine
module_path: modules/flowable-engine, modules/flowable-bpmn-model, modules/flowable-bpmn-converter
---

# BPMN Process Engine

## What It Is

Flowable's BPMN engine is a complete BPMN 2.0 runtime implementing the process execution standard. It is the historical core of the product (Activiti heritage) and the most mature component. Written in Java, it provides a fast, efficient, reliable process execution engine with rich Java and REST APIs.

## Architecture

The BPMN engine follows established patterns:
- **Command pattern** for all operations
- **Agenda-based** execution (FlowableEngineAgenda)
- **Shared services** for tasks, variables, jobs, identity links
- **Interceptor chain** for transactions, logging, security

## Core Services

### ProcessEngine
Central entry point providing access to all services:
- `RepositoryService` -- deploy/query process definitions
- `RuntimeService` -- start/manage process instances
- `TaskService` -- manage human tasks
- `HistoryService` -- query audit trail
- `ManagementService` -- admin/management operations
- `FormService` -- form handling
- `IdentityService` -- user context
- `DynamicBpmnService` -- runtime definition changes
- `ProcessMigrationService` -- live migration

## BPMN Elements Supported

### Tasks
- **UserTask** -- human work items
- **ServiceTask** -- Java delegate, expression, delegate expression
- **ScriptTask** -- script execution
- **MailTask** -- email sending
- **HttpTask** -- HTTP calls
- **CamelTask** -- Apache Camel integration
- **ExternalWorkerTask** -- external worker pattern
- **BusinessRuleTask** -- DMN integration
- **SendTask** / **ReceiveTask** -- messaging
- **ManualTask** -- manual work (no system action)

### Gateways
- **ExclusiveGateway** (XOR)
- **ParallelGateway** (AND)
- **InclusiveGateway** (OR)
- **EventBasedGateway**

### Events
- **StartEvent** (none, timer, message, signal, error, conditional, event-registry)
- **EndEvent** (none, error, terminate, cancel, escalation)
- **IntermediateThrowEvent** (signal, compensation, none)
- **IntermediateCatchEvent** (timer, message, signal, conditional)
- **BoundaryEvent** (timer, error, signal, message, cancel, compensation, conditional, escalation)

### Subprocesses
- **Embedded subprocess**
- **Call activity** (calls another process or CMMN case)
- **Event subprocess**
- **Transaction subprocess**
- **Ad-hoc subprocess**

### Advanced Features
- **Multi-instance** (parallel/sequential) on any activity
- **Compensation** handling
- **Error/escalation** propagation
- **Data objects**
- **Execution listeners** and **Task listeners**
- **Async execution** (job-based)
- **Process migration** (version-to-version)
- **Dynamic process modification** at runtime

## BPMN-CMMN Integration

Bidirectional integration:
- **CMMN ProcessTask** can start a BPMN process instance
- **BPMN CallActivity** can start a CMMN case instance
- Entity links track parent-child relationships across engines
- Callback mechanism for completion notification
- Shared task service means tasks from both engines appear in unified queries

## REST API

Comprehensive REST API for:
- Process definitions (deploy, list, get, delete)
- Process instances (start, query, delete, variables)
- Tasks (query, claim, complete, delegate)
- History (process instances, activities, tasks, variables)
- Jobs (execute, delete, query)
- Deployments (create, list, get, delete)
- Execution (signal, query, variables)
- Forms (get, submit)
- Identity (users, groups)

## Database Structure

Tables with `ACT_RU_*` (runtime) and `ACT_HI_*` (history) prefixes:
- `ACT_RE_DEPLOYMENT`, `ACT_RE_PROCDEF` -- definitions
- `ACT_RU_EXECUTION` -- process instances and executions
- `ACT_RU_TASK` -- active tasks
- `ACT_RU_VARIABLE` -- runtime variables
- `ACT_RU_JOB` / `ACT_RU_TIMER_JOB` / `ACT_RU_DEADLETTER_JOB` -- jobs
- `ACT_HI_PROCINST`, `ACT_HI_ACTINST`, `ACT_HI_TASKINST`, `ACT_HI_VARINST` -- history

## Relevance to Procest

| Feature | Flowable BPMN | Procest |
|---------|--------------|---------|
| Process model | BPMN 2.0 XML | n8n workflows (JSON) |
| Task types | 10+ activity types | n8n nodes (400+ integrations) |
| Gateways | 4 gateway types | n8n IF/Switch/Merge |
| Events | 20+ event types | n8n triggers + webhooks |
| Subprocesses | 5 subprocess types | n8n sub-workflows |
| Multi-instance | Native parallel/sequential | Manual in n8n |
| Error handling | BPMN compensation/errors | n8n error workflows |
| Deployment | Versioned XML definitions | n8n workflow versions |
| Migration | Formal migration service | Manual |
| Integration depth | Deep Java integration | HTTP/webhook based |

### Opportunities for Procest
- Flowable's comprehensive BPMN support is overkill for most government use cases
- Procest can offer simpler process modeling through n8n visual editor
- Focus on the practical subset of BPMN patterns actually used in government workflows
- Consider BPMN import capability for migration from Flowable/Camunda environments
