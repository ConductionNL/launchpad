# Design: edit-mode-draft-and-save

## Decisions

### D1. The draft lives in the browser until Save

Entering edit mode copies the dashboard's placements and grid columns into a draft in the dashboard store. Every edit
(add, remove, move, resize, configure a widget including its visibility rules, columns) changes the draft only; no
placement endpoint is called. The grid renders the draft. The draft pill counts changed things: each added, removed,
moved or resized, or reconfigured widget counts once, a changed column count once ("Concept · 3 wijzigingen",
nl, "Draft · 3 changes" en). With nothing changed the pill reads "No changes" and Save is disabled.

The dashboard settings window (name, own look, sharing, publication, languages, exports, versions) keeps its own Save
inside the window, as today; it is not part of the draft (Ruben: "every save of Dashboard settings" makes a version).

A reload or closing the tab with unsaved changes triggers the browser's own leave warning (`beforeunload`); the draft
is not kept across reloads.

### D2. One request saves the draft

Save sends the whole draft in one request: `PUT /api/dashboard/{id}` with `gridColumns` and the full `placements`
list (existing placements with their id, new ones without, a missing one is removed), or the matching group dashboard
endpoint. The server applies it in one database transaction and answers with the stored placements. Either all of it
is stored or nothing is. The same permission checks as today run per change before anything is written: a member with
add-only permission may add but not remove or change a compulsory widget, and the whole save is refused with 403 if
one change is not allowed.

The per-placement endpoints (`POST /api/dashboard/{id}/widgets`, `PUT`/`DELETE /api/widgets/{id}`) stay for the API,
the MCP tools and the grid's keyboard move, but the edit mode UI no longer calls them.

### D3. Every save makes one version, without debounce

Ruben: "every Save of edit mode creates one version of the whole dashboard (layout + widget config + look), and so does
every save of Dashboard settings". The 60 s debounce goes for these two saves: one save, one version. A version holds
the dashboard's fields (name, description, columns, own look) and every placement with its content and rules. The
version is written in the same transaction as the save, so a failed save leaves no version.

The first version of a dashboard is special: if a dashboard has no versions yet, the save first stores a version of
the state before the change, then the version after it. So the Versions tab always has something to restore. The
automatic version's label says what the save changed (REQ-VERSUI-004), e.g. "3 widgets changed".

Other writers (template resync, store update, MCP write tools) keep their own capture calls; they are not edit mode.

### D4. Two editors

Edit mode holds the dashboard lock (REQ-LOCKUI-001), so on a locked dashboard only one person drafts. Where co-editing
is on (dashboard-co-editing), the save carries the version of each placement the draft started from; a placement
someone else changed in the meantime makes the server answer 409 with those placements, and the editor chooses per
widget: keep mine or take theirs. Nothing is silently replaced.

### D5. Leaving edit mode

- Save stores and leaves edit mode.
- Cancel with changes asks "Discard changes?" (Discard, Keep editing); without changes it leaves at once.
- Anything else that leaves edit mode or the dashboard with unsaved changes (choosing another dashboard in More,
  following a link, Escape on the edit bar) opens "Save changes?" with Don't save, Keep editing and Save (board
  LpBewerken: Niet opslaan, Verder bewerken, Opslaan), anchored under the edit bar as drawn.

### D6. The widget action menu (item 21)

View mode: a widget shows its action menu only when it has an action for the reader: its own user actions (refresh,
Hide for me on a dashboard the reader does not own, which is a reader's choice and stays) or none. A tile, a heading or
any widget without such an action shows no "..." at all. Create embed token MUST NOT appear in view mode.

Edit mode: every widget, top level and inside a well or tabs, has the edit action menu with Configure, Duplicate,
Create embed token and Remove. Create embed token keeps its gate (who may create embed tokens; today administrators)
and opens the embed token form with the widget preselected, in the Embeds section of the General tab of launchpad's admin page. Until change
`admin-page-in-nextcloud-settings` lands, that is the current admin route; afterwards
`/settings/admin/launchpad#embeds`, which opens that section. Because the action leaves the dashboard, it asks to save first when the draft has
changes (D5) and is disabled for a widget the draft added but did not save yet (it has no id).

A widget inside a well or a tabs widget has no placement id of its own. Every child therefore carries a key: its
`id`, else its `uuid`; the server gives a child without either a `uuid` whenever the content is written
(`WidgetPlacement::setContentArray` through `ContainerChildKeys`: `<placement id>-<index>` for a direct child of a
well, the key the well already counted tile use with, unless another child holds it; a random one otherwise), and
the repair step `KeyContainerChildren` keys the children stored before. An embed token names a child as
`<placement id>.<child key>` (a dot, because the embed route takes the subject id as one path segment); the embed
renders the child as a widget of its own, and only while its well is visible. The admin Embeds form lists the children
under their well (`Well › Tab › Child`). A child whose key is not stored yet (added in this draft, inside a tab) has
the entry disabled until the draft is saved. Duplicate copies the child after itself in the same list without its
key; Remove takes it out; both change the content of the widget on the dashboard, in the draft.

## Migration

None for data. Existing dashboards have no versions; their first Save after this change stores the before and after
versions (D3).

## Risks

- Tests that expect a placement request per drag (grid e2e, co-editing e2e) change to the one-save model.
- A large dashboard sends a larger request at Save; still one request instead of many, and inside the API budget for
  the dashboards measured today (to be confirmed by the speed run).
