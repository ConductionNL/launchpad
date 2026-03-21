# Krayin CRM - Complete Browser Walkthrough (Live Instance)

**Version:** 1.2.2
**Tech Stack:** Laravel + MySQL + Vue.js 2 (PHP 7.4.33)
**Docker Image:** webkul/krayin:latest (self-contained with Apache + MySQL)
**Default Login:** admin@example.com / admin123
**Port:** localhost:9012
**Date:** 2026-03-14

---

## 1. Login Page

**URL:** `/admin/login`
**Screenshot:** `01-login-page.png`

**Fields:**
- Email (text input)
- Password (text input)
- "Forgot Password?" link

**Notes:** Clean, centered login form with Krayin branding. Tagline: "Consumers interaction goes smoothly and efficiently". No registration option -- admin-only portal.

---

## 2. Dashboard

**URL:** `/admin/dashboard`
**Screenshot:** `02-dashboard.png`

**Dashboard Cards (date-range filtered, default 30 days):**
- **Leads Over Time** -- bar chart of leads created over time
- **Leads Started** -- leads created in period with drag-to-reorder
- **Top Leads** -- highest-value leads (avatar icon when empty)
- **Activities** -- call/meeting/lunch counts (done/total format: "0 / 0")
- **Pipelines** -- per-pipeline lead counts (shows "Default Pipeline 0 / 0")
- **Emails** -- Total, Inbox, Draft, Outbox, Sent, Trash counts
- **Customers** -- customer chart
- **Top Customers** -- highest-value customers
- **Products** -- product chart
- **Top Products** -- highest-revenue products
- **Quotes** -- quote chart

**Global Actions:**
- Blue "+" FAB button (top center) for quick-create
- User profile dropdown (top right): "Howdy! Example"
- Date range picker (top right of dashboard): "15 Feb 2026 - 14 Mar 2026"

---

## 3. Navigation Sidebar (Left)

**Icons-only sidebar with tooltips:**
1. Dashboard (clock icon)
2. Leads (funnel icon)
3. Quotes (document icon)
4. Mail (envelope icon)
5. Activities (pin/location icon)
6. Contacts (phone icon) -- links to Persons sub-page
7. Products (clipboard icon)
8. Settings (gear icon)
9. Configuration (sliders icon)

---

## 4. Leads

### 4a. Kanban View (Default)
**URL:** `/admin/leads`
**Screenshots:** `03-leads-kanban.png`, `08-lead-created-kanban.png`

**Features:**
- Pipeline selector dropdown (Default Pipeline)
- Toggle: Kanban view / Table view
- Search bar
- Filter panel with date range
- Kanban columns: **New**, **Follow Up**, **Prospect**, **Negotiation**, **Won**, **Lost**
- Each column shows aggregate dollar value
- "Create Lead" button per column
- Lead cards show: Title, Contact Person name (linked), Dollar amount

### 4b. Table View
**URL:** `/admin/leads?view_type=table`
**Screenshot:** `04-leads-table-view.png`

**Features:**
- Export button
- Items per page (10/20/30/40/50)
- Pipeline selector
- Stage filter tabs: All, New, Follow Up, Prospect, Negotiation, Won, Lost
- Search bar + Filter panel

**Filter panel fields:**
- ID (text)
- Sales Person (dropdown: users)
- Subject (text)
- Tags (text)
- Lead source (dropdown: Email, Web, Web Form, Phone, Direct)
- Lead Value (text)
- Contact Person (text)
- Expected Close Date (date range: Start Date to End Date)
- Created Date (date range)

### 4c. Create Lead Form
**URL:** `/admin/leads/create`
**Screenshots:** `05-lead-create-details.png`, `06-lead-create-contact-person.png`, `07-lead-create-products.png`

**Three tabs:**

**Tab 1 - Details:**
- Title * (text)
- Description (textarea)
- Lead Value ($) * (number)
- Source * (dropdown: Email, Web, Web Form, Phone, Direct)
- Type * (dropdown: New Business, Existing Business)
- Sales Owner (dropdown: system users)
- Expected Close Date (date picker)

