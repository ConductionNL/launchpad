# Design: engagement-activity-digest

Read at development `d767c282`.

## Context

- `lib/Activity/Extension.php` is the activity provider (registered at `appinfo/info.xml:182`). Its catalogue (`ALL_EVENTS`, lines 104-119) lists fourteen event types.
- `lib/Activity/ActivityPublisher.php` builds events with `setApp('launchpad')`, `setType($type)`, `setAffectedUser()` and `setSubject($type, ...)` (lines 347-351) and offers `publish()`, `publishToRecipients()`, `publishToGroup()` and `publishGlobal()`; `lib/Activity/DebounceHelper.php` rate-limits fan-out.
- Emitters today: `BulkOperationService.php:949` and `TemplateResyncService.php:867` (`dashboard_updated`), `AcknowledgementService.php:532` (`dashboard_acknowledged`). A grep for the other constants outside `lib/Activity` finds none.
- No `OCP\Activity\ActivitySettings` subclass exists in `lib`. The activity app queues an email only for types whose setting allows mail for that user, so no LaunchPad event reaches the digest today.
- Sharing is `lib/Service/DashboardShareService.php` (share notifications at line 497); publication state lives on `lib/Db/Dashboard.php` (`publicationStatus`, line 464) and is handled in `lib/Service/DashboardService.php`.

## Decisions

### D1: Settings per event type, in one LaunchPad group

Four `ActivitySettings` classes, one per type, all in group `launchpad` ("LaunchPad"):

| type | stream default | mail default |
|---|---|---|
| `dashboard_shared` | on | on |
| `dashboard_published` | on | on |
| `dashboard_updated` | on | off |
| `dashboard_acknowledged` | on | off |

`canChangeStream()` and `canChangeMail()` return true, so each person decides. The classes register under `<activity><settings>` in `appinfo/info.xml`.

### D2: Emit what a digest needs, at the source

- `dashboard_shared`: when `DashboardShareService` creates a share, `publishToRecipients()` to the user or the group's members (the same audience the share notification already resolves).
- `dashboard_published`: when a dashboard moves to `published` (directly, or when a scheduled one is first surfaced), to its target audience through `publishToGroup()` per target group.
- `dashboard_updated`: when a shared or group dashboard is saved, to its audience, at most once per dashboard per 24 hours through `DebounceHelper`.

The author of the change never gets their own event (the publisher already skips the actor for self events).

### D3: The activity app sends the email

LaunchPad sends no mail. The activity app's `DigestSender` and batch settings give hourly, daily or weekly emails, and people manage them where they manage every other Nextcloud digest.

## Declarative-vs-imperative decision

Activity events are Nextcloud platform calls from the services that change state; there is no schema register involved.

## Privacy

Events go only to people who could already see the dashboard. The digest shows the dashboard name and the actor, as the stream does today.

## Test plan

- PHPUnit: each settings class (identifier, group, defaults); `DashboardShareService` emits `dashboard_shared` to the right recipients; publication emits once; updates are debounced to one per day.
- Integration: with the activity app enabled and mail batching on daily, sharing a dashboard with a user queues a mail entry for that user (`oc_activity_mq`).
