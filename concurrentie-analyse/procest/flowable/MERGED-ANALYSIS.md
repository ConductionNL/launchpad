# Flowable -- Merged Competitive Analysis

**Analysis date:** 2026-03-14
**Analyzed by:** Automated competitive intelligence pipeline

---

## 1. Sources Summary

### Codebase Files Analyzed
| Category | Files | Description |
|----------|-------|-------------|
| Overview | `overview.md`, `flowable.md` | Company profile, positioning, feature comparison |
| Business Logic | `business-logic/browser-walkthrough-notes.md`, `business-logic/cmmn-case-lifecycle.md`, `business-logic/engine-integration.md` | UI walkthrough, state machines, engine integration flows |
| Documentation | `docs/case-management.md`, `docs/open-source-engines.md`, `docs/pricing-and-support.md`, `docs/product-platform.md`, `docs/success-stories.md` | Official website content captured |
| Specs | 15 spec files in `specs/` (see Feature Inventory below) | Detailed feature analysis per capability |
| **Total files** | **25 analysis files** | |

### Screenshots Captured
23 screenshots (`screenshots/01-idm-users.png` through `screenshots/23-admin-cmmn-engine.png`) covering the full Flowable open-source UI: IDM, Modeler (BPMN, CMMN, DMN, Forms, Apps), Task App, and Admin Dashboard. All taken from `flowable/all-in-one:latest` Docker image (v6.5.0).

### External Sources
- GitHub repository: `flowable/flowable-engine` (9,126 stars, 2,815 forks, 390 open issues)
- Official website: flowable.com (product pages, pricing, success stories, documentation)
- Live Docker instance walkthrough (localhost:9002)

---

## 2. Product Overview

### What It Is
Flowable is a **Java-based business process automation platform** implementing three OMG standards: BPMN 2.0 (processes), CMMN 1.1 (case management), and DMN 1.1 (decision tables). It positions itself as an "Agentic Case Platform" -- combining process automation, case management, and AI agents for process-intensive, compliance-driven organizations.

### Who Makes It
**Flowable AG**, a Swiss company founded in 2016. The project has Activiti heritage (forked/evolved from Activiti, the original Alfresco BPM engine).

**Leadership:**
- Agim Emruli (CEO)
- Micha Kiener (CTO)
- Tijs Rademakers (VP Product Engineering)

**Customer base:** 600+ enterprises worldwide, concentrated in banking (LGT, Raiffeisen, ZKB, Migros Bank), insurance (Direct Insurance, Concordia), and government (ASTRA Swiss Federal Roads Office, Provincie Antwerpen).

Recognized in the **Gartner Magic Quadrant for Business Orchestration and Automation Technologies 2025**.

### Tech Stack
| Layer | Technology |
|-------|-----------|
| Language | Java (JDK 17+, JRE 21 for Docker) |
| Framework | Spring Boot, Spring Security |
| ORM | MyBatis |
| Database | H2, MySQL, PostgreSQL, Oracle, MSSQL, DB2 |
| Schema Management | Liquibase |
| Connection Pooling | HikariCP (recommended) |
| Frontend (OSS) | Angular 1.x / Bootstrap 3 |
| Build | Maven (90+ modules) |
| Container | Tomcat 9 (4 WAR files in all-in-one) |
| Repo Size | 238 MB, 15,791 files |

### Product Tiers
1. **Community Edition** (Apache 2.0) -- Engines only, no UI/low-code/HA/reporting
2. **Flowable Platform** (Commercial) -- Work + Design + Control apps, enterprise security, low-code
3. **Agentic Case Platform** (Commercial) -- Everything + AI Studio, Orchestrator/Knowledge/Document/Utility/A2A agents, LLM integration

---

## 3. Architecture Summary

### Multi-Engine Architecture
Flowable uses a modular multi-engine architecture where each standard has its own engine, composed via shared services:

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
    +-----+------------------+------------------+-----+
    |              Shared Services                     |
    |  Task | Variable | Job | Identity | EntityLink   |
    |  Event Registry | IDM | Batch | History          |
    +--------------------------------------------------+
