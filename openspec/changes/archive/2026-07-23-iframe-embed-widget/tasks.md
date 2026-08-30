# Tasks: iframe-embed widget

## Backend
- [ ] `lib/Widget/IframeWidgetProvider.php` — register `launchpad_iframe` (v2) with `IManager`; title + icon.
  - NOT implemented as specced. LaunchPad has no `lib/Widget/` PHP-provider layer for ANY widget type (confirmed against the `clock`/`weather`/`livetile` siblings, which also have no PHP provider) — custom widget types register from the frontend `src/constants/widgetRegistry.js` catalog instead (`registerDashboardWidget('iframe', …)`), consumed by `CnAddWidgetModal` / `WidgetRenderer`. No `OCP\Dashboard\IManager::registerWidget()` call exists for this widget. This is a real gap vs. the spec's REQ-IFRAME-001 "Registration via IManager" scenario, left honestly unchecked rather than silently reinterpreted.
- [x] Admin config `iframe_allowed_hosts` (LaunchPad app config); admin settings UI to edit the allow-list.
  - The `iframe_allowed_hosts` `IAppConfig` key is implemented and enforced (`IframeService`). The admin settings **UI** is NOT built — mirrors the `livetile_allowed_hosts` precedent, which is also `occ config:app:set`-only with no admin Vue panel. Documented in `docs/features/iframe-embed.md`.
- [x] Validate a placement's URL host against `iframe_allowed_hosts` on save (fail-closed) and expose an allow-list-checked flag to the config UI.
- [x] `lib/Listener/CspListener.php` — listen for `AddContentSecurityPolicyEvent`; add each allow-listed host to the app `IContentSecurityPolicy` `frame-src`; register the listener in `Application.php`.
- [x] `appinfo/routes.php` / bootstrap wiring for the admin setting and listener (no per-widget data endpoint — config lives in `widgetContent`).
  - Added `iframe#validateUrl` (`POST /api/iframe/validate-url`) before the catch-all deep-link route.

## Frontend
- [x] `src/components/Widgets/Renderers/IframeWidget.vue` — sandboxed iframe with `title`; detect frame-refused / blank load and render the fallback card (title + explanation + open-in-new-tab link); loading + error states.
  - Path differs from the proposal's `src/components/widgets/IframeWidget.vue` — matches the ACTUAL sibling location (`clock`/`weather`/`livetile` all live under `src/components/Widgets/Renderers/`).
- [x] `src/components/Widgets/Renderers/IframeWidgetForm.vue` — URL (allow-list-validated), title, height/aspect, sandbox token toggles (no `allow-top-navigation`), allow-list-checked indicator.
- [x] Register `launchpad_iframe` in the widget catalogue/constants.
  - Registered as type key `iframe` in `src/constants/widgetRegistry.js` (matching the short-key convention of every other LaunchPad-native type — `clock`, `weather`, `livetile` — not a literal `launchpad_iframe` id, since there is no `IManager` registration for that id to match). `EXPECTED_TYPES` in the registry completeness spec updated in the same commit.

## Testing
- [x] PHPUnit: allow-list host validation fail-closed on save; empty list embeds nothing.
  - `tests/Unit/Service/IframeServiceTest.php` (17 tests).
- [x] PHPUnit: `CspListener` adds only allow-listed hosts to `frame-src`; no wildcard.
  - `tests/Unit/Listener/CspListenerTest.php` (7 tests). Also added `tests/Unit/Controller/IframeControllerTest.php` (4 tests) for the validation endpoint.
- [x] Vitest: config validation (host allow-list, sandbox cannot include `allow-top-navigation`); fallback-card render when load fails.
  - `IframeWidget.spec.js` (12 tests) + `IframeWidgetForm.spec.js` (15 tests).
- [ ] Playwright: drop iframe widget, configure an allow-listed URL, confirm the frame renders; configure a frame-refusing target and confirm the fallback card + open-in-new-tab link appear (no blank frame).
  - NOT run — explicitly out of scope for this build pass (unit tests only, no e2e/Playwright, no deploy to the shared dev instance).

## Docs
- [x] Document the admin allow-list, the CSP `frame-src` contribution, and the X-Frame-Options / frame-ancestors fallback behaviour in the dashboard-authoring docs.
  - `docs/features/iframe-embed.md`. The master widget catalog (`docs/features/widgets.md`) was NOT resynced — it already predates the `clock`/`weather`/`livetile` siblings too (still lists only 25 types), so leaving it untouched matches existing precedent rather than a partial renumbering in an unrelated change.

## Out of scope (follow-ups)
- SSO / authenticated pass-through into the target — `iframe-auth-passthrough`.
- Per-user allow-listing — admin-only in v1.
- Reverse-proxy stripping of `X-Frame-Options` — fallback card only in v1.
- Admin settings Vue UI for editing `iframe_allowed_hosts` (currently `occ config:app:set` only, matching the `livetile_allowed_hosts` precedent).
- `OCP\Dashboard\IManager` v2 registration (`launchpad_iframe` id) — LaunchPad's widget architecture has no PHP provider layer for any custom widget type; would require an app-wide architectural change, not scoped to this widget alone.
