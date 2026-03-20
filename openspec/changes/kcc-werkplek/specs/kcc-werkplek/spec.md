# KCC Werkplek Specification (Cross-App)

## Purpose

The KCC werkplek (frontoffice workspace) is the unified agent screen for KCC (Klant Contact Centrum) employees. It combines citizen/business identification, open case visibility, contact moment registration, knowledge base access, and backoffice routing into a single interface. This is the most demanded capability in Dutch government tenders: **100% of 52 klantinteractie-tenders** require an integrated KCC workspace.

The KCC werkplek is inherently cross-app: it pulls client data from Pipelinq, case data from Procest, knowledge articles from the kennisbank, and enrichment data from BRP/KVK via OpenConnector. It is the primary consumer of klantbeeld-360, omnichannel-registratie, kennisbank, and terugbel-taakbeheer capabilities.

**Consuming apps**: Pipelinq (primary host), Procest (case data), OpenConnector (BRP/KVK), OpenRegister (data layer)
**Tender frequency**: 52/52 KCC tenders (100%); 43/52 klantbeeld (83%); 28/52 omnichannel (54%)
**Standards**: VNG Klantinteracties, Haal Centraal BRP API, KVK API, ZGW Zaken API, NEN-ISO 18295, WCAG AA

---

## Requirements

### Requirement 1: Agent dashboard landing

The system MUST provide a dedicated KCC agent landing screen with queue overview and quick actions.

#### Scenario 1.1: Agent opens KCC werkplek
- GIVEN a KCC agent with appropriate role permissions
- WHEN they navigate to the KCC werkplek
- THEN the dashboard MUST display: active queue count, recent contactmomenten (last 10), quick-action buttons for "Nieuw contact" and "Zoek klant"
- AND personal statistics for today (contacts handled, average handling time)

#### Scenario 1.2: Queue overview
- GIVEN 5 contacts waiting in the queue
- THEN the dashboard MUST display: caller/contact identification, wait time per contact, channel
- AND contacts MUST be ordered by wait time descending

#### Scenario 1.3: Multi-app dashboard widgets
- GIVEN Pipelinq and Procest are both installed
- THEN the dashboard MUST show combined statistics from both apps
- AND widgets MUST indicate data source (CRM / Zaaksysteem)

---

### Requirement 2: Citizen/business identification

The system MUST allow agents to quickly identify a citizen or business using BSN, KVK, or name search.

#### Scenario 2.1: Identify citizen by BSN
- GIVEN an agent enters BSN "123456789"
- THEN the system MUST query BRP via OpenConnector
- AND display name, address, date of birth, municipality
- AND automatically search for an existing client

#### Scenario 2.2: Identify business by KVK
- GIVEN an agent enters KVK "12345678"
- THEN the system MUST query KVK via OpenConnector
- AND display business name, address, legal form, signatories
- AND search for existing client

#### Scenario 2.3: Search by name or phone
- GIVEN an agent searches "Jansen" or "+31 6 12345678"
- THEN existing clients MUST be searched by name and telephone
- AND matching results ranked by relevance

#### Scenario 2.4: No matching client found
- GIVEN BSN identification succeeds but no existing client matches
- THEN "Nieuwe klant aanmaken" MUST be offered with BRP data pre-populated

#### Scenario 2.5: CTI screen pop (Enterprise)
- GIVEN an incoming phone call with caller ID
- THEN the system MUST auto-search by phone number
- AND if found, pre-populate the workspace with the identified client

---

### Requirement 3: Open cases view

The system MUST display all open cases for the identified citizen/business.

#### Scenario 3.1: Display open cases
- GIVEN citizen "Jan de Vries" has 3 open cases in Procest
- THEN all 3 MUST display: zaaktype, status, start date, handler
- AND each MUST be clickable for details

#### Scenario 3.2: Case details inline
- GIVEN the agent clicks on a case
- THEN details MUST display in a side panel: zaaktype, status history, documents, handler
- AND the agent MUST NOT leave the KCC werkplek

#### Scenario 3.3: No open cases
- GIVEN the identified citizen has no cases
- THEN "Geen openstaande zaken" MUST be displayed with option to create a new case

#### Scenario 3.4: Combined CRM and case view
- GIVEN the citizen has 2 leads in Pipelinq and 3 cases in Procest
- THEN both leads and cases MUST be shown in the context panel
- AND they MUST be grouped by app source with clear labels

---

### Requirement 4: Contact moment registration

The system MUST allow agents to register contactmomenten during or after interactions.

#### Scenario 4.1: Register phone contact
- GIVEN an agent has identified a citizen during a phone call
- WHEN they fill in channel "telefoon", subject, and notes
- THEN an OpenRegister contactmoment object MUST be created linked to the client
- AND it MUST record agent identity, timestamp, channel, and duration

