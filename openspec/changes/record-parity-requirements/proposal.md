---
kind: spec
depends_on: []
---

# Requirements for five parity rows the specs did not yet say

## Why

Every row in `openspec/parity/capabilities.json` records the requirement that covers it. Five rows had no requirement whose scenarios say what the row says:

- **build-picker**, "Pick a widget from a searchable list of everything you may add". `src/modals/WidgetPickerModal.vue:18-25` has a search box and `filteredWidgets` (line 163) narrows the list by title. The widgets spec (REQ-WDG-010) describes a plain type select.
- **d-auto-refresh**, "Refresh widget content on its own without reloading the page". `src/components/WidgetRenderer.vue:478-481` arms a timer from the widget's `reloadInterval`. The widgets spec only says the formatter copies the interval.
- **g-health**, "Check the app's health from the admin page". `src/components/admin/HealthPanel.vue` sits on the Operations tab (`src/components/admin/tabs/OperationsTab.vue:9`). The metrics spec only describes the endpoint.
- **s-policy**, "Decide who may share dashboards, and with whom". The Sharing tab (`src/components/admin/DashboardSharingPolicy.vue`) sets a default share level and forced share groups. Nothing limits who may share or with whom.
- **d-tour**, "Take a guided tour of the dashboard and the admin area on first use". REQ-SHELL-010 covers the Workspace tour and says the tour does not visit the admin settings.

## What changes

- `widgets`: the picker narrows its list as you type, and a Nextcloud widget with a reload interval refreshes itself.
- `prometheus-metrics`: the admin page shows the health check result.
- `dashboard-sharing`: administrators limit who may share and with whom. Not built yet.
- `runtime-shell`: the admin area gets its own first-visit tour. Not built yet.

## Capabilities

### Modified capabilities

- `widgets`, `prometheus-metrics`, `dashboard-sharing`, `runtime-shell`

## Impact

- The first three requirements describe code on `development`; they only add the spec text.
- s-policy and d-tour move from `built` to `specified` in the parity matrix until the code exists.
