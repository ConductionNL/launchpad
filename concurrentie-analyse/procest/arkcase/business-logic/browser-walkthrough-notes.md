# ArkCase Browser Walkthrough Notes

## Date: 2026-03-13 (initial), 2026-03-14 (Helm deployment attempt)

## 1. Docker Setup Attempts

### Initial Assessment (2026-03-13)
ArkCase **cannot be run via a simple docker-compose setup**. The deployment options are:

1. **Vagrant VM** (arkcase-ce repo) -- Requires VirtualBox + Vagrant, 16GB+ RAM, 50GB disk. Pre-built box available at `arkcase/arkcase-ce` on Vagrant Cloud.

2. **Kubernetes with Helm** (ark_helm_charts repo) -- Full stack of 12 pods. See docker-setup-notes.md for full details.

3. **Individual Docker images** exist (84 repos in the ArkCase org), but no unified docker-compose.yml is provided.

### Kubernetes/Helm Deployment Attempt (2026-03-14)
We actually attempted a Helm-based deployment using K3d (lightweight K3s in Docker):

- **Tools installed**: k3d v5.8.3, helm v3.17.3
- **Chart used**: `arkcase/app` v0.9.17 (appVersion 25.09.00)
- **Result**: Partially succeeded -- 6 of 12 pods reached Running state before memory exhaustion
- **Root cause**: 24GB system with ~70 other containers left insufficient RAM for ArkCase's 12 pods
- **Key finding**: The core image (2.25GB) has NO WAR files -- the deployer init container must download them from the artifacts container. This makes a minimal docker-compose impossible.
- **Full details**: See `docker-setup-notes.md`

### Why a Minimal Docker-Compose Is Impossible
1. Core image has no application code (WAR files) -- needs deployer + artifacts containers
2. All inter-service communication requires TLS via step-ca ACME
3. Spring Cloud Config Server must be running before app starts
4. Complex init container chain: ACME -> Config -> Deployer -> Permissions -> Dependency check

### Default Credentials
- **URL**: https://arkcase-ce.local/arkcase (Vagrant) or https://localhost:9017/arkcase (K3d)
- **Username**: arkcase-admin@arkcase.org
- **Password**: @rKc@3e

---

## 2. Website & Marketing Page Analysis

### Homepage (screenshot: 01-homepage.png)
- Tagline: "MANAGE MORE CASES WITH LESS STRESS"
- Subtitle: "Low-Code, User-Friendly Open-Source Case Management System"
- FedRAMP Authorized (significant for US government customers)
- Key value props: Host Anywhere, Rock Solid Security, Ease of Integration, Low-Code Workflows Builder
- Client logos carousel showing 15 organizations (government agencies, healthcare, etc.)
- "Schedule A Free Demo" CTA -- no self-service demo available

### Product Page (screenshot: 02-product-page.png)
Two editions offered:

| Feature | Community Edition | Enterprise Edition |
|---------|------------------|-------------------|
| Document Viewer | Viewer Only | Viewer, Annotation, & Redaction |
| Online Editing | Basic | Online + Co-Editing |
| Reporting | Pentaho CE | Full Pentaho Business Analytics |
| Platform | LAMP + Chrome | Major OS + Browser support |
| AI | No AI | AI integrations (transcription) |
| Forms | Angular Forms | Angular + ArkCase Forms Designer |
| Clustering | None | HA/Clustering & Scalability |
| Support | Self/Online only | Full Support |

---

## 3. Solution Pages Analysis

### 3.1 FOIA / Public Records (screenshot: 04-foia-solution.png)
**Target**: Government agencies handling Freedom of Information Act requests

**Key Capabilities** (organized in accordion sections):
- **Automate Case Worker Actions**: MS Office integration, activity monitoring, correspondence/template management, duplicate identification, RPA, OCR, alerts/notifications
- **Improve Requester/Citizen Experience**: (details behind accordion)
- **Manage Processes**: (details behind accordion)
- **Redaction**: (details behind accordion)
- **Operation/Compliance**: (details behind accordion)
- **Security**: (details behind accordion)

**Claimed Results**:
- 50%+ reduction in FOIA processing time
- 50%+ cost savings in eDiscovery
- 90% reduction in processing errors
- 5-star reviews

**Compliance**: ISO 27001, HIPAA, SOC2

**Customer Success**: EEOC, Department of Justice, University of Washington

**Resources**: FOIA Demo video, FOIA Handout PDF, FOIA Guide, AI (Illume) Overview, Security Whitepaper

### 3.2 Complaint Management (screenshot: 05-complaint-management.png)
**Target**: Organizations handling incidents and customer/employee complaints

