---
kind: code
depends_on: []
---

# React to a dashboard with an emoji

## Why

Ruben decided on 29 Sep 2026 (DECISIONS row 14) to build the screen for this backend. `ReactionService` (`lib/Service/ReactionService.php:91`) adds, removes and summarises reactions, honours the global and per-dashboard toggles and the allowed emoji list, and the routes exist (`appinfo/routes.php:230-247`). The store actions `addReaction` and `fetchReactionsSummary` (`src/stores/dashboard.js:1229-1292`) have no component caller.

This change covers 1 row of the parity matrix, reversed from `decided-no` to `build` on 2026-09-29.

**e-react** (matrix `launchpad`, area `engagement`), "React to a dashboard with an emoji." `built.state` moves to `building`.

## What changes

- Below the dashboard title a reactions bar shows each emoji with its count; the person's own reactions are marked.
- Clicking an emoji toggles the person's reaction; "Add reaction" offers the allowed emoji only.
- Hovering or focusing a count lists who reacted.
- The bar is absent when reactions are off globally or for the dashboard.

## Capabilities

### Modified capabilities
- `dashboard-reactions`: adds the reactions bar.
