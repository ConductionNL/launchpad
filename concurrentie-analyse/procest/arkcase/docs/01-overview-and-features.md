# ArkCase Overview and Features

**Source:** arkcase.com, GitHub (ArkCase/ArkCase)
**License:** LGPL-3.0
**Company:** Armedia LLC (parent company)
**Repository:** https://github.com/ArkCase/ArkCase (62 stars, 40 forks, 22,764 commits)

## What Is ArkCase?

ArkCase is an open-source case management and IT modernization platform developed by Armedia. It emerged from a framework built through numerous case management and IT modernization initiatives for US government agencies and enterprise customers. ArkCase is designed to be a configurable, low-code platform supporting the full lifecycle of legal matters -- from intake and assignment through investigation, documentation, adjudication, and closure.

## Target Audience

- **US Federal Government agencies** (DOJ, DHS, EEOC, OPM, etc.)
- **State and local government** (Washington State Legislature, DC OfCIO, etc.)
- **Law enforcement agencies** (Victoria Police Department)
- **Legal departments and law firms** (Top 100 US Law Firms)
- **Healthcare organizations** (International Health Agency)
- Any organization requiring case management, complaint handling, or FOIA processing

## Solution Verticals

ArkCase offers specialized modules for:

1. **Freedom of Information Act (FOIA)** -- flagship product, see separate FOIA doc
2. **Legal Case Management** -- 360-degree case view, AI-infused insights
3. **Matter Management** -- comprehensive legal matter oversight
4. **Complaint Management** -- multi-channel incident/complaint tracking
5. **Correspondence Management** -- template-based document generation
6. **Docket Management** -- court docket tracking
7. **e-Filing** -- electronic filing for courts/agencies
8. **Release of Information Management** -- legal compliance out-of-the-box

## Core Platform Capabilities

### Case Management
- Case files with start/end dates, identifying numbers, assigned users/groups
- Tasks, folders, and documents within each case
- Complaints (lightweight pre-case objects that can be converted to full cases)
- Document repositories (plain folders without compliance overhead)
- Milestones and timelines
- Case references and associations between objects

### Document and Records Management
- Support for 100+ document types organized in folders
- Online editing via WebDAV
- Web-based document viewer with annotation and redaction (Snowbound / PDFTron)
- Document tagging and categorization
- Version control for all documents
- CMIS 1.1 compliant storage (Alfresco Content Services)
- Integrated Records Management (Alfresco Governance Services, DoD 5015.2 compliant)
- Email and calendar integration (Microsoft O365 / Exchange)
- PST email archive extraction

### Workflow Automation
- Configurable queue-based workflows
- Dynamic workflows for requests and appeals
- Automated and manual tasks
- Business rules engine (Drools)
- Business process engine (Activiti)
- Automated case intake, routing, and notifications
- Document review and approval workflows

### Search, Reporting, and Analytics
- Full-text and metadata search (Apache Solr)
- Faceted search with access control filtering
- Canned and ad-hoc reporting (Pentaho)
- Scheduled tabular and graphical reports
- Predictive analytics and dashboards
- Heatmaps and data visualization
- NIEM-XML output for DOJ reports

### Security and Compliance
- **FedRAMP Authorized** (highest US government cloud security standard)
- **StateRAMP** authorized
- HIPAA and HITECH compliant
- Section 508 accessibility compliance
- VPAT (Voluntary Product Accessibility Template) available
- Encryption at rest and in transit
- Role-Based Access Control (RBAC)
- Full audit trail/log
- Data access control embedded in Solr search results

### AI Capabilities (ArkCase Illume)
- AI-driven workflow automation (intake, routing, notifications)
- PII auto-redaction across formats (documents, audio, video)
- Enhanced search with AI-created document summaries
- Smart content extraction from unstructured files
- Auto-tagging for indexing and retrieval
- Predictive case prioritization
- Document intelligence (deduplication, version conflict elimination)
- OCR for scanned content
- Natural Language Processing (NLP)
- Audio/video transcription (Amazon Transcribe integration)

### Collaboration
- Real-time collaboration tools
- Notes and references
- @mentions in notes
- Calendar integration
- Email integration
- Automated notification system

### Financial Management
- Time tracking (timesheets)
- Cost tracking (costsheets)
- Billing integration
- Automated payment processing
- Budgeting tools for legal expenses

## Community Edition vs Enterprise Edition

| Feature | Community (CE) | Enterprise (EE) |
|---|---|---|
| Document Viewer | View only | View, Annotate, Redact |
| Online Editing | Basic | Full co-editing |
| Reporting | Pentaho CE | Full Pentaho Business Analytics |
| Platform Support | LAMP + Chrome | Major OS + browsers |
| AI Integration | None | Transcription, NLP, auto-redaction |
| Forms | Angular forms | Angular + ArkCase Forms Designer |
| High Availability | None | Clustering + HA + scalability |
| Support | Self/online only | Full support |

## Customer Success Stories

- Washington State Legislature
- DC Office of the CIO
- DC Public Defender Service
- Sharecare
- Federal Conference
- International Health Agency
- US Office of Personnel Management (OPM)
- DOJ INTERPOL
- Equal Employment Opportunity Commission (EEOC)
- Victoria Police Department
- BC Government Service & Employees' Union
- University of Washington
- Privacy Rights Clearinghouse (PRC)

## Claimed Metrics

- 40-80% efficiency gains in case processing
- "5-star reviews" (self-reported)
- Significant reduction in FOIA processing time
- Cost savings in eDiscovery
- Reduction in FOIA processing errors
