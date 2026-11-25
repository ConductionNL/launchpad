# Proposal: announcement-form-board-sync

## Why

Board LpAankondiging draws "Nieuwe aankondiging" as one dialog of two columns: the form (title, text, target group, the kind as two choice cards News and Maintenance notice, "Ask for read confirmation" with "Confirm before", "Allow comments and following", Publish on, Show until) and beside it "This is how it looks", a live preview with "I have read this" and Follow, and the note that only the author sees the preview. It ends with Cancel and Schedule. On development `AnnouncementEditorModal.vue` asks the fields one under the other, the kind in a select, and shows the preview as a second step after saving a draft. An announcement cannot ask for a read confirmation: that exists only for a widget placement (dashboard-acknowledgements).

## What changes

- `announcements`: the form as the board draws it, with a live preview beside it (REQ-ANN-006) and a read confirmation with a deadline on an announcement (REQ-ANN-007).

## Impact

- `src/modals/AnnouncementEditorModal.vue`, the announcement entity and API (confirmation fields), the read receipts report for announcements, translations.
