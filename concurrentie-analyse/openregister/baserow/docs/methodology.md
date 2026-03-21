---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Analysis Methodology

## Scope

This competitive analysis examines Baserow v2.1.6 as a competitor to OpenRegister, covering both source code analysis and hands-on UI evaluation.

## Approach

### Part 1: Codebase Analysis

The Baserow repository (https://github.com/baserow/baserow) was cloned and analyzed across 10 focus areas:

1. **Architecture** — Monorepo structure, tech stack, plugin/registry patterns
2. **Data Modeling** — Workspace > Database > Table > Field/Row hierarchy, dynamic DDL
3. **Field Types** — 30+ field type implementations and PostgreSQL column mappings
4. **View Types** — 6 view types (Grid, Gallery, Form, Kanban, Calendar, Timeline)
5. **Formula System** — ANTLR4 grammar, AST, type inference, SQL expression generation
6. **REST API** — Endpoint structure, filtering, pagination, batch operations
7. **Webhooks & Integrations** — Event webhooks, service integrations, data sync
8. **Application Builder** — Pages, elements, data sources, workflow actions, domains
9. **Automations** — Trigger nodes, action nodes, execution model
10. **Permissions & RBAC** — Workspace roles, API tokens, enterprise RBAC, field permissions

Additional areas discovered during analysis and documented:
- Real-time collaboration (WebSocket)
- AI features (5 provider integrations)
- MCP support (early implementation)
- Templates & snapshots
- Search & export
- Data sync sources

### Part 2: Docker Walkthrough

Baserow was deployed locally using Docker on port 9023:

```bash
docker run -d --name baserow-eval \
  -e BASEROW_PUBLIC_URL=http://localhost:9023 \
  -e DISABLE_VOLUME_CHECK=yes \
  -e BASEROW_AMOUNT_OF_GUNICORN_WORKERS=1 \
  -p 9023:80 \
  -v baserow_data2:/baserow/data \
  --memory=6g \
  baserow/baserow:1.31.1
```

A Playwright browser session systematically walked through every accessible page, capturing 24 screenshots covering:
- User registration and onboarding flow
- All view types (grid, gallery, form)
- Row detail and history
- Field type selection
- Filter and sort configuration
- Workspace and member management
- Admin settings and dashboard
- Application builder and element palette
- Automation builder (triggers and actions)
- API documentation
- Health checks

## Key Files Analyzed

| Area | Primary File(s) | Lines |
|------|-----------------|-------|
| Field types | `backend/src/baserow/contrib/database/fields/field_types.py` | 7,505 |
| View types | `backend/src/baserow/contrib/database/views/view_types.py` | 1,440 |
| Formula grammar | `backend/src/baserow/contrib/database/formula/parser/BaserowFormula.g4` | ~200 |
| Builder elements | `backend/src/baserow/contrib/builder/elements/element_types.py` | 2,520 |
| Automation nodes | `backend/src/baserow/contrib/automation/nodes/node_types.py` | 426 |
| WebSocket | `backend/src/baserow/ws/consumers.py` | ~300 |
| RBAC roles | `enterprise/backend/src/baserow_enterprise/role/default_roles.py` | ~150 |
| MCP tools | `backend/src/baserow/contrib/database/mcp/rows/tools.py` | 277 |
| AI models | `backend/src/baserow/core/generative_ai/` | ~500 |

## Licensing Tiers Identified

- **Core (MIT)**: Grid, Gallery, Form views, 25+ field types, formula engine, REST API, webhooks, automations, real-time collaboration, basic permissions, templates, snapshots, MCP
- **Premium**: Kanban, Calendar, Timeline views, AI field type, row comments, personal views
- **Enterprise**: RBAC (6 roles), field permissions, teams, SSO (SAML/OAuth), audit logging, data sync (7 sources)

## Tools Used

- Git for repository cloning
- ripgrep/grep for code search
- Playwright (via MCP) for browser automation and screenshots
- Docker for running the Baserow instance
