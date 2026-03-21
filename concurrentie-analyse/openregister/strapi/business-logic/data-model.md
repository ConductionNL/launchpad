# Strapi Data Model

## Core Database Tables

```
strapi_core_store_settings
    |-- id (increments)
    |-- key (string) -- e.g., "plugin_i18n_default_locale"
    |-- value (json)
    |-- type (string)
    |-- environment (string)
    |-- tag (string)

strapi_webhooks
    |-- id (increments)
    |-- name (string)
    |-- url (text)
    |-- headers (json)
    |-- events (json) -- ["entry.create", "entry.update"]
    |-- enabled (boolean)

strapi_migrations
    |-- id (increments)
    |-- name (string) -- migration file name
    |-- time (datetime)

strapi_database_schema
    |-- id (increments)
    |-- schema (json) -- full schema snapshot for diff
    |-- time (datetime)
    |-- hash (string)
```

## Content Type Table Pattern

For each content type `api::article.article`:

```
articles (collection name)
    |-- id (increments, auto PK)
    |-- document_id (string) -- stable document identifier
    |-- [user-defined columns from schema attributes]
    |-- created_at (datetime)
    |-- updated_at (datetime)
    |-- published_at (datetime, null = draft)
    |-- created_by_id (FK -> admin_users.id)
    |-- updated_by_id (FK -> admin_users.id)
    |-- locale (string, if i18n enabled)
```

## Relation Tables

### One-to-Many / Many-to-One
Uses foreign key on the "many" side:
```
articles
    |-- category_id (FK -> categories.id)
```

### Many-to-Many
Uses join table:
```
articles_categories_lnk
    |-- id (increments)
    |-- article_id (FK -> articles.id)
    |-- category_id (FK -> categories.id)
    |-- article_ord (float) -- ordering on article side
    |-- category_ord (float) -- ordering on category side
```

### Polymorphic Relations (morphToMany)
```
files_related_mph
    |-- id (increments)
    |-- file_id (FK -> files.id)
    |-- related_id (integer) -- target entity ID
    |-- related_type (string) -- target content type UID
    |-- field (string) -- attribute name on target
    |-- order (float) -- ordering
```

## Component Tables

Each component type gets its own table:
```
components_shared_seos
    |-- id (increments)
    |-- title (string)
    |-- description (text)
    |-- keywords (string)
```

Component-to-parent join tables:
```
articles_cmps
    |-- id (increments)
    |-- entity_id (FK -> articles.id)
    |-- cmp_id (integer) -- component record ID
    |-- component_type (string) -- component UID
    |-- field (string) -- attribute name on parent
    |-- order (float) -- ordering (for repeatable)
```

## Admin & Auth Tables

```
admin_users
    |-- id, firstname, lastname, username, email
    |-- password (hashed)
    |-- reset_password_token
    |-- registration_token
    |-- is_active, blocked
    |-- prefered_language
    |-- created_at, updated_at

admin_roles
    |-- id, name, code, description
    |-- created_at, updated_at

admin_permissions
    |-- id
    |-- action (string) -- e.g., "plugin::content-manager.explorer.read"
    |-- action_parameters (json)
    |-- subject (string) -- content type UID or null
    |-- properties (json) -- { fields: [...], locales: [...] }
    |-- conditions (json) -- ["admin::is-creator"]
    |-- created_at, updated_at

admin_users_roles_lnk
    |-- id, user_id, role_id

admin_permissions_role_lnk
    |-- id, permission_id, role_id
```

## Users & Permissions (Public) Tables

```
up_users
    |-- id, username, email, provider
    |-- password (hashed)
    |-- reset_password_token
    |-- confirmation_token
    |-- confirmed (boolean)
    |-- blocked (boolean)
    |-- created_at, updated_at

up_roles
    |-- id, name, description, type
    |-- created_at, updated_at

up_permissions
    |-- id
    |-- action (string) -- e.g., "api::article.article.find"
    |-- created_at, updated_at

up_users_role_lnk
    |-- id, user_id, role_id

up_permissions_role_lnk
    |-- id, permission_id, role_id
```

## Media Library Tables

```
files
    |-- id, name, alternative_text, caption
    |-- width, height (integer, images only)
    |-- formats (json) -- { thumbnail: {...}, small: {...}, ... }
    |-- hash (string) -- unique file hash
    |-- ext (string) -- .jpg, .pdf, etc.
    |-- mime (string) -- MIME type
    |-- size (decimal) -- file size in KB
    |-- url (string) -- public URL
    |-- preview_url (string)
    |-- provider (string) -- "local", "aws-s3", etc.
    |-- provider_metadata (json)
    |-- folder_path (string)
    |-- created_at, updated_at
    |-- created_by_id, updated_by_id

upload_folders
    |-- id, name, path_id (integer), path (string)
    |-- created_at, updated_at
    |-- created_by_id, updated_by_id

upload_folders_parent_lnk
    |-- id, folder_id, inv_folder_id (parent)
    |-- folder_ord (float)
```

## i18n Tables

```
i18n_locale
    |-- id, name, code (string) -- e.g., "English (en)", "en"
    |-- created_at, updated_at
    |-- created_by_id, updated_by_id
```

## History Tables

```
strapi_history_versions
    |-- id
    |-- content_type (string) -- content type UID
    |-- related_document_id (string) -- document ID
    |-- locale (string)
    |-- status (string) -- draft/published
    |-- data (json) -- full entry snapshot
    |-- schema (json) -- schema at time of version
    |-- created_at
    |-- created_by_id (FK -> admin_users.id)
```

## Key Design Patterns

1. **Link tables with `_lnk` suffix** for all many-to-many relations
2. **Component join tables with `_cmps` suffix** for component embedding
3. **Morphic tables with `_mph` suffix** for polymorphic relations
4. **`document_id`** as stable identifier across draft/published/locale variants
5. **`*_ord` columns** (float) for maintaining order in relations and components
6. **Separate admin and public user systems** with independent role/permission tables
