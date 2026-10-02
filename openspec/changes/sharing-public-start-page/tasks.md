# Tasks: sharing-public-start-page

- [ ] 1. Add `public_start_enabled` and `public_start_share_token` with the D1 validation, and the admin section on `SharingTab.vue` listing eligible shares and the switched-off reason. Verify: PHPUnit and Vitest.
- [ ] 2. Add the `BeforeLoginTemplateRenderedEvent` listener with the D2 conditions and the redirect script. Verify: PHPUnit per condition.
- [ ] 3. Add `PageController::start()` on `/start` with `#[PublicPage]`, `#[NoCSRFRequired]` and the public-share rate limit. Verify: PHPUnit and route-reachability.
- [ ] 4. Show the sign-in bar in the public view when rendered as the start page. Verify: Vitest and an axe check.
- [ ] 5. Playwright: land on the start page, sign in, deep link still logs in.
- [ ] 6. Add `nl` and `en` strings, fold the delta into `openspec/specs/dashboard-public-share/spec.md` on archive, and set d-public-start to `built` in the parity matrix with evidence lines.
