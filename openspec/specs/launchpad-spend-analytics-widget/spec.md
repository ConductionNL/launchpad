---
status: done
---

# Spec: launchpad-spend-analytics-widget

**Status:** proposed
**Scope:** launchpad
**Tier:** widget-capabilities
**Depends on:** widgets, widget-add-edit-modal, widget-collision-placement, runtime-shell, permissions, conditional-visibility, responsive-grid-breakpoints; cross-app runtime sources: financeq (GraphQL), procest (GraphQL), docudesk (GraphQL), openconnector (LLM source)

## Purpose

Surface procurement + financial spend analytics on a launchpad dashboard
as a single widget (`launchpad_spend_analytics`). The widget consumes
data live at render time via runtime GraphQL queries against
financeq + procest (and, for evidence attachment, docudesk through
OR's `object-interactions` integration registry per ADR-022). The
widget MUST NOT add an install-time dependency on any sibling app —
per `feedback_launchpad-no-or-dependency.md`, launchpad stays the
always-available shell.

Consolidates 8 Specter context briefs (`spend-analytics-reporting`,
`-ai`, `-analytics-t1`, `-analytics-t2`, `-document-management`,
`-other-t1`, `-other-t2`, `-other-t3`) — all budget-bucket fragments
of the same procurement-spend surface — into one coherent widget
capability. The misleading `— Shillinq` suffix on the source briefs
came from the budgetq/shillinq cluster origin and is dropped; this
is a launchpad widget that reads sibling-app data, not a shillinq
feature.

## Requirements

### Requirement: REQ-SAW-001: The system SHALL register a `launchpad_spend_analytics` widget type discoverable via the unified Add Widget modal

The widget MUST appear in `src/constants/widgetRegistry.js` (per the
widgets capability REQ-WDG-014 single-source-of-truth rule) and MUST
be selectable from the Add Widget modal type picker (REQ-WDG-010).
The registry entry MUST carry `displayName`, `defaultContent`,
`renderer`, `form`, `icon`, and a soft `requires` declaration naming
the runtime GraphQL endpoints it consumes — never an entry in
`manifest.dependencies`.

#### Scenario: Widget type listed in registry completeness test

@e2e exclude unit-only: this scenario is a vitest, not a browser behaviour. src/constants/__tests__/widgetRegistry.completeness.spec.js pins `spend-analytics` in EXPECTED_TYPES (read from lib/widget-types.json) with non-null form, renderer, displayName, defaultContent and icon, and widgetRegistry.spec.js pins the LaunchPad form override. The `requires` clause differs from the text: the communal entry (nc-vue CnSpendAnalyticsWidget/index.js) declares `{graphql: ['financeq', 'procest']}`, not `financeq.transactions` / `procest.cases`, and no test asserts it.

- **GIVEN** the registry completeness test (REQ-WDG-023 EXPECTED_TYPES)
- **WHEN** the test runs
- **THEN** `spend-analytics` MUST appear in EXPECTED_TYPES
- **AND** the registry entry MUST carry non-null `form`, `renderer`,
  `displayName`, `defaultContent`, and `icon` fields
- **AND** the entry's `requires` MUST list at minimum
  `{graphql: ['financeq.transactions', 'procest.cases']}`

#### Scenario: Widget appears in unified Add modal

- **GIVEN** the unified Add Widget modal is open with no preselected type
- **WHEN** the user opens the type picker
- **THEN** the picker MUST list `Spend analytics` as a selectable
  type
- **AND** selecting it MUST mount the `SpendAnalyticsForm` sub-form

### Requirement: REQ-SAW-002: The widget SHALL declare a `launchpad_spend_analytics` entry in `src/manifest.json` with a soft `requires` clause

The manifest entry MUST live in `widgets[]` (per ADR-024 + the launchpad
widget convention). It MUST set `requires.graphql` listing the
sibling-app GraphQL schemas it queries. It MUST NOT add
`shillinq`, `financeq`, `procest`, `docudesk`, or `openconnector`
to top-level `manifest.dependencies` — runtime checks gate
behaviour, not install.

#### Scenario: Manifest entry present and soft-required

@e2e exclude built and removed: 7d7da1bb added a `launchpad_spend_analytics` entry to the v1 top-level `widgets[]` of src/manifest.json, and 2cf53831 deleted that block in the v2 manifest migration (the widget is now registered in the JS registry only). There is no manifest entry to assert, in e2e or anywhere else; the scenario text is out of date.

- **GIVEN** the launchpad manifest at `src/manifest.json`
- **WHEN** parsed
- **THEN** `manifest.widgets[].find(w => w.id === 'launchpad_spend_analytics')` MUST exist
- **AND** that entry's `requires.graphql` MUST be a non-empty array
- **AND** `manifest.dependencies` MUST NOT contain any of
  `financeq`, `procest`, `docudesk`, `shillinq`, `openconnector`

#### Scenario: Manifest validation passes

@e2e exclude CLI, not a browser behaviour: `npm run check:manifest` (scripts/check-manifest.js) runs in `npm run lint`. Its premise is gone too: 2cf53831 removed the spend-analytics manifest entry, so the check validates a manifest without one.

- **GIVEN** `npm run check:manifest` is wired per ADR-024 §5
- **WHEN** the script runs
- **THEN** the manifest MUST pass schema validation with the
  spend-analytics widget entry present

### Requirement: REQ-SAW-003: The widget content shape SHALL be the persisted JSON contract for placements

The placement MUST persist `{type: 'spend-analytics', content: {...}}` in `oc_launchpad_widget_placements.content` (per the widgets capability
REQ-WDG-010 emit shape). The `content` object MUST carry:

| Field | Type | Required | Default | Purpose |
|---|---|---|---|---|
| `viewMode` | enum | Yes | `'summary'` | `'summary' \| 'trend' \| 'top-vendors' \| 'top-categories'` |
| `period` | enum | Yes | `'quarter'` | `'month' \| 'quarter' \| 'ytd' \| 'fy'` |
| `filters.categoryIds` | string[] | No | `[]` | CPV / category filter |
| `filters.departmentIds` | string[] | No | `[]` | Cost-centre / department filter |
| `filters.vendorIds` | string[] | No | `[]` | Supplier / vendor filter |
| `drillThroughTarget` | enum | No | `'detail-page'` | `'detail-page' \| 'sibling-app'` |
| `attachEvidence` | boolean | No | `true` | Surface docudesk-attached evidence per OR object-interactions |
| `aiInsights.enabled` | boolean | No | `false` | Whether to surface LLM-generated narrative (REQ-SAW-006) |

#### Scenario: Minimal placement validates

- **GIVEN** the content shape contract
- **WHEN** a placement is saved with `{type: 'spend-analytics', content: {viewMode: 'summary', period: 'quarter'}}`
- **THEN** validation MUST pass
- **AND** defaults for unset fields MUST be applied by the renderer

#### Scenario: Invalid viewMode rejected

@e2e exclude unreachable from the UI and pinned by no test: CnSpendAnalyticsWidgetForm offers only the four valid view modes in its select and its data() coerces a stored out-of-enum `viewMode` to `summary`, so no person can put `forecast` into the form for validate() to reject. No vitest covers validate() since the launchpad form spec went away with b67cd6a9 (the renderer and form moved to nc-vue, whose tree has no spend test).

- **GIVEN** the form attempts to save `content.viewMode = 'forecast'`
- **WHEN** `validate()` runs
- **THEN** validation MUST return a non-empty error array
- **AND** the modal MUST keep the Add/Save button disabled

### Requirement: REQ-SAW-004: The widget SHALL fetch spend data via runtime GraphQL against financeq + procest

The renderer MUST issue GraphQL operations to the sibling apps'
OR-mounted `/graphql` endpoints via the shared client from
`@conduction/nextcloud-vue` (per `feedback_shared-deps.md` +
`feedback_vue-logic-in-ncvue.md`). Queries MUST be read-only. The
renderer MUST NOT cache responses across component unmounts —
freshness is the sibling apps' responsibility. The renderer MUST
NOT call OR endpoints directly via `axios` from launchpad code.

#### Scenario: Renderer issues GraphQL to financeq

- **GIVEN** a placement with `{viewMode: 'summary', period: 'quarter'}`
- **WHEN** the widget mounts
- **THEN** the renderer MUST issue a GraphQL query to financeq's
  `/graphql` endpoint covering at minimum the fields:
  `transactions { totalAmount currency }`
- **AND** the query MUST include the period filter as a variable

#### Scenario: Renderer issues GraphQL to procest for cases

- **GIVEN** a placement with `{viewMode: 'top-vendors'}`
- **WHEN** the widget mounts
- **THEN** a parallel GraphQL query MUST be issued to procest's
  `/graphql` endpoint resolving `cases { vendor totalCommitted }`
- **AND** both queries MUST run via the shared
  `@conduction/nextcloud-vue` GraphQL client

#### Scenario: No direct axios to sibling apps

@e2e exclude a source scan, not a browser behaviour, and no test performs it. The only launchpad code that calls a sibling app is queryGraphql in src/services/graphqlClient.js (axios POST to `/apps/{app}/graphql`), pinned by src/services/__tests__/graphqlClient.spec.js and queryGraphql.golden.spec.js; spendAnalytics.spec.js pins that financeq and procest go through it.

- **GIVEN** the spend-analytics renderer source files
- **WHEN** scanned for `axios` / `fetch(` calls targeting
  `/index.php/apps/financeq/`, `/apps/procest/`, `/apps/shillinq/`,
  `/apps/docudesk/`
- **THEN** zero matches MUST exist — every cross-app call MUST
  route through the shared GraphQL client

### Requirement: REQ-SAW-005: The widget SHALL render an empty-state without throwing when a required sibling app is absent

When any required GraphQL endpoint returns 404 (sibling app not installed) or an empty result, the widget MUST render an empty-state
panel naming the missing source and a hint for the admin. The
widget MUST NOT show a 500 / red error, MUST NOT block the rest of
the dashboard, and MUST NOT prompt the user to install anything
client-side (install is an admin action).

#### Scenario: financeq absent renders empty-state

- **GIVEN** financeq is not installed on the Nextcloud instance
- **WHEN** the spend-analytics widget mounts
- **THEN** the GraphQL call MUST return 404
- **AND** the widget MUST render the empty-state with text
  including `t('launchpad', 'No spend data — financeq is not installed')`
- **AND** the surrounding GridStack grid MUST remain interactive

#### Scenario: financeq present but no data in window

- **GIVEN** financeq is installed and returns `{transactions: {edges: []}}`
- **WHEN** the widget renders
- **THEN** the empty-state MUST display
  `t('launchpad', 'No spend recorded for the selected period')`
- **AND** the period selector MUST remain operable so the viewer
  can widen the window

#### Scenario: Partial sibling availability

- **GIVEN** financeq returns data but procest is absent
- **WHEN** the `top-vendors` view renders
- **THEN** the financeq-derived summary cards MUST still render
- **AND** the vendor breakdown panel MUST render its own empty-state
  inline without disabling the rest of the widget

### Requirement: REQ-SAW-006: The widget SHALL surface AI-generated narrative via the openconnector LLM source — never a direct LLM client in launchpad

When `content.aiInsights.enabled === true`, the widget MUST issue an
inference call through the openconnector LLM source registered as
`local-llm` (per `reference_llphant-ollama-think-false` — Ollama +
Qwen, `think: false`, `keep_alive: -1`). The widget MUST NOT import
`@ollama/ollama` or any LLM SDK directly. The widget MUST stream
the reply into the panel using the established Conduction SSE
pattern from `project_ai-chat-companion-end-to-end` (ADR-034 chain).

#### Scenario: Narrative request routes through openconnector

- **GIVEN** `aiInsights.enabled === true` and the viewer requests an insight
- **WHEN** the widget issues the inference call
- **THEN** the request MUST target openconnector's source endpoint,
  NOT `localhost:11434` or any other Ollama / LLM URL directly
- **AND** the request body MUST include the spend summary as
  context payload prepared by the widget

#### Scenario: openconnector absent disables AI panel gracefully

@e2e exclude never built: the toggle has no tooltip and knows nothing of the source. CnSpendAnalyticsWidgetForm renders a plain checkbox; the renderer only learns the source is missing after the viewer clicks the panel's Generate button and the call has been sent. Neither 7d7da1bb nor the nc-vue form ever checked the `local-llm` source before the toggle (`git log --all -S'local-llm' -- src` shows only the data-layer commits). The data-layer half (a 404 maps to `available: false`) is pinned by src/services/__tests__/spendAnalytics.spec.js.

- **GIVEN** openconnector is not installed or the `local-llm` source
  is not configured
- **WHEN** the user toggles `aiInsights.enabled`
- **THEN** the toggle MUST present a tooltip explaining the missing
  source
- **AND** no inference request MUST be issued
- **AND** the rest of the widget MUST continue to render data
  cards

#### Scenario: No LLM SDK import

@e2e exclude a source scan, not a browser behaviour: src/components/Widgets/Renderers/__tests__/noModelClient.spec.js fails on any import of openai, @anthropic-ai, @ollama or llphant, or a localhost:11434 string, anywhere in launchpad src/. The renderer itself now lives in nc-vue, which that scan does not read.

- **GIVEN** the spend-analytics widget source files
- **WHEN** scanned for `import ... from '@ollama/'`,
  `from 'openai'`, `from '@anthropic-ai/'`, or `LLPhant`
- **THEN** zero matches MUST exist

### Requirement: REQ-SAW-007: Drill-through SHALL navigate to the sibling app's detail surface — launchpad MUST NOT mirror sibling-app pages

Clicking a row in the widget (transaction, vendor, case) MUST
navigate via deep-link to the owning sibling app's detail surface
(per OR's deep-link registry — ADR-022 table row "Deep link
registry"). launchpad MUST NOT render a local detail page for sibling
data.

#### Scenario: Vendor row click deep-links to procest

- **GIVEN** the `top-vendors` view shows vendor `acme`
- **WHEN** the user clicks the row
- **THEN** the browser MUST navigate to procest's vendor detail URL
  (resolved via OR deep-link registry)
- **AND** launchpad MUST NOT render a local `/spend/vendor/acme` page

#### Scenario: Transaction row click deep-links to financeq

@e2e exclude never built: the summary view has no transaction list and no transaction rows; CnSpendAnalyticsWidget links only category rows (financeq) and vendor rows (procest). No commit ever rendered transaction rows: the original renderer in 7d7da1bb drilled only into `category` and `vendor`.

- **GIVEN** the `summary` view's drill-through opens a transaction
  list
- **WHEN** the user clicks a transaction row
- **THEN** the navigation target MUST be financeq's transaction
  detail URL

#### Scenario: drillThroughTarget=detail-page renders launchpad overlay

@e2e exclude never built: `drillThroughTarget` is read only by the form; CnSpendAnalyticsWidget never reads it and always renders rows as plain links, and the 7d7da1bb renderer did the same. `git log --all -S'drillThroughTarget' -- src lib` finds only 7d7da1bb, fda6bd06 and 8e0488cf, none with an overlay. No unit test either.

- **GIVEN** `content.drillThroughTarget === 'detail-page'` (the
  default — for users who prefer staying in launchpad)
- **WHEN** the user clicks a row
- **THEN** the widget MUST surface the sibling app's detail
  payload via an OR object-interactions panel inside the widget
  cell — still served by the sibling app's GraphQL, not a launchpad
  copy

### Requirement: REQ-SAW-008: Evidence attachments SHALL be consumed via OR's `object-interactions` integration registry

When `content.attachEvidence === true`, each spend row (transaction, case) MUST surface its docudesk-attached evidence (invoices,
contracts) using OR's `object-interactions` files integration per
ADR-022 (Integration registry row) + `feedback_integration-registry-end-to-end`.
The widget MUST NOT POST/PUT to docudesk endpoints — uploads are
the sibling app's responsibility.

#### Scenario: Evidence files surface inline

@e2e exclude never built: `attachEvidence` is read only by the form; no renderer, past or present, shows evidence chips or queries docudesk (`git log --all -S'attachEvidence' -- src lib` finds only 7d7da1bb, fda6bd06 and 8e0488cf, all form or registry code). No unit test either.

- **GIVEN** a transaction with 2 docudesk-attached invoices
- **WHEN** the widget renders the transaction row with
  `attachEvidence === true`
- **THEN** the row MUST surface 2 evidence chips with the
  document titles
- **AND** clicking a chip MUST deep-link to the docudesk viewer
  (via OR deep-link registry)

#### Scenario: Evidence widget is read-only

@e2e exclude never built, and a source scan rather than a browser behaviour: there is no evidence code to scan. No launchpad or nc-vue spend file names `/apps/docudesk/` at all, and no test performs the scan.

- **GIVEN** the spend-analytics widget source files
- **WHEN** scanned for HTTP method `POST` / `PUT` / `DELETE` against
  paths matching `/apps/docudesk/`
- **THEN** zero matches MUST exist — uploads happen in docudesk

#### Scenario: No evidence attached

@e2e exclude never built: there are no transaction rows and no evidence area in CnSpendAnalyticsWidget, so there is no slot whose absence could be observed. No unit test either.

- **GIVEN** a transaction with zero attached evidence
- **WHEN** the row renders
- **THEN** the evidence area MUST be hidden (no empty chip slot)
- **AND** the row MUST NOT show an "Attach evidence" affordance —
  attaching happens in docudesk

## Non-Functional Requirements

- **Performance:** The widget MUST debounce period / filter changes
  to ≤200 ms before re-issuing GraphQL queries. Aggregated queries
  SHOULD set GraphQL operation timeouts to 5 s; on timeout the
  widget MUST render a partial / empty-state, never a thrown error.
- **Accessibility:** Every panel inside the widget (summary cards,
  trend chart, drill-through list, AI narrative panel) MUST satisfy
  WCAG 2.1 AA. Numeric values MUST be screen-reader accessible
  (e.g. `aria-label="Total spend Q1 2026: 1.2 million euro"`).
- **Localisation:** Strings MUST be available in English and Dutch
  per the i18n requirement; numeric formatting MUST respect the
  Nextcloud user's locale (NL: `1.234.567,89`, EN: `1,234,567.89`).
- **Privacy:** The widget MUST NOT log spend payloads to the
  browser console or to any external telemetry. The optional AI
  inference payload (REQ-SAW-006) carries spend summary data — the
  openconnector LLM source MUST be the local `local-llm`
  (Ollama+Qwen) per Conduction default, never a hosted-LLM source,
  unless an admin explicitly configures one.
- **Browser support:** Same baseline as the broader widgets capability
  (modern evergreen browsers; no IE).

## Source consolidation

This spec replaces / supersedes the following Specter context
briefs as a single capability:

- `spend-analytics-reporting`
- `spend-analytics-reporting-ai` → REQ-SAW-006 (AI narrative)
- `spend-analytics-reporting-analytics-t1` → REQ-SAW-001..-004 (data + widget)
- `spend-analytics-reporting-analytics-t2` → REQ-SAW-007 (drill-through)
- `spend-analytics-reporting-document-management` → REQ-SAW-008 (evidence)
- `spend-analytics-reporting-other-t1` → REQ-SAW-001..-005 (consolidated)
- `spend-analytics-reporting-other-t2` → REQ-SAW-003 (filters)
- `spend-analytics-reporting-other-t3` → REQ-SAW-003 (category baseline)

The misleading `— Shillinq` suffix in those briefs is dropped; the
capability is reframed as a launchpad widget consuming runtime data.

## Reuses (launchpad)

- `widgets` — registry + add/edit modal contract
- `widget-add-edit-modal` — modal host + per-type validation
- `widget-collision-placement` — grid placement defaults
- `runtime-shell` — workspace mount + edit affordances
- `permissions` — view / edit gating
- `conditional-visibility` — per-role visibility rules
- `responsive-grid-breakpoints` — mobile fallback

## Standards & References

- ADR-022 — apps consume OR abstractions (object-interactions,
  deep-link registry, GraphQL via OR mount).
- ADR-024 — manifest declaration of widgets.
- `feedback_launchpad-no-or-dependency.md` — runtime-only cross-app
  consumption.
- `feedback_shared-deps.md` — GraphQL client from
  `@conduction/nextcloud-vue`.
- `project_ai-chat-companion-end-to-end.md` — established SSE +
  openconnector LLM-source pattern.
- `reference_llphant-ollama-think-false.md` — `think: false` +
  `keep_alive: -1` for the Ollama path.
- ECCMA UNSPSC / CPV — category coding for `filters.categoryIds`
  (consumed from financeq/procest, not redefined here).
