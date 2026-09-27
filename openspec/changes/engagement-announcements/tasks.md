# Tasks: engagement-announcements

## Backend

- [ ] 1. Add the two tables in one migration with builders, entities and mappers (including `notified_at`). Verify: migration test.
- [ ] 2. Seed `announcement.manage`, `announcement.read`, `announcement.follow`, `announcement.comment` and add the `announcement_editor_groups` admin setting. Verify: action-matrix test.
- [ ] 3. Implement `AnnouncementService` (create, update, publish, visibleTo, drafts for authors, notice end time required, reach count). Verify: PHPUnit.
- [ ] 4. Add `AnnouncementController` routes for list, get, create, update, publish, delete, follow and unfollow, each with the action check and no IDOR (visibility checked per id). Verify: PHPUnit and route-reachability.
- [ ] 5. Wire likes and comments through `ICommentsManager` with object type `launchpad_announcement` and the access check. Verify: PHPUnit with the comments manager.
- [ ] 6. Add `announcement_published` to `lib/Notification/Notifier.php` and `AnnouncementNotifyJob` to `appinfo/info.xml`. Verify: PHPUnit that followers are notified once.

## Frontend

- [ ] 7. Add the `announcements` widget to `src/constants/widgetRegistry.js` with card, category filter, like and comment counts. Verify: Vitest.
- [ ] 8. Build the announcement editor modal with the preview step, reach count and "Preview as". Verify: Vitest and an axe check.
- [ ] 9. Add the notice banner region to `src/views/WorkspaceApp.vue` with dismiss for dismissible notices. Verify: Vitest.
- [ ] 10. Add follow and unfollow per category in the widget. Verify: Vitest.

## Close

- [ ] 11. Add the three demo announcements. Verify: PHPUnit on the showcase.
- [ ] 12. Playwright for the notice flow and a liked, commented news item.
- [ ] 13. Add `nl` and `en` strings, copy the spec to `openspec/specs/announcements/spec.md` on archive, and set e-announcements, d-news-preview and e-maintenance to `built` in the parity matrix with evidence lines.
