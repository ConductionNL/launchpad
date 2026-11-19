---
kind: code
depends_on: []
---

# A daily or weekly email of what changed on your dashboards

## Why

Nextcloud's activity app already sends people an hourly, daily or weekly
email of what happened, and LaunchPad writes its events to the activity stream
(`lib/Activity/ActivityPublisher.php`). None of it reaches that email:
LaunchPad registers no activity settings (`appinfo/info.xml:174-184` declares a
provider only), and the activity app mails only event types a setting allows.
On top of that, of the fourteen events in LaunchPad's catalogue
(`lib/Activity/Extension.php:104-119`) only two are ever emitted: `dashboard_updated`
by bulk operations and template re-sync (`lib/Service/BulkOperationService.php:949`,
`lib/Service/TemplateResyncService.php:867`) and `dashboard_acknowledged`
(`lib/Service/AcknowledgementService.php:532`). A dashboard shared with you or
published to your group leaves no trace in the stream at all.

Matrix row **e-digest** (`openspec/parity/capabilities.json`), "Get a daily or
weekly email summing up what changed", rated `no`, `built.state` `none`.

- Workspace 365, yes: https://support.workspace365.net/en/articles/175650-activity-feed "Daily briefing" and "Weekly briefing" emails.
- Nextcloud dashboard, yes: the shipped activity app sends hourly, daily or weekly activity emails (nextcloud/activity stable35 `lib/Settings/Personal.php:99-106` batch time and `lib/DigestSender.php:185` "Daily activity summary"); it covers Nextcloud activity, not dashboard content.

## What changes

- LaunchPad registers activity settings, so its events can go into the activity app's digest email. Each person picks, in their Nextcloud activity settings, which LaunchPad events they want in the stream and in the email, and how often the email comes.
- LaunchPad emits the events people need in a digest: a dashboard shared with you, a dashboard published to you, and a change to a dashboard shared with you (at most one per dashboard per day).
- Sensible defaults: shared and published go into the email; updates stay in the stream only unless the person turns them on.

## Capabilities

### Modified capabilities

- `activity-feed-integration`: adds activity settings for the digest and the missing emitters.

## Impact

- New `lib/Activity/Settings/*` classes extending `OCP\Activity\ActivitySettings`, registered in `appinfo/info.xml`
- Emit calls in `lib/Service/DashboardShareService.php` (share created) and `lib/Service/DashboardService.php` (publication and updates to shared dashboards), through `ActivityPublisher` and its `DebounceHelper`
- No mailer in LaunchPad: the activity app sends the email.

## Out of scope

- A LaunchPad-designed briefing email with its own layout. The activity app's digest is the one email people already manage.
- A scheduled report of a dashboard's content. That is the open change `scheduled-exports`.
