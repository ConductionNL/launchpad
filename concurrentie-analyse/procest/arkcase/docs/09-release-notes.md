# ArkCase Release Notes (2024-2026)

**Source:** arkcase.com release notes pages

## Release Cadence
ArkCase follows a version scheme of `YY.MM.patch` (e.g., 25.09.01 = 2025, September, patch 1).
Releases appear roughly quarterly with patch releases between major versions.

---

## Release 25.09.01 (February 2, 2026)

### Enhancements
- Enhancements to FOIA Log Report
- Exemption codes during document redaction shown in white color as default
- Improved speed of generating Audit report
- Enhancements to Throughput Time and Business Days Calculation Within FOIA Workflow
- Naming Convention Enhancements to DOJ Reports
- Enhancements to Merge Fields for Exemption Code & Exemption Code 3 Statutes
- Enhancements to Improve Description and Addition of Exemption Code 3 Statutes
- Ability to Define the Start Date of a FOIA Hold
- Perfected Date Calendar Should Restrict Future Date Selection in FOIA Workflow

### New Features (Stories)
- Show "Untimely Appeal" Flag on Appeals Created 90 Days Past the FOIA Deliver Date
- Manually Select Date the Request was supposed to come off Hold
- Add Folders & Documents from Underlying Appeal/Original Request on Remanded Requests
- Ability to View and Configure Untimely Appeal Configuration in Admin Module
- Fiscal Year Added to Admin Config
- **Dashboard Widgets:**
  - Pending Requests, PAs, Appeals and Consultations
  - Backlogged FOIA Requests, PAs, Appeals and Consultations
  - Admin Configuration for Staff and Budget Data
  - Active FOIA Users & Staff Activity
  - Average Throughput Time

### Bug Fixes
- Email settings for Email Ingestion not saved
- Unable to search by Tags
- Hold History timezone offset (EDT/EST transition)
- Due Date Calculation ignoring Perfected Date after Hold removal
- Hold Duration miscalculated during holidays/weekends

---

## Release 25.09.00 (November 21, 2025)

### Key Features
- Enable Default Redaction
- Untimely Appeal Configuration in Admin module
- Disable Hold Button When Disposition Closed Date Is Set
- ArkCase FOIA Substantial Interest Functionality + Report
- **Implementation of clustering support for Kubernetes environments**

### Improvements
- Security Improvements for FedRAMP Recertification
- Performance Improvements for large file uploads
- Improvements to the Relativity Connector for reliability
- Annual Updates to Exemption 3 Statutes (2024)
- Improvements to FOIA DOJ Reports
- Large File Uploads UI Progress Bar
- Adding Additional Expressions to Document Viewer Redactions
- Ability to Mass Delete Proposed Auto Redactions in Document Viewer
- FOIA Request Form Chunked Upload on Public Portal
- Do Not Send Portal User Activation Email in Unauthenticated Mode

### Bug Fixes (Selected)
- Some patterns not marking correct content during auto-redactions
- Auto Redaction alignment issues for Word format files
- Unable to Upload EML or MSG files (stuck at 59%)
- Various billing, holiday schedule, and tag management fixes
- Hold Time Days calculation corrections

---

## Release 25.03.00 (June 30, 2025)

### Major Features
- **Extra Fields** -- dynamic custom fields for entities (Person, Organization, etc.)
  - Configurable in Admin
  - Searchable and facetable
  - Audit events for create/update/delete
  - Web component-based
- **Relativity Integration** -- workspace creation, file sync
- **Box Integration** within ArkCase
- "Case Summary" Report (Legal/FOIA)
- Functionality to add attachments to email modal
- Interactive report "Matter" in Legal
- Enable group management within customer's directory
- Referral Section for FOIA and PA Requests
- Portal Reading Room ZIP download
- Exemption 5 Subtypes in CORE FOIA
- Configurable retention rules for portal documents
- New email templates and correspondence templates

### Improvements
- Auto Redaction Configuration (exemption code colors)
- Queue status indicator (Return to previous queue)
- FOIA Due Date Approaching Email with request link
- Enable withdrawal option in request/appeal workflow
- Mixed-Case Email Address handling for Portal login
- Various IDE and build logging improvements

### Bug Fixes
- 100+ bug fixes across Legal, FOIA, and Portal modules
- Major areas: document management, workflow, people/organizations, search, portal

---

## Release 24.09.02 (December 19, 2024)

### Improvements (17 items)
- "Person Organization Position" label updates
- Spelling correction: "Complaintant" to "Complainant"
- ArkCase News Widget
- Expedited Processing configurable in Admin for Portal
- "Time in Hold Queue" metric for DOJ Reporting
- Users cannot add Disposition Closed Date while in HOLD Queue
- Anonymous Request Submissions configurability
- Exemption 5 Subtypes
- Parent/subsidiary organization naming
- Solr index improvements
- International phone number format support
- Multiple calendar events display improvements
- New merge fields for correspondence/email templates
- New Standard Document Metadata
- Remanded Request references
- Anonymous person association restrictions
- Exemption Codes Node view in admin

### Bug Fixes (50+ items)
- Hold functionality issues (Request & Appeal)
- PDFTron document version loading
- OCR issues
- PowerPoint opening in Microsoft Office Online
- Document version management
- Records declaration
- Drag & drop file management
- Email/calendar integration
- Various FOIA workflow fixes
- Password disclosure remediation

---

## Observations for Competitive Analysis

### Release Focus Areas
1. **FOIA dominates development** -- 80%+ of release items are FOIA-related
2. **Government compliance** -- DOJ reports, exemption codes, FedRAMP recertification
3. **Document management** -- redaction, viewer, version management heavily iterated
4. **Portal improvements** -- public-facing portal receives continuous attention
5. **Enterprise integrations** -- Relativity, Box, Kubernetes clustering
6. **AI/redaction** -- auto-redaction patterns continuously improved

### Technical Debt Signals
- Many UI refresh/reload bugs (data not appearing until page refresh)
- Document viewer (PDFTron) has recurring issues
- Email integration has persistent problems
- Holiday/date calculation bugs appear across releases
- AngularJS 1.x frontend (legacy framework)
