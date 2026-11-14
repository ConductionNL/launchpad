# Tasks: sharing-public-start-page

- [ ] 1. Add `public_start_enabled` and `public_start_share_token` with the D1 validation, and the admin section on `SharingTab.vue` listing eligible shares and the switched-off reason. Verify: PHPUnit and Vitest.
  - Backend done: settings keys, `PublicStartPage` validation service, `GET/PUT /api/admin/public-start`, PHPUnit. Left: the `SharingTab.vue` admin section and its Vitest.
- [x] 2. Add the `BeforeLoginTemplateRenderedEvent` listener with the D2 conditions and the redirect script. Verify: PHPUnit per condition.
  - `PublicStartRedirectListener` adds `launchpad-start-redirect` (`src/startRedirect.js`, webpack entry `start-redirect`) only for a signed-out GET without `redirect_url`, `direct` or `clear` while the start page can be shown; `PublicStartRedirectListenerTest` covers each condition and a failing check.
- [x] 3. Add `PageController::start()` on `/start` with `#[PublicPage]`, `#[NoCSRFRequired]` and the public-share rate limit. Verify: PHPUnit and route-reachability.
  - `page#start` on `/start`, before the deep-link catch-all. The token goes to the page as initial state (`Page::PUBLIC_START`, key `publicStartToken`, schema version 3); without a showable link it redirects to the login form with `direct=1`. `PageControllerStartTest`.
- [x] 4. Show the sign-in bar in the public view when rendered as the start page. Verify: Vitest and an axe check.
  - `DashboardPublicShareView` `startPage` prop: a `nav` bar with the instance name and a "Sign in" link to `/login?direct=1`, kept on a load error. Vitest in `DashboardPublicShareView.startPage.spec.js` and `publicStart.spec.js`; the axe check needs a browser and is left for the Playwright run.
- [ ] 5. Playwright: land on the start page, sign in, deep link still logs in.
- [ ] 6. Add `nl` and `en` strings, fold the delta into `openspec/specs/dashboard-public-share/spec.md` on archive, and set d-public-start to `built` in the parity matrix with evidence lines.
