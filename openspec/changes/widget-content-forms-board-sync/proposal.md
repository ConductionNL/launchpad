# Proposal: widget-content-forms-board-sync

## Why

Board LpWidgetInstellen draws the content part of three widget forms. Every widget has the same dialog, Content and then Layout. Live data: where the value comes from (a connection the administrator made, or a web address that returns JSON) as choice cards, the connection with its last success, the path in the response, the view (Value, List, Table, Key-value), the unit, the refresh, and under Formatting the title, colour, border and a live preview. Image: upload a file or a web address, the chosen file with its size and dimensions, the required text for who does not see the image, and an optional link. Calendar: the calendars to show, an external calendar by ICS address, and 7 days or month.

On development these widgets open nextcloud-vue's generic widget editor with their own forms (`LiveTileWidgetForm.vue`, the image and calendar forms), laid out field after field and without the preview, the choice cards or the shown connection state. No requirement describes the dialog shape.

## What changes

- `live-data-tile-widget`: the Live data form as the board draws it, with its preview (REQ-LIVETILE-020).
- `image-widget`: the Image content form (REQ-IMG-020).
- `calendar-widget`: the Calendar content form (REQ-CAL-020).

## Impact

- `src/components/Widgets/Renderers/LiveTileWidgetForm.vue`, the image and calendar widget forms, translations.
