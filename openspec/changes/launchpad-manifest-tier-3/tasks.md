# Tasks: LaunchPad manifest tier 3

## Decide first

- [ ] Settle whether `CnAppRoot` hosts `WorkspaceApp` or the workspace shell keeps the page and only borrows the footer chrome.
- [ ] Settle the skip link: one bypass target, not two.
- [ ] Settle whether a dashboard gets a URL, and whether that is this change or `dashboard-deeplinking`.

## Routing

- [ ] Add `vue-router` and create the router in `src/main.js` in **path** mode (`createWebHistory`), matching the fleet: a hash route would be ignored and land silently on the dashboard.
- [ ] Derive the route table from `manifest.pages` rather than hand-wiring it, so a declared page is a routed page by construction.
- [ ] `/dashboard` (and `/`) render `WorkspaceApp`; `#launchpad-main-content` keeps `tabindex="-1"` and stays the bypass target.
- [ ] `/store`, `/reports`, `/reports/dashboards` and `/features-roadmap` render their declared page types.

## Shell

- [ ] Root on `CnAppRoot` per the decision above, keeping the org navigation rail and slide-in sidebar working.
- [ ] One skip link.
- [ ] `.workspace-shell` still renders on the dashboard route — the chrome spec's tripwire test asserts the shell, and it should keep passing.

## Tests

- [ ] Remove the four `test.fail()` markers in `tests/e2e/app-chrome.spec.ts`. They are expected-to-fail today; Playwright fails the run if a `test.fail()` test passes, so landing routing turns them red until the markers come off. That is the intended signal.
- [ ] The tripwire test `the shell is LaunchPad's own, not the shared CnAppNav one` asserts `cn-nav` has count 0. If `CnAppRoot` is adopted it must be rewritten, not deleted.
- [ ] A route test per declared page, so a page added to the manifest without a component fails.

## Verify

- [ ] gate-107 still reports 5 of 5, and now truthfully.
- [ ] The E2E leg is green on the `development` push run — it has not been since 2026-09-03.
