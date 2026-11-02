# Design: engagement-announcements

Read at development `d767c282`.

## Context

- LaunchPad owns its tables (`lib/Db`, `lib/Migration`, latest `Version002010Date20260918184500`) and consumes OpenRegister only at runtime (`openspec/specs/launchpad-adopt-or-abstractions/spec.md`).
- Dashboard reactions are LaunchPad rows (`lib/Db/DashboardReaction.php`, `lib/Service/ReactionService.php`), scoped to a dashboard. There is no comments code in `lib`; the open change `openregister-leaf-integrations` records "Building comments, mentions, or notification features in launchpad" as a non-goal and hands discussion to a platform host.
- `lib/Notification/Notifier.php` renders LaunchPad notifications (`dashboard_shared` and others, lines 135-151) and is registered at `lib/AppInfo/Application.php:116`.
- Scheduled dashboards surface as published at read time when `publishAt` has passed (`lib/Service/DashboardService.php:1493-1517`); the same pattern fits announcements.
- Background jobs are listed in `appinfo/info.xml:94-98`.
- Time-boxed messages today are text widgets with a date visibility rule (`src/components/Widgets/VisibilityRuleRow.vue:105-115`).
- The Workspace page shell is `src/views/WorkspaceApp.vue` (sidebar, `Views` grid at line 73, footer at line 100).
- `lib/Controller/VisibilityPreviewController.php:102` already previews whether a given person sees a widget.

## Data

`oc_launchpad_announcements`: id, uuid, kind (`news` or `notice`), title, body (markdown, sanitised on render as the text widget does), category (nullable string), level (`info` or `warning`, notices only), dismissible (0/1), target_groups (JSON, empty means everyone), status (`draft`, `published`), publish_at, expires_at (nullable), allow_comments (0/1), author_id, created_at, updated_at.

`oc_launchpad_announcement_follows`: id, user_id, category, created_at, unique on (user_id, category).

Dismissed notices are stored per user in the existing preferences (`/api/preferences/{key}`, key `dismissed-notices`).

## Decisions

### D1: Announcements are their own object, shown by one widget and one banner

A news announcement shows in the new `announcements` widget (newest first, category filter, like and comment counts). A notice shows in a banner region above the grid in `WorkspaceApp.vue`, for everyone it targets, from `publish_at` to `expires_at`, whatever dashboard they open. Nothing needs placing on each dashboard, which is the missing half of e-maintenance.

### D2: Likes and comments use Nextcloud's comments service

`OCP\Comments\ICommentsManager` with object type `launchpad_announcement` stores comments and reactions (`supportReactions()`), and a "like" is the 👍 reaction. LaunchPad keeps no comment rows, which honours the non-goal in `openregister-leaf-integrations`. Comment access is checked by LaunchPad: a reader may comment only on an announcement that targets them and has `allow_comments`.

### D3: Targeting is checked on the server for every read

`AnnouncementService::visibleTo(userId)` returns published announcements whose `target_groups` is empty or intersects `IGroupManager::getUserGroupIds()`, with `publish_at <= now` and (`expires_at` null or in the future). Drafts are visible only to authors and administrators.

### D4: Preview renders the real component

The editor has a "Preview" step that renders the same widget card or banner component with the draft, plus "Reaches about N people" computed from the target groups' member counts. "Preview as" reuses the visibility-preview idea: pick a user and see whether the announcement reaches them. Publishing is a separate button after the preview.

### D5: Follow notifications from one job

`AnnouncementNotifyJob` (every 5 minutes, `TimedJob`) finds announcements that became visible since its last run and sends `announcement_published` to followers of the category who are also in the target groups. Publishing with a publish time of now notifies at once from the service, and the job skips those already notified (a `notified_at` column).

### D6: Who may author

Administrators, plus members of the admin setting `announcement_editor_groups`. Enforced by new actions in `lib/actions.seed.json` (`announcement.manage` for authors, `announcement.read`, `announcement.follow` and `announcement.comment` for everyone) and a group check in the service.

## Declarative-vs-imperative decision

LaunchPad has no OpenRegister register for its own data, so announcements are LaunchPad tables and a service. Notifications go through the existing Nextcloud notifier; comments and reactions through the Nextcloud comments service.

## Seed data

Demo installs get three announcements: a news item "Nieuwe werkplekken op de 3e verdieping" (category "Facilitair", everyone), a news item "Inloopspreekuur privacy" (category "Privacy", group "Medewerkers"), and a notice "Onderhoud zaaksysteem zaterdag 08:00 tot 12:00" (level warning, not dismissible, ends on the Saturday).

## Risks

- A notice with no end time stays forever. Mitigation: notices require `expires_at`; news items may leave it empty.
- Large target groups make the reach count slow. Mitigation: count through `IGroupManager` once per preview, cached for the editing session.

## Test plan

- PHPUnit: targeting, drafts hidden, time window, comment access, follow notification once, author rights, notice needs an end time.
- Vitest: widget list, like and comment counts, editor preview step, banner dismiss.
- Playwright: an editor writes a notice for group "Burgerzaken", previews it as a banner, publishes; a member sees the banner above his dashboard; a non-member does not.

## What the build changed (5 Oct 2026)

- The follows table is `oc_launchpad_ann_follows`, not `oc_launchpad_announcement_follows`: Nextcloud refuses table names over 27 characters without the prefix.
- A like is a comment with verb `like` (message 👍) on object type `launchpad_announcement`, one per person, not a Nextcloud reaction: Nextcloud reactions hang on a parent comment, and an announcement is not one. Comments use verb `comment`. Counts come from `getNumberOfCommentsForObjects` per verb.
- The preview shows "People reached: N" (an exact count of the distinct members of the target groups, or of all users when no group is set).
- Demo announcements are written once, by `DemoDataService::install()`, with the app as author and already marked notified, so a demo install sends no notifications. "Inloopspreekuur privacy" targets "Medewerkers" only when that group exists.
- The editor groups are their own admin tab ("Announcements") and endpoint `/api/announcement-settings`, behind the admin-only action `announcement.settings`.
- Dismissed notices are stored in the preference `dismissed-notices`, pruned to notices still running.
