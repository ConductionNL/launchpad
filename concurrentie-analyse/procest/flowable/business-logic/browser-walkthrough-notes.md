# Flowable Browser Walkthrough Notes

**Date:** 2026-03-14
**Version:** Flowable 6.5.0 (all-in-one Docker image)
**Docker image:** `flowable/all-in-one:latest`
**Port mapping:** localhost:9002 -> 8080
**Credentials:** admin / test

---

## Architecture Overview

Flowable all-in-one deploys **4 separate WAR files** on a single Tomcat 9 instance:

1. **flowable-idm** -- Identity Management (SSO, users, groups, privileges)
2. **flowable-modeler** -- Visual editors for BPMN, CMMN, DMN, Forms
3. **flowable-task** -- Runtime task/process/case execution UI
4. **flowable-admin** -- Administration dashboard for all 6 engines

Each app has its own URL path and session, but they share the same IDM for authentication. The IDM redirects are cookie-based (FlowableCookieFilter).

---

## 1. Flowable IDM (Identity Management)

**URL:** `/flowable-idm/`

### Users (screenshot 01)
- Table view: ID, Email, Name, Tenant columns
- "Create user" button, search by name sidebar
- Bulk actions dropdown ("Select an action")
- Sorting by User ID A-Z

### Groups (screenshot 02)
- Simple list + detail layout
- "Create group" button
- Empty by default

### Privileges (screenshots 03-04)
- 5 built-in privileges:
  - Access identity management application
  - Access admin application
  - Access modeler application
  - Access the workflow application
  - Access the REST API
- Clicking a privilege shows Users/Groups tabs with add/remove capability
- Each privilege can be assigned to individual users or groups

**Relevance to Procest:** Basic IAM. Procest would need similar role-based access (case manager, admin, viewer) but likely delegates to Nextcloud's user system.

---

## 2. Flowable Modeler

**URL:** `/flowable-modeler/`

Top-level navigation: **Processes | Case models | Forms | Decision Tables | Apps**

### 2a. BPMN Process Editor (screenshots 05-09)

**List view:**
- Search, sort by "Last modified"
- "Create Process" and "Import Process" buttons
- Version indicator badge on each model card

**Editor (full-featured visual BPMN 2.0 editor):**

**Toolbar (left to right):**
- Save, Validate
- Cut, Copy, Paste, Delete
- Redo, Undo
- Align vertical, Align horizontal, Same size
- Zoom in, Zoom out, Zoom actual size, Zoom to fit
- Add/Remove bend-point on sequence flows
- Guided tour (help)
- Close

**Element Palette (left sidebar, 10 categories):**
1. **Start Events** (8 types): None, Timer, Signal, Message, Error, Event Registry, Escalation, Conditional
2. **Activities** (13 types): User task, Service task, Script task, Business rule task, Receive task, Manual task, Mail task, Camel task, Http task, Mule task, Decision task, Send event task, Shell task
3. **Structural** (5 types): Sub process, Collapsed sub process, Event sub process, Call activity, Adhoc sub process
4. **Gateways** (4 types): Exclusive, Parallel, Inclusive, Event
5. **Boundary Events** (9 types): Error, Escalation, Timer, Signal, Message, Cancel, Event Registry, Conditional, Compensation
6. **Intermediate Catching Events** (4 types): Timer, Signal, Message, Conditional
7. **Intermediate Throwing Events** (3 types): None, Signal, Escalation
8. **End Events** (5 types): None, Error, Escalation, Cancel, Terminate
9. **Swimlanes** (2 types): Pool, Lane
10. **Artifacts** (1 type): Text annotation

**Properties Panel (bottom):**
- Process identifier, Name, Documentation
- Process author, version string
- Target namespace
- History level setting
- Is executable checkbox
- Data Objects configuration
- Execution listeners, Event listeners
- Signal/Message/Escalation definitions
- Potential starter user/group
- Eager execution fetching toggle

**Process Navigator (bottom-left):**
- Tree view of structural elements in the process

**Assessment:** Very mature, full BPMN 2.0 spec support. Based on Oryx editor (open-source diagramming framework). The 46+ element types cover virtually every BPMN construct. The Properties panel is comprehensive with listeners, data objects, and signals.

### 2b. CMMN Case Editor (screenshots 10-11)

**Element Palette (5 categories):**
1. **Containers**: Stage
2. **Activities** (10 types): Task, Human task, Service task, Decision task, Http task, Script task, Send event task, Milestone, Case task, Process task
3. **Event Listeners** (3 types): Event listener, Timer event listener, User event listener
4. **Sentries** (2 types): Entry criterion, Exit criterion
5. **Connectors**: Association

**Properties Panel:**
- Case identifier, Name, Documentation
- Initiator variable name
- Case author, version string
- Target namespace
- Event key/name, correlation parameters
- Channel key/name/type/destination
- Event key detection (fixed value, json field, json pointer)

**Assessment:** Full CMMN 1.1 support. The Case Plan Model container is automatically created. The canvas supports nesting stages within stages. Sentries (entry/exit criteria) are first-class visual elements. The event-driven channel configuration is particularly advanced.

### 2c. Form Editor (screenshots 12-13)

**Drag-and-drop field types (18 types):**
- Text, Password, Multiline text
- Number, Decimal
- Checkbox, Date
- Dropdown, Radio buttons
- People, Group of people (user pickers)
- Upload (file attachment)
- Expression (dynamic value)
- Hyperlink
- Spacer, Horizontal line
- Headline (2 size variants: H1, H2)

**Tabs:** Design | Outcomes

