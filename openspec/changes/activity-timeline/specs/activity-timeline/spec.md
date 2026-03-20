# Activity Timeline Specification (Cross-App)

## Purpose

Provide a chronological, unified activity feed per entity across all Conduction apps. Every interaction -- status changes, notes, emails, calls, document uploads, field changes, and linked events from other apps -- appears in one timeline. This gives users the complete history of any entity at a glance, whether it is a CRM contact (Pipelinq), a case (Procest), a document (Docudesk), or a register object (OpenRegister).

Activity timelines are the single most requested CRM feature and are essential for government "klantbeeld" (customer view) requirements. They answer "what happened with this entity?" without searching multiple views. This spec defines the cross-app shared capability; each app implements its own event types and UI integration.

**Consuming apps**: Pipelinq (CRM contacts, leads, requests), Procest (cases, tasks), Docudesk (documents), OpenRegister (any registered object)
**Tender frequency**: 88% of tenders require rapportage/dashboards; 83% require klantbeeld-360 (both depend on activity timeline data)
**Standards**: VNG Klantinteracties `Contactmoment`, Schema.org `Action`/`InteractionCounter`, Nextcloud Activity API

---

## Requirements

### Requirement 1: Every entity MUST have a timeline view

All entities across consuming apps must display a unified activity timeline in their detail views.

#### Scenario 1.1: View entity timeline in CRM context
- GIVEN contact "Jan de Vries" has had 15 interactions over the past month in Pipelinq
- WHEN a user opens Jan's contact detail page
- THEN the timeline tab MUST show all 15 interactions in reverse chronological order
- AND each entry MUST display: timestamp, actor (who did it), action type icon, and description

#### Scenario 1.2: View entity timeline in case management context
- GIVEN case "Bouwvergunning #2024-001" has 20 status changes, notes, and document events in Procest
- WHEN a case handler opens the case detail page
- THEN the timeline MUST show all 20 entries aggregated from case events, linked documents, and internal notes
- AND each entry MUST indicate the event source (Procest, Docudesk, or OpenRegister)

#### Scenario 1.3: View entity timeline for organization with aggregated contacts
- GIVEN organization "Gemeente Utrecht" has 3 contacts with combined 40 timeline entries across Pipelinq
- WHEN a user opens the organization detail page
- THEN the timeline MUST show all 40 entries aggregated from all linked contacts
- AND each entry MUST indicate which contact it relates to

#### Scenario 1.4: Timeline for OpenRegister objects
- GIVEN an OpenRegister object of any schema has been created, updated 5 times, and had 2 notes added
- WHEN a user opens the object detail view
- THEN the timeline MUST show all 8 events (1 creation + 5 updates + 2 notes) in reverse chronological order
- AND field-level changes from updates MUST be shown with before/after values

#### Scenario 1.5: Empty timeline for new entities
- GIVEN a newly created entity with no prior history
- WHEN a user opens the detail view
- THEN the timeline MUST show a single "Created" entry with the creation timestamp and actor
- AND an empty state message "Nog geen activiteiten" MUST be displayed below

---

### Requirement 2: Timeline MUST capture all interaction types

The timeline automatically records a comprehensive set of event types, standardized across all consuming apps.

#### Scenario 2.1: Status/stage change recorded
- GIVEN a pipeline item in Pipelinq is moved from "Offerte" to "Onderhandeling"
- THEN a timeline entry MUST be created with type `status_change`, description "Status gewijzigd van Offerte naar Onderhandeling", actor, and metadata `{"from": "Offerte", "to": "Onderhandeling"}`

#### Scenario 2.2: Case status change recorded in Procest
- GIVEN case "Zaak-2024-001" changes from "In behandeling" to "Besluit genomen" in Procest
- THEN a timeline entry MUST be created with the same `status_change` type
- AND the entry MUST be visible both on the case timeline and on the linked client's timeline in Pipelinq

#### Scenario 2.3: Note added
- GIVEN a user adds a note "Telefonisch gesproken, klant is geinteresseerd" to any entity
- THEN a timeline entry MUST be created with type `note`, content as the full note text, and actor as the note author

#### Scenario 2.4: Document uploaded or linked
- GIVEN a user uploads "offerte-2026-q1.pdf" to any entity (via Pipelinq, Procest, or Docudesk)
- THEN a timeline entry MUST be created with type `document`, description "Document toegevoegd: offerte-2026-q1.pdf", and metadata with fileName and fileId

#### Scenario 2.5: Field value changed
- GIVEN a user changes any tracked field on an entity (e.g., expectedValue from 50000 to 75000)
- THEN a timeline entry MUST be created with type `field_change`, description showing old and new values, and metadata with field name, old value, and new value

