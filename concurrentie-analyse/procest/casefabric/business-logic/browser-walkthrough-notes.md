# CaseFabric (Cafienne) -- Browser Walkthrough Notes

**Date:** 2026-03-14
**Version tested:** Engine 1.1.35, IDE latest, UI 0.7.3
**Docker setup:** `casefabric/getting-started` repo (docker-compose with 5 containers)

---

## 1. Product Overview

CaseFabric (formerly Cafienne) is a **100% CMMN 1.1 case management engine**. The product suite consists of three main components:

| Component | Port | Description |
|-----------|------|-------------|
| **CaseFabric Engine** | 2027 | Scala/Pekko-based CMMN engine with REST API (Swagger) |
| **CaseFabric IDE** (Case Designer) | 2081 | Browser-based visual CMMN model editor |
| **CaseFabric UI** | 3317 | Generic React-based case management user interface |
| Dex IDP | 5556 | OpenID Connect identity provider |
| PostgreSQL | 5431 | Event store + query database |
| MailCatcher | 1080 | Dev email capture |

**GitHub:** Migrated from `cafienne` to `casefabric` org. Only 6 repos, very small codebase footprint.
**License:** Mozilla Public License (MPL)
**Tech stack:** Scala + Apache Pekko (formerly Akka), React UI, PostgreSQL event sourcing

---

## 2. Docker Setup Experience

- **Ease of setup:** Very easy. Single `docker-compose up` from `casefabric/getting-started` repo.
- **Image pull:** ~2 minutes for all 6 images.
- **Issue encountered:** Dex IDP container crashed on first boot due to SQLite database file permissions (`unable to open database file`). Fixed by correcting directory permissions on `run/dex/`.
- **Boot time:** ~15 seconds to healthy state after all images pulled.
- **Persistence:** PostgreSQL-backed, data survives restarts.

---

## 3. Authentication

- Uses **Dex** (OpenID Connect) as identity provider.
- Demo accounts: `suzy`, `lana`, `hank` (password = username).
- Standard OAuth2 redirect flow to Dex login page.
- Custom branded "CaseFabric Login" page.
- Multi-tenant support (default tenant: "world").

---

## 4. CaseFabric UI Walkthrough

### 4.1 Navigation Structure

Left sidebar with 5 items:
1. **Dashboard** -- Overview of user's cases (empty when no cases exist)
2. **Cases** -- List of all cases with filtering (tenant, state, definition, identifiers)
3. **Tasks** -- List of all tasks with filtering (same filter options)
4. **Start Case** -- Browse and launch case definitions
5. **Guide** -- External link to reference guide

### 4.2 Cases List (screenshot: 05)
- Table view with columns: Case Name, Case ID, State, Last Modified
- Filter bar: tenant dropdown, state dropdown, definition text, identifiers text
- Submit/Reset filter buttons
- No pagination controls visible (may appear with more data)

### 4.3 Tasks List (screenshot: 07)
- Same layout as Cases but with extra "Assignee" column
- Columns: Task Name, Task ID, State, Assignee, Last Modified
- Same filter bar as Cases

### 4.4 Start Case (screenshot: 08)
- Shows available case definitions from deployed CMMN models
- Each definition shows: internal name (e.g., "travelrequest") + display name (e.g., "Travel Request")
- Refresh button to reload definitions from engine
- Demo includes: `helloworld` and `travelrequest`

### 4.5 Case Start Form -- Travel Request (screenshot: 09)
A rich, multi-section form generated from CMMN case file schema:

**Form sections:**
1. **Requestor / Traveller Details** -- Request date, requestor (user dropdown), point of contact
2. **Traveller** (repeatable) -- Name, email, nationality (country dropdown), pass number. "Add Item" button for multiple travellers.
3. **Destination and Dates** -- Country/city (repeatable), departure/return dates, departure/return points, advance required checkbox, purpose, justification, comments
4. **Meeting Details** -- Start/end date/time, duration incl. leave
5. **Transport Details** -- Mode of transport dropdown, POMV make, POMV license
6. **Project Details** -- IMIS code, activity code, project manager (user dropdown), chief service line, project assistant

