# Strapi Request Pipeline

## HTTP Request Flow

```
Client Request
    |
    v
[Koa Middleware Stack]
    |-- logger (request logging)
    |-- errors (error formatting)
    |-- security (helmet headers)
    |-- cors (CORS headers)
    |-- body (request body parsing)
    |-- query (query string parsing via qs)
    |-- session (session management)
    |-- favicon (favicon handling)
    |-- public (static file serving)
    |-- compression (response compression)
    |-- response-time (X-Response-Time header)
    |
    v
[Router Resolution]
    |-- Content API routes (/api/*)
    |-- Admin API routes (/admin/*)
    |-- Plugin routes (/plugin-name/*)
    |
    v
[Authentication]
    |-- Extract credentials (Bearer token, session, API token)
    |-- Resolve auth strategy (JWT, session, API token)
    |-- Set ctx.state.auth + ctx.state.user
    |
    v
[Route Policies]
    |-- Auth scope check (action allowed for role?)
    |-- Custom route policies
    |-- Plugin policies
    |
    v
[Route Middleware]
    |-- Route-specific middleware
    |
    v
[Controller]
    |-- validateQuery(ctx)     -> Zod schema validation
    |-- sanitizeQuery(ctx)     -> Strip unauthorized query params
    |-- validateInput(data)    -> Validate request body
    |-- sanitizeInput(data)    -> Strip unauthorized fields
    |
    v
[Service Layer]
    |-- Core API Service (auto-generated CRUD)
    |-- Or custom service logic
    |
    v
[Document Service] (v5)
    |
    +-- [Document Service Middlewares]
    |   |-- Database error handling
    |   |-- Custom middlewares
    |   |
    |   v
    +-- [Repository]
        |-- Draft/Publish transforms (status -> DB filters)
        |-- i18n transforms (locale -> DB filters)
        |-- Parameter normalization
        |
        v
    [Entity Manager] (@strapi/database)
        |
        +-- [Database Lifecycle Hooks]
        |   |-- beforeCreate / afterCreate
        |   |-- beforeUpdate / afterUpdate
        |   |-- beforeDelete / afterDelete
        |   |-- beforeFind / afterFind
        |   |-- Timestamp auto-management
        |   |-- Custom subscribers
        |   |
        |   v
        +-- [Query Builder]
            |-- Where clause processing (filter operators)
            |-- Join generation (relations)
            |-- Sort, limit, offset
            |-- Population (eager loading)
            |
            v
        [Knex.js]
            |
            v
        [Database] (PostgreSQL / MySQL / SQLite)

    <-- Result flows back up -->

[Controller - Output]
    |-- sanitizeOutput(data)   -> Strip unauthorized fields from response
    |-- transformResponse(data) -> Format for v5 or v4 response format
    |
    v
[Koa Response]
    |-- Content-type headers
    |-- Status code
    |-- JSON body: { data, meta }
    |
    v
Client Response
```

## Event Side Effects

```
[Entity Manager Operation]
    |
    +-- [Database Lifecycle Hooks]
    |   |-- afterCreate/afterUpdate/afterDelete
    |   |
    |   +-- [History Version Creation]
    |   |   (snapshot entry data for version history)
    |   |
    |   +-- [Review Workflow Stage Update] (EE)
    |       (update stage assignments)
    |
    +-- [Event Hub Emission]
        |-- entry.create / entry.update / entry.delete
        |-- entry.publish / entry.unpublish
        |-- media.create / media.update / media.delete
        |
        +-- [Webhook Runner]
        |   |-- Match event to subscribed webhooks
        |   |-- Queue HTTP POST to webhook URLs
        |   |-- Worker pool (5 concurrent)
        |
        +-- [Plugin Event Listeners]
            |-- i18n locale propagation
            |-- Content release tracking
            |-- Metrics collection
```

## Content Type Schema Change Flow

```
[Content-Type Builder UI]
    |
    v
[Admin API: PUT /content-type-builder/content-types/:uid]
    |
    v
[Schema Builder Service]
    |-- Validate attribute names (no reserved names)
    |-- Validate relations (bidirectional setup)
    |-- Validate components (nested resolution)
    |-- Replace temporary UIDs with generated UIDs
    |
    v
[File Writer]
    |-- Write schema.json to src/api/{name}/content-types/
    |-- Write component JSON files
    |-- Update relation inverses
    |
    v
[Server Reload]
    |-- Strapi detects file changes
    |-- Full server restart
    |
    v
[Boot Sequence]
    |-- Load all content type definitions
    |-- Convert to database metadata
    |
    v
[Schema Sync]
    |-- Inspect current database schema
    |-- Diff against expected schema
    |-- Generate DDL (CREATE TABLE, ALTER TABLE, etc.)
    |-- Execute migrations
    |
    v
[Ready]
    (New content type available via API)
```
