# Klantbeeld 360 Specification (Cross-App)

## Purpose

Klantbeeld 360 provides a comprehensive, aggregated view of all interactions, cases, documents, and notes for a single person or business across all channels and systems. This "single pane of glass" is essential for KCC agents and case handlers to deliver consistent, informed service. **83% of klantinteractie-tenders** (43/52) require a 360-degree customer view.

The klantbeeld is inherently cross-app: it aggregates client data from Pipelinq, case data from Procest, document data from Docudesk, and enrichment data from BRP/KVK via OpenConnector. It is consumed by the KCC werkplek as its center panel, by case handlers in Procest for participant context, and by any app that needs a unified entity view.

**Consuming apps**: Pipelinq (KCC werkplek center panel, client detail), Procest (case participant view), Docudesk (document owner view)
**Tender frequency**: 43/52 klantinteractie (83%); 36/52 basisregistratie-integratie (69%); 86% gebruikersbeheer
**Standards**: VNG Klantinteracties (Partij, Betrokkene, Contactmoment), Haal Centraal BRP API, KVK API, ZGW Zaken API, AVG

---

## Requirements

### Requirement 1: Unified client profile

The system MUST display a consolidated profile page combining identity data, contact details, and base registry enrichment.

#### Scenario 1.1: View person client profile
- GIVEN person client "Jan de Vries" with BSN, email, telephone
- WHEN the agent opens the klantbeeld
- THEN name, contact details, BSN (masked by default), and "Verrijk met BRP" button MUST display
- AND client type (Persoon) and avatar placeholder MUST show in the header

#### Scenario 1.2: View organization client profile
- GIVEN organization "Acme B.V." with KVK number
- WHEN the agent opens the klantbeeld
- THEN business name, KVK, contact details, and linked contact persons MUST display
- AND "Verrijk met KVK" MUST be available

#### Scenario 1.3: BRP enrichment on demand
- GIVEN a client with BSN linked
- WHEN the agent clicks "Verrijk met BRP"
- THEN the system MUST query BRP via OpenConnector and display: address, nationality, partner info, municipality
- AND the lookup MUST be logged in the audit trail with agent identity and doelbinding reason

#### Scenario 1.4: KVK enrichment on demand
- GIVEN an organization client with KVK number
- WHEN the agent clicks "Verrijk met KVK"
- THEN the system MUST query KVK and display: legal form, authorized signatories, branch offices
- AND the lookup MUST be logged

#### Scenario 1.5: Cross-app profile aggregation
- GIVEN the same entity exists as a client in Pipelinq and a participant in Procest
- THEN the klantbeeld MUST aggregate data from both sources
- AND conflicting data MUST be flagged (e.g., different phone numbers in different systems)

---

### Requirement 2: Interaction history timeline

The system MUST display a chronological timeline of all interactions across all apps.

#### Scenario 2.1: Complete interaction history
- GIVEN a client with: contactmomenten (Pipelinq), case events (Procest), document events (Docudesk), and notes
- WHEN the agent views the interaction history
- THEN ALL events MUST be displayed in reverse chronological order
- AND each event MUST show: date, type (icon), channel (if applicable), summary, actor, and source app

#### Scenario 2.2: Filter by type
- GIVEN 20 mixed-type interaction entries
- WHEN the agent filters by "Contactmomenten"
- THEN only contactmoment entries MUST be shown
- AND filter options MUST include: Alle, Contactmomenten, Zaken, Notities, Documenten

#### Scenario 2.3: Filter by date range
- GIVEN 2 years of history
- WHEN the agent selects "01-01-2024" to "31-03-2024"
- THEN only entries within that range MUST display with total count

#### Scenario 2.4: Infinite scroll or pagination
- GIVEN 200+ interaction entries
- THEN the timeline MUST support infinite scroll or "Meer laden" pagination
- AND initial load MUST show the 20 most recent entries

---

### Requirement 3: Open and closed cases overview

All cases (from Procest) for the client MUST be displayed prominently.

