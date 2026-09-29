---
kind: code
depends_on: []
---

# Arrange dashboards in a tree of parent and child pages and navigate it

## Why

`DashboardTreeService` (`lib/Service/DashboardTreeService.php`) validates parents (no cycles, no self-parent), keeps slugs unique among siblings, computes breadcrumbs and resolves a slug path, and `GET /api/dashboards/tree` returns the tree filtered to what the viewer may see. The store action `loadDashboardTree` (`src/stores/dashboard.js:1108`) has no caller outside the stores, so no screen shows a tree and no form sets a parent. The row is in the core area `dashboards`, so the screens are built.

This change covers 1 row(s) of the parity matrix (`openspec/parity/capabilities.json`), decided `build` on 2026-09-28.

**dash-tree** (matrix `launchpad`, area `dashboards`), "Arrange dashboards in a tree of parent and child pages." Rated `no` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.

## What changes

- The dashboard switcher shows dashboards as an expandable tree when any dashboard has a parent, and stays flat when none does.
- A page under a parent shows a breadcrumb trail above the grid.
- The create and rename dialogs get a "Parent dashboard" select (with an input label) and a slug field; invalid choices are refused with the service's message.
- A dashboard opens by its path, such as `/apps/launchpad/d/hr/onboarding`, using the resolver that already exists.

## Capabilities

### Modified capabilities
- `dashboards`: adds the tree navigation, breadcrumb and parent selection requirements.
