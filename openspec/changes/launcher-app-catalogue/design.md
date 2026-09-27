# Design: launcher-app-catalogue

Read at development `d767c282`.

## Context

- The add flow is `src/modals/WidgetPickerModal.vue` (search box at line 17, widget list at line 33); the archived `2026-05-03-unified-add-widget-flow` made it the single place to add widgets and tiles. The archived `2026-06-21-deprecate-catalog` removed a separate read-only catalogue region because adding belongs in this modal.
- Tiles are created through `WidgetService::addTileFromArray()` (`lib/Service/WidgetService.php:192`), which the tile endpoint calls after `ActionAuthService::requireAction()` and `PermissionService::canAddWidget()` (`lib/Controller/WidgetApiController.php:480-509`).
- Actions live in `lib/actions.seed.json` (`widget.add-tile` is `["admin", "@all"]`).
- Nextcloud notifications go through `lib/Notification/Notifier.php`, which already renders `dashboard_shared`, `dashboard_ownership_transferred` and `dashboard_template_resynced` (lines 135-151).
- Admin pages are tabs under `src/components/admin/tabs/` (for example `OperationsTab.vue`, `SharingTab.vue`).
- LaunchPad owns its tables through Nextcloud migrations (`lib/Migration/*TableBuilder.php` plus `Version*` classes, latest `Version002010Date20260918184500`).

## Data

`oc_launchpad_catalogue_apps`: id, uuid, name, description, icon, icon_type, link_type (`app` or `url`), link_value, target_groups (JSON list, empty means everyone), sort_order, created_at, updated_at.

`oc_launchpad_app_requests`: id, uuid, requested_by, app_name, purpose, status (`open`, `approved`, `declined`), decided_by, decision_note, catalogue_app_id (nullable), created_at, decided_at.

## Decisions

### D1: The catalogue lives in the add flow

`WidgetPickerModal` gains a "From the app catalogue" section above the widget list, filtered by the same search box. Picking an entry calls the existing tile creation with the entry's fields, so tiles from the catalogue are ordinary tiles afterwards. The catalogue entry's later edits do not change tiles already placed.

### D2: Visibility by group, checked on the server

`GET /api/catalogue` returns only entries whose `target_groups` is empty or intersects the caller's groups (`IGroupManager::getUserGroupIds()`). The client never filters for security.

### D3: Requests are notifications, not a workflow engine

`POST /api/catalogue/requests` stores the request and sends a notification with subject `app_requested` to every administrator and to members of an optional `catalogue_manager_group` admin setting. Approving creates the catalogue entry (the admin form opens prefilled with the requested name) and sends `app_request_decided` to the requester; declining requires a note and sends the same subject with the note. A user may have at most 10 open requests, which stops floods.

### D4: Actions

New seeded actions: `catalogue.list` and `catalogue.request` (`["admin", "@all"]`), `catalogue.manage` and `catalogue.decide` (`["admin"]`), enforced with `ActionAuthService::requireAction()` in each controller method. Admin methods use `#[AuthorizedAdminSetting]` rather than `#[NoAdminRequired]` with an in-body check (see the open change `fix-group-dashboard-admin-auth-attribute`).

## Declarative-vs-imperative decision

LaunchPad keeps its own tables and consumes OpenRegister only at runtime (`launchpad-adopt-or-abstractions`), so the catalogue and requests are LaunchPad tables with a service. The notifications use the existing Nextcloud notifier.

## Seed data

Demo installs (`DemoShowcasesService`) get four entries a municipality would recognise: "Zaaksysteem" (`https://zaken.example.nl`), "Personeelsportaal" (`https://hr.example.nl`), "Bestanden" (app `files`) and "Agenda" (app `calendar`), all visible to everyone.

## Test plan

- PHPUnit: group filtering, request limit, approve and decline paths, notifications sent to admins and the requester, admin attribute on admin routes.
- Vitest: the catalogue section in the picker, the request form, the admin request list.
- Playwright: an admin adds "Zaaksysteem" for group "Burgerzaken", a member adds it as a tile, a non-member does not see it; a user requests "Kaartviewer", the admin declines with a note, the user sees the notification.