**Tab 2 - Contact Person:**
- Name * (typeahead search -- searches existing persons, "Add as new" option)
- Email * (text + type: Work/Home, + Add More)
- Contact Numbers (text + type: Work/Home, + Add More)
- Organization (typeahead search for existing organizations)

**Tab 3 - Products:**
- "+ Add More" link to add product line items

### 4d. Lead Detail View
**URL:** `/admin/leads/view/{id}`
**Screenshots:** `09-lead-detail-top.png`, `10-lead-detail-full.png`

**Left panel - Details:**
- Title, Description, Lead Value, Source, Type, Sales Owner, Expected Close Date
- Contact Person section: Name (linked to edit), Email (with type), Contact Numbers (with type), Organization
- Products section

**Right panel - Pipeline Stage Progress:**
- Horizontal stage bar: New > Follow Up > Prospect > Negotiation > Won/Lost
- Arrow navigation (< >) to move between stages
- Created date (relative)

**Action tabs (right panel):**
- **Note** -- textarea + Save button (`11-lead-activity-form.png` area)
- **Activity** -- inline create form:
  - Title * (text)
  - Type * (dropdown: Call, Meeting, Lunch)
  - Schedule * (From date/time, To date/time)
  - Location (text)
  - Description (textarea)
  - Participants (typeahead)
  - Save button
- **Email** (`12-lead-email-form.png`) -- full email composer:
  - To * (chip/tag input with CC/BCC toggle)
  - Subject * (text)
  - Reply * (TinyMCE rich text editor with full toolbar: File, Edit, Insert, View, Format, Table, Tools menus; Bold, Italic, Strikethrough, Link, Horizontal line, Alignment, Lists, Clear formatting, Source code, Table)
  - Add Attachment
  - Send button
- **File** -- file upload
- **Quote** (`13-lead-quote-tab.png`) -- "Create Quote" link

**Activity timeline (below action tabs):**
- Filter tabs: All, Notes, Calls, Meetings, Lunches, Files, Emails, Quote
- Sections: Planned activities, Done activities

**Other actions:**
- Edit button (top right)
- Tags (next to title)

---

## 5. Contacts

### 5a. Persons List
**URL:** `/admin/contacts/persons`
**Screenshot:** `14-contacts-persons-list.png`

**Table columns:** ID, Name, Emails, Contact Numbers, Organization Name, Actions (edit/delete)
**Features:** Search, Items per page, Filter, Export, Create Person

### 5b. Create Person Form
**URL:** `/admin/contacts/persons/create`
**Screenshot:** `15-create-person-form.png`

**Fields:**
- Name * (text)
- Emails * (text + type: Work/Home, + Add More)
- Contact Numbers (text + type: Work/Home, + Add More)
- Organization (typeahead search)

### 5c. Organizations List
**URL:** `/admin/contacts/organizations`
**Screenshot:** `16-organizations-list.png`

**Table columns:** (empty state shown) -- Search, Filter, Create Organization

### 5d. Create Organization Form
**URL:** `/admin/contacts/organizations/create`
**Screenshot:** `17-create-organization-form.png`

**Fields:**
- Name * (text)
- Address:
  - Address textarea
  - Country (dropdown with 250+ countries)
  - State (text)
  - City (text)
  - Postcode (text)

---

## 6. Activities

### 6a. Table View (Default)
**URL:** `/admin/activities`
**Screenshot:** `18-activities-list.png`

**Features:**
- Toggle: Table view / Calendar view
- Type filter tabs: All, Call, Meeting, Lunch
- Time filter: Yesterday, Today, Tomorrow, This Week, This Month, Custom
- Search, Filter, Items per page

### 6b. Calendar View
**URL:** `/admin/activities?view_type=calendar`
**Screenshot:** `19-activities-calendar.png`

