# Conditional visibility editor — an author UI over the existing rules engine

LaunchPad already ships a fully-implemented conditional-visibility rules engine (`conditional-visibility` spec, status: done): group / time-of-day / date-range / attribute rules, evaluated at render time, with include=OR and exclude=AND semantics. But that spec is explicit that in v1.0.5 there is **no UI surface for rule editing** — rules can only be created, listed, updated and deleted via the JSON API (`POST/GET /api/widgets/{placementId}/rules`, `PUT/DELETE /api/rules/{ruleId}`). Conditional visibility is LaunchPad's own headline feature, yet an author cannot use it without hand-crafting API calls.

This change adds the admin/author UI to create, edit, preview and delete visibility rules on a widget placement directly from the widget settings panel, plus a **"preview as audience / date"** affordance so authors can see the effective visibility for a chosen group on a chosen date/time **before** publishing. Preview de-risks the headline feature (a mis-scoped exclude rule can silently hide a widget from everyone) and closes a competitive gap: Microsoft Viva offers preview-as-audience for its adaptive-card targeting.

This change is a **UI over the existing engine**. It MUST NOT change rule evaluation semantics, rule storage, or the rule shape. The one new backend endpoint is read-only and non-persisting: it exists solely so the preview reuses the *same* evaluation code path as render-time, guaranteeing the preview can never diverge from what the dashboard will actually show.

## Affected code units

- `src/components/ConditionalVisibilityEditor.vue` — new. The rule builder embedded in the placement/widget settings panel: lists a placement's rules, adds/removes rows, groups them visually into "Show when…" (include / OR) and "Hide when…" (exclude / AND) sections so the engine's semantics are legible, and hosts the preview affordance. Reads and writes rules via the existing `/api/widgets/{placementId}/rules` and `/api/rules/{ruleId}` endpoints — no new persistence path.
- `src/components/VisibilityRuleRow.vue` — new. One rule: a `ruleType` selector (`group` / `time` / `date` / `attribute`) plus the type-specific operand fields (group multi-select; startTime/endTime + day-of-week for time; startDate/endDate for date; attribute + operator + value for attribute), and an include/exclude toggle. Emits the canonical `ruleConfig` shape defined by the existing spec (camelCase keys).
- `src/composables/useVisibilityPreview.js` — new. Given the current in-editor rule set and a chosen `(groups, datetime)` context, calls the preview endpoint and returns the effective visibility plus which rules matched. Uses the same rule shape the editor emits so no translation layer can drift.
- Integration into the existing placement/widget settings modal — mount `ConditionalVisibilityEditor` in a "Visibility" section of the settings panel; no change to the modal's other fields.
- `lib/Controller/VisibilityPreviewController.php` — new. `#[NoAdminRequired]` `POST /api/visibility/preview` that evaluates a supplied rule set against a supplied `(groups, datetime)` context and returns the effective visibility **without persisting anything**. It reuses the existing evaluation service (`VisibilityChecker` / `RuleEvaluatorService` via `ConditionalService`) so preview and runtime agree by construction.

No DB schema change: rules are already persisted by the `conditional-visibility` capability (`oc_launchpad_conditional_rules`). This change adds no table, column, or migration.

## Why a new change

The rules engine and its CRUD API are `status: done`; retrofitting a UI into that closed spec would muddy a completed capability. The UI is also a distinct surface with its own concerns — client-side validation, legibility of include/exclude semantics, and a preview affordance — that warrant their own requirements and e2e coverage. The one backend addition (a stateless preview endpoint) is deliberately minimal and read-only; it is bundled here rather than in the engine spec because it exists only to serve the UI's preview, and its whole contract is "reuse the render-time evaluation code path, persist nothing".

## Approach

- **No semantic change.** The editor reads/writes rules through the existing endpoints and emits the exact `ruleConfig` shapes the `conditional-visibility` spec defines (`group`: `{groups:[…]}`; `time`: `{startTime,endTime,days?}`; `date`: `{startDate?,endDate?}`; `attribute`: `{attribute,operator,value}`). Evaluation semantics (include=OR, exclude=AND, isVisible gate) are untouched.
- **Legible semantics.** The editor renders include rules under a "Show when any of these match" heading and exclude rules under "Hide when any of these match", making the OR/AND behaviour explicit rather than implied by a boolean flag.
- **Preview reuses the engine.** `POST /api/visibility/preview` accepts `{rules:[…], context:{groups:[…], datetime:"…"}}` and evaluates it through the *same* `VisibilityChecker`/`RuleEvaluatorService` used at render time, injected with the supplied context instead of the live user/clock. It returns `{visible, matchedIncludeRuleIds, matchedExcludeRuleIds}` and writes nothing to the database. This is the single guarantee that preview cannot diverge from actual visibility.
- **Validation both sides.** The row component validates operand shape client-side (e.g. time `HH:MM`, non-empty groups) before enabling save; the preview endpoint and the existing CRUD endpoints validate server-side, rejecting unknown `ruleType` values and malformed `ruleConfig` with HTTP 400.
- **WCAG AA.** Include/exclude grouping is conveyed by heading text and layout, not colour alone; the preview result states "Visible" / "Hidden" in text with an icon, not colour only.

## Notes

- Server-time semantics carry over: time rules evaluate in the server timezone. The preview accepts a `datetime` and evaluates it as server-local, matching render-time behaviour (including the known midnight-spanning limitation — preview reflects the real engine, warts and all, rather than a "corrected" model).
- Out of scope: fixing the engine's known limitations (midnight-spanning time windows, missing timezone field, missing ruleType validation in the engine, missing ownership checks on update/delete). Those belong to the `conditional-visibility` engine spec, not its UI. Where the missing update/delete ownership check is user-visible, this change surfaces it as a follow-up but does not add the guard here.
- Out of scope: bulk rule templates / saved audiences (follow-up `visibility-saved-audiences`).
- Out of scope: preview across a full dashboard (this change previews one placement's rule set at a time).
