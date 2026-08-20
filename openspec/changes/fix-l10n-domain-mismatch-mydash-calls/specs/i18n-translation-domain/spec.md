---
capability: i18n-translation-domain
delta: true
status: draft
---

# I18N Translation Domain — Consistent `t()`/`n()` Domain Across the Frontend

## ADDED Requirements

### Requirement: REQ-I18N-001 All `t()`/`n()` calls MUST use the domain the shipped l10n bundles register under

Every call to `t(app, …)` or `n(app, …)` from `@nextcloud/l10n` in `src/**/*.vue` and `src/**/*.js` MUST pass the same `app` (translation domain) string that the app's shipped `l10n/<lang>.js` bundles register
under via `OC.L10N.register(app, …)`. A mismatched domain string causes
`translate()`/`translatePlural()` to silently fall back to the raw
(English) source text on every locale, with no error or warning.

#### Scenario: Health panel renders in the active locale

- **GIVEN** a Nextcloud session with locale `nl`
- **WHEN** the admin opens the LaunchPad Health panel
  (`HealthPanel.vue`)
- **THEN** the "Health" / "Checking…" / "Healthy" / "Degraded" strings
  MUST render in Dutch, matching the translations present in
  `l10n/nl.json`

#### Scenario: Widget context menu renders in the active locale

- **GIVEN** a Nextcloud session with locale `nl`
- **WHEN** a user opens the widget right-click context menu
  (`WidgetContextMenu.vue`)
- **THEN** the "Visibility rules…" entry MUST render in Dutch

#### Scenario: Lint guard catches a mismatched domain literal

- **GIVEN** a developer adds `t('mydash', 'New string')` to a `src/`
  file
- **WHEN** `npm run lint` runs
- **THEN** the translation-domain lint guard MUST fail with the
  offending file:line, before the change can be merged
