# Design: integrations-office-tiles

Read at development `d767c282`.

## Context

- File creation today is the link-button widget's `POST /api/files/create` (`appinfo/routes.php:356`, `FileController::createFile()`), specified by `openspec/specs/link-button-widget/spec.md` REQ-LBN-004: it writes given content to a fixed name, OVERWRITES an existing file (point 5) and allows extensions from `link_create_file_extensions`.
- Nextcloud core's `OCP\Files\Template\ITemplateManager` lists the file creators office apps register (`listCreators()`, each with an id, label, extension, mimetypes and a blank template) and creates a file from one (`createFromTemplate($filePath, $templateId, $templateType)`). The Files app's "New" menu uses the same API, so Nextcloud Office and ONLYOFFICE creators appear there and here alike.
- Tiles are placements created through `WidgetService::addTileFromArray()`; containers hold tiles in `content.placements`.
- `IAppManager::isEnabledForUser()` is the optional-app check LaunchPad already uses (`lib/Service/WeatherService.php:418`).

## Decisions

### D1: The pack is computed per user

`OfficeTilesService::packFor(userId)` returns tiles for: every creator from `ITemplateManager::listCreators()` (as `create` tiles named by the creator label, for example "New document"), then Files, Mail, Calendar, Contacts and Talk for the apps enabled for that user. Nothing is listed for an app the user cannot open.

### D2: A `create` link type, not an overwrite

A new tile link type `create` stores the creator id. Clicking opens a small dialog: name (prefilled "New document"), folder (default `/Documents`, created if missing). `POST /api/office-tiles/create` checks the name with the REQ-LBN-004 filename rules, adds the creator's extension, picks a free name (`Name (2).docx`) when the file exists, calls `createFromTemplate()` with the creator's blank template, and returns the file id. The browser then opens the file in its editor through the Files app (`/apps/files/?openfile=<id>`).

### D3: One click places a container

Choosing "Office tiles" in the add flow places one container titled "Office" holding the pack's tiles in a 2 by 2 grid each, appended at the bottom; it counts as one placement toward the dashboard quota. Templates can carry the pack like any other container.

### D4: Actions and permissions

`office-tiles.list` and `office-tiles.create` are seeded `["admin", "@all"]`. Creating a file needs nothing beyond the person's own storage; placing the pack needs `canAddWidget()`.

## Declarative-vs-imperative decision

Platform calls from a small service; no schema register involved.

## Test plan

- PHPUnit: the pack with and without creators and apps; free-name selection; filename rules; the creator id must be one `listCreators()` returns.
- Vitest: the pack in the picker, the `create` tile dialog.
- Playwright with Nextcloud Office enabled: place the pack, click "New document", name it "Notulen", and the editor opens `Documents/Notulen.docx`; doing it again creates `Notulen (2).docx`.
