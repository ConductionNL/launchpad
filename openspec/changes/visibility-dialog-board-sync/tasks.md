# Tasks: visibility-dialog-board-sync

- [ ] Dialog: match all or one as choice cards, a card per rule with Edit and delete, Add rule, View as with the sentence
- [x] Who sees this widget? in the widget's menu in edit mode, not only on right-click
  - Done: `src/components/Widgets/WidgetEditMenu.vue` (Edit widget, Who sees this widget?, Delete widget; the same data-testids as nextcloud-vue's CnWidgetEditCog, which has no slot for a third item) replaces the cog in `WidgetWrapper.vue`; the right-click item is renamed to match. The dialog is titled "Who sees this widget?" with "<widget> · <dashboard>" under it. Tests: `WidgetEditMenu.spec.js`, `WidgetWrapper.chrome.spec.js`, `VisibilityRulesModal.spec.js`, `Views.templateWiring.coverage.spec.js`.
- [ ] Translations in every shipped locale
- [ ] Unit tests: the sentence for a person who does and does not see the widget
- [ ] Playwright: open the dialog from the widget menu and check View as
