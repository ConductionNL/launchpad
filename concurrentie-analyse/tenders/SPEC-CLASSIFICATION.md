# Spec Classification Framework — Abstraction Routing for Tender Requirements

## Purpose

When extracting requirements from Dutch government tenders, each requirement must be routed to the correct **abstraction layer** before creating a spec. This prevents duplicate specs across apps and ensures capabilities are built at the right level of the stack.

## Abstraction Layers

### Layer 1: Foundation (OpenRegister)

Capabilities that **all apps inherit**. If a tender requirement is about data management, security fundamentals, or cross-cutting concerns, it belongs here.

| Category | Examples | Rationale |
|----------|----------|-----------|
| Data storage & CRUD | Object storage, schema management, registers | All apps store data through OpenRegister |
| Audit trail & logging | Who changed what, when, mutation history | Foundation-level — inherited by every app |
| Archiving (Archiefwet) | Retention policies, selectielijsten, destruction | Legal obligation applies to all data, not one app |
| RBAC & access control | Role-based permissions, organization multitenancy | Shared permission model across apps |
| Search & faceting | Full-text search, filters, Solr integration | Cross-app search capability |
| Data retention & GDPR | Right of access, right to erasure, data export | Privacy applies to all registered data |
| API patterns | REST API, MCP, JSON-LD, linked data | Shared API layer |
| Source synchronization | External data sources, federation, sync | Data layer concern |
| Organisation management | Multitenancy, org profiles, org-level config | Cross-app identity |
| File attachments | Files linked to objects (via Nextcloud Files) | Foundation file handling |
| Event-driven architecture | Webhooks, cloud events, event listeners | Cross-app integration bus |

**Routing rule:** If the requirement is about *data*, *security*, *audit*, *archiving*, or *API access* without domain-specific business logic, it goes to OpenRegister.

### Layer 2: Platform (Nextcloud Core)

Capabilities provided by Nextcloud itself. These are **not built by Conduction** but are leveraged by all apps. Tender responses should reference these as "provided by the platform."

| Category | Examples | Rationale |
|----------|----------|-----------|
| Authentication | SSO, SAML, LDAP, 2FA, DigiD (via SAML) | Nextcloud core auth |
| User management | User provisioning, groups, group admin | Nextcloud user system |
| File management | Upload, versioning, sharing, WebDAV | Nextcloud Files |
| Calendar & tasks | CalDAV, VTODO, scheduling | Nextcloud Calendar |
| Notifications | Push, email, in-app notifications | Nextcloud Notifications |
| Activity feeds | Activity stream, audit log UI | Nextcloud Activity |
| Real-time chat | Talk rooms, video calls, screen sharing | Nextcloud Talk |
| Federation | Cross-instance sharing, federated cloud | Nextcloud Federation |
| Mobile apps | iOS, Android, desktop sync clients | Nextcloud mobile |
| Email | Mail app, SMTP integration | Nextcloud Mail |
| AI features | AI assistant, text generation, image recognition | Nextcloud AI |
| Encryption | Server-side encryption, E2EE | Nextcloud Encryption |

**Routing rule:** If the requirement is about *infrastructure*, *authentication*, *file storage*, *communication*, or *mobile access*, reference Nextcloud core capabilities. Only create a spec if custom integration work is needed.

### Layer 3: Theming (NL Design)

Government visual identity and accessibility compliance. If the requirement is about how the application *looks* or *meets accessibility standards*, it routes here.

| Category | Examples | Rationale |
|----------|----------|-----------|
| Design tokens | NL Design System tokens, huisstijl | Government visual identity |
| WCAG compliance | WCAG 2.1 AA, EN 301 549, keyboard navigation | Accessibility law |
| Government branding | Municipality logos, color schemes | Per-organisation theming |
| Token sets | VNG, Den Haag, Rotterdam, custom token sets | Multi-tenant theming |
| Responsive design | Mobile-friendly, tablet support | Usability on all devices |

**Routing rule:** If the requirement is about *visual identity*, *accessibility*, *branding*, or *design standards*, it goes to NL Design.

### Layer 4: Documents (Docudesk)

Document lifecycle management. If the requirement is about *document processing*, *generation*, or *compliance publishing*, it routes here.

