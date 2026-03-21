# EspoCRM Browser Walkthrough Notes

**Date:** 2026-03-14
**Version:** Latest (Docker `espocrm/espocrm:latest`)
**Setup:** Docker with MySQL 8.0 on port 9004, credentials admin/admin123

---

## 1. Overall Architecture & Navigation

EspoCRM is a single-page application (SPA) using hash-based routing (`#Entity/action`). The UI follows a consistent three-panel layout:

- **Left sidebar:** Fixed navigation grouped into categories (CRM, Activities, Support)
- **Main content:** Entity lists, detail views, or forms
- **Right sidebar:** Metadata (assigned user, teams, timestamps) and related panels (Activities, History, Tasks)

The top bar features: global search, quick-create (+), notifications bell, and a user/settings menu.

### Navigation Structure
```
Home (Dashboard)
CRM
  Accounts
  Contacts
  Leads
  Opportunities
Activities
  Emails
  Meetings
  Calls
  Tasks
  Calendar
Support
  Cases
  Knowledge Base
```

## 2. Dashboard (Home)

**Screenshot:** `02-dashboard-home.png`

- Two default widgets: **Stream** and **My Activities**
- Dashboard is customizable (admin can deploy dashboard templates to users)
- Stream shows activity feed across the system
- Clean, minimal design with considerable whitespace

## 3. Accounts

**Screenshots:** `03-accounts-list.png`, `04-account-create-form.png`, `05-account-detail-view.png`

### List View
- Standard table/list with search bar and filter dropdown ("All" with preset filters)
- Pagination (0/0 format) with next/prev arrows
- "Create Account" button top-right
- Three-dot menu for additional list operations

### Create Form Fields
- **Overview section:** Name (required), Website, Email (multi-value with opt-out/invalid toggles), Phone (with type picker: Office/Mobile/etc and country code +1), Billing Address (Street/City/State/Postal/Country), Shipping Address
- **Details section:** Type (dropdown), Industry (dropdown), Description (textarea)
- **Right panel:** Assigned User (select), Teams (multi-select)

### Detail View
- Edit button + three-dot menu for actions
- Overview and Details sections showing all field values
- **Tabbed bottom panel:** Stream | Account | Support
- **Right sidebar panels:** Assigned User, Teams, Created timestamp, Activities (with quick-add buttons for email/meeting/call), History, Tasks (with + button)
- Star/Follow buttons for personal tracking

## 4. Contacts

**Screenshots:** `06-contacts-list.png`, `07-contact-create-form.png`

