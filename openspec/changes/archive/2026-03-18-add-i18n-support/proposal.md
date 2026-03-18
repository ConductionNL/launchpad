# Proposal: add-i18n-support

## Summary
Add full internationalization (i18n) support to all Conduction Nextcloud apps using Nextcloud's built-in translation system — both frontend (`@nextcloud/l10n`) and backend (`IL10N` / `$l->t()`). Most apps already wrap their frontend strings with `t()` but lack translation files. Several apps have broken imports or use Composition API without direct imports. The PHP backend has hardcoded English error/success messages in controllers across all apps. This change creates translation files, fixes broken setups, handles Composition API components, and translates backend messages — making all apps fully multilingual starting with Dutch (nl) and English (en).

## Motivation
Conduction apps target Dutch government organizations and international users. Investigation reveals:
- **Frontend**: Most apps already call `t()` extensively (openregister: 1,302 strings, openconnector: 321, pipelinq: 362) but have no `l10n/` translation files — so the strings just fall back to English
- **Backend**: PHP controllers return hardcoded English messages (`"Failed to retrieve statistics"`, `"Contract deleted successfully"`) with no `$l->t()` wrapping
- **Broken setups**: openconnector, docudesk, softwarecatalog have `Vue.mixin({ t, n })` but missing the import statement
- **Composition API**: openconnector (86/108), zaakafhandelapp (72/93), softwarecatalog (25/54) use `<script setup>` where `Vue.mixin` doesn't inject methods — these need per-component imports
- **zaakafhandelapp**: 93 components with hardcoded Dutch strings and nearly zero `t()` usage — the largest effort

## Affected Projects
- [ ] Project: `openregister` — 146 components, 1,302 t() calls already present. **Needs**: l10n files, fix main.js import
- [ ] Project: `opencatalogi` — 81 components, 214 t() calls already present. **Needs**: l10n files (import + mixin already working)
- [ ] Project: `openconnector` — 108 components (86 Composition API), 321 t() calls. **Needs**: fix missing import, add per-component imports for `<script setup>` files, l10n files
- [ ] Project: `docudesk` — 15 components (8 Composition API), 31 t() calls (mostly hardcoded). **Needs**: fix missing import, wrap remaining strings, add per-component imports, l10n files
- [ ] Project: `nldesign` — No changes (CSS/design library, no translatable UI)
- [ ] Project: `mydash` — 12 components, 128 t() calls. **Needs**: l10n files (import + mixin already working)
- [ ] Project: `softwarecatalog` — 54 components (25 Composition API), 66 t() calls. **Needs**: fix missing import, add per-component imports, l10n files
- [ ] Project: `larpingapp` — Already has proper i18n (78 keys, en+nl) — use as reference implementation
- [ ] Project: `zaakafhandelapp` — 93 components (72 Composition API), ~2 t() calls. **Needs**: full string extraction from hardcoded Dutch, per-component imports, l10n files (LARGEST EFFORT)
- [ ] Project: `procest` — 34 components, 274 t() calls, has l10n files. **Needs**: verify completeness, fix import if missing
- [ ] Project: `pipelinq` — 43 components, 362 t() calls, has l10n files. **Needs**: verify completeness, fix import if missing
- [ ] Project: ALL apps (backend) — PHP controllers have hardcoded English error/success messages. **Needs**: inject `IL10N`, wrap messages with `$l->t()`

## Scope
### In Scope
- **Frontend**: Fix broken `@nextcloud/l10n` imports in main.js where missing
- **Frontend**: Add per-component `import { translate as t, translatePlural as n } from '@nextcloud/l10n'` for all `<script setup>` (Composition API) components
- **Frontend**: Wrap remaining hardcoded strings with `t()` (mainly zaakafhandelapp and docudesk)
- **Frontend**: Create `l10n/en.json` and `l10n/nl.json` translation files for all apps
- **Backend**: Inject Nextcloud's `IL10N` service into PHP controllers/services
- **Backend**: Wrap hardcoded error/success messages in controllers with `$l->t()`
- **Backend**: Add backend translation keys to the same `l10n/` JSON files

### Out of Scope
- Transifex or other translation platform integration (future enhancement)
- RTL language support
- Languages beyond English and Dutch (can be added later by translators)
- Replacing native Nextcloud components (they handle their own translations)
- Translating data content stored in the database

## Approach
1. **Fix infrastructure** — Fix broken imports in main.js, add Composition API per-component imports
2. **Frontend string extraction** — Only needed for zaakafhandelapp (hardcoded Dutch) and docudesk (mostly hardcoded). Other apps already use `t()` extensively.
3. **Backend translation** — Inject `IL10N` into controllers, wrap hardcoded response messages with `$l->t()`
4. **Create translation files** — Generate `l10n/en.json` and `l10n/nl.json` per app covering both frontend and backend strings
5. **Verify** — Visual audit per app in Dutch locale

## Cross-Project Dependencies
- All apps depend on `@nextcloud/l10n` package (already available via Nextcloud's npm ecosystem)
- Backend depends on Nextcloud's `OCP\IL10N` interface (already available in all apps via DI)
- `larpingapp` serves as the frontend reference implementation
- `nldesign` is excluded (CSS-only, no translatable strings)

## Rollback Strategy
Each app's i18n changes are independent — rollback per-app by reverting the commit. Frontend: `t('app', 'string')` falls back to the original string if no translation file exists. Backend: `$l->t('string')` falls back to the source string. Removing the `l10n/` directory simply removes translations without breaking functionality.

## Capabilities

### New Capabilities
- `i18n-infrastructure` — Standard frontend i18n setup pattern: main.js import + mixin, Composition API per-component imports
- `i18n-string-extraction` — Rules for wrapping translatable strings with `t()` / `n()` in Vue templates and `$l->t()` in PHP
- `i18n-dutch-translations` — Dutch (nl) translations for all user-facing strings across all apps
- `i18n-backend-messages` — Backend PHP controller/service message translation using Nextcloud's `IL10N`

### Modified Capabilities
None — this is new functionality with no existing spec-level behavior changes.

## Open Questions
- Should we prioritize certain apps over others for the initial rollout, or do all apps simultaneously?
- Are there any app-specific terms that need consistent translation across all apps (glossary)?
