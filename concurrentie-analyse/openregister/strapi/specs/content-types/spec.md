---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Content Types & Schema System

## Overview

Strapi's content type system is the foundation of the entire platform. Content types are JSON schema definitions stored as files on disk that define the structure of data. They are created via the Content-Type Builder (visual GUI) or manually in code files, and auto-generate database tables, API endpoints, and admin panel forms.

## Content Type Kinds

### Collection Types
- Represent multiple entries (like a database table)
- Auto-generate CRUD API endpoints: `find`, `findOne`, `create`, `update`, `delete`
- Routes follow RESTful pattern: `GET /api/{pluralName}`, `GET /api/{pluralName}/:id`, etc.

### Single Types
- Represent a single entry (like a settings page)
- Auto-generate: `find`, `update`, `delete` (no `findOne` or `create`)
- Routes: `GET /api/{singularName}`, `PUT /api/{singularName}`, `DELETE /api/{singularName}`

## Field Types (Scalar)

| Type | DB Column | Description |
|------|-----------|-------------|
| `string` | VARCHAR | Short text, max configurable length |
| `text` | TEXT | Long text |
| `richtext` | TEXT | Rich text (markdown or blocks) |
| `blocks` | JSON | Structured block editor content |
| `email` | VARCHAR | Email with validation |
| `password` | VARCHAR | Hashed password field |
| `uid` | VARCHAR | URL-friendly unique identifier |
| `integer` | INTEGER | Whole numbers |
| `biginteger` | BIGINT | Large integers |
| `float` | FLOAT | Floating point |
| `decimal` | DECIMAL | Precise decimal |
| `boolean` | BOOLEAN | True/false |
| `date` | DATE | Date only |
| `time` | TIME | Time only |
| `datetime` | DATETIME | Date and time |
| `timestamp` | TIMESTAMP | Unix timestamp |
| `json` | JSON | Arbitrary JSON |
| `enumeration` | VARCHAR | Constrained string from list |

## Relation Types

| Relation | Description |
|----------|-------------|
| `oneToOne` | Bidirectional one-to-one |
| `oneToMany` | Bidirectional one-to-many |
| `manyToOne` | Bidirectional many-to-one |
| `manyToMany` | Bidirectional many-to-many |
| `oneWay` | Unidirectional one-to-one (no inverse) |
| `manyWay` | Unidirectional one-to-many (no inverse) |
| `morphToOne` | Polymorphic: one target of any type |
| `morphToMany` | Polymorphic: many targets of any type |
| `morphOne` | Inverse polymorphic: one source |
| `morphMany` | Inverse polymorphic: many sources |

## Components

Components are reusable groups of fields that can be embedded in content types:

- Organized in **categories** (e.g., `seo`, `layout`, `shared`)
- Have their own schema files in `src/components/{category}/{name}.json`
- Can be used as **single** or **repeatable** within a content type
- Cannot exist independently - always embedded in a parent content type
- Have their own database tables with join tables linking to parents

## Dynamic Zones

Dynamic zones are flexible content areas that accept multiple component types:

- Define a list of allowed components for the zone
- Each entry in the zone specifies its `__component` type
- Stored as JSON with component references
- Enable flexible page-building patterns (like blocks in WordPress Gutenberg)

## Schema Definition Format

Content types are defined in JSON files at `src/api/{name}/content-types/{name}/schema.json`:

```json
{
  "kind": "collectionType",
  "collectionName": "articles",
  "info": {
    "singularName": "article",
    "pluralName": "articles",
    "displayName": "Article",
    "description": "Blog articles"
  },
  "options": {
    "draftAndPublish": true
  },
  "pluginOptions": {
    "i18n": { "localized": true }
  },
  "attributes": {
    "title": { "type": "string", "required": true },
    "slug": { "type": "uid", "targetField": "title" },
    "content": { "type": "blocks" },
    "cover": { "type": "media", "multiple": false },
    "category": { "type": "relation", "relation": "manyToOne", "target": "api::category.category" },
    "seo": { "type": "component", "component": "shared.seo" },
    "sections": { "type": "dynamiczone", "components": ["layout.hero", "layout.text-block"] }
  }
}
```

## Reserved Attributes

The system reserves these attribute names (in snake_case):
- `id`, `document_id` - Identifiers
- `created_at`, `updated_at`, `published_at` - Timestamps
- `created_by`, `updated_by`, `created_by_id`, `updated_by_id` - Audit
- `entry_id`, `status`, `localizations`, `meta`, `locale` - System fields
- `__component`, `__contentType` - Type discriminators
- Any name starting with `strapi`, `_strapi`, `__strapi`

## Content-Type Builder Service

The builder service handles:
1. **Schema validation** - Validates attribute names, types, and relations
2. **UID generation** - Creates unique identifiers for content types and components
3. **File writing** - Writes schema JSON files to disk
4. **Relation management** - Manages bidirectional relation setup
5. **Component nesting** - Handles nested component creation

Key constraint: schema changes require a server restart to take effect, as the database must be re-synced.

## Relevance to OpenRegister

**Key differences:**
- Strapi schemas are file-based JSON; OpenRegister uses JSON Schema stored in the database
- Strapi requires restart for schema changes; OpenRegister applies dynamically
- Strapi components are separate schema files; OpenRegister uses nested JSON Schema `$ref`
- Strapi has dynamic zones (flexible blocks); OpenRegister uses `oneOf`/`anyOf` in JSON Schema

**Features OpenRegister could adopt:**
- Visual content-type builder concept (though OpenRegister's JSON Schema editor already exists)
- UID field type (auto-generated URL-friendly slugs from another field)
- Block editor structured content type
- Component categories for organizational grouping
