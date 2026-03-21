---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Admin Dashboard

## Summary
PocketBase includes a full-featured admin dashboard built with Svelte, embedded in the binary and served at `/_/`. It provides collection management, record CRUD, log viewing, settings configuration, and API documentation.

## Key Features
- **Collection management**: Create, edit, delete collections with visual field editor
- **Record CRUD**: Table view with sorting, filtering, inline editing, rich text editor
- **Field type picker**: Visual selector for 13+ field types
- **API Rules editor**: Per-collection access rules with expression builder
- **API Preview**: Interactive documentation with JS/Dart SDK code samples
- **Logs viewer**: Request/audit log with chart visualization, level filtering
- **Settings panels**: Application, mail, file storage, backups, crons
- **Export/Import**: Collection schema export as JSON, import from JSON
- **Backup management**: Create, download, upload, restore backups
- **Superuser menu**: Account management, logout

## Architecture
- `ui/src/` - Svelte source code
- `ui/dist/` - Compiled static assets
- `ui/embed.go` - Go embed directive for serving UI from binary
- Admin UI is a pure SPA communicating with PocketBase API

## Screenshots Captured
15 screenshots showing: login page, dashboard, new collection, field types, products list, record edit, API preview, realtime docs, logs, settings, file storage, backups, export collections, collection fields editor, API rules editor.

## Relevance to OpenRegister
OpenRegister's admin experience is through the Nextcloud UI. PocketBase's standalone admin dashboard is more focused and polished for data management. The API Preview panel with SDK code samples is a standout feature worth considering.
