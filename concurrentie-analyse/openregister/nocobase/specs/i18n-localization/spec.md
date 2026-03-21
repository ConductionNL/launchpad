---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# I18n & Localization

## Purpose

NocoBase supports multi-language interfaces through the `plugin-localization` plugin. It provides translation management for both system strings and user-defined content (collection titles, field labels, menu items).

## Architecture Overview

The localization system uses i18next on both server and client:

```
Source strings (code + UI schemas)
    |
    v
Localization plugin (extracts + stores translations)
    |
    v
i18next (runtime translation)
    |
    v
Rendered UI (localized)
```

## Data Model

### Localization Texts Collection
- `module` - Source module/plugin
- `text` - Original string
- Translations stored per locale

### Localization Translations Collection
- `locale` - Target language code (e.g., "zh-CN", "ja-JP")
- `text` - Original string reference
- `translation` - Translated string

## Business Logic

### Translation Sources
1. **Plugin strings** - i18n keys in plugin code (`t('key')`)
2. **UI schema strings** - Titles, descriptions, tooltips in UI schemas
3. **Collection/field labels** - User-defined names
4. **Menu item titles** - Navigation labels

### String Extraction
The system extracts translatable strings from:
- `title` property of schema nodes
- `description` property
- `x-component-props.title`, `.description`, `.tooltip`, `.children`
- `x-decorator-props.title`, `.description`
- Template patterns: `{{ t 'string' }}`

### Supported Locales
- English (en-US) - default
- Chinese Simplified (zh-CN)
- Japanese (ja-JP)
- Korean (ko-KR)
- Spanish (es-ES)
- Portuguese (pt-PT)
- German (de-DE)
- French (fr-FR)

### Locale Tester Plugin
`plugin-locale-tester` allows testing translations by switching locales without changing the system language.

## Requirements

### Functional
- System-wide language switching
- Per-user locale preference
- Translation management UI
- Auto-extraction of translatable strings
- Support for 8+ languages

### Non-functional
- Runtime language switching without reload
- Fallback to default locale for missing translations

## Comparison Notes

### vs Nextcloud l10n
- Nextcloud uses Transifex for community translations; NocoBase manages translations in-app
- Nextcloud has 100+ language support; NocoBase has 8 languages
- Both support runtime language switching
- Nextcloud uses PHP gettext; NocoBase uses i18next
- NocoBase can translate user-defined content (field names, menu labels); Nextcloud l10n is code-only