---

### Requirement 3: Timeline MUST support manual entries

Users can add notes, log calls, and record meetings manually in all consuming apps.

#### Scenario 3.1: Log a phone call
- GIVEN a user clicks "Log gesprek" on any entity in Pipelinq or Procest
- WHEN they fill in duration, summary, and outcome
- THEN a timeline entry MUST be created with type `call`
- AND the entry MUST be visible on both the entity's and any related entity's timeline

#### Scenario 3.2: Log a meeting
- GIVEN a user clicks "Log meeting" on any entity
- WHEN they fill in date, duration, participants, and summary
- THEN a timeline entry MUST be created with type `meeting`
- AND all participants who are known entities MUST have this entry on their timelines

#### Scenario 3.3: Log an email interaction
- GIVEN a user clicks "Log e-mail" on a contact
- WHEN they fill in subject, recipient, and summary
- THEN a timeline entry MUST be created with type `email`, direction "outbound", and metadata with subject and recipient

---

### Requirement 4: Timeline MUST be filterable and searchable

Users need to find specific interactions quickly within potentially large timelines.

#### Scenario 4.1: Filter by activity type
- GIVEN an entity has 50 timeline entries of mixed types
- WHEN a user filters by type `note`
- THEN only note entries MUST be shown
- AND the filter controls MUST support multi-select (e.g., show notes and calls together)

#### Scenario 4.2: Search timeline content
- GIVEN an entity has 100 timeline entries
- WHEN a user searches for "offerte"
- THEN only entries containing "offerte" in their description or content MUST be shown
- AND the search term MUST be highlighted in results

#### Scenario 4.3: Date range filter
- GIVEN an entity has timeline entries spanning 2 years
- WHEN a user filters to "last 30 days"
- THEN only entries from the last 30 days MUST be shown
- AND filter presets MUST include: vandaag, deze week, deze maand, laatste 30 dagen, aangepast bereik

#### Scenario 4.4: Filter by actor
- GIVEN an entity has been worked on by users "admin", "handler1", and "handler2"
- WHEN a user filters by actor "handler1"
- THEN only entries where the actor is "handler1" MUST be shown

#### Scenario 4.5: Combined filters
- GIVEN active filters: type="note", date range="last 7 days"
- WHEN the user views the timeline
- THEN only notes from the last 7 days MUST be displayed
- AND a filter summary badge MUST show "2 filters active"

---

### Requirement 5: Timeline entries MUST be stored as OpenRegister objects

Timeline entries must be queryable per entity using OpenRegister's API, separate from the Nextcloud Activity global stream.

#### Scenario 5.1: Timeline entry persisted on note addition
- GIVEN a user adds a note to any entity in any consuming app
- THEN an OpenRegister object MUST be created in the timeline schema with: entityType, entityId, activityType "note", content, actor (user UID), timestamp (ISO 8601), and metadata
- AND the Nextcloud Activity event MUST still be published in parallel for the global activity stream

#### Scenario 5.2: Timeline entry persisted on automatic event
- GIVEN any entity changes status
- THEN an OpenRegister timeline object MUST be created with activityType "status_change", entityType, entityId, and metadata with from/to values
- AND the timeline object MUST reference the register configured for the app

#### Scenario 5.3: Timeline data queryable via API
- GIVEN entity "contact-1" has 200 timeline entries
- WHEN `GET /api/{app}/timeline/{entityType}/{entityId}?page=1&limit=20` is called
- THEN the first 20 entries (newest first) MUST be returned with pagination metadata

---

### Requirement 6: Cross-app timeline integration

Activities from linked entities across apps must be visible in aggregated timelines.

#### Scenario 6.1: Case events visible in CRM contact timeline
- GIVEN contact "Jan de Vries" in Pipelinq is linked to case "zaak-1" in Procest
- AND case "zaak-1" changes status to "besluit_genomen"
- THEN the contact timeline in Pipelinq MUST show: "Zaak ZK-2026-001: Status gewijzigd naar Besluit genomen"
- AND clicking the entry MUST navigate to the case in Procest

#### Scenario 6.2: Document events from Docudesk visible in case timeline
- GIVEN a document "besluit.pdf" is uploaded to case "zaak-1" via Docudesk
- THEN the case timeline in Procest MUST show the document upload event
- AND the linked contact's timeline in Pipelinq MUST also show the event

#### Scenario 6.3: Client timeline aggregates contact and lead activities
- GIVEN client "Gemeente Utrecht" has 2 linked contacts and 3 linked leads in Pipelinq
- WHEN the user views the client's timeline
- THEN all entries from contacts, leads, and the client itself MUST be shown in unified reverse chronological order
- AND each entry MUST display a badge indicating its source entity

