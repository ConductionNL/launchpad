# BottleCRM (Django-CRM by MicroPyramid) - Browser Walkthrough Notes

**Date:** 2026-03-14
**Version:** Latest from `main` branch
**Tech Stack:** Django 5 + SvelteKit 2 + PostgreSQL 16 + Redis 7 + Celery
**GitHub:** https://github.com/MicroPyramid/Django-CRM
**Port:** Backend on :9010, Frontend on :9011

---

## 1. Architecture Overview

BottleCRM is a modern, open-source CRM with a decoupled architecture:
- **Backend:** Django 5 REST API with PostgreSQL Row-Level Security (RLS) for multi-tenancy
- **Frontend:** SvelteKit 2 with Tailwind CSS 4, bits-ui components, svelte-dnd-action
- **Task Queue:** Celery + Redis for async tasks (emails, etc.)
- **Auth:** Passwordless magic-link authentication + Google OAuth
- **PDF:** WeasyPrint for invoice/estimate PDF generation
- **Mobile:** Mobile app directory present (SvelteKit-based)

### Multi-tenancy Model
- Users belong to **Organizations** (Org model)
- All data is scoped to an organization via PostgreSQL RLS
- Users can switch between organizations
- Roles: admin, user (per org)

---

## 2. Authentication Flow

### Login Page (`/login`)
**Screenshot:** `01-login-page.png`

- Clean, minimal login page with BottleCRM logo
- **Two auth methods:**
  1. "Continue with Google" (OAuth 2.0 with PKCE)
  2. Email-based magic link (passwordless)
- **Fields:** Email address input
- **Flow:** Enter email -> receive magic link via email -> click link -> authenticated
- No traditional password-based login
- Footer links: Privacy Policy, Terms of Service, GitHub

### Organization Selection (`/org`)
**Screenshot:** `02-org-select.png`

- After authentication, user must select an organization
- Shows list of organizations the user belongs to
- Each org card shows: org name, user role (admin/member), icon
- "Create new organization" link
- "Sign out" option in header

### Create Organization (`/org/new`)
**Screenshot:** `03-create-org.png`

- **Fields:**
  - Organization name (text, required, placeholder: "e.g. Acme Inc.")
- Helper text: "This will be your workspace name in BottleCRM"
- "Back" link returns to org selection

---

## 3. Dashboard (`/`)
**Screenshots:** `05-dashboard.png`, `05b-dashboard-full.png`

The dashboard is a comprehensive sales overview with these sections:

### Header
- Greeting: "Good morning" (time-based)
- Title: "Dashboard"
- Description: "Here's what's happening with your CRM today..."

### Today's Focus Bar
- Due Today count (links to `/tasks?filter=today`)
- Follow-ups count (links to `/leads?filter=followup_today`)

### Sales Pipeline Visualization
- Horizontal pipeline stages: Prospecting -> Qualification -> Proposal -> Negotiation -> Closed Won/Lost
- Each stage shows: count of deals, total dollar value
- Color-coded stage indicators (orange, blue, purple, yellow, green/red)
- Labeled "USD only" with currency awareness

### Key Metrics Cards (2x2 grid)
- **Pipeline Value** (total open pipeline, USD)
- **Weighted Pipeline** (probability-adjusted value)
- **Won This Month** (closed-won revenue)
- **Conversion Rate** (percentage)

### Pipeline by Stage (bar chart)
- Breakdown by stage with progress bars
- Total Open Pipeline sum

### Hot Leads Widget
- Shows leads marked as "Hot" rating
- "View all" links to `/leads?rating=HOT`
- Empty state: "Mark leads as 'Hot' to see them here"

### My Tasks Widget
- Tab filters: All, Overdue, Today, Week
- Task list with status indicators
- "View all" links to `/tasks`

### My Opportunities Widget
- List of assigned open opportunities
- "View all" links to `/opportunities`

### Goal Progress Widget
- Active sales goals with progress
- "Create a goal" link

### Recent Activity Feed
- Chronological activity log
- Shows: action description, user, timestamp
- Groups by date (Today, Yesterday, etc.)
- "View all" links to `/activities`

---

