# PocketBase Architecture

## Single Binary Philosophy

PocketBase's defining characteristic is its single-binary architecture. The entire backend -- database, API server, admin UI, file storage, auth system -- ships as one Go executable with zero external dependencies.

### Technology Stack
- **Language:** Go 1.24+
- **Database:** SQLite (via modernc.org/sqlite, pure Go implementation)
- **HTTP Router:** Custom router (`tools/router/`)
- **Admin UI:** Svelte SPA (compiled and embedded via `go:embed`)
- **JS Runtime:** goja (Go-based JavaScript engine for hooks)
- **Auth:** JWT tokens, bcrypt passwords, OAuth2
- **File Storage:** Local filesystem or S3-compatible

### Package Structure

```
pocketbase/
  core/           # Core app interface, models, DB operations (72 Go files)
  apis/           # REST API handlers (CRUD, auth, realtime, files)
  forms/          # Form validation
  migrations/     # Database migration system
  plugins/
    jsvm/         # JavaScript VM for hooks
    migratecmd/   # Migration command generator
    ghupdate/     # GitHub auto-update
  tools/          # Utilities (auth, cron, filesystem, search, etc.)
  ui/             # Svelte admin dashboard (embedded)
```

### Database Schema

PocketBase uses a meta-driven approach where collection definitions are stored in the `_collections` table:

```sql
CREATE TABLE _collections (
  id         TEXT PRIMARY KEY,
  system     BOOLEAN DEFAULT FALSE,
  type       TEXT DEFAULT "base",    -- base, auth, view
  name       TEXT UNIQUE NOT NULL,
  fields     JSON DEFAULT "[]",      -- field definitions
  indexes    JSON DEFAULT "[]",
  listRule   TEXT DEFAULT NULL,       -- API access rules
  viewRule   TEXT DEFAULT NULL,
  createRule TEXT DEFAULT NULL,
  updateRule TEXT DEFAULT NULL,
  deleteRule TEXT DEFAULT NULL,
  options    JSON DEFAULT "{}",
  created    TEXT,
  updated    TEXT
);
```

Each collection gets its own SQLite table with columns matching its field definitions.

### Event/Hook System

PocketBase uses a priority-based hook system with bind/unbind:

```go
app.OnRecordCreate("products").Bind(&hook.Handler[*RecordEvent]{
    Func: func(e *RecordEvent) error {
        // custom logic
        return e.Next()
    },
    Priority: 0,
})
```

Hooks fire at multiple lifecycle points: Validate, Create, CreateExecute, AfterCreateSuccess, AfterCreateError, and equivalents for Update/Delete.

### Comparison with OpenRegister Architecture

| Aspect | PocketBase | OpenRegister |
|--------|-----------|-------------|
| Runtime | Single Go binary | PHP on Nextcloud + Apache |
| Database | Embedded SQLite | MySQL/PostgreSQL via Nextcloud |
| Schema storage | `_collections` JSON fields | Schema entities in DB |
| API generation | Automatic per collection | Automatic per register/schema |
| Admin UI | Embedded Svelte SPA | Nextcloud Vue app |
| Extensions | Go/JS hooks | PHP services + n8n workflows |
| Multi-tenancy | None (single DB) | Via Nextcloud users/groups |
| Deployment | Download and run | Nextcloud app install |
