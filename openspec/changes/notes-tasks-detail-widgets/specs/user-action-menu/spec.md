# User Action Menu Specification

## Purpose
Provides a reusable popover component (`CnUserActionMenu`) that appears when clicking a user's name, offering contextual actions to communicate with or schedule meetings with that user.

## Current Behavior
Today, user names in the sidebar (note authors, task assignees) are rendered as plain text with no interactivity. The sidebar renders note authors as `<strong>{{ note.actorDisplayName || note.author || 'Unknown' }}</strong>` and task assignees as plain strings in `NcListItem` subname. There is no mechanism to interact with other users from within the app — users must manually navigate to Talk, Mail, or Calendar to contact someone.

No component in `@conduction/nextcloud-vue` currently uses the Nextcloud OCS Capabilities API or Talk/Mail/Calendar APIs. The `@nextcloud/capabilities` package (v1.2.1) is available as an indirect dependency through `@nextcloud/vue` and provides `getCapabilities()` which synchronously reads from the page's initial state.

## ADDED Requirements

### Requirement: User action menu display
The system MUST display a popover menu when a user's name is clicked, showing available communication actions.

#### Scenario: Opening the menu
- GIVEN a clickable user name in any component (notes card, tasks card, or other)
- WHEN the user clicks the display name
- THEN a popover MUST appear anchored to the clicked element
- AND the popover MUST show the user's avatar, display name, and available actions
- AND the popover MUST be dismissable by clicking outside or pressing Escape

#### Scenario: Menu positioning
- GIVEN a user name near the edge of the viewport
- WHEN the menu is opened
- THEN the popover MUST reposition to remain fully visible within the viewport

### Requirement: Capability-based action visibility
The system MUST only show actions for which the required Nextcloud app is installed and accessible.

#### Scenario: All apps installed
- GIVEN Nextcloud Talk, Mail, and Calendar are all installed
- WHEN the user action menu is opened
- THEN it MUST show all four actions: "Send message", "Start chat", "Send email", "Plan meeting"

#### Scenario: Talk not installed
- GIVEN `getCapabilities()?.spreed` is falsy (Talk not installed)
- WHEN the user action menu is opened
- THEN "Send message" and "Start chat" actions MUST NOT be shown

#### Scenario: Mail not installed and no email available
- GIVEN `getCapabilities()?.mail` is falsy AND the target user has no email address (from OCS Users API)
- WHEN the user action menu is opened
- THEN "Send email" action MUST NOT be shown

#### Scenario: Mail not installed but email available
- GIVEN `getCapabilities()?.mail` is falsy BUT the target user has an email address
- WHEN the user action menu is opened
- THEN "Send email" action MUST still be shown (using `mailto:` fallback)

#### Scenario: Calendar not installed
- GIVEN `getCapabilities()?.dav` is falsy (CalDAV/Calendar not available)
- WHEN the user action menu is opened
- THEN "Plan meeting" action MUST NOT be shown

#### Scenario: No apps installed
- GIVEN none of Talk, Mail, or Calendar are installed
- WHEN the user action menu is opened
- THEN the menu MUST show a message "No communication apps available"

### Requirement: Send message action
The "Send message" action MUST open or create a 1:1 Talk conversation with the target user.

#### Scenario: Starting a message
- GIVEN the user action menu is open for user "jan"
- WHEN the current user clicks "Send message"
- THEN the system MUST POST to `/ocs/v2.php/apps/spreed/api/v4/room` with body `{ "roomType": 1, "invite": "jan" }` and headers `OCS-APIREQUEST: true`, `requesttoken: OC.requestToken`, `Content-Type: application/json`
- AND on success, navigate the browser to `/apps/spreed/#/call/{ocs.data.token}` (same tab)
- AND on failure, show an error via `showError()` from `@nextcloud/dialogs`

### Requirement: Start chat action
The "Start chat" action MUST navigate to an existing Talk conversation or create a new one.

#### Scenario: Starting a chat
- GIVEN the user action menu is open for user "jan"
- WHEN the current user clicks "Start chat"
- THEN the system MUST open the Talk conversation with "jan" in a new tab
- AND the conversation MUST be a 1:1 direct message room

### Requirement: Send email action
The "Send email" action MUST open an email compose interface for the target user.

#### Scenario: Sending email with Mail app
- GIVEN `getCapabilities()?.mail` is truthy AND the target user has email "jan@example.nl" (resolved via `GET /ocs/v2.php/cloud/users/jan?format=json` → `ocs.data.email`)
- WHEN the current user clicks "Send email"
- THEN the system MUST navigate to `/apps/mail/compose?to=jan@example.nl`

#### Scenario: Sending email without Mail app
- GIVEN `getCapabilities()?.mail` is falsy AND the target user has email "jan@example.nl"
- WHEN the current user clicks "Send email"
- THEN the system MUST open a `mailto:jan@example.nl` link (via `window.location.href`)

#### Scenario: User has no email address
- GIVEN the OCS Users API returns no email for the target user (empty string or null)
- WHEN the user action menu is opened
- THEN "Send email" action MUST NOT be shown regardless of Mail app availability

### Requirement: Plan meeting action
The "Plan meeting" action MUST open the Calendar app with a pre-filled event including the target user as attendee.

#### Scenario: Planning a meeting
- GIVEN Nextcloud Calendar is installed
- WHEN the current user clicks "Plan meeting" for user "jan"
- THEN the system MUST navigate to the Calendar app's new event view
- AND the event MUST have "jan" pre-added as an attendee

### Requirement: Accessibility
The user action menu MUST be fully accessible via keyboard and screen readers.

#### Scenario: Keyboard navigation
- GIVEN a clickable user name is focused
- WHEN the user presses Enter or Space
- THEN the action menu MUST open
- AND arrow keys MUST navigate between actions
- AND Enter MUST activate the focused action
- AND Escape MUST close the menu and return focus to the trigger

#### Scenario: Screen reader announcements
- GIVEN the action menu is rendered
- WHEN a screen reader reads the component
- THEN the trigger MUST have `role="button"` and `aria-haspopup="menu"`
- AND the popover MUST have `role="menu"` with `aria-label="Actions for {userName}"`
- AND each action MUST have `role="menuitem"`

### Requirement: Edge cases

#### Scenario: User email resolution failure
- GIVEN the OCS Users API call to resolve email fails (network error, 404, etc.)
- WHEN the menu opens
- THEN "Send email" action MUST be hidden (fail gracefully)
- AND other actions (Talk, Calendar) MUST still appear if their apps are installed

#### Scenario: Talk room creation failure
- GIVEN the user clicks "Send message"
- WHEN the Talk API POST fails
- THEN the system MUST show an error via `showError()` from `@nextcloud/dialogs`
- AND the menu MUST remain open (not navigate away)

#### Scenario: User ID is a system account
- GIVEN the note author or task assignee is a system/service account (e.g., "system", "cron")
- WHEN the card renders
- THEN the name SHOULD still be displayed but the `CnUserActionMenu` SHOULD NOT be triggered (system accounts cannot receive messages)
- NOTE: Detection of system accounts may require checking the user type via OCS API or maintaining a known-system-accounts list

#### Scenario: Capability state caching
- GIVEN capabilities are read from `@nextcloud/capabilities` initial state
- WHEN the menu is opened multiple times during the same page session
- THEN it MUST reuse the cached capability data (no repeated API calls)
- AND if `@nextcloud/capabilities` is unavailable as a direct dependency, the component MUST fetch from `/ocs/v2.php/cloud/capabilities?format=json` ONCE and cache for the session
