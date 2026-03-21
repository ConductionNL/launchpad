---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Architecture

## Summary

Baserow is an open-source no-code database platform with a monorepo architecture. The backend is Django/Python (DRF + Channels for WebSocket), the frontend is Nuxt.js/Vue. The codebase is split into core (open-source), premium (paid features), and enterprise (advanced RBAC, SSO, audit log) tiers.

## Monorepo Structure

```
baserow/
  backend/           # Django REST Framework + Channels
    src/baserow/
      core/           # Workspace, users, templates, snapshots, AI, MCP, notifications
      contrib/
        database/     # Tables, fields, views, rows, formula, webhooks, tokens, search, export, data_sync, MCP
        builder/      # Application builder: pages, elements, domains, data sources, workflow actions
        automation/   # Automation workflows: nodes, triggers, actions
        dashboard/    # Dashboard widgets
        integrations/ # SMTP, Slack, AI, LocalBaserow integrations
      ws/             # WebSocket consumers, page subscriptions
      api/            # REST API layer (DRF views, serializers)
  web-frontend/       # Nuxt.js (Vue) SPA
    modules/
      core/           # Auth, workspace, settings UI
      database/       # Table grid, field editors, view components
      builder/        # App builder drag-and-drop UI
      automation/     # Automation workflow editor
      dashboard/      # Dashboard UI
      integrations/   # Integration configuration UIs
  premium/            # Kanban, Calendar, Timeline views; AI fields; row comments; export
  enterprise/         # RBAC roles, SSO (SAML/OAuth), audit log, teams, field permissions, data sync connectors
  formula/            # ANTLR4 grammar for formula parser (BaserowFormula.g4)
  e2e-tests/          # End-to-end test suite
  docs/               # API and developer documentation
```

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend framework | Django 4.x + Django REST Framework |
| Real-time | Django Channels (WebSocket via ASGI) |
| Task queue | Celery + Redis (beat scheduler) |
| Database | PostgreSQL (one DB per Baserow instance, dynamic table creation) |
| Cache | Redis + django-cachalot |
| Frontend framework | Nuxt.js 3 / Vue 3 |
| Formula parsing | ANTLR4 grammar compiled to Python |
| File storage | Local / S3-compatible |
| Reverse proxy | Caddy (built into all-in-one Docker) |
| Telemetry | OpenTelemetry + Sentry |
| AI | OpenAI, Anthropic, Mistral, Ollama, OpenRouter integrations |

## Key Architectural Patterns

### Plugin/Registry System
Baserow uses a registry pattern throughout. Field types, view types, element types, node types, integration types, export types, and data sync types are all registered via type registries. This makes the system highly extensible.

```python
# Example: every field type is registered
class TextFieldType(FieldType):
    type = "text"
    model_class = TextField
```

### Dynamic Table Generation
Unlike traditional ORMs, Baserow creates actual PostgreSQL tables dynamically for each user table. Each field addition creates a real database column. This is a core architectural decision enabling SQL-level performance for queries, filtering, and sorting.

### Polymorphic Content Types
Models use Django's ContentType framework for polymorphism. A `Field` base model has subclasses like `TextField`, `NumberField`, etc., resolved via `content_type` foreign key.

### Hierarchical Permissions
Workspace > Application (Database) > Table > View/Field. Enterprise adds role-based access at each level.

## Comparison with OpenRegister

| Aspect | Baserow | OpenRegister |
|--------|---------|-------------|
| Architecture | Standalone Django monolith | Nextcloud app (PHP) |
| Data storage | Dynamic PostgreSQL tables | JSON objects in single table |
| Schema | Dynamic DDL (ALTER TABLE) | JSON Schema validation |
| Real-time | Django Channels WebSocket | Nextcloud notification polling |
| Formula | ANTLR4 grammar, SQL expressions | N/A |
| Extensibility | Python plugin registry | PHP service container |
| Deployment | Standalone Docker / K8s | Nextcloud ecosystem |

## Code Scale

- Backend core: ~270,000 lines Python (including migrations: ~26k LOC)
- Premium: ~47,000 lines Python
- Enterprise: ~74,000 lines Python
- Frontend: ~1,600 Vue/JS/TS files
- Total backend files: ~2,100 Python files