| Category | Examples | Rationale |
|----------|----------|-----------|
| Document generation | Templates, merge fields, PDF/DOCX output | Document creation |
| Document validation | Format checks, metadata validation | Document quality |
| Digital signing | Electronic signatures, PKIoverheid | Legal validity |
| GDPR anonymization | PII redaction, anonymization | Publication compliance |
| Text extraction | OCR, NLP, keyword extraction | Document intelligence |
| Publication consent | Wet Open Overheid, objection periods | WOO compliance |
| Document classification | Auto-categorization, topic detection | Document management |
| Batch processing | Bulk document operations | Scale |

**Routing rule:** If the requirement is about *creating*, *validating*, *signing*, *classifying*, or *publishing documents*, it goes to Docudesk.

### Layer 5: Integration (OpenConnector)

External system integration, API gateway, and data synchronization. If the requirement is about *connecting to external systems* or *API translation*, it routes here.

| Category | Examples | Rationale |
|----------|----------|-----------|
| API gateway | Reverse proxy, rate limiting, API key management | External API access |
| Data synchronization | Source-to-register sync, scheduled sync | External data ingestion |
| API translation | StUF-to-REST, SOAP-to-JSON mapping | Legacy system integration |
| Cloud events | Event bus, pub/sub, webhook dispatch | Event-driven integration |
| Authentication relay | OAuth, API keys, certificate auth to external APIs | External auth |

**Routing rule:** If the requirement is about *connecting to external systems*, *translating between API standards*, or *synchronizing external data*, it goes to OpenConnector.

### Layer 6: Catalogus (OpenCatalogi)

Federated catalogue and open data publication. If the requirement is about *publishing catalogues*, *metadata standards*, or *federated data sharing*, it routes here.

| Category | Examples | Rationale |
|----------|----------|-----------|
| Catalogue management | Publication listings, metadata, categories | Core catalogue |
| Federated sync | Cross-organisation catalogue federation | Federated data |
| Open data publishing | DCAT, Schema.org, open data portals | Open data standards |
| Metadata standards | Dublin Core, OWMS, TOOI | Government metadata |
| Directory services | Organisation directories, service listings | Service discovery |

**Routing rule:** If the requirement is about *publishing metadata*, *catalogue federation*, or *open data standards*, it goes to OpenCatalogi.

### Layer 7: Dashboard (MyDash)

Dashboard and reporting capabilities. If the requirement is about *KPI visualization*, *dashboard customization*, or *information presentation*, it routes here.

| Category | Examples | Rationale |
|----------|----------|-----------|
| Dashboard layouts | Drag-and-drop grids, multiple dashboards | Dashboard UX |
| Admin templates | Pre-configured dashboards for teams | Organizational dashboards |
| Widget management | Custom tiles, shortcuts, widget styling | Dashboard components |
| Conditional visibility | Show/hide based on role, time, group | Smart dashboards |
| KPI presentation | Charts, counters, status indicators | Management reporting |

**Routing rule:** If the requirement is about *dashboards*, *KPI visualization*, or *information presentation for management*, it goes to MyDash.

### Layer 8: Software Portfolio (SoftwareCatalog)

Software landscape management and GEMMA compliance. If the requirement is about *tracking an organisation's software portfolio*, it routes here.

| Category | Examples | Rationale |
|----------|----------|-----------|
| Software registration | Application metadata, module tracking | Portfolio management |
| Connection mapping | System dependencies, koppelingen | Landscape visibility |
| GEMMA compliance | Gemeentelijk Model Architectuur categories | Dutch gov architecture |
| User provisioning | Auto-create users from catalogue data | Access management |

**Routing rule:** If the requirement is about *the organisation's software landscape* or *GEMMA architecture compliance*, it goes to SoftwareCatalog.

### Layer 9: App-Specific (Procest / Pipelinq / etc.)

Domain-specific business logic and UI. Requirements that are **unique to a domain** and cannot be generalized to the foundation or platform layers.

| App | Domain | Examples |
|-----|--------|----------|
| **Procest** | Case management | Case lifecycle, case types, CMMN, zaakgericht werken, ZGW Zaken/Besluiten/Catalogi |
| **Pipelinq** | CRM & client interaction | Client/lead management, pipelines, kanban, VNG Klantinteracties/Verzoeken |
| **ZaakAfhandelApp** | Case handling | Case handling workflows (legacy, being merged into Procest) |
| **LarpingApp** | LARP event management | Character management, event registration, game mechanics |

