# Notes Card Specification

## Purpose
Provides an inline card widget (`CnNotesCard`) for detail pages that displays recent notes and allows adding new notes, giving users immediate visibility into notes without opening the sidebar.

## Current Behavior
Today, notes are only accessible via the CnObjectSidebar's Notes tab. The sidebar loads notes lazily (only when the tab is activated, and only once per session). Notes are displayed in a flat list with author name (plain text), message body, and timestamp. Users can add notes via a textarea + button, and delete their own notes. There is no inline card — users must toggle the sidebar open and switch to the Notes tab to see any notes.

The sidebar's note data structure supports dual formats from the OpenRegister API: `actorDisplayName`/`author` for the author, `message`/`content` for the body, and `creationDateTime`/`created` for the timestamp. The `CnNotesCard` must handle both formats identically.

## Requirements

### Requirement: Inline notes display
The system MUST render a `CnDetailCard` with title "Notes" that displays the most recent notes for the current object.

#### Scenario: Notes card shows recent notes
- GIVEN a detail page with a `CnNotesCard` component
- WHEN the card is rendered with a valid register, schema, and object ID
- THEN the card MUST display up to 5 most recent notes in reverse chronological order
- AND each note MUST show the author's display name, the note content, and a relative timestamp

#### Scenario: Empty state
- GIVEN a detail page with a `CnNotesCard` component
- WHEN the object has no notes
- THEN the card MUST display an empty state message "No notes yet"
- AND the add-note input MUST still be visible

### Requirement: Add note from card
The system MUST allow users to add a new note directly from the inline card without opening the sidebar.

#### Scenario: Adding a note via the card
- GIVEN a `CnNotesCard` with the add-note input visible
- WHEN the user types a note and clicks the add button (or presses Enter)
- THEN the system MUST POST `{ "message": "<trimmed text>" }` to `{apiBase}/objects/{register}/{schema}/{id}/notes` using `buildHeaders()` (Content-Type: application/json, requesttoken, OCS-APIREQUEST: true)
- AND the new note MUST appear at the top of the notes list immediately
- AND the input MUST be cleared
- AND the component MUST emit a `note-added` event so the parent can sync sidebar data

#### Scenario: Empty note submission
- GIVEN a `CnNotesCard` with an empty input
- WHEN the user clicks the add button
- THEN the system MUST NOT submit the note
- AND the add button MUST be disabled when input is empty

### Requirement: Show all link
The system MUST provide a way to navigate to the full notes list in the sidebar.

#### Scenario: More notes available than displayed
- GIVEN an object with more than 5 notes
- WHEN the `CnNotesCard` is rendered
- THEN a "Show all ({count})" link MUST appear in the card footer
- AND clicking it MUST open the sidebar on the Notes tab

### Requirement: Author name is interactive
Each note's author name MUST be clickable and trigger the `CnUserActionMenu` for that user.

#### Scenario: Clicking a note author
- GIVEN a note authored by a different user
- WHEN the current user clicks the author's display name
- THEN the `CnUserActionMenu` MUST open for that author
- AND the menu MUST be positioned relative to the clicked name

#### Scenario: Note authored by current user
- GIVEN a note authored by the current user
- WHEN the current user views the note
- THEN the author name MUST still be displayed but MUST NOT trigger the action menu
- AND current user detection MUST use `note.actorId === OC?.currentUser || note.author === OC?.currentUser` (matching existing sidebar logic)

### Requirement: API response format handling
The component MUST handle the dual response formats from the OpenRegister notes API.

#### Scenario: Comments API format
- GIVEN the API returns notes with `actorDisplayName`, `message`, `creationDateTime`, and `actorId` fields
- WHEN the card renders
- THEN it MUST use these fields for display and author identification

#### Scenario: Direct storage format
- GIVEN the API returns notes with `author`, `content`, `created` fields
- WHEN the card renders
- THEN it MUST fall back to these fields when the Comments API fields are absent

#### Scenario: Wrapped response
- GIVEN the API returns `{ "results": [...] }` instead of a raw array
- WHEN the card processes the response
- THEN it MUST handle both `data.results` and raw array formats (using `data.results || data || []`)

### Requirement: Edge cases

#### Scenario: Concurrent note addition
- GIVEN two users viewing the same object's notes
- WHEN one user adds a note
- THEN the other user's card will NOT automatically update (no WebSocket/polling)
- AND the card will show the latest data on next manual interaction (e.g., adding their own note triggers a re-fetch)

#### Scenario: Network error on note submission
- GIVEN the user submits a note
- WHEN the POST request fails
- THEN the input MUST NOT be cleared
- AND an error message SHOULD be displayed (via `showError` from `@nextcloud/dialogs` or inline)

#### Scenario: Very long note content
- GIVEN a note with content exceeding 500 characters
- WHEN the card renders
- THEN the note body MUST use `white-space: pre-wrap; word-break: break-word` (matching sidebar behavior)
- AND no truncation MUST occur within the visible notes
