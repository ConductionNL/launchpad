# Flowable - Competitive Analysis Overview

## Company Profile

- **Company**: Flowable AG (Swiss company, founded in Switzerland)
- **Website**: https://www.flowable.com
- **GitHub**: https://github.com/flowable/flowable-engine
- **License**: Apache 2.0 (open-source core)
- **Language**: Java (JDK 17+)
- **GitHub Stars**: 9,126
- **GitHub Forks**: 2,815
- **Open Issues**: 390
- **Founded**: 2016 (repo created Oct 2016)
- **Last Updated**: Active development (latest commit Mar 12, 2026)
- **Recognized by**: Gartner Magic Quadrant for Business Orchestration and Automation Technologies 2025

## Product Positioning

Flowable positions itself as an **"Agentic Case Platform"** - combining process automation, case management, and AI agents. Their tagline is "Agentic process automation that performs for you, with you." They target process-intensive, compliance-driven organizations.

Key differentiator: Flowable is built around the **case** (not the task). A case is a living, structured environment holding full context of a piece of work: data, policies, decisions, approvals, AI-generated insights, exceptions, and an unbroken audit trail.

## Product Tiers

### 1. Community Edition (Open Source)
- BPMN, CMMN, DMN engines
- Apache 2.0 license
- Community support only
- No HA/scalability support
- No low-code, no reporting, no UI, no content management

### 2. Flowable Platform (Commercial)
- Flowable Work + Flowable Design + Flowable Control
- BPMN, CMMN, DMN engines
- Enterprise security, low-code, reporting & analytics
- Configurable business UI and rich forms
- Visual design & debug (Inspect)
- Content management
- Configurable task management
- Customer managed or Flowable Cloud (shared/dedicated)
- Silver/Gold/Platinum support SLAs
- User-based pricing + RCPI (Running Case & Process Instance) packages

### 3. Agentic Case Platform (Commercial)
- Everything in Flowable Platform +
- Flowable AI Studio
- Orchestrator AI Agents (embedded in CMMN engine)
- Knowledge AI Agents (RAG)
- Document AI Agents (classification & extraction)
- Utility AI Agents (tool support)
- A2A Agents
- External Agents (Azure AI Foundry, AWS Bedrock, Agentforce)
- LLM support: OpenAI, Azure OpenAI, Anthropic (BYOL)
- AI-assisted modeling and generation
- User + RCPI + Agent package pricing

## Target Industries

- **Banking** (primary - most success stories)
- **Insurance**
- **Healthcare**
- **Manufacturing**
- **Government** (ASTRA - Swiss Federal Roads Office success story, Provincie Antwerpen)

## Key Customers (600+ enterprises worldwide)

### Banking & Finance
- LGT, Raiffeisen, Zurich Kantonalbank, Migros Bank, Bank CLER, Basler Kantonalbank, Graubundner Kantonalbank, SEBA, SDX, Quilvest, BTG Pactual, Federal Home Loan Bank of Atlanta, Atom Bank, Kindred

### Insurance
- Direct Insurance, Concordia, Vaudoise, Hotela

### Technology
- Autodesk, T-Systems, BT, Accenture

### Government/Public Sector
- ASTRA (Swiss Federal Roads Office), Provincie Antwerpen, CdC/ZAS (Swiss social security)

### Others
- Great Dane (manufacturing), Axial3D (medtech), Taylor & Francis (publishing), York University

## Management Team

- **Agim Emruli** - CEO
- **Micha Kiener** - CTO
- **Tijs Rademakers** - VP Product Engineering
- **Fabio Filippelli** - VP Global Sales
- **Simon Maier** - VP Operations
- **Stephan Aina** - VP Sales EMEA
- **Paul Holmes-Higgin** - Fellow

## Competitive Strengths

