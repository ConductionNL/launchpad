# Design: notes-tasks-detail-widgets

## Architecture Overview

This change adds three new Vue components to `nextcloud-vue` and integrates them into detail pages across apps:

```
nextcloud-vue/src/components/
├── CnNotesCard/CnNotesCard.vue       ← Inline notes widget (card)
├── CnTasksCard/CnTasksCard.vue       ← Inline tasks widget (card)
├── CnUserActionMenu/CnUserActionMenu.vue  ← User action popover
├── CnDetailPage/CnDetailPage.vue     ← (existing) layout shell
├── CnDetailCard/CnDetailCard.vue     ← (existing) card wrapper
└── CnObjectSidebar/CnObjectSidebar.vue   ← (existing) sidebar tabs — unchanged
```

Both `CnNotesCard` and `CnTasksCard` reuse the same API endpoints that the sidebar tabs already call. The key design decision is **data sharing**: when both the inline card and sidebar tab are visible, they MUST show consistent data without duplicate API calls.

## Existing Component Analysis

### CnObjectSidebar — Current Notes Implementation
The sidebar (`CnObjectSidebar.vue`) manages notes entirely within its own component state:
- **Data**: `notes: []`, `notesLoading: false`, `newNoteText: ''`, `noteSaving: false` — all local reactive data
- **Fetch**: `fetchNotes()` calls `GET {apiBase}/objects/{register}/{schema}/{id}/notes` using `buildHeaders()` from `@conduction/nextcloud-vue/utils`
- **Lazy loading**: Notes are only fetched when the Notes tab becomes the active tab AND `this.notes.length === 0 && !this.notesLoading` — this is a "fetch once" pattern with no re-fetch mechanism
- **Add**: `addNote()` POSTs `{ "message": newNoteText.trim() }` then calls `fetchNotes()` to refresh the list
- **Delete**: `deleteNote(note)` sends DELETE then removes from local array (optimistic-ish — removes from `this.notes` but doesn't re-fetch)
- **Author display**: Uses `note.actorDisplayName || note.author || 'Unknown'` — the API returns both formats depending on whether it bridges to NC Comments API or stores directly
- **Timestamp display**: Uses `note.creationDateTime || note.created` — same dual-format pattern
- **Delete permission**: `canDeleteNote(note)` checks `note.actorId === OC?.currentUser || note.author === OC?.currentUser`

### CnObjectSidebar — Current Tasks Implementation
- **Fetch**: `fetchTasks()` calls `GET {apiBase}/objects/{register}/{schema}/{id}/tasks`
- **Display**: `NcListItem` with `task.title || task.name`, subname `task.assignee || '' + task.dueDate`
- **Status icons**: `CheckboxMarkedOutline` (completed, green), `CheckboxBlankOutline` (all others)
- **No creation UI**, no delete, no status transitions — read-only display only
- **No overdue detection** — due dates displayed but not color-coded

### CnDetailPage Architecture
`CnDetailPage.vue` provides:
- **Layout**: Header (back button, title, subtitle, action slot) + Body (content area + optional sidebar)
- **Default slot**: Main content area where `CnDetailCard` components go — rendered in a flex column with 16px gap
- **Sidebar slot**: Defaults to `CnObjectSidebar` when `objectType` and `objectId` are provided
- **Sidebar toggle**: Fixed button appears when sidebar is closed
- **Props passed to sidebar**: `objectType`, `objectId`, `open`, plus `sidebarProps` spread via `v-bind`
- **No data sharing mechanism**: The page does NOT fetch notes/tasks — that's entirely the sidebar's responsibility. There is no event bus or shared state between content area and sidebar.

### CnDetailCard Architecture
`CnDetailCard.vue` provides:
- **Slots**: `icon` (header left), `header-actions` (header right), default (content), `footer`
- **Props**: `title`, `icon` (MDI component), `collapsible`, `collapsed`
- **Footer slot**: Available for "Show all" links — rendered with top border, 8px/16px padding
- **No data fetching** — purely presentational wrapper

### Key Architectural Insight: Data Sharing Challenge
The current architecture has a fundamental mismatch with the data-sharing goal:
1. `CnObjectSidebar` owns its notes/tasks data internally (local `data()` state)
2. `CnDetailPage` does not know about notes/tasks — it just renders the sidebar
3. The new inline cards would need the SAME data, but the sidebar doesn't expose it

**Solution options:**
- **Option A (Props-based, chosen)**: Parent page fetches notes/tasks, passes to both card and sidebar via props. Requires adding props to `CnObjectSidebar` to accept external data (e.g., `:notes="notes"` `:tasks="tasks"`).
- **Option B**: Shared composable (`useObjectNotes(register, schema, objectId)`) that both card and sidebar import — returns reactive `notes`, `addNote()`, `fetchNotes()`. More elegant but introduces a new pattern.
- **Option C**: Event bus — card emits `note-added`, sidebar listens and re-fetches. Simplest change but fragile.

Option A is chosen per the trade-offs section. This means `CnObjectSidebar` needs new optional props: `externalNotes`, `externalTasks`, and corresponding events, so it can be "controlled" by the parent when inline cards are also present.

## API Design

No new backend APIs. All components use existing endpoints:

### `GET /api/objects/{register}/{schema}/{id}/notes`
**Actual response format** (based on CnObjectSidebar field access patterns):
```json
[
  {
    "id": 42,
    "actorId": "admin",
    "actorDisplayName": "Admin User",
    "message": "Note text here",
    "creationDateTime": "2026-03-16T10:00:00Z"
  }
]
```
Note: The API bridges to Nextcloud's Comments API. Fields may also appear as `author` (string userId), `content` (string body), and `created` (ISO date). The sidebar handles both formats via fallback: `note.actorDisplayName || note.author || 'Unknown'` and `note.message || note.content`.

The response may be wrapped: `data.results || data || []` — the sidebar handles both array and `{ results: [...] }` formats.

### `POST /api/objects/{register}/{schema}/{id}/notes`
**Actual request format** (based on CnObjectSidebar `addNote()`):
```json
{ "message": "New note text" }
```
Note: The sidebar sends `message`, not `content`. The POST uses `buildHeaders()` which sets `Content-Type: application/json`, `requesttoken`, and `OCS-APIREQUEST: true`.

### `DELETE /api/objects/{register}/{schema}/{id}/notes/{noteId}`
Uses `buildHeaders()`. The sidebar removes from local array after successful delete.

### `GET /api/objects/{register}/{schema}/{id}/tasks`
**Actual response format** (based on CnObjectSidebar field access patterns):
```json
[
  {
    "id": "uuid",
    "title": "Review document",
    "name": "Review document",
    "status": "completed",
    "assignee": "user1",
    "dueDate": "2026-03-20T00:00:00Z"
  }
]
```
Note: The sidebar accesses `task.title || task.name` for display name, `task.assignee` as a plain string (NOT an object), and `task.dueDate` (NOT `due`). The `status` values observed in Procest are: `available`, `active`, `completed`, `terminated`, `disabled`.

### Nextcloud Talk — Create 1:1 conversation
`POST /ocs/v2.php/apps/spreed/api/v4/room`
```json
{ "roomType": 1, "invite": "userId" }
```
Returns conversation token → navigate to `/apps/spreed/#/call/{token}`

### Nextcloud Calendar — Create event
Deep-link: `/apps/calendar/new?attendees={userId}&title=Meeting`

### Nextcloud Mail — Compose
Deep-link: `/apps/mail/compose?to={email}` or `mailto:{email}`

## Database Changes

None. All data flows through existing OpenRegister object sub-resource APIs.

## Nextcloud Integration

No backend changes needed. Frontend-only:

- **OCS Capabilities API** (`/ocs/v2.php/cloud/capabilities`): Check which apps are installed (Talk, Mail, Calendar) to determine available user actions
- **OCS Users API** (`/ocs/v2.php/cloud/users/{userId}`): Resolve user email for mail action
- **Talk API**: Create/find 1:1 rooms
- **Router**: Deep-link navigation to Talk, Mail, Calendar

### Capability Detection Pattern

The `@nextcloud/capabilities` package (v1.2.1, already an indirect dependency) provides `getCapabilities()` which reads from Nextcloud's `__nc_initial_state` — this is a synchronous read from the page's initial state, NOT a network call. This means:

1. No API request needed at component mount
2. Capabilities are available immediately
3. Values are cached for the entire page session (no re-fetch)
4. If an admin installs/uninstalls an app mid-session, changes won't reflect until page refresh

**Detection logic:**
```js
import { getCapabilities } from '@nextcloud/capabilities'
const caps = getCapabilities()
const hasTalk = !!caps?.spreed
const hasCalendar = !!caps?.dav  // CalDAV indicates Calendar availability
const hasMail = !!caps?.mail     // Mail app capabilities
```

If `@nextcloud/capabilities` is not available as a direct dependency, fallback to fetching `GET /ocs/v2.php/cloud/capabilities?format=json` with OCS headers and caching the result per session.

### Data Synchronization Design

When both inline card and sidebar tab are visible, they must show consistent data. The chosen approach:

1. **Parent page owns the data**: The detail page (e.g., `RequestDetail`) fetches notes/tasks and passes them as props to both `CnNotesCard` and `CnObjectSidebar`
2. **Cards emit events**: `CnNotesCard` emits `@note-added`, `@note-deleted`; `CnTasksCard` emits `@task-updated`
3. **Parent re-fetches**: On event, parent calls its own fetch method and updates props for both consumers
4. **CnObjectSidebar changes needed**: Add optional props (`notes`, `tasks`, `notesLoading`, `tasksLoading`) that, when provided, override the sidebar's internal fetching. When these props are set, the sidebar skips its own `fetchNotes()`/`fetchTasks()` calls and uses the prop data instead.
5. **Backwards compatible**: When props are not provided (default), sidebar behaves exactly as today

## File Structure

New files:
```
nextcloud-vue/src/components/
  CnNotesCard/
    CnNotesCard.vue          # Inline notes card with preview + add input
  CnTasksCard/
    CnTasksCard.vue          # Inline tasks card with status indicators
  CnUserActionMenu/
    CnUserActionMenu.vue     # Popover with user actions (message, chat, mail, meet)
```

Modified files:
```
nextcloud-vue/src/components/CnObjectSidebar/CnObjectSidebar.vue  # Add optional external data props
pipelinq/src/views/requests/RequestDetail.vue    # Add notes + tasks cards
pipelinq/src/views/leads/LeadDetail.vue          # Add notes + tasks cards
pipelinq/src/views/contacts/ContactDetail.vue    # Add notes + tasks cards
pipelinq/src/views/clients/ClientDetail.vue      # Add notes + tasks cards
procest/src/views/cases/CaseDetail.vue           # Add notes + tasks cards (has existing inline tasks table — consider replacing with CnTasksCard)
procest/src/views/tasks/TaskDetail.vue           # Add notes + tasks cards
opencatalogi/src/views/publications/PublicationDetail.vue  # Deferred — needs CnDetailPage migration first
```

## Security Considerations

- **User action menu**: Only shows actions the current user has permission for (e.g., Talk must be enabled, user must have access)
- **CSRF**: All OCS calls include the Nextcloud request token (handled by `@nextcloud/axios`)
- **Input validation**: Note content sanitized before display (existing behavior in notes API)
- **No new endpoints**: No additional attack surface

## NL Design System

- Cards use `CnDetailCard` which already follows NL Design System patterns
- User action menu uses Nextcloud `NcPopover` + `NcActionButton` for consistent styling
- User avatars use `NcAvatar` component
- All interactive elements MUST meet WCAG AA: keyboard navigable, proper ARIA labels, sufficient contrast

## Trade-offs

### Decision: Inline cards vs. promoting sidebar tabs
**Chosen**: Add new inline card components alongside existing sidebar tabs
**Alternative**: Move notes/tasks out of sidebar entirely
**Rationale**: Users may prefer either location depending on workflow. Keeping both gives flexibility. Apps can choose which to show via props.

### Decision: Data sharing via props vs. shared store
**Chosen**: Props-based — parent page fetches data and passes to both card and sidebar
**Alternative**: Shared reactive store (Pinia/Vuex)
**Rationale**: Avoids adding state management dependency. The parent detail page already owns the object context. Cards emit events, parent updates state. Simpler, consistent with existing Options API + `createObjectStore` pattern.

### Decision: Capability detection for user actions
**Chosen**: Check `/ocs/v2.php/cloud/capabilities` at component mount to discover installed apps
**Alternative**: Hardcode all actions and let them fail
**Rationale**: Better UX — only show actions that will work. Single API call, cacheable per session.

### Decision: Limited preview in cards
**Chosen**: Cards show last 5 items with "Show all in sidebar" link
**Alternative**: Show all items inline
**Rationale**: Detail pages already have many cards. Showing everything inline makes pages too long. The sidebar remains the full-list view.
