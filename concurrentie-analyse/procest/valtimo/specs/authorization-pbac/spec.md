---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Authorization (PBAC) -- Valtimo

## Purpose
Policy-Based Access Control system that governs all resource access in Valtimo. Unlike traditional RBAC, PBAC evaluates permissions based on contextual conditions (field values, resource relationships) in addition to role membership, enabling fine-grained access rules like "user can only view cases assigned to them."

## Architecture Overview
- **Backend module**: `authorization/` (Kotlin, Spring Security integration)
- **Frontend module**: `access-control/` and `access-control-management/` Angular libraries
- **Authentication**: Keycloak (OIDC) -- roles from JWT tokens mapped to Valtimo roles
- **Query-level enforcement**: Generates JPA Criteria predicates for database-level filtering
- **Default stance**: Deny-all -- users have NO access unless explicitly granted

## Data Model

### Role
Named role that maps to Keycloak groups/roles.

| Field | Type | Description |
|-------|------|-------------|
| key | String | Unique role identifier |
| name | String | Display name |

### Permission
Access rule linking a role to an action on a resource type.

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Unique permission ID |
| roleKey | String | Reference to Role |
| resourceType | String | Target resource class (e.g., `JsonSchemaDocument`) |
| action | Enum | Allowed action (e.g., `VIEW`, `CREATE`, `MODIFY`, `DELETE`) |
| conditionContainer | JSON | Optional conditions that must all be true |

### ConditionContainer
Nested condition structure for contextual access rules.

| Field | Type | Description |
|-------|------|-------------|
| conditions | List | List of conditions (AND logic) |

### Condition Types
| Type | Description | Example |
|------|-------------|---------|
| Field | Match a resource field value | `assigneeId == currentUser` |
| Expression | SpEL expression evaluation | `${document.status != 'CLOSED'}` |
| Container | Join to related resource and check conditions there | Check case definition via document |

## Business Logic

### Permission Evaluation Flow
1. User makes a request (API call or UI action)
2. Spring Security extracts roles from Keycloak JWT token
3. System finds all Permission records matching the user's roles + requested resource type + action
4. Multiple matching permissions evaluated with **OR logic** (any match = authorized)
5. Within each permission, conditions evaluated with **AND logic** (all must pass)
6. If no permission matches, request is denied (deny-by-default)

### Query-Level Filtering
1. For list endpoints, permissions are translated to JPA Criteria predicates
2. Predicates added to the database query (WHERE clause)
3. User only sees records they have permission for -- no post-query filtering needed
4. This ensures consistent pagination and performance regardless of permission complexity

### Resource Types
Each module registers its own resource types and actions:

| Module | Resource | Actions |
|--------|----------|---------|
| Case | `JsonSchemaDocument` | `view`, `view_list`, `create`, `modify`, `delete`, `assign`, `claim` |
| Process | `OperatonExecution` | `create` |
| Process | `OperatonProcessDefinition` | `view_list` |
| Dashboard | `Dashboard` | `view`, `view_list` |
| Notes | `Note` | `view`, `create`, `modify`, `delete` |
| Search | `SearchField` | `view` |
| Forms | `Form` | `view` |

### BPMN Bypass
Automated BPMN tasks (service tasks, listeners, script tasks) execute without user context and bypass authorization checks. This ensures process automation is not blocked by access rules.

### Auto-Deployment
Roles and permissions can be auto-deployed from JSON configuration files at startup.

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- Uses **Nextcloud's built-in ACL** system (groups, circles, file ACLs)
- OpenRegister applies schema-level access rules
- No condition-based permission evaluation -- simpler model
- Inherits Nextcloud's mature user/group management

### Valtimo advantages
- Fine-grained contextual conditions (field-based, expression-based, nested containers)
- Query-level enforcement via JPA Criteria (performance-safe)
- Deny-by-default security posture
- Per-resource-type, per-action granularity
- Auto-deployable permission configurations

### Valtimo disadvantages
- Requires Keycloak as external dependency (complex setup)
- Permission configuration is complex -- easy to misconfigure
- No UI for testing "what would user X see?" (permission simulation)
- Tight coupling to JPA -- alternative data stores would need reimplementation
- Role sync between Keycloak and Valtimo is manual/fragile
