---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Internationalization (i18n)

## Overview

Strapi's i18n plugin (`@strapi/plugin-i18n`) provides first-class content localization support. It allows content types to be marked as "localized," enabling multiple language versions of each entry. The system manages locales, handles locale-aware queries, integrates with the Document Service, and in v5 adds AI-powered translation capabilities.

## Core Concepts

### Locales
- A locale is a language identifier (e.g., `en`, `fr`, `nl-NL`)
- One locale is designated as the **default locale**
- Locales are stored in the database and managed via admin UI or API
- ISO locale list is built into the plugin for selection

### Localized Content Types
- Content types opt-in to localization via `pluginOptions`:
  ```json
  {
    "pluginOptions": {
      "i18n": { "localized": true }
    }
  }
  ```
- Each entry exists in multiple locale versions sharing the same `documentId`
- Non-localized content types ignore the `locale` parameter entirely

### Per-Field Localization
- Individual fields can be marked as localized or not within a localized content type
- Non-localized fields share the same value across all locale versions
- This allows, for example, a shared `slug` but localized `title` and `content`

## Document Service Integration

The i18n plugin integrates deeply with Strapi's Document Service via transforms:

1. **defaultLocale** - If no locale provided, defaults to the system default locale
2. **localeToLookup** - Adds locale filter to database queries
3. **multiLocaleToLookup** - Handles `locale: '*'` for fetching all locale versions

### Querying Localized Content

```
# Get content in specific locale
GET /api/articles?locale=fr

# Get content in all locales
GET /api/articles?locale=*

# Default locale is used if not specified
GET /api/articles
```

## Services

| Service | Purpose |
|---------|---------|
| `locales` | CRUD for locale records, default locale management |
| `iso-locales` | Built-in ISO locale list |
| `content-types` | Check if a content type is localized |
| `localizations` | Manage locale variants of entries |
| `permissions` | Locale-aware permission checks |
| `sanitize` | Sanitize locale data in responses |
| `metrics` | Usage tracking |
| `settings` | i18n configuration |
| `ai-localizations` | AI translation service (v5, EE) |
| `ai-localization-jobs` | Async AI translation job management |

## AI Translations (v5 EE)

Strapi v5 Enterprise adds AI-powered translations:
- Automatic content translation between locales
- Async job-based processing
- Dedicated service for managing translation jobs
- Integration with content editing workflow

## Admin Panel Integration

The i18n plugin adds to the admin panel:
- Locale selector in content editing views
- Locale management in settings
- Field-level localization configuration in Content-Type Builder
- Locale-based permission configuration (restrict editors to specific locales)

## GraphQL Integration

The plugin registers GraphQL types for locale handling:
- `locale` argument on queries and mutations
- Locale filtering in the type system
- Locale-aware response formatting

## Relevance to OpenRegister

**Key differences:**
- Strapi has a dedicated i18n system; OpenRegister has no built-in i18n
- Strapi stores locale versions as related entries; would need schema support in OpenRegister
- Strapi's per-field localization is granular; JSON Schema doesn't natively support this

**Features OpenRegister could adopt:**
- Locale-as-a-dimension on objects (same documentId, different locale versions)
- Default locale fallback when no locale specified
- Per-field localization flags (some fields shared, some localized)
- AI translation as a value-add feature (could use n8n + LLM integration)
- Locale-aware API filtering