**Assessment:** Basic form builder for human tasks. No conditional visibility, no repeating sections, no table fields. The "Outcomes" tab allows defining decision buttons (Approve/Reject). Adequate for simple task forms but limited compared to dedicated form builders.

### 2d. DMN Decision Table Editor (screenshots 14-15)

**Features:**
- Hit policy selector (F = First, by default)
- Input columns (blue background) with variable name + type
- Output columns (white background) with variable name + type
- Row-based rule definition with row numbers
- Actions dropdown menu
- Add column via "+" button on each column header

**Assessment:** Standard DMN decision table. Supports hit policies (First, Unique, Any, Priority, Rule Order, Output Order, Collect). No visual DRD (Decision Requirements Diagram) editor in this OSS version -- that appears to be a Flowable Work (commercial) feature.

### 2e. App Definitions (screenshot 16)

- Bundle processes, forms, case models, and decision tables into deployable "Apps"
- "Create App" and "Import App" buttons
- Apps are the deployment unit for the Task app

---

## 3. Flowable Task (Workflow Runtime)

**URL:** `/flowable-task/`

### Home (screenshot 17)
- Tile-based app launcher showing "Task App" with clock icon
- Additional deployed apps would appear as additional tiles

### Tasks View (screenshot 18)
- Left panel: task list with filter + sort
  - Filter: "Showing your tasks, no filter applied" (clickable filter icon)
  - Sort: "Newest first" dropdown
  - "+ Create Task" button
- Right panel: task detail (empty state shows helpful guidance)
- Master-detail layout

### Processes View (screenshot 19)
- Same master-detail layout
- Left panel: process instance list
  - Filter: "Showing running processes"
  - "+ Start a process" button
  - Sort: "Newest first"
- Right panel: process detail/diagram

### Cases View (screenshot 20)
- Same master-detail layout as processes
- Left panel: case instance list
  - Filter: "Showing running cases"
  - "+ Start a case" button
- Right panel: case detail

**Assessment:** Clean, functional task/process/case execution UI. The three-tab structure (Tasks, Processes, Cases) separates concerns well. Each section has consistent filtering, sorting, and creation capabilities. The empty states provide good onboarding guidance. However, the UI is relatively dated (Angular 1.x / Bootstrap 3 era styling).

---

## 4. Flowable Admin (Administration Dashboard)

**URL:** `/flowable-admin/`

### Engine Configuration (screenshot 21)
Top-level tabs for **6 engines**:
1. **Process Engine** (yellow/green)
2. **CMMN Engine** (yellow)
3. **App Engine**
4. **Form Engine**
5. **DMN Engine**
6. **Content Engine**

Each engine has REST endpoint configuration:
- Server address, port, context root, REST root, username
- Actions: Edit REST endpoint, Check REST endpoint

### Process Engine Admin (screenshot 22)
Sub-navigation: **Deployments | Definitions | Instances | Tasks | Jobs | Batches | Event subscriptions**

Each section provides:
- Tabular data view with sortable columns
- Filters sidebar (Name, Tenant identifier)
- Actions panel (e.g., "Upload process or package")
- Pagination controls (Show 25 results)

### CMMN Engine Admin (screenshot 23)
Sub-navigation: **Deployments | Definitions | Instances | Tasks | Jobs**

Same pattern as Process Engine admin but for CMMN artifacts.

**Assessment:** Comprehensive admin dashboard for monitoring and managing all engine types. The separation by engine type is clean. The ability to upload deployments directly and manage jobs/batches is powerful for operations.

---

## Key Observations for Procest Competitive Analysis

### Strengths
1. **Complete BPM suite** -- BPMN + CMMN + DMN + Forms in one product
2. **Visual editors for everything** -- drag-and-drop modelers for all notation types
3. **Multi-engine architecture** -- each engine (Process, CMMN, DMN, Form, Content) is independent but integrated
4. **Standards-based** -- full BPMN 2.0, CMMN 1.1, DMN 1.1 compliance
5. **Comprehensive element library** -- 46+ BPMN elements, 16+ CMMN elements
6. **Admin tooling** -- monitoring deployments, instances, tasks, jobs, batches
7. **Identity management** -- built-in user/group/privilege system
8. **App bundling** -- package models into deployable apps
9. **REST API** -- every engine exposes a REST API
10. **Import/Export** -- BPMN XML, CMMN XML, DMN XML import/export

### Weaknesses
1. **Dated UI** -- Angular 1.x / Bootstrap 3 styling, not modern
2. **i18n broken** -- "LOGIN.USERNAME-PLACEHOLDER" and similar untranslated keys (en-US.json 404s)
3. **Form builder is basic** -- no conditional logic, no repeating sections, no table fields
4. **No DRD editor in OSS** -- only decision tables, not full Decision Requirements Diagrams
5. **Complex deployment** -- 4 separate WAR files, Tomcat-based, heavy JVM footprint
6. **Commercial features gated** -- Flowable Work (commercial) has much richer modeler, analytics, work app
7. **No real-time collaboration** -- single-user editing only
8. **Memory-heavy** -- all-in-one image runs 4 Spring Boot apps + Tomcat, significant RAM usage

### Relevance to Procest
- Flowable is the most feature-complete open-source BPM engine
- Procest competes primarily on the **case management** (CMMN) and **task execution** aspects
- Flowable's CMMN support is a direct competitor to Procest's case handling
- The integration story differs: Flowable is standalone Java, Procest integrates into Nextcloud
- Procest's advantage: Nextcloud ecosystem integration (files, users, sharing, collaboration)
- Flowable's advantage: standards compliance, engine maturity (10+ years), massive BPMN element library
