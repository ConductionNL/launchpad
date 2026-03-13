---
status: draft
source: competitive-analysis
competitor: objects-api
analyzed_date: 2026-03-12
---

# Admin Interface — Objects API

## Purpose
Django Admin interface for managing objecttypes, objects, tokens, and permissions. Includes custom actions for publishing versions, creating new versions, and importing objecttypes from URLs.

- **Product**: Objects API
- **Category**: Administration
- **Relevance to OpenRegister**: OpenRegister uses Nextcloud UI; this is Django Admin-based

## Features

### ObjectType Admin
- List: name, name_plural, allow_geometry
- Search: name, name_plural, uuid
- Inline: Last version only (shows JSON schema in readonly when published)
- Custom actions: Publish version, Create new version
- Import: Import objecttype from URL (fetches JSON schema from URL)

### Object Admin
- List: id, object_type, current_record, uuid, object_type_uuid, modified_on, created_on
- Search: uuid (can be disabled via `OBJECTS_ADMIN_SEARCH_DISABLED`)
- Inline: ObjectRecords (tabular, read-only after creation)
- Data search: Supports `key__operator__value` search syntax in admin search bar
- Filters: object_type, created_on, modified_on

### Token Admin
- List: identifier, contact_person, organization, administration, application, is_superuser
- Readonly: token (generated, shown once)
- Inline: Permissions with object_type, mode, use_fields, fields

### Permission Admin
- Custom change form with JavaScript for field-level auth UI
- Shows object serializer fields for selection
- Admin search data: Reuses `filter_queryset_by_data_attr` from API filters

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Admin UI | Django Admin (customized) | Nextcloud Vue frontend |
| Schema editing | JSON editor widget (jsonsuit) | JSON editor in Vue |
| Version management | Admin actions (publish/new) | Manual |
| Import | URL import form | No equivalent |
| Data search | key__operator__value in admin | Table search |

**Already in OpenRegister**: Admin interface for schema/object management via Nextcloud
**Not yet in OpenRegister**: Admin data search with operator syntax, one-click version publish/create, URL-based schema import