**Case Team panel** (right side): Select team members (suzy, lana, hank) with role assignment and owner toggle.

**UI quality:** Forms use Material UI (MUI) components. Country dropdowns, date pickers, user selection dropdowns. Repeatable sections with add/remove. Required field indicators.

### 4.6 Case Detail View (screenshot: 10)

Three tabs: **Plan**, **Team**, **File**

#### Plan Tab
- Shows CMMN case plan as a **hierarchical list** (not visual diagram)
- Each plan item shows: icon (human task/stage/milestone/event), name, timestamp, state badge (Active/Assigned/Available/Completed)
- Action buttons per item: Suspend, Complete, Terminate, Fault, Exit
- **Expand stages** checkbox to show nested items
- **Show history** checkbox to include completed/terminated items
- **Show future items** checkbox for items awaiting entry criteria

**Plan items visible in Travel Request:**
1. "View Travel request" -- Human Task, Assigned
2. "Approve and Generate Travel Order" -- Stage, Active
3. "Check prerequisites" -- Stage, Active
4. "Cancel Travel request" -- Event, Available
5. "Request Submitted" -- Milestone, Completed
6. "Select approver manually" -- Discretionary Human Task

#### Details panel (right side)
- Case ID, Name, Modified/Created timestamps, State, Tenant
- Refresh case / Update definition buttons

#### Team Tab (screenshot: 11)
- **Case team users** table: User ID, Case roles (editable), Owner toggle
- Add new team members via user dropdown
- **Groups** section: Tenant Role, Case roles, Owner
- Submit button to save team changes

#### File Tab (screenshot: 12)
- Full **case file editor** with all data sections
- Refresh, Update, Replace buttons
- "Edit raw json" toggle for direct JSON editing
- All form data displayed in editable fields organized by schema structure
- Sections: TravelRequest > TravelDetails, TravellerDetails, TravelOrderDoc, Meeting, Transport, Project, Approval, TravelStatus

### 4.7 Task Detail View (screenshot: 13)
- Full task form (same rich form as start case, pre-filled with data)
- Action buttons: **Revoke**, **Submit**, **Save for later**
- Task Details panel: Task ID, Case ID, Name, Role, Due date, Assignee, Owner, State, Modified/Created, Tenant

### 4.8 Email Notifications (screenshot: 20)
- Engine automatically sent "Travel Approval request" email upon case creation
- MailCatcher captured the email from `travelrequest@email.org`
- Demonstrates built-in process task integration with SMTP

---

## 5. Case Designer (IDE) Walkthrough

### 5.1 Repository Browser (screenshot: 14)
Left panel organized into 5 expandable sections:
1. **Cases** -- CMMN case models (helloworld, travelrequest)
2. **Human Task Models** -- Task form definitions
3. **Decisions** -- DMN-like decision models
4. **Processes** -- Process task implementations
5. **Types** -- Data type definitions (case file schemas)

Each section has a "+" button to create new items. Search bar at top. Refresh button.

### 5.2 CMMN Visual Editor (screenshot: 16)
- **Canvas** with CMMN diagram showing:
  - Case plan boundary rectangle ("Travel Request")
  - Human tasks (rounded rectangles with person icon)
  - Stages (expandable containers)
  - Milestones (rounded pill shapes)
  - Discretionary items (dashed borders)
  - Sentry connectors (entry/exit criteria diamonds)

- **Shapes palette** (left): All CMMN elements including:
  - Human Task, Case Task, Process Task
  - Stage
  - Milestone
  - Timer Event, User Event
  - Entry/Exit Criterion
  - Other CMMN shapes

