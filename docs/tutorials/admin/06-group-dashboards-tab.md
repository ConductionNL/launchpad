---
sidebar_position: 6
title: Manage group dashboards from the Beheer tab
description: Create, rename, set-default and delete group-shared dashboards directly from the LaunchPad admin settings.
---

# Manage group dashboards from the Beheer tab

The **Group dashboards** tab in the LaunchPad admin settings page lets you manage every group-shared dashboard in one place. Earlier tutorials covered the per-dashboard cog-menu flow ([admin templates](02-admin-templates.md), [group defaults](03-group-defaults.md)); the Beheer tab is the **admin-centric** entry-point that lists groups first, dashboards second.

## Goal

Find the Group dashboards tab, create a new dashboard for a Nextcloud group, mark it as default, and learn how the last-in-group delete guard protects against accidental data loss.

## Prerequisites

- You are a Nextcloud admin.
- LaunchPad ≥ 0.2.x is installed (the tab is shipped by the `admin-group-management` openspec change).
- At least one Nextcloud group exists (admins-only is fine; you'll also see the synthetic `default` row).

## Where to find it

Open the LaunchPad admin settings via **Settings → Administration → LaunchPad** and switch to the **Group dashboards** tab. The strip shows every IA area; **Group dashboards** is the rightmost tab.

The tab renders:

- A header row per Nextcloud group.
- A synthetic **Default group** row at the top — this controls the **org-wide default dashboard set** that members of no specific group still get.
- A **count badge** next to each group name — the number of dashboards currently shared with that group.

## Steps

### 1. Create a group dashboard

Click **Create group dashboard** on the row for the group you want. The Create modal opens with three fields:

- **Name** (required, 2-64 characters).
- **Icon** (Material Design name; defaults to `view-dashboard`).
- **Layout template** (Blank / KPI overview / People & teams).

Optionally tick **Set as the group default dashboard** to immediately promote the new dashboard. The request is a single `POST /api/dashboards/group/<groupId>` and the new row appears in the count badge immediately.

### 2. Open the manage modal

Click **Manage** on a group row to open the full dashboard list for that group. Each row exposes three actions:

- **Set as default** — promotes the dashboard to the group's default. The previous default is demoted in the same transaction (REQ-DASH-015).
- **Rename** — inline prompt that PUTs the new name; reflects immediately in the count badge.
- **Delete** — confirm and delete. Subject to the **last-in-group guard** below.

The currently-default dashboard wears a green **Default** badge.

### 3. Last-in-group delete guard

LaunchPad never lets you delete the **only** dashboard a group has — that would leave members staring at an empty sidebar. The backend returns HTTP 400 with `error: "last_group_dashboard"` and the frontend toasts **Last dashboard cannot be deleted**. Add a replacement dashboard first, then retry.

## API summary

| Action       | Endpoint                                                |
|--------------|---------------------------------------------------------|
| List         | `GET /api/dashboards/group/{groupId}`                   |
| Create       | `POST /api/dashboards/group/{groupId}`                  |
| Update       | `PUT /api/dashboards/group/{groupId}/{uuid}`            |
| Delete       | `DELETE /api/dashboards/group/{groupId}/{uuid}`         |
| Set default  | `POST /api/dashboards/group/{groupId}/default`          |

The Beheer tab uses these exact endpoints — there are no new routes specific to the tab, by design. The store slice `src/stores/groupDashboards.js` is the single client-side wrapper; the backend behaviour is unchanged from the [multi-scope-dashboards](../../architecture.md) change that introduced them.

## Permissions

The tab is admin-only and gated by the same `#[AuthorizedAdminSetting]` posture as the rest of the LaunchPad admin settings page. Non-admins attempting the underlying endpoints get HTTP 403.

## Related

- [Admin templates](02-admin-templates.md) — push a layout into each user's personal space.
- [Group defaults](03-group-defaults.md) — per-dashboard cog-menu flow for marking a default.