## 4. Leads Module (`/leads`)
**Screenshots:** `06-leads-list-empty.png`, `09-leads-list-with-data.png`, `10-leads-kanban.jpeg`

### List View (Table)
- **Header:** "Leads" title with count ("1 of 1 leads")
- **View Toggle:** Table | Kanban
- **Status Filters:** All, Open, Lost (with counts)
- **Toolbar:** Filters button, Columns selector (7/12 available), "New Lead" button
- **Table Columns (configurable, 12 total):**
  - Checkbox (bulk select)
  - Title
  - Name (first + last)
  - Company
  - Email
  - Status (dropdown inline edit with badge)
  - Rating (inline rating stars/icons)
  - Created date
  - (Hidden by default: Phone, Source, Industry, Deal Value, Currency)
- **Pagination:** Rows per page selector (10/25/50/100), page navigation

### Kanban View
- **Columns by lead status:** Assigned, In Process, (and other pipeline stages)
- **Cards show:** Lead title, company name with icon
- **Column footer:** Lead count per column
- **Drag-and-drop** support via svelte-dnd-action

### New Lead Form (slide-out sheet from right)
**Screenshots:** `07-new-lead-form.png`, `08-new-lead-form-expanded.png`

**Primary Fields (always visible):**
| Field | Type | Notes |
|-------|------|-------|
| Lead Title | Text input | e.g., "Enterprise Deal" |
| First Name | Text input | |
| Last Name | Text input | |
| Email | Email input | |
| Phone | Text input | With phone validation |
| Company | Text input | |
| Status | Dropdown | Assigned, In Process, Converted, Recycled, Dead |
| Deal Value | Number (spinner) | Default: 0 |
| Currency | Searchable dropdown | All ISO currency codes |
| Country | Searchable dropdown | All countries |

**Extended Fields (18 more, collapsed by default):**
| Field | Type | Notes |
|-------|------|-------|
| Salutation | Dropdown | Mr, Mrs, Ms, Dr |
| Job Title | Text input | |
| Website | URL input | |
| LinkedIn | URL input | |
| Rating | Dropdown | Hot, Warm, Cold |
| Source | Dropdown | Call, Email, Existing Customer, Partner, etc. |
| Industry | Dropdown | All industries |
| Probability | Number (spinner) | 0-100% |
| Follow-up | Date picker | Calendar popup |
| Address | Text input | Street address |
| City | Text input | |
| State | Text input | |
| Postal Code | Text input | |
| Notes | Textarea | |
| Assigned To | Multi-select dropdown | Users in org |
| Teams | Multi-select dropdown | Teams in org |
| Contacts | Multi-select dropdown | Existing contacts |
| Tags | Multi-select dropdown | Tag management |

**Actions:** Cancel, Create Lead

### Lead Pipeline (Backend Model)
- LeadPipeline: name, stages, default
- LeadStage: name, order, color, pipeline FK
- Supports custom pipelines and stages

---

## 5. Contacts Module (`/contacts`)
**Screenshots:** `11-contacts-list-empty.jpeg`, `12-new-contact-form.jpeg`, `13-contacts-list-with-data.jpeg`

### List View (Table)
- **Header:** "Contacts" with count
- **Toolbar:** Filters, Columns (6/7), "New Contact" button
- **Table Columns (7 total):**
  - Checkbox
  - Contact (full name)
  - Company
  - Title (job title)
  - Email
  - Phone
  - Created date

### New Contact Form (slide-out sheet)
| Field | Type | Notes |
|-------|------|-------|
| First Name | Text input | Required |
| Last Name | Text input | |
| Email | Email input | |
| Phone | Phone input | With validation |
| Company | Text input | |
| Job Title | Text input | |
| Department | Text input | |
| Do Not Call | Toggle/switch | Boolean |
| LinkedIn | URL input | |
| Address | Text input | |
| City | Text input | |
| State | Text input | |
| Postal Code | Text input | |
| Country | Searchable dropdown | |
| Tags | Multi-select | |
| Notes | Textarea | |

**Actions:** Cancel, Create Contact

### Contact Model (Backend)
- FK to Account (optional)
- Linked to leads, opportunities, cases, tasks
- Do Not Call flag