#### Scenario 4.2: Link contact to existing case
- GIVEN the citizen has an open case
- WHEN the agent selects the case in "Koppel aan zaak"
- THEN the contactmoment MUST reference the case
- AND appear in both client history and case history

#### Scenario 4.3: Register without identification
- GIVEN a caller who refuses to identify
- THEN contactmoment registration MUST be allowed without a client reference
- AND agent, timestamp, channel, and content MUST still be recorded

#### Scenario 4.4: Cross-app contact registration
- GIVEN the contact relates to both a CRM lead and a Procest case
- THEN the contactmoment MUST be linkable to entities in both apps simultaneously

---

### Requirement 5: Three-panel workspace layout

The workspace MUST present a structured multi-panel layout for simultaneous identification, context viewing, and registration.

#### Scenario 5.1: Three-panel layout
- GIVEN the agent begins a new contact
- THEN three panels MUST display: identification/search (left), klantbeeld-360 (center), active registration (right)
- AND each panel MUST be independently scrollable

#### Scenario 5.2: Panel resizing
- GIVEN the agent needs more space for case history
- THEN panels MUST be resizable with minimum 300px width
- AND size preferences MUST persist via user preferences

#### Scenario 5.3: Keyboard navigation
- GIVEN the agent is working in the identification panel
- THEN keyboard shortcuts (Ctrl+1/2/3) MUST move focus between panels
- AND all elements MUST be keyboard-accessible (WCAG AA)

#### Scenario 5.4: Responsive collapse
- GIVEN a screen narrower than 1280px
- THEN the layout MUST collapse to a tabbed view
- AND no functionality MUST be lost

---

### Requirement 6: Klantbeeld-360 integration

The center panel MUST embed the klantbeeld-360 view showing all interactions, cases, documents, and notes.

#### Scenario 6.1: Auto-load klantbeeld after identification
- GIVEN a citizen is identified and a matching client exists
- THEN the klantbeeld MUST load within 2 seconds showing: contact history, open cases, documents, notes

#### Scenario 6.2: Interaction timeline in klantbeeld
- GIVEN 15 contactmomenten and 4 cases
- THEN all interactions MUST be shown in reverse chronological order with date, channel icon, agent, subject

#### Scenario 6.3: New client empty klantbeeld
- GIVEN a newly created client
- THEN "Eerste contact" MUST be displayed with empty timeline
- AND the current contact MUST appear as the first entry

---

### Requirement 7: Call logging workflow

The system MUST support a structured call logging workflow: intake, handling, and wrap-up phases.

#### Scenario 7.1: Intake phase
- GIVEN the agent clicks "Nieuw telefoongesprek"
- THEN the timer starts, channel sets to "telefoon", direction to "inkomend"
- AND the identification panel gains focus
- AND a reference number is auto-generated

#### Scenario 7.2: Handling phase
- GIVEN the caller is identified
- THEN notes auto-save every 10 seconds
- AND the agent can add structured tags and open the kennisbank without losing form state

#### Scenario 7.3: Wrap-up phase
- GIVEN the call is finished
- WHEN the agent clicks "Afronden"
- THEN a wrap-up form MUST require: onderwerp, resultaat (afgehandeld/doorverwezen/terugbelverzoek)
- AND the timer stops and duration is recorded

---

### Requirement 8: Knowledge base integration

The kennisbank MUST be accessible directly within the workspace.

#### Scenario 8.1: Context-aware search
- GIVEN the agent has filled in onderwerp "Paspoort"
- WHEN they open the kennisbank (Ctrl+K)
- THEN search MUST be pre-populated with the current subject
- AND results ranked by relevance

#### Scenario 8.2: Insert FAQ answer
- GIVEN the agent finds a relevant article
- WHEN they click "Gebruik antwoord"
- THEN the article summary MUST be inserted into the contactmoment notes
- AND a reference to the article MUST be stored

#### Scenario 8.3: No relevant article
- GIVEN no matching articles exist
- THEN "Geen artikelen gevonden" with "Suggestie indienen" button MUST be shown

---

### Requirement 9: Backoffice routing and escalation

The system MUST support routing contacts to backoffice with SLA tracking.

#### Scenario 9.1: Route to department
- GIVEN a complex question requires specialist handling
- WHEN the agent selects "Doorsturen naar backoffice" with department and priority
- THEN a task MUST be created with contactmoment summary, client reference, and SLA deadline

#### Scenario 9.2: Escalation with SLA
- GIVEN escalation with priority "Hoog"
- THEN SLA deadline MUST be set (Hoog = 4 hours, Normaal = 24 hours, Laag = 72 hours)
- AND the originating agent MUST be notified when the escalation is handled

#### Scenario 9.3: View escalation status
- GIVEN a previous escalation for the same citizen
- THEN the klantbeeld MUST show: status, assigned handler, remaining SLA time
- AND if SLA is within 1 hour, a warning indicator MUST display

---

### Requirement 10: Quick actions