**Features:**
- Weekly calendar (Mon-Sun)
- Week navigation (previous/next arrows)
- Hour rows from 12:00am to 11:00pm
- Shows "No Event" when empty
- Current time indicator

---

## 7. Products

### 7a. Products List
**URL:** `/admin/products`
**Screenshot:** `20-products-list.png`

**Features:** Search, Filter, Items per page, Create Product

### 7b. Create Product Form
**URL:** `/admin/products/create`
**Screenshot:** `21-create-product-form.png`

**Fields:**
- Name * (text)
- Description (textarea)
- SKU * (text)
- Quantity * (number)
- Price * (number)

---

## 8. Quotes

### 8a. Quotes List
**URL:** `/admin/quotes`
**Screenshot:** `22-quotes-list.png`

**Features:** Search, Filter, Items per page, Create Quote

### 8b. Create Quote Form
**URL:** `/admin/quotes/create`
**Screenshot:** `23-create-quote-form.png`

**Three collapsible sections:**

**Section 1 - Quote Information:**
- Description (textarea)
- Expired At * (date picker)
- Person * (typeahead search)
- Subject * (text)
- Sales Owner * (dropdown: system users)
- Lead (typeahead search)

**Section 2 - Address Information:**
- Billing Address * (textarea + Country dropdown + State + City + Postcode)
- Shipping Address (textarea + Country dropdown + State + City + Postcode)

**Section 3 - Quote Items:**
- Line item table with columns: Name *, Quantity *, Price ($) *, Amount ($), Discount ($) *, Tax ($) *, Total ($)
- Name is a typeahead search for products
- "+ Add More" for additional line items
- Summary fields: Sub Total ($), Discount ($), Tax ($), Adjustment ($), Grand Total ($)

---

## 9. Mail

### 9a. Inbox
**URL:** `/admin/mail/inbox`
**Screenshot:** `24-mail-inbox.png`

**Features:** Search, Filter, Items per page
**Routes:** inbox, draft, outbox, sent, trash (via sidebar/breadcrumb)

---

## 10. Settings

### 10a. Settings Overview
**URL:** `/admin/settings`
**Screenshot:** `25-settings-overview.png`

**Four categories:**

**User:**
- Groups -- team/department grouping
- Roles -- permission roles
- Users -- CRM user management

**Lead:**
- Pipelines -- sales pipeline configuration
- Sources -- lead source categories
- Types -- lead type categories

**Automation:**
- Attributes -- custom field definitions
- Email Templates -- notification templates
- Workflows -- automated actions

**Other Settings:**
- Web Forms -- embeddable lead capture forms
- Tags -- label/categorization system

### 10b. Pipelines
**URL:** `/admin/settings/pipelines`
**Screenshot:** `26-settings-pipelines.png`

**Table columns:** ID, Name, Rotten Days, Is Default, Actions
**Default:** "Default Pipeline" with 30 rotten days

### 10c. Create Pipeline Form
**URL:** `/admin/settings/pipelines/create`
**Screenshot:** `27-create-pipeline-form.png`

**Fields:**
- Name * (text)
- Rotting Days * (number, default 30)
- Mark as Default (checkbox)
- Stage table (draggable rows):
  - Name * (text)
  - Probability (%) * (number)
  - Delete button per row
  - Default stages: New (100%), [blank] (100%), Won (100%), Lost (0%)
  - "Add Stage" button

### 10d. Groups
**URL:** `/admin/settings/groups`
**Screenshot:** `28-settings-groups.png`

**Features:** Search, Filter, Create Group

### 10e. Roles
**URL:** `/admin/settings/roles`
**Screenshot:** `29-settings-roles.png`

**Table columns:** ID, Name, Description, Permission Type, Actions
**Default:** "Administrator" role with permission type "all"

### 10f. Users
**URL:** `/admin/settings/users`
**Screenshot:** `37-settings-users.png`

**Table columns:** ID, Name (with avatar), Email, Status, Created Date, Actions
**Default:** "Example Admin", admin@example.com, Active, 20 Jan 2023

