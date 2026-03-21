---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Deployment Architecture — Dimpact ZAC

## Purpose

Deployment model, infrastructure requirements, scalability characteristics, and operational concerns for running ZAC in production.

## Architecture Overview

- Kubernetes-native deployment using **Helm Chart**
- Single WildFly Docker container serving backend + frontend
- Multiple sidecar services: Solr, OPA, OfficeConverter
- Cloud-agnostic (any Kubernetes cluster)
- Currently deployed on **Azure AKS** for Dimpact
- Fully automated **CI/CD via GitHub Actions**

## Data Model

### Runtime Pods

| Pod | Image | Purpose |
|-----|-------|---------|
| ZAC Application | ghcr.io/infonl/zaakafhandelcomponent | WildFly with Kotlin backend + Angular frontend |
| Solr | solr:9.10.1-slim | Full-text search indexing |
| OPA | openpolicyagent/opa:1.14.1-static | Access control policies |
| OfficeConverter | kontextwork-converter:1.8.2 | LibreOffice-based document conversion |

### Cron Jobs (Helm-managed)

| Job | Purpose | Schedule |
|-----|---------|----------|
| Send signaleringen | Deadline notification emails | Daily |
| Delete old signaleringen | Cleanup expired notifications | Daily |

### External Dependencies (not in Helm)

- PostgreSQL 17.9 (+ PostGIS 17-3.4)
- Keycloak 26.5.5
- Redis 8.4.0
- RabbitMQ 4.2.4
- Open Zaak 1.26.0
- Open Notificaties 1.13.0
- Open Klant 2.14.0
- Objects API 3.3.1
- Open Archiefbeheer 1.1.1
- PABC 1.0.0

### Observability Stack

- OpenTelemetry Collector 0.147.0
- Grafana 12.4.1
- Tempo 2.10.2
- Prometheus v3.10.0

## Business Logic

### Deployment Flow
1. PostgreSQL database must be pre-created with `zaakafhandelcomponent` and `flowable` schemas
2. Helm chart deploys all ZAC pods + cron jobs
3. ZAC auto-creates database tables on first startup (Flyway migrations)
4. ZAC deploys OPA policies to OPA server on startup
5. ZAC checks for Solr `zac` core availability (fails to start if missing)
6. Internal endpoints secured with API key for cron job access

### Scalability Constraints
**ZAC cannot scale horizontally** (single instance only). Blockers:
1. Open Notificaties events must be handled exactly once
2. HTTP sessions stored in-memory (no shared session store)

To enable horizontal scaling, would need:
- Notification deduplication mechanism
- Sticky sessions at load balancer OR shared session store (Redis)

## Requirements (as observed)

1. Kubernetes cluster required for production deployment
2. PostgreSQL must be provisioned separately
3. All Common Ground components must be deployed and configured independently
4. API key must be configured for internal endpoints (cron jobs)
5. Solr core must be available before ZAC can start
6. Database schemas auto-created but databases must pre-exist
7. Single-instance limitation for ZAC itself
8. Helm values configure connections to all external dependencies

## Comparison Notes

**vs. Procest:**
- ZAC requires a full Kubernetes cluster with ~15+ containers; Procest runs within Nextcloud
- ZAC's infrastructure complexity is significant (Solr, OPA, Flowable, OfficeConverter all as sidecars)
- No horizontal scaling is a notable limitation for large municipalities
- Observability stack (OTEL/Grafana/Tempo/Prometheus) is enterprise-grade
- Procest's Nextcloud-native approach dramatically reduces deployment complexity
- ZAC's Helm chart deployment is standard but requires Kubernetes expertise
- The number of external dependencies (Open Zaak, Open Notificaties, Keycloak, etc.) creates integration complexity
- ZAC v4.4.38 actively maintained with multiple releases per day (rapid iteration)