---

## 6. Accounts Module (`/accounts`)
**Screenshot:** `14-accounts-list.jpeg`

### List View (Table)
- **Header:** "Accounts" with count
- **Status Filters:** All, Active, Closed
- **Toolbar:** Filters, Columns (5/6), "New Account" button

### Account Model Fields
| Field | Type | Notes |
|-------|------|-------|
| Account Name | Text | Required |
| Email | Email | |
| Phone | Phone | With validation |
| Website | URL | |
| Industry | Dropdown | Standard industry list |
| Number of Employees | Integer | |
| Annual Revenue | Decimal | |
| Currency | Dropdown | ISO codes |
| Address/City/State/PostCode/Country | Text/Dropdown | Flat address fields |
| Status | Dropdown | Active, Closed |
| Assigned To | Multi-select | Users |
| Teams | Multi-select | Teams |
| Tags | Multi-select | Tags |
| Notes | Textarea | |

---

## 7. Deals/Opportunities Module (`/opportunities`)
**Screenshot:** `15-deals-list.jpeg`

### List View (Table)
- **Header:** "Opportunities" with pipeline value display
- **Status Filters:** All, Open, Won, Lost (with counts)
- **Toolbar:** Filters, Columns (6/9), "New" button

### Opportunity Model Fields
| Field | Type | Notes |
|-------|------|-------|
| Opportunity Name | Text | Required |
| Account | FK dropdown | Link to account |
| Stage | Dropdown | Prospecting, Qualification, Proposal, Negotiation, Closed Won, Closed Lost |
| Type | Dropdown | New Business, Existing Business |
| Currency | Dropdown | ISO codes |
| Amount | Decimal | Deal value |
| Amount Source | Radio | Manual / Calculated from Products |
| Probability | Integer | 0-100% |
| Expected Close Date | Date | |
| Lead Source | Dropdown | Same as lead sources |
| Description | Textarea | |
| Contacts | Multi-select | |
| Assigned To | Multi-select | |
| Teams | Multi-select | |
| Tags | Multi-select | |

### Opportunity Line Items (Products)
| Field | Type |
|-------|------|
| Product | FK to Product |
| Description | Text |
| Quantity | Decimal |
| Unit Price | Decimal |
| Discount | Decimal |
| Discount Type | Percentage/Fixed |
| Tax Rate | Decimal |

### Stage Aging Config
- Tracks how long opportunities stay in each stage
- Warning thresholds for stale deals

### Sales Goals
| Field | Type | Notes |
|-------|------|-------|
| Name | Text | Goal name |
| Goal Type | Dropdown | Revenue, Deals Won, New Accounts, etc. |
| Target Value | Decimal | |
| Current Value | Decimal | Auto-calculated |
| Period Type | Dropdown | Weekly, Monthly, Quarterly, Yearly |
| Start/End Date | Date | |
| Assigned To | FK | |

---

## 8. Goals Module (`/goals`)
**Screenshot:** `16-goals.jpeg`

- **Title:** "Sales Goals - Track targets and measure team performance"
- **Tabs:** All, Active, Completed, Needs Attention
- **Search:** Search goals input field
- **Empty state:** "No goals set yet. Create your first sales goal to start tracking performance."

---

## 9. Cases/Tickets Module (`/cases`)
**Screenshot:** `17-tickets-list.jpeg`

### List View
- **Header:** "Cases" with count
- **Status Filters:** All, Open, Closed
- **View Toggle:** List and Kanban views (icons visible)

### Case Model Fields
| Field | Type | Notes |
|-------|------|-------|
| Name | Text | Required, max 64 chars |
| Status | Dropdown | New, Assigned, Pending, Closed, Rejected, Duplicate |
| Priority | Dropdown | Low, Normal, Medium, High, Critical |
| Case Type | Dropdown | Question, Incident, Problem, Feature Request, Enhancement |
| Account | FK dropdown | |
| Contacts | Multi-select | |
| Closed On | Date | |
| Description | Textarea | |
| Assigned To | Multi-select | |
| Teams | Multi-select | |
| Tags | Multi-select | |
| Is Active | Boolean | |

