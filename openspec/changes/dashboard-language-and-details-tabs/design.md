# Design: dashboard-language-and-details-tabs

## Context (read at launchpad development 35e2b873)

- Translations: `listVariants` (`:109`), `listAvailableLanguages` (`:124`), `resolveForLocale` (`:154`, returns `{translation, isFallback}` or null), `createVariant` (`:268`, with `copyFromLanguage`), `updateVariant`, `deleteVariant`, `promoteVariantToPrimary` (`:424`). Routes under `/api/dashboards/{uuid}/translations`, plus `GET /api/dashboards/{uuid}/resolved` (`appinfo/routes.php:79-101`); an optional `?lang=` overrides the user locale.
- Metadata: `MetadataService` lists, creates, updates and deletes field definitions (`:86-249`), reads and writes a dashboard's values (`:277`, `:327`), and `filterDashboards()` (`:402`) filters by a `metadata.<key>` map. Routes `/api/admin/metadata-fields` and `/api/dashboards/{uuid}/metadata` (`:486-503`). `src/services/api.js:873-901` already has the field and value calls.
- `filterDashboards()` has no caller in `lib/`; the list endpoint does not filter by metadata today.
- `src/modals/DashboardConfigModal.vue` renders a tab strip (`general`, `sharing`, and one more) hidden in create mode; that is where two tabs go.

## Decisions

### D1. Two tabs in the existing dialog

`LanguagesTab.vue` and `DetailsTab.vue` live under `src/components/Workspace/config/` and are added to the strip in `DashboardConfigModal.vue`. Both are hidden in create mode like the existing tabs, because a dashboard needs a uuid first.

### D2. The resolver decides, the page follows

`Views.vue` calls `GET /api/dashboards/{uuid}/resolved` when the dashboard has more than one variant (the dashboard payload already says whether variants exist; if it does not, task 1 adds a `hasVariants` flag rather than an extra request per page). `isFallback` true shows a small "Shown in the primary language" note. A dashboard without variants behaves exactly as today.

### D3. Metadata fields are edited in administration, values on the dashboard

The field editor is a new section in the administration settings (`src/components/admin/`), following `DashboardRegistrySettings.vue`. Select and multi-select fields carry options; the service already rejects a select field without options (`:791`). The Details tab renders one control per field, with `NcSelect` inputs carrying an input label.

### D4. Wire the filter, do not rewrite it

The dashboards list endpoint reads `metadata.<key>` query parameters and passes them to `MetadataService::filterDashboards()`. The switcher gets one "Filter by detail" control that appears only when at least one field is filterable. This removes the uncalled-method finding.

## Declarative-vs-imperative decision

Metadata field definitions are administrator data stored by the service, not app configuration shipped in a manifest.

