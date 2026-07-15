---
sidebar_position: 6
title: Remove a widget
description: Take a widget off the dashboard without deleting the dashboard itself.
---

# Remove a widget

Removing a widget deletes its placement row (`oc_launchpad_widget_placements`) but doesn't touch the dashboard or the widget's underlying data.

## Goal

Remove one widget from a dashboard.

## Prerequisites

- A dashboard you can edit.
- The widget must not be marked **compulsory** by an admin template (compulsory widgets can only be removed by an admin).

## Steps

### 1. Open the widget's menu

In edit mode, click the placement's **Widget menu** (⋯/cog) button in its top-right corner.

![Widget menu](/screenshots/tutorials/user/05-context-menu.png)

### 2. Click **Delete widget**

The menu auto-closes and the placement disappears from the grid. The DELETE call fires immediately; there is no undo.

![After remove](/screenshots/tutorials/user/06-after-remove.png)

:::caution
The delete is destructive. If you're unsure, [edit the appearance](05-edit-content.md) and toggle **Show title** off — it de-emphasises the placement without deleting it.
:::

## Verification

- The widget is gone from the grid.
- Reload — it stays gone (the row was deleted server-side).

## Common issues

| Symptom | Fix |
|---|---|
| **Delete widget** is greyed out | The widget is `isCompulsory=1` on this dashboard (admin-pinned). Ask your admin to lift it. |
| Deleting throws "permission denied" | Your permission level on the dashboard is `view_only`. |

## Reference

- [Widgets feature reference](../../features/widgets.md)
- [Permissions reference](../../features/permissions.md)
