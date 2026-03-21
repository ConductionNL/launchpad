# Twenty CRM - Browser Walkthrough Notes

**Date:** 2026-03-14
**Version:** latest (Docker image `twentycrm/twenty:latest`)
**Setup:** Docker Compose on port 9003 (server, worker, PostgreSQL 16, Redis)
**Startup time:** ~60 seconds to healthy state

---

## 1. Onboarding Flow

Twenty has a polished multi-step onboarding:
1. **Welcome page** - "Continue with Email" (also supports Google/Microsoft SSO when configured)
2. **Email + Password signup** - min 8 characters, instant account creation
3. **Create Workspace** - name + logo upload (PNG/JPEG/GIF under 10MB)
4. **Create Profile** - first/last name + avatar upload
5. **Email & Calendar sync** - privacy levels: Everything / Subject+metadata / Metadata only (skip option)
6. **Invite team** - up to 3 email fields + copy invitation link (skip option)

Demo data is pre-seeded: 5 companies (Airbnb, Anthropic, Stripe, Figma, Notion), 5 people, 6 opportunities, 1 workflow, 1 dashboard.

## 2. Navigation Structure

Left sidebar with two sections:

**Workspace:**
- People
- Companies
- Opportunities (sub-views: All Opportunities, By Stage)
- Tasks (sub-views: All Tasks, By Status, Assigned to Me)
- Notes
- Workflows
- Dashboards

**Other:**
- Settings
- Documentation (external link to docs.twenty.com)

Top bar: Workspace switcher, Search button, notification icon, "+ New record" button, command palette (Ctrl+K).

## 3. Companies (CRM Core)

### List View (screenshot: 03)
- Table with sortable/resizable columns: Name, Domain Name, Created by, Account Owner, Creation date, Employees, Linkedin, Address
- Column headers are draggable for reordering
- Each row has a checkbox for bulk selection and a context menu button
- Footer row with "Calculate" dropdown supporting: Count all, Max, Min, Sum, Average, Empty%, Not empty% per column
- Filter, Sort, and Options buttons at the top right
- "+ Add New" row at the bottom

### Side Panel Detail (screenshot: 04)
- Clicking a company opens a split-view side panel
- Tabs: Home, Timeline, Tasks, +4 More
- Fields section with collapsible "General" group
- Shows all fields inline: Address, ARR, Created by, Domain Name, Employees, ICP (boolean), Linkedin, Last update, Updated by, X
- Relation sections: Account Owner, Opportunities (linked), People (linked)
- "Open" button to go to full detail view

### Full Detail View (screenshot: 05)
- Large company logo (auto-fetched from domain) + name
- "Added X ago" timestamp
- Same Fields section as side panel but full width
- Right panel: Timeline (activity feed, currently empty for new data)
- Top bar actions: Add to favorites, Delete, Navigate prev/next record, Open side panel

### Company Fields
- Name, Domain Name, Address (structured: street, city, state, zip, country)
- Employees (number), ARR (currency), ICP (boolean)
- Linkedin URL, X (Twitter) URL
- Account Owner (user relation), Created by, Updated by, Last update, Creation date

## 4. People (Contacts)

### List View (screenshot: 06)
- Columns: Name (first+last with avatar initial), Emails (mailto links), Created by, Company (linked), Phones (tel links), Creation date, City, Job Title, Linkedin, X
- Same table features as Companies (filter, sort, options, calculate footer)
- 5 demo contacts linked to demo companies

## 5. Opportunities (Pipeline/Deals)

### List View (screenshot: 07)
- Columns: Name, Amount (currency with $ icon), Created by, Close date, Company (linked), Point of Contact (linked person)
- 6 demo opportunities: Platform Migration ($60k), AI Model Training ($100k), Workspace Expansion ($45k), API Integration Deal ($75k), Enterprise Plan Upgrade ($50k), Design Partnership ($30k)
- Footer: Average of Amount ($60k)

### Kanban/Pipeline View - "By Stage" (screenshot: 08)
- **This is the key pipeline view for Pipelinq comparison**
- 5 stages as columns: New (80k), Screening (75k), Meeting (45k), Proposal (60k), Customer (100k)
- Each column header shows stage name + total value
- Cards show: Name, Amount, Created by, Close date, Company, Point of Contact
- Each card is a rich mini-detail with all key fields visible
- "+ New" button at bottom of each column
- Drag-and-drop supported between stages

### Opportunity Fields
- Name, Amount (currency), Stage (select: New/Screening/Meeting/Proposal/Customer)
- Close date, Company (relation), Point of Contact (person relation)
- Created by, Creation date

## 6. Tasks

### List View (screenshot: 09)
- Columns: Title, Status, Relations, Created by, Due Date, Assignee, Body, Creation date
- Three saved views: All Tasks, By Status (Kanban), Assigned to Me (filtered)
- Empty state with "Add your first Task" CTA

### Task Fields
- Title, Status (select), Body (rich text)
- Due Date, Assignee (user relation), Relations (polymorphic links)

## 7. Notes

### List View (screenshot: 10)
- Columns: Title, Relations, Body, Created by, Creation date
- Simpler than Tasks - no status or assignee
- Notes can be linked to any record via Relations