The system MUST provide quick-action buttons for common KCC operations.

#### Scenario 10.1: Quick action to create case
- GIVEN the agent determines a new case is needed
- THEN "Nieuwe zaak" MUST open a pre-populated case creation form (in Procest if installed)

#### Scenario 10.2: Quick status update
- GIVEN a citizen calls about case status
- THEN "Status mededelen" MUST display status in citizen-friendly format
- AND the contactmoment MUST auto-mark as "Status informatieverzoek - afgehandeld"

#### Scenario 10.3: Quick callback creation
- GIVEN the agent cannot resolve the question
- THEN "Terugbelverzoek" MUST open the terugbel-taakbeheer form pre-populated with context

---

### Requirement 11: Agent availability and status

Agents MUST be able to set availability status affecting queue assignment.

#### Scenario 11.1: Status "Beschikbaar"
- THEN the agent MUST be included in queue rotation
- AND status MUST be visible to supervisors

#### Scenario 11.2: Status "Nawerk"
- THEN the agent MUST be excluded from new queue assignments for configurable duration (default: 3 min)
- AND after expiry, status MUST auto-revert to "Beschikbaar"

#### Scenario 11.3: Status "Pauze"
- THEN the agent MUST be excluded from queue
- AND if there are open contacts, a warning MUST display

---

### Requirement 12: Queue management

The system MUST provide queue management for distributing incoming contacts.

#### Scenario 12.1: Current queue status
- GIVEN 8 contacts waiting, 5 agents online
- THEN total queue count, average wait, longest wait, and available agents MUST display

#### Scenario 12.2: Pick up next contact
- GIVEN the agent is ready
- WHEN they click "Volgende contact"
- THEN the longest-waiting contact MUST be assigned
- AND the workspace MUST auto-open with timer started

#### Scenario 12.3: Priority queue for returning citizens
- GIVEN a citizen calls back within 30 minutes of an unresolved contact
- THEN the system MUST flag as "Terugkerende beller" with elevated priority
- AND route to the same agent if available

---

### Requirement 13: Agent performance metrics

Real-time and historical performance metrics MUST be available.

#### Scenario 13.1: Personal statistics
- GIVEN 23 contacts handled today (18 phone, 3 email, 2 counter)
- THEN total, breakdown by channel, average handling time, FCR rate, and 30-day comparison MUST display

#### Scenario 13.2: Team overview for supervisor
- GIVEN a supervisor with "kcc-supervisor" role
- THEN agents online, queue status, contacts per agent, SLA compliance MUST display

#### Scenario 13.3: Historical export
- GIVEN a date range selection
- THEN a report with: total contacts per channel, handling time, FCR, escalation rate, top subjects MUST be exportable as CSV and PDF

---

### Requirement 14: SLA timer display

The workspace MUST display SLA timers for active contacts and pending tasks.

#### Scenario 14.1: Active contact SLA timer
- GIVEN a phone contact with 5-minute SLA
- THEN elapsed time, SLA target, and progress bar MUST display (green -> orange at 80% -> red at 100%)

#### Scenario 14.2: Pending task SLA countdown
- GIVEN 3 terugbelverzoeken with different deadlines
- THEN each MUST show countdown to SLA deadline
- AND overdue tasks MUST be highlighted in red

#### Scenario 14.3: SLA configuration per channel
- GIVEN different channels have different SLAs
- THEN SLA targets MUST be configurable per channel type in admin settings

---

## Data Model

The KCC werkplek orchestrates data from multiple sources:
- **Klant** (client): OpenRegister object (Pipelinq register, client schema)
- **Contactmoment**: OpenRegister object (Pipelinq register, contactmoment schema)
- **Zaak** (case): ZGW Zaken API or OpenRegister (Procest)
- **BRP/KVK enrichment**: Via OpenConnector sources
- **Kennisartikel**: OpenRegister object (Pipelinq register, kennisartikel schema)
- **Taak/Terugbelverzoek**: OpenRegister object (Pipelinq register, taak schema)

---

## Dependencies

- Pipelinq (client, contact, contactmoment entities)
- Procest (case data, optional)
- OpenConnector (BRP/KVK integration)
- OpenRegister (data storage and query API)
- Kennisbank spec (knowledge base articles)
- Klantbeeld-360 spec (center panel)
- Omnichannel-registratie spec (contact registration)
- Terugbel-taakbeheer spec (callback tasks)
- Nextcloud Calendar (appointment scheduling)
- Nextcloud Notification API

## Standards & References

- VNG Klantinteracties API -- Contactmoment, Klant, Medewerker
- Haal Centraal BRP API v2 -- BSN-based citizen lookup
- KVK API -- business lookup
- ZGW Zaken API -- open cases retrieval
- NEN-ISO 18295 -- customer contact centre requirements
- Common Ground -- cross-system data access
- AVG/GDPR -- doelbinding for personal data access
- WCAG AA -- accessibility
