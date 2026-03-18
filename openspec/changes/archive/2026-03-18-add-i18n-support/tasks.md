# Tasks: add-i18n-support

## 1. Fix Broken Frontend Imports (Category B)

### Task 1.1: Fix openconnector main.js i18n import
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `openconnector/src/main.js`
- **acceptance_criteria**:
  - GIVEN openconnector's main.js WHEN loaded THEN `t` and `n` are imported from `@nextcloud/l10n` and no ReferenceError occurs
- [x] Add `import { translate as t, translatePlural as n } from '@nextcloud/l10n'` to main.js
- [x] Verify `Vue.mixin({ methods: { t, n } })` is present

### Task 1.2: Fix docudesk main.js i18n import
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `docudesk/src/main.js`
- **acceptance_criteria**:
  - GIVEN docudesk's main.js WHEN loaded THEN `t` and `n` are imported from `@nextcloud/l10n` and no ReferenceError occurs
- [x] Add `import { translate as t, translatePlural as n } from '@nextcloud/l10n'` to main.js
- [x] Verify `Vue.mixin({ methods: { t, n } })` is present

### Task 1.3: Fix softwarecatalog main.js i18n import
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `softwarecatalog/src/main.js`
- **acceptance_criteria**:
  - GIVEN softwarecatalog's main.js WHEN loaded THEN `t` and `n` are imported from `@nextcloud/l10n` and no ReferenceError occurs
- [x] Add `import { translate as t, translatePlural as n } from '@nextcloud/l10n'` to main.js
- [x] Verify `Vue.mixin({ methods: { t, n } })` is present

## 2. Fix Partial i18n Setups (Category C)

### Task 2.1: Fix procest main.js i18n import
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `procest/src/main.js`
- **acceptance_criteria**:
  - GIVEN procest's main.js WHEN loaded THEN `t` and `n` are properly imported
- [x] Add missing import statement if not present
- [x] Verify existing l10n/en.json and l10n/nl.json are valid format

### Task 2.2: Fix pipelinq main.js i18n import
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `pipelinq/src/main.js`
- **acceptance_criteria**:
  - GIVEN pipelinq's main.js WHEN loaded THEN `t` and `n` are properly imported
- [x] Add missing import statement if not present
- [x] Verify existing l10n/en.json and l10n/nl.json are valid format

## 3. Ensure main.js Infrastructure (Category A — already using t())

### Task 3.1: Verify openregister main.js i18n setup
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `openregister/src/main.js`
- **acceptance_criteria**:
  - GIVEN openregister's main.js WHEN inspected THEN import + mixin are both present and correct
- [x] Verify or add `@nextcloud/l10n` import
- [x] Verify or add `Vue.mixin({ methods: { t, n } })`

### Task 3.2: Verify opencatalogi main.js i18n setup
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `opencatalogi/src/main.js`
- **acceptance_criteria**:
  - GIVEN opencatalogi's main.js WHEN inspected THEN import + mixin are correct
- [x] Verify import and mixin are present (already confirmed working)

### Task 3.3: Verify mydash main.js i18n setup
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `mydash/src/main.js`
- **acceptance_criteria**:
  - GIVEN mydash's main.js WHEN inspected THEN import + mixin are correct
- [x] Verify import and mixin are present (already confirmed working)

### Task 3.4: Add i18n infrastructure to zaakafhandelapp
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-l10n-import`
- **files**: `zaakafhandelapp/src/main.js`
- **acceptance_criteria**:
  - GIVEN zaakafhandelapp's main.js WHEN loaded THEN t and n are imported and registered
- [x] Add `@nextcloud/l10n` import
- [x] Add or verify `Vue.mixin({ methods: { t, n } })`

## 4. Add Composition API Per-Component Imports

### Task 4.1: Add per-component imports in openconnector (86 `<script setup>` files)
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-composition-api-import`
- **files**: `openconnector/src/**/*.vue` (files using `<script setup>` with `t()` calls)
- **acceptance_criteria**:
  - GIVEN a `<script setup>` component using `t()` WHEN loaded THEN it has its own import and no ReferenceError occurs
- [x] Identify all `<script setup>` files that use `t()` in template or script
- [x] Add `import { translate as t, translatePlural as n } from '@nextcloud/l10n'` to each

