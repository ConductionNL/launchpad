# Design: engagement-dashboard-reactions-bar

## Context (read at launchpad development 106fd259)

- `GET /api/dashboards/{uuid}/reactions` returns the summary; `POST` adds, `DELETE .../reactions/{emoji}` removes, `GET .../reactions/{emoji}/users` lists reactors.
- A disabled dashboard or global toggle makes the add call refuse (REQ-RXN-005, REQ-RXN-006).

## Decisions

### D1. A component under the title, not a widget
Reactions belong to the dashboard, so `DashboardReactions.vue` renders under the title in view mode. It is not a placement and cannot be moved.

### D2. Remove goes through a new store action
The store has add and summary; this change adds `removeReaction` calling the existing DELETE route. The toggle picks add or remove from the summary's `reactedByMe`.