### Create Form Fields
- Name with salutation dropdown (Mr./Mrs./etc), First Name, Last Name (required)
- Accounts (link/relationship field with search)
- Email (multi-value), Phone (with type: Mobile default, country code)
- Address (single address vs Account's billing/shipping)
- Description
- Right panel: Assigned User, Teams

## 5. Leads

**Screenshots:** `08-leads-list.png`, `09-lead-create-form.png`

### Create Form Fields
- **Overview:** Name (salutation/first/last), Account Name (text, not linked), Email, Phone, Title, Website, Address
- **Details:** Status (dropdown, default: "New"), Source (dropdown), Opportunity Amount (currency with USD), Campaign (link), Industry (dropdown), Description
- Right panel: Assigned User, Teams

### Key Observation
Leads are separate from Contacts/Accounts. There is a "Convert Lead" layout in the Layout Manager, indicating a built-in lead-to-contact/account conversion workflow.

## 6. Opportunities (Pipeline) -- KEY FOR PIPELINQ COMPARISON

**Screenshots:** `10-opportunities-list.png`, `11-opportunities-kanban-pipeline.png`, `12-opportunity-create-form.png`

### List View
- Standard list + **Kanban toggle** (two icons top-right: list and bar chart)
- Filter dropdown with search

### Kanban / Pipeline View
- **5 color-coded stages:** Prospecting (gray), Qualification (gray), Proposal (blue), Negotiation (orange), Closed Won (green)
- Each column has a + button for quick-create
- Cards are draggable between columns (standard Kanban behavior)
- Note: "Closed Lost" is NOT shown as a Kanban column (filtered out, only active stages shown)
- The stages are configurable via Entity Manager > Opportunity > Stage field

### Create Form Fields
- Name (required), Account (link), Stage (dropdown: Prospecting default), Amount (required, currency with USD), Probability % (auto-set: 10 for Prospecting), Close Date (required), Contacts (multi-link), Lead Source (dropdown), Description
- Right panel: Assigned User, Teams

### Pipeline Comparison Notes (vs Pipelinq)
- EspoCRM has a **fixed entity model** for pipeline (Opportunity with Stage field)
- Pipelinq uses a **generic pipeline model** that can be applied to any entity type
- EspoCRM's Kanban is available on Opportunities and Tasks; Pipelinq's pipeline views are available on any register/schema
- EspoCRM has built-in probability % and weighted amount; useful for sales forecasting
- EspoCRM pipeline stages are an Enum field -- stages can be customized in Entity Manager but the structure is fixed

## 7. Calendar

**Screenshots:** `13-calendar-view.png`, `14-calendar-month-view.png`

### Views Available
- **Month:** Full month grid with week numbers, today highlighted
- **Week:** 7-day view with hourly time slots (00:00-23:00), current time indicator (red line)
- **Timeline:** Available as third option (not tested)
- Navigation: Previous/Next arrows, "Today" button
- "..." menu likely contains additional calendar options (shared calendars, etc.)

### Calendar Integration
- Shows Meetings, Calls, and Tasks on the calendar
- Click on time slot to create new event
- Color coding by event type

## 8. Emails

**Screenshot:** `15-emails-view.png`

### Built-in Email Client
- Full email client with folder sidebar: **Inbox, Important, Sent, Archive, Drafts, Trash**
- **Compose** button (prominent red) for creating new emails
- Search bar for email search
- "All" view + "..." menu for additional filters
- Emails are linked to CRM records (Accounts, Contacts, Leads, Opportunities)

### Email Configuration (from Admin panel)
- Outbound Emails (SMTP)
- Inbound Emails (IMAP for group accounts)
- Group Email Accounts
- Personal Email Accounts
- Email Filters
- Email Templates
- Group Email Folders

## 9. Meetings, Calls, Tasks

**Screenshots:** `16-meetings-list.png`, `17-tasks-list.png`

- Meetings: Standard list with Create Meeting button
- Calls: Same pattern
- Tasks: Standard list + **Kanban toggle** (same as Opportunities)
- All three are "Activity" entities that appear on Calendar and in entity-related Activity panels

## 10. Cases (Support)

**Screenshot:** `18-cases-list.png`

- Standard list view for support case tracking
- Create Case button
- Part of the "Support" category alongside Knowledge Base

## 11. Knowledge Base

**Screenshot:** `30-knowledge-base.png`

- Article-based knowledge base with categories (Category Tree type)
- Create Article button
- Can be exposed through Portal for self-service support

## 12. Administration Panel

**Screenshot:** `19-admin-panel.png`

### System Section
- Settings, User Interface, Authentication, Scheduled Jobs, Currency, Notifications, Integrations, Extensions, System Requirements, Job Settings, Upgrade, Clear Cache, Rebuild

### Users Section
- Users, Teams, Roles, Auth Log, Auth Tokens, Action History, API Users

### Customization Section (VERY STRONG)
- **Entity Manager:** Create/edit custom entities, manage fields and relationships
- **Layout Manager:** Customize all view layouts (list, detail, edit, search, mass update, Kanban, side panels)
- **Label Manager:** Customize all UI labels/translations
- **Template Manager:** Message templates

### Messaging Section
- Outbound/Inbound Emails, Group/Personal Email Accounts, Email Filters, Email Folders, Email Templates, SMS

### Portal Section
- Portals, Portal Users, Portal Roles
- Allows creating customer-facing portals

### Setup Section
- Working Time Calendars, Layout Sets, Dashboard Templates, **Lead Capture** (web forms/API endpoints), **PDF Templates**, **Webhooks**, Address Countries, Authentication Providers

### Data Section
- Import (CSV), Attachments, Jobs, Email Addresses, Phone Numbers, App Secrets, **OAuth Providers**, App Log

### Misc
- **Formula Sandbox** -- scripting engine for business logic

### Official Extensions (Paid)
- **Advanced Pack:** Reports, Workflows, Business Process Management (BPM)
- **Sales Pack:** Products/pricing, Quotation/invoicing, Purchases, Subscriptions, Inventory management, Payments
- **Project Management:** Projects, Tasks/milestones, Kanban boards, Gantt chart
- **Google Integration, Outlook Integration, Meeting Scheduler, Zoom Integration, VoIP Integration, MailChimp Integration, Stripe Integration, Real Estate**

## 13. Entity Manager (Deep Dive)

**Screenshots:** `20-entity-manager.png`, `21-opportunity-entity-config.png`, `22-opportunity-fields.png`, `23-add-field-types.png`

### Entities
- 15+ clickable/editable entities (Account, Call, Campaign, Case, Contact, Document, Email, Knowledge Base Article, Lead, Meeting, Note, Opportunity, Target List, Task, User)
- 50+ total system entities visible
- **"Create Entity" button** -- allows creating entirely new custom entities

### Entity Configuration (per entity)
- General/Details tabs
- **Fields:** Full field management with types
- **Relationships:** Define links between entities
- **Layouts:** Customize all layouts for the entity
- **Formula:** Server-side scripting for business logic

### Available Field Types (23 types)
Address, Array, Attachment Multiple, Auto-increment, Barcode, Boolean, Checklist, Currency, Date, Date-Time, Decimal, Enum, File, Float, Foreign, Image, Integer, Multi-Enum, Number (auto-increment), Text, Url, Url Multiple, Varchar, Wysiwyg

## 14. Layout Manager

**Screenshot:** `24-layout-manager.png`

### Layout Types per Entity (15+ types)
- **List:** Column layout for list views
- **Detail:** Field layout for detail/read view
- **List (Small):** Compact list for related panels
- **Detail (Small):** Compact detail for popups
- **Side Panel Fields:** Fields shown in right sidebar
- **Bottom Panels:** Configure which related panels show at bottom
- **Search Filters:** Configure available search/filter fields
- **Mass Update:** Which fields available for bulk edit
- **Side Panels (Detail/Edit/Detail Small/Edit Small):** Fine-grained side panel control
- **Kanban:** Configure Kanban card layout
- **Convert Lead:** Field mapping for lead conversion
- **List (for Account/Contact):** Contextual list layouts when viewing from related entity

## 15. Roles & Permissions (RBAC)

**Screenshots:** `25-roles-list.png`, `26-role-create-form.png`

### Global Permissions (12 types)
Export, Mention, Assignment, Portal, Group Email Account, User Calendar, Mass Update, Follower Management, Message, Audit, User, Data Privacy

### Scope Level (per entity)
Matrix of: **Access** (enabled/disabled/team/own) x **Create/Read/Edit/Delete/Stream**

### Field Level (per entity)
Per-field **Read/Edit** permissions -- extremely granular

## 16. Integrations

**Screenshot:** `27-integrations.png`

### Free (built-in)
- Google Maps, Google reCAPTCHA

### Paid Extensions
- Google (Calendar, Contacts, Gmail), Outlook (Calendar, Contacts, Email), Zoom, VoIP, MailChimp, Stripe

## 17. Formula Sandbox (Scripting)

**Screenshot:** `29-formula-sandbox.png`

- Server-side formula language for business logic
- Code editor with line numbers
- Target Type selector (context for testing)
- Run button with Output panel
- Used in: Entity Manager > Formula (before-save scripts, calculated fields)

## 18. Lead Capture

**Screenshot:** `28-lead-capture.png`

- Create "Entry Points" that generate API endpoints for web form submissions
- Maps form fields to Lead entity fields
- Supports email confirmation opt-in

---

## Summary: Key Strengths for Pipelinq Comparison

### EspoCRM Strengths
1. **Mature CRM entity model:** Accounts, Contacts, Leads, Opportunities with well-defined relationships
2. **Built-in Kanban pipeline** on Opportunities with color-coded stages and drag-and-drop
3. **Extremely strong customization:** Entity Manager allows creating custom entities with 23 field types, full layout customization, and formula scripting
4. **Built-in email client** with IMAP/SMTP, templates, and CRM record linking
5. **Calendar integration** with Month/Week/Timeline views
6. **Granular RBAC** down to field-level Read/Edit per role
7. **Portal system** for customer-facing self-service
8. **Lead capture** API endpoints for web forms
9. **Activity tracking** with Stream (audit log) on every entity
10. **Lightweight and fast** -- SPA architecture, clean UI, responsive

### EspoCRM Weaknesses (vs Pipelinq vision)
1. **Fixed CRM model:** Pipeline is tied to Opportunity entity; not a generic pipeline system
2. **No BPM/Workflows in free version** -- requires paid Advanced Pack ($49/server/year)
3. **Reports only in paid version** -- no built-in reporting in free tier
4. **Limited integrations in free version** -- just Google Maps/reCAPTCHA
5. **No Nextcloud integration** -- standalone application
6. **Single-purpose pipeline:** Cannot apply pipeline/Kanban views to arbitrary entities without customization
7. **No government/public sector features** -- no NL Design, no ZGW, no WCAG focus
8. **Traditional CRM focus** -- designed for sales teams, not adaptable to process/case management out of the box
9. **No document management** -- basic file attachments only, no document generation beyond PDF templates
10. **Paid extensions required** for many enterprise features (invoicing, project management, BPM)

### Pipelinq Differentiators
- Generic pipeline model applicable to any entity/register
- Nextcloud-native with full integration (files, users, sharing, calendar)
- Government-ready (NL Design, ZGW standards)
- OpenRegister foundation allows dynamic schema creation similar to Entity Manager
- n8n integration for workflows (vs EspoCRM's paid Advanced Pack)
