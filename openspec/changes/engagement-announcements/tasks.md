# Tasks: engagement-announcements

## Backend

- [x] 1. Add the two tables in one migration with builders, entities and mappers (including `notified_at`). Verify: migration test.
- [x] 2. Seed `announcement.manage`, `announcement.read`, `announcement.follow`, `announcement.comment` and add the `announcement_editor_groups` admin setting. Verify: action-matrix test.
- [x] 3. Implement `AnnouncementService` (create, update, publish, visibleTo, drafts for authors, notice end time required, reach count). Verify: PHPUnit.
- [x] 4. Add `AnnouncementController` routes for list, get, create, update, publish, delete, follow and unfollow, each with the action check and no IDOR (visibility checked per id). Verify: PHPUnit and route-reachability.
- [x] 5. Wire likes and comments through `ICommentsManager` with object type `launchpad_announcement` and the access check. Verify: PHPUnit with the comments manager.
- [x] 6. Add `announcement_published` to `lib/Notification/Notifier.php` and `AnnouncementNotifyJob` to `appinfo/info.xml`. Verify: PHPUnit that followers are notified once.

## Frontend

- [x] 7. Add the `announcements` widget to `src/constants/widgetRegistry.js` with card, category filter, like and comment counts. Verify: Vitest.
- [ ] 8. Build the announcement editor modal with the preview step, reach count and "Preview as". Verify: Vitest and an axe check. Built, Vitest `src/modals/__tests__/AnnouncementEditorModal.spec.js` green; the axe check needs the live instance (open: the live pass).
- [x] 9. Add the notice banner region to `src/views/WorkspaceApp.vue` with dismiss for dismissible notices. Verify: Vitest.
- [x] 10. Add follow and unfollow per category in the widget. Verify: Vitest.

## Close

- [x] 11. Add the three demo announcements. Verify: PHPUnit on the showcase.
- [ ] 12. Playwright for the notice flow and a liked, commented news item. Written: `tests/e2e/announcements.spec.ts`; open until it has run green on the live instance.
- [ ] 13. Add `nl` and `en` strings, copy the spec to `openspec/specs/announcements/spec.md` on archive, and set e-announcements, d-news-preview and e-maintenance to `built` in the parity matrix with evidence lines. Strings are in all 37 locales and the spec is synced to `openspec/specs/announcements/spec.md`; the rows are `building` until tasks 8 and 12 pass live, then `built` and the change is archived.
