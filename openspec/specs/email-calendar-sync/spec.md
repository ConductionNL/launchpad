# Email & Calendar Sync Specification (Cross-App)

## Purpose

Enable bidirectional email and calendar synchronization across Conduction apps. Emails are automatically linked to entities by matching sender/recipient addresses. Calendar events for follow-ups and meetings are synced with Nextcloud Calendar. This ensures all communication context is captured without manual data entry, leveraging Nextcloud's built-in Mail and Calendar apps.

This capability is cross-app: Pipelinq uses it for CRM contact/lead email linking, Procest uses it for case correspondence tracking, and Docudesk uses it for document-related email threads.

**Consuming apps**: Pipelinq (CRM email/calendar sync), Procest (case correspondence), Docudesk (document exchange emails)
**Tender frequency**: 46% communication/notificaties (32/69); 80% document management includes email
**Standards**: CalDAV (RFC 4791), iCalendar (RFC 5545), IMAP (RFC 3501), vCard (RFC 6350), GDPR/AVG

---

## Requirements

### Requirement 1: Emails MUST be automatically linked to entities

Inbound and outbound emails are matched to entities by email address across all consuming apps.

#### Scenario 1.1: Incoming email matched to CRM contact
- GIVEN contact "Jan de Vries" has email "jan@gemeente-utrecht.nl" in Pipelinq
- AND Nextcloud Mail receives an email from "jan@gemeente-utrecht.nl"
- WHEN the email sync runs
- THEN the email MUST appear in Jan's entity timeline
- AND the email MUST show: subject, sender, date, and body preview

#### Scenario 1.2: Outgoing email matched to CRM contact
- GIVEN a user sends email to "jan@gemeente-utrecht.nl" via Nextcloud Mail
- WHEN the email sync runs
- THEN the email MUST appear in Jan's timeline as an outgoing interaction

#### Scenario 1.3: Email matched to organization by domain
- GIVEN organization "Gemeente Utrecht" has domain "gemeente-utrecht.nl" configured
- AND an email arrives from "info@gemeente-utrecht.nl" (no matching contact)
- THEN the email MUST appear in the organization timeline
- AND the system MUST suggest creating a new contact for the sender

#### Scenario 1.4: Email matched to case in Procest
- GIVEN case "Bouwvergunning #2024-001" has correspondence with "aanvrager@example.nl"
- AND an email arrives from "aanvrager@example.nl" with subject containing "Bouwvergunning"
- WHEN the email sync runs
- THEN the email MUST appear in the case correspondence timeline in Procest

#### Scenario 1.5: No match found
- GIVEN an email from an address not matching any entity
- THEN the email MUST NOT be added to any timeline
- AND the email MUST remain in the user's regular Nextcloud Mail inbox

---

### Requirement 2: Manual email linking to entities

Users can explicitly link emails to specific entities beyond automatic matching.

#### Scenario 2.1: Link email to pipeline item from Mail
- GIVEN a user views an email about a quote for deal "deal-1"
- WHEN the user clicks "Link to [App]" and selects "deal-1"
- THEN the email MUST appear in deal-1's timeline
- AND the email MUST also remain on the contact's timeline

#### Scenario 2.2: Link email from entity detail view
- GIVEN a user is viewing any entity detail
- WHEN they click "Link email" and search recent emails
- THEN matching emails from linked entities MUST be shown
- AND selecting an email MUST create the link

#### Scenario 2.3: Link email to case in Procest
- GIVEN a case handler views case "Zaak-2024-001"
- WHEN they click "Koppel correspondentie" and select a Nextcloud Mail message
- THEN the email MUST be linked to the case
- AND the email MUST appear in the case's correspondence section

---

### Requirement 3: Calendar events MUST sync with Nextcloud Calendar

Follow-ups and meetings created in any app appear in Nextcloud Calendar and vice versa.