**Routing rule:** If the requirement describes *domain-specific workflows*, *domain-specific UI patterns*, or *domain-specific business rules* that only make sense in one application context, it goes to that specific app.

## Classification Decision Tree

```
Tender Requirement
│
├─ Is it about DATA storage, audit, archiving, RBAC, search, GDPR?
│  └─ YES → Layer 1: OpenRegister (Foundation)
│
├─ Is it about AUTH, files, calendar, chat, federation, mobile?
│  └─ YES → Layer 2: Nextcloud Core (Platform) — reference, don't spec
│
├─ Is it about VISUAL IDENTITY, accessibility, design tokens, branding?
│  └─ YES → Layer 3: NL Design (Theming)
│
├─ Is it about DOCUMENT creation, signing, anonymization, publication?
│  └─ YES → Layer 4: Docudesk (Documents)
│
├─ Is it about CONNECTING to external systems, API translation, sync?
│  └─ YES → Layer 5: OpenConnector (Integration)
│
├─ Is it about CATALOGUE publishing, open data, metadata standards?
│  └─ YES → Layer 6: OpenCatalogi (Catalogus)
│
├─ Is it about DASHBOARDS, KPI visualization, admin templates?
│  └─ YES → Layer 7: MyDash (Dashboard)
│
├─ Is it about SOFTWARE PORTFOLIO tracking, GEMMA architecture?
│  └─ YES → Layer 8: SoftwareCatalog (Software Portfolio)
│
└─ Is it about DOMAIN-SPECIFIC workflows (cases, CRM, leads)?
   └─ YES → Layer 9: App-Specific (Procest/Pipelinq/etc.)
```

## Split Requirements

Some tender requirements span multiple layers. In that case:

1. **Create one spec per layer** — e.g., "audit logging with configurable retention" becomes:
   - OpenRegister spec: audit trail storage and retention policy enforcement
   - App-specific spec: UI for viewing audit trail in the app's context

2. **Link specs with references** — use `depends_on:` in the spec frontmatter to link cross-layer specs

3. **Foundation takes precedence** — if a capability can be built at the foundation layer and inherited, prefer that over building it in every app

## Common Dutch Tender Terms → Layer Mapping

| Dutch Term | English | Primary Layer | Notes |
|------------|---------|---------------|-------|
| Zaakgericht werken | Case-oriented working | Procest (App) | |
| Archiefwet | Archival law | OpenRegister (Foundation) | Cross-cutting |
| AVG / GDPR | Privacy regulation | OpenRegister (Foundation) | Cross-cutting |
| BIO | Baseline security | Nextcloud (Platform) | Infrastructure |
| DigiD | Citizen authentication | Nextcloud (Platform) | Via SAML |
| PKIoverheid | Government PKI | Docudesk (Documents) | Digital signing |
| Wet Open Overheid (WOO) | Open Government Act | Docudesk (Documents) | Publication consent |
| WCAG 2.1 AA | Web accessibility | NL Design (Theming) | |
| NL Design System | Government design tokens | NL Design (Theming) | |
| Common Ground | Architecture principles | OpenConnector (Integration) | API layer patterns |
| ZGW API's | Case management APIs | Procest (App) | Domain-specific |
| Klantinteracties | Client interactions | Pipelinq (App) | Domain-specific |
| StUF | Legacy XML standard | OpenConnector (Integration) | API translation |
| DCAT | Data Catalogue Vocabulary | OpenCatalogi (Catalogus) | Metadata standard |
| GEMMA | Municipal architecture model | SoftwareCatalog (Portfolio) | Architecture standard |
| Selectielijst | Retention schedule | OpenRegister (Foundation) | Archiving |
| Vernietigingslijst | Destruction schedule | OpenRegister (Foundation) | Archiving |
| Programma van Eisen | Requirements spec | — | Input document, not a layer |
| Koppelvlak | Integration interface | OpenConnector (Integration) | |

## Usage in Tender Pipeline

This framework is used in **Phase 3: Analyze** of the tender pipeline (see TENDER-PLAN.md). After extracting requirements from tender texts:

1. **Classify** each requirement using the decision tree above
2. **Check for existing specs** in the target layer's OpenSpec directory
3. **Create new specs** only if the requirement isn't already covered
4. **Link to tender source** using `source: tender` and `tender_id:` in spec frontmatter
5. **Track frequency** — requirements that appear across multiple tenders get higher priority
