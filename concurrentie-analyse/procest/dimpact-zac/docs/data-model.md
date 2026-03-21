# ZAC Data Model

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/dataModel.md

## Data Stores

### PostgreSQL Database

Two schemas:

| Schema | Description |
|--------|-------------|
| `zaakafhandelcomponent` | Main ZAC application tables |
| `flowable` | Flowable process engine tables (CMMN/BPMN) |

- Both schemas must exist before startup
- Tables auto-created on first start, auto-updated on version upgrades
- Uses **Flyway** for database schema versioning

### File System

- ZAC itself has NO persistent file system
- Solr stores its search index on the file system (separate runtime)
- Internal endpoint available to recreate Solr index from source data

### In-Memory Data

| Data Type | Description |
|-----------|-------------|
| Session data | Logged-in user session data (OIDC); auto-recreated if lost |
| Cached data | External ZGW data cached for performance (via **Caffeine** library) |

Notes:
- Cache starts empty; first user experiences slower performance
- No way to automatically pre-fill cache
- Internal endpoint available to clear specific caches
