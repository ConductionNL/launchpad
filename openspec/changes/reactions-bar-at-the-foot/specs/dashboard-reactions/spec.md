# Delta: dashboard-reactions

## MODIFIED Requirements

### Requirement: REQ-RXN-010 A viewer can react from the dashboard

In view mode a reactions bar MUST show at the foot of the dashboard, under the last widget and over the full width, as a card that asks "What do you think of this dashboard?" before each emoji and its count and names who last changed the dashboard and when after them. It MUST mark the viewer's own reactions and toggle the viewer's reaction on click. It MUST offer only the allowed emoji, and MUST be absent when reactions are disabled globally or for the dashboard.

#### Scenario: Add and remove

- **GIVEN** Pieter views the dashboard "Team" with reactions on
- **WHEN** he clicks the thumbs up, then clicks it again
- **THEN** the count rises by one and is marked as his, then returns to what it was

#### Scenario: Reactions off

- **GIVEN** the dashboard "Board" has reactions switched off
- **WHEN** Pieter opens it
- **THEN** no reactions bar is shown

## Non-Functional Requirements

- **Performance**: `GET /api/dashboards/{uuid}/reactions` MUST return within 200ms. `GET /api/dashboards/{uuid}/reactions/{emoji}/users` with 100-item result MUST return within 300ms (pagination used if > 100).
- **Data integrity**: The unique constraint `(dashboardUuid, userId, emoji)` MUST be enforced at the database level to prevent duplicate reactions.
- **Idempotency**: POST add and DELETE remove MUST be fully idempotent (same request sent twice produces the same result without error).
- **Localization**: All error messages (`"Emoji not allowed"`, `"Reactions are disabled"`, etc.) MUST support English and Dutch per i18n requirements.
- **Cascade safety**: Deleting a dashboard MUST reliably cascade-delete all reactions via `ReactionsListener` subscribing to `DashboardDeletedEvent`.

### Current Implementation Status

**Implemented** — files of record:

- `lib/Migration/Version001014Date20260502120000.php` — schema (table + index + per-dashboard column)
- `lib/Db/DashboardReaction.php` + `lib/Db/DashboardReactionMapper.php`
- `lib/Db/Dashboard.php` (`reactionsEnabled` column)
- `lib/Service/ReactionService.php` + `PermissionDeniedException` + `ReactionsDisabledException`
- `lib/Listener/ReactionsListener.php` (DashboardDeletedEvent → cascade delete)
- `lib/Controller/DashboardReactionApiController.php`
- `appinfo/routes.php` (4 routes)
- `src/services/api.js` + `src/stores/dashboard.js` (4 actions)
- `l10n/{en,nl}.{json,js}` (6 strings each)