### SLA Tracking
| Field | Type | Notes |
|-------|------|-------|
| First Response At | DateTime | Tracked automatically |
| Resolved At | DateTime | |
| SLA First Response (hours) | Integer | Default: 4 |
| SLA Resolution (hours) | Integer | Default: 24 |

### Case Pipeline (Kanban)
- CasePipeline: name, stages
- CaseStage: name, order, color
- Cases have `pipeline` and `stage` FK fields

### Solutions
| Field | Type |
|-------|------|
| Case | FK |
| Title | Text |
| Description | Textarea |
| Status | Pending, Approved, Rejected |

---

## 10. Tasks Module (`/tasks`)
**Screenshot:** `18-tasks-list.jpeg`

### Three View Modes
1. **List View** - Table with columns
2. **Board View** (`/tasks/board`) - Kanban boards (returned 404, likely needs board creation)
3. **Calendar View** (`/tasks/calendar`) - Calendar display (returned 500 "fetch failed")

### List View
- **Header:** "Tasks" with count
- **Status Filters:** All, Active, Completed (with counts)
- **Toolbar:** Filters, Columns (6/10), view toggle (List/Board/Calendar)

### Task Model Fields
| Field | Type | Notes |
|-------|------|-------|
| Title | Text | Required |
| Status | Dropdown | New, In Progress, Completed |
| Priority | Dropdown | Low, Medium, High |
| Due Date | DateTime | |
| Account | FK | Optional |
| Contacts | Multi-select | |
| Assigned To | Multi-select | |
| Teams | Multi-select | |
| Tags | Multi-select | |

### Board System (Kanban)
- **Board:** name, description, owner, members, archived flag
- **Board Members:** profile, role (owner/admin/member)
- **Board Columns:** name, order, color, WIP limit
- **Board Tasks:** task FK, column FK, position (for ordering)

### Task Pipeline
- TaskPipeline: name, stages
- TaskStage: name, order, color

---

## 11. Invoicing Module (`/invoices`)

### Sub-pages
- `/invoices` - Invoice list
- `/invoices/new` - Create new invoice
- `/invoices/[id]` - Invoice detail
- `/invoices/estimates` - Estimates
- `/invoices/products` - Product catalog
- `/invoices/recurring` - Recurring invoices
- `/invoices/templates` - Invoice templates
- `/invoices/reports` - Invoice reports

### Invoice List
**Screenshot:** `22-invoices-list.jpeg`
- **Status Filters:** All, Open, Paid, Overdue (with counts)
- **Empty state:** "No invoices yet. Create your first invoice to get started."
- Create Invoice button (orange)

### Invoice Model Fields
| Field | Type | Notes |
|-------|------|-------|
| Invoice Number | Auto-generated | Prefix + sequential |
| Title | Text | |
| Status | Dropdown | Draft, Sent, Viewed, Paid, Partially Paid, Overdue, Pending, Cancelled |
| Account | FK | |
| Contact | FK | |
| Opportunity | FK | Optional link to deal |
| Currency | Dropdown | |
| Amount Due | Decimal | |
| Amount Paid | Decimal | |
| Due Date | Date | |
| Payment Terms | Dropdown | Due on Receipt, Net 15/30/45/60, Custom |
| Tax Rate | Decimal | |
| Discount | Decimal | |
| Discount Type | Percentage/Fixed | |
| Notes | Textarea | |
| Terms & Conditions | Textarea | |
| Billing Period Start/End | Date | |
| PO Number | Text | |
| Template | FK | Invoice template |
| Assigned To | Multi-select | |
| Teams | Multi-select | |
| Portal Token | Auto-generated | For client portal access |
| Reminder settings | Multiple fields | Auto-reminders |

### Invoice Line Items
| Field | Type |
|-------|------|
| Name | Text |
| Product | FK (optional) |
| Description | Text |
| Quantity | Decimal |
| Unit Price | Decimal |
| Discount | Decimal |
| Discount Type | Percentage/Fixed |
| Tax Rate | Decimal |

### Payments
| Field | Type |
|-------|------|
| Invoice | FK |
| Amount | Decimal |
| Date | Date |
| Method | Dropdown (Cash, Check, Credit Card, Bank Transfer, PayPal, Stripe, Other) |
| Reference | Text |
| Notes | Textarea |

