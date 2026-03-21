---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Datasets and Resources

## What It Does

CKAN's core data model is built around Packages (datasets) and Resources. A Package is a metadata container describing a dataset -- with title, description, author, license, organization ownership, and JSONB extras for arbitrary key-value metadata. Resources are individual data files or URLs attached to a Package.

## How It Works

The `package` table uses SQLAlchemy with PostgreSQL JSONB for extras. Key columns include: `id`, `name` (unique slug), `title`, `version`, `url`, `author`, `author_email`, `maintainer`, `notes` (description), `license_id`, `type` (default "dataset"), `owner_org`, `private`, `state` (active/deleted), `extras` (JSONB), `metadata_created`, `metadata_modified`.

Resources are linked via `package_id` foreign key and store: `url`, `format`, `name`, `description`, `size`, `mimetype`, `hash`, `state`, `extras`.

The API provides full CRUD via action functions: `package_create`, `package_show`, `package_update`, `package_patch`, `package_delete` (soft delete). All actions go through authorization (`_check_access`) and schema validation (`_validate`) before touching the database.

Packages support relationships to other packages, tags (free-text and from controlled vocabularies), and membership in organizations/groups.

## Key Source Files
- `ckan/model/package.py` - Package table (80+ lines of column definitions)
- `ckan/model/resource.py` - Resource table with URL, format, hash, size
- `ckan/logic/action/create.py` - `package_create` (1477 lines total)
- `ckan/logic/action/get.py` - `package_show`, `package_search` (3198 lines total)

## Relevance to OpenRegister

OpenRegister uses a Register -> Schema -> Object hierarchy which is more flexible than CKAN's Package -> Resource model. CKAN resources are file references while OpenRegister objects are schema-validated data records. However, CKAN's rich metadata model (author, license, DCAT fields) shows the value of standardized metadata for government data interoperability.