**Key Capabilities**:
- **Accessible Through Any Device**: Mobile-responsive, 24/7 access
- **Track Complaints from Submission to Resolution**: Multiple users can track and collaborate on cases
- **Keep Future Customers Happy**: Trend identification, reporting, auditing
- **Security and Confidentiality**: Role-based access, field-level redaction

**Testimonial**: "ArkCase Legal has an intuitive yet powerful interface. The application stores all the important case details within the application, including documents, contacts, calendars, emails, tasks, and billing." -- Michael L., CIO, Top 100 US Law Firm

**Customer**: BC Government Service & Employees' Union

### 3.3 Legal Case Management (screenshot: 09-legal-case-management.png)
**Target**: Legal organizations, law firms, government entities

**Key Capabilities**:
- **360-degree View of Case**: Case/document/records management, time/cost management, people/organization management, notes/references
- **Document and Records Management**: 100+ document types, online editing, MS O365/Exchange integration, web viewer with annotations/redactions, AI transcription/NLP, retention policies
- **Task and Workflow Automation**: Case/document approval, automated tasks, business rules engine
- **Search, Reporting, and Business Analytics**: Full-text search, canned/ad-hoc reports, scheduled reports, predictive analytics, dashboards, heatmaps

**Customer**: Victoria Police Department, US Office of Personnel Management

### 3.4 Correspondence Management (screenshot: 10-correspondence-management.png)
**Target**: Public sector entities and large organizations

**Key Capabilities**:
- **Never Lose Correspondence Again**: Email ingestion, document organization, retention management, search
- **Customized to Fit Your Needs**: Drag-and-drop form/template builder, version control, revision history, document lifecycle management
- **Save Time**: Streamlined creation-to-distribution process
- **No More Missed Deadlines**: Tracking all incoming/outgoing correspondence, role-based access

**Testimonial**: "ArkCase Correspondence Management provides our agency with efficient and cost-effective management plus seamless automation of all our incoming and outgoing correspondence from within a single platform." -- Mohamad G., CIO, UAE Government Agency

### 3.5 Docket Management (screenshot: 11-docket-management.png)
**Target**: Legal organizations, law firms, courts

**Key Capabilities**:
- **Business Value**: Increased efficiency, cost savings (digital over paper), scalability, decision-making support, integrated communication
- **360-degree View of Filing**: Single docketing, eFiling, case monitoring in the cloud
- **Easy Access**: Create filings, upload documents, protect sensitive materials, designate filing submitters
- **Immediate Notifications**: Document alerts on docket updates
- **Dynamic Insights**: Customizable reports and analytics
- **Worry-Free Compliance**: Electronic and paper filing, PDF and Word format support

**Customer**: Postal Regulatory Commission

### 3.6 Matter Management (screenshot: 12-matter-management.png)
**Target**: Legal departments managing complex matter assignments

**Core Features**:
- Matter tracking and organization
- Document management and secure storage
- Workflow automation
- Financial management (budgeting, expense tracking)
- Compliance management
- Vendor management (external legal services)
- Integration and customization
- Collaboration and communication
- Reporting and analytics
- Security and compliance

**Customer**: US Office of Personnel Management

### 3.7 e-Filing (not separately screenshotted, bundled with Docket Management)
Integrated eFiling portal for submitting and accessing filings electronically.

---

## 4. Technology Stack & Architecture

### Backend
- **Language**: Java (66.4% of codebase)
- **Framework**: Spring (Spring Cloud Config Server for configuration)
- **Application Server**: Tomcat 9 with TLS (port 8843)
- **Build**: Maven (`mvn -DskipITs clean install`)
- **Prerequisites**: Java 8, Maven 3.5+, Node.js

### Frontend
- **Framework**: AngularJS (Angular 1.x) -- LEGACY framework
- **Build Tool**: Grunt (via Gruntfile.js)
- **Language**: JavaScript (27.0%), HTML (6.2%)
- **Styling**: SCSS

### UI Modules (from GitHub source analysis)
30 AngularJS modules in the webapp:

**Core/Navigation**:
- `admin` -- Administration panel
- `core` -- Core application framework
- `dashboard` -- Main dashboard
- `welcome` -- Welcome/landing page
- `goodbye` -- Logout page
- `preference` -- User preferences
- `profile` -- User profile

**Case Management**:
- `cases` -- Case management
- `complaints` -- Complaint handling
- `consultations` -- Consultation management
- `organizations` -- Organization management
- `people` -- People/contact management
- `tags` -- Tagging system

**Document Management**:
- `document-details` -- Document detail view
- `document-repository` -- Document repository browser
- `my-documents` -- Personal document management

