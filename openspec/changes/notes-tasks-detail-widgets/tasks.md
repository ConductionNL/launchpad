# Tasks: notes-tasks-detail-widgets

## Implementation Tasks

### Task 0: Add external data props to CnObjectSidebar
- **spec_ref**: `specs/notes-card/spec.md`, `specs/tasks-card/spec.md`
- **files**: `nextcloud-vue/src/components/CnObjectSidebar/CnObjectSidebar.vue`
- **acceptance_criteria**:
  - GIVEN `externalNotes` prop is provided WHEN the sidebar renders the Notes tab THEN it uses the prop data instead of fetching its own
  - GIVEN `externalNotes` prop is NOT provided WHEN the sidebar renders THEN it behaves exactly as today (lazy-fetch on tab activation)
  - GIVEN `externalTasks` prop is provided WHEN the sidebar renders the Tasks tab THEN it uses the prop data instead of fetching its own
- [ ] Add optional `externalNotes` prop (Array, default null) — when non-null, skip `fetchNotes()` and use this data
- [ ] Add optional `externalTasks` prop (Array, default null) — when non-null, skip `fetchTasks()` and use this data
- [ ] Add `@note-added` and `@note-deleted` events that emit when sidebar's own add/delete actions complete (so parent can re-fetch)
- [ ] Update `loadTabData('notes')` to check `externalNotes !== null` before self-fetching
- [ ] Update `loadTabData('tasks')` to check `externalTasks !== null` before self-fetching
- [ ] Add computed `displayNotes` and `displayTasks` that return external data when available, internal data otherwise
- [ ] Test backwards compatibility — sidebar without new props must work identically

### Task 1: Create CnUserActionMenu component
- **spec_ref**: `specs/user-action-menu/spec.md`
- **files**: `nextcloud-vue/src/components/CnUserActionMenu/CnUserActionMenu.vue`
- **acceptance_criteria**:
  - GIVEN a user name is clicked WHEN the popover opens THEN it shows avatar, display name, and available actions based on installed apps
  - GIVEN Talk is not installed WHEN the menu opens THEN "Send message" and "Start chat" are hidden
  - GIVEN the menu is open WHEN Escape is pressed THEN the menu closes and focus returns to trigger
- [x] Implement capability detection — prefer `@nextcloud/capabilities` package (`getCapabilities()` reads from initial state, no network call) with fallback to `GET /ocs/v2.php/cloud/capabilities?format=json`; cache result for session
- [x] Detect Talk: `!!caps?.spreed`; detect Calendar: `!!caps?.dav`; detect Mail: `!!caps?.mail`
- [x] Implement user info resolution (email via `GET /ocs/v2.php/cloud/users/{userId}?format=json` with headers `OCS-APIREQUEST: true` + `requesttoken`)
- [x] Implement popover with NcPopover + NcActionButton for each action
- [x] Implement "Send message" action — POST to `/ocs/v2.php/apps/spreed/api/v4/room` with `{ roomType: 1, invite: userId }`, navigate to `/apps/spreed/#/call/{token}` from response `ocs.data.token`
- [x] Implement "Start chat" action — same API call but open in new tab via `window.open()`
- [x] Implement "Send email" action — if Mail installed: navigate to `/apps/mail/compose?to={email}`; else: `window.location.href = 'mailto:{email}'`
- [x] Implement "Plan meeting" action — navigate to `/apps/calendar/new?attendees={userId}&title=Meeting`
- [x] Add ARIA roles (role="button" on trigger, role="menu" on popover, role="menuitem" on actions)
- [x] Add keyboard navigation (Enter/Space to open, arrow keys, Escape to close)
- [ ] Test all capability combinations (all installed, none installed, partial)

### Task 2: Create CnNotesCard component
- **spec_ref**: `specs/notes-card/spec.md`
- **files**: `nextcloud-vue/src/components/CnNotesCard/CnNotesCard.vue`
- **acceptance_criteria**:
  - GIVEN a detail page WHEN CnNotesCard renders THEN it shows up to 5 recent notes with author, content, and timestamp
  - GIVEN no notes exist WHEN the card renders THEN it shows "No notes yet" with the input still visible
  - GIVEN a note by another user WHEN the author name is clicked THEN CnUserActionMenu opens
- [x] Implement CnDetailCard wrapper with "Notes" title and `CommentTextOutline` icon (same as sidebar)
- [x] Implement notes list (last 5, reverse chronological) using `GET {apiBase}/objects/{register}/{schema}/{id}/notes` — handle both `data.results` and raw array response formats
- [x] Implement add-note input (textarea + NcButton with Send icon) — POST `{ "message": text }` using `buildHeaders()` from `@conduction/nextcloud-vue/utils`
- [x] Handle dual field format: display `note.actorDisplayName || note.author || 'Unknown'`, `note.message || note.content`, `note.creationDateTime || note.created`
- [x] Implement empty state ("No notes yet")
- [x] Implement "Show all ({count})" footer link that opens sidebar Notes tab
- [x] Integrate CnUserActionMenu on author names (skip for current user)
- [ ] Test note submission and list refresh

