# Tasks: engagement-communities

- [x] 1. Add `pinned_communities` to `lib/Db/AdminSettingKey.php` with token validation, and `talkEnabled` plus the pinned list to `lib/Service/InitialStateBuilder.php`. Verify: PHPUnit and the initial-state contract test.
- [x] 2. Build the `communities` renderer over Talk's listed-room, room and join endpoints, with pinned first, joined marks and the empty state. Verify: Vitest with mocked Talk.
- [x] 3. Register the widget and its form (title, show only pinned) in `src/constants/widgetRegistry.js` and the manifest. Verify: manifest check and Vitest.
- [x] 4. Add the pinned-communities admin section. Verify: Vitest.
- [x] 5. Playwright with Talk enabled: join an open conversation from the widget. Done 17 Nov 2026: `tests/e2e/communities-widget.spec.ts` passed on a live NC 34 with Talk 24.0.8. It was red first: Join posted to `participants/self`, which Talk answers only for leaving (405), so nobody could join; fixed to Talk's join endpoint. The pinned mark was near-invisible (a text colour meant for primary backgrounds) and is now readable.
- [x] 6. Add `nl` and `en` strings, copy the spec to `openspec/specs/communities-widget/spec.md` on archive, and set e-communities to `built` in the parity matrix with evidence lines. Done on archive 17 Nov 2026: the spec is `openspec/specs/communities-widget/spec.md` and e-communities carries the e2e evidence.