**Reporting & Analytics**:
- `adhoc-reports` -- Ad-hoc report builder
- `analytics` -- Analytics dashboards
- `analytics-audit` -- Audit analytics
- `reports` -- Canned reports

**Workflow & Tracking**:
- `cost-tracking` -- Cost/billing tracking
- `time-tracking` -- Time tracking
- `tasks` -- Task management
- `progress-bar` -- Progress indicators

**Additional**:
- `audit` -- Audit trail
- `common` -- Shared components
- `frevvo` -- Form builder integration (Frevvo)
- `notifications` -- Notification system
- `search` -- Search functionality
- `subscriptions` -- Subscription management

### Infrastructure Dependencies
- **Search**: Apache Solr
- **Content Management**: Alfresco (CE or Enterprise)
- **Message Queue**: ActiveMQ/Artemis
- **Database**: PostgreSQL or MariaDB
- **Directory Services**: Samba (LDAP/Active Directory)
- **Reporting**: Pentaho (CE or Enterprise)
- **AI**: ArkCase Illume (Enterprise only, for transcription/NLP)

---

## 5. Key Observations for Procest Comparison

### Strengths of ArkCase
1. **Mature platform** -- Years of development with real government deployments (EEOC, DOJ, OPM, Washington State Legislature)
2. **FedRAMP Authorized** -- Highest US government security certification for SaaS
3. **Comprehensive case management** -- 360-degree case view with documents, contacts, calendars, emails, tasks, billing all in one place
4. **Multiple solution verticals** from one platform -- FOIA, Complaint, Correspondence, Docket, Legal, Matter Management
5. **AI integration** (Enterprise) -- Transcription, NLP, PII redaction
6. **Document redaction** -- Native annotation and redaction capabilities
7. **Low-code workflow builder** -- Drag-and-drop workflow automation
8. **Compliance certifications** -- ISO 27001, HIPAA, SOC2, FedRAMP

### Weaknesses of ArkCase
1. **Legacy frontend** -- AngularJS (1.x) is end-of-life since December 2021. No migration to modern Angular, React, or Vue visible.
2. **Extremely complex deployment** -- Requires 7+ services (Solr, Alfresco, ActiveMQ, Pentaho, Samba, DB, Tomcat). No docker-compose available.
3. **No online demo** -- Cannot try the product without setting up a Vagrant VM or Kubernetes cluster
4. **US-government focused** -- All testimonials and customers are US agencies. No European/Dutch government focus.
5. **Heavy Java stack** -- Requires Java 8, Maven, Tomcat; developer onboarding is complex
6. **Closed-source enterprise features** -- AI, redaction, forms designer, HA/clustering all require Enterprise license
7. **No REST API documentation visible** -- API-first design not evident
8. **Alfresco dependency** -- Tightly coupled to Alfresco for content management

### Opportunities for Procest
1. **Modern tech stack advantage** -- Nextcloud + Vue.js/PHP is far simpler to deploy and maintain
2. **European/Dutch market** -- No comparable open-source case management in the Dutch government space
3. **Simpler deployment** -- Nextcloud ExApp or standard Nextcloud app vs. 7-service Kubernetes stack
4. **Built-in collaboration** -- Nextcloud already provides files, calendar, contacts, email, talk -- no need for external Alfresco/ActiveMQ
5. **API-first approach** -- OpenRegister provides clean REST APIs from the start
6. **Design tokens / NL Design** -- Government-compliant theming built in
7. **n8n workflow integration** -- Modern workflow automation vs. legacy Frevvo forms

### Feature Parity Gaps (what Procest would need)
1. **Document redaction** -- Annotation and redaction capabilities for sensitive documents
2. **Time and cost tracking** -- Built-in billing/time management for legal teams
3. **Advanced search** -- Full-text search with Solr/Elasticsearch integration (already planned via OpenRegister)
4. **Reporting/analytics** -- Dashboard widgets, canned reports, scheduled reports
5. **Compliance audit trail** -- Complete audit logging of all actions
6. **Template management** -- Correspondence and document template system
7. **Workflow automation** -- Visual drag-and-drop workflow builder (n8n provides this)

---

## 6. Screenshots Captured

| # | File | Description |
|---|------|-------------|
| 01 | 01-homepage.png | ArkCase marketing homepage |
| 02 | 02-product-page.png | Product page with CE vs Enterprise comparison |
| 03 | 03-community-edition.png | Community Edition details |
| 04 | 04-foia-solution.png | FOIA/Public Records solution page |
| 05 | 05-complaint-management.png | Complaint Management solution |
| 06 | 06-foia-overview-video-15s.png | FOIA Overview video (animated whiteboard) |
| 07 | 07-foia-demo-video-20s.png | FOIA Demo video title slide |
| 08 | 08-foia-demo-video-80s.png | Video playback error |
| 09 | 09-legal-case-management.png | Legal Case Management solution |
| 10 | 10-correspondence-management.png | Correspondence Management solution |
| 11 | 11-docket-management.png | Docket Management solution |
| 12 | 12-matter-management.png | Matter Management solution |