---

### Requirement 7: Timeline entries MUST be grouped by date in the UI

The timeline view must visually group entries by calendar date with clear date separators.

#### Scenario 7.1: Timeline renders date groups
- GIVEN an entity has 5 entries from today, 3 from yesterday, and 2 from last week
- WHEN the user views the timeline tab
- THEN entries MUST be grouped under date headers: "Vandaag", "Gisteren", and the specific date (e.g., "13 maart 2026")
- AND within each group, entries MUST be ordered newest-first

#### Scenario 7.2: Activity type icons distinguish entry types
- GIVEN a timeline contains entries of types note, call, email, meeting, status_change, assignment, document, and field_change
- THEN each type MUST display a distinct icon (pencil for note, phone for call, envelope for email, calendar for meeting, arrow for status_change, person for assignment, file for document, swap for field_change)
- AND each type MUST have a translatable label (Dutch + English minimum)

#### Scenario 7.3: Relative timestamps with absolute tooltip
- GIVEN a timeline entry was created 3 hours ago
- THEN the entry MUST display "3u geleden" (or "3h ago" in English)
- AND hovering MUST show the full ISO 8601 timestamp in a tooltip

---

### Requirement 8: Activity notifications MUST be configurable per type

Users must be able to enable or disable notifications for each activity type independently.

#### Scenario 8.1: User disables call activity notifications
- GIVEN a user navigates to Personal Settings > Activity
- WHEN they uncheck call activity notifications for email
- THEN call-type timeline entries MUST NOT generate email notifications for that user
- AND call entries MUST still appear in the activity stream and entity timeline

#### Scenario 8.2: Default notification settings
- GIVEN a fresh installation with activity types: assignment, stage_status, note, call, meeting, email, document, field_change
- THEN each type MUST have an IActivitySetting implementation
- AND all types MUST default to "enabled" for activity stream display
- AND email/push notifications MUST default to "enabled" only for assignment and stage_status types

#### Scenario 8.3: App-specific notification categories
- GIVEN Pipelinq and Procest both publish timeline events
- THEN the Activity settings MUST show categories per app ("Pipelinq", "Procest")
- AND users MUST be able to independently control notifications for each app's event types

---

### Requirement 9: Scheduled activities MUST support follow-up reminders

Users must be able to schedule follow-up activities with due dates that trigger reminders.

#### Scenario 9.1: Schedule a follow-up call
- GIVEN a user is viewing any entity's timeline
- WHEN they click "Plan opvolging" and set type "call", due date, and note
- THEN a timeline entry MUST be created with activityType "scheduled_call", dueDate, status "pending", and content

#### Scenario 9.2: Overdue follow-up generates notification
- GIVEN a scheduled follow-up has passed its due date
- THEN a Nextcloud notification MUST be sent to the assigned user
- AND the timeline entry MUST display a warning indicator

#### Scenario 9.3: Complete a scheduled activity
- GIVEN a scheduled follow-up exists with status "pending"
- WHEN the user marks it as completed
- THEN the timeline entry status MUST change to "completed"
- AND a new timeline entry MUST record the completion

---

### Requirement 10: Activity export MUST support CSV and JSON formats

Timeline data must be exportable for reporting and compliance (WOO, AVG audit).

#### Scenario 10.1: Export entity timeline as CSV
- GIVEN an entity has 45 timeline entries
- WHEN the user clicks "Exporteer tijdlijn" and selects CSV
- THEN a CSV file MUST be downloaded with columns: Datum, Type, Beschrijving, Gebruiker, Inhoud
- AND all 45 entries MUST be included regardless of current filter settings
- AND timestamps MUST be formatted as "dd-mm-yyyy HH:mm" in Dutch locale

#### Scenario 10.2: Export filtered timeline as JSON
- GIVEN a timeline is filtered to show only "call" and "meeting" entries
- WHEN the user clicks "Exporteer gefilterd" and selects JSON
- THEN a JSON file MUST be downloaded containing only the filtered entries
- AND each entry MUST include all fields: id, entityType, entityId, activityType, actor, timestamp, content, metadata

#### Scenario 10.3: Export for compliance reporting
- GIVEN a government compliance requirement (WOO/AVG audit)
- WHEN an admin exports all timeline data for a specific entity
- THEN the export MUST include an immutable audit signature (hash of contents)
- AND the export MUST be available in both CSV and JSON formats

---

### Requirement 11: Activity reporting MUST provide productivity metrics

Managers must be able to view aggregated activity statistics per user for performance tracking.

#### Scenario 11.1: View agent activity summary
- GIVEN user "handler1" has logged 15 calls, 8 meetings, 25 notes, and 5 emails in the current month
- WHEN a manager views the activity productivity dashboard
- THEN a summary card MUST show: total activities (53), breakdown by type, and comparison to previous month

