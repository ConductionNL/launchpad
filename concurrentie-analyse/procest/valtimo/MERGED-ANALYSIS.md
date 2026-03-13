# Valtimo (GZAC) — Merged Competitive Analysis

**Analyzed**: 2026-03-13
**Sources**: Codebase (4,100+ backend files, 2,600+ frontend files), Documentation (12 docs), Browser walkthrough (15 screenshots from live Docker)
**Verdict**: Most serious Procest competitor — full BPMN process engine + ZGW integration + growing government adoption

---

## Executive Summary

Valtimo is a **process automation and case management platform** built on Operaton (Camunda 7 fork), developed by Ritense/Taxonic (Netherlands). It targets Dutch government organizations for zaakgericht werken under the Common Ground/ZGW API ecosystem. Licensed under EUPL 1.2.

**This is Procest's most direct and serious competitor.** Valtimo has a mature process engine (BPMN 2.0 + DMN), deep ZGW integration (10+ API plugins), growing municipality adoption via Dimpact, and a comprehensive plugin system. It solves the same problem — case management for Dutch government — with a fundamentally different architecture (JVM + Camunda vs PHP + Nextcloud).

## Scale

- Backend: ~4,100 files, 50+ Kotlin/Java modules (Spring Boot + JPA + Operaton)
- Frontend: ~2,600 files, 39 Angular libraries (Carbon Design System)
- Stack: Keycloak + PostgreSQL + RabbitMQ + Operaton engine (6+ containers)

## Architecture

```
Angular 16+ (Carbon Design) <-> Spring Boot BFF <-> Operaton/Camunda 7 Engine
                                                 <-> PostgreSQL (Hibernate)
                                                 <-> Keycloak (OIDC)
                                                 <-> RabbitMQ (Outbox)
                                                 <-> ZGW APIs (10+ plugins)
```

| Layer | Technology |
|-------|-----------|
| Backend | Kotlin/Java, Spring Boot 3, Operaton (Camunda 7 fork), Hibernate 6 |
| Frontend | Angular 16+, TypeScript, IBM Carbon Design System |
| Auth | Keycloak (OIDC) with dedicated IAM module |
| Database | PostgreSQL/MySQL via Liquibase |
| Messaging | RabbitMQ (outbox pattern) |
| Process Engine | BPMN 2.0 + DMN decision tables |

## Key Comparison

| Aspect | Valtimo | Procest |
|--------|---------|---------|
| Process engine | Full BPMN 2.0 (Operaton/Camunda 7) | n8n visual automation |
| Case management | JSON Schema documents + configurable tabs | Vue.js case handling UI |
| Task management | BPMN user tasks + assignment | My Work queue + assignment |
| ZGW integration | 10+ plugins (Zaken, Documenten, Catalogi, Objecten, Besluiten, Notificaties) | Basic ZGW compatibility |
| Forms | Form.io + multi-step FormFlow wizards | Vue.js forms |
| Auth | Keycloak OIDC + PBAC | Nextcloud auth |
| Dashboards | Configurable widgets with data sources | Basic dashboard |
| Plugins | Annotation-based extensibility framework | n8n + ExApp architecture |
| Frontend | Angular + Carbon Design | Vue.js + NL Design |
| Deployment | 6+ Docker containers (JVM + Keycloak + RabbitMQ) | Nextcloud app (zero-infra) |
| Document generation | SmartDocuments + local PDF | Not available |
| Decision tables | DMN engine | Not available |
| Process visualization | BPMN modeler + heatmaps | Not available |

## Features Valtimo Has That Procest DOES NOT Have