**Note**: No actual application UI screenshots could be captured because:
- No online demo instance exists
- Docker/Vagrant setup too complex for quick evaluation
- YouTube demo videos had playback issues in headless browser
- The marketing site shows only icons and illustrations, not actual application screenshots

### Additional Screenshots (2026-03-14)

| # | File | Description |
|---|------|-------------|
| 13 | 13-architecture-page.png | Architecture documentation page links |
| 14 | 14-architecture-diagram.png | Architecture overview diagram (layered: Clients > Angular > ArkCase REST API/Services > Storage) |
| 15 | 15-complaint-management-detail.png | Complaint Management hero section |
| 16 | 16-youtube-demos.png | YouTube search results showing ArkCase demo videos |
| 17 | 17-foia-demo-video.png | ArkCase FOIA Demo video page on YouTube |

---

## 7. Video Resources (for manual review)

These videos contain actual ArkCase UI walkthroughs and should be reviewed manually:

1. **ArkCase FOIA Demo** (14:41) -- https://www.youtube.com/watch?v=PsXvvL0Hf1E
   - 7 chapters: Introduction | Public Portal | User Interface | Documents Library | Document Viewer | Correspondence Management | Reporting
2. **ArkCase Complaint Management Solution Demo** (24:00) -- https://www.youtube.com/watch?v=rP0EPKzzsTI
3. **ArkCase Legal Demo** (video file) -- https://www.arkcase.com/wp-content/uploads/2024/09/Shorter-Legal-Demo.mp4
4. **ArkCase Docket Demo** (video file) -- https://www.arkcase.com/wp-content/uploads/2024/08/Dockets-08132024.mp4
5. **Legal Case Management Overview** (2:59) -- https://www.youtube.com/watch?v=uSDHzffOUYk
6. **ArkCase Legal: Secure Case Management** (1:08) -- https://www.youtube.com/watch?v=zRwwX9sDCBc
7. **ArkCase Claims Management System** (43:00) -- https://www.youtube.com/watch?v=T-MrRwbbniU
   - 14 moments covering: claim info, attachments, approvers, case investigation, records management, reports, calendar, email templates
8. **ArkCase 3.1 An Overview** (9:50) -- https://www.youtube.com/watch?v=Vq-wYt7F4wo
   - 9 chapters: Dashboard | Search | Complaint | Viewer | Workflow | Investigation | File Plan | Configuration | Summary
9. **ArkCase An Overview** (8:17) -- https://www.youtube.com/watch?v=UYOvJU8M1wA
   - 7 chapters: Dashboard | Complaints | Closing a Complaint | Converting to a Case | Records Management | Reports
10. **ArkCase - Legal** (36:00) -- https://www.youtube.com/watch?v=pSPF9mvonF4
11. **Legal Case Management Webinar (Demo Only)** (46:00) -- https://www.youtube.com/watch?v=0B1s95L4hwU
12. **ArkCase - FOIA Overview** (32:00) -- https://www.youtube.com/watch?v=_U2C9CW75-U

## 8. Downloadable Resources

- **FOIA Handout** -- https://www.arkcase.com/wp-content/uploads/2024/10/ArkCase-FOIA-Handout.pdf
- **Complaint Handout** -- https://www.arkcase.com/wp-content/uploads/2024/10/ArkCase-Handout-for-Complaint-Managment.pdf
- **Legal Handout** -- https://www.arkcase.com/wp-content/uploads/2026/01/ArkCase-Handout-for-Legal-Case-Management-2026.pdf
- **Correspondence Handout** -- https://www.arkcase.com/wp-content/uploads/2024/10/Draft-of-ArkCase-Handout-for-Correspondence-Management.pdf
- **Docket Handout** -- https://www.arkcase.com/wp-content/uploads/2024/10/Draft-of-ArkCase-Handout-for-Docket-Management.pdf
- **Matter Handout** -- https://www.arkcase.com/wp-content/uploads/2024/10/Draft-of-ArkCase-Handout-for-Matter-Management.pdf
- **Security Whitepaper** -- https://www.arkcase.com/wp-content/uploads/2024/11/security-compliance-exported.pdf
- **Docket Guide** -- https://www.arkcase.com/wp-content/uploads/2024/10/ArkCase-Docket-Management-Solution-final.pdf
