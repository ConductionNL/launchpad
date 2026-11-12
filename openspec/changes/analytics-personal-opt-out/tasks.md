# Tasks: analytics-personal-opt-out

- [ ] 1. Add a route that reads and writes the signed-in person's own `analytics_optout` value (`AnalyticsService::USER_PREF_KEY_OPTOUT`), `#[NoAdminRequired]`, and refuse any other user id. Verify: PHPUnit on the controller.
- [ ] 2. Add the "Count my visits" switch to LaunchPad's personal settings (the user settings modal, not a `/settings/user` page), hidden while `analytics_enabled` is off. Verify: Vitest on the component.
- [ ] 3. Fold REQ-ANLT-UI-001 into `openspec/specs/dashboard-view-analytics` on archive, and set parity row g-optout back to `built` with evidence lines.
