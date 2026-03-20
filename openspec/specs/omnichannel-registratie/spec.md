# Omnichannel Registratie Specification (Cross-App)

## Purpose

Omnichannel registratie enables users across Conduction apps to register contact moments from any communication channel (telefoon, e-mail, balie, chat, social media, brief) using a unified data model. Regardless of channel, every contact produces a consistent record that can be linked to clients, cases, and other entities. **54% of klantinteractie-tenders** (28/52) explicitly require omnichannel contact registration.

This is a cross-app capability: Pipelinq provides the primary registration UI for KCC agents, Procest consumes contactmomenten linked to cases, and OpenRegister stores the data with a standardized schema aligned to VNG Klantinteracties.

**Consuming apps**: Pipelinq (primary registration UI), Procest (case-linked contacts), OpenRegister (storage), OpenConnector (external channel integrations)
**Tender frequency**: 28/52 omnichannel (54%); 52/52 KCC werkplek (100%); 51/52 rapportage (98%)
**Standards**: VNG Klantinteracties (Contactmoment, Kanaal), Schema.org (InteractionCounter, CommunicateAction), ISO 8601

---

## Requirements

### Requirement 1: Unified contact registration form

The system MUST provide a single form that adapts based on channel selection while maintaining consistent core data.

#### Scenario 1.1: Register phone contact
- GIVEN channel "Telefoon" selected
- THEN core fields (klant, onderwerp, toelichting, resultaat) plus channel-specific fields (gespreksduur, inkomend/uitgaand) MUST display
- AND the contactmoment MUST store `kanaal: "telefoon"` with duration metadata

#### Scenario 1.2: Register email contact
- GIVEN channel "E-mail" selected
- THEN core fields plus: email subject, sender, recipient, thread-ID MUST display
- AND email linking to Nextcloud Mail SHOULD be supported

#### Scenario 1.3: Register counter (balie) contact
- GIVEN channel "Balie" selected
- THEN core fields plus: locatie, volgnummer MUST display

#### Scenario 1.4: Register chat contact
- GIVEN channel "Chat" selected
- THEN core fields plus: platform (website/WhatsApp), transcript link MUST display

#### Scenario 1.5: Register social media contact
- GIVEN channel "Social media" selected
- THEN core fields plus: platform, message URL, openbaar flag MUST display

---

### Requirement 2: Channel configuration

Administrators MUST be able to configure available channels and custom metadata per channel.

#### Scenario 2.1: Enable/disable channels
- GIVEN admin channel configuration
- WHEN "Social media" is disabled
- THEN it MUST NOT appear in the registration form
- AND existing records MUST remain accessible

#### Scenario 2.2: Custom metadata fields
- GIVEN admin configuring the "Telefoon" channel
- WHEN they add field "Wachtrij" (text, optional)
- THEN the field MUST appear in the form and be stored in metadata

#### Scenario 2.3: Channel icon and color
- GIVEN admin configuring channels
- THEN each channel MUST support icon and color assignment for consistent visual display

#### Scenario 2.4: Cross-app channel consistency
- GIVEN channels configured in Pipelinq
- THEN the same channel definitions MUST be available in Procest for case-linked contacts
- AND channel configuration MUST be stored in a shared location (OpenRegister or IAppConfig)

---

### Requirement 3: Auto-linking to client and case

The system MUST support automatically linking contactmomenten to entities based on context.

#### Scenario 3.1: Auto-link when client identified
- GIVEN the agent has identified a client in the KCC werkplek
- THEN the client field MUST be pre-populated (changeable)

#### Scenario 3.2: Auto-suggest cases
- GIVEN the client has 3 open cases
- THEN the "Koppel aan zaak" field MUST suggest these cases
- AND if Procest is not installed, the field MUST be hidden

#### Scenario 3.3: Create from email
- GIVEN an email from burger@example.nl
- THEN channel "E-mail", sender, and subject MUST be pre-populated
- AND the system MUST search for existing client by email address

#### Scenario 3.4: Auto-link from phone number
- GIVEN incoming call from +31612345678 matching a known contact
- THEN the client field MUST be pre-populated
- AND if multiple matches, all MUST be shown for agent selection

