# Quick search

A search bar that filters the tiles on the current dashboard as you type,
and opens the one you pick — without reaching for the mouse.

## It is a widget, not page furniture

Quick search used to be part of the page chrome: LaunchPad rendered a
search bar above the grid on every dashboard, whether anyone wanted one
there or not.

It is now the **`search` widget type**. You place it like any other widget,
which means you choose whether a dashboard has one at all, where it sits,
and how wide it is.

> **Upgrading?** Existing dashboards do **not** get a search widget
> automatically, so the bar disappears from them after this upgrade. To get
> it back, edit the dashboard, choose **Add widget → Search**, and put it
> where you want it — most people place it full-width across the top row,
> which is where the old bar was.

## Using it

| Key | Does |
|---|---|
| `/` | Focus the search input from anywhere on the page |
| `Ctrl`+`K` / `Cmd`+`K` | The same, and suppresses the browser's own shortcut |
| `↑` / `↓` | Move through the matches |
| `Enter` | Open the selected tile, honouring its configured link target |
| `Esc` | Clear the query, undim the grid, return focus to the tiles |

Typing narrows the grid by **de-emphasising** non-matching tiles rather
than removing them, so the layout never reflows underneath you while you
type. Matches rank prefix first, then mid-string, then subsequence.

If a dashboard carries more than one search widget, the first one takes the
`/` and `Ctrl`+`K` shortcut — two inputs cannot both hold focus. Remove it
and the next one takes over. A dashboard with no search widget simply has
no shortcut.

## Configuration

Two settings on the widget itself:

**Placeholder text** — leave empty for the default, which advertises the
two shortcuts.

**When nothing matches** — what happens when the query matches no tile:

| Option | Behaviour |
|---|---|
| Use the administrator setting | Inherit the instance-wide default (the shipped default) |
| Show "no results" only | Stay on the dashboard and announce the empty result |
| Hand off to Nextcloud search | Pass the query to Nextcloud's own unified search |
| Open a web search | Open a new tab using an `https` URL template containing `{query}` |

The instance-wide default is the `quicksearch_fallback_target` app config,
set in LaunchPad's admin settings. A widget that inherits it follows
whatever the administrator configures; a widget with its own choice
overrides it.

```bash
occ config:app:set launchpad quicksearch_fallback_target --value='unified-search'
```

An unset value means "show no-results only" — LaunchPad never navigates a
user away from their dashboard by default.

## Accessibility

The bar is a WCAG 2.2 AA combobox: `role="search"` with a programmatically
associated label, `role="listbox"` results, `aria-activedescendant`
tracking the keyboard selection, and an `aria-live` announcement of the
match count or the no-match state. The selected match is marked with an
icon as well as with colour, so the selection is never conveyed by colour
alone.

## See also

- [Widgets](./widgets.md) — the full widget catalog and how placements persist
- [Custom Tiles](./tiles.md) — what the searchable labels come from