### Task 4.2: Add per-component imports in zaakafhandelapp (72 `<script setup>` files)
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-composition-api-import`
- **files**: `zaakafhandelapp/src/**/*.vue` (files using `<script setup>`)
- **acceptance_criteria**:
  - GIVEN a `<script setup>` component WHEN t() is called THEN it has its own import
- [x] Add import to each `<script setup>` component that will have translated strings

### Task 4.3: Add per-component imports in softwarecatalog (25 `<script setup>` files)
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-composition-api-import`
- **files**: `softwarecatalog/src/**/*.vue` (files using `<script setup>` with `t()` calls)
- **acceptance_criteria**:
  - GIVEN a `<script setup>` component using `t()` WHEN loaded THEN it has its own import
- [x] Add import to each `<script setup>` component that uses `t()`

### Task 4.4: Add per-component imports in docudesk (8 `<script setup>` files)
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-composition-api-import`
- **files**: `docudesk/src/**/*.vue` (files using `<script setup>`)
- **acceptance_criteria**:
  - GIVEN a `<script setup>` component WHEN t() is called THEN it has its own import
- [x] Add import to each `<script setup>` component that uses `t()`

### Task 4.5: Verify per-component imports in openregister and opencatalogi
- **spec_ref**: `specs/i18n-infrastructure/spec.md#requirement-composition-api-import`
- **files**: `openregister/src/**/*.vue`, `opencatalogi/src/**/*.vue`
- **acceptance_criteria**:
  - GIVEN any `<script setup>` component using `t()` WHEN loaded THEN no ReferenceError occurs
- [x] Check if any `<script setup>` components exist that use `t()` without importing it
- [x] Add missing imports where needed

## 5. Frontend String Extraction (only apps with significant gaps)

### Task 5.1: Full string extraction for zaakafhandelapp (93 components, hardcoded Dutch)
- **spec_ref**: `specs/i18n-string-extraction/spec.md#requirement-wrap-user-facing-strings`, `specs/i18n-string-extraction/spec.md#requirement-hardcoded-dutch-conversion`
- **files**: `zaakafhandelapp/src/views/*.vue`, `zaakafhandelapp/src/components/*.vue`, `zaakafhandelapp/src/modals/*.vue`
- **acceptance_criteria**:
  - GIVEN zaakafhandelapp's components WHEN inspected THEN all hardcoded Dutch strings are converted to English t() keys
- [x] Audit all 93 components for hardcoded Dutch strings
- [x] Convert Dutch strings to English keys: `t('zaakafhandelapp', 'English key')`
- [x] Preserve original Dutch text for nl.json translation file
- [x] Handle dynamic strings with parameter substitution

### Task 5.2: Complete string wrapping for docudesk (mostly hardcoded)
- **spec_ref**: `specs/i18n-string-extraction/spec.md#requirement-wrap-user-facing-strings`
- **files**: `docudesk/src/**/*.vue`
- **acceptance_criteria**:
  - GIVEN docudesk's components WHEN inspected THEN all user-facing strings are wrapped with `t('docudesk', '...')`
- [x] Audit 15 components — only 31 t() calls found, many strings still hardcoded
- [x] Wrap remaining hardcoded strings

### Task 5.3: Audit remaining apps for any missed strings
- **spec_ref**: `specs/i18n-string-extraction/spec.md#requirement-wrap-user-facing-strings`
- **files**: All other apps' Vue files
- **acceptance_criteria**:
  - GIVEN each app WHEN Vue components are scanned THEN no user-facing hardcoded strings remain unwrapped
- [x] Quick audit of openregister (1,302 calls — likely complete, check for gaps)
- [x] Quick audit of opencatalogi (214 calls — check for mixed Dutch/English hardcoded strings)
- [x] Quick audit of openconnector (321 calls — verify coverage)
- [x] Quick audit of mydash (128 calls — verify coverage)
- [x] Quick audit of softwarecatalog (66 calls — may have gaps)
- [x] Quick audit of procest (274 calls — verify against l10n files)
- [x] Quick audit of pipelinq (362 calls — verify against l10n files)

## 6. Backend Translation — Inject IL10N and Wrap Messages

### Task 6.1: Add IL10N to openregister controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`, `specs/i18n-backend-messages/spec.md#requirement-translate-response-messages`
- **files**: `openregister/lib/Controller/*.php`
- **acceptance_criteria**:
  - GIVEN openregister controllers WHEN returning user-facing messages THEN they use `$this->l10n->t()`