#### Scenario 3.5: Cross-app entity linking
- GIVEN a contactmoment related to both a Pipelinq lead and a Procest case
- THEN both entities MUST be linkable simultaneously on the same contactmoment

---

### Requirement 4: Contactmoment schema (OpenRegister)

A standardized contactmoment schema MUST be defined for structured storage.

#### Scenario 4.1: Schema definition
- THEN a `contactmoment` schema MUST include: timestamp (datetime, required), agent (string, required), client (uuid, optional), contact (uuid, optional), zaak (uuid, optional), request (uuid, optional), kanaal (string, required), onderwerp (string, required), toelichting (string, optional), resultaat (string, optional), metadata (object, optional), initiatiefnemer (string: klant/medewerker)

#### Scenario 4.2: Separate from request entity
- THEN contactmoment MUST be a separate entity type
- AND MAY be linked to zero or more requests/cases
- AND the relationship MUST be stored as references on both entities

#### Scenario 4.3: VNG alignment
- THEN the schema MUST be mappable to VNG `Contactmoment` entity
- AND kanaal values MUST align with VNG `Kanaal` enum
- AND a `registratiedatum` MUST auto-set to creation timestamp

---

### Requirement 5: Channel statistics

The system MUST track contact volume per channel for reporting.

#### Scenario 5.1: Count per channel
- GIVEN 50 contactmomenten today (30 telefoon, 10 email, 5 balie, 3 chat, 2 social)
- THEN accurate counts per channel MUST be queryable in real-time

#### Scenario 5.2: Trends over time
- GIVEN 3 months of data
- THEN time series per channel MUST show volume trends and channel shifts

#### Scenario 5.3: Agent workload per channel
- GIVEN per-agent data for current week
- THEN per agent: total contacts, per channel, average handling time MUST be available

#### Scenario 5.4: Peak hour analysis
- GIVEN 30 days of data
- THEN contact volume per hour per channel MUST identify busiest hours

---

### Requirement 6: Bulk registration

The system MUST support registering multiple contactmomenten at once.

#### Scenario 6.1: Import email batch
- GIVEN 15 emails processed in a morning
- THEN common fields MUST be settable once, per-contact fields per row
- AND all 15 MUST be created in a single operation with success/failure report

#### Scenario 6.2: CSV import for historical data
- GIVEN a CSV from a legacy system
- THEN validation per row, configurable column mapping, and error reporting MUST be supported

#### Scenario 6.3: Bulk registration UI
- THEN a spreadsheet-like table with add/remove rows and inline validation MUST be provided

---

### Requirement 7: Unified inbox

The system MUST provide an aggregated inbox view of incoming contacts across channels.

#### Scenario 7.1: Pending contacts display
- GIVEN 5 unprocessed emails, 3 callbacks, 2 social mentions
- THEN all 10 MUST display in chronological order with channel icon, timestamp, sender, subject

#### Scenario 7.2: Process inbox item
- GIVEN an unprocessed email
- WHEN "Verwerken" is clicked
- THEN the registration form MUST open pre-populated
- AND after saving, the item MUST be marked processed

#### Scenario 7.3: Inbox filtering
- THEN filter by channel (multi-select) MUST be supported

#### Scenario 7.4: Inbox assignment
- GIVEN a supervisor assigns an item to agent "Maria"
- THEN it MUST appear in Maria's personal inbox with notification

---

### Requirement 8: Contact registration timer

An integrated timer MUST track call duration during phone contact registration.

#### Scenario 8.1: Auto-start timer
- GIVEN channel "Telefoon" selected
- THEN a visible timer (MM:SS) MUST auto-start with manual start/stop/reset controls

#### Scenario 8.2: Timer auto-fills duration
- GIVEN the timer shows 04:23 at stop
- THEN gespreksduur MUST auto-fill with "PT4M23S" (ISO 8601), overridable

#### Scenario 8.3: Timer persistence
- GIVEN the timer is running and the agent temporarily navigates away
- THEN the timer MUST continue and form state MUST be preserved on return

---

### Requirement 9: Letter (brief) registration

