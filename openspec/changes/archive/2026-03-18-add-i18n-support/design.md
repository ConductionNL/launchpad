# Design: add-i18n-support

## Architecture Overview

This change adopts Nextcloud's existing i18n system consistently across all Conduction apps — both frontend (`@nextcloud/l10n`) and backend (`OCP\IL10N`).

Each app follows the same structure:
```
app/
  l10n/
    en.json              # English translations (frontend + backend, source of truth)
    nl.json              # Dutch translations
  src/
    main.js              # Import + Vue.mixin registration (Options API)
    views/*.vue          # t('appid', 'string') — direct import for <script setup>
    components/*.vue     # t('appid', 'string') — direct import for <script setup>
  lib/
    Controller/*.php     # $this->l10n->t('string') for response messages
    Service/*.php        # $this->l10n->t('string') for error messages
```

### Frontend Translation Flow
1. `@nextcloud/l10n` provides `translate` (as `t`) and `translatePlural` (as `n`)
2. **Options API components**: Available via `Vue.mixin({ methods: { t, n } })` in main.js
3. **Composition API (`<script setup>`) components**: MUST import directly — `Vue.mixin` does NOT inject into `<script setup>`
4. In templates: `{{ t('appid', 'Readable string') }}`
5. Nextcloud loads the appropriate `l10n/<locale>.json` based on user language preference
6. If no translation exists, the original string is returned as-is (safe fallback)

### Backend Translation Flow
1. Nextcloud's `OCP\IL10N` interface provides `->t('string')` and `->n('singular', 'plural', count)`
2. Inject via constructor: `public function __construct(IL10N $l10n)`
3. Use in controller responses: `new JSONResponse(['message' => $this->l10n->t('Successfully deleted')])`
4. Nextcloud resolves the user's locale and returns the correct translation from `l10n/<locale>.json`

## API Design

No API endpoint changes. Backend responses that currently return hardcoded English messages will return locale-aware translated messages via `$l->t()`. The API contract (status codes, JSON structure) remains identical.

Example before/after:
```php
// Before:
return new JSONResponse(['message' => 'Contract deleted successfully'], 200);

// After:
return new JSONResponse(['message' => $this->l10n->t('Contract deleted successfully')], 200);
```

## Database Changes

None.

## Nextcloud Integration

### Frontend
- **@nextcloud/l10n**: Core dependency, provides `translate()` and `translatePlural()`
- **Nextcloud locale detection**: Automatically reads user language preference from Nextcloud settings
- **l10n JSON loading**: Nextcloud server injects the correct translation file via `OC.L10N`

### Backend
- **OCP\IL10N**: Nextcloud's localization interface, injected via DI
- **IFactory**: Can be used if locale needs to be resolved outside of request context
- Nextcloud reads the same `l10n/<locale>.json` files for both frontend and backend

## File Structure

Per-app changes (repeated for each of the 10 UI apps):
```
{app}/
  l10n/
    en.json                    # NEW — all translatable strings (frontend + backend)
    nl.json                    # NEW — Dutch translations
  src/
    main.js                    # FIX — ensure import + Vue.mixin present
    views/*.vue                # FIX — add import for <script setup> components
    components/*.vue           # FIX — add import for <script setup> components
    modals/*.vue               # FIX — add import for <script setup> components
  lib/
    Controller/*.php           # MODIFY — inject IL10N, wrap messages with $l->t()
    Service/*.php              # MODIFY — inject IL10N where error messages are returned to users
```

## App Categories (Revised Based on Investigation)

### Category A: Frontend t() already used, needs l10n files + backend
**openregister** (146 components, 1,302 t() calls), **opencatalogi** (81 components, 214 calls), **mydash** (12 components, 128 calls)
- Import and mixin already working
- Create `l10n/en.json` and `l10n/nl.json` from existing t() calls
- Add backend `$l->t()` wrapping in controllers