- [x] Inject `IL10N $l10n` into controllers with hardcoded messages
- [x] Wrap response messages with `$this->l10n->t()`
- [x] Keep log messages and internal exceptions in English

### Task 6.2: Add IL10N to opencatalogi controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `opencatalogi/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages
- [x] Keep internal strings untranslated

### Task 6.3: Add IL10N to openconnector controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `openconnector/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages

### Task 6.4: Add IL10N to docudesk controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `docudesk/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages

### Task 6.5: Add IL10N to mydash controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `mydash/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages

### Task 6.6: Add IL10N to softwarecatalog controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `softwarecatalog/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages

### Task 6.7: Add IL10N to zaakafhandelapp controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `zaakafhandelapp/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages

### Task 6.8: Add IL10N to procest controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `procest/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages

### Task 6.9: Add IL10N to pipelinq controllers
- **spec_ref**: `specs/i18n-backend-messages/spec.md#requirement-il10n-injection`
- **files**: `pipelinq/lib/Controller/*.php`
- [x] Inject `IL10N` and wrap user-facing messages

## 7. Create Translation Files

### Task 7.1: Create l10n files for openregister (~1,302 frontend + backend strings)
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `openregister/l10n/en.json`, `openregister/l10n/nl.json`
- [x] Extract all t() keys from frontend code
- [x] Extract all $l->t() keys from backend code
- [x] Create l10n/en.json (identity mapping)
- [x] Create l10n/nl.json (Dutch translations using consistent terminology)

### Task 7.2: Create l10n files for opencatalogi (~214 strings)
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `opencatalogi/l10n/en.json`, `opencatalogi/l10n/nl.json`
- [x] Extract keys and create l10n files
- [x] Verify terminology consistency

### Task 7.3: Create l10n files for openconnector (~321 strings)
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `openconnector/l10n/en.json`, `openconnector/l10n/nl.json`
- [x] Extract keys and create l10n files
- [x] Verify terminology consistency

### Task 7.4: Create l10n files for docudesk (~31+ strings)
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `docudesk/l10n/en.json`, `docudesk/l10n/nl.json`
- [x] Extract keys and create l10n files

### Task 7.5: Create l10n files for mydash (~128 strings)
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `mydash/l10n/en.json`, `mydash/l10n/nl.json`
- [x] Extract keys and create l10n files

### Task 7.6: Create l10n files for softwarecatalog (~66 strings)
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `softwarecatalog/l10n/en.json`, `softwarecatalog/l10n/nl.json`
- [x] Extract keys and create l10n files

### Task 7.7: Create l10n files for zaakafhandelapp (new — all strings from extraction)
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `zaakafhandelapp/l10n/en.json`, `zaakafhandelapp/l10n/nl.json`
- [x] Create l10n/en.json from English keys introduced during extraction
- [x] Create l10n/nl.json using the original Dutch strings + consistent terminology

### Task 7.8: Verify and complete procest translations
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `procest/l10n/en.json`, `procest/l10n/nl.json`
- [x] Compare t() calls against existing l10n files
- [x] Add any missing backend translation keys

### Task 7.9: Verify and complete pipelinq translations
- **spec_ref**: `specs/i18n-dutch-translations/spec.md#requirement-complete-dutch-translations`
- **files**: `pipelinq/l10n/en.json`, `pipelinq/l10n/nl.json`
- [x] Compare t() calls against existing l10n files
- [x] Add any missing backend translation keys

## 8. Verification

- [x] 8.1 Visual audit: Load each app in browser (Dutch locale) and verify all visible text is translated
- [x] 8.2 Verify no ReferenceError for `t` or `n` in any app's browser console (especially `<script setup>` components)
- [x] 8.3 Verify fallback: Confirm English strings display correctly when no translation exists
- [x] 8.4 Cross-app terminology: Verify shared terms (Save, Cancel, Delete, etc.) are identical across all apps
- [x] 8.5 Backend verification: Call API endpoints and verify response messages are translated based on user locale
- [x] 8.6 Grep audit: Run `grep -r '<script setup>' | grep -v 'import.*@nextcloud/l10n'` to find Composition API components missing imports