## 8. Workflows (Automation)

### List View (screenshot: 11)
- Columns: Name, Statuses (Active/Draft/Deactivated), Last update, Created by, Versions, Runs
- "See runs" button in top bar to view execution history
- 1 demo workflow: "Quick Lead" (Active)

### Workflow Editor (screenshot: 12)
- **Visual node-based flow builder** (similar to n8n / Zapier)
- Demo flow: Trigger (Launch manually) -> Action (Quick Lead Form) -> Action (Create Company) -> Action (Create Person)
- Each node is clickable to configure
- Top bar actions: Delete, + Add a node, See runs, Test, Deactivate
- "Active" status badge
- "+ Add a step" button at the end of the flow

## 9. Dashboards

### List View (screenshot: 13)
- Columns: Title, Created by, Creation date, Last update
- 1 demo dashboard: "My First Dashboard"

### Dashboard Detail (screenshots: 14-15)
- **Rich dashboard builder with multiple widget types:**
  - Text block (welcome message with rich formatting)
  - Deals by Company (donut/pie chart with legend)
  - Pipeline Value by Stage (horizontal bar chart: New 80k, Screening 75k, Meeting 45k, Proposal 60k, Customer 100k)
  - Revenue Timeline (line chart over time)
  - Opportunities by Owner (horizontal bar chart)
  - Stock market iframe (embedded TradingView widget)
  - KPI number cards: "Deals created this month: 6", "Deal value created this month: $360k"
- Top bar: Add to favorites, Delete, Edit button

## 10. Settings

### User Settings (screenshot: 16)
- **Profile:** Picture upload, First/Last Name, Email
- **Experience:** UI preferences
- **Accounts > Emails:** Email provider connections (Google/Microsoft)
- **Accounts > Calendars:** Calendar sync settings

### Workspace Settings
- **General (screenshot: 19):** Workspace name, picture, Danger zone (delete workspace)
- **Data model (screenshot: 17):** Visual entity relationship diagram, list of all objects with field counts. 7 managed objects: Companies (21 fields), Dashboards (10), Notes (11), Opportunities (17), People (23), Tasks (14), Workflows (14). "+ Add object" button for custom objects.
- **Members:** Team member management
- **Roles:** RBAC role configuration
- **Domains:** Allowed email domains for signup
- **APIs & Webhooks (screenshot: 18):** Built-in REST and GraphQL API playground. Schema selector (Core/Metadata). API key management.

### Other Settings
- **Admin Panel:** Server administration
- **Updates:** Version updates
- **Advanced toggle:** Reveals additional system-level objects in data model

## 11. Command Palette / Search (screenshot: 20)

Ctrl+K opens a unified command palette with:

**Context-aware actions (based on current page):**
- Create new record
- Import records
- Export view
- See deleted records (soft-delete recovery)
- Create View

**Global actions:**
- Search records (/ shortcut)
- Quick Lead (workflow shortcut)
- Go to [any section] with keyboard shortcuts (G then W/P/D/O/S/T/N)

## 12. View Configuration (screenshot: 21)

Options menu per view:
- Default View indicator (locked for system views)
- Fields configuration (show/hide columns, "8 shown")
- Copy link to view
- Create custom view

Filter and Sort are separate buttons with inline configuration.

## 13. Key UX Patterns

- **Consistent table pattern:** Every object type uses the same table component with filter/sort/options/calculate
- **Inline editing:** Click any cell to edit directly in the table
- **Side panel + full view:** Click to preview in side panel, double-click or "Open" for full detail
- **Record navigation:** Prev/next arrows on detail views to browse through records
- **Relation chips:** Related records shown as clickable chips with avatar initials
- **Favorites system:** Any record can be bookmarked
- **Soft delete:** Deleted records are recoverable via "See deleted records"
- **Keyboard-first:** Extensive keyboard shortcuts for all navigation

## 14. Relevance to Pipelinq

### Strengths Twenty has that Pipelinq should consider:
1. **Polished Kanban pipeline view** with stage totals and rich cards
2. **Built-in workflow automation** (visual flow builder, not requiring external tools)
3. **Dashboard builder** with charts, KPIs, and embeddable iframes
4. **Custom objects** via Data model settings (extend beyond CRM primitives)
5. **Command palette** for power-user navigation
6. **API playground** built into settings (REST + GraphQL)
7. **Calculated column footers** (count, sum, avg, min, max, empty%)
8. **Import/Export** accessible from command palette
9. **Soft-delete with recovery**
10. **Email/Calendar sync** with privacy levels

### Potential weaknesses:
1. No visible multi-language support (English only in this version)
2. Demo data is US-centric (may not appeal to European government use cases)
3. Workflow builder is basic compared to n8n (limited trigger types, no conditional branching visible)
4. No visible role-based field-level permissions
5. No visible audit trail / change history per field
6. Dashboard widgets are pre-defined types, not fully custom
7. No visible form builder for external data collection

### Architecture notes:
- React SPA with GraphQL API (Apollo Client)
- PostgreSQL for data storage
- Redis for caching
- Background worker for async jobs
- Single Docker image for both server and worker
- JWT-based auth with token refresh
