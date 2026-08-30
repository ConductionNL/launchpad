---
status: withdrawn
---

# groupfolder-storage-backend Specification (WITHDRAWN)

## Purpose

This capability is **withdrawn**. It was never wired into the application, and
the decision recorded in launchpad#87 is to retire it rather than complete it.

The capability intended to abstract dashboard content storage behind a
read/write/delete interface so an operator could choose between a database
backend and a Nextcloud GroupFolder backend. The interface, both backends, the
factory, the admin setting, the migration command and the toggle command were
all written. **The wiring that would have made any of them run was not.**

## Why it was withdrawn rather than completed

Task 5 of the original change ("wire `DashboardContentStorageFactory` into
`DashboardService` so get/create/update/delete route through the active
backend") was ticked in `tasks.md` but never performed. The measured
consequence, at the time of withdrawal:

- `DashboardService::readDashboardContent()`, `writeDashboardContent()` and
  `deleteDashboardContent()` each had **zero callers** anywhere in the
  repository. `DashboardContentStorageFactory::getStorage()` was called only by
  those three methods, so the entire backend tree was unreachable.
- Nothing ever populated `oc_launchpad_dashboards.content`. Widgets live in
  `WidgetPlacement` rows and always have; `Dashboard::jsonSerialize()` emitted
  a `content` key that was always `null`.
- `launchpad:storage:migrate-to-groupfolder` read a column that was always
  null, decoded it, and wrote the result to a GroupFolder — migrating nothing.
- `DashboardApiController::storageUnavailableResponse()`, the HTTP 503 path for
  a storage failure, also had zero callers.

Completing it instead would have required deciding whether the `content` blob
or the `WidgetPlacement` rows are the source of truth for a dashboard's layout.
The original spec asserted the blob (REQ-GFSB-002); the code has used the rows
since long before this capability existed. Wiring the blob in as a second
source of truth invites divergence between the two, and that is an
architectural decision that belongs in an ADR, not in a quality sweep. The
capability was not in use by anyone, so retiring it costs nothing and removes
the divergence risk permanently.

## What was removed

`lib/Service/DashboardContentStorage/` (interface, both backends, three
exception classes), `lib/Service/DashboardContentStorageFactory.php`, the three
`DashboardService` facade methods and the factory constructor parameter,
`DashboardApiController::storageUnavailableResponse()`,
`DashboardMapper::findAll()` (whose only caller was the migration command),
`lib/Command/MigrateStorageToGroupFolder.php`,
`lib/Command/ToggleStorageSetting.php` and their `<command>` registrations, the
`content` and `locale` properties on the `Dashboard` entity together with
`decodeContent()` and their `jsonSerialize()` keys, and the four PHPUnit
classes that covered the removed tree.

The `content` and `locale` columns are dropped by
`lib/Migration/Version002009Date20260811000000.php`. Dropping them is safe
because nothing ever wrote to them: the only writer,
`DbContentStorage::write()`, was reachable only through the factory, and the
factory was reachable only through the three orphaned facade methods.

## Deliberately NOT removed, and why

The `launchpad.content_storage` admin setting, `AdminSettingKey::CONTENT_STORAGE`,
`SetupWizardService::{getContentStorage,setContentStorage,hasGroupfolderApp}`,
`AdminController::setWizardStorage`, its route, and step 2 of the setup wizard
are **retained**. They belong to the `setup-wizard` capability, which *writes*
the setting; this capability was the *reader*. The setting was already inert
before this change — nothing read it, because the readers were orphaned — so
removing the reader does not make it worse.

Retiring the wizard step is a separate, reviewable decision that changes the
`setup-wizard` spec, and it is tracked on launchpad#87 rather than folded into
this retirement. Anyone completing that follow-up should note that
`tools/spec-annotations-allowlist.txt` carries entries for those three
`SetupWizardService` methods and for `AdminController::setWizardStorage`.

`lib/Migration/Version002001Date20260603000000.php` and
`DashboardTableBuilder::addContentStorageColumns()` are also retained: a shipped
migration is part of the version ledger and deleting it would desynchronise
instances that have already run it. The new migration drops what it added.

## Requirements

None. This capability has no requirements; it is withdrawn. Its original
requirements REQ-GFSB-001 through REQ-GFSB-010 and their 35 scenarios are
preserved in the archived change at
`openspec/changes/archive/2026-06-14-groupfolder-storage-backend/`, and the
`- [x]` tick on Task 5 there is corrected to `- [ ]` with a note, because the
project record asserted a wiring that did not exist.
