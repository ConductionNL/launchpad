# Valtimo Modules Overview

Source: https://docs.valtimo.nl/fundamentals/architectural-overview/modules

## Core Modules

### Audit
Tracks user actions post-facto for authorization verification and data modification documentation. Records activities automatically for task completion, file uploads, etc.

### Authorization
Implements Policy-Based Access Control (PBAC). Restricts access to features and data via policies. Resource types from other modules leverage this layer.

### Connector (deprecated)
Framework for creating/configuring connections to external systems. Being phased out in favor of the Plugin system.

### Contract
Integrative "glue" between modules. Contains interfaces and events, endpoint sanitization, database-agnostic query helpers.

### Core
Main module building on Operaton process engine. Provides endpoints and functions for task and process data, choice fields, and security controls.

### Dashboards
Statistical insights via configurable widgets. Supports bar charts, numbers, meters, gauges, and custom display types. Multiple dashboards as tabs, JSON-based auto-deployment.

### Documents
Primary data storage as JSON Schema-defined entities. Documents contain case information and interact with processes through user tasks.

### Document Generation
Interfaces for generating files (PDFs, etc.) using specified data.

### Exporter/Importer
Data export/import for case definitions and configurations.

### Flowmailer
SaaS email service integration for transactional communications.

### Form
Form.IO-based visual task completion. Forms composed via Form.IO builder, managed via endpoints or config files.

### Form Flow
Sequential multi-step form wizards with forward/backward navigation, conditional routing via SpEL expressions, breadcrumb navigation. Does not require task completion between steps.

### Keycloak IAM
OAuth authentication and user management. Supports external IdP integration (Microsoft Entra, LDAP).

### Mail
Email sending with filtering capabilities across implementations.

### Milestones
Process progression tracking through framework-based checkpoints.

### Notes
Case-related collaboration through attachable messages.

### Outbox / Outbox RabbitMQ
Transactional outbox pattern with RabbitMQ support for reliable event delivery.

### Plugins
Configurable platform extensions. Connect to external services without coding. See plugin-system spec for details.

### Process Document
Links documents to BPMN processes, enabling data access within workflows.

### Resource / Temporary Resource Storage
File upload/download management with local temporary storage.

### Value Resolvers
Retrieve/store data from multiple sources:
- `doc:` — JSON document paths
- `pv:` — Process variables
- `case:` — Case-level properties (assignee, dates)
- `zaak:` — Zaken API data
- `zaakstatus:` — Zaak status
- `zaakresultaat:` — Zaak results
- `zaakobject:` — Linked objects

### Web
OpenAPI integration, CORS configuration, error message sanitization.

## ZGW Modules

### Zaken API
Case management per Dutch government ZGW standard.

### Documenten API
Document storage, retrieval, and metadata management per ZGW standard.

### Besluiten API
Decision record management.

### Catalogi API
Case type and document type definitions.

### Notificaties API
Publish-subscribe messaging between systems.

### Objecten API
Generic object storage with CRUD operations.

### Objecttypen API
Object type definitions and validation.

### Klanten API
Customer data management.

### Contactmomenten API
Contact moment registration including email communications.

### Haalcentraal BRP
Civilian data retrieval from national registry.

### SmartDocuments
External document generation integration.

### Verzoek
Form submission handling and processing.

### Portaaltaak
External portal task management (NL Portal integration).

### OpenZaak
Authentication and connector for OpenZaak installations.