#### Scenario 3.1: Open cases first
- GIVEN 2 open and 5 closed cases
- THEN open cases MUST display first, prominently
- AND each MUST show: zaaktype, identification, status, start date, handler
- AND closed cases MUST be in a collapsible section below

#### Scenario 3.2: Case detail from klantbeeld
- GIVEN an open case
- WHEN the agent clicks on it
- THEN details MUST display in a side panel: status history, documents, besluit, handler
- AND the agent MUST NOT leave the klantbeeld

#### Scenario 3.3: Case statistics summary
- GIVEN 3 open and 12 closed cases over 2 years
- THEN the header MUST show: open count, closed count, average duration, last activity date

#### Scenario 3.4: Graceful degradation without Procest
- GIVEN Procest is not installed
- THEN the cases section MUST display "Zaaksysteem niet beschikbaar"
- AND the klantbeeld MUST still function with Pipelinq data only

---

### Requirement 4: Documents overview

All documents associated with the client MUST be displayed.

#### Scenario 4.1: Documents from linked cases
- GIVEN 2 cases with 3 documents each
- THEN all 6 documents MUST display: filename, type, date, source case
- AND each MUST be downloadable or viewable inline (PDFs)

#### Scenario 4.2: Search within documents
- GIVEN 20 documents
- WHEN searching "vergunning"
- THEN documents matching by filename and metadata MUST be filtered

#### Scenario 4.3: Direct client documents
- GIVEN documents directly linked to the client (not via cases)
- THEN these MUST also appear in the documents tab
- AND they MUST be distinguishable from case-linked documents

---

### Requirement 5: Privacy and access control (doelbinding)

AVG-compliant access MUST be enforced with purpose logging.

#### Scenario 5.1: Log access to klantbeeld
- GIVEN an agent opens the klantbeeld
- THEN an audit log entry MUST be created: agent identity, client identity, timestamp, accessed data categories
- AND the log MUST be immutable for AVG audits

#### Scenario 5.2: Require doelbinding for BRP access
- GIVEN an agent clicks "Verrijk met BRP"
- THEN a doelbinding reason MUST be selected (e.g., "Afhandeling vergunningaanvraag")
- AND the reason MUST be stored alongside the BRP query in the audit trail

#### Scenario 5.3: Role-based data visibility
- GIVEN frontoffice and backoffice roles
- THEN frontoffice agents MUST NOT see restricted case details
- AND an indication "Beperkt zichtbare informatie beschikbaar" MUST show
- AND visibility rules MUST be configurable per data category

#### Scenario 5.4: Cross-app permission aggregation
- GIVEN data from Pipelinq, Procest, and Docudesk in the klantbeeld
- THEN each data source MUST apply its own permission rules
- AND the klantbeeld MUST show only data the current user is authorized to see

---

### Requirement 6: Notes and internal communication

Internal notes visible to colleagues but not citizens MUST be supported.

#### Scenario 6.1: Add internal note
- GIVEN the klantbeeld for "Jan de Vries"
- WHEN the agent adds a note "Let op: burger is slechthorend"
- THEN the note MUST be stored with agent identity and timestamp
- AND visible to all agents viewing this client

#### Scenario 6.2: Pin important note
- GIVEN a client with 5 notes, one marked important
- THEN the pinned note MUST display prominently at the top as a warning banner

#### Scenario 6.3: Cross-app note visibility
- GIVEN a note added in Pipelinq
- THEN the note MUST also be visible when viewing the same entity in Procest's klantbeeld

---

### Requirement 7: Contact summary statistics

The klantbeeld MUST display aggregated statistics for quick context.

#### Scenario 7.1: Contact frequency summary
- GIVEN a client with 30 contacts in the past year
- THEN the header MUST show: total contacts (30), contacts this month, preferred channel, average contacts per month

#### Scenario 7.2: Service satisfaction indicator
- GIVEN satisfaction scores collected from survey links
- THEN an aggregate satisfaction indicator MUST display if data is available

#### Scenario 7.3: First-call resolution rate per client
- GIVEN a client's contact history
- THEN the percentage of contacts resolved without follow-up MUST be calculated and displayed

