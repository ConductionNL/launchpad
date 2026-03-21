---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Translations & Internationalization

## Overview

Directus provides two levels of i18n support: system-level string translations for the admin UI, and content-level translations via a dedicated relational field type. The system supports custom translation strings and content translation workflows.

## System Translations

### Custom String Translations
Stored in `directus_translations`:
- **Key**: Translation string identifier
- **Language**: ISO language code
- **Value**: Translated text

These allow customizing the admin UI labels, button text, and messages per language. The `TranslationsService` enforces unique key+language combinations.

### Admin UI Language
- The admin app supports 50+ languages
- Language files are stored in `app/src/lang/`
- Users can set their preferred language in their profile

## Content Translations

### Translations Interface
Directus provides a dedicated `translations` relationship type that creates a structured i18n pattern:

1. **Main collection**: e.g., `products` (language-agnostic fields like SKU, price)
2. **Translations collection**: e.g., `products_translations` (language-specific fields like name, description)
3. **Languages collection**: e.g., `languages` (list of supported languages)

The translations interface automatically:
- Creates a junction-table-like relationship
- Presents a tabbed UI for editing content in multiple languages
- Groups translated fields by language
- Shows translation completeness indicators

### Configuration
```json
{
  "field": "translations",
  "type": "alias",
  "meta": {
    "interface": "translations",
    "special": ["translations"],
    "options": {
      "languageField": "code",
      "defaultLanguage": "en-US",
      "userLanguage": true
    }
  }
}
```

### API Access
Translations are accessed as nested relational data:
```
GET /items/products/1?fields=*,translations.*
```

Filtering by language:
```
GET /items/products?deep[translations][_filter][languages_code][_eq]=nl-NL
```

## Field-Level Translations

Individual field labels can be translated:
```json
{
  "field": "title",
  "meta": {
    "translations": [
      { "language": "en-US", "translation": "Title" },
      { "language": "nl-NL", "translation": "Titel" }
    ]
  }
}
```

## Relevance to OpenRegister

OpenRegister does not currently have built-in i18n support for content. For Dutch government use cases, this is relevant because:
- Government services must support Dutch and often English
- Frisian is an official language in some regions
- Content management for multilingual public services

Options for OpenRegister:
1. Implement a translations-style relational pattern similar to Directus
2. Store translations as JSON within object properties
3. Leverage Nextcloud's built-in localization for UI strings
4. Build translation workflows using n8n + translation APIs
