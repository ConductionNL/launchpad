# Tasks: add-widget-dialog-with-miniatures

- [ ] Registry: `sampleContent` for every launchpad widget type in `src/constants/widgetRegistry.js`; `preview` flag honoured by every renderer (no fetch, poll, subscribe or tracking)
- [ ] nextcloud-vue: `sampleContent` and the `preview` flag for the widget types it registers (PR in nextcloud-vue, then bump the dependency)
- [ ] `WidgetPickerModal.vue`: 1120 px wide; card = miniature frame above icon, name and line; miniature mounted on view, lazy chunks, icon fallback on error
- [ ] Kinds: add Layout (nl Indeling), move Container there; counts follow
- [ ] Remove the Add tiles button from the edit bar and the empty state; Add widget is the one entry
- [ ] Translations for the new strings
- [ ] Unit tests: every registered type renders a miniature from `sampleContent` with `preview` and sends no request (fails on a type without sample content); picker width, kinds and counts; no Add tiles button
- [ ] Playwright `tests/e2e/widget-picker-miniatures.spec.ts`: open Add widget, every visible card shows a miniature, the dialog is 1120 px wide, choosing Tiles and links lists the tile widgets; the edit bar has no Add tiles button
- [ ] Speed: the picker opens within the speed rule on the reference stack (first useful content <= 300 ms)
- [ ] Live check on a sandbox against LpWidgetKiezen (pixel pass)
