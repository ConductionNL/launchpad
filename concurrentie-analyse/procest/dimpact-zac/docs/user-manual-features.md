# ZAC User Manual — Feature Summary

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/manuals/ZAC-gebruikershandleiding/ZAC-gebruikershandleiding.md
Version: 1.6.9 (ZAC v3.12)

## Main Navigation

Blue toolbar with:
- Home button (dashboard)
- Worklist menu
- Search
- Case creation
- Profile/logout

## Dashboard

- Three-column layout, customizable
- Add/remove/rearrange card widgets
- Card types: worklist compacts and alert aggregates
- Persistent session settings
- Red indicator on home button for new alerts

## Case (Zaak) Management

### Core Operations
- Create new cases with initiator and BAG-object
- Tabbed view: data, history, involved parties, related cases, BAG objects
- Edit dates, groups, handlers, communication channels
- Suspend/resume with automatic deadline recalculation
- Extend deadlines (configurable per zaaktype)
- Close: abort, finalize, or reopen

### Case Relations
- Parent-child (hoofd-/deelzaak) relationships
- View related cases and associated documents
- Unlink with documented reasons

### Location Management
- Assign geographic coordinates
- View closest address details

### Involved Parties (Betrokkenen)
- Initiator tracking with role-based assignment
- Multiple involved parties with relationship types
- Person indicators (investigation status, confidentiality, deceased)

### Decisions (Besluiten)
- Multiple decisions per case
- Decision status tracking (effective/expired)
- Linked document requirements
- Result-type-driven decision mandates

## Task Management

### Task Types (CMMN)
1. **Aanvullende informatie** — Request additional information, with email + suspension options
2. **Intern advies** — Internal advisory with document selection
3. **Extern advies** — External advice requests with advisor identification
4. **Goedkeuring** — Approval routing with multi-document support
5. **Document verzenden** — Document transmission assignment

### Task Workflow
- Distribute to groups/individuals
- Track progression and deadlines
- Interim saving and final completion
- Integrated document access within tasks

## Document Management

### Operations
- Upload and attach documents to cases
- Edit MS Office (Word, Excel, PowerPoint) via WebDAV — creates versioned copies
- Modify metadata independently
- Digital signing with timestamp
- Convert Office documents to PDF
- Transmit with date/status tracking
- Move documents between cases
- Detach (moved to separate worklist)
- Delete (Recordmanager only)
- Preview PDF/images

### Properties
- Status tracking: Draft / Final / Definitive
- Confidentiality classifications
- Multiple version management
- User-specific access right indicators (lock/unlock)

## Search

### Multi-Category Search
- **Cases**: Boolean operators (AND, OR, NOT), wildcards, multiple filters
- **Persons**: BSN, surname + birthdate, address
- **Companies**: KvK number, branch number, RSIN, business name
- **BAG objects**: Address components with result expansion

### Filter Capabilities
- Include/exclude attributes
- Date range filtering
- Initiator filtering with person/company lookup
- Save search queries for reuse

## Work Distribution (Werkvoorraad)

### Coordinator Functions
- Distribute cases/tasks to groups and individuals
- Release work from handlers (vacation/termination coverage)
- Batch selection from worklists
- Documented justification for distributions

### Worklists
- Werkvoorraad (all organizational cases/tasks)
- Personal cases/tasks (Mijn zaken/taken)
- Completed cases (Afgehandelde zaken)
- Disconnected documents (Ontkoppelde documenten)
- Inbox documents
- Product requests (Productaanvragen)

## Notifications (Signaleringen)

### Alert Types
- Document additions to user's cases
- Case assignment notifications
- Deadline approach warnings (target + fatal dates)
- Task assignments
- Task deadline alerts

### Delivery
- Dashboard widget
- Email (user-configurable on/off per type)
- Red indicator on home button

## Intake Process

- Structured intake-to-treatment transitions
- Admissibility determinations with conditional case closure
- Automated email at phase transitions

## Email

- Send emails from within case context
- Receipt confirmation sending
- Email template system (admin-configured)
- Sender address configuration

## Historical Tracking

- Complete action history per case
- Change reason documentation
- Version control for documents and decisions
- Task completion timestamps
