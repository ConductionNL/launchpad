# ArkCase API and Integration Documentation

**Source:** arkcase.com/external-interfaces/, arkcase.com/technical-stack-and-constraints/

## REST API

### Access
- Available at `/swagger-ui.html` on any ArkCase installation
- Example: `https://your-host/arkcase/swagger-ui.html`
- Provides the same data visible through the user interface
- Any system can call the REST API for integration

### Authentication
- Session-based authentication via LDAP/AD/ADFS
- Default admin: `arkcase-admin@arkcase.org` / `@rKc@3e`
- REST API requires authentication (no unauthenticated endpoints except external portal)

### API Details
No public OpenAPI/Swagger spec file is published in the repository. The API is self-documenting via the Swagger UI at runtime. Based on the codebase structure, the API covers:

- Case files (CRUD, status changes, assignments)
- Complaints (CRUD, conversion to case files)
- Tasks (creation, assignment, completion)
- Documents (upload, download, versioning, metadata)
- People/organizations (CRUD, associations)
- Notes and references
- Tags
- Billing and timesheets
- Reports
- Search (Solr queries)
- Notifications
- Calendar events
- Audit logs
- Admin configuration
- FOIA-specific endpoints (requests, appeals, exemption codes, queues)

## Incoming Interfaces

### 1. User Interface (AngularJS)
- Full web application requiring user login
- Session timeout shows login page
- AngularJS 1.x single-page application

### 2. REST API
- All data available through REST services
- Documented via Swagger UI
- Used by the UI itself and any external systems

### 3. AMQP Messaging (ActiveMQ)
- Advanced Message Queuing Protocol interface
- Purpose-built integration based on Interface Control Documents (ICD)
- ArkCase can listen for messages and take defined actions
- Optional reply messages
- Disconnected, uncoupled integration pattern

**Example use case:** A CRM system posts messages with new/updated/removed customers. ArkCase adds, updates, or removes corresponding records.

### 4. Electronic Mail
- Every ArkCase instance has a dedicated email address
- Incoming emails auto-attach to case files/complaints/tasks based on subject
  - Example: subject containing "Case 20180831_115" attaches to that case
- Optionally creates new case files for unmatched emails
- Extensible based on any email header (From, To, Subject, Content-Type, Content-Length, attachments)
- In principle, email could provide all REST service functionality

## Outgoing Interfaces

### 1. AMQP Messaging
- ArkCase can put messages onto queues for consumption by other systems
- Disconnected, uncoupled pattern

### 2. Electronic Mail
- Users can email documents from the UI
- Automated notifications based on configurable rules
- Example: new case file triggers email notification to assigned user

### 3. Spring Integration / Apache Camel
- Generic interfaces to Spring Integration and Apache Camel modules
- In principle supports integration with any system supported by these frameworks
- Examples: SalesForce, Box, AWS services

## Third-Party Integrations

### Content Management
- **Alfresco Content Services** (5.1+) -- primary ECM via CMIS 1.1
- **Alfresco Governance Services** -- records management (DoD 5015.2)
- **Box** -- cloud storage integration (via Spring Integration/Camel)

### eDiscovery
- **Relativity** -- eDiscovery integration (connector improved in v25.09.00)
- **ZyLAB** -- eDiscovery integration (dedicated service module)

### Microsoft Integration
- **Microsoft O365 / Exchange** -- email and calendar integration
- **Microsoft Office Online** -- document editing
- **Outlook** -- dedicated integration service

### AI/ML Services
- **Amazon Transcribe** -- audio/video transcription
- **Amazon Comprehend Medical** -- medical NLP (dedicated service module)
- **Custom AI models** -- PII redaction, content extraction

### Authentication
- **LDAP** -- direct binds
- **Active Directory** -- via LDAP
- **ADFS** -- via SAML or Kerberos

### Reporting
- **Pentaho** -- reporting and business analytics (CE or EE)

### Search
- **Apache Solr** -- full-text search and indexing

### Other
- **Frevvo** -- forms server
- **Snowbound / PDFTron** -- document viewing, annotation, redaction
- **AWS S3** -- file storage (referenced in release notes)

## Integration Patterns

### CMIS (Content Management Interoperability Services)
- Standard protocol for ECM interoperability
- Version 1.1 required
- Used for all file operations (store, retrieve, version, copy, move, delete)
- Any CMIS 1.1 compliant system could theoretically replace Alfresco

### AMQP (Advanced Message Queuing Protocol)
- Standard messaging protocol
- ActiveMQ as the broker
- Used for both incoming and outgoing integrations
- Supports disconnected/uncoupled patterns

### REST
- Standard HTTP REST APIs
- Swagger documentation
- Used by UI and external systems

### WebDAV
- Standard protocol for online document editing
- Used by the content file store

### SAML / Kerberos
- Standard authentication protocols
- Used for ADFS integration

## Data Migration

- REST API endpoints can be used for migration (compliance-guaranteed but potentially slow)
- For large datasets: Armedia's **Caliente** migration tool
- Specialized approaches available for very large incoming data sets
