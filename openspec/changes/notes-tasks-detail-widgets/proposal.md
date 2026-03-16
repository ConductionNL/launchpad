# Proposal: notes-tasks-detail-widgets

## Summary
Add Notes and Tasks as both inline CnDetailCard widgets on detail pages AND as sidebar tabs, and introduce a user action menu on notes/tasks authored by other users — enabling direct messaging, chat, email, and meeting scheduling from within the detail view.

## Motivation
Currently, Notes and Tasks only live inside the CnObjectSidebar as tabs — hidden behind a sidebar toggle. Users working on detail pages (requests, cases, tasks) must open the sidebar to see or add notes/tasks, breaking their workflow. Making these available as inline cards on the detail page itself gives immediate visibility.

Additionally, collaborative features are missing: when a note is written by another user or a task is assigned to someone else, there is no way to interact with that person directly. Users must leave the app to find contact information. A user action menu (message, chat, email, plan meeting) on clickable user names closes this gap and keeps users in context.

## Current State Analysis

### CnObjectSidebar — Notes Tab (Today)
The sidebar's Notes tab (`CnObjectSidebar.vue`, lines 82-139) provides:
- A `<textarea>` for composing notes and a "Add note" `NcButton` (POST body: `{ "message": "..." }`)
- Notes fetched via `GET {apiBase}/objects/{register}/{schema}/{id}/notes` — response is `data.results || data || []`
- Each note renders: `note.actorDisplayName || note.author || 'Unknown'` as author, `note.message || note.content` as body, `note.creationDateTime || note.created` as timestamp
- Delete button (visible on hover) for notes where `note.actorId === OC?.currentUser || note.author === OC?.currentUser`
- Data loaded lazily — only fetched when the Notes tab becomes active (the sidebar defaults to the Files tab)
- **No user interactivity on author names** — author is rendered as plain `<strong>` text

### CnObjectSidebar — Tasks Tab (Today)
The sidebar's Tasks tab (`CnObjectSidebar.vue`, lines 192-223) provides:
- Tasks fetched via `GET {apiBase}/objects/{register}/{schema}/{id}/tasks` — same response pattern
- Each task rendered as an `NcListItem` with: `task.title || task.name` as name, status icon (`CheckboxMarkedOutline` for completed, `CheckboxBlankOutline` otherwise), subname shows `task.assignee` + ` · ` + formatted `task.dueDate`
- **No task creation UI** in the sidebar — tasks are read-only display
- **No overdue highlighting** — due dates are formatted but not color-coded
- **No user interactivity on assignee names**

### Detail Pages Across Apps

**Pipelinq — RequestDetail** (`pipelinq/src/views/requests/RequestDetail.vue`):
- Uses `CnDetailPage` + `CnDetailCard` pattern with sidebar enabled
- Cards: Status & Priority, Request Information, Client, Assignment, Pipeline
- Sidebar gets `register` and `schema` from `objectStore.objectTypeRegistry.request`
- No inline notes/tasks cards — users must open sidebar

**Procest — CaseDetail** (`procest/src/views/cases/CaseDetail.vue`):
- Uses `CnDetailPage` + `CnDetailCard` with sidebar
- Cards: Status, Status Timeline, Case Information, Deadline & Timing, Participants, Tasks (inline table), Activity (with `ActivityTimeline` component that includes `onAddNote`)
- Already has an inline Tasks section as a table showing title, status, assignee, due date, priority with overdue highlighting
- Has its own Activity card with note-adding capability — this is separate from the sidebar Notes tab
- **Key insight**: CaseDetail already duplicates some sidebar functionality (tasks as inline table, notes via ActivityTimeline) but using app-specific components, not shared `CnNotesCard`/`CnTasksCard`

**Procest — TaskDetail** (`procest/src/views/tasks/TaskDetail.vue`):
- Uses `CnDetailPage` + `CnDetailCard` with sidebar
- Cards: Status (with transition buttons), Task Information, Linked Case
- No inline notes/tasks cards

**OpenCatalogi — PublicationDetail** (`opencatalogi/src/views/publications/PublicationDetail.vue`):
- Uses a legacy `<script setup>` pattern with custom store — does NOT use `CnDetailPage`
- No sidebar integration — uses modal-based edit pattern (`navigationStore.setModal`)
- **Key insight**: OpenCatalogi needs migration to `CnDetailPage` before notes/tasks cards can be added, or cards need to work standalone without `CnDetailPage`

### Nextcloud Integration APIs

**OCS Capabilities API** — `GET /ocs/v2.php/cloud/capabilities?format=json`:
- Returns installed app capabilities including `spreed` (Talk), `calendar`, etc.
- The `@nextcloud/capabilities` package (v1.2.1) is already an indirect dependency via `@nextcloud/vue` — it reads capabilities from Nextcloud's initial state, avoiding a network call
- Response structure: `ocs.data.capabilities.spreed` exists when Talk is installed, `ocs.data.capabilities.dav` exists when Calendar/CalDAV is available
- Mail detection: check `ocs.data.capabilities.mail` or fallback to checking if `/apps/mail` route exists

