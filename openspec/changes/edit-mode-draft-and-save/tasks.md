# Tasks: edit-mode-draft-and-save

- [ ] Store: draft copy of placements and columns on entering edit mode; edits change the draft only; change counter; `isDirty`
- [ ] `DashboardEditBar.vue`: draft pill (count / No changes), Columns into the draft, Add widget, Dashboard settings, Cancel, Save (disabled without changes); remove the "Changes are saved immediately" hint and Done
- [ ] Leave prompt "Save changes?" (Don't save, Keep editing, Save) as its own dialog under `src/dialogs/`, on every way out of edit mode or the dashboard; "Discard changes?" on Cancel; `beforeunload` warning while dirty
- [ ] Grid and widget forms write to the draft (add, remove, move, resize, configure, visibility rules) instead of calling placement endpoints
- [ ] Backend: `PUT /api/dashboard/{id}` (and the group endpoint) applies `gridColumns` plus the full `placements` list in one transaction, per-change permission checks before writing, 409 with the conflicting placements when co-editing finds a newer placement
- [ ] Versions: one version per edit-mode Save and per dashboard settings save, no debounce, inside the save transaction; before-and-after on the first version; label says what changed
- [ ] `WidgetWrapper.vue` and the edit menu (`WidgetEditMenu.vue` / `WidgetContextMenu.vue`): no action menu in view mode unless the widget has a reader action; Create embed token in the edit menu for every widget type, also inside a well or tabs; disabled for an unsaved new widget; asks to save first when dirty (PART 1 in cloud/b-launchpad-help-edit-mode-draft-and-save: view mode has no embed entry, the edit menu of every top-level widget and tile carries it, disabled without an id. LEFT: well/tabs children (they have no placement id: needs an embed target for a child), Duplicate, ask-to-save-first once the draft exists)
- [ ] Translations for the new strings in every shipped locale
- [ ] Unit tests: draft store (count, dirty, discard), edit bar, leave prompt, save service (transaction, all-or-nothing, permission refusal, 409), version capture (one per save, first save stores two, failed save stores none), widget menu in both modes
- [ ] Playwright `tests/e2e/edit-mode-draft.spec.ts`: move a widget, the pill counts 1, reload warns; Cancel discards and the widget is back; Save stores; choosing another dashboard with changes opens the prompt
- [ ] Playwright `tests/e2e/dashboard-versions-on-save.spec.ts`: change a widget, Save, a version appears in the Versions tab; restoring it brings the old widget back
- [ ] Playwright `tests/e2e/widget-action-menu.spec.ts` (written for view mode and top-level widget + tile, live 4/4 with --repeat-each 2 on 27 Nov; the well-child case waits for task 7's rest): view mode, a tile has no "..." and no widget offers Create embed token; edit mode, an administrator finds Create embed token on a top-level widget and on a well child and it opens the token form with that widget chosen
- [ ] Rewrite the e2e specs that rely on immediate saves (grid move, co-editing, edit bar Done)
- [ ] Live check on a sandbox against LpBewerken and LpVersies, and the edit-mode Save inside the API budget