- **Case File editor** (right panel) with tabs:
  - **Editor** -- Tree view of case file properties with types
  - **Source** -- Raw XML source
  - **JSON Schema** -- JSON Schema representation

### 5.3 Model Management
- Deploy dialog with: "View CMMN" (XML preview), "Deploy" (to engine), "Server validation" (validates against engine at localhost:2027)
- Delete, Rename, Deploy buttons per model in repository
- Grid size control and "Show grid" toggle

---

## 6. REST API (Swagger) (screenshot: 17)

Version: 1.1.34-SNAPSHOT, OAS 3.0

**API categories:**
- **case** -- Case instance CRUD, debug mode, state transitions
- **case-file** -- Case file data operations
- **case-history** -- Event history, plan item history
- **case-team** -- Team member management
- **discretionary** -- Discretionary item planning
- **repository** -- Model deployment and management
- **tasks** -- Task lifecycle (claim, revoke, complete, save)
- **tenant** -- Multi-tenant management, user/group management
- **platform** -- Health check, version info, configuration
- **migration** -- Case migration between model versions

---

## 7. Key Observations for Procest Competitive Analysis

### Strengths
1. **Pure CMMN 1.1 implementation** -- The only open-source engine that is 100% CMMN compliant. This is a genuine differentiator.
2. **Visual CMMN designer** -- Browser-based IDE for designing case models with drag-and-drop. No equivalent in Procest.
3. **Generic UI** -- Can interpret ANY case model without custom UI development. Rapid prototyping capability.
4. **Event sourcing architecture** -- Pekko/Cassandra/PostgreSQL event store provides full audit trail and high scalability.
5. **Multi-tenant** -- Built-in tenant support with role-based access.
6. **Docker-first** -- Easy to deploy, microservices architecture.
7. **Automated email notifications** -- Process tasks can trigger emails as part of case flow.
8. **Discretionary tasks** -- True CMMN feature allowing case workers to add tasks at their discretion.
9. **Team management** -- Dynamic case teams with role assignment per case instance.

### Weaknesses
1. **Minimal UI polish** -- The UI (v0.7.3) is functional but very basic. Material UI components without custom styling. No dashboard visualizations, no charts, no KPIs.
2. **Small community** -- Only 6 repos in the GitHub org. Very few contributors. Last blog post from 2020.
3. **No document management** -- Case file is purely data-driven (JSON). No file attachments, no document preview.
4. **No search** -- Cases and tasks are filtered but there is no full-text search capability.
5. **Dex IDP dependency** -- Requires external identity provider setup. No built-in user management.
6. **Limited process automation** -- Process tasks exist but the automation capabilities seem limited compared to n8n/workflow engines.
7. **No low-code form builder** -- Forms are generated from JSON Schema (React JSON Schema Form). No visual form designer.
8. **Dated technology choices** -- Dex v2.23.0 is old, React app uses class components, no TypeScript.
9. **CMMN-only** -- No BPMN support. CMMN has lower market adoption and fewer practitioners.
10. **No mobile UI** -- Not responsive, fixed-width layout.

### Relevance to Procest
- CaseFabric targets the **same problem space** (case management for knowledge workers) but with a different approach (CMMN standard vs custom implementation).
- The CMMN visual designer is a unique capability that Procest does not have.
- CaseFabric's generic UI approach (render any case model) is interesting for rapid prototyping but sacrifices UX quality.
- Procest's integration with Nextcloud (file management, sharing, collaboration) gives it an advantage CaseFabric cannot match.
- CaseFabric's event sourcing architecture is technically sophisticated but adds operational complexity.
- The Mendix integration (DCM for Mendix) shows CaseFabric positioning as an embeddable engine, not a standalone product.

### Market Position
- **Niche player** in open-source case management
- Strong appeal to organizations mandating CMMN compliance
- Used by Visionplanner (Dutch accounting platform) for fiscal dossier automation
- Dutch company (SpinQ/CaseFabric B.V.), potential direct competitor in NL government market
