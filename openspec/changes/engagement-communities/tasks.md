# Tasks: engagement-communities

- [ ] 1. Add `pinned_communities` to `lib/Db/AdminSettingKey.php` with token validation, and `talkEnabled` plus the pinned list to `lib/Service/InitialStateBuilder.php`. Verify: PHPUnit and the initial-state contract test.
- [ ] 2. Build the `communities` renderer over Talk's listed-room, room and join endpoints, with pinned first, joined marks and the empty state. Verify: Vitest with mocked Talk.
- [ ] 3. Register the widget and its form (title, show only pinned) in `src/constants/widgetRegistry.js` and the manifest. Verify: manifest check and Vitest.
- [ ] 4. Add the pinned-communities admin section. Verify: Vitest.
- [ ] 5. Playwright with Talk enabled: join an open conversation from the widget.
- [ ] 6. Add `nl` and `en` strings, copy the spec to `openspec/specs/communities-widget/spec.md` on archive, and set e-communities to `built` in the parity matrix with evidence lines.