1. **Open standards native**: BPMN + CMMN + DMN in a single platform (rare in market)
2. **Case-centric architecture**: Case as the foundation, not an afterthought
3. **Apache 2.0 open source core**: No vendor lock-in for engine
4. **Enterprise proven**: 600+ enterprises, Gartner recognized
5. **Swiss origin**: Strong compliance/regulatory DNA
6. **Active development**: 10 years of continuous development
7. **AI integration**: Agentic AI embedded in CMMN engine (2025+)
8. **Comprehensive API**: Rich Java and REST APIs
9. **Spring Boot native**: Easy integration for Java ecosystem
10. **Multi-database**: H2, MySQL, PostgreSQL, Oracle, MSSQL, DB2

## Competitive Weaknesses (vs Procest)

1. **Java-only engine**: Requires JVM, heavy runtime
2. **Complex deployment**: Enterprise Java stack (Spring, MyBatis, etc.)
3. **No Nextcloud integration**: Standalone system, no platform ecosystem
4. **Opaque pricing**: Contact sales required, no public pricing
5. **Open-core model**: Many features locked behind commercial license
6. **No PHP support**: Can't embed in PHP applications
7. **Heavy weight**: Large codebase (238MB repo, 90+ Maven modules, 15,791 files), complex setup
8. **Enterprise focus**: Not designed for small/medium organizations
9. **No built-in document management**: Content management only in commercial tier
10. **No NL Design System support**: No Dutch government theming/accessibility standards
11. **No native Dutch government standard support**: No VNG, ZGW, or StUF integration
12. **XML-based model definitions**: CMMN XML has steep learning curve vs JSON
13. **No low-code approach in OSS tier**: Low-code tooling locked behind commercial license

## Technical Architecture (Codebase Analysis)

**Analyzed from:** https://github.com/flowable/flowable-engine (commit at 2026-03-14)
**Schema version:** 8.0.0.0

### Module Architecture

Flowable follows a modular multi-engine architecture where each standard (BPMN, CMMN, DMN) has its own engine, composed via shared services:

```
                    +------------------+
                    |   App Engine     |
                    | (Unified Entry)  |
                    +--------+---------+
                             |
          +------------------+------------------+
          |                  |                  |
    +-----+------+    +-----+------+    +------+-----+
    | BPMN Engine|    | CMMN Engine|    | DMN Engine |
    | (Processes)|    | (Cases)    |    | (Decisions)|
    +-----+------+    +-----+------+    +------+-----+
          |                  |                  |
    +-----+------+    +-----+------+    +------+-----+
    | BPMN REST  |    | CMMN REST  |    | DMN REST   |
    +------------+    +------------+    +------------+
          |                  |                  |
    +-----+------------------+------------------+-----+
    |              Shared Services                     |
    |  Task | Variable | Job | Identity | EntityLink   |
    |  Event Registry | IDM | Batch | History          |
    +--------------------------------------------------+
```

### Module Count: 90+ Maven modules

| Category | Key Modules | Purpose |
|----------|-------------|---------|
| CMMN | `flowable-cmmn-api`, `-engine`, `-model`, `-rest`, `-converter` | Case management (CMMN 1.1) |
| BPMN | `flowable-engine`, `flowable-bpmn-model`, `-converter`, `flowable-rest` | Process execution (BPMN 2.0) |
| DMN | `flowable-dmn-api`, `-engine`, `-model`, `-rest` | Decision tables (DMN 1.1+) |
| Form | `flowable-form-api`, `flowable-form-model` | Form definitions and rendering |
| Event Registry | `flowable-event-registry`, `-api`, `-model` | Event-driven integration |
| Identity | `flowable-idm-api`, `-engine`, `flowable-ldap` | User/group/privilege management |
| Shared Services | `flowable-task-service`, `flowable-variable-service`, `flowable-job-service` | Cross-engine shared services |
| Infrastructure | `flowable-spring-boot`, `flowable-common-rest`, `flowable-spring-security` | Spring Boot auto-config |

### Database Architecture