The system MUST support registering physical letters with scan integration.

#### Scenario 9.1: Incoming letter
- GIVEN channel "Brief"
- THEN fields: ontvangstdatum, kenmerk, richting, scan upload MUST display
- AND scans MUST be stored in Nextcloud Files

#### Scenario 9.2: Outgoing letter
- GIVEN richting "Uitgaand"
- THEN verzenddatum, tracking number, copy upload MUST display

#### Scenario 9.3: Link existing file
- GIVEN an already-scanned document in Nextcloud Files
- THEN a file picker MUST allow linking (not copying) the existing file

---

### Requirement 10: Activity integration

Contactmomenten MUST integrate with the existing activity stream across apps.

#### Scenario 10.1: Client activity timeline
- GIVEN a contactmoment for client "Jan de Vries"
- THEN it MUST appear in the client's activity timeline with channel icon, timestamp, agent, subject

#### Scenario 10.2: Case activity timeline
- GIVEN a contactmoment linked to a case
- THEN it MUST appear in the case's timeline (in Procest)

#### Scenario 10.3: Activity publishing
- GIVEN a new contactmoment
- THEN a `contactmoment_created` activity event MUST be published via ActivityService
- AND appear in Nextcloud activity app for the agent and linked assignees

---

### Requirement 11: KCC integration points (Enterprise)

Integration hooks for external systems MUST be provided.

#### Scenario 11.1: CTI screen pop
- GIVEN an incoming call with caller ID from PBX
- THEN auto-search by phone and pre-populate if matched

#### Scenario 11.2: Nextcloud Talk integration
- GIVEN a chat in Nextcloud Talk
- THEN "Registreer als contactmoment" MUST create a contact with chat transcript linked

#### Scenario 11.3: External webhook
- GIVEN an external system POSTs to `/api/contactmomenten/webhook`
- THEN the payload MUST be validated and a contactmoment created with API key auth

---

### Requirement 12: Search and filter contactmomenten

The system MUST provide search and filter capabilities.

#### Scenario 12.1: Keyword search
- GIVEN 200 contactmomenten
- WHEN searching "parkeervergunning"
- THEN matches in onderwerp or toelichting MUST be returned, sorted by timestamp

#### Scenario 12.2: Combined filters
- GIVEN filters: kanaal "Telefoon" + date range "March 2024"
- THEN only matching contactmomenten MUST display
- AND filters MUST be combinable (channel + date + agent + client)

#### Scenario 12.3: Filter by outcome
- GIVEN filter resultaat "terugbelverzoek"
- THEN only contactmomenten with that outcome MUST display

#### Scenario 12.4: Export to CSV
- GIVEN a filtered set
- THEN a CSV with columns Datum, Kanaal, Medewerker, Klant, Onderwerp, Resultaat, Toelichting MUST be downloadable

---

## Data Model

### Contactmoment Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `timestamp` | datetime | YES | When contact occurred |
| `agent` | string | YES | Nextcloud user ID |
| `client` | string (uuid) | no | Client entity reference |
| `contact` | string (uuid) | no | Contact person reference |
| `zaak` | string (uuid) | no | Linked case reference |
| `request` | string (uuid) | no | Linked request reference |
| `kanaal` | string | YES | Channel type |
| `onderwerp` | string | YES | Subject/topic |
| `toelichting` | string | no | Detailed notes |
| `resultaat` | string | no | Outcome |
| `metadata` | object | no | Channel-specific metadata |
| `initiatiefnemer` | string | no | "klant" or "medewerker" |
| `registratiedatum` | datetime | auto | Auto-set to creation time |

---

## Dependencies

- OpenRegister (contactmoment storage)
- Pipelinq (registration UI, client entities)
- Procest (case linking, optional)
- Nextcloud Mail (email linking)
- Nextcloud Talk (chat integration)
- Nextcloud Files (letter scan storage)
- Activity and Notification services

## Standards & References

- VNG Klantinteracties API -- Contactmoment, Kanaal
- Schema.org -- InteractionCounter, CommunicateAction
- Common Ground -- contact registration
- ISO 8601 -- duration/datetime formats
- WCAG AA -- form accessibility