### Task 3: Create CnTasksCard component
- **spec_ref**: `specs/tasks-card/spec.md`
- **files**: `nextcloud-vue/src/components/CnTasksCard/CnTasksCard.vue`
- **acceptance_criteria**:
  - GIVEN a detail page WHEN CnTasksCard renders THEN it shows up to 5 tasks sorted by due date with status indicator, summary, assignee, and due date
  - GIVEN a task is overdue WHEN it renders THEN the due date is shown in warning color
  - GIVEN a task assigned to another user WHEN the assignee name is clicked THEN CnUserActionMenu opens
- [x] Implement CnDetailCard wrapper with "Tasks" title and `CheckboxMarkedOutline` icon (same as sidebar)
- [x] Implement tasks list (up to 5, sorted by due date) using `GET {apiBase}/objects/{register}/{schema}/{id}/tasks` — handle `task.title || task.name` for display
- [x] Implement status indicators: `CheckboxMarkedOutline` (completed, `--color-success`), `CheckboxBlankOutline` (available/other) — matching sidebar pattern. Add `ProgressClock` or similar for `active`/`in-process` status
- [x] Implement overdue highlighting using NL Design System error token
- [x] Implement "Show all ({count})" footer link that opens sidebar Tasks tab
- [x] Implement "Unassigned" display for tasks without assignee
- [x] Integrate CnUserActionMenu on assignee names
- [ ] Test status indicators and overdue styling

### Task 4: Integrate cards into Pipelinq RequestDetail
- **spec_ref**: `specs/notes-card/spec.md`, `specs/tasks-card/spec.md`
- **files**: `pipelinq/src/views/requests/RequestDetail.vue`
- **acceptance_criteria**:
  - GIVEN the RequestDetail page WHEN it loads THEN CnNotesCard and CnTasksCard are visible as inline cards below the existing Pipeline card
  - GIVEN both card and sidebar are visible WHEN a note is added via the card THEN the sidebar also reflects the new note
- [ ] Import `CnNotesCard` and `CnTasksCard` from `@conduction/nextcloud-vue`
- [ ] Add notes/tasks data fetching in `mounted()` or a computed — get register/schema from `objectStore.objectTypeRegistry.request`
- [ ] Mount cards after Pipeline card, passing `register`, `schema`, `objectId` (= `requestId`), and `apiBase` props
- [ ] Wire `@note-added` event to refresh notes data and pass updated data to sidebar via `externalNotes` prop
- [ ] Also consider adding cards to LeadDetail, ContactDetail, ClientDetail, ProductDetail (all use CnDetailPage)
- [ ] Test end-to-end on a request detail page

### Task 5: Integrate cards into Procest CaseDetail and TaskDetail
- **spec_ref**: `specs/notes-card/spec.md`, `specs/tasks-card/spec.md`
- **files**: `procest/src/views/cases/CaseDetail.vue`, `procest/src/views/tasks/TaskDetail.vue`
- **acceptance_criteria**:
  - GIVEN CaseDetail or TaskDetail WHEN it loads THEN CnNotesCard and CnTasksCard are visible as inline cards
- [ ] Import and mount CnNotesCard and CnTasksCard in CaseDetail
- [ ] Import and mount CnNotesCard and CnTasksCard in TaskDetail
- [ ] Pass register, schema, and object ID props
- [ ] Test end-to-end on case and task detail pages

### Task 6: Integrate cards into OpenCatalogi detail pages
- **spec_ref**: `specs/notes-card/spec.md`, `specs/tasks-card/spec.md`
- **files**: `opencatalogi/src/views/publications/PublicationDetail.vue` (and other detail views)
- **acceptance_criteria**:
  - GIVEN an OpenCatalogi detail page WHEN it loads THEN CnNotesCard and CnTasksCard are visible as inline cards
- **Blocker**: PublicationDetail uses a legacy pattern (`<script setup>` + custom store + modal-based edit) — it does NOT use `CnDetailPage` or `CnDetailCard`. Options:
  1. Migrate PublicationDetail to `CnDetailPage` first (recommended, separate change)
  2. Use `CnNotesCard`/`CnTasksCard` standalone without `CnDetailPage` — they wrap `CnDetailCard` internally so this should work, but sidebar data sync won't be available
- [ ] Assess which OpenCatalogi detail views exist (currently only PublicationDetail found)
- [ ] Decide on migration vs. standalone approach
- [ ] Import and mount CnNotesCard and CnTasksCard
- [ ] Pass register, schema, and object ID props (OpenCatalogi uses its own store pattern — `objectStore.getActiveObject('publication')`)
- [ ] Test end-to-end

## Verification
- [ ] All tasks checked off
- [ ] `openspec validate` passes
- [ ] Manual testing against acceptance criteria
- [ ] Code review against spec requirements
- [ ] WCAG AA compliance verified (keyboard nav, screen reader, contrast)
