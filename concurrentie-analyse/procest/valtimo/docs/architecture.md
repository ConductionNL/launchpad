# Valtimo Architecture Documentation

Source: https://docs.valtimo.nl/fundamentals/architectural-overview/modules

## Overview

Valtimo is a low-code platform for Business Process Automation and Case Management built on top of Operaton (formerly Camunda 7). The GZAC edition extends the core with ZGW (Zaakgericht Werken) API integrations for Dutch government use.

## Technology Stack

- **Backend:** Kotlin (72.1%) and Java (27.9%)
- **Build System:** Gradle
- **Process Engine:** Operaton (fork of Camunda 7, migrated from Camunda in v12.0)
- **Frontend:** Angular
- **Forms:** Form.IO
- **Authentication:** Keycloak (OAuth2/OIDC)
- **Database:** PostgreSQL (primary), MySQL supported
- **Messaging:** RabbitMQ (for outbox pattern)
- **License:** EUPL (European Union Public Licence) — strong copyleft

## Core Architecture

Valtimo follows a modular architecture with 45+ focused modules. The platform is organized into:

### Core Modules
- **Core** — Main module building on Operaton process engine
- **Contract** — Interfaces and events ("glue" between modules)
- **Documents** — Primary data storage as JSON Schema-defined entities
- **Process Document** — Links documents to BPMN processes
- **Authorization** — Policy-Based Access Control (PBAC)
- **Keycloak IAM** — OAuth authentication and user management
- **Plugins** — Configurable platform extensions
- **Web** — OpenAPI, CORS, error handling

### Feature Modules
- **Audit** — User action tracking
- **Dashboards** — Statistical insights via configurable widgets
- **Form** — Form.IO-based task completion interfaces
- **Form Flow** — Multi-step form wizards with conditional routing
- **Milestones** — Process progression checkpoints
- **Notes** — Case-related collaboration messages
- **Localization** — Multi-language support
- **Exporter/Importer** — Data export/import for case definitions
- **Value Resolvers** — Data retrieval from multiple sources (doc:, pv:, case:)

### Communication Modules
- **Mail** — Email sending with filtering
- **Flowmailer** — SaaS email integration
- **Outbox/RabbitMQ** — Transactional outbox pattern

### Storage Modules
- **Resource** — File upload/download management
- **Temporary Resource Storage** — Local temporary storage
- **Document Generation** — PDF generation interfaces

## GZAC (ZGW) Modules

The GZAC edition adds Dutch government API integrations:

- **Zaken API** — Case management per ZGW standard
- **Documenten API** — Document storage and metadata
- **Catalogi API** — Case and document type definitions
- **Besluiten API** — Decision records
- **Notificaties API** — Publish-subscribe messaging
- **Objecten API** — Generic object storage
- **Objecttypen API** — Object type definitions
- **Klanten API** — Customer data management
- **Contactmomenten API** — Contact registration
- **Haalcentraal BRP** — Civilian data retrieval
- **SmartDocuments** — External document generation
- **Verzoek** — Form submission handling
- **Portaaltaak** — External portal tasks
- **OpenZaak** — Authentication connector

## Deployment

- Docker Compose for development (supporting services)
- Backend runs as Spring Boot application
- Frontend runs as Angular application
- Kubernetes-ready for production
- Available as SaaS (managed by Ritense)

## Repository Structure

The codebase was consolidated into a single repository in October 2025:
- **Current:** https://github.com/valtimo-platform/valtimo
- **Archived:** https://github.com/valtimo-platform/valtimo-backend-libraries (read-only since Oct 2025)
- **Archived:** https://github.com/valtimo-platform/valtimo-frontend-libraries (read-only)
