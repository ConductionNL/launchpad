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
- [x] 8. Build the announcement editor modal with the preview step, reach count and "Preview as". Verify: Vitest and an axe check.
- [x] 9. Add the notice banner region to `src/views/WorkspaceApp.vue` with dismiss for dismissible notices. Verify: Vitest.
- [x] 10. Add follow and unfollow per category in the widget. Verify: Vitest.

## Close

- [x] 11. Add the three demo announcements. Verify: PHPUnit on the showcase.
- [ ] 12. Playwright for the notice flow and a liked, commented news item.
- [ ] 13. Add `nl` and `en` strings, copy the spec to `openspec/specs/announcements/spec.md` on archive, and set e-announcements, d-news-preview and e-maintenance to `built` in the parity matrix with evidence lines.

Built: tasks 1 to 11. Backend: `AnnouncementTablesBuilder` and migration `Version002013Date20261114000000`, `Announcement`/`AnnouncementFollow` with their mappers, `AnnouncementService` (with "Preview as" through `reachesUser()`), `AnnouncementInput`, `AnnouncementFollowService`, `AnnouncementCommentService` (likes and comments through `ICommentsManager`, object type `launchpad_announcement`), `AnnouncementFollowNotifier` and `AnnouncementNotifyJob` (`announcement_published`, once per announcement through `notified_at`), `DemoAnnouncements` (seeded with the demo data), `AnnouncementController` with thirteen routes, the four `announcement.*` actions, and `announcementEditorGroups` in the admin settings API. Frontend: the `announcements` widget and its form, `AnnouncementCard`, `NoticeBanner` and `NoticeBannerRegion` above the Workspace grid, `AnnouncementEditorModal` with the preview step, and the editor groups picker in Beheer > Sharing.

Open: task 12 (Playwright needs a live instance), the axe check of task 8 (no axe tooling in the repository), and of task 13 the strings of the 34 locales besides `en` and `nl` and the copy of the spec on archive. The `en` and `nl` strings and the parity matrix rows are done.
