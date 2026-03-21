---
competitor: flowable
analyzed_date: 2026-03-14
feature: rest-api
module_path: modules/flowable-cmmn-rest, modules/flowable-rest, modules/flowable-dmn-rest, modules/flowable-common-rest
---

# REST API Layer

## Overview

Flowable exposes comprehensive REST APIs for each engine (CMMN, BPMN, DMN) following consistent patterns. The APIs are Spring MVC controllers with JSON request/response.

## URL Structure

The CMMN REST API (most relevant for Procest) uses these path prefixes:

| Prefix | Purpose |
|--------|---------|
| `cmmn-repository/` | Case definition and deployment management |
| `cmmn-runtime/` | Active case instances, plan items, tasks |
| `cmmn-query/` | Complex query endpoints (POST with filter body) |
| `cmmn-history/` | Historic data access |
| `cmmn-management/` | Engine admin, jobs, properties |

## CMMN REST Endpoints

### Repository (Definitions)
| Method | Path | Purpose |
|--------|------|---------|
| GET/POST | `/cmmn-repository/deployments` | List/create deployments |
| GET/DELETE | `/cmmn-repository/deployments/{id}` | Get/delete deployment |
| GET | `/cmmn-repository/deployments/{id}/resources` | List deployment resources |
| GET | `/cmmn-repository/case-definitions` | List case definitions |
| GET/PUT | `/cmmn-repository/case-definitions/{id}` | Get/update case definition |
| GET | `/cmmn-repository/case-definitions/{id}/resourcedata` | Get definition XML |
| GET | `/cmmn-repository/case-definitions/{id}/image` | Get definition diagram |
| GET | `/cmmn-repository/case-definitions/{id}/model` | Get definition model |
| GET | `/cmmn-repository/case-definitions/{id}/start-form` | Get start form |
| GET | `/cmmn-repository/case-definitions/{id}/decisions` | List linked decisions |
| GET | `/cmmn-repository/case-definitions/{id}/form-definitions` | List linked forms |
| GET/POST/DELETE | `/cmmn-repository/case-definitions/{id}/identitylinks` | Access control |

### Runtime (Cases)
| Method | Path | Purpose |
|--------|------|---------|
| GET/POST | `/cmmn-runtime/case-instances` | List/start case instances |
| GET/PUT/DELETE | `/cmmn-runtime/case-instances/{id}` | Get/update/delete instance |
| GET | `/cmmn-runtime/case-instances/{id}/diagram` | Runtime diagram |
| GET | `/cmmn-runtime/case-instances/{id}/stage-overview` | Stage progress |
| PUT | `/cmmn-runtime/case-instances/{id}/change-state` | Dynamic state change |
| PUT | `/cmmn-runtime/case-instances/{id}/migrate` | Migrate to new version |
| PUT | `/cmmn-runtime/case-instances/{id}/validate-migration` | Validate migration |
| GET/POST/PUT/DELETE | `/cmmn-runtime/case-instances/{id}/variables` | Case variables |
| GET/POST/DELETE | `/cmmn-runtime/case-instances/{id}/identitylinks` | Case participants |

### Runtime (Plan Items)
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/cmmn-runtime/plan-item-instances` | List plan items |
| GET/PUT | `/cmmn-runtime/plan-item-instances/{id}` | Get/transition plan item |
| GET/POST/PUT/DELETE | `/cmmn-runtime/plan-item-instances/{id}/variables` | Plan item variables |

### Runtime (Tasks)
| Method | Path | Purpose |
|--------|------|---------|
| GET/POST | `/cmmn-runtime/tasks` | List/create tasks |
| GET/PUT/DELETE | `/cmmn-runtime/tasks/{id}` | Get/update/delete task |
| POST | `/cmmn-runtime/tasks/{id}` (with action body) | Complete/claim/delegate/resolve |
| GET | `/cmmn-runtime/tasks/{id}/form` | Get task form |
| GET/POST/PUT/DELETE | `/cmmn-runtime/tasks/{id}/variables` | Task variables |
| GET/POST/DELETE | `/cmmn-runtime/tasks/{id}/identitylinks` | Task participants |
| GET | `/cmmn-runtime/tasks/{id}/subtasks` | Sub-tasks |

### Runtime (Variables & Events)
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/cmmn-runtime/variable-instances` | Query variable instances |
| GET | `/cmmn-runtime/event-subscriptions` | List event subscriptions |

### Query (Complex Filters)
| Method | Path | Purpose |
|--------|------|---------|
| POST | `/cmmn-query/case-instances` | Complex case instance query |
| POST | `/cmmn-query/tasks` | Complex task query |
| POST | `/cmmn-query/plan-item-instances` | Complex plan item query |
| POST | `/cmmn-query/variable-instances` | Complex variable query |

### History
| Method | Path | Purpose |
|--------|------|---------|
| GET/DELETE | `/cmmn-history/historic-case-instances/{id}` | Historic case |
| GET | `/cmmn-history/historic-case-instances` | List historic cases |
| GET | `/cmmn-history/historic-case-instances/{id}/stage-overview` | Historic stage progress |
| GET | `/cmmn-history/historic-case-instances/{id}/identitylinks` | Historic participants |
| GET | `/cmmn-history/historic-task-instances` | Historic tasks |
| GET | `/cmmn-history/historic-variable-instances` | Historic variables |
| GET | `/cmmn-history/historic-milestone-instances` | Historic milestones |
| GET | `/cmmn-history/historic-planitem-instances` | Historic plan items |

### Management
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/cmmn-management/engine` | Engine info |
| GET/DELETE/POST | `/cmmn-management/jobs` | Job management |
| GET/DELETE/POST | `/cmmn-management/timer-jobs` | Timer job management |
| GET/DELETE/POST | `/cmmn-management/deadletter-jobs` | Dead letter jobs |
| GET/DELETE/POST | `/cmmn-management/suspended-jobs` | Suspended jobs |

## API Patterns

### Query Pattern
All collection endpoints support:
- **GET** with query parameters for simple filtering
- **POST** to `/query/*` endpoints with JSON body for complex queries (including nested AND/OR conditions, variable queries)

### Variable Handling
Variables support multiple types with binary data upload:
- Simple types: string, integer, long, double, boolean, date
- Complex types: serialized objects (Java serializable)
- Binary: file upload via multipart

### Interceptors
`CmmnRestApiInterceptor` provides hooks for:
- Authorization checks on every API call
- Request/response transformation
- Custom validation

### Response Format
Standard pagination response:
```json
{
  "data": [...],
  "total": 100,
  "start": 0,
  "sort": "createTime",
  "order": "desc",
  "size": 10
}
```

## API Security

- Basic Authentication (configurable)
- Spring Security integration
- Per-endpoint authorization via interceptor
- Form-level handler interceptor for sensitive operations

## Procest Comparison

| Feature | Flowable REST | Procest API |
|---------|--------------|-------------|
| API style | REST with query endpoints | Nextcloud OCS + custom REST |
| Authentication | Basic Auth / Spring Security | Nextcloud auth (session/token) |
| Pagination | offset/limit with sorting | Nextcloud standard pagination |
| Variable handling | Typed variables with binary support | OpenRegister JSON objects |
| Query complexity | POST-based complex queries with AND/OR | OCS filter parameters |
| Bulk operations | Bulk terminate/delete | Not yet available |
| Migration API | Dedicated endpoints | Not available |
| Endpoint count | 60+ CMMN endpoints alone | ~10-15 endpoints |
