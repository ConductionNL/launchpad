---
sidebar_position: 7
title: Make LaunchPad the default app
description: Land every user on LaunchPad after login and disable the built-in Nextcloud Dashboard.
---

# Make LaunchPad the default app

By default Nextcloud opens the built-in **Dashboard** app after login and when a user clicks the logo. You can point that landing slot at LaunchPad instead, so every user starts on their LaunchPad dashboards — and, optionally, retire the stock Dashboard app entirely so the two don't compete.

This is configured in Nextcloud core, not in LaunchPad's own admin settings. LaunchPad registers with the app id **`mydash`**, which is the value Nextcloud uses everywhere it refers to the app.

## Goal

Set LaunchPad as the global default app so users land on it after login, and disable the native Dashboard app.

## Prerequisites

- You must be a Nextcloud admin.
- LaunchPad is installed and enabled for the users who should land on it.

## Steps

### 1. Open the navigation bar settings

Settings menu (avatar) → **Administration settings** → **Theming** in the left nav, then scroll to the **Navigation bar settings** section.

### 2. Enable a custom default app

Turn on **Use custom default app**. This reveals the **Global default app** selector and the **Default app priority** list.

> The **default app** is the app that opens after login or when the logo in the menu is clicked.

### 3. Select LaunchPad as the global default

In the **Global default app** dropdown, pick **LaunchPad**. It moves to the top of the **Default app priority** list. The change persists immediately — there is no Save button.

The priority list is a fallback chain: if the top app is not enabled for a given user, Nextcloud falls through to the next one. Keep at least one app every user can reach (e.g. **Files**) below LaunchPad so no one lands on a 403.

### 4. (Optional) Disable the native Dashboard app

With LaunchPad as the landing page, the stock Dashboard app is usually redundant. Disable it so it disappears from the top navigation and stops being a competing default:

```bash
occ app:disable dashboard
```

To re-enable it later:

```bash
occ app:enable dashboard
```

Disabling Dashboard does **not** touch LaunchPad data — LaunchPad is a separate app (`mydash`) with its own dashboards, widgets, and storage.

### 5. Verify

Log in as a non-admin user (or open a fresh private window). After authentication the browser should land on LaunchPad. Clicking the Nextcloud logo in the top-left should return to LaunchPad as well.

## Doing it from the command line

The same settings are plain `occ` config, which is handy for scripted or air-gapped instances:

```bash
# Make LaunchPad the default landing app for everyone
occ config:system:set defaultapp --value mydash

# Disable the built-in Dashboard
occ app:disable dashboard
```

`defaultapp` accepts a comma-separated fallback chain — the equivalent of the priority list — e.g. `mydash,files`.

## Behaviour notes

- **App id vs. display name.** The setting stores the app id **`mydash`**, even though the UI and menu label it **LaunchPad**. If you script this, use `mydash`.
- **Per-user override.** Users can still set their own personal default app under **Personal settings → Appearance and accessibility** unless you enforce the value. The global default only sets the instance-wide fallback.
- **Per-user landing dashboard.** Which LaunchPad dashboard a user lands on is separate from this setting — see [Set a default dashboard](../user/07-set-default.md) (user) and [Mark a group's default dashboard](03-group-defaults.md) (admin).
- **Logo click.** The default app also governs where the Nextcloud logo links, so once set, the logo becomes a quick "home" button to LaunchPad.

## Common issues

| Symptom | Fix |
|---|---|
| Users still land on Dashboard | Confirm **Use custom default app** is on and LaunchPad is top of the priority list; clear the per-user override if one is set. |
| Some users land on a 403 / blank | LaunchPad isn't enabled for that user's groups, and no reachable fallback exists. Add **Files** below LaunchPad in the priority list. |
| `occ` says `defaultapp` unchanged | Apache cache. `docker exec nextcloud apache2ctl graceful` to refresh. |
| Dashboard tile still shows after disable | Hard-refresh the browser; the top-nav app list is cached client-side. |

## Reference

- [Admin settings feature reference](../../features/admin-settings.md)
- [Set a default dashboard (user)](../user/07-set-default.md)
- [Mark a group's default dashboard](03-group-defaults.md)