#### Scenario 3.1: Create follow-up from app
- GIVEN a user creates a follow-up for entity "Jan de Vries" on March 25 at 14:00
- WHEN the follow-up is saved
- THEN a calendar event MUST be created in the user's Nextcloud Calendar
- AND the event MUST include: title, entity name, and link back to the entity
- AND the event MUST appear on the entity's timeline as a scheduled activity

#### Scenario 3.2: Calendar event with known contact creates timeline entry
- GIVEN a user creates a calendar event with attendee "jan@gemeente-utrecht.nl"
- AND this matches an entity in any consuming app
- WHEN the calendar sync runs
- THEN the event MUST appear in the entity's timeline as type "meeting"

#### Scenario 3.3: Calendar event completion
- GIVEN a follow-up calendar event date has passed
- WHEN the user marks it complete or the date passes
- THEN the timeline entry MUST be updated to reflect the event occurred
- AND the user MUST be prompted to add notes

#### Scenario 3.4: Case deadline as calendar event (Procest)
- GIVEN a case has a legal deadline (wettelijke termijn)
- WHEN the case handler enables calendar sync for the case
- THEN the deadline MUST appear as a calendar event with reminder
- AND the event MUST link back to the case in Procest

---

### Requirement 4: Privacy and scope controls

Not all emails should be synced to apps. User privacy MUST be respected.

#### Scenario 4.1: Configure which mail accounts to sync
- GIVEN a user has 3 email accounts in Nextcloud Mail
- THEN the user MUST be able to select which accounts to sync
- AND personal accounts MUST NOT be synced unless explicitly enabled

#### Scenario 4.2: Exclude specific email threads
- GIVEN an email thread is synced to an entity
- WHEN a user marks the thread as "not relevant"
- THEN the thread MUST be removed from the timeline
- AND future emails in that thread MUST NOT be synced

#### Scenario 4.3: Visibility controls
- GIVEN user A syncs their emails to entity "Jan"
- WHEN user B views Jan's timeline
- THEN user A's synced emails MUST be visible (shared data)
- BUT full email body MUST only be accessible to users with appropriate permissions

#### Scenario 4.4: AVG compliance for government email
- GIVEN email content may contain personal data
- THEN email sync MUST log access to synced content in the audit trail
- AND synced email metadata (subject, date, sender) MUST be distinguishable from synced email body

---

### Requirement 5: Near-real-time sync with conflict handling

Email and calendar sync MUST be frequent and handle conflicts gracefully.

#### Scenario 5.1: Sync frequency
- GIVEN email sync is enabled
- THEN new emails MUST be synced within 5 minutes of arrival
- AND calendar events MUST be synced within 2 minutes of creation/modification

#### Scenario 5.2: Deleted email handling
- GIVEN an email linked to an entity is deleted from Nextcloud Mail
- THEN the timeline entry MUST be marked as "email deleted" but NOT removed
- AND subject and date MUST be preserved for audit purposes

#### Scenario 5.3: Calendar event conflict
- GIVEN a follow-up calendar event is modified in Nextcloud Calendar
- WHEN the next sync runs
- THEN the timeline entry MUST be updated to reflect the new time/date
- AND a note "Afspraak gewijzigd" MUST be added to the timeline

---

### Requirement 6: Per-user sync configuration

Each user controls their own sync preferences.

#### Scenario 6.1: User opt-in
- GIVEN a new user
- THEN email sync MUST be disabled by default
- AND the user MUST explicitly enable it and select mail accounts
- AND an admin MUST be able to enforce sync for all users (organization policy)

#### Scenario 6.2: Sync status indicator
- GIVEN a user has email sync enabled
- THEN their profile settings MUST show: last sync time, number of emails synced, any sync errors

#### Scenario 6.3: Pause and resume sync
- GIVEN a user wants to temporarily stop sync (e.g., during vacation)
- THEN a "Pauzeer synchronisatie" option MUST be available
- AND emails arriving during pause MUST be synced retroactively when resumed

---

### Requirement 7: Email thread tracking

The system MUST track email threads across multiple messages for coherent conversation display.