### Estimates
- Similar to invoices but with estimate-specific statuses
- Status: Draft, Sent, Viewed, Accepted, Declined, Expired
- Valid Until date field
- Can convert estimate to invoice

### Products
| Field | Type |
|-------|------|
| Name | Text |
| Description | Textarea |
| Price | Decimal |
| Currency | Dropdown |
| SKU | Text |
| Tax Rate | Decimal |
| Is Active | Boolean |

### Recurring Invoices
| Field | Type |
|-------|------|
| Frequency | Dropdown (Weekly, Bi-weekly, Monthly, Quarterly, Semi-annually, Yearly, Custom) |
| Start/End Date | Date |
| Next Invoice Date | Date (auto-calculated) |
| Auto-send | Boolean |
| Is Active | Boolean |

### Invoice Templates
| Field | Type |
|-------|------|
| Template Name | Text |
| Logo | Image upload |
| Primary Color | Color picker |
| Secondary Color | Color picker |
| Custom HTML | Textarea |
| Company details | Multiple text fields |
| Footer text | Textarea |
| Is Default | Boolean |

---

## 12. Users & Teams (`/users`)
**Screenshot:** `29-users.jpeg`

### Two Tabs: Users | Teams

### Add New Member
- **Fields:**
  - Email Address (required)
  - Role dropdown (User, Admin)
- "Add Member" button (orange)

### Team Members Section
- Shows existing members with count
- Role badges

---

## 13. Settings

### Organization Settings (`/settings/organization`)
**Screenshot:** `31-settings-org.jpeg`
- **Title:** "Organization Settings"
- "Save Changes" button
- Manages organization preferences

### Tags (`/settings/tags`)
**Screenshot:** `32-settings-tags.jpeg`
- **Title:** "Tags - Create and manage tags to organize contacts, companies, deals, and tickets"
- "Create tag" button (orange)
- **Tabs:** Active, Archived (with counts)
- **Search:** Search tags input
- Tags have: name, color, description, is_active flag

### Salesforce Import (`/settings/salesforce`)
- Import data from Salesforce
- Sub-page: `/settings/salesforce/import` for import progress

### General Settings (`/settings`)
- Returned 404 -- this is a redirect/index page, actual settings are under sub-routes

---

## 14. Profile (`/profile`)
- User profile management
- Profile model fields: role, phone, alternate_phone, address, profile picture
- Could not capture screenshot (browser crash on this page)

---

## 15. Help & Support (`/support`)
**Screenshot:** `21-help-desk.jpeg`

- **Banner:** "OPEN SOURCE - FOREVER FREE"
- **Title:** "Help & Support"
- Description: "Join thousands of businesses using BottleCRM..."

### Sections:
1. **Our Mission** - Open source, free, self-hostable CRM
2. **Join the Community** - GitHub link, open source, active community
3. Feature request and documentation links

---

## 16. Portal (Public-facing)

### Invoice Portal (`/portal/invoice/[token]`)
- Public page for clients to view/pay invoices
- Token-based access (no login required)
- PDF download capability

### Estimate Portal (`/portal/estimate/[token]`)
- Public page for clients to view/accept/decline estimates
- Token-based access

---

## 17. Navigation Structure

### Sidebar (always visible)
**CRM Section:**
- Dashboard (`/`)
- Leads (`/leads`)
- Contacts (`/contacts`)
- Accounts (`/accounts`)
- Deals (`/opportunities`)
- Goals (`/goals`)
- Tickets (`/cases`)
- Tasks (`/tasks`)

**Sales Section:**
- Invoices (expandable, sub-items not visible by default)

**Support Section:**
- Help Desk (`/support`)

**Footer:**
- Collapse/expand sidebar
- User profile card (avatar, name, email, org switcher)

---

## 18. Common UI Patterns

### Slide-out Sheet (Create/Edit Forms)
- All create forms use a right-side slide-out sheet pattern (480px wide)
- Sheet has: header (entity type badge + "New"), form fields, footer (Cancel + Create button)
- "Show X more fields" expandable section for less-common fields

