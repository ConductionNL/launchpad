---
competitor: casefabric
analyzed_date: 2026-03-14
feature: REST API Layer
category: architecture
---

# REST API Layer

## Overview

CaseFabric exposes a REST API via Akka HTTP on port 2027. The API follows a command/query separation pattern -- write operations send commands to case actors, read operations query the CQRS projection database.

## Implementation Details

### Framework

- **Akka HTTP** with Scala route DSL
- **Swagger 3.x** annotations for OpenAPI documentation
- **Spray JSON** for request/response serialization
- **Jackson** for complex object mapping

### Route Architecture

All routes extend `CaseServiceRoute`:
- `AuthenticatedRoute` -- adds JWT validation
- `CommandRoute` -- sends commands to case actors and awaits response
- `QueryRoute` -- queries the read-side database

Route tree assembled in `Main.scala`:
```
SwaggerRoute           /swagger
CaseEngineHealthRoute  /status
CasesRoutes            /cases/**
  CaseRoute            /cases (list, start, get)
  CaseFileRoute        /cases/{id}/casefile/**
  CaseTeamRoute        /cases/{id}/team
  PlanItemRoute        /cases/{id}/caseplan/**
  DiscretionaryRoute   /cases/{id}/discretionaryitems
  CaseDocumentationRoute /cases/{id}/documentation
  CaseHistoryRoute     /cases/{id}/history/**
  CaseMigrationRoute   /cases/{id}/migration
IdentifierRoutes       /identifiers/**
TaskRoutes             /tasks/**
  TaskQueryRoutes      /tasks (list, count)
  TaskActionRoutes     /tasks/{id}/** (claim, assign, complete, etc.)
TenantRoutes           /tenant/**
PlatformRoutes         /platform/**
RepositoryRoute        /repository/**
DebugRoute             /debug/**
AnonymousRequestRoutes /anonymous/** (optional)
```

### Authentication

- `AuthenticationDirectives` extracts JWT from Authorization header
- Token validated against configured OIDC provider
- `sub` claim mapped to `PlatformUser` -> `TenantUser` via `IdentityCache`

### Query Patterns

Query routes use Slick-based implementations:
- `CaseQueriesImpl` -- case listing, filtering, statistics
- `TaskQueriesImpl` -- task listing, filtering, counting
- `IdentifierQueriesImpl` -- business identifier search
- `TenantQueriesImpl` -- tenant/user queries

Filtering:
- `CaseFilter` -- tenant, identifiers, caseName, status
- `TaskFilter` -- tenant, caseName, taskName, taskState, assignee, owner
- `IdentifierFilter` -- name, value equality/inequality

Pagination/sorting:
- `Area(offset, numberOfResults)` -- pagination
- `Sort(sortBy, sortOrder)` -- column-based sorting

### Command Flow

1. HTTP request -> route handler
2. JWT validation -> `PlatformUser`
3. Command created with user context
4. Command sent to case actor via `CaseSystem.router`
5. Actor processes command, generates events
6. Response returned (with `Case-Last-Modified` header for read-after-write)

### Response Headers

- `Case-Last-Modified` -- timestamp for eventual consistency coordination
- Clients can pass this header on subsequent GET requests to ensure projections are up-to-date

### Configuration

```hocon
cafienne.api {
  bindhost = "localhost"
  bindport = 2027
  security {
    oidc { connect-url, token-url, key-url, authorization-url, issuer }
    identity.cache.size = 1000
    debug.events.open = false
  }
}
```

### Anonymous Access

Optional anonymous API (`AnonymousRequestRoutes`):
- Enabled via `cafienne.api.anonymous.enabled = true`
- Allows unauthenticated case creation for public-facing use cases
- `CaseRequestRoute` handles anonymous case start

## Relevance for Procest

1. **Command/query separation** in API design improves scalability
2. **Case-Last-Modified header** -- elegant solution for eventual consistency
3. **Business identifier filtering** -- powerful cross-case search capability
4. **Swagger integration** -- auto-generated API documentation
5. **Anonymous access** -- useful for citizen-facing portals
