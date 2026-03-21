---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# CLI Management

## What It Does

CKAN provides an extensive command-line interface (`ckan` CLI) for administrative tasks including database management, search index rebuilding, user management, dataset operations, and plugin-specific commands. Plugins can add their own CLI commands via the `IClick` interface.

## How It Works

The CLI is built on Click (Python CLI framework) and invoked via:
```bash
ckan -c /etc/ckan/default/ckan.ini <command> [args]
```

**Core commands:**
- `ckan db init` - Initialize database tables
- `ckan db upgrade` - Run Alembic migrations
- `ckan db downgrade` - Rollback migrations
- `ckan search-index rebuild` - Reindex all datasets in Solr
- `ckan search-index clear` - Clear Solr index
- `ckan user add <name>` - Create user account
- `ckan user setpass <name>` - Set user password
- `ckan sysadmin add <name>` - Grant sysadmin privileges
- `ckan dataset show <name>` - Display dataset metadata
- `ckan dataset list` - List all datasets
- `ckan dataset purge <name>` - Permanently delete dataset
- `ckan tracking update` - Update page view tracking
- `ckan views create` - Create default resource views
- `ckan jobs worker` - Start background job worker
- `ckan jobs list` - List pending jobs
- `ckan config-tool` - Modify configuration values

**Plugin commands via IClick:**
```python
class IClick(Interface):
    def get_commands(self):
        # Return list of Click command groups
        return [my_click_group]
```

This allows extensions like ckanext-harvest to add `ckan harvester run`, `ckan harvester gather`, etc.

**Database migrations:**
CKAN uses Alembic for database migrations. Extensions can have their own migration chains:
```bash
ckan db upgrade -p datastore    # Run DataStore migrations
ckan db upgrade -p harvest      # Run harvest extension migrations
```

## Key Source Files
- `ckan/cli/` - CLI command definitions
- `ckan/cli/db.py` - Database management commands
- `ckan/cli/search_index.py` - Solr index commands
- `ckan/cli/user.py` - User management commands
- `ckan/migration/` - Alembic migration scripts
- `ckan/plugins/interfaces.py` - `IClick` interface

## Relevance to OpenRegister

OpenRegister uses Nextcloud's `occ` command system for CLI operations. CKAN's approach of allowing plugins to register their own CLI commands via `IClick` is more extensible. The search index rebuild command is particularly relevant -- OpenRegister could benefit from a dedicated `occ openregister:reindex` command for Solr/Elasticsearch index management.
