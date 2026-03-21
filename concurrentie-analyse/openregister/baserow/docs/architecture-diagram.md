---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Baserow Architecture Diagram

## System Components

```
                            +------------------+
                            |   Caddy Proxy    |
                            |   (reverse proxy)|
                            +--------+---------+
                                     |
                     +---------------+---------------+
                     |                               |
              +------+------+                +-------+-------+
              |  Nuxt/Vue   |                |    Django      |
              |  Frontend   |                |    Backend     |
              |  (SSR + SPA)|                |  (REST + WS)  |
              +------+------+                +---+---+---+---+
                     |                           |   |   |
                     |                    +------+   |   +------+
                     |                    |          |          |
                     |              +-----+----+ +--+---+ +----+-----+
                     |              |  Django   | |Django| |  Celery  |
                     |              |  REST API | |Chan- | |  Workers |
                     |              | (DRF)     | |nels  | | (async)  |
                     |              +-----+----+ +--+---+ +----+-----+
                     |                    |         |          |
                     |                    +----+----+----+-----+
                     |                         |         |
                     |                   +-----+----+ +--+-----+
                     |                   |PostgreSQL | | Redis  |
                     |                   | (data +   | |(cache +|
                     |                   |  dynamic  | | queue) |
                     |                   |  tables)  | +--------+
                     |                   +----------+
                     |
              +------+------+
              | File Storage |
              | (S3 / local) |
              +--------------+
```

## Monorepo Structure

```
baserow/
  backend/                    # Django application
    src/baserow/
      api/                    # REST API serializers, views, URLs
      core/                   # Core models (Workspace, Application, User)
        generative_ai/        # AI provider integrations
        snapshots/            # Point-in-time backups
        templates/            # Pre-built database templates
      contrib/
        database/             # Database application type
          fields/             # 30+ field types
          views/              # Grid, Gallery, Form views
          formula/            # ANTLR4 formula engine
          search/             # PostgreSQL full-text search
          export/             # CSV/JSON export
          airtable/           # Airtable import
          webhooks/           # Event webhooks
          mcp/                # MCP server (early)
        builder/              # Application builder
          elements/           # 20+ UI elements
          data_sources/       # Data source connectors
          workflow_actions/   # Workflow action types
          domains/            # Custom domain publishing
        automation/           # Automation engine
          nodes/              # Trigger + action node types
      ws/                     # WebSocket consumers

  web-frontend/               # Nuxt.js/Vue application
    modules/
      core/                   # Core Vue components
      database/               # Database UI (grid, gallery, form)
      builder/                # App builder UI
      automation/             # Automation UI

  premium/                    # Premium features (paid)
    backend/                  # Kanban, Calendar, Timeline, AI field, comments
    web-frontend/             # Premium UI components

  enterprise/                 # Enterprise features (paid)
    backend/                  # RBAC, SSO, audit, teams, data sync
    web-frontend/             # Enterprise UI components
```

## Data Flow

### Request Processing
```
HTTP Request
  -> Caddy (TLS, routing)
    -> Django REST Framework
      -> Permission check (workspace role + object-level)
        -> Handler (business logic)
          -> Model (dynamic or static)
            -> PostgreSQL
              <- Response serialization
                <- JSON Response
```

### Real-Time Updates
```
WebSocket Connection
  -> Django Channels (ASGI)
    -> CoreConsumer
      -> Page subscription (table_id, view_id)
        <- Redis pub/sub
          <- Row/field/view change events
            <- JSON WebSocket message
```

### Async Task Processing
```
Task Request (export, snapshot, template install)
  -> Celery task queue
    -> Redis broker
      -> Celery worker
        -> PostgreSQL (read/write)
          -> File storage (if export)
            <- Job status update
              <- Polling or WebSocket notification
```

### Dynamic Table Management
```
Create Field
  -> FieldHandler.create_field()
    -> ALTER TABLE ADD COLUMN (PostgreSQL DDL)
      -> Update tsvector index
        -> WebSocket broadcast to subscribers

Create Table
  -> TableHandler.create_table()
    -> CREATE TABLE (PostgreSQL DDL)
      -> Register dynamic Django model
        -> Create initial fields (primary text field)
```

## Plugin Registry Pattern

```python
# Central pattern used throughout Baserow
class Registry:
    def register(self, instance):
        self.registry[instance.type] = instance

    def get(self, type_name):
        return self.registry[type_name]

# Usage:
field_type_registry.register(TextFieldType())
field_type_registry.register(NumberFieldType())
field_type_registry.register(LinkRowFieldType())
# ... 30+ field types

view_type_registry.register(GridViewType())
view_type_registry.register(GalleryViewType())
# ... 6 view types

element_type_registry.register(HeadingElementType())
element_type_registry.register(TableElementType())
# ... 20+ element types
```
