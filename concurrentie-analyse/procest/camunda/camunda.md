# Camunda 8 — Competitor Analysis

## Overview

- **Website:** https://camunda.com/
- **Open Source:** Partially (Zeebe)
- **Self-Hosted:** Yes
- **Summary:** Universal process orchestrator supporting BPMN and DMN

## Codebase

- https://github.com/camunda/camunda (Camunda 8, source-available)
- https://github.com/camunda/camunda-bpm-platform (Camunda 7, EOL)

## Business Model

SaaS-first with self-managed option. Camunda 8 follows a commercial open-source model — the Zeebe workflow engine is source-available, but the full platform requires a subscription. Revenue comes from SaaS subscriptions (pay-as-you-go and annual), self-managed enterprise licenses, professional services, and training. Over 700 companies use Camunda, including Atlassian, ING, and Vodafone.

## Target Market

Enterprise organizations across all industries needing process orchestration at scale. Strong in financial services, insurance, telecommunications, and government. Targets both developers (BPMN modeling) and operations teams (process monitoring). International focus with significant European presence.

## Pricing

- **Free tier:** Limited free plan for evaluation
- **Professional:** Starting at $49/month with pay-as-you-go pricing; includes 1 production cluster, 10 users, 50 process instances, 50 decision instances
- **Enterprise:** Custom pricing for large-scale deployments with dedicated support, SLA, and unlimited usage
- Additional usage billed per process instance, decision instance, and user

## Key Features

- Cloud-native process orchestration platform (Kubernetes-based)
- BPMN 2.0 modeling and execution via Zeebe engine
- DMN decision tables for business rules
- Operate dashboard for process monitoring and incident management
- Tasklist for human task management
- Optimize for process analytics and reporting
- Pre-built connectors for integration (REST, messaging, databases, SaaS apps)
- Agent orchestration for AI/LLM workflows
- RPA integration for robotic process automation
- Document processing capabilities
- Horizontal scaling designed for high-throughput enterprise workloads

## Feature Comparison with Procest

| Feature | Camunda 8 | Procest |
|---------|-------|---------|
| Case lifecycle management | Partial (process-centric) | Yes |
| CMMN 1.1 support | No (dropped in v8) | Yes |
| ZGW API compatible | No | Yes |
| Deadline tracking | Yes (timers, SLAs) | Yes |
| Task assignment | Yes | Yes |
| Document checklists | No | Yes |
| Decisions (besluiten) | Partial (DMN only) | Yes |
| Sub-cases | Partial (sub-processes) | Yes |
| Confidentiality levels | No | Yes |
| Audit trail | Yes | Yes |
| Nextcloud integration | No | Native |
| RBAC | Yes | Yes |
| WCAG AA accessible | Partial | Yes |

## Strengths

- Industry-leading process orchestration with massive scale (millions of process instances per day)
- Rich ecosystem of pre-built connectors, marketplace, and a large developer community
- Cloud-native architecture with SaaS offering reducing operational overhead

## Weaknesses

- Dropped CMMN support in Camunda 8 — only BPMN is supported, limiting dynamic case management flexibility
- No Dutch government domain knowledge — no ZGW APIs, no zaakgericht werken, no Common Ground awareness
- Expensive at enterprise scale — pricing quickly escalates with usage-based billing model

## Notes

Camunda is the market leader in process orchestration but is not purpose-built for Dutch government case management. The decision to drop CMMN in Camunda 8 is significant — CMMN is essential for dynamic case management in zaakgericht werken. Valtimo (GZAC) was built on Camunda 7 and now faces a migration challenge. Procest's native CMMN support and ZGW compatibility are strong differentiators against Camunda in the Dutch government market.
