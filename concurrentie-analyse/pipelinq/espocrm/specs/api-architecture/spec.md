---
competitor: espocrm
analyzed_date: 2026-03-14
feature: api-architecture
---

# API Architecture

## Overview

EspoCRM provides a RESTful JSON API that serves both the built-in frontend SPA and external integrations. The API follows a **convention-over-configuration** pattern where every entity automatically gets full CRUD endpoints through a generic routing system.

## Authentication

- **Basic Auth** - Username/password
- **API Key** - Header or query parameter authentication
- **OAuth 2.0** - Via OAuthProvider/OAuthAccount entities
- **OIDC** - OpenID Connect support
- **HMAC** - Webhook signature verification
- **2FA** - TOTP and email-based two-factor authentication
- Auth tokens stored in `AuthToken` entity with expiration

## Route Architecture

Routes are defined in `Resources/routes.json` (698 lines for core, 87 for CRM module).

### Generic CRUD Routes
Every entity gets these routes automatically via wildcard controller routing:

```
GET    /:controller              - List records
POST   /:controller              - Create record
GET    /:controller/:id          - Read record
PUT    /:controller/:id          - Update record (full)
PATCH  /:controller/:id          - Update record (partial)
DELETE /:controller/:id          - Delete record

GET    /:controller/:id/:link    - List related records
POST   /:controller/:id/:link    - Create relationship
DELETE /:controller/:id/:link    - Remove relationship

GET    /:controller/layout/:name - Get entity layout
PUT    /:controller/layout/:name - Update entity layout

POST   /:controller/action/:action - Custom action (POST)
PUT    /:controller/action/:action - Custom action (PUT)
GET    /:controller/action/:action - Custom action (GET)
```

### Record Controller (`Core/Controllers/Record.php`)

The base Record controller provides:
- `getAction` - Read single record
- `getActionList` - List with search params
- `postAction` - Create
- `putAction` - Update
- `deleteAction` - Delete (soft delete)
- `getActionListLinked` - List related records
- `postActionCreateLink` - Create relationship
- `deleteActionRemoveLink` - Remove relationship
- `postActionMassUpdate` - Bulk update
- `postActionMassDelete` - Bulk delete
- `getActionExport` - CSV/XLSX export
- `postActionFollow` / `deleteActionUnfollow` - Stream following

### Search Parameters

List endpoints accept:
- `where` - Filter conditions (array of filter objects)
- `orderBy` - Sort field
- `order` - Sort direction (asc/desc)
- `offset` - Pagination offset
- `maxSize` - Page size
- `textFilter` - Full-text search
- `primaryFilter` - Named filter presets
- `boolFilterList` - Boolean filter toggles

### Entity-Specific Routes

Beyond generic CRUD, entities have custom routes:

```
# Kanban
GET  /:controller/action/listKanban
PUT  /:controller/action/orderKanban

# Global Search
GET  /GlobalSearch

# Lead Capture (no auth)
POST /LeadCapture/:apiKey
POST /LeadCapture/form/:id

# Activities
GET  /Activities/:parentType/:id/:type
GET  /Activities/upcoming

# Calendar/Timeline
GET  /Activities (calendar data)
GET  /Timeline
GET  /Timeline/busyRanges

# Campaigns
POST /Campaign/:id/generateMailMerge
POST /Campaign/unsubscribe/:id (no auth)

# Email
POST /Email/inbox/read
POST /Email/inbox/important
POST /Email/inbox/inTrash
POST /Email/sendTest
GET  /Email/notReadCounts

# App
GET  /App/user
GET  /App/about
POST /App/destroyAuthToken

# Metadata
GET  /Metadata
GET  /I18n (no auth)
GET  /Settings (no auth)
```

## OpenAPI Support

EspoCRM includes an OpenAPI specification generator (`Tools/OpenApi/`) that produces API documentation from entity metadata.

## Webhook System

### Outgoing Webhooks
- **Webhook entity** - Configurable endpoint URL, event type, secret key
- **Event types** - `{entityType}.create`, `{entityType}.update`, `{entityType}.delete`, etc.
- **Queue system** - WebhookQueueItem for async delivery with retry
- **HMAC signatures** - Request signing for verification

### API entry points
```
POST /Webhook              - Register webhook
GET  /Webhook              - List webhooks
DELETE /Webhook/:id        - Remove webhook
```

## Middleware Architecture

```json
// metadata/app/api.json
{
    "globalMiddlewareClassNameList": [],
    "routeMiddlewareClassNameListMap": {},
    "controllerMiddlewareClassNameListMap": {},
    "controllerActionMiddlewareClassNameListMap": {}
}
```
Supports middleware at global, route, controller, and controller-action levels.

## Mass Actions

Generic mass operation framework (`Tools/MassAction/`):
- Mass update (change field values on multiple records)
- Mass delete
- Mass follow/unfollow
- Mass recalculate formula
- Custom mass actions per entity

## Import

CSV/XLSX import with field mapping (`Tools/Import/`):
- Column-to-field mapping
- Duplicate detection strategies
- Create vs update mode
- Import history and undo

## Relevance to Pipelinq

### Strengths
- Convention-based CRUD eliminates boilerplate
- Rich search/filter system
- Webhook support for integrations
- OpenAPI spec generation
- Mass operations framework
- Import/export built-in

### Opportunities for Pipelinq
- **OpenRegister API**: Already provides generic CRUD via MCP and REST
- **GraphQL**: EspoCRM is REST-only; Pipelinq could add GraphQL for frontend flexibility
- **MCP protocol**: Pipelinq already has MCP for AI/LLM integration, which EspoCRM lacks
- **Nextcloud API conventions**: Leverage Nextcloud's OCS API patterns for consistency
- **Real-time**: EspoCRM has limited WebSocket support; Pipelinq could use Nextcloud Notify Push
