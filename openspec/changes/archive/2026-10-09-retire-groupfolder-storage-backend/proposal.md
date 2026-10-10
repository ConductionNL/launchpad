# Proposal — retire the groupfolder-storage-backend capability

## Why

`gate-57` (orphaned-write-capability) reports exactly one finding on launchpad:

```
lib/Service/DashboardService.php:3189 method=writeDashboardContent rule=orphaned-write-capability class=DashboardService
```

It is a true positive, and the real scope is larger than one method. Task 5 of
the original change — the wiring that would have routed dashboard
get/create/update/delete through the storage factory — was ticked in `tasks.md`
but never performed. Everything around it landed, so the capability looks
complete from every angle except the one that matters.

Measured on `origin/development` at `e24334b6`, each claim taken with a positive
control (the same search resolves callers for `getUserDashboards` and
`findByUuid`):

| symbol | callers outside its own definition |
|---|---|
| `DashboardService::writeDashboardContent()` | 0 |
| `DashboardService::readDashboardContent()` | 0 |
| `DashboardService::deleteDashboardContent()` | 0 |
| `DashboardContentStorageFactory::getStorage()` | 0 (only the three above) |
| `DashboardApiController::storageUnavailableResponse()` | 0 |
| `DashboardMapper::findAll()` | 1 — the migration command, itself retired here |

Consequences that follow: nothing ever populated
`oc_launchpad_dashboards.content`, so `Dashboard::jsonSerialize()` emitted a
`content` key that was always `null`; `launchpad.content_storage` was a no-op
setting; and `launchpad:storage:migrate-to-groupfolder` migrated nothing.

## What

Retire the capability (path 2 of the two options in launchpad#87), rather than
complete it. Completing it needs an ADR-level decision about whether the
`content` blob or the `WidgetPlacement` rows are the source of truth for a
dashboard's layout — the spec asserts the blob, the code has used the rows
since long before this capability existed, and wiring the blob in as a second
source of truth invites divergence. Nobody is using the capability, so retiring
it costs nothing and removes that risk permanently.

## Scope boundary

The `launchpad.content_storage` setting and setup-wizard step 2 are **retained**
and tracked separately on launchpad#87. They belong to the `setup-wizard`
capability, which *writes* the setting; this capability was the *reader*. The
setting was already inert before this change, so removing the reader does not
make it worse, and removing the wizard step changes a different spec and
deserves its own review.