### Table Lists
- Configurable columns with column selector showing "X/Y" visible count
- Status filter buttons with counts (All, Open, Closed, etc.)
- Bulk select via checkboxes
- Pagination with rows-per-page selector
- Inline editing for dropdowns (e.g., Status column)

### Toast Notifications
- Bottom-right success toasts (green checkmark + message)
- Auto-dismiss

### Activity Tracking
- All CRUD operations are logged as activities
- Activity model: action type, description, user, timestamp, linked entity

---

## 19. API Structure

### Backend API Endpoints (`/api/`)
- `auth/` - Authentication (magic link, Google OAuth, token refresh, profile)
- `contacts/` - CRUD contacts
- `accounts/` - CRUD accounts
- `leads/` - CRUD leads
- `opportunities/` - CRUD opportunities
- `cases/` - CRUD cases/tickets
- `tasks/` - CRUD tasks, boards, board columns/tasks
- `invoices/` - CRUD invoices, estimates, products, templates, payments, recurring
- `users/` - User management, teams, roles
- `settings/` - API settings, org settings
- `tags/` - Tag management

### API Key Management
- Organizations can generate API keys
- Key: title, description, key value (auto-generated), is_active
- Used for external integrations

---

## 20. Data Model Summary

### Core Entities
| Entity | Key Relationships |
|--------|------------------|
| Lead | -> Contact (optional), Tags, Teams, Assigned Users |
| Contact | -> Account (optional), Tags |
| Account | -> Contacts, Opportunities, Cases, Invoices |
| Opportunity | -> Account, Contacts, Line Items, Tags |
| Case | -> Account, Contacts, Solutions, Tags |
| Task | -> Account, Contacts, Board Tasks |
| Invoice | -> Account, Contact, Opportunity, Line Items, Payments, Template |
| Estimate | -> Account, Contact, Line Items |
| Product | (standalone catalog) |
| SalesGoal | -> Assigned User |

### Support Entities
| Entity | Purpose |
|--------|---------|
| Org | Multi-tenancy organization |
| Profile | User profile within org |
| Teams | Group users for assignment |
| Tags | Cross-entity labeling |
| Comment/CommentFiles | Discussion threads on entities |
| Attachments | File uploads on entities |
| Document | Document management |
| Activity | Audit log |

---

## 21. Technical Issues Encountered

1. **Tailwind CSS 4.2.1 + bits-ui incompatibility**: `Invalid declaration: boxWith, mergeProps` error in scroll-area-viewport.svelte. Workaround: downgrade to Tailwind 4.1.x
2. **PUBLIC_DJANGO_API_URL split-brain**: Frontend SSR uses `http://backend:8000` (Docker network), but client-side JS also uses this URL which fails in browser (ERR_NAME_NOT_RESOLVED). Needs separate server/client API URL config.
3. **Vite dev server instability**: Repeated crashes, "fetch failed" errors on SSR, browser page crashes due to heavy CSS processing
4. **Some pages return 404/500**: Board view (needs board creation first), calendar view (fetch failed), settings index (redirect), profile (heavy rendering)
5. **Magic link auth friction**: No password login; each browser session requires a new magic link cycle

---

## 22. Feature Comparison Notes (vs Pipelinq)

### Features BottleCRM Has
- Full invoicing suite (invoices, estimates, recurring, templates, products, payments)
- Client portal for invoices/estimates (token-based public access)
- Sales goals tracking with period-based targets
- Salesforce import
- Row-Level Security (PostgreSQL) for multi-tenancy
- Kanban boards for tasks (customizable columns, WIP limits)
- SLA tracking for cases/tickets
- Stage aging alerts for opportunities
- Magic link (passwordless) authentication
- PDF generation (WeasyPrint)
- Activity audit trail
- Celery for async task processing

### Architectural Differences
- SvelteKit 2 frontend (vs Vue.js in Pipelinq context)
- Django REST backend (vs PHP/Nextcloud)
- Standalone deployment (vs Nextcloud app ecosystem)
- PostgreSQL RLS for multi-tenancy (vs Nextcloud user/group system)
- Slide-out sheet pattern for forms (vs modal/page navigation)
