---
status: draft
source: competitive-analysis
competitor: baserow
analyzed_date: 2026-03-14
---

# Key Takeaways for OpenRegister

## Features Worth Adopting

### 1. Formula / Computed Fields (High Priority)

Baserow's ANTLR4-based formula engine allows users to define computed properties that execute at the database level. This is a significant productivity feature.

**What to adopt:** A simplified formula/expression system for JSON Schema properties. Rather than building a full ANTLR grammar, OpenRegister could support:
- Basic arithmetic on numeric fields
- String concatenation and formatting
- Date calculations
- Conditional expressions (if/then)
- Aggregate lookups across related objects

**Spec reference:** `specs/formula-system/spec.md`

### 2. Additional View Types (Medium Priority)

Baserow offers Gallery (card layout) and Form (data entry) views in the open-source tier, plus Kanban, Calendar, and Timeline in premium.

**What to adopt:**
- **Gallery view** — Card-based browsing of register objects, useful for catalogs
- **Form view** — Structured data entry forms generated from JSON Schema (already partially achievable)
- **Kanban view** — Status-based workflow boards for process tracking

**Spec reference:** `specs/view-types/spec.md`

### 3. Data Export (Medium Priority)

Baserow supports CSV export with view filters/sorts applied, plus JSON and Excel in premium.

**What to adopt:**
- CSV export of filtered/sorted object lists
- JSON export of register contents
- Export scoped to current view configuration

**Spec reference:** `specs/search-export/spec.md`

### 4. Webhooks (Medium Priority)

Baserow provides per-table webhooks triggered on row create/update/delete events.

**What to adopt:**
- Per-register/schema webhook configuration
- Event types: object.created, object.updated, object.deleted
- Configurable HTTP endpoint, headers, and payload format
- Reduces dependency on n8n for simple event-driven integrations

**Spec reference:** `specs/webhooks-integrations/spec.md`

### 5. Row-Level History (Low Priority)

Baserow tracks field-level changes with before/after values per row.

**What to adopt:**
- Object-level change history showing which properties changed
- Before/after value display
- User attribution and timestamps
- Accessible via object detail view

**Spec reference:** `specs/real-time-collaboration/spec.md`

### 6. Templates (Low Priority)

Baserow provides pre-built database templates for common use cases.

**What to adopt:**
- Pre-built register/schema templates for Dutch government use cases (e.g., Zaakafhandeling, Softwarecatalogus patterns)
- One-click installation of template into a register

**Spec reference:** `specs/templates-snapshots/spec.md`

## OpenRegister's Differentiators to Preserve

These advantages should be actively maintained and communicated:

1. **Nextcloud Integration** — Native embedding in the Nextcloud ecosystem (files, sharing, users, apps) vs. Baserow's standalone deployment
2. **NL Design System** — Government theming support that Baserow completely lacks
3. **JSON Schema** — Standards-based data modeling with complex validation, nested structures, and schema references vs. Baserow's proprietary field system
4. **Mature MCP** — Production-ready MCP server (full CRUD, JSON-RPC 2.0, streamable HTTP) vs. Baserow's ~400 LOC early implementation
5. **Government Focus** — NLGov API compliance, faceted catalog search, registry management patterns
6. **Lightweight Deployment** — Single Nextcloud app vs. PostgreSQL + Redis + Celery + Caddy stack
7. **Faceted Search** — Purpose-built catalog browsing with configurable facets vs. basic full-text search

## Architectural Patterns Worth Studying

### Registry/Plugin Pattern
Baserow's `Registry` class with `register()` and `get()` methods provides clean extensibility. Each field type, view type, element type, and automation node is a registered plugin class. This is similar to OpenRegister's approach but more formalized.

### Dynamic Model Generation
Baserow's `get_model()` generates Django model classes dynamically from table/field definitions. While OpenRegister uses JSON objects rather than dynamic tables, the pattern of runtime model construction from schema definitions is worth understanding.

### WebSocket Page Subscriptions
Baserow's approach of subscribing to "pages" (e.g., a specific table view) rather than individual records is efficient for real-time updates. Users only receive events for the data they're currently viewing.

## Competitive Positioning

Baserow is a strong general-purpose no-code database platform. Its strengths (polished UI, formula engine, application builder, real-time collaboration) serve business users building internal tools.

OpenRegister serves a different niche: government data management with standards compliance, Nextcloud integration, and registry/catalog patterns. The overlap is in basic data CRUD, but the target users and use cases differ significantly.

**Baserow is NOT a direct threat to OpenRegister** in the Dutch government market because:
1. No Nextcloud integration (the primary deployment platform for Dutch government)
2. No NL Design System support
3. No NLGov API compliance
4. Heavier deployment requirements
5. Premium/enterprise features require paid licenses

**Baserow IS a source of inspiration** for features that would enhance OpenRegister's competitiveness in broader markets.
