# ArkCase Architecture

**Source:** arkcase.com/developer-support/architecture/, GitHub README

## Architecture Overview

ArkCase follows a distributed, service-oriented architecture. The platform consists of multiple separate processes that together form a running system. The architecture is modular -- components can be activated or deactivated to tailor the platform.

## Core Components (Separate Processes)

### 1. ArkCase Web Application (Java WAR)
- Provides the user interface (AngularJS) and REST APIs
- Hosted on **Apache Tomcat 9** with **Java 8**
- Contains embedded Activiti (workflow engine) and Drools (business rules)
- All REST services documented at `/swagger-ui.html`
- Single WAR file deployment

### 2. ArkCase External Portal
Two separate processes for public-facing access:
- **UI Portal**: Standalone single-page web application (static JS/HTML), hosted on any web server
- **REST Portal**: Java WAR providing REST API and authentication for the UI portal; calls back to the main ArkCase webapp

### 3. Search Engine — Apache Solr
- Version 7.2.1 supported/tested
- Full-text and fielded search
- Access controls embedded in Solr metadata
- Populated via event handlers (near real-time) + configurable batch updates
- Can trigger complete re-index of all data

### 4. Messaging System — Apache ActiveMQ
- Version 5.15+
- AMQP interface for purpose-built integrations
- Used for disconnected, uncoupled integration between systems

### 5. File Storage — Alfresco Content Services
- Version 5.1+ (CMIS 1.1 compliant)
- Stores all content files (Word, Excel, images, movies, audio, etc.)
- Versioning, copying, moving, deleting
- Online editing via WebDAV
- Optional: Alfresco Governance Services for records management (DoD 5015.2 compliant)

### 6. Relational Database
Stores all structured metadata. Supported databases:
- **MySQL**
- **MariaDB**
- **PostgreSQL**
- **Oracle RDBMS**
- **Microsoft SQL Server**

### 7. Reporting Engine — Pentaho
- Version 8.0+ supported
- Community Edition or Enterprise Edition
- Canned and ad-hoc reports
- Scheduled report generation

### 8. Forms Server — Frevvo
- Dynamic form creation and management
- Enterprise Edition also has ArkCase Forms Designer

### 9. Document Viewer — Snowbound / PDFTron
- Web-based document viewing
- Annotation and redaction capabilities
- Enterprise only: full redaction support

### 10. Configuration Server — Spring Cloud Config
- Separate process (since v3.3.1)
- Runs on port 9999 by default
- Houses all ArkCase configuration

## Data Storage Model (4 Repositories)

### Relational Database
- Primary store for all structured/fielded metadata
- User queries for detailed object info go to the database
- Lists, trees, tabular views populated from Solr

### Content File Store (CMIS)
- Primary store for all content files
- WebDAV for online editing
- Annotation and redaction via document viewer

### Solr Index
- Indexes all metadata and content files
- Powers all list/tree views in the UI
- Access controls embedded in metadata (facet counts, search results)
- Dual update mechanism: event-driven (near real-time) + batch (configurable interval)

### Configuration Folder
- File system folder with system configuration:
  - UI labels
  - Connection info (database, email, content store, Solr)
  - RBAC configuration
  - Business rules
  - Business process definitions

## Technology Stack

| Layer | Technology |
|---|---|
| Primary Language | Java (66.4%), JavaScript (27%), HTML (6.2%) |
| Java Version | Java 8 (AdoptOpenJDK) |
| Build Tool | Apache Maven 3.5+ |
| Application Server | Apache Tomcat 9 |
| Frontend Framework | AngularJS (1.x) |
| Backend Framework | Spring (Spring Cloud Config) |
| Workflow Engine | Activiti (embedded) |
| Rules Engine | Drools (embedded) |
| Search | Apache Solr 7.2.1 |
| Messaging | Apache ActiveMQ 5.15+ |
| Content Management | Alfresco Content Services 5.1+ (CMIS 1.1) |
| Records Management | Alfresco Governance Services |
| Database | MySQL / MariaDB / PostgreSQL / Oracle / SQL Server |
| Reporting | Pentaho 8.0+ |
| Forms | Frevvo / Angular Forms |
| Document Viewer | Snowbound / PDFTron |
| Integration | Spring Integration, Apache Camel |
| CI/CD | GitLab CI (`.gitlab-ci.yml`) |
| Quality | Checkstyle, JaCoCo (test coverage) |
| License | LGPL-3.0 |

## Authentication Architecture

- **LDAP** (direct simple LDAP binds)
- **Active Directory** (via LDAP)
- **SAML** (via ADFS)
- **Kerberos** (via ADFS)
- **Active Directory Federation Services (ADFS)**

Most production deployments use Active Directory or ADFS.

## Security Architecture

- Database, Solr, and ECM repository should be isolated from user-accessible network
- Access controls enforced at the ArkCase application layer (UI + service)
- Users should not directly access database, Solr, or ECM
- External Portal UI is publicly accessible; REST portal is intermediary
- ArkCase webapp only accessible from REST portal + internal users

## Deployment Considerations

- External portal components must be publicly accessible
- All other components should be restricted to ArkCase users only
- System administrators need direct access to components
- REST portal makes API calls to ArkCase webapp
- Most customers deploy to **CentOS 7** hosts
- Kubernetes clustering support added in v25.09.00

## GitHub Repository Structure

```
ArkCase/
  acm-core-api/          -- Core API interfaces
  acm-services/          -- 50+ service modules (see service list)
  acm-plugins/
    acm-default-plugins/ -- 20+ default plugins
    acm-extra-plugins/   -- Additional plugins
    acm-plugins-samples/ -- Sample plugin templates
  acm-standard-applications/ -- WAR packaging
  acm-tool-integrations/ -- Third-party integrations
  acm-user-interface/    -- AngularJS frontend
  acm-web/               -- Web configuration
  acm-forms/             -- Form definitions
  acm-jmeter/            -- Performance tests
  arkcase-lib/           -- Shared libraries
```

### Service Modules (acm-services/)
alfresco-ldap-syncer, arkcase-identifier, audit, authentication-token, billing, calendar, calendar-integration-exchange, comprehend-medical, compress-folder, config, configuration, convert-file, convert-folder, correspondence, costsheet, data, data-access-control, data-update, ecm, electronic-signature, email, email-smtp, exemption, form-configuration, frevvo-forms, functional-access-control, history, holiday, labels, ldap-syncer, login, media-engine, message-broker, milestone, ms-outlook-integration, note, notification, object-change-status, object-history, object-lock, object-title, ocr, participants, pipeline, plugin-manager, portal-gateway, protect-url, search, sequence-manager, state-of-arkcase, subscription, suggestion, tag, template-configuration, timesheet, transcribe, users, webdav, zylab-integration

### Default Plugins (acm-plugins/acm-default-plugins/)
addressable, admin, analytics-audit, analytics, audit, billing, business-process, case-file, category, complaint, consultation, dashboard, document-repository, object-association, object-lock, person, phone-home, profile, report, state-of-arkcase, task
