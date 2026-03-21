---
competitor: twenty
analyzed_date: 2026-03-14
feature: api-layer
---

# API Layer

## Overview

Twenty exposes three API interfaces: GraphQL (primary), REST, and MCP (Model Context Protocol). All APIs are auto-generated from the workspace metadata — when a custom object is created, it automatically gets GraphQL queries/mutations, REST endpoints, and MCP tools.

## GraphQL API (Primary)

### Architecture
- Built with `@graphql-yoga/nestjs` (Yoga + NestJS integration)
- Two GraphQL modules:
  - **Core GraphQL API** — CRUD operations on workspace objects (companies, people, opportunities, etc.)
  - **Metadata GraphQL API** — Managing object definitions, field definitions, views, roles

### Auto-Generated Schema
The workspace schema is dynamically built from object metadata:
- `workspace-schema.factory.ts` generates the full schema
- `workspace-resolver-builder/` creates resolvers for each object type
- `workspace-query-builder/` constructs SQL queries from GraphQL operations
- `workspace-query-runner/` executes queries with proper workspace scoping

### Query Features
- Filtering with complex operators (AND/OR/NOT, nested conditions)
- Sorting on any field
- Pagination (cursor-based)
- Duplicate detection criteria
- Aggregation operations
- Full-text search via `searchVector`

### GraphQL Query Runner
- `graphql-query-runner/` handles the execution pipeline
- Supports dataloaders for N+1 prevention
- Event emission for real-time subscriptions

## REST API

### Structure
- `rest/core/` — CRUD endpoints for workspace objects
- `rest/metadata/` — Metadata management endpoints
- REST-to-common args handlers convert REST params to internal format
- OpenAPI spec auto-generated

### Endpoints Pattern
```
GET    /api/objects/{objectNamePlural}
GET    /api/objects/{objectNamePlural}/{id}
POST   /api/objects/{objectNamePlural}
PATCH  /api/objects/{objectNamePlural}/{id}
DELETE /api/objects/{objectNamePlural}/{id}
```

## MCP API (Model Context Protocol)

Twenty has native MCP support, exposing workspace data as tools for AI agents:

### Architecture
- `mcp-core.controller.ts` — HTTP endpoint for JSON-RPC 2.0 MCP protocol
- `mcp-protocol.service.ts` — Protocol handling (initialize, list tools, call tools)
- `mcp-tool-executor.service.ts` — Executes MCP tool calls against workspace data

### MCP Tools
Auto-generated from object metadata — each workspace object becomes a set of MCP tools:
- `list_{objectName}` — List/search records
- `get_{objectName}` — Get single record
- `create_{objectName}` — Create record
- `update_{objectName}` — Update record
- `delete_{objectName}` — Delete record

### JSON Schema Generation
- `get-json-schema.ts` converts field metadata types to JSON Schema for MCP tool input descriptions

## Authentication

- **API Keys** — For programmatic access (REST, GraphQL, MCP)
- **JWT tokens** — For authenticated user sessions
- **OAuth** — For connected accounts (Google, Microsoft)
- Workspace-scoped — all API calls are scoped to the user's workspace

## Pipelinq Comparison

| Aspect | Twenty | Pipelinq (OpenRegister) |
|--------|--------|------------------------|
| Primary API | GraphQL (auto-generated) | REST (OCS + custom routes) |
| Secondary API | REST (auto-generated) | GraphQL (not yet) |
| AI Integration | Native MCP protocol | MCP via OpenRegister app |
| Schema generation | Dynamic from metadata | Schema-driven endpoints |
| Authentication | API keys + JWT + OAuth | Nextcloud auth + API tokens |
| Real-time | GraphQL subscriptions | SSE (not yet) |
| OpenAPI | Auto-generated | Manual definition |
