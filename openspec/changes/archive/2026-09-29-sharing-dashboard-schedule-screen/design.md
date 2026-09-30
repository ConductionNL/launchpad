# Design: sharing-dashboard-schedule-screen

## Context (read at launchpad development 106fd259)

- `DashboardApiController::schedule` takes `publishAt`; `DashboardService` refuses a past time with `ERR_SCHEDULE_PAST_DATE`.
- The list filter at `DashboardService.php:1599` treats a `scheduled` row whose `publishAt` has passed as published.
- The store actions exist and return the updated dashboard.

## Decisions

### D1. One dialog for both times
Go-live and take-down live in one dialog in `src/dialogs/`, so the person sees the whole window a dashboard is visible. Both are optional except that "Schedule" needs at least one.

### D2. Take-down reuses the read filter
`unpublishAt` is a nullable column next to `publishAt`. The read filter treats a published dashboard whose `unpublishAt` has passed as unpublished. No background job: the state is computed at read time, as `publishAt` already is.

### D3. Only managers see the actions
The menu items appear when the dashboard's `canEdit` (or admin) flag is true, the same flag the edit action uses. The server check stays the authority.