#### Scenario 11.2: Activity count per pipeline stage or case type
- GIVEN activities are distributed across pipeline stages (Pipelinq) or case types (Procest)
- WHEN a manager views the activity report
- THEN each stage/type MUST display total activities logged in the current period
- AND stages/types with zero activities in the last 7 days MUST be highlighted as potentially stale

#### Scenario 11.3: Cross-app activity dashboard
- GIVEN a user works in both Pipelinq and Procest
- WHEN a manager views the unified activity report
- THEN activities from both apps MUST be aggregated with app source indicated
- AND the report MUST be filterable by app, user, date range, and activity type

---

### Requirement 12: Dual-write to Nextcloud Activity and OpenRegister

The existing Nextcloud Activity integration must remain the global notification backbone while per-entity timelines use OpenRegister storage.

#### Scenario 12.1: Dual-write ensures both systems receive events
- GIVEN an entity's stage changes
- THEN the app's ActivityService MUST publish to Nextcloud Activity (global stream, notification delivery)
- AND a timeline OpenRegister object MUST be created (per-entity queryable timeline)
- AND both records MUST contain the same actor, timestamp, and event metadata

#### Scenario 12.2: Nextcloud Activity Filter shows app-specific events
- GIVEN a user has activities from multiple apps
- WHEN they click an app-specific filter in the Activity app sidebar
- THEN only events from that app MUST be shown
- AND the filter MUST include all activity types registered by that app

#### Scenario 12.3: OpenRegister timeline survives Activity retention cleanup
- GIVEN Nextcloud Activity has a 365-day retention policy
- AND an entity timeline entry is 18 months old
- WHEN the Nextcloud Activity cleanup job runs
- THEN the entry MUST be deleted from the Activity table
- BUT the OpenRegister timeline object MUST be preserved for the entity's full history

---

### Requirement 13: Timeline MUST support i18n

All timeline labels, type names, and system messages must support Dutch and English as minimum languages.

#### Scenario 13.1: Timeline renders in user's language
- GIVEN a user's Nextcloud language is set to "nl"
- WHEN they view any entity timeline
- THEN all system-generated labels MUST be in Dutch: "Vandaag", "Gisteren", "Status gewijzigd", "Notitie toegevoegd"

#### Scenario 13.2: English language support
- GIVEN a user's Nextcloud language is set to "en"
- WHEN they view any entity timeline
- THEN all system-generated labels MUST be in English: "Today", "Yesterday", "Status changed", "Note added"

#### Scenario 13.3: User-entered content preserves original language
- GIVEN a user enters a note in Dutch
- AND another user has their language set to English
- WHEN the English user views the timeline
- THEN the note content MUST be displayed as-is in Dutch (user content is not translated)
- AND only system labels around the note MUST be in English

---

## Data Model

### Timeline Entry Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `entityType` | string | YES | The type of entity (contact, lead, case, document, etc.) |
| `entityId` | string (uuid) | YES | UUID of the entity this entry belongs to |
| `activityType` | string | YES | Event type: note, call, email, meeting, status_change, assignment, document, field_change, scheduled_call, scheduled_meeting |
| `actor` | string | YES | Nextcloud user UID who performed the action |
| `timestamp` | datetime | YES | ISO 8601 timestamp of the event |
| `content` | string | no | Free-text content (note body, call summary, etc.) |
| `description` | string | no | System-generated human-readable description |
| `metadata` | object | no | Structured data specific to the activity type |
| `sourceApp` | string | YES | Originating app identifier (pipelinq, procest, docudesk, openregister) |
| `relatedEntities` | array | no | Array of {entityType, entityId} for cross-entity display |
| `dueDate` | datetime | no | For scheduled activities: when the follow-up is due |
| `status` | string | no | For scheduled activities: pending, completed, cancelled |

---

## Dependencies

- OpenRegister (timeline entry storage and query API)
- Nextcloud Activity API (`OCP\Activity\IManager`, `IProvider`, `IFilter`, `ISetting`)
- Nextcloud Notification API (`OCP\Notification\IManager`)
- Each consuming app's event system (ObjectCreatedEvent, ObjectUpdatedEvent)

## Standards & References

- Nextcloud Activity API -- event publishing and notification delivery
- OpenRegister audit log -- basic versioned change history per object
- Schema.org `Action` / `InteractionCounter` -- timeline event modeling
- VNG Klantinteracties `Contactmoment` -- government-facing timeline entries
- WCAG AA -- accessibility for timeline UI components
- WOO (Wet open overheid) -- compliance export requirements
- AVG -- audit trail requirements for personal data access
