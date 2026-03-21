# PocketBase - Competitive Analysis for OpenRegister

## Overview

PocketBase is an open-source backend-as-a-service (BaaS) written in Go that ships as a single binary. It provides a SQLite-based database with auto-generated REST APIs, built-in authentication, file storage, realtime subscriptions, and an admin dashboard -- all with zero external dependencies.

**Repository:** https://github.com/pocketbase/pocketbase
**License:** MIT
**Language:** Go (backend), Svelte (admin UI)
**Version analyzed:** v0.25.9
**Analysis date:** 2026-03-14

## Architecture

### Single Binary Design
PocketBase compiles to a single Go binary (~30MB) that embeds:
- SQLite database (via modernc.org/sqlite, pure Go)
- REST API server with built-in router
- Admin dashboard (Svelte SPA, embedded via Go embed)
- File storage (local filesystem or S3)
- Realtime SSE broker
- Cron scheduler
- Mail client (SMTP/sendmail)

### Core Data Model
- **Collections** = database tables with auto-generated CRUD APIs
- **Records** = rows within collections
- Three collection types: **Base**, **Auth**, **View**
- Fields are strongly typed: text, number, bool, email, URL, date, editor, select, file, relation, JSON, password, autodate, geo_point

### Key Source Files (262 Go files total)
- `core/app.go` (1541 lines) - Main App interface with 100+ methods
- `core/collection_model.go` (1073 lines) - Collection model with hooks
- `core/record_model.go` (1620 lines) - Record model with data store
- `apis/record_crud.go` - Auto-generated CRUD endpoints
- `apis/realtime.go` - SSE realtime subscriptions
- `apis/record_auth.go` - Auth endpoints (password, OAuth2, OTP, MFA)
- `plugins/jsvm/` - JavaScript hooks runtime (goja engine)

## Strengths vs OpenRegister

1. **Zero-dependency deployment** - Single binary, no PHP/Apache/PostgreSQL needed
2. **Instant API generation** - Create a collection, get REST API immediately
3. **Built-in admin UI** - Full-featured Svelte dashboard with record editing
4. **Realtime out of the box** - SSE subscriptions per collection/record
5. **Per-collection API rules** - Filter-based access control rules
6. **Multi-auth support** - Email/password, OAuth2 (20+ providers), OTP, MFA
7. **File handling** - Upload, thumbnails, S3 support built-in
8. **API Preview** - Interactive API docs with JS/Dart SDK code samples
9. **Export/Import collections** - JSON-based schema portability
10. **Extremely fast** - SQLite + Go = sub-millisecond query times

## Weaknesses vs OpenRegister

1. **No Nextcloud integration** - Standalone only, no ecosystem
2. **SQLite limitations** - Single-writer, no concurrent write scaling
3. **No multi-tenancy** - Single database per instance
4. **No workflow engine** - No n8n or business logic layer
5. **No schema validation standards** - No JSON Schema, no OAS export
6. **Limited search** - SQLite FTS only, no Solr/Elasticsearch faceting
7. **No government compliance** - No NL Design System, no WCAG focus
8. **No data harvesting** - No federation or data source syncing
9. **No register/schema hierarchy** - Flat collection model only
10. **No plugin marketplace** - Extensions require Go/JS coding

## Relevance to OpenRegister

PocketBase represents the "developer simplicity" end of the spectrum. Its strongest lessons for OpenRegister are:
- **Auto-generated API documentation** with live code samples
- **Per-collection access rules** using a filter expression language
- **Collection export/import** for environment portability
- **Built-in realtime** via SSE (OpenRegister could add this)
- **Rich admin UI** with inline record editing and field type selection
