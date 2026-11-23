# Proposal: reactions-bar-at-the-foot

## Why

Board LpIntranet ends the dashboard with a bar over the full width: "Wat vindt u van dit intranet?" with the reactions and their counts, and at the right "Laatst bijgewerkt door Communicatie, vandaag 08:30". REQ-RXN-010 puts the reactions bar under the dashboard title, and the code draws it above the title, so the board's bar is missing and a row of emoji sits above the page title instead. The board is canon, so the requirement follows the board.

## What changes

- `dashboard-reactions`: REQ-RXN-010 is MODIFIED: the bar sits at the foot of the dashboard, under the last widget, with a question before the reactions and the last change after them.

## Impact

- `src/views/Views.vue` (where `DashboardReactions` mounts), `src/components/Workspace/DashboardReactions.vue`, translations.
