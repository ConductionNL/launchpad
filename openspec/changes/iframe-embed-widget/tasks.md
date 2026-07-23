# Tasks: iframe-embed widget

## Backend
- [ ] `lib/Widget/IframeWidgetProvider.php` — register `launchpad_iframe` (v2) with `IManager`; title + icon.
- [ ] Admin config `iframe_allowed_hosts` (LaunchPad app config); admin settings UI to edit the allow-list.
- [ ] Validate a placement's URL host against `iframe_allowed_hosts` on save (fail-closed) and expose an allow-list-checked flag to the config UI.
- [ ] `lib/Listener/CspListener.php` — listen for `AddContentSecurityPolicyEvent`; add each allow-listed host to the app `IContentSecurityPolicy` `frame-src`; register the listener in `Application.php`.
- [ ] `appinfo/routes.php` / bootstrap wiring for the admin setting and listener (no per-widget data endpoint — config lives in `widgetContent`).

## Frontend
- [ ] `src/components/widgets/IframeWidget.vue` — sandboxed iframe with `title`; detect frame-refused / blank load and render the fallback card (title + explanation + open-in-new-tab link); loading + error states.
- [ ] `src/components/widgets/IframeWidgetConfig.vue` — URL (allow-list-validated), title, height/aspect, sandbox token toggles (no `allow-top-navigation`), allow-list-checked indicator.
- [ ] Register `launchpad_iframe` in the widget catalogue/constants.

## Testing
- [ ] PHPUnit: allow-list host validation fail-closed on save; empty list embeds nothing.
- [ ] PHPUnit: `CspListener` adds only allow-listed hosts to `frame-src`; no wildcard.
- [ ] Vitest: config validation (host allow-list, sandbox cannot include `allow-top-navigation`); fallback-card render when load fails.
- [ ] Playwright: drop iframe widget, configure an allow-listed URL, confirm the frame renders; configure a frame-refusing target and confirm the fallback card + open-in-new-tab link appear (no blank frame).

## Docs
- [ ] Document the admin allow-list, the CSP `frame-src` contribution, and the X-Frame-Options / frame-ancestors fallback behaviour in the dashboard-authoring docs.

## Out of scope (follow-ups)
- SSO / authenticated pass-through into the target — `iframe-auth-passthrough`.
- Per-user allow-listing — admin-only in v1.
- Reverse-proxy stripping of `X-Frame-Options` — fallback card only in v1.
