# Tasks: sharing-kiosk-screens

- [x] 1. Serve the HTML player from `GET /kiosk/{token}` on an HTML Accept header and keep the JSON for callers that ask for it. Verify: PHPUnit for both, and for a revoked token (404 in both).
- [x] 2. Add the `kiosk.js` webpack entry, `templates/kiosk.php` and `KioskPlayerView.vue` with dwell timer, refresh and offline retry. Verify: Vitest with fake timers for rotation, refresh and network failure.
- [x] 3. Add the administration "Kiosk" tab: list, create, edit, copy link, revoke. Verify: Vitest using the real service limits.
- [x] 4. `nl` and `en` strings; axe on the tab. The player has no interactive controls to check.
- [ ] 5. Playwright: create a playlist with two dashboards, open the link logged out, see both in turn, revoke, see the revoked message. Not written in this change: the spec carries `@e2e exclude` naming the Vitest files.
- [x] 6. On archive fold the delta into `openspec/specs/dashboard-kiosk-mode/spec.md` and set `s-kiosk` to `built`.