**Talk API — Create/find 1:1 room**:
- `POST /ocs/v2.php/apps/spreed/api/v4/room` with body `{ "roomType": 1, "invite": "{userId}" }` (format=json)
- Headers: `OCS-APIREQUEST: true`, `requesttoken: OC.requestToken`
- Returns `{ ocs: { data: { token: "abc123", ... } } }`
- Navigate to: `/apps/spreed/#/call/{token}` (same tab) or open in new tab

**Mail — Compose deep-link**:
- With Mail app: `/apps/mail/compose?to={email}`
- Without Mail app: `mailto:{email}` (opens system mail client)
- User email resolved via `GET /ocs/v2.php/cloud/users/{userId}?format=json` — returns `ocs.data.email`

**Calendar — Event deep-link**:
- `/apps/calendar/new?attendees={userId}&title=Meeting`
- Note: Calendar deep-link API is not fully documented; the `attendees` parameter behavior should be verified

## Affected Projects
- [ ] Project: `nextcloud-vue` — Add `CnNotesCard` and `CnTasksCard` widget components; add `CnUserActionMenu` component
- [ ] Project: `pipelinq` — Integrate notes/tasks cards on RequestDetail and other detail pages
- [ ] Project: `procest` — Integrate notes/tasks cards on CaseDetail and TaskDetail pages
- [ ] Project: `opencatalogi` — Integrate notes/tasks cards on relevant detail pages

## Scope
### In Scope
- New `CnNotesCard` component — inline card showing recent notes with add-note input, using the existing `/api/objects/{register}/{schema}/{id}/notes` API
- New `CnTasksCard` component — inline card showing tasks with status indicators, using the existing `/api/objects/{register}/{schema}/{id}/tasks` API
- Both cards remain available as sidebar tabs in `CnObjectSidebar` (no removal)
- New `CnUserActionMenu` component — popover/dropdown triggered by clicking a user's name, with actions:
  - Send message (Nextcloud Talk 1:1 chat)
  - Start chat (Nextcloud Talk)
  - Send email (mailto: link or Nextcloud Mail integration)
  - Plan meeting (calendar event creation with user as attendee)
- User action menu integration on notes (author name) and tasks (assignee name)
- Card-based design consistent with existing `CnDetailCard` pattern

### Out of Scope
- Task creation/editing UI beyond what already exists in the sidebar
- Full calendar/meeting scheduling app — just launch the action
- Custom notification system — relies on existing Nextcloud notifications
- Changes to the Notes or Tasks backend APIs

## Approach
1. Create reusable `CnNotesCard` and `CnTasksCard` in `nextcloud-vue` that wrap the same API calls the sidebar tabs already use
2. Create `CnUserActionMenu` as a reusable popover component that resolves user capabilities (Talk installed? Mail installed?) and shows available actions
3. Integrate the cards into detail page layouts via named slots in `CnDetailPage`
4. Both cards and sidebar tabs share the same data — use the existing object store pattern to avoid duplicate API calls

## Cross-Project Dependencies
- Nextcloud Talk API — for creating 1:1 conversations (`/ocs/v2.php/apps/spreed/api/v4/room`)
- Nextcloud Mail — for compose links (deep-link or mailto:)
- Nextcloud Calendar — for event creation links
- All three are optional: `CnUserActionMenu` checks which apps are installed and only shows available actions

## Rollback Strategy
- All new components are additive — removing card imports from detail pages reverts to sidebar-only behavior
- `CnUserActionMenu` is self-contained — removing it leaves user names as plain text
- No database migrations or API changes involved

## Open Questions
- Should notes/tasks cards show a limited preview (e.g., last 5) with "show all" linking to the sidebar tab, or show everything inline?
- Should the user action menu also appear on participant lists (e.g., Procest ParticipantsSection)?
- **Email fallback**: When Mail is not installed but the target user has an email, should "Send email" use `mailto:` or be hidden entirely? Current design says use `mailto:` fallback — confirm this is acceptable UX.
- **Data refresh strategy**: When a note is added via the inline card, the sidebar Notes tab may also be open. Should the sidebar re-fetch on focus, or should the parent page emit an event that both card and sidebar listen to? The current sidebar uses lazy loading with a "fetch once" guard (`if (this.notes.length === 0 && !this.notesLoading)`) — this means the sidebar will NOT automatically reflect changes made via the inline card.
- **OpenCatalogi target pages**: PublicationDetail does not use `CnDetailPage` — it uses a legacy modal-based pattern. Should OpenCatalogi be migrated to `CnDetailPage` first, or should `CnNotesCard`/`CnTasksCard` work standalone (without requiring `CnDetailPage` as parent)?
- **Task creation scope**: The sidebar Tasks tab is read-only (no creation UI). Should `CnTasksCard` also be read-only, or should it include a quick-create input similar to `CnNotesCard`? Procest's CaseDetail already has a "New task" button that routes to TaskNew.
- **Capability caching**: `@nextcloud/capabilities` reads from Nextcloud's initial state (loaded once at page load). If an admin installs Talk mid-session, the menu won't reflect it until page refresh. Is this acceptable?
- **Show-all threshold**: Design says 5 items. Should this be a configurable prop (e.g., `:limit="5"`) to allow apps to adjust?