### 10g. Attributes
**URL:** `/admin/settings/attributes`
**Screenshot:** `30-settings-attributes.png`

**Table columns:** ID, Code, Name, Entity Type, Type, Actions
**Entity type tabs:** All, Leads, Persons, Organizations, Products, Quotes
**Pre-configured attributes visible:** person_id (lookup), expired_at (date), grand_total (price) -- all Quotes entity type
**Attribute types seen:** lookup, date, price

### 10h. Email Templates
**URL:** `/admin/settings/email-templates`
**Screenshot:** `35-email-templates.png`

**Table columns:** ID, Name, Subject, Actions
**Pre-configured templates:**
1. "Activity created" -- Subject: "Activity created: {%activities.title%}"
2. "Activity modified" -- Subject: "Activity modified: {%activities.title%}"

### 10i. Workflows
**URL:** `/admin/settings/workflows`
**Screenshot:** `31-settings-workflows.png`

**Table columns:** ID, Name, Actions
**Pre-configured workflows:**
1. "Emails to participants after activity creation"
2. "Emails to participants after activity updation"

### 10j. Web Forms
**URL:** `/admin/web-forms`
**Screenshot:** `32-web-forms.png`

**Features:** Search, Filter, Create Web Form (generates embeddable HTML)

### 10k. Sources
**URL:** `/admin/settings/sources`
**Screenshot:** `33-settings-sources.png`

**Table columns:** ID, Name, Actions
**Pre-configured sources:** Direct, Phone, Web Form, Web, Email (5 total)

### 10l. Types
**URL:** `/admin/settings/types`
**Screenshot:** `34-settings-types.png`

**Table columns:** ID, Name, Actions
**Pre-configured types:** Existing Business, New Business

### 10m. Tags
**URL:** `/admin/settings/tags`
**Screenshot:** `38-settings-tags.png`

**Features:** Search, Filter, Create Tag (modal-based creation)

---

## 11. Configuration

**URL:** `/admin/configuration/general`
**Screenshot:** `36-configuration-general.png`

**Left sidebar:** General (only section)
**Tab:** Locale Settings
**Fields:**
- Locale (dropdown: English, Turkce, Arabic)

---

## 12. CRUD Test Results

### Lead Creation (Successful)
- Created lead "Test Lead - Website Redesign" with:
  - Value: $15,000.00
  - Source: Web
  - Type: New Business
  - Sales Owner: Example Admin
  - Contact: John Doe (john.doe@example.com, +31612345678)
- Lead appeared in "New" column of kanban view
- Lead detail view accessible at `/admin/leads/view/1`
- Pipeline stage bar shows New > Follow Up > Prospect > Negotiation > Won/Lost
- Contact person was auto-created in Persons list

---

## 13. Key Observations for Pipelinq Comparison

### Strengths
1. **Kanban + Table dual view** for leads -- well-executed toggle
2. **Rich lead detail page** with inline note/activity/email/file/quote creation
3. **Pipeline stage visualization** with progress bar and arrow navigation
4. **Comprehensive quote system** with line items, tax, discount, adjustment calculations
5. **Activity calendar** with weekly view
6. **Custom attributes** system with entity-type filtering (Leads, Persons, Orgs, Products, Quotes)
7. **Workflow automation** with pre-configured email notification workflows
8. **Web Forms** for lead capture (embeddable)
9. **Email integration** built-in with rich text composer (TinyMCE)
10. **Typeahead search** for contact/organization/product lookups

### Weaknesses / Gaps
1. **No deal/opportunity entity** -- leads serve dual purpose
2. **No reporting/analytics** page beyond dashboard cards
3. **No custom views/saved filters**
4. **No import/data migration** tools visible in UI
5. **Single pipeline visible** at a time (no multi-pipeline dashboard)
6. **No task management** -- only activities (calls, meetings, lunches)
7. **No document generation/PDF export** for quotes
8. **Configuration is minimal** -- only locale settings
9. **No API documentation** or integrations page
10. **No calendar sync** (Google Calendar, Outlook) in this version
11. **Old tech stack** -- PHP 7.4 (EOL), Vue 2 (legacy)
12. **No mobile-responsive design** observed (fixed-width layout)
13. **Lead rotting** configured but no visual indicator in kanban
14. **No contact deduplication** tools