```

### Key Architectural Patterns
1. **Agenda-based execution** -- Each state change queued as an operation, executed sequentially within a transaction, with sentry re-evaluation after each step
2. **Command pattern** -- All service methods wrapped in commands with interceptor chains (transaction, logging, security)
3. **Shared task service** -- Tasks from BPMN and CMMN share the same table (`ACT_RU_TASK`) and query API, enabling unified task lists
4. **Optimistic locking** -- `REV_` column on all entities for concurrent access control
5. **Lock-based concurrency** -- `LOCK_TIME_` + `LOCK_OWNER_` for exclusive access to case instances
6. **Separate runtime/history** -- Runtime tables (`ACT_RU_*`) for active data, history tables (`ACT_HI_*`) for audit trail

### Database Architecture
- **CMMN tables:** 7 runtime + 3 definition + 3 history tables
- **BPMN tables:** `ACT_RU_EXECUTION`, `ACT_RE_PROCDEF`, etc.
- **DMN tables:** `ACT_DMN_*` prefix
- **Shared tables:** `ACT_RU_TASK`, `ACT_RU_VARIABLE`, `ACT_RU_IDENTITYLINK`, `ACT_RU_ENTITYLINK`
- **Identity tables:** `ACT_ID_*` prefix

### Application Components (OSS)
1. **flowable-idm** -- Identity management (users, groups, privileges)
2. **flowable-modeler** -- Visual editors for BPMN, CMMN, DMN, Forms
3. **flowable-task** -- Runtime task/process/case execution UI
4. **flowable-admin** -- Administration dashboard for all 6 engines

---

## 4. Feature Inventory

| # | Spec | Category | One-Line Description |
|---|------|----------|---------------------|
| 1 | `cmmn-engine` | core-engine | Full CMMN 1.1 implementation with 10 task types, 8 event listeners, sentries, stages, milestones, and case reactivation |
| 2 | `cmmn-case-management` | core-engine | Case-centric architecture with event-driven progression, discretionary items, and bidirectional BPMN integration |
| 3 | `bpmn-engine` | core-engine | Complete BPMN 2.0 runtime with 46+ element types, multi-instance, compensation, and process migration |
| 4 | `dmn-engine` | core-engine | DMN 1.1 decision table engine with all 7 hit policies and full audit trail |
| 5 | `dmn-decision-engine` | core-engine | Standalone/embeddable decision engine with strict mode, custom function delegates, and decision services chaining |
| 6 | `form-engine` | core-engine | JSON-based form engine with typed fields, outcomes (approve/reject), and co-deployment validation |
| 7 | `task-service` | core-engine | Shared task service across BPMN+CMMN with 5-state lifecycle, claiming, delegation, sub-tasks, and detailed event logging |
| 8 | `event-registry` | core-engine | Event broker abstraction with typed definitions, correlation-based routing, and JMS/Kafka/HTTP channels |
| 9 | `identity-management` | core-engine | Built-in user/group/privilege/token management with LDAP integration and identity link tracking |
| 10 | `rest-api` | technical | 60+ CMMN endpoints alone; comprehensive REST layer with complex POST-based queries and bulk operations |
| 11 | `api-architecture` | technical | Service-oriented API with Java and REST interfaces, MyBatis ORM, and rich query capabilities |
| 12 | `deployment-options` | technical | Embedded, standalone, Docker, Kubernetes, and cloud (shared/dedicated) deployment models |
| 13 | `process-orchestration` | platform | End-to-end orchestration via Work (runtime), Design (modeling), and Control (monitoring) applications |
| 14 | `agentic-ai` | platform | AI agents (Orchestrator, Knowledge, Document, Utility, A2A) embedded in CMMN engine with LLM support (commercial only) |
| 15 | `pricing-model` | business | Three-tier open-core model: free Community, user+RCPI Platform, user+RCPI+agent Agentic Case Platform |

---

## 5. Key Strengths

1. **Open standards native** -- The only platform implementing BPMN 2.0 + CMMN 1.1 + DMN 1.1 in a single integrated suite. This is rare in the market and provides interoperability and portability.

2. **Case-centric architecture** -- Built around the case as the foundational concept (not the process). Cases hold full context: data, policies, decisions, approvals, exceptions, and audit trail. This aligns with how government work actually operates.

3. **Battle-tested maturity** -- 10+ years of continuous development (Activiti heritage), 600+ enterprise customers, proven at scale in banking and insurance. ZKB reduced processing from 2 weeks to 2 hours.

4. **Comprehensive CMMN implementation** -- 10 task types, 8 event listener types, full sentry evaluation, stages, milestones, repetition rules, case reactivation, and formal migration service. The most complete open-source CMMN engine available.

5. **Unified task management** -- Tasks from BPMN processes and CMMN cases share the same table and query API, enabling a single task inbox regardless of origin. The 5-state task lifecycle (created/claimed/in-progress/suspended/completed) with delegation is mature.

6. **Rich REST API** -- 60+ CMMN endpoints alone, with complex POST-based queries supporting AND/OR conditions, variable filtering, and bulk operations. Consistent patterns across all engines.

7. **Apache 2.0 core** -- No vendor lock-in for the engine layer. Companies can embed Flowable in their own products (Dimpact ZAC does this).

8. **Gartner recognition** -- Magic Quadrant for Business Orchestration and Automation Technologies 2025, validating enterprise credibility.

9. **Spring Boot native** -- Easy integration for Java ecosystem, auto-configuration, actuator monitoring, Spring-managed transactions.

10. **Agentic AI roadmap** -- Forward-looking with MCP-based agent orchestration, A2A architecture, and multi-LLM support (though commercial only).

---

## 6. Key Weaknesses

1. **Java-only runtime** -- Requires JVM, heavy memory footprint (4 Spring Boot apps in all-in-one), complex deployment. Cannot embed in PHP, Python, or .NET applications.

2. **Open-core lock-in** -- Most valuable features (low-code, reporting, content management, business UI, HA, Agentic AI) are locked behind commercial license. The OSS edition is an engine without a usable end-user application.

3. **Dated OSS UI** -- The open-source modeler and task app use Angular 1.x / Bootstrap 3 styling. Translation keys broken (404 for locale files). Not modern or accessible.

4. **No domain specialization** -- Generic process engine with no built-in support for Dutch government standards (ZGW, VNG, StUF), zaakgericht werken patterns, or any specific industry domain.

5. **Complex deployment** -- 4 separate WAR files on Tomcat, separate database required, 90+ Maven modules, 238 MB repository. Significant operational overhead compared to a Nextcloud app.

6. **Opaque pricing** -- No public pricing for commercial tiers. "Contact sales" required, creating friction for smaller organizations and government procurement.

7. **No built-in document management (OSS)** -- Content management only available in commercial tier. No native file storage, versioning, or collaboration.

8. **Basic form builder** -- Only ~6 field types in OSS, no conditional visibility, no repeating sections, no table fields. The "Outcomes" tab (approve/reject buttons) is useful but the form builder is limited.

9. **XML-based model definitions** -- CMMN and BPMN models stored as XML, requiring specialized modeling tools and expertise. Steep learning curve for non-technical users.

10. **No NL Design System support** -- No Dutch government theming, no WCAG AA compliance verification, no government design token integration.

11. **No real-time collaboration** -- Single-user editing only in the modeler. No co-editing or shared workspace features.

12. **Enterprise focus** -- Designed for large organizations with dedicated Java development teams. Not suitable for small/medium organizations or citizen developers without the commercial platform.

---

## 7. Relevance to Procest

### Direct Competition
Flowable is the **most relevant open-source competitor** to Procest in the case management space. Its CMMN engine directly addresses the same problem domain: managing complex, unpredictable cases with human-in-the-loop decisions. However, Flowable competes as a **generic engine** while Procest competes as a **domain-specific application**.

### How Flowable Is Used in Dutch Government
Flowable is already present in the Dutch government ecosystem as the embedded engine inside **Dimpact ZAC** (Zaak Afhandel Component). This means Dutch municipalities are already running Flowable indirectly, but through a purpose-built application layer on top.

### Where Procest Wins
| Dimension | Procest Advantage |
|-----------|------------------|
| **Deployment simplicity** | Nextcloud app (one-click install) vs Java stack with separate database |
| **Document management** | Native Nextcloud Files integration vs commercial-only content engine |
| **Dutch government standards** | Built-in ZGW, VNG, zaakgericht werken vs generic engine |
| **Accessibility** | WCAG AA + NL Design System vs dated Angular 1.x UI |
| **Ecosystem integration** | Nextcloud collaboration (users, groups, sharing, Talk, calendar) vs standalone |
| **Pricing transparency** | Open-source with full functionality vs open-core with gated features |
| **Operational cost** | Shared Nextcloud infrastructure vs dedicated Java servers |
| **Integration breadth** | n8n with 400+ nodes vs Flowable's smaller connector library |
| **Data sovereignty** | Nextcloud self-hosted + Ollama local AI vs cloud LLM dependency |

### Where Flowable Wins
| Dimension | Flowable Advantage |
|-----------|-------------------|
| **Standards compliance** | Full BPMN 2.0 + CMMN 1.1 + DMN 1.1 vs custom case model |
| **Engine maturity** | 10+ years, 600+ enterprises vs early-stage product |
| **Case lifecycle** | 8 states + 3 extensions, sentries, milestones vs simpler state model |
| **Task management** | 5 states, claim/delegate/suspend vs basic open/completed |
| **Decision engine** | 7 DMN hit policies vs n8n IF/Switch |
| **API breadth** | 60+ CMMN endpoints vs ~10-15 endpoints |
| **History/audit** | Dedicated history tables with 11 timestamps per plan item vs OpenRegister audit |
| **Process migration** | Formal migration service vs manual |
| **Multi-tenancy** | Native tenant isolation vs Nextcloud instance scope |
| **Enterprise validation** | Gartner MQ, banking/insurance deployments vs emerging product |

---

## 8. Feature Gap Analysis

Features Flowable has that Procest should consider implementing, ordered by relevance to Dutch government case management:

### High Priority (Core Case Management)

| # | Feature | Flowable Implementation | Procest Recommendation |
|---|---------|------------------------|----------------------|
| 1 | **Task claiming** | Candidate users/groups claim tasks from pool | Implement claim/unclaim with candidate group support via Nextcloud groups |
| 2 | **Task lifecycle states** | Created/claimed/in-progress/suspended/completed | Extend beyond open/completed to at least include claimed and in-progress |
| 3 | **Stage-based progress** | `getStageOverview()` API returns visual stage progress | Implement case progress visualization showing completed/active/pending phases |
| 4 | **Case history/audit** | Dedicated history tables with 11 timestamps per plan item | Build comprehensive audit trail beyond OpenRegister's generic logging |
| 5 | **Milestone tracking** | Named achievement points within cases | Implement milestones as case checkpoints with completion tracking |
| 6 | **Case reactivation** | Built-in ability to reopen completed cases | Allow reopening closed cases (common in government: new information on closed zaak) |

### Medium Priority (Advanced Case Patterns)

| # | Feature | Flowable Implementation | Procest Recommendation |
|---|---------|------------------------|----------------------|
| 7 | **Task delegation** | Delegate to colleague with resolve workflow | Implement delegation for vacation/absence scenarios |
| 8 | **Sub-tasks** | Parent-child task hierarchy | Allow task decomposition for complex activities |
| 9 | **Decision tables** | DMN engine with 7 hit policies | Build simpler rule evaluation UI (e.g., eligibility, routing, SLA calculation) |
| 10 | **Event-driven triggers** | Event registry with correlation routing | Leverage n8n webhooks and triggers more systematically |
| 11 | **Bulk operations** | Bulk terminate/delete case instances | Add bulk actions for case management efficiency |
| 12 | **Complex queries** | POST-based queries with AND/OR, variable filtering | Extend API with advanced filtering for dashboards and reporting |

### Lower Priority (Nice-to-Have)

| # | Feature | Flowable Implementation | Procest Recommendation |
|---|---------|------------------------|----------------------|
| 13 | **Case migration** | Formal version-to-version migration service | Not urgent for Procest's simpler model, but plan for it |
| 14 | **Two-level due dates** | inProgressStartDueDate + dueDate | Single due date likely sufficient for government use |
| 15 | **Multi-instance patterns** | Parallel/sequential execution on any activity | n8n handles parallel execution adequately |
| 16 | **BPMN import** | Native BPMN 2.0 support | Consider import capability for migration from Flowable/Camunda environments |

### Features Where Procest Already Leads

| Feature | Procest Has, Flowable Lacks |
|---------|---------------------------|
| ZGW API compatibility | Native support for Dutch government standards |
| Document checklists | Case-specific document requirements |
| Confidentiality levels | Per-case/per-document access control |
| Nextcloud integration | Files, users, groups, sharing, Talk, calendar |
| NL Design System | Government theming and accessibility |
| WCAG AA compliance | Accessibility-first UI design |
| n8n integration | 400+ integration nodes for automation |
| Local AI (Ollama) | Data-sovereign AI processing |

---

## Summary

Flowable is the gold standard for open-source CMMN case management engines, but it is a **component** (engine), not a **complete application**. It requires significant development effort to build a usable case management system on top. Procest's competitive advantage lies in being a **complete, domain-specific, Nextcloud-integrated application** for Dutch government case management -- trading Flowable's standards depth for deployment simplicity, ecosystem integration, and government-specific features. The key features to adopt from Flowable are task claiming, richer task lifecycle states, stage-based progress visualization, and comprehensive audit trails.
