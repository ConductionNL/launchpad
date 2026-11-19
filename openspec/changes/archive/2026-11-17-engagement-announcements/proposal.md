---
kind: code
depends_on: []
---

# Announcements: targeted news people can like, comment on and follow, previewed before publishing, and time-boxed notices

## Why

LaunchPad has no news of its own. A text widget can carry a message, with no
likes, comments or follow (matrix note on e-announcements). A maintenance
notice is possible only by hand, as a text widget with a date rule, and there
is no notice type (note on e-maintenance). With no authoring there is nothing
to preview before it goes out (note on d-news-preview).

This change covers three rows of the LaunchPad parity matrix
(`openspec/parity/capabilities.json`). They share one object, one editor and
one service, so they are one change.

**e-announcements**, "Post targeted news announcements that people can like, comment on and follow." Rated `no`, `built.state` `none`.

- Workspace 365, yes: https://support.workspace365.net/en/articles/284826-create-announcements targeting, "comment sections, emoji reactions"; https://portal.productboard.com/iqfsnhkpzih6grzjwagif2ek/tabs/3-launched "Follow announcement categories".
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/use-audience-targeting-sharepoint-app-in-teams news targeted to groups; https://learn.microsoft.com/en-us/sharepoint/homesites/sharepoint-app-in-teams-news-notifications likes, comments, follow sites.

**d-news-preview**, "Preview a news item before it is published." Rated `no`, `built.state` `none`.

- Demand: roadmap, https://portal.productboard.com/iqfsnhkpzih6grzjwagif2ek/tabs/3-launched ("Preview of hub items before publishing", launched 2025).
- Workspace 365, yes: the same Productboard entry.
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/viva/connections/announcements-viva-connections "Select Next to review the details of your announcement" before sending.

**e-maintenance**, "Put a maintenance or outage notice on everyone's dashboard for a set period." Rated `partial`, `built.state` `built` (a text widget with a date rule, `src/components/Widgets/VisibilityRuleRow.vue:111`). This change specifies the missing half, a notice type.

- Workspace 365, yes: https://support.workspace365.net/en/articles/175643-best-practices app maintenance with a message for a period; https://support.workspace365.net/en/articles/348459-news-ticker for "service disruptions".
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/viva/connections/announcements-viva-connections targeted announcements shown at the top with schedule and end date.

## What changes

- **Announcements.** Authors (administrators and an "announcement editors" group) write an announcement with a title, text, category, target groups, a publish time and an optional end time. It appears in a new "Announcements" widget for the people it targets.
- **Likes and comments.** Readers like and comment on an announcement. Both use Nextcloud's own comments service, so LaunchPad stores no comment text of its own.
- **Follow.** Readers follow categories and get a Nextcloud notification when an announcement in a followed category is published.
- **Preview.** Before publishing, the author sees the announcement exactly as a reader will, as a card or as a banner, and how many people the targeting reaches.
- **Notices.** An announcement of kind "notice" shows as a banner above every targeted person's dashboard for its period, with a level (information or warning). Authors choose whether readers may dismiss it.

## Capabilities

### New capabilities

- `announcements`: authoring, targeting, preview, reactions, comments, follow and notices.

## Impact

- New tables `oc_launchpad_announcements` and `oc_launchpad_announcement_follows`, new `AnnouncementService`, `AnnouncementController`, `AnnouncementNotifyJob`
- `lib/Notification/Notifier.php` (subject `announcement_published`), `appinfo/info.xml` (the job), `lib/actions.seed.json` (new actions)
- New `announcements` widget in `src/constants/widgetRegistry.js`, an editor modal, a banner region in `src/views/WorkspaceApp.vue`

## Out of scope

- Sending the same message to chat and mail (matrix row s-omnichannel, deferred).
- Asking readers to confirm they read it. That is the existing acknowledgement flow (`dashboard-acknowledgements`) on placements, not repeated here.
