# CKAN Architecture

## Multi-Service Architecture

CKAN runs as a multi-service Python application requiring PostgreSQL, Solr, and Redis. This contrasts sharply with PocketBase's single-binary approach and OpenRegister's Nextcloud-embedded design.

### Technology Stack
- **Language:** Python 3.9+ with Flask web framework (migrated from legacy Pylons)
- **Database:** PostgreSQL with SQLAlchemy ORM, Alembic migrations
- **Search:** Apache Solr for full-text and faceted search
- **Cache/Queue:** Redis for caching and RQ background job queue
- **Frontend:** Jinja2 server-side templates, jQuery, Bootstrap 3
- **WSGI:** uWSGI or Gunicorn behind NGINX reverse proxy

### Package Structure

```
ckan/
  logic/
    action/          # Action API (get, create, update, delete, patch)
    auth/            # Authorization functions per action
    schema.py        # Navl validation schemas
  model/             # SQLAlchemy models (Package, Resource, Group, User, Tag, etc.)
  views/             # Flask blueprints (dataset, organization, user, admin, etc.)
  lib/
    search/          # Solr integration (query builder, indexer)
    dictization/     # Model-to-dict conversion layer
    plugins.py       # Plugin loading and discovery
  plugins/
    interfaces.py    # 25+ plugin interfaces (IActions, IFacets, IBlueprint, etc.)
    toolkit.py       # Helper toolkit exposed to plugins
  templates/         # Jinja2 templates
  public/            # Static assets (CSS, JS, images)
  migration/         # Alembic database migrations
ckanext/
  datastore/         # DataStore extension (structured data storage)
  datapusher/        # DataPusher integration
  activity/          # Activity stream extension
  stats/             # Usage statistics extension
  text_view/         # Text file preview
  image_view/        # Image preview
  datatables_view/   # DataTables grid view
  recline_view/      # Recline.js data explorer
```

### Database Schema

CKAN uses PostgreSQL with SQLAlchemy declarative models. Core tables:

```sql
-- Datasets
CREATE TABLE package (
    id          TEXT PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,
    title       TEXT,
    version     VARCHAR(100),
    url         TEXT,
    author      TEXT,
    notes       TEXT,
    license_id  TEXT,
    type        TEXT DEFAULT 'dataset',
    owner_org   TEXT REFERENCES group(id),
    private     BOOLEAN DEFAULT FALSE,
    state       TEXT DEFAULT 'active',
    extras      JSONB,              -- custom key-value metadata
    metadata_created  TIMESTAMP,
    metadata_modified TIMESTAMP
);

-- Data files / URLs
CREATE TABLE resource (
    id          TEXT PRIMARY KEY,
    package_id  TEXT REFERENCES package(id),
    url         TEXT NOT NULL,
    format      TEXT,
    description TEXT,
    name        TEXT,
    size        BIGINT,
    mimetype    TEXT,
    hash        TEXT,
    state       TEXT DEFAULT 'active',
    extras      JSONB
);

-- Organizations and Groups (shared table)
CREATE TABLE "group" (
    id              TEXT PRIMARY KEY,
    name            TEXT UNIQUE NOT NULL,
    title           TEXT,
    type            TEXT NOT NULL,
    description     TEXT,
    is_organization BOOLEAN DEFAULT FALSE,
    state           TEXT DEFAULT 'active',
    extras          JSONB
);

-- Membership (polymorphic: users, packages, or child groups)
CREATE TABLE member (
    id          TEXT PRIMARY KEY,
    table_name  TEXT NOT NULL,     -- 'user', 'package', or 'group'
    table_id    TEXT NOT NULL,     -- ID of the member entity
    capacity    TEXT NOT NULL,     -- 'admin', 'editor', 'member', 'public', 'private'
    group_id    TEXT REFERENCES group(id),
    state       TEXT DEFAULT 'active'
);
```

### Action API Pattern

CKAN uses a functional action API pattern rather than class-based controllers:

```python
# Every API call goes through logic.get_action()
def package_create(context, data_dict):
    _check_access('package_create', context, data_dict)     # Authorization
    schema = context.get('schema') or default_create_schema()
    data, errors = _validate(data_dict, schema, context)    # Validation
    if errors:
        raise ValidationError(errors)
    # ... create the package
    return model_dictize.package_dictize(pkg, context)       # Serialization
```

Actions are organized by verb: `get.py`, `create.py`, `update.py`, `delete.py`, `patch.py`. Plugins can override or chain actions via `IActions`.

### Comparison with OpenRegister Architecture

| Aspect | CKAN | OpenRegister |
|--------|------|-------------|
| Runtime | Python Flask + uWSGI | PHP on Nextcloud + Apache |
| Database | PostgreSQL (required) | MySQL/PostgreSQL via Nextcloud |
| Search | Apache Solr (required) | Solr/Elasticsearch (optional) |
| Cache | Redis (required) | Nextcloud APCu/Redis |
| Schema storage | SQLAlchemy models + JSONB extras | Schema entities in DB |
| API generation | Action API (function-based) | REST API per register/schema |
| Admin UI | Jinja2 + jQuery + Bootstrap | Nextcloud Vue app |
| Extensions | IPlugin interfaces (25+) | PHP services + n8n workflows |
| Multi-tenancy | Organizations with member roles | Via Nextcloud users/groups |
| Deployment | Docker Compose (5+ services) | Nextcloud app install |
| Data model | Package -> Resources (files) | Register -> Schema -> Objects |
