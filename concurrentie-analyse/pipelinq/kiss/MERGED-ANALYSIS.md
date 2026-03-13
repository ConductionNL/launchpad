# KISS (Klantinteractie Servicesysteem) — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (KISS-frontend monorepo — Vue 3 + ASP.NET Core BFF), Documentation (5 docs + decision records), Browser (14 screenshots from live demo)
**Verdict**: Specialized government call center tool — overlaps on contact moments, not on CRM/pipeline

---

## Executive Summary

KISS is a **klantcontactmedewerker (KCM) workstation** — a frontend for customer service representatives in Dutch municipalities. Developed by ICATT for Dimpact (~40 municipalities). It is NOT a CRM or pipeline tool — it's a specialized call center application deeply integrated with the VNG API ecosystem.

**Relationship to Pipelinq**: KISS handles inbound citizen/business interactions (phone, email, counter). Pipelinq handles relationship management, lead pipelines, and workflow automation. They overlap only on contact moment logging. KISS could be a **complementary tool** or a **replacement target** for the contact moment portion.

## Architecture

```
Vue 3 (Vite) Frontend
        |
  ASP.NET Core BFF (Backend-for-Frontend)
        |
   YARP Reverse Proxy
        |
  +-----------+-----------+-----------+-----------+
  |           |           |           |           |
OpenKlant  OpenZaak   Haal Centraal  KvK API   Elasticsearch
(1 + 2)    (ZGW)       BRP                     Enterprise Search
```

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3, TypeScript, Vite, Pinia |
| BFF | ASP.NET Core 8, C#, Entity Framework Core |
| Database | PostgreSQL (BFF-internal only) |
| Search | Elasticsearch + Enterprise Search |
| Auth | OIDC + 3-role system |
| UI | Utrecht Component Library (NL Design System) |

## Key Comparison

| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Purpose | Call center workstation | CRM + pipeline management |
| Contact management | Read-only (via BRP/KvK) | Full CRUD |
| Contact moments | Core feature (multi-concurrent) | Yes |
| Pipeline/kanban | No | Yes (core feature) |
| Lead management | No | Yes |
| Organization management | No (read-only KvK lookup) | Yes |
| Search | Elasticsearch knowledge base | Full-text + faceted |
| File attachments | No (external DRC) | Nextcloud native |
| Import/export | No | CSV/vCard |
| Duplicate detection | No | Yes |
| Nextcloud integration | No | Native |
| BRP citizen lookup | Yes (Haal Centraal) | No |
| KvK business lookup | Yes | No |
| Case system integration | Yes (OpenZaak ZGW) | Yes (Procest) |
| RBAC | 3 roles (KCM/Redacteur/Beheerder) | Nextcloud permissions |
| Audit/privacy | Verwerking logging (AVG) | Basic |
| UI framework | NL Design (Utrecht) | NL Design |
| Deployment | Kubernetes (8+ services) | Nextcloud app |

## Features KISS Has That Pipelinq DOES NOT Have

| Feature | Description | Priority |
|---------|-------------|----------|
| **BRP Integration** | Citizen lookup via Haal Centraal BRP (BSN, name+DOB, postcode) | HIGH — needed for government |
| **KvK Integration** | Business registry lookup via KvK API | HIGH — needed for government |
| **Multi-contactmoment switching** | Handle multiple concurrent interactions with tab-based switching | MEDIUM |
| **Multi-question per contactmoment** | Track multiple questions within one interaction | MEDIUM |
| **Elasticsearch knowledge base** | Unified search across kennisartikelen, werkberichten, VACs, employee directory, website crawls | MEDIUM |
| **Werkberichten (work messages)** | Internal news/instructions with skill-based filtering | LOW |
| **Verwerking audit logging** | AVG/GDPR privacy compliance trail for all personal data access | HIGH — legal requirement |
| **Configurable intake forms** | Dynamic question forms per department (VragenSets) | MEDIUM |
| **Channel (kanaal) management** | Explicit communication channel tracking per interaction | LOW |
| **Zaaksysteem integration** | Direct case linking via ZGW APIs during contact moments | MEDIUM |

## Features Pipelinq Has That KISS LACKS

| Feature | Pipelinq | KISS |
|---------|----------|------|
| **Pipeline/kanban views** | Visual drag-and-drop | Not available |
| **Lead management** | Full lead lifecycle | Not available |
| **Organization management** | CRUD with hierarchy | Read-only KvK lookup |
| **Contact CRUD** | Full create/edit/delete | Read-only from external APIs |
| **Import/export** | CSV, vCard, bulk operations | Not available |
| **File attachments** | Nextcloud native files | Not available |
| **Duplicate detection** | Automated matching | Not available |
| **Nextcloud Contacts sync** | Native CardDAV | Not available |
| **n8n automation** | Built-in workflow triggers | Not available |
| **Custom fields** | Schema-driven flexibility | Fixed VNG API model |
| **Faceted search** | Configurable facets | Full-text only |
| **Self-hosted on Nextcloud** | Single app install | Requires 8+ services |
| **MCP/AI** | LLM-friendly API | Not available |

## Specs Created

### From Codebase (12 specs)
contactmoment-logging, contactverzoek-management, persoon-search, bedrijf-search, zaaksysteem-integration, elasticsearch-knowledge-base, werkberichten-news, beheer-admin, authentication-rbac, contactmomenten (multi-concurrent), autorisatie (verwerking audit), kanalen-channels

### From Documentation (5 docs)
architecture, configuration, features-overview, adoption-and-governance, decision-records

### From Browser (14 screenshots)
Contactverzoek-beheren series (01-14) — admin contact request form builder from live KISS demo

### Business Logic Diagrams (4)
contactmoment-flow, contactverzoek-flow, search-flow, authentication-flow

## Strategic Assessment

**LOW-MEDIUM competitive threat.** KISS and Pipelinq serve different segments:
- **KISS** = Government call center workstation (inbound service desk)
- **Pipelinq** = General-purpose CRM/pipeline (sales + service + operations)

**Overlap is narrow**: only contact moment logging and contact management. KISS wins on government API integration (BRP, KvK, ZGW). Pipelinq wins on everything else.

**Recommendations for Pipelinq:**
1. **Add BRP/KvK integration** — critical for government market access (also identified in Open Klant analysis)
2. **Add verwerking/audit logging** — AVG/GDPR compliance is a legal requirement for handling citizen data
3. **Consider multi-contactmoment support** — useful for call center use cases
4. **Position as KISS replacement** — "everything KISS does plus full CRM, in Nextcloud, without 8 services"
5. **Or position as complement** — Pipelinq for pipeline/relationship management, KISS for call center
