---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# Migrations System

## Summary
PocketBase includes a database migration system that tracks applied migrations and supports up/down operations. It also provides auto-migration generation based on collection changes.

## Key Features
- Migration files tracked in `_migrations` table
- Commands: `up` (apply all), `down [n]` (revert last n), `history-sync`
- System migrations for core schema updates
- App migrations for user-defined changes
- Auto-migration generation via `migratecmd` plugin
- Go and JavaScript migration formats supported
- Collection snapshot/restore via JSON export

## Architecture
- `core/migrations_runner.go` - Migration execution engine
- `core/migrations_list.go` - Migration list management
- `migrations/` - Built-in system migrations
- `plugins/migratecmd/` - Auto-migration generation from collection changes

## Relevance to OpenRegister
OpenRegister uses Nextcloud's migration system (IRepairStep). PocketBase's approach of auto-generating migrations from UI changes is more developer-friendly and reduces manual migration writing.