#### Scenario 7.1: Thread grouping in timeline
- GIVEN 5 emails in the same thread (Re: Re: Re: Subject)
- WHEN displayed in an entity timeline
- THEN the emails MUST be grouped as a single thread entry expandable to show individual messages
- AND the thread MUST show: message count, latest message date, and full subject

#### Scenario 7.2: Thread with multiple entities
- GIVEN an email thread involving 3 known contacts (CC'd)
- THEN the thread MUST appear in all 3 contacts' timelines
- AND each timeline MUST indicate the contact's role (sender, recipient, CC)

#### Scenario 7.3: Thread continuation
- GIVEN a synced thread with 3 messages
- WHEN a 4th message arrives in the same thread
- THEN the existing timeline entry MUST be updated to include the new message
- AND the entry timestamp MUST update to the latest message time

---

### Requirement 8: App-specific email integration

Each consuming app MUST support app-specific email linking behaviors.

#### Scenario 8.1: Pipelinq email-to-lead linking
- GIVEN an email linked to a CRM contact
- THEN the user MUST be able to also link the email to a specific lead
- AND the email MUST appear in both the contact and lead timelines

#### Scenario 8.2: Procest case correspondence
- GIVEN a case in Procest
- THEN all case correspondence MUST be viewable in a dedicated "Correspondentie" tab
- AND outgoing case decisions sent via email MUST be auto-linked to the case

#### Scenario 8.3: Docudesk document exchange
- GIVEN a document shared via email
- THEN the email with attachment MUST be linkable to the document's version history in Docudesk
- AND the attachment MUST be extractable and storable in Nextcloud Files

---

### Requirement 9: Calendar integration for case management

The system MUST support case-specific calendar events for hearings, inspections, and deadlines.

#### Scenario 9.1: Schedule hearing from case
- GIVEN case "Bezwaarschrift #2024-010" in Procest
- WHEN the case handler clicks "Plan hoorzitting"
- THEN a calendar event MUST be created with case reference, participants, and location
- AND the event MUST appear in all participants' Nextcloud Calendars

#### Scenario 9.2: Schedule inspection from case
- GIVEN a VTH case requiring site inspection
- WHEN the inspector schedules an inspection
- THEN a calendar event MUST be created in a shared "Inspecties" calendar
- AND the event MUST link to the case and include the inspection location

#### Scenario 9.3: Deadline calendar with automatic reminders
- GIVEN cases with legal deadlines (beslistermijnen)
- THEN a dedicated "Termijnen" calendar MUST display all upcoming deadlines
- AND reminders MUST be sent at configurable intervals before the deadline (default: 5 days, 2 days, 1 day)

---

### Requirement 10: Sync monitoring and diagnostics

Administrators MUST be able to monitor sync health and diagnose issues.

#### Scenario 10.1: Sync health dashboard
- GIVEN email and calendar sync is active for 20 users
- THEN an admin dashboard MUST show: total users syncing, last sync time per user, error count, total emails/events synced

#### Scenario 10.2: Sync error reporting
- GIVEN an email sync fails for user "maria"
- THEN the error MUST be logged with details (connection timeout, auth failure, etc.)
- AND the admin MUST be able to view error history per user

#### Scenario 10.3: Manual sync trigger
- GIVEN a user's sync appears stale
- THEN an admin MUST be able to trigger an immediate sync for that user
- AND the sync result MUST be displayed in real time

---

## Dependencies

- Nextcloud Mail API (`OCA\Mail\Service\MailManager`)
- Nextcloud Calendar/DAV API (`OCA\DAV\CalDAV\CalDavBackend`)
- OpenRegister (timeline entry storage for linked emails/events)
- Each consuming app's entity matching logic
- Nextcloud background jobs for periodic sync

## Standards & References

- CalDAV (RFC 4791) -- calendar protocol
- iCalendar (RFC 5545) -- event format
- IMAP (RFC 3501) -- email retrieval
- vCard (RFC 6350) -- contact matching via email
- GDPR/AVG -- privacy for email content indexing
- Nextcloud Mail and Calendar app APIs
