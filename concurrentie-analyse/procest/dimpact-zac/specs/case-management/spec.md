---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Case Management — Dimpact ZAC

## Purpose

Core zaak (case) lifecycle management: creation, handling, status transitions, suspension, extension, closure, and reopening. Includes involved party management, BAG object linking, and parent-child case relationships.

## Architecture Overview

- Cases are stored in **Open Zaak** (ZGW Zaken API) as the single source of truth
- ZAC maintains a **Solr index** of case data for fast retrieval in worklist screens
- Case state is managed through the embedded **Flowable** process engine (CMMN/BPMN)
- ZAC's own PostgreSQL stores zaakafhandelparameters and internal state
- Event-driven: case changes from Open Zaak arrive via **Open Notificaties** subscriptions

## Data Model

Cases are NOT stored in ZAC's database — they live in Open Zaak. ZAC stores:
- `zaakafhandelparameters` — per-zaaktype configuration (workflow type, default group, task settings)
- Flowable process state (CMMN/BPMN) in the `flowable` schema
- Solr index mirrors case data for search/display

Key case attributes (from ZGW APIs):
- Zaak identification, zaaktype, status, resultaat
- Start date, target date (streef), fatal date (fataal)
- Assigned group, handler (behandelaar)
- Communication channel
- Involved parties (initiator + betrokkenen)
- BAG objects (geographic references)
- Related cases (parent-child)

## Business Logic

### Case Lifecycle States
1. **Intake** — Initial assessment phase
2. **In behandeling** — Active handling phase
3. **Opgeschort** — Suspended (with reason)
4. **Afgehandeld** — Completed
5. **Heropend** — Reopened (by Recordmanager only)

### State Transitions
- Intake -> In behandeling (admissibility determination)
- In behandeling -> Opgeschort (suspension with auto-deadline recalculation)
- Opgeschort -> In behandeling (resume)
- In behandeling -> Afgehandeld (closure with result + reason)
- Afgehandeld -> Heropend (Recordmanager only)
- Any open state -> Afbreken (abort with reason)

### Suspension Logic
- Suspension pauses deadline timers
- Deadline dates automatically recalculated on resume
- Cannot suspend if already suspended or reopened

### Extension Logic
- Can extend case deadlines (configurable per zaaktype)
- Maximum one extension per case
- Cannot extend if suspended or reopened

### Case Relations
- Parent-child (hoofd-/deelzaak) relationships
- Both cases must be open to create a link (except Recordmanager: both can be closed)
- Unlinking requires documented reason

## Requirements (as observed)

1. Every zaaktype must be configured with zaakafhandelparameters before use
2. Zaaktype must use either generic CMMN model or a custom BPMN process
3. Cases can be assigned to groups and/or individual handlers
4. Initiator (person or company) can be linked via BSN or KVK
5. Multiple betrokkenen with relationship types supported
6. Geographic location (BAG objects + coordinates) linkable to cases
7. Full audit trail of all case actions (history tab)
8. Change reasons documented for modifications
9. Email notifications at phase transitions (configurable)
10. Warning windows for approaching target/deadline dates

## Comparison Notes

**vs. Procest:**
- ZAC is tightly coupled to Open Zaak as case store; Procest uses OpenRegister for its own data model
- ZAC's two-phase model (Intake + In behandeling) is simpler than a fully customizable state machine
- ZAC's CMMN/BPMN approach requires external tooling (Flowable Designer) for custom processes
- ZAC cannot scale horizontally — significant limitation for large municipalities
- ZAC has no public API — all data access is through the Angular frontend only
- ZAC's BAG integration for geographic case linking is mature
- ZAC's suspension/resume with automatic deadline recalculation is well-designed
