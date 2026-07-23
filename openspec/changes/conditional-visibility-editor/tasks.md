# Tasks: Conditional visibility editor

## Backend
- [ ] `lib/Controller/VisibilityPreviewController.php` — `#[NoAdminRequired]` `POST /api/visibility/preview`; accepts `{rules:[…], context:{groups:[…], datetime}}`; returns `{visible, matchedIncludeRuleIds, matchedExcludeRuleIds}`; persists nothing.
- [ ] Route the preview endpoint in `appinfo/routes.php` with an auth attribute.
- [ ] Wire the preview controller to the EXISTING evaluation code path (`ConditionalService` / `VisibilityChecker` / `RuleEvaluatorService`) with the supplied `(groups, datetime)` injected in place of the live user/clock — no fork, no re-implementation.
- [ ] Server-side validation: reject unknown `ruleType` and malformed `ruleConfig` with HTTP 400 (shared with, not duplicated from, the CRUD path).

## Frontend
- [ ] `src/components/VisibilityRuleRow.vue` — ruleType selector (group/time/date/attribute) + type-specific operands + include/exclude toggle; emits the canonical camelCase `ruleConfig` shape; client-side operand validation.
- [ ] `src/components/ConditionalVisibilityEditor.vue` — list/add/remove rows; group them under "Show when…" (include/OR) and "Hide when…" (exclude/AND); read/write via existing `/api/widgets/{placementId}/rules` and `/api/rules/{ruleId}`; host the preview affordance (group picker + datetime picker + result).
- [ ] `src/composables/useVisibilityPreview.js` — take the in-editor rule set + `(groups, datetime)`, call `POST /api/visibility/preview`, return effective visibility + matched rule ids; use the same rule shape the editor emits.
- [ ] Mount `ConditionalVisibilityEditor` in a "Visibility" section of the existing placement/widget settings modal; leave the modal's other fields untouched.

## Testing
- [ ] PHPUnit: `VisibilityPreviewController` returns the same verdict as render-time evaluation for identical `(rules, groups, datetime)` — assert it delegates to the shared evaluation service and persists nothing (no DB write).
- [ ] PHPUnit: preview endpoint rejects unknown `ruleType` / malformed `ruleConfig` with HTTP 400.
- [ ] Vitest: `VisibilityRuleRow` emits correct `ruleConfig` per type; client-side validation blocks malformed operands (bad time, empty groups).
- [ ] Vitest: `useVisibilityPreview` posts the editor's rule shape unchanged and maps the response.
- [ ] Playwright: open a placement's Visibility section, add an include group rule + an exclude date rule, run "preview as audience/date" for a chosen group+datetime, confirm the previewed verdict matches what the dashboard renders for that context; save and confirm rules persist via the existing API.

## Docs
- [ ] Add a "Visibility rules & preview" section to dashboard-authoring docs; explain include=OR / exclude=AND and the preview-as-audience affordance; cross-reference the `conditional-visibility` engine spec.

## Out of scope (follow-ups)
- Engine limitations (midnight-spanning time windows, timezone field, engine-side ruleType validation, update/delete ownership guards) — belong to the `conditional-visibility` engine spec.
- Saved audiences / rule templates — `visibility-saved-audiences`.
- Whole-dashboard preview — this change previews one placement's rule set at a time.
