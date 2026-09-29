# Design: dashboard-edit-lock-ui

## Context (read at launchpad development 35e2b873)

- `lib/Service/DashboardLockService.php` acquires (`:116`), heartbeats (`:199`), releases (`:267`), reads (`:316`) and force-releases (`:348`) a lock. The lock lives 15 minutes from `updatedAt`; there is no stored expiry.
- `DashboardLockApiController` maps a conflict to 409 with `error`, `code` and the holder's display name, and strips the holder's user id on purpose (comment M2). A caller without view access gets 403.
- Routes: `POST`, `PUT`, `DELETE`, `GET /api/dashboards/{uuid}/lock` and `POST .../lock/force-release` (`appinfo/routes.php:159-175`).
- `src/views/Views.vue:1111` `toggleEditMode()` flips `isEditMode` and nothing else. `src/services/api.js` has no lock functions.

## Decisions

### D1. One composable owns the lock

`src/composables/useDashboardLock.js` exposes `acquire(uuid)`, `release()`, `state` (`none`, `held`, `blocked`), `holderName` and `since`. `Views.vue` calls `acquire` inside `toggleEditMode()` before it sets `isEditMode = true`. A 409 leaves `isEditMode` false and sets `blocked`. A 403 or a network failure also leaves the page read-only and says so; it never falls through to editing without a lock.

### D2. Heartbeat every five minutes

The TTL is 15 minutes, so a five-minute `PUT` survives two missed beats. A `PUT` that answers 404 (the lock expired and someone else took it) drops the page back to read-only with the banner, and unsaved edits stay in the store so the person can copy them.

### D3. Release on every exit path

`release()` runs on leaving edit mode, after a successful save, and on `pagehide` with `fetch(..., {keepalive: true})`, because a `DELETE` cannot use `sendBeacon`. A missed release costs at most 15 minutes, which is the design of the service.

### D4. The banner, not a modal

`src/components/Workspace/EditLockBanner.vue` renders as an `NcNoteCard` above the grid with the holder's display name and a relative time. It carries `role="status"`. The admin "Take over" button opens a confirmation `NcDialog` in `src/dialogs/ForceReleaseLockDialog.vue` (modal isolation rule) and calls force-release; on success the page acquires the lock itself.

### D5. Personal dashboards take the same path

A personal dashboard has one editor, so its lock is always free. Using one code path costs one request and avoids a second branch to test.

## Declarative-vs-imperative decision

The lock state is runtime state of a page, not configuration. Nothing here belongs in a manifest or a register schema.

