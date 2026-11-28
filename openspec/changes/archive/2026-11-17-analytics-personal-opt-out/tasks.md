# Tasks: analytics-personal-opt-out

- [x] 1. Add a route that reads and writes the signed-in person's own `analytics_optout` value (`AnalyticsService::USER_PREF_KEY_OPTOUT`), `#[NoAdminRequired]`, and refuse any other user id. Verify: PHPUnit on the controller.
- [x] 2. Add the "Count my visits" switch to LaunchPad's personal settings (the user settings modal, not a `/settings/user` page), hidden while `analytics_enabled` is off. Verify: Vitest on the component.
- [x] 3. Fold REQ-ANLT-UI-001 into `openspec/specs/dashboard-view-analytics` on archive, and set parity row g-optout back to `built` with evidence lines. Done on archive 17 Nov 2026, after a live check on NC 34: `GET /api/analytics/me` answered `{"enabled":true,"optedOut":false}`, `PUT {"optedOut":true}` stored `analytics_optout=true` for the caller, and a PUT naming another user was refused ("You can only change your own analytics setting"). g-optout is `built` with evidence lines.