Runtime tables (`ACT_RU_*`), History tables (`ACT_HI_*`), Definition tables (`ACT_RE_*`/`ACT_CMMN_*`):

**CMMN Tables:**
- `ACT_CMMN_DEPLOYMENT` + `ACT_CMMN_DEPLOYMENT_RESOURCE` -- deployed artifacts
- `ACT_CMMN_CASEDEF` -- versioned case definitions (unique key+version+tenant)
- `ACT_CMMN_RU_CASE_INST` -- active case instances (with optimistic locking)
- `ACT_CMMN_RU_PLAN_ITEM_INST` -- active plan items (11 timestamp columns for full lifecycle)
- `ACT_CMMN_RU_SENTRY_PART_INST` -- sentry evaluation state
- `ACT_CMMN_RU_MIL_INST` -- active milestone instances
- `ACT_CMMN_HI_CASE_INST` / `HI_MIL_INST` / `HI_PLAN_ITEM_INST` -- history tables

**Shared Tables:**
- `ACT_RU_TASK` -- unified task table (BPMN + CMMN tasks in one table)
- `ACT_RU_VARIABLE` -- unified variable storage
- `ACT_RU_IDENTITYLINK` -- user/group involvement
- `ACT_RU_ENTITYLINK` -- cross-engine references

### Key Technical Patterns

1. **Agenda-based execution**: Each state change queued as operation, executed sequentially within transaction, sentries re-evaluated after each step
2. **Command pattern**: All service methods wrapped in commands with interceptor chain
3. **Shared task service**: Tasks from BPMN and CMMN share same table and query API
4. **Optimistic locking**: `REV_` column on all entities for concurrent access control
5. **Lock-based concurrency**: `LOCK_TIME_` + `LOCK_OWNER_` for exclusive access to case instances

### Feature Inventory for Procest Comparison

| Feature | Flowable | Procest |
|---------|----------|---------|
| Standards compliance | Full BPMN 2.0 + CMMN 1.1 + DMN 1.1 | Custom case model |
| Case lifecycle states | 8 standard + 3 extensions | Simpler state model |
| Plan item types | 10 task types + events + milestones | Tasks via OpenRegister |
| Sentries (criteria) | Full entry/exit criteria with conditions | n8n conditions |
| Human task lifecycle | 5 states (created/claimed/inProgress/suspended/completed) | Basic open/completed |
| Task claiming | Claim/unclaim pattern with candidate users/groups | Direct assignment |
| Task delegation | Full delegation with resolve workflow | Not available |
| Decision tables | DMN with 7 hit policies | n8n IF/Switch nodes |
| Forms | Dedicated form engine (typed fields, outcomes) | Nextcloud Forms / Vue |
| Event registry | Typed events with correlation routing | n8n triggers/webhooks |
| History | Dedicated history tables with full audit | OpenRegister audit |
| Case migration | Formal migration service | Manual |
| Case reactivation | Built-in (reopen completed cases) | Not available |
| Stage overview | Visual stage progress API | Not available |
| Multi-tenancy | Native tenant isolation | Nextcloud org-based |
| REST API | 60+ CMMN endpoints alone | ~10-15 endpoints |
| Deployment model | Standalone Java app / embedded lib | Nextcloud app (PHP + n8n) |

### Detailed Specs

See `/specs/` for detailed analysis of each engine:
- `specs/cmmn-engine/spec.md` -- CMMN case management (most relevant)
- `specs/bpmn-engine/spec.md` -- BPMN process automation
- `specs/dmn-engine/spec.md` -- DMN decision tables
- `specs/rest-api/spec.md` -- REST API layer
- `specs/form-engine/spec.md` -- Form engine
- `specs/event-registry/spec.md` -- Event registry
- `specs/identity-management/spec.md` -- Identity management
- `specs/task-service/spec.md` -- Shared task service

See `/business-logic/` for Mermaid diagrams of key business logic flows.
