# Tasks: Integration — Polls

> Wave-4 partial-leaf scope. Backend `PollsProvider` already shipped via the umbrella (direct `polls_polls` DB query — session workaround documented inline). The PollLink table + wrapping PollService + dedicated controller are intentionally deferred to a follow-up change once NC Polls' `PollService` exposes a stable API surface without the session-context dependency. This change lands the bespoke Vue tab + widget + descriptor so consuming apps (decidesk first) can reach the live tally today.

## Backend

- [x] `PollsProvider` — id='polls', label='Polls', icon='Poll', group='workflow', requiredApp='polls', storage='link-table' (already shipped via umbrella; DB-direct `polls_polls` query with documented session workaround)
- [x] DI-tag (inherited via the umbrella's IntegrationProvider tag)
- [x] Sub-resource controller (`ObjectIntegrationsController` serves `/integrations/polls` generically)
- [ ] _Deferred_: `PollLink` entity + mapper + migration — once link-table writes are needed alongside the marker-in-title workaround
- [ ] _Deferred_: `PollService` wrapping Polls REST API — once NC Polls exposes a session-free service
- [ ] _Deferred_: dedicated unit tests for `PollsProvider::list()` — pairs with the wrapping service follow-up

## Frontend — Tab

- [x] `CnPollsTab.vue` — linked polls with status/tally/user-vote; per-option progress bars + deadline countdown
- [x] Co-located `__tests__/CnPollsTab.spec.js` — empty / results / deadline-elapsed / 503 / generic-error / bare-payload coverage

## Frontend — Widget

- [x] `CnPollsCard.vue`:
  - `user-dashboard`: count + open headline + most-recent leader
  - `app-dashboard`: count + open headline + most-recent leader
  - `detail-page`: per-row mini option bars + deadline meta
  - `single-entity`: chip with poll title + leading option fragment
- [x] Co-located `__tests__/CnPollsCard.spec.js` — empty / dashboard headline / all-closed / detail-page list / single-entity chip / 503 / fetch-throw / closed-row class coverage

## Registration

- [x] `src/integrations/builtin/polls.js` — bespoke descriptor with `referenceType: 'polls'`, order=66 (mirrors leaves.js)
- [x] AD-13 collision policy: bespoke wins when registered before `registerLeafIntegrations()`

## Quality

- [x] Parity gate (`scripts/check-integration-parity.js`) passes
- [x] ESLint clean on the polls/ tree
- [x] Jest: 14 polls-specific tests + 54 existing integration-suite tests all green
- [x] Pre-translated default props ship English fallbacks; nl bundle update batched into the next i18n sweep
- [x] Backend untouched — PHPCS/PHPMD/PHPStan/Psalm unchanged

## Acceptance verification

- [x] Component-level scenario coverage via the 14 Jest specs (closed poll, deadline countdown, 503, single-entity, etc.)
- [x] Registry-level hide / reference-property / parity coverage via `tests/integrations/{leaves,registry,builtin}.spec.js` (54 tests green)
- [ ] _Deferred_: full E2E (install Polls, create poll from object, vote, verify in Polls app) — pairs with the PollLink table follow-up

## Notes

- All `_Deferred_` items intentionally remain `[ ]` per ADR-022 "honest scope" — they belong to a follow-up change once NC Polls' service surface stabilises. The bespoke Vue components ship today because the registry surface and provider payload already work.