### Category B: Broken frontend import + needs l10n files + backend
**openconnector** (108 components, 86 use `<script setup>`), **softwarecatalog** (54 components, 25 use `<script setup>`), **docudesk** (15 components, 8 use `<script setup>`)
- Fix missing `import { translate as t, translatePlural as n } from '@nextcloud/l10n'` in main.js
- Add per-component import for `<script setup>` files
- docudesk also needs more strings wrapped (only 31 t() calls for 15 components)
- Create l10n files, add backend `$l->t()`

### Category C: Partial — has l10n files, verify completeness
**procest** (34 components, 274 calls, has l10n/), **pipelinq** (43 components, 362 calls, has l10n/)
- Verify all strings covered in existing l10n files
- Fix import if missing
- Add backend `$l->t()`

### Category D: Full extraction needed
**zaakafhandelapp** (93 components, 72 use `<script setup>`, ~2 t() calls)
- All UI strings hardcoded in Dutch — needs full extraction
- Add per-component import for every `<script setup>` file
- Convert Dutch hardcoded strings to English `t()` keys
- Create l10n files with Dutch translations
- Add backend `$l->t()`

### Reference (no changes needed)
**larpingapp** — Already fully implemented
**nldesign** — CSS-only library

## Decisions

### Decision 1: Use `@nextcloud/l10n` and `OCP\IL10N` directly
**Rationale**: Standard Nextcloud approach. Both frontend and backend use the same `l10n/<locale>.json` files.
**Alternative considered**: Custom wrapper — rejected, adds complexity.

### Decision 2: Per-component import for Composition API
**Rationale**: `Vue.mixin()` does NOT inject methods into `<script setup>` components. Each Composition API component MUST import `t` and `n` directly.
**Alternative considered**: Global provide/inject — rejected because `@nextcloud/l10n` is already a simple import.

### Decision 3: English as source language, Dutch as first translation
**Rationale**: English-first is the Nextcloud convention. zaakafhandelapp's hardcoded Dutch strings will be converted to English keys with Dutch in l10n/nl.json.

### Decision 4: Per-app translation files (not shared)
**Rationale**: Nextcloud's l10n system is app-scoped. Both `t('appid', ...)` and `$l->t(...)` resolve from the app's own `l10n/` directory.

### Decision 5: Backend messages translated via IL10N injection
**Rationale**: Nextcloud's standard DI pattern. Controllers already use constructor injection for other services. Adding IL10N follows the same pattern.
**Alternative considered**: Translating only in the frontend — rejected because API responses should be locale-aware for non-browser clients.

## Security Considerations

- Translation strings MUST NOT include user-generated content without sanitization
- Use Vue's built-in template escaping (default behavior with `{{ }}`)
- Backend: `$l->t()` returns escaped strings by default
- Translation files are static JSON served by the server — no injection risk

## NL Design System

No NL Design System changes. Nextcloud components (`NcButton`, `NcDialog`, etc.) already handle their own internal translations. Our translated strings render inside these components via props/slots.

## Trade-offs

| Decision | Benefit | Cost |
|----------|---------|------|
| Per-component import for `<script setup>` | Correct behavior, explicit dependencies | Extra import line in ~195 files |
| Backend `$l->t()` | API responses locale-aware | Must inject IL10N in all controllers |
| English source for zaakafhandelapp | Consistent with other apps, ecosystem standard | Must translate all existing Dutch strings to English keys |
| All apps in one change | Consistent UX across suite | Large change set |

## Risks

- **Risk**: Missed strings — some hardcoded strings may be overlooked
  - **Mitigation**: Visual audit per app in Dutch locale; missed strings can be added incrementally
- **Risk**: zaakafhandelapp Dutch→English key conversion introduces errors
  - **Mitigation**: Preserve original Dutch strings in nl.json; visual comparison before/after
- **Risk**: Composition API components silently fail without per-component import
  - **Mitigation**: Grep for `<script setup>` files that use `t(` without importing it
- **Risk**: Backend IL10N injection breaks constructor signatures
  - **Mitigation**: Follow existing DI patterns; Nextcloud auto-resolves IL10N
