# KISS (Klantinteractie Servicesysteem) - Competitive Analysis Overview

**Analyzed:** 2026-03-13
**Repos:** KISS-frontend, KISS-Elastic-Sync, KissBundle (7 repos total in org)
**Competitor for:** Pipelinq (CRM/pipeline management for Nextcloud)

## What is KISS?

KISS is a Dutch government **klantcontactmedewerker (KCM) workstation** - a frontend for customer service representatives who handle citizen/business interactions across multiple channels (phone, email, counter). It is developed by ICATT for Dimpact (a cooperative of ~40 Dutch municipalities).

KISS is NOT a general CRM or pipeline tool. It is a **specialized call center/service desk application** for Dutch local government, deeply integrated with the VNG (Vereniging Nederlandse Gemeenten) API ecosystem.

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

### Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vue 3, TypeScript, Vite, Pinia, SCSS |
| BFF | ASP.NET Core 8, C#, Entity Framework Core |
| Database | PostgreSQL (BFF-internal data only) |
| Search | Elasticsearch + Elastic Enterprise Search |
| Auth | OpenID Connect (OIDC) via identity provider |
| Deployment | Docker, Helm charts, Kubernetes |
| UI Framework | Utrecht Component Library (NL Design System) |

### Repository Structure

- **KISS-frontend** (main repo): Vue 3 SPA + ASP.NET Core BFF in one monorepo
- **KISS-Elastic-Sync**: Cronjob-based sync tool that indexes data sources into Elasticsearch
- **KissBundle**: Legacy CommonGateway/OpenRegister bundle (seems deprecated)

## Core Features

### 1. Contactmoment (Contact Moment) Logging
The primary workflow. A KCM starts a "contactmoment" (call/interaction), searches for relevant info, links clients/zaken, and logs the entire interaction. Supports multiple concurrent contactmomenten and multiple "vragen" (questions) per contactmoment.

### 2. Contactverzoek (Contact Request) Management
Internal task routing - forward a contact request to a department/group/employee for follow-up. Uses the OpenKlant 2 "internetaak" (internal task) model.

### 3. Person Search (BRP)
Search Dutch citizens via Haal Centraal BRP API using BSN, name+DOB, or postcode+housenumber.

### 4. Business Search (KvK)
Search businesses via KvK (Kamer van Koophandel) API using trade name, KvK number, postcode, or vestigingsnummer.

### 5. Zaaksysteem (Case System) Integration
View and search cases from external zaaksystemen via ZGW APIs. Link cases to contactmomenten.

### 6. Elasticsearch Knowledge Base
Global search across multiple knowledge sources: kennisartikelen (SDG products), werkberichten, VACs, smoelenboek (employee directory), website crawls, SharePoint pages.

### 7. Werkberichten (Work Messages)
Internal news and work instructions management with skill-based filtering.

### 8. Beheer (Administration)
CRUD management for: kanalen (channels), skills, links, gespreksresultaten (conversation results), contactverzoek forms, VACs, news/work instructions.

### 9. Authentication & RBAC
OIDC-based with 3 roles: Klantcontactmedewerker, Redacteur, Beheerder (+ Kennisbank). Fine-grained permission system for UI features.

## Key Observations

### What KISS Does Well
1. **Deep VNG API integration** - native support for OpenKlant 1+2, OpenZaak, Haal Centraal BRP, KvK
2. **Multi-contactmoment switching** - handle multiple concurrent calls
3. **Multi-question per contactmoment** - track multiple questions within one interaction
4. **Knowledge base search** - unified search across many content sources
5. **Verwerking logging** - audit trail of all API calls for privacy compliance (AVG)
6. **Contactverzoek question forms** - configurable intake forms per department

### What KISS Lacks (vs Pipelinq)
1. **No pipeline/kanban** - no visual workflow stages or drag-and-drop
2. **No lead management** - no concept of leads, opportunities, or deals
3. **No import/export** - no CSV/data import or bulk operations
4. **No file attachments** - no document management (relies on external DRC)
5. **No duplicate detection** - no deduplication logic for contacts
6. **No Nextcloud integration** - standalone app, no Nextcloud Contacts sync
7. **No n8n automation** - no workflow automation engine
8. **No faceted search** - Elasticsearch full-text only, no facets/filters on structured data
9. **No organization hierarchy** - flat contact model, no org trees
10. **No custom fields** - data model is fixed by VNG API specs
11. **No offline support** - fully server-dependent
12. **Not self-hostable on Nextcloud** - requires separate infrastructure

### What Pipelinq Lacks (vs KISS)
1. **No BRP integration** - no citizen lookup via BSN
2. **No KvK integration** - no business registry lookup
3. **No zaaksysteem integration** - no case management system linking
4. **No multi-contactmoment switching** - single interaction at a time
5. **No multi-question per contactmoment** - single thread per contact moment
6. **No Elasticsearch-powered knowledge base** - no unified content search
7. **No werkberichten/news system** - no internal communications for agents
8. **No verwerking/audit logging** - no privacy compliance trail
9. **No configurable intake forms** - no dynamic question forms per department
10. **No channel (kanaal) management** - no explicit communication channel tracking

## Strategic Assessment

KISS and Pipelinq serve **different market segments**:

- **KISS** = Government call center workstation (inbound service desk)
- **Pipelinq** = General-purpose CRM/pipeline (sales + service + operations)

The overlap is primarily in **contact moment logging** and **contact management**. KISS's strength is its deep integration with the Dutch government API ecosystem (ZGW, BRP, KvK). Pipelinq's strength is its flexibility, Nextcloud integration, pipeline visualization, and automation capabilities.

### Competitive Threat Level: LOW-MEDIUM
KISS is not a direct competitor - it's a specialized government tool. However, municipalities using KISS may not see the need for Pipelinq's contact management features. The opportunity is to position Pipelinq as a **complementary tool** that handles the pipeline/workflow side that KISS lacks, or as a **replacement** for organizations that want everything in Nextcloud.

## External Dependencies

| Dependency | Purpose | Required? |
|-----------|---------|-----------|
| OpenKlant 1/2 | Customer registration, contact moments | Yes |
| OpenZaak (ZGW) | Case management | Yes |
| Haal Centraal BRP | Citizen lookup | Yes |
| KvK API | Business registry | Yes |
| Elasticsearch | Knowledge base search | Yes |
| Enterprise Search | Search engine management | Yes |
| Objecten API | VACs, employees, SDG products | Yes |
| OIDC Provider | Authentication | Yes |
| PostgreSQL | BFF internal data | Yes |
| SMTP | Feedback email | Optional |
| SharePoint | Knowledge source (optional) | Optional |
