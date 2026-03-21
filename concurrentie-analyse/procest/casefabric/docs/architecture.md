# CaseFabric Architecture Documentation

**Source:** https://guide.cafienne.io/docs/engine/overview, https://guide.cafienne.io/docs/engine/configuration

## Engine Architecture

CaseFabric is built on Apache Pekko (formerly Akka), making it a platform for building highly concurrent, distributed, and resilient message-driven case management applications.

### Core Design Patterns

1. **Event Sourcing** - All case state changes are stored as immutable events in a journal. The current state of any case can be reconstructed by replaying events.

2. **CQRS (Command Query Responsibility Segregation)** - Two types of storage:
   - **Event Journal** - Stores all events generated during case lifecycle (Cassandra or JDBC)
   - **Query Database** - Projections of events for fast retrieval (PostgreSQL)

3. **Persistent Actors** - Each case instance is a persistent actor in the Pekko system. This provides:
   - Concurrent case execution
   - Fault isolation (one case failure doesn't affect others)
   - Location transparency (can distribute across nodes)
   - Message-driven processing

### Deployment Model

- Deployed as a **Docker container**
- Orchestrated via **Docker Swarm** or **Kubernetes**
- REST API exposed on configurable port (default: 2027)
- Configuration via HOCON format (`local.conf`) plus environment variables

### Storage Options

**Event Journal (choose one):**
- Apache Cassandra
- JDBC (PostgreSQL, SQL Server, etc.)

**Query Database:**
- PostgreSQL (for case/task projections and business identifier indexes)

**Note for SQL Server:** requires `sendStringParametersAsUnicode=false` to avoid full table scans during event insertion.

### Configuration Structure

Configuration uses Pekko's HOCON format with CaseFabric-specific sections:

```
cafienne {
  platform {
    owners = ["admin"]           # Platform owner user IDs
    default-tenant = "world"     # Default tenant
    bootstrap-tenants = [...]    # Auto-create tenants on startup
  }
  engine {
    mail-service {               # Jakarta Mail configuration
      mail.host = "..."
      mail.smtp.port = 1025
    }
  }
  api {
    bindhost = "localhost"
    bindport = 2027
    security {
      oidc = [{ issuer = "..." }]  # OpenID Connect providers
    }
  }
}
```

### Authentication Architecture

- **Protocol:** OpenID Connect (OIDC)
- **Token format:** JWT (JSON Web Tokens)
- **IdP:** External, configurable (ships with Dex in demo)
- **Required JWT claim:** `sub` (used as internal user identifier)
- **Multiple OIDC providers** can be configured simultaneously
- CaseFabric itself does NOT implement authentication

### Authorization Model (Multi-level)

1. **Platform level** - Platform owners (configured in `local.conf`) can create/manage tenants
2. **Tenant level** - Tenant owners manage users and roles within a tenant
3. **Case level** - Per-case teams with role-based access
4. **CMMN level** - Task-level authorization via CMMN performer roles

### Multitenancy

- Built-in multitenancy with strict data isolation
- Platform owners create tenants
- Each tenant has its own users, roles, and cases
- Users can belong to multiple tenants with different roles
- Cross-tenant queries possible (e.g., GetMyCases across all tenants)

## Technology Stack Summary

| Component | Technology |
|-----------|-----------|
| Language | Java + Scala |
| Actor framework | Apache Pekko (formerly Akka) |
| Event store | Cassandra or JDBC |
| Query store | PostgreSQL |
| API | REST (Swagger UI) |
| Authentication | OpenID Connect / JWT |
| Expression engine | Spring Expression Language (SpEL) |
| Container | Docker |
| Orchestration | Docker Swarm / Kubernetes |
| Demo IdP | Dex |
| Task UI forms | React JSON Schema Forms |

## GitHub Repositories

| Repository | Description | Language |
|-----------|-------------|----------|
| `cafienne-engine` | Core CMMN case engine (not publicly visible) | Scala |
| `getting-started` | Docker-based demo environment | HTML |
| `cmmn-test-framework` | JavaScript test framework for CMMN models | TypeScript |
| `bounded-framework` | DDD framework for Scala/Akka (11 stars) | Scala |
| `guide` | Documentation site source | JavaScript |
| `mendix-docs` | Mendix DCM documentation | Markdown |
