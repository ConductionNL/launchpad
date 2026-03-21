# ZAC Deployment Model

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/deploymentModel.md

## Deployment Architecture

Kubernetes deployment using Helm Chart.

### Pods

| Pod | Description |
|-----|-------------|
| ZAC Application | WildFly Docker container (backend + frontend) |
| Apache Solr | Search engine |
| OPA | Open Policy Agent |
| Office Converter | Document conversion (LibreOffice-based) |

### Cron Jobs

| Job | Description | Frequency |
|-----|-------------|-----------|
| Send signaleringen | Sends due-date notifications to employees | Daily |
| Delete old signaleringen | Removes signaleringen older than configured days | Daily |

## Dependencies (Not in Helm Chart)

- PostgreSQL database (must be pre-created)
- Keycloak
- Open Zaak
- Open Notificaties
- Open Klant
- Various external services (BAG, BRP, KVK, etc.)

## Key Limitations

### No Horizontal Scaling

ZAC currently runs as a **single instance only**. To enable horizontal scaling, these issues must be solved:
1. **Open Notificaties** — ensure notification events are handled only once
2. **Session management** — requires sticky sessions at load balancer or shared session store (e.g., Redis)

### Cloud-Agnostic

Can deploy on any Kubernetes cluster (cloud or on-premises).

## Docker Images (v4.4.38)

Core:
- postgres: 17.9
- keycloak: 26.5.5
- solr: 9.10.1-slim
- openpolicyagent/opa: 1.14.1-static
- kontextwork-converter: 1.8.2
- redis: 8.4.0
- rabbitmq: 4.2.4-alpine

Common Ground:
- open-zaak: 1.26.0
- objects-api: 3.3.1
- open-klant: 2.14.0
- open-notificaties: 1.13.0
- open-archiefbeheer: 1.1.1
- pabc: 1.0.0

Observability:
- otel/opentelemetry-collector-contrib: 0.147.0
- grafana/tempo: 2.10.2
- prom/prometheus: v3.10.0
- grafana/grafana: 12.4.1
