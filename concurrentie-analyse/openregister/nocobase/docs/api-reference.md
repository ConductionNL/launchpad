---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# NocoBase API Reference

## REST API Pattern

NocoBase uses a resource-action pattern rather than pure REST:

```
<method> /api/<resource>:<action>?<params>
```

### Standard CRUD Actions

| Action | HTTP Method | URL | Description |
|--------|-------------|-----|-------------|
| list | GET/POST | `/api/users:list` | List records with filtering/pagination |
| get | GET | `/api/users:get?filterByTk=1` | Get single record |
| create | POST | `/api/users:create` | Create new record |
| update | PUT | `/api/users:update?filterByTk=1` | Update record |
| destroy | DELETE | `/api/users:destroy?filterByTk=1` | Delete record |
| firstOrCreate | POST | `/api/users:firstOrCreate` | Find or create |
| updateOrCreate | POST | `/api/users:updateOrCreate` | Upsert |

### Association Actions

```
GET /api/users/1/roles:list         # List user's roles
POST /api/users/1/roles:add         # Add roles to user
POST /api/users/1/roles:remove      # Remove roles from user
POST /api/users/1/roles:set         # Replace all roles
POST /api/users/1/roles:toggle      # Toggle role membership
```

### Filtering

```
# Simple equality
?filter[name]=John

# Operators
?filter[age.$gt]=18
?filter[name.$includes]=john
?filter[status.$in][]=active&filter[status.$in][]=pending

# Logical operators
?filter[$and][0][age.$gte]=18&filter[$and][1][status]=active
?filter[$or][0][role]=admin&filter[$or][1][role]=root

# Nested/related filtering
?filter[roles.name]=admin
```

### Pagination & Sorting

```
?page=1&pageSize=20
?sort=createdAt        # ASC
?sort=-createdAt       # DESC
?sort[]=name,-createdAt  # Multi-field
```

### Field Selection

```
?fields=id,name,email           # Include only
?except=password,token          # Exclude
?appends=roles,department       # Include relations
```

## Authentication

### Token-based (Bearer)

```
POST /api/auth:signIn
{
  "account": "admin@nocobase.com",
  "password": "admin123"
}
// Returns: { data: { token: "..." } }

// Use token:
Authorization: Bearer <token>
```

### Request Headers

```
X-Role: admin          # Current role context
X-Hostname: localhost   # Client hostname
X-Timezone: +01:00     # Client timezone
X-Locale: en-US        # Client locale
X-Authenticator: basic # Auth provider
```

## Key API Endpoints

### Collections Management
```
POST /api/collections:listMeta        # List all collection metadata
POST /api/collections:create          # Create collection
PUT  /api/collections:update          # Update collection
POST /api/collections:sync            # Sync from database
```

### UI Schema
```
GET  /api/uiSchemas:getJsonSchema/<uid>  # Get UI schema tree
POST /api/uiSchemas:insertAdjacent       # Insert schema node
PUT  /api/uiSchemas:patch                # Update schema
POST /api/uiSchemas:remove               # Remove schema node
```

### Workflow
```
GET  /api/workflows:list              # List workflows
POST /api/workflows:create            # Create workflow
POST /api/workflows:update            # Update workflow
GET  /api/executions:list             # List executions
POST /api/workflows:trigger           # Manual trigger
```

### Roles & Permissions
```
GET  /api/roles:list                  # List roles
POST /api/roles:create                # Create role
GET  /api/roles/<name>/resources:list # List role's resource permissions
POST /api/roles:check                 # Check current user's permissions
```
