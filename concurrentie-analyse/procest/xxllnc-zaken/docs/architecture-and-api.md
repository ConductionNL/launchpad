# xxllnc Zaken - Architecture and API Documentation

**Sources:**
- https://xxllnc.nl/blog/zgw-api
- https://xxllnc.nl/applicaties/koppelen
- https://gitlab.com/xxllnc/zaakgericht/zaken/start

**Last fetched:** 2026-03-14

## Architecture Overview

xxllnc Zaken is built on an open architecture following Common Ground principles, allowing other vendors to integrate with their systems. The platform uses a microservices architecture with Domain Driven Design.

### Technology Stack

| Layer | Technology | Details |
|-------|-----------|---------|
| **Frontend** | TypeScript/JavaScript | Monorepo: `zsnl-frontend-mono` |
| **Backend** | Python (Pyramid) | Custom "Minty" DDD framework |
| **Database** | PostgreSQL | PLpgSQL stored procedures |
| **Infrastructure** | Docker Compose | Containerized microservices |
| **Legacy** | Perl | Significant legacy codebase (25.5%) |

### Minty Framework

The custom Python framework consists of three core libraries:

1. **minty** — Domain Driven Design core functionality
   - Repository: `gitlab.com/xxllnc/zaakgericht/zaken/libraries/minty`
   - Provides DDD building blocks (entities, value objects, aggregates, repositories)

2. **minty-pyramid** — API server layer
   - Repository: `gitlab.com/xxllnc/zaakgericht/zaken/libraries/minty-pyramid`
   - Builds on Python Pyramid web framework
   - Creates REST API servers for domain services

3. **minty-infra-sqlalchemy** — Database infrastructure
   - Repository: `gitlab.com/xxllnc/zaakgericht/zaken/libraries/minty-infra-sqlalchemy`
   - SQLAlchemy ORM integration for Minty domains

### Repository Structure

The main repository at `gitlab.com/xxllnc/zaakgericht/zaken/start` is public with:
- 54,422 commits (as of 2026-03-14)
- 345 branches
- 2,550 tags
- 5 environments
- README and CONTRIBUTING files

Key sub-repositories:
- `zsnl-frontend-mono` — Frontend monorepo
- `zsnl-consumer-cm` — Consumer/case management service
- `libraries/minty` — Core DDD library
- `libraries/minty-pyramid` — API server framework
- `libraries/minty-infra-sqlalchemy` — Database layer

## API Standards

### ZGW-API (Zaakgericht Werken API)

Developed in collaboration with **Conduction** (summer 2022):
- Unlocks xxllnc Zaken as a ZGW-compliant case system
- Enables integration with any ZGW-API compatible application
- Based on VNG Realisatie standards
- JSON-based REST APIs

**Active integrations via ZGW-API:**
- Rx.Mission (VTH application) — completed
- MijnZaken — planned
- KISS (Klant Interactie Service Systeem) — planned

### xxllnc Koppelen (Integration Platform)

A separate application combining three products:
- **Connect** — traditional integration middleware
- **Koppel.app** — modern API-based integrations
- **API-Gateway** — API management and routing

**Supported standards:**
| Standard | Protocol | Use Case |
|----------|----------|----------|
| StUF-BG 2.04/3.10 | XML | Basic registration queries |
| StUF-ZKN 3.10 | XML | Case data exchange |
| ZGW-API | JSON | Modern case data exchange |
| HaalCentraal | JSON | Basic registration queries (modern) |
| DSO STAM/SWF | Various | Omgevingswet compliance |

**Key integration features:**
- Open connectors (reusable)
- Flexible data mapping and transformation
- Monitoring and alerting for data flows
- Field additions and format changes
- Centralized integration management

**Partner integrations:**
- Xential — document creation
- ValidSign — digital signing
- Datamask — document anonymization
- Zynyo — digital signing
- MijnOverheid — government portal
- Office365 — document editing

## Common Ground Strategy

xxllnc advises municipalities to adopt Common Ground incrementally:
- Application-by-application transformation
- New Common Ground apps work alongside existing applications
- Controlled transition preserving existing data
- xxllnc Zaken adapted as Archive component within Common Ground architecture
- Open architecture enabling third-party vendor integration

## XxllncZGWBundle

There is also an open-source Symfony bundle for xxllnc API and ZGW standard functionality:
- Repository: `github.com/CommonGateway/XxllncZGWBundle`
- Built by the CommonGateway/Conduction team
- Translates between xxllnc native API and ZGW standards
