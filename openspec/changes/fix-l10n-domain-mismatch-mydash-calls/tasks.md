# Tasks — fix-l10n-domain-mismatch-mydash-calls

## Verify scope

- [ ] Task 1: Re-run `grep -rnoP "(?<![A-Za-z_])t\('mydash'" src --include=*.vue --include=*.js` and
  `grep -rnoP "(?<![A-Za-z_])n\('mydash'" src --include=*.vue --include=*.js`
  against current HEAD to confirm the exact call-site count and file list
  before editing (167 calls / 20 files at time of writing; do not trust a
  stale count if other work has landed in the meantime).

## Fix call sites

- [ ] Task 2: In each of the 20 affected files, change every
  `t('mydash', …)` (and `n('mydash', …)` if any exist) call to
  `t('launchpad', …)` / `n('launchpad', …)`. Do this as a literal string
  substitution only — do not alter the translated text, placeholders, or
  argument order.
  - `src/dialogs/RolePermissionDeleteDialog.vue` (4 calls)
  - `src/dialogs/PublicSharePasswordDialog.vue` (4 calls)
  - `src/components/admin/AdminDemoData.vue` (8 calls)
  - `src/views/Views.vue` (2 calls)
  - `src/views/DashboardPublicShareView.vue` (11 calls)
  - `src/dialogs/RoleLayoutDefaultEditorDialog.vue` (16 calls)
  - `src/dialogs/RoleLayoutDefaultDeleteDialog.vue` (4 calls)
  - `src/components/DashboardConfigModal.vue` (5 calls)
  - `src/components/admin/RoleLayoutDefaultsSection.vue` (9 calls)
  - `src/components/admin/BeheerTabs.vue` (1 call)
  - `src/components/admin/OrgNavigationEditorRow.vue` (2 calls)
  - `src/components/admin/PrometheusMetricsPanel.vue` (3 calls)
  - `src/components/admin/ConditionalVisibilityOverview.vue` (10 calls)
  - `src/components/admin/LegacyWidgetBridgeToggle.vue` (3 calls)
  - `src/components/admin/tabs/TemplatesPage.vue` (25 calls)
  - `src/components/admin/HealthPanel.vue` (4 calls)
  - `src/components/admin/DashboardSharingPolicy.vue` (8 calls)
  - `src/components/Widgets/VisibilityRulesModal.vue` (27 calls)
  - `src/components/admin/AdminSettings.vue` (12 calls)
  - `src/components/Widgets/WidgetContextMenu.vue` (1 call)
- [ ] Task 3: Confirm every English source string used in these 20 files
  already exists as a key in `l10n/en.json` under the `"launchpad"`
  domain content (spot-check a sample per file) — if any string is
  genuinely new/missing from the bundle, flag it separately rather than
  silently shipping an unresolvable key.

## Prevent regression

- [ ] Task 4: Add a lint guard (new script under `scripts/`, e.g.
  `scripts/lint-translation-domain.js`, wired into the existing
  `npm run lint` chain alongside `lint:initial-state`) that scans `src/`
  for `t('<literal>'` / `n('<literal>'` calls where `<literal>` is not
  `'launchpad'`, and fails with file:line output if any are found.
- [ ] Task 5: Add the new script to `package.json`'s `lint` script
  (`"lint": "eslint src && npm run check:manifest && npm run lint:initial-state && npm run lint:translation-domain"`).

## Verification

- [ ] Task 6: Run `npm run lint` and confirm the new guard passes with
  zero violations after Task 2's fixes.
- [ ] Task 7: Manually verify in a Dutch-locale (`nl`) Nextcloud session:
  open the admin Health panel (`HealthPanel.vue`) and the widget
  right-click context menu (`WidgetContextMenu.vue`) and confirm the
  previously-English "Health"/"Healthy"/"Degraded" and "Visibility
  rules…" strings now render in Dutch.
- [ ] Task 8: Re-run the Vitest suite (`npm run test`) — component tests
  that snapshot or assert on rendered English text for these components
  must still pass since the English fallback text is unchanged for the
  `en` locale; only non-English locales change behaviour.
