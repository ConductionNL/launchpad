---
competitor: flowable
analyzed_date: 2026-03-14
feature: API Architecture
category: technical
---

# API Architecture

## What It Is

Flowable provides a comprehensive API layer for all engines, available through both Java APIs and REST APIs. The API follows a service-oriented architecture with each engine exposing multiple service interfaces.

## Java API

### Service Pattern
Each engine exposes services through a factory:
```java
ProcessEngine engine = ProcessEngines.getDefaultProcessEngine();
RuntimeService runtime = engine.getRuntimeService();
TaskService tasks = engine.getTaskService();
```

### BPMN Engine Services
- `RepositoryService` - process definition management
- `RuntimeService` - process instance lifecycle
- `TaskService` - human task management
- `HistoryService` - audit/history queries
- `ManagementService` - engine administration
- `FormService` - form data handling
- `IdentityService` - user context
- `DynamicBpmnService` - runtime modifications
- `ProcessMigrationService` - version migration

### CMMN Engine Services
- `CmmnRepositoryService` - case definition management
- `CmmnRuntimeService` - case instance lifecycle
- `CmmnTaskService` - case task management
- `CmmnHistoryService` - case audit trail
- `CmmnManagementService` - case engine admin

### DMN Engine Services
- `DmnRepositoryService` - decision definition management
- `DmnRuleService` - decision execution
- `DmnManagementService` - DMN engine admin

## REST API

### Authentication
- Basic Authentication
- Token-based (commercial)

### Endpoint Structure
All REST endpoints follow consistent patterns:
```
GET    /process-api/runtime/process-instances
POST   /process-api/runtime/process-instances
GET    /process-api/runtime/process-instances/{id}
DELETE /process-api/runtime/process-instances/{id}
PUT    /process-api/runtime/process-instances/{id}
```

### Key REST Resources

#### Process Definitions
- `GET /repository/deployments` - list deployments
- `POST /repository/deployments` - create deployment
- `GET /repository/process-definitions` - list definitions
- `GET /repository/process-definitions/{id}` - get definition

#### Process Instances
- `POST /runtime/process-instances` - start process
- `GET /runtime/process-instances` - query instances
- `GET /runtime/process-instances/{id}` - get instance
- `DELETE /runtime/process-instances/{id}` - delete instance
- `GET /runtime/process-instances/{id}/variables` - get variables

#### Tasks
- `GET /runtime/tasks` - query tasks
- `GET /runtime/tasks/{id}` - get task
- `POST /runtime/tasks/{id}` - complete/claim/delegate
- `GET /runtime/tasks/{id}/variables` - get task variables

#### History
- `GET /history/historic-process-instances` - query history
- `GET /history/historic-task-instances` - task history
- `GET /history/historic-activity-instances` - activity history

### Query Capabilities
- Filter by any field
- Pagination (start, size)
- Sorting (sort, order)
- Native SQL queries for complex filtering
- OR-based query conditions

## Database Schema

### Table Prefixes
- `ACT_RE_*` - Repository (definitions)
- `ACT_RU_*` - Runtime (instances, tasks, variables)
- `ACT_HI_*` - History (audit trail)
- `ACT_GE_*` - General (byte arrays, properties)
- `ACT_DMN_*` - DMN engine tables
- `ACT_CMMN_*` - CMMN engine tables
- `ACT_ID_*` - Identity (users, groups)

### ORM
- MyBatis for data access
- Custom query builders
- Configurable connection pooling (HikariCP recommended)

## Relevance to Procest

### Applicable Patterns
- Service-based API design with clear separation of concerns
- REST API with consistent CRUD patterns
- Rich query capabilities with pagination and sorting
- History/audit as first-class API resource
- Separate runtime vs history data models

### Key Differences
- Flowable is Java-native; Procest is PHP/Nextcloud-based
- Flowable uses MyBatis ORM; Procest uses Nextcloud's ORM
- Flowable REST is standalone; Procest API integrates with Nextcloud API framework

### Opportunities
- Procest can follow Flowable's clear API separation patterns
- Implement comprehensive query APIs for tasks, cases, history
- History API is critical for government audit requirements
- Procest can offer GraphQL or OData alongside REST (more modern)
- Nextcloud's existing auth infrastructure simplifies API security
