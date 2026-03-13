# Spec: Competitive Positioning — Open Formulieren vs Procest

## Executive Summary

Open Formulieren and Procest are **complementary products** operating in different segments of the municipal service delivery chain. Open Formulieren handles citizen-facing intake (forms); Procest handles internal case management (processing). They compete only in a narrow overlap zone around ZGW case creation.

## Market Positioning

```
Citizen Journey:
[Discovery] → [INTAKE] → [PROCESSING] → [DECISION] → [NOTIFICATION]
                 ↑              ↑
          Open Formulieren    Procest
```

### Open Formulieren's Territory
- Citizen-facing form rendering and submission
- Authentication (DigiD, eHerkenning)
- Data prefill from national registries
- Payment collection
- Initial case creation in ZGW

### Procest's Territory
- Case lifecycle management (status, deadlines, assignments)
- Task management for case workers
- Document management (Nextcloud Files + DRC)
- Decision management (besluiten)
- Audit trail and compliance
- KPI dashboards and reporting
- Team collaboration on cases

### Overlap Zone
- ZGW case creation: Both can create Zaken, but for different purposes
  - Open Formulieren: citizen self-service intake → automated case creation
  - Procest: manual case creation by case workers or API intake

## Competitive Strengths

### Open Formulieren Strengths (vs Procest)
1. **Form builder** — Full no-code visual form designer; Procest has none
2. **Citizen authentication** — DigiD/eHerkenning/eIDAS support; Procest is internal-only
3. **Prefill** — BRP/KvK automatic population; Procest has no citizen data sources
4. **Payment** — Integrated payment flow; Procest has none
5. **Adoption** — 150+ municipalities via Dimpact; Procest is early-stage
6. **Ecosystem** — Deep Common Ground integration; standalone product
7. **SDK embedding** — Can be embedded in any CMS; flexible deployment

### Procest Strengths (vs Open Formulieren)
1. **Case lifecycle** — Full case management from creation to archival; OF stops at creation
2. **Task management** — Assignment, deadlines, task workflow; OF has nothing post-submission
3. **Document management** — Nextcloud Files integration + DRC sync; OF only uploads documents
4. **Decision management** — Besluiten via BRC; OF has no decision capability
5. **Integrated platform** — Runs inside Nextcloud (collaboration, files, calendar, mail); OF is standalone
6. **Bidirectional ZGW** — Reads and writes all ZGW APIs; OF only writes outbound
7. **CMMN support** — Case modeling standard; OF has no process modeling
8. **No additional infrastructure** — Runs as Nextcloud app; OF needs Django + Celery + Redis + PostgreSQL
9. **Audit trail** — Full case activity logging; OF has submission log only

## Competitive Threats

### Threat FROM Open Formulieren
**Low.** Open Formulieren has no plans to add case management. It is firmly positioned as an intake-only tool. The risk is that municipalities choose a different case management system that integrates with Open Formulieren, bypassing Procest.

### Threat TO Open Formulieren
**Low.** Procest should not try to replicate Open Formulieren's form builder. The form builder market is saturated and Open Formulieren has dominant market share in the Dutch government space.

### Real Competitive Threats
- **Valtimo** — Full BPM/case management platform with form building; competes with BOTH Open Formulieren and Procest
- **Rx.Mission** — Commercial ZGW case management; competes with Procest
- **DecoZEN/JOIN** — Commercial document/case management; competes with Procest
- **OpenZaak + Open Inwoner** — Combined with Open Formulieren creates a full stack without Procest

## Recommended Strategy

### 1. Complementary Positioning (Primary)
- Position Procest as the **case management layer** that receives submissions from Open Formulieren
- Build integration documentation and demonstrate the flow
- Target municipalities already using Open Formulieren who need case management

### 2. Nextcloud Platform Advantage (Differentiator)
- Emphasize single-platform value: case management + document handling + collaboration + calendar
- No need for multiple standalone Common Ground components
- Lower total cost of ownership vs. Open Formulieren + OpenZaak + Open Inwoner separately

### 3. Simple Internal Intake (Minor Feature)
- Build basic intake forms using OpenRegister schemas for internal case creation
- NOT competing with Open Formulieren's citizen-facing forms
- For case workers to quickly create structured cases without needing external form tools

### 4. Integration Over Competition
- Ensure ZGW API compatibility with Open Formulieren's registration output
- Auto-detect Zaken created by Open Formulieren
- Display form submission PDF as case document
- Map Zaakeigenschappen to case detail fields