---

### Requirement 8: Related entities and relationships

The klantbeeld MUST show relationships to other entities (other contacts, organizations, etc.).

#### Scenario 8.1: Family relationships from BRP
- GIVEN BRP data shows partner and children
- THEN a "Familie" section MUST display relationships
- AND each family member MUST be clickable to view their klantbeeld

#### Scenario 8.2: Professional relationships
- GIVEN the client has employer/colleague relationships
- THEN a "Professioneel" section MUST display these relationships

#### Scenario 8.3: Linked organizations
- GIVEN a person linked to organizations as employee or contact person
- THEN linked organizations MUST display with the person's role

---

### Requirement 9: CRM-specific data in klantbeeld

When viewed from Pipelinq, the klantbeeld MUST show CRM-specific data.

#### Scenario 9.1: Active leads and opportunities
- GIVEN a client with 3 active leads
- THEN leads MUST display: title, stage, value, assignee
- AND total pipeline value MUST be summarized

#### Scenario 9.2: Active requests
- GIVEN a client with 2 open requests
- THEN requests MUST display: title, status, channel, assignee

#### Scenario 9.3: Quotes and proposals
- GIVEN a client with 2 outstanding quotes
- THEN quotes MUST display: number, title, total, expiry date, status

---

### Requirement 10: Configurable klantbeeld layout

Administrators MUST be able to configure which sections appear in the klantbeeld.

#### Scenario 10.1: Enable/disable sections
- GIVEN admin settings for klantbeeld
- THEN sections (Interactiehistorie, Zaken, Documenten, Notities, Relaties, CRM-gegevens) MUST be individually toggleable

#### Scenario 10.2: Section ordering
- GIVEN enabled sections
- THEN the admin MUST be able to reorder sections
- AND the order MUST persist for all users

#### Scenario 10.3: Per-role section visibility
- GIVEN different roles (frontoffice, backoffice, manager)
- THEN section visibility MUST be configurable per role
- AND frontoffice MUST have a simpler default layout than backoffice

---

### Requirement 11: Klantbeeld loading performance

The klantbeeld MUST load within acceptable timeframes for real-time use.

#### Scenario 11.1: Initial load within 2 seconds
- GIVEN a client with moderate data (20 contacts, 5 cases, 10 documents)
- THEN the klantbeeld MUST load and display within 2 seconds

#### Scenario 11.2: Lazy loading for heavy sections
- GIVEN a client with 200+ contacts and 50 documents
- THEN the timeline MUST lazy-load (first 20 entries immediately, more on scroll)
- AND documents MUST lazy-load only when the documents tab is activated

#### Scenario 11.3: Caching for repeated access
- GIVEN an agent views the same client multiple times in a session
- THEN subsequent loads MUST use cached data (with cache invalidation on changes)

---

## Data Model

The klantbeeld aggregates from multiple sources:
- **Client record**: Pipelinq client object (master record)
- **Contactmomenten**: All registered contact moments (Pipelinq)
- **Zaken**: Open and closed cases (Procest/ZGW)
- **Documenten**: Documents via cases or direct (Docudesk/Nextcloud Files)
- **Notes**: Internal notes (entity-notes)
- **BRP/KVK data**: Enrichment from base registries (OpenConnector)
- **Relationships**: Typed links to other entities (contact-relationship-mapping)

---

## Dependencies

- Pipelinq (client entities, contactmomenten)
- Procest (case data, optional)
- Docudesk (document data, optional)
- OpenConnector (BRP/KVK enrichment)
- OpenRegister (data storage and aggregation)
- Contact-relationship-mapping spec
- Activity-timeline spec

## Standards & References

- VNG Klantinteracties API -- Partij, Betrokkene, Contactmoment
- Haal Centraal BRP API v2 -- citizen data enrichment
- KVK API -- business data enrichment
- ZGW Zaken API -- case retrieval
- AVG -- doelbinding requirements
- Common Ground -- federated data access
- WCAG AA -- accessibility
