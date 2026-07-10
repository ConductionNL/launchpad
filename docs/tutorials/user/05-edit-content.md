---
sidebar_position: 5
title: Edit widget content & style
description: Change a widget's content, colours, custom title, or border without removing it.
---

# Edit widget content & style

Each placement carries two layers of configuration, both edited from the **same** modal:

- **Content** — type-specific fields (text body, link URL, folder path, …). Changes the widget's payload.
- **Style / appearance** — show-title toggle, custom title override, background, and custom icon. Cosmetic.

Both are editable post-add without removing the widget. Content and style used to be separate menu entries; they are now one unified **Edit widget** modal with a **Content** area and an **Appearance** section.

## Goal

Edit a widget you already added — both its content and its appearance.

## Prerequisites

- A dashboard you can edit, with at least one widget on it.

## Steps

### 1. Enter edit mode and open the widget's menu

Cog menu → **Edit dashboard**. Each placement then shows a **Widget menu** (⋯/cog) button in its top-right corner. Click it:

![Widget menu](/screenshots/tutorials/user/05-context-menu.png)

Options:
- **Edit widget** — opens the unified configuration + appearance form (same modal as during add).
- **Delete widget** — see [Remove a widget](06-remove-widget.md).

### 2. Edit the content

Pick **Edit widget**. The same **Add Widget** modal you used to add it reopens, pre-filled with the current placement's content. Change the type-specific fields at the top (label, URL, folder, colours, …).

![Edit content modal](/screenshots/tutorials/user/05-edit-content.png)

### 3. Edit the appearance

Scroll to the **Appearance** section of the same modal:

- **Show title** — toggle the title bar on/off.
- **Custom title** — overrides the widget's default title (leave blank for default).
- **Background** — Default, or a custom colour.
- **Icon** — pick from the Material Design Icons catalogue, the NL Design set (when the `nldesign` app is enabled), or **Upload** your own; leave empty for the default.

![Appearance section](/screenshots/tutorials/user/05-style-editor.png)

The appearance settings persist as a JSON blob in `placement.styleConfig`; they don't touch the widget's content.

### 4. Save

The modal closes on **Save** and the change is reflected immediately.

## Verification

- Reload the page. Content and appearance changes are still applied.
- The widget header reflects any custom title; the widget background reflects any custom colour.

## Common issues

| Symptom | Fix |
|---|---|
| The type-specific fields are absent | The widget type is renderer-only (no configuration form). You can still change the Appearance section, or remove and re-add. |
| The NL Design icon set is missing from the Icon picker | The `nldesign` app is not enabled on this instance — the pack is hidden by design (MDI + Upload still work). |
| Title row gone after toggling **Show title** | Re-open Edit and toggle it back on, OR set a custom title. |

## Reference

- [Widgets feature reference](../../features/widgets.md)
