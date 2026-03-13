# Procest vs OpenZaak — Strategic Position Analysis

## Fundamental Difference

**OpenZaak** = API-only data layer (backend). No user interface. No workflow engine. No task management.

**Procest** = Full case management application (frontend + backend). Native Nextcloud integration. CMMN workflow. Task management. Deadline tracking.

## Relationship Models

### Model 1: Procest as OpenZaak Consumer
Procest connects to an external OpenZaak instance as a consumer application:
- OpenZaak provides the ZGW-compliant data storage
- Procest provides the case handling UI and workflow
- Other applications can also connect to the same OpenZaak instance
- Maximum interoperability with existing municipal infrastructure

### Model 2: Procest as ZGW Provider (Current Direction)
Procest implements its own ZGW-compliant APIs:
- Procest IS the zaaksysteem — no external dependency
- ZGW APIs exposed from Procest for other consumers
- Simpler architecture (one system instead of two)
- Less interoperability flexibility

### Model 3: Hybrid
Procest implements core ZGW APIs internally but can also consume external ZGW providers:
- Own data for internally managed cases
- External references for federated scenarios
- Best of both worlds but highest complexity

## Feature Gap Analysis

### Features OpenZaak Has That Procest Needs

| Priority | Feature | Risk if Missing |
|----------|---------|----------------|
| CRITICAL | Full archiving/selectielijst model | Legal non-compliance (Archiefwet) |
| CRITICAL | Confidentiality level enforcement | Data breach risk |
| HIGH | Case closure enforcement (resultaat required) | Data integrity issues |
| HIGH | Document locking mechanism | Concurrent editing conflicts |
| HIGH | Audit trail per VNG spec | Compliance gap |
| HIGH | Catalog versioning (concept/publish) | Configuration management issues |
| MEDIUM | Notification integration (Open Notificaties) | No event-driven integration |
| MEDIUM | External API interoperability | Cannot participate in federated setups |
| MEDIUM | Mandate authentication context | No citizen portal support |
| LOW | Cloud Events | Future-proofing only |
| LOW | Bulk document import | Migration convenience only |

### Features Procest Has That OpenZaak Lacks

| Feature | Procest Advantage |
|---------|------------------|
| End-user UI | Case workers interact directly with Procest |
| CMMN 1.1 workflow | Dynamic case management with process modeling |
| Task management | Assign and track tasks within cases |
| Deadline tracking | Automatic alerts and overdue management |
| Document checklists | Required document tracking per case type |
| Nextcloud integration | Native file management, collaboration, sharing |
| WCAG AA accessibility | Accessible UI for government requirements |
| Dashboard and KPIs | Management overview with charts and metrics |
| My Work view | Personal task queue for case workers |
| NL Design System | Government theming compliance |

## Competitive Position

OpenZaak is NOT a competitor to Procest. It is a potential **integration partner** or **implementation reference**:

1. **Reference for compliance** — OpenZaak shows exactly what VNG compliance looks like
2. **Potential backend** — Procest could consume OpenZaak APIs for maximum interoperability
3. **Feature roadmap guide** — OpenZaak's experimental features show where the standard is heading
4. **Market validator** — OpenZaak's municipal adoption proves the market need

## Municipalities Currently Using OpenZaak

The founding coalition represents some of the largest Dutch cities:
Amsterdam, Rotterdam, Utrecht, Tilburg, Arnhem, Haarlem, 's-Hertogenbosch, Delft, and the SED coalition (Hoorn, Medemblik, Stede Broec, Drechterland, Enkhuizen).

These municipalities use OpenZaak as their backend, with separate frontend applications (like Dimpact ZAC or custom solutions) for case handling. Procest targets the same market with the added advantage of native Nextcloud integration.
