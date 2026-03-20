---
status: reviewed
---

# i18n-infrastructure Specification

## Purpose
Establishes the standard i18n setup pattern for all Conduction Nextcloud apps so that translation functions are available throughout the Vue frontend — for both Options API and Composition API (`<script setup>`) components.

## ADDED Requirements

### Requirement: l10n-import
Each Conduction Nextcloud app with a Vue frontend MUST import the translation functions from `@nextcloud/l10n` in its main entry point (`src/main.js`).

The import MUST be:
```js
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
```

#### Scenario: App entry point includes l10n import
- GIVEN a Conduction Nextcloud app with a Vue frontend
- WHEN the app's `src/main.js` is loaded
- THEN it SHALL contain `import { translate as t, translatePlural as n } from '@nextcloud/l10n'`

#### Scenario: Import uses correct aliases
- GIVEN the `@nextcloud/l10n` import in `src/main.js`
- WHEN the import statement is parsed
- THEN `translate` SHALL be aliased as `t`
- AND `translatePlural` SHALL be aliased as `n`

### Requirement: vue-mixin-registration
Each app MUST register `t` and `n` as global Vue methods via `Vue.mixin` so they are available in Options API component templates.

The registration MUST be:
```js
Vue.mixin({ methods: { t, n } })
```

#### Scenario: Translation methods available in Options API components
- GIVEN an app with the Vue.mixin registration
- WHEN any Options API Vue component template references `t()` or `n()`
- THEN the functions SHALL resolve to the imported `@nextcloud/l10n` functions
- AND no `ReferenceError` SHALL occur

#### Scenario: Mixin registered before app mount
- GIVEN the app's `src/main.js`
- WHEN the code executes
- THEN `Vue.mixin({ methods: { t, n } })` SHALL be called before `new Vue()` or app mount

### Requirement: composition-api-import
Every Vue component using `<script setup>` (Composition API) MUST import translation functions directly, because `Vue.mixin` does NOT inject methods into `<script setup>` components.

The import MUST be:
```js
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
```

#### Scenario: Composition API component with translation calls
- GIVEN a Vue component using `<script setup>` that calls `t()` in its template or script
- WHEN the component is loaded
- THEN it SHALL contain `import { translate as t, translatePlural as n } from '@nextcloud/l10n'` in its `<script setup>` block

#### Scenario: Vue.mixin does not apply to script setup
- GIVEN a Vue component using `<script setup>` WITHOUT a direct `@nextcloud/l10n` import
- WHEN the template references `t()`
- THEN a `ReferenceError` SHALL occur because `Vue.mixin` does not inject into `<script setup>`

#### Scenario: Unused import not required
- GIVEN a `<script setup>` component that has NO translatable strings
- WHEN the component is checked
- THEN it SHALL NOT be required to import `@nextcloud/l10n`

### Requirement: l10n-directory-structure
Each app MUST have a `l10n/` directory at its root containing JSON translation files.

#### Scenario: Translation directory exists
- GIVEN a Conduction Nextcloud app
- WHEN the app repository is checked
- THEN a `l10n/` directory SHALL exist at the app root

#### Scenario: English translation file exists
- GIVEN the `l10n/` directory
- WHEN its contents are listed
- THEN it SHALL contain an `en.json` file

#### Scenario: Dutch translation file exists
- GIVEN the `l10n/` directory
- WHEN its contents are listed
- THEN it SHALL contain an `nl.json` file

### Requirement: translation-file-format
Translation files MUST follow Nextcloud's standard JSON format and include both frontend and backend strings.

#### Scenario: Valid translation file structure
- GIVEN a translation file `l10n/{locale}.json`
- WHEN the file is parsed as JSON
- THEN it SHALL have a top-level `translations` object
- AND each key in `translations` SHALL be a source string
- AND each value SHALL be the translated string

#### Scenario: Translation file contains both frontend and backend strings
- GIVEN an app with both frontend `t('appid', 'string')` calls and backend `$l->t('string')` calls
- WHEN the `l10n/{locale}.json` file is checked
- THEN it SHALL contain keys for both frontend and backend strings in a single file

#### Scenario: Translation file example
- GIVEN `l10n/nl.json` for an app
- WHEN the file is read
- THEN it SHALL follow this structure:
```json
{
  "translations": {
    "Source string": "Vertaalde tekst",
    "Failed to save": "Opslaan mislukt"
  }
}
```

## Affected Apps

**Options API only** (mixin sufficient): `mydash`, `procest`, `pipelinq`, `larpingapp` (already compliant)

**Mixed Options + Composition API** (mixin + per-component imports): `openregister`, `opencatalogi`, `openconnector`, `docudesk`, `softwarecatalog`, `zaakafhandelapp`

**Excluded**: `nldesign` (CSS-only, no Vue frontend)
