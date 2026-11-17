---
kind: code
---

# Fix `t('mydash', …)` translation-domain mismatch — 167 calls never translate on any non-English locale

## Why

LaunchPad's shipped translation bundles register under the JS global
translation-registry key **`"launchpad"`**, not `"mydash"` — verified
directly in the committed build artifact:

```
$ head -c 120 l10n/nl.js
OC.L10N.register(
    "launchpad",
    {
    "share_not_found" : "Gedeeld dashboard niet gevonden",
    ...
```

Every `l10n/<lang>.js` file in this repo registers under `"launchpad"`
(confirmed for `nl.js`; the same generator produced all 30+ locale files).
`@nextcloud/l10n`'s `translate(app, text)` looks the string up via
`getAppTranslations(app)`, which is a **strict key match** against
`globalThis._oc_l10n_registry_translations[app]`
(`node_modules/@nextcloud/l10n/dist/chunks/translation-DoG5ZELJ.mjs`,
`getAppTranslations()` / `translate()`) — there is no fallback, alias, or
normalization between `app` values. If `app` was never registered under
that exact string, `translate()` returns the raw (English) source text
unconditionally (`bundle.translations[text] || text`), regardless of the
active locale.

The app's own `info.xml` declares `<id>mydash</id>` (line 8) — the actual
Nextcloud app id, still used for routes (`mydash.page.index`, line 134)
and DB tables (`oc_mydash_dashboards`) for backward compatibility with the
app's pre-rebrand history. Because of this, **167 `t('mydash', …)` calls
across 20 frontend files** ask the registry for a bundle keyed `"mydash"`
that is never registered (only `"launchpad"` is) — for example:

- `src/components/admin/HealthPanel.vue:8,13,19,25` — the admin Health
  panel's "Health", "Checking…", "Healthy", "Degraded" badge text.
- `src/components/Widgets/WidgetContextMenu.vue:27` — the widget
  right-click menu's "Visibility rules…" entry.
- `src/components/admin/AdminSettings.vue` (12 calls), `src/views/Views.vue`
  (2 calls), `src/dialogs/RoleLayoutDefaultEditorDialog.vue` (16 calls),
  `src/components/Widgets/VisibilityRulesModal.vue` (27 calls), and 15
  other files.

These 167 strings render in **English on every locale**, including Dutch,
even though the corresponding source text is present (word-for-word
identical to text elsewhere in the same files that correctly uses
`t('launchpad', …)`) in the shipped `l10n/nl.json`/`l10n/nl.js` bundles —
the translations exist, they are simply unreachable under the domain key
these 167 call sites request. This directly contradicts the fleet's NL
Design System / WCAG-adjacent i18n positioning: a Dutch-locale
municipality user sees a silently untranslated mix of Dutch and English
strings within the *same* admin panel or context menu.

## What Changes

- Change all 167 `t('mydash', …)` call sites (and confirm there are no
  `n('mydash', …)` plural calls — verified zero) across the 20 affected
  files to `t('launchpad', …)`, matching the domain the shipped
  `l10n/<lang>.js`/`.json` bundles actually register under. This is a
  drop-in string replacement — the translation *content* keyed by the
  English source text is already correct and present in every locale
  bundle; only the domain argument is wrong.
- Affected files: `src/dialogs/RolePermissionDeleteDialog.vue`,
  `src/dialogs/PublicSharePasswordDialog.vue`,
  `src/components/admin/AdminDemoData.vue`, `src/views/Views.vue`,
  `src/views/DashboardPublicShareView.vue`,
  `src/dialogs/RoleLayoutDefaultEditorDialog.vue`,
  `src/dialogs/RoleLayoutDefaultDeleteDialog.vue`,
  `src/components/DashboardConfigModal.vue`,
  `src/components/admin/RoleLayoutDefaultsSection.vue`,
  `src/components/admin/BeheerTabs.vue`,
  `src/components/admin/OrgNavigationEditorRow.vue`,
  `src/components/admin/PrometheusMetricsPanel.vue`,
  `src/components/admin/ConditionalVisibilityOverview.vue`,
  `src/components/admin/LegacyWidgetBridgeToggle.vue`,
  `src/components/admin/tabs/TemplatesPage.vue`,
  `src/components/admin/HealthPanel.vue`,
  `src/components/admin/DashboardSharingPolicy.vue`,
  `src/components/Widgets/VisibilityRulesModal.vue`,
  `src/components/admin/AdminSettings.vue`,
  `src/components/Widgets/WidgetContextMenu.vue`.
- Do **not** touch the l10n bundle generation/domain (`"launchpad"`) —
  1052 existing call sites already correctly use `t('launchpad', …)` and
  work today; aligning the minority (167) to match is the smaller, safer
  fix versus re-registering bundles under a second domain.
- Add an ESLint rule or a `scripts/lint-initial-state.js`-style guard
  script (wired into `npm run lint`) that fails the build if any
  `t(`/`n(` call in `src/` uses a translation-domain string literal other
  than `'launchpad'`, to prevent regression.
- **BREAKING**: none — pure bugfix, output text is unchanged for
  English-locale users; Dutch (and all other non-English locale) users
  will see previously-broken strings translate correctly for the first
  time.

## Capabilities

### Added Capabilities

- `i18n-translation-domain`: establishes the fleet convention that all
  `t()`/`n()` calls in LaunchPad's frontend MUST use the same
  translation-domain string that the shipped `l10n/<lang>.js` bundles
  register under, and that this MUST be mechanically enforced.

## Impact

**Affected code:** 20 Vue/JS files (listed above), 167 call sites total.

**Affected APIs:** none — this is a pure frontend string-domain fix; no
route, controller, or DB schema changes.

**Dependencies:** none.
