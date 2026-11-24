# Proposal: visibility-dialog-board-sync

## Why

Board LpZichtbaarheid draws "Wie ziet deze widget?": whether all rules or one rule must match as two choice cards, one card per rule (Groups as chips, Times, Period, Profile field) with Edit and delete, "Add rule", and "View as" with a person and a moment and a sentence that says whether that person sees the widget and why. The rules dialog on development (`VisibilityRulesModal.vue`, titled "Visibility rules") lists rules differently, and it opens only from the widget's right-click context menu in edit mode, which a keyboard user and a measurement cannot reach.

## What changes

- `conditional-visibility-editor`: the dialog as the board draws it (REQ-CVUI-006) and a "Who sees this widget?" item in the widget's menu in edit mode (REQ-CVUI-007).

## Impact

- `src/modals/VisibilityRulesModal.vue`, the widget menu (`WidgetContextMenu.vue` and the placement action menu), translations.