| Feature | Description | Priority |
|---------|-------------|----------|
| **BPMN 2.0 Process Engine** | Visual process modeler, service tasks, timers, gateways, events, heatmaps | CRITICAL — core differentiator |
| **DMN Decision Tables** | Business rule engine for automated decisions | HIGH |
| **Plugin System** | Annotation-based (`@Plugin`, `@PluginAction`) with encrypted properties, DB-stored configs | HIGH |
| **Deep ZGW Integration** | 10+ plugins: Zaken, Documenten, Catalogi, Objecten, Besluiten, Notificaties, Portaaltaak | HIGH |
| **Form.io Integration** | Drag-and-drop form builder with JSON Schema | MEDIUM |
| **FormFlow (Multi-Step Wizards)** | Step-by-step form completion with breadcrumbs, back, intermediate save | MEDIUM |
| **Policy-Based Access Control** | Fine-grained permissions with field conditions, JPA Criteria query-level filtering | HIGH |
| **Configurable Case Tabs** | Admin-configurable tabs per case type (standard, formio, custom, widgets) | MEDIUM |
| **Case Tags with Colors** | Colored label system for cases | LOW |
| **Milestone Tracking** | Process milestones linked to BPMN nodes with milestone sets | MEDIUM |
| **Value Resolvers** | Pluggable data binding (`doc:`, `pv:`, etc.) between forms, processes, and documents | MEDIUM |
| **SmartDocuments Integration** | Template-based document generation | MEDIUM |
| **Outbox Pattern (RabbitMQ)** | Reliable event distribution | LOW |
| **Process Instance Migration** | Batch migration between process versions | LOW |
| **Case Widgets** | Configurable dashboard widgets on case detail tabs | MEDIUM |
| **CSV Case Export** | Export case lists to CSV | LOW |
| **Document Snapshots** | Point-in-time document snapshots | LOW |
| **Case Definition Import/Export** | ZIP archives with full definition + config | MEDIUM |
| **Quick Search** | Stored search queries per user per case type | LOW |
| **Portal Tasks (Portaaltaak)** | Citizen-facing portal task system | MEDIUM |
| **Process Heatmaps** | Visual analytics on BPMN process execution (count + duration) | LOW |

## Features Procest Has That Valtimo LACKS

| Feature | Procest | Valtimo |
|---------|---------|---------|
| **Nextcloud Integration** | Native files, users, sharing, collaboration | Not available |
| **MCP/AI Integration** | LLM-friendly API + vector search | Not available |
| **n8n Visual Automation** | Low-code automation (more accessible than BPMN) | Not available |
| **NL Design System Theming** | Government design system with token-based theming | Carbon Design (not NL Design) |
| **Pipeline/Kanban Views** | Visual drag-and-drop pipeline | Not available |
| **Lightweight Architecture** | PHP + Vue.js, runs as Nextcloud app | JVM + Keycloak + RabbitMQ (6+ containers) |
| **Document Checklists** | Checklist-based document requirements | Not available |
| **Zero-Infra Deployment** | Install as Nextcloud app | Requires dedicated infrastructure |

## Specs Created

### From Codebase (13 specs)
case-management, process-engine, plugin-system, authorization-pbac, zgw-integration, form-system, dashboard-system, task-management, document-generation, audit-trail, milestone-tracking, value-resolvers, case-import-export

### From Documentation (8 docs)
architecture, modules-overview, access-control, process-management, forms-and-formflow, dashboard, search-and-case-lists, plugin-system, zgw-integration

### From Browser (15 screenshots)
dashboard, tasks-list, analyse-heatmap, admin-sidebar, admin-dossiers, admin-plugins, plugin-selector-zgw, access-control-roles, admin-processes, bpmn-editor, admin-forms, decision-tables, swagger-dev, logging, keycloak-admin

### Business Logic Diagrams (4)
case-lifecycle, process-execution, plugin-action-flow, authorization-flow

## Strategic Assessment

**HIGH competitive threat.** Valtimo is the most serious competitor to Procest:

1. **Same target market** — Dutch government zaakgericht werken
2. **Deeper process capabilities** — full BPMN 2.0 vs n8n automation
3. **Deeper ZGW integration** — 10+ dedicated API plugins
4. **Growing adoption** — Dimpact backing, multiple municipalities
5. **Mature codebase** — 4,100+ backend files, 50+ modules

**Valtimo's weaknesses Procest can exploit:**
1. **Infrastructure complexity** — 6+ containers vs Nextcloud app install
2. **Camunda 7 EOL** — Operaton fork carries risk as Camunda 7 reached EOL October 2025
3. **No NL Design System** — uses Carbon Design, not the government standard
4. **JVM complexity** — requires Java/Kotlin expertise vs PHP/Vue.js
5. **No AI/MCP** — no LLM integration
6. **No Nextcloud ecosystem** — no native file management, user management, or collaboration

**Recommendations for Procest:**
1. **Strengthen n8n automation** to compete with BPMN on workflow capabilities
2. **Add DMN-like decision tables** — even a simple rules engine would close the gap
3. **Implement configurable case tabs** — high UX value, medium complexity
4. **Add case tags with colors** — quick win for visual organization
5. **Position on deployment simplicity** — "Valtimo needs 6 containers and a Keycloak server; Procest needs Nextcloud"
6. **Position on NL Design compliance** — Valtimo uses Carbon, Procest uses the government standard
