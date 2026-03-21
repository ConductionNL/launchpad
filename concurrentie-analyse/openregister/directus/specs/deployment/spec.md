---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Deployment & Multi-Tenancy

## Overview

Directus supports multiple deployment models from a single Docker container to clustered multi-node setups. Recent additions include a Deployment service for managing multiple Directus projects and deployment webhooks for CI/CD integration.

## Deployment Models

### Single Instance
- Docker container or bare Node.js process
- Single database connection
- All-in-one (API + admin UI + assets)

### Clustered / Horizontal Scaling
- Multiple Directus instances behind a load balancer
- Redis required for shared cache, rate limiting, and session storage
- Message bus (Redis pub/sub) for cross-instance event synchronization
- Synchronized cron jobs (only one instance runs scheduled tasks)

### Docker
Official Docker image with environment-variable-based configuration:
```yaml
services:
  directus:
    image: directus/directus
    environment:
      DB_CLIENT: postgres
      DB_HOST: db
      DB_DATABASE: directus
      DB_USER: directus
      DB_PASSWORD: secret
      SECRET: a-random-secret
      ADMIN_EMAIL: admin@example.com
      ADMIN_PASSWORD: admin
```

## Database Support

One Directus instance connects to one database. Supported databases:
- PostgreSQL (+ PostGIS for spatial)
- MySQL 5.7 / 8
- MariaDB
- SQLite
- Microsoft SQL Server
- Oracle DB
- CockroachDB

## Multi-Tenancy Approach

Directus does **not** have native multi-tenancy within a single instance. Instead:
- **Database-per-tenant**: Each tenant gets its own database and Directus instance
- **Schema-per-tenant**: Some databases support schema isolation (PostgreSQL)
- **Deployment Projects**: Recent feature for managing multiple Directus projects

### Deployment Service (New)
Recent additions (`deployment-projects.ts`, `deployment-runs.ts`) suggest a new deployment management system:
- Track multiple Directus project instances
- Manage deployment runs
- Deployment webhooks for triggering CI/CD pipelines
- Schema migration between environments

## Environment Configuration

Directus uses environment variables exclusively for configuration (no config files):
- 200+ configurable variables
- `@directus/env` package handles parsing
- Variables prefixed by category: `DB_*`, `CACHE_*`, `STORAGE_*`, `AUTH_*`, etc.

## Schema Migration

For moving schema between environments:
```bash
# Export schema from source
GET /schema/snapshot -> schema.json

# Compare with target
POST /schema/diff (body: schema.json) -> diff.json

# Apply to target
POST /schema/apply (body: diff.json)
```

This enables GitOps-style schema management without manual database DDL.

## Health & Monitoring

- `/server/health` - Health check endpoint
- `/server/info` - Server information
- Telemetry collection (optional, configurable)
- Metrics endpoint for Prometheus integration

## Relevance to OpenRegister

OpenRegister has a fundamentally different deployment model:
- **Nextcloud app**: Installed via Nextcloud app store, runs within Nextcloud
- **No separate infrastructure**: Shares Nextcloud's database, auth, and file storage
- **Multi-tenancy via Nextcloud**: Each Nextcloud instance is a tenant, with multiple registers per instance
- **ExApp model**: Python/Node services can run as Nextcloud External Apps

Advantages of OpenRegister's approach:
- Zero additional infrastructure
- Shared user management and auth
- Integrated file storage and sharing
- One-click installation

Advantages of Directus's approach:
- Database flexibility (7+ databases)
- Independent scaling
- Schema migration between environments
- Dedicated resource allocation