### Data Model
- **Leads** -- central entity, linked to pipeline stages
- **Persons** -- contact individuals (name, emails[], phones[], organization)
- **Organizations** -- companies (name, address)
- **Products** -- catalog items (name, description, SKU, quantity, price)
- **Quotes** -- proposals with line items, billing/shipping addresses
- **Activities** -- calls, meetings, lunches (scheduled events)
- **Emails** -- integrated email (inbox/draft/outbox/sent/trash)
- **Tags** -- labels for categorization
- **Pipelines** -- configurable stage sequences with probability %
- **Sources** -- lead origin tracking
- **Types** -- business type classification
- **Workflows** -- automated actions triggered by events
- **Email Templates** -- notification templates with variable interpolation
- **Web Forms** -- embeddable lead capture forms
- **Users/Roles/Groups** -- RBAC access control

---

## 14. Screenshots Index (38 total)

| # | File | Page |
|---|------|------|
| 01 | `01-login-page.png` | Login page |
| 02 | `02-dashboard.png` | Dashboard (full page) |
| 03 | `03-leads-kanban.png` | Leads kanban view (empty) |
| 04 | `04-leads-table-view.png` | Leads table view with filters |
| 05 | `05-lead-create-details.png` | Create lead - Details tab |
| 06 | `06-lead-create-contact-person.png` | Create lead - Contact Person tab |
| 07 | `07-lead-create-products.png` | Create lead - Products tab |
| 08 | `08-lead-created-kanban.png` | Kanban with created lead card |
| 09 | `09-lead-detail-top.png` | Lead detail view (top) |
| 10 | `10-lead-detail-full.png` | Lead detail view (full page) |
| 11 | `11-lead-activity-form.png` | Lead detail - Activity form |
| 12 | `12-lead-email-form.png` | Lead detail - Email composer |
| 13 | `13-lead-quote-tab.png` | Lead detail - Quote tab |
| 14 | `14-contacts-persons-list.png` | Persons list with data |
| 15 | `15-create-person-form.png` | Create person form |
| 16 | `16-organizations-list.png` | Organizations list (empty) |
| 17 | `17-create-organization-form.png` | Create organization form |
| 18 | `18-activities-list.png` | Activities table view |
| 19 | `19-activities-calendar.png` | Activities calendar view |
| 20 | `20-products-list.png` | Products list (empty) |
| 21 | `21-create-product-form.png` | Create product form |
| 22 | `22-quotes-list.png` | Quotes list (empty) |
| 23 | `23-create-quote-form.png` | Create quote form (full page) |
| 24 | `24-mail-inbox.png` | Mail inbox (empty) |
| 25 | `25-settings-overview.png` | Settings overview page |
| 26 | `26-settings-pipelines.png` | Pipelines list |
| 27 | `27-create-pipeline-form.png` | Create pipeline form with stages |
| 28 | `28-settings-groups.png` | Groups list (empty) |
| 29 | `29-settings-roles.png` | Roles list (Administrator) |
| 30 | `30-settings-attributes.png` | Attributes list with entity tabs |
| 31 | `31-settings-workflows.png` | Workflows list (2 pre-configured) |
| 32 | `32-web-forms.png` | Web Forms list (empty) |
| 33 | `33-settings-sources.png` | Sources list (5 pre-configured) |
| 34 | `34-settings-types.png` | Types list (2 pre-configured) |
| 35 | `35-email-templates.png` | Email templates list (2 pre-configured) |
| 36 | `36-configuration-general.png` | Configuration - locale settings |
| 37 | `37-settings-users.png` | Users list (Example Admin) |
| 38 | `38-settings-tags.png` | Tags list (empty) |
