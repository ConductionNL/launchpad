# OpenZaak — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (74 Django models), Documentation (16 RTD files + VNG standards), Browser walkthrough (40 screenshots)
**Verdict**: Not a competitor but a compliance reference — API-only backend, no UI, no workflow

---

## Executive Summary

OpenZaak is the VNG ZGW API reference implementation — a Python/Django REST API implementing 5 ZGW components (Zaken, Documenten, Catalogi, Besluiten, Autorisaties). It is the government standard for zaakgericht werken backends, used by 100+ municipalities.

**OpenZaak is NOT a direct competitor to Procest.** It is an API-only backend with no end-user frontend, no workflow engine, and no case handling UI. Procest provides the complete case handling experience that sits ON TOP of a ZGW backend. They are complementary.

The competitive question is: does Procest need ZGW API compliance to compete? **Yes — for government market access.**

## Scale

- 74 Django models
- 38+ API ViewSets
- 23 authorization scopes across 5 components
- 5 ZGW API components
- 8 confidentiality levels
- 9 brondatum derivation methods for archiving

## What Procest MUST Implement for ZGW Compliance

### Critical (legal/regulatory requirements)

| Feature | OpenZaak | Procest Status | Priority |
|---------|---------|---------------|----------|
| **Archiving (Archiefwet)** | 9 brondatum methods, selectielijst API, auto-calculation | Basic | CRITICAL |
| **Confidentiality enforcement** | 8-level filtering per zaaktype | Basic levels | HIGH |
| **Case closure rules** | Resultaat required before eindstatus, auto-archive | Not enforced | HIGH |
| **Catalogus/Zaaktype config** | Concept/published workflow, validity periods, versioning | Flexible schemas | HIGH |
| **VNG audit trail** | Every write with user attribution, AuditTrail API | Basic logging | HIGH |
| **Document locking** | lockId-based concurrency control | No locking | MEDIUM |

### Important (interoperability)

| Feature | OpenZaak | Procest Status | Priority |
|---------|---------|---------------|----------|
| **JWT M2M auth** | ZGW JWT standard, Applicatie + Autorisatie | NC auth | MEDIUM |
| **ZGW API surface** | Full VNG-compliant endpoints | Custom API | MEDIUM |
| **Notificaties API** | Pub-sub notifications channel | n8n webhooks | LOW |
| **Selectielijst integration** | External API for archiving rules | Not integrated | MEDIUM |
| **NLX gateway** | Service-to-service via NLX | Not available | LOW |

### Nice-to-have

| Feature | OpenZaak | Procest |
|---------|---------|---------|
| Zaakobjecten (20+ RGBZ types) | BAG, Kadaster, WOZ links | Flexible via OR |
| Zaakrelaties (deelzaken) | Parent-child case relations | Basic relations |
| Rollen (5 betrokkene types) | BSN, KvK, Vestiging, etc. | Simplified roles |
| Chunked uploads (BestandsDelen) | Large file upload support | NC file handling |

## What Procest Already Has That OpenZaak LACKS

| Feature | Procest | OpenZaak |
|---------|---------|---------|
| **End-user Vue.js frontend** | Complete case handling UI | No frontend at all |
| **Workflow engine** | Case handling with task assignment | No workflow |
| **Deadline tracking** | Visual deadline management | No deadline UI |
| **Task assignment** | My Work queue, user assignment | No task management |
| **Document checklists** | Required document tracking | No checklists |
| **Sub-case management** | Nested case handling | API-only relations |
| **Nextcloud integration** | Files, users, sharing, collaboration | Standalone |
| **NL Design theming** | Government design system | Django Admin |
| **n8n automation** | Built-in workflow triggers | External notifications only |
| **MCP/AI** | LLM-friendly API | Not available |
| **Zero-infra deployment** | Runs inside Nextcloud | Requires 4+ containers |

## Specs Created

### From Codebase (11 specs)
zaak-lifecycle, catalogi-zaaktypen, documenten-api, besluiten-api, autorisaties-model, rollen-betrokkenen, archivering, status-resultaat, zaak-relaties, zaakobjecten, zaakeigenschappen

### From Documentation (15 specs)
6 API compliance specs, archiving/selectielijst, confidentiality, zaaktype config, mandate auth, audit trail, document lifecycle, case closure, external API interop, convenience endpoints

### From Browser (27 specs + 40 screenshots)
Complete overview with gap analysis, 15 compliance specs, 11 detailed component specs

### Business Logic Diagrams (8)
zaak-lifecycle, document-lifecycle, besluit-flow, statustype-progression, archiving-rules, authorization-flow, zgw-data-model, procest-vs-openzaak

## Strategic Recommendation

**Don't compete with OpenZaak — integrate with it and surpass it.**

1. Add ZGW API compatibility layer (like the VNG Objects API layer for OpenRegister)
2. Implement archiving compliance (Archiefwet is a legal requirement)
3. Implement confidentiality enforcement (8 levels)
4. Keep winning on UI, workflow, Nextcloud integration, and developer experience
5. Position Procest as "OpenZaak + frontend + workflow + Nextcloud" — the complete solution
