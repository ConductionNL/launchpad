# Erxes Browser Walkthrough Notes

**Date**: 2026-03-14
**Version analyzed**: erxes v2.17.36 (Docker images) + erxes-next (main branch source code)
**GitHub**: https://github.com/erxes/erxes

## Deployment Status

**Docker deployment was attempted but failed due to Apollo Router compatibility issues.** The erxes v2 Docker images (2.17.36) have GraphQL Federation schema incompatibilities between plugins (contacts plugin `[String]` vs core `JSON` type mismatch, forms plugin missing required arguments). The Apollo Router binary spawned inside the gateway container crashes repeatedly. The gateway reaches "ready" status but the GraphQL endpoint returns empty responses.

**Root cause**: erxes v2 Docker images are published independently per-plugin without coordinated compatibility testing. The `2.17.36` tag for different plugins was built at different dates (core: 2026-03-04, contacts: 2024-09-15) leading to schema drift.

Analysis below is based on **source code inspection** of both the old (v2) and new (erxes-next) codebases, including all frontend components, forms, GraphQL schemas, and backend models.

---

## 1. Architecture Overview

### Tech Stack
- **Frontend**: React 18.3, Rspack (Rust bundler), Module Federation, TailwindCSS v4, Radix UI, Jotai (state), Apollo Client (GraphQL), React Hook Form + Zod, BlockNote editor, react-i18next, Recharts
- **Backend**: Node.js + TypeScript 5.7, Express, Apollo Server v4 (Federation), tRPC v11, MongoDB (Mongoose), Redis + BullMQ, Elasticsearch 7, RabbitMQ (v2) / Redis PubSub (next)
- **Infrastructure**: MongoDB with replica set, Redis, RabbitMQ (v2) or just Redis (next), Elasticsearch (optional), Apollo Router for GraphQL Federation

### Plugin Architecture
- **Backend plugins**: Independent microservices registered via Redis service discovery, federated through Apollo Gateway/Router
- **Frontend plugins**: Module Federation remotes loaded dynamically at runtime
- **Core API** (port 3300): Contacts, products, auth, organization, segments, automations
- **Gateway** (port 4000 next / 3300 v2): Apollo Router + GraphQL Federation
- **Core UI** (port 3001 next / 3000 v2): Module Federation host

### Available Plugins (erxes-next)
| Plugin | Type | Description |
|--------|------|-------------|
| sales_ui/api | Sales | Deals pipeline, POS, orders, covers |
| operation_ui/api | Operations | Tasks, projects, cycles, teams, triage |
| frontline_ui/api | Customer Service | Inbox, tickets, forms, channels, integrations, knowledgebase |
| accounting_ui/api | Accounting (EE) | Chart of accounts, transactions, inventory, VAT |
| content_ui/api | Content (EE) | CMS, web builder, posts, pages |
| loyalty_ui/api | Loyalty | Scores, vouchers, lotteries, coupons, pricing |
| insurance_ui/api | Insurance | Contracts, risk types, vendors |
| payment_ui/api | Payments | Invoices, payment config |
| mongolian_ui/api | Mongolia-specific | eBarimt, Erkhet sync |
| tourism_ui/api | Tourism | PMS, TMS |

---

## 2. Authentication

### Login Page (`/sign-in`)
**Fields**:
- Email (text input)
- Password (password input)
- "Sign in" button
- "Forgot password?" link
- Dynamic banner area (left panel)

### Forgot Password Page
**Fields**:
- Email (text input)
- "Send reset link" button

### Reset Password Page
**Fields**:
- New password (password input)
- Submit button

### User Confirmation/Invitation Page
- Accept invitation from team admin
- Set initial password

### Initial Setup (Owner Creation)
Via GraphQL mutation `usersCreateOwner`:
- Email
- Password
- First name
- Last name
- Purpose (text)

---

## 3. Contacts Module (Core)

### 3.1 Customers List View
- Table/list view with search, filters, bulk actions
- Command bar with: Merge, Delete, Tag, Export
- Column sorting and customization
- Pagination

### 3.2 Customer Add Form
**Fields**:
| Field | Type | Required |
|-------|------|----------|
| Avatar | Image upload | No |
| First name | Text input | No |
| Last name | Text input | No |
| Code | Text input | No |
| Owner | Member selector | No |
| Primary email | Email input | No |
| Email validation status | Select | No |
| Primary phone | Phone input | No |
| Phone validation status | Select | No |
| Description | Rich text editor (BlockNote) | No |
| Is subscribed | Checkbox/toggle | No |

**Links tab** (separate fields):
- LinkedIn, Twitter, Facebook, YouTube, GitHub, Website URLs

### 3.3 Customer Detail View
- General information fields (editable inline)
- Custom fields (dynamic, configurable per organization)
- Activity log tab
- Related items (deals, tasks, tickets)
- Internal notes
- Tags

### 3.4 Companies List View
- Table/list view similar to customers
- Bulk actions: Merge, Delete, Tag

### 3.5 Company Add Form
**Fields**:
| Field | Type | Required |
|-------|------|----------|
| Avatar | Image upload | No |
| Name (primaryName) | Text input | Yes |
| Code | Text input | No |
| Owner | Member selector | No |
| Parent company | Company selector | No |
| Email | Email input | No |
| Phone | Phone input | No |
| Website | Text input | No |
| Industries | Multi-select (predefined list) | No |
| Headquarters country | Country selector (with flags) | No |
| Business type | Select (Analyst, Competitor, Customer, etc.) | No |
| Size | Number input | No |
| Description | Rich text editor | No |

### 3.6 Company Detail View
- All company fields editable inline
- CompanyDetailFields, CompanyDetailGeneral components
- Custom fields support
- Related contacts, deals, tasks

---

## 4. Sales Module (Plugin: sales_ui)

### 4.1 Sales Pipeline (Deals) - Kanban View
- **Board > Pipeline > Stage > Deal** hierarchy
- Drag-and-drop kanban board
- Stages are configurable columns
- Each deal card shows: name, amount, assignees, labels, dates
- Stage probability tracking for forecasting

### 4.2 Deal Add Form (AddCardForm)
**Fields**:
| Field | Type | Required |
|-------|------|----------|
| Name | Text input | Yes |
| Description | Rich text editor (BlockNote) | No |
| Pipeline/Stage (workflow) | Workflow selector | Conditional |
| Assigned to | Multi-member selector | No |
| Labels | Multi-label selector | No |
| Companies | Multi-company selector | No |
| Customers | Multi-customer selector | No |
| Tags | Multi-tag selector | No |

### 4.3 Deal Detail View
- **Overview tab**: Labels, checklists, attachments, custom fields
- **Products tab**: Product/service line items with quantities, prices, discounts, tax
- **Activity tab**: Activity log, comments, email threads
- Labels with color coding (LabelForm: name + color picker)
- Checklists (ChecklistForm: title, items with checkboxes)
- Date pickers (start date, close date)

### 4.4 Pipeline Settings
- Board management (create/edit boards)
- Pipeline configuration within boards
- Stage configuration (name, probability %, color)
- Pipeline permissions

### 4.5 POS (Point of Sale)
Dedicated POS module within sales:
- POS Index/Settings/Edit pages
- Orders management
- Covers tracking
- Items/products by items view
- Summary reporting
- Orders by customer/subscription views

---

## 5. Operations Module (Plugin: operation_ui)

### 5.1 Tasks (Project Management)
Linear-style task management with teams, projects, cycles.

### 5.2 Task Add Form (AddTaskForm)
**Fields**:
| Field | Type | Required |
|-------|------|----------|
| Team | Team selector | Yes |
| Name | Text input | Yes |
| Status | Status selector (per project) | No |
| Priority | Priority selector (0-4: None/Urgent/High/Medium/Low) | No |
| Assignee | Member selector (filtered by team) | No |
| Project | Project selector (filtered by team) | No |
| Milestone | Milestone selector (filtered by project) | No |
| Estimate point | Point selector (team-configured scale) | No |
| Cycle | Cycle selector (sprint/iteration) | No |
| Start date | Date picker | No |
| Target date | Date picker | No |
| Tags | Multi-tag selector (type: operation:task) | No |
| Description | Block editor (BlockNote) | No |
| Template | Template selector (auto-fills fields) | No |

### 5.3 Task Views
- List view (table)
- Board/kanban view (by status)
- Task detail page with full editing

### 5.4 Projects
- Project list with team assignment
- Project detail: tasks list, milestones, cycles
- Project tags management

### 5.5 Cycles (Sprints)
- Cycle list page
- Cycle detail: tasks within cycle, date range, progress

### 5.6 Teams
- Team member management
- Team status overview
- Team templates
- Template form (reusable task templates)

### 5.7 Triage
- Unassigned/incoming work items
- Quick assignment workflow

---

## 6. Frontline Module (Plugin: frontline_ui) - Customer Service

### 6.1 Team Inbox
- Conversation list with real-time updates
- Conversation detail with message history
- Assign conversations to team members
- Channel-based filtering
- Integration with external messaging (Facebook, email, chat widget)

### 6.2 Tickets Pipeline
- Similar kanban board to deals
- Ticket statuses management
- Pipeline detail and permissions
- Pipeline configuration list
- Ticket-specific fields: TicketBasicFields component

### 6.3 Forms Builder
- Form creation page (FormCreatePage)
- Form detail/edit page
- Form preview page
- Form list with search and filters

### 6.4 Channels & Integrations
- Channel settings: create channels, assign team members
- Channel detail: integrations linked to channel
- Integration settings: configure external services
- Integration config page
- Supported integrations: Facebook Messenger, email, chat widget, bookings

### 6.5 Knowledge Base
- Knowledge base index page
- Article management
- Category organization

### 6.6 Response Templates
- Canned response management
- Response detail editing

### 6.7 Reports
- Report index page
- Conversation/ticket analytics

### 6.8 Call Center
- Call index page
- Call detail page

---

## 7. Automations (Core)

### 7.1 Automation Builder
- Visual workflow editor
- Trigger-based automation chains
- Node types: triggers, actions, conditions, delays

### 7.2 Automation Triggers (per plugin)
| Plugin | Trigger | Description |
|--------|---------|-------------|
| sales | dealCreated | When a deal is created |
| sales | stageProbability | When deal reaches probability threshold |
| frontline | conversationCreated | New conversation |
| frontline | facebookComment | Facebook comment received |
| frontline | facebookMessage | Facebook message received |
| operation | taskCreated | New task created |

### 7.3 Automation Actions (per plugin)
| Plugin | Action | Description |
|--------|--------|-------------|
| sales | createDeal | Create a deal automatically |
| sales | moveDeal | Move deal to stage |
| core | sendEmail | Send email notification |
| core | createCustomer | Create customer record |
| core | sendNotification | Push notification |

### 7.4 Automation Settings
- **Email templates**: Create/edit HTML email templates (EmailTemplateForm)
- **AI Agents**: Configure AI-powered agents with training data (files upload, test chat)
- **Bots**: Facebook Messenger bots with page selection and persistence menu config

---

## 8. Products (Core)

### Product Management
- Product list with categories
- Product add/edit forms
- Product categories management
- Fields: name, code, type, unit price, description, category, tags, UOM, tax, barcodes, attachments
- Custom fields support

---

## 9. Segments (Core)

### Dynamic Segmentation
- Segment builder with conditions
- Content types: customers, companies, deals, tasks, etc.
- Field-based filtering with operators
- Elasticsearch-backed querying

---

## 10. Settings

### 10.1 Account Settings
| Page | Description |
|------|-------------|
| Profile | Edit user profile (name, avatar, email) |
| Change Password | Current + new password |
| Experience | UI preferences, language |
| Notification settings | Per-category notification preferences |

### 10.2 Workspace Settings
| Page | Description |
|------|-------------|
| General Settings | Organization name, domain config |
| Team Members | Invite, manage, deactivate members |
| Permissions | Role-based access control, module permissions |
| Brands | Multi-brand support |
| Tags | Manage tags across modules |
| Properties (Custom Fields) | Create/edit/manage custom field groups |
| Structure | Organization hierarchy: branches, departments, units, positions |
| Mail Config | SMTP/email service configuration |
| File Settings | File storage configuration |
| App Settings | Third-party app integrations |
| Broadcast Settings | Mass notification settings |

### 10.3 Automation Settings
| Page | Description |
|------|-------------|
| Email Templates | Create/manage email templates |
| AI Agents | Configure and train AI agents |
| Bots | Messenger bot configuration |

### 10.4 Client Portal
- Client portal list management
- Client portal detail configuration

### 10.5 Logs
- System activity logs with filtering

---

## 11. Other Core Features

### Import/Export
- Bulk import from CSV/Excel for customers, companies, deals, etc.
- Export data with field selection

### Documents
- Document template management
- Variable-based document generation

### Internal Notes
- Attachable notes on any entity
- Rich text support

### Activity Logs
- Timeline of all actions on entities
- Integration with automation triggers

### Notifications
- In-app notification center
- Real-time push via GraphQL subscriptions
- Email notification delivery

### Organization Structure
- **Branches**: Physical locations
- **Departments**: Organizational divisions
- **Units**: Sub-divisions within departments
- **Positions**: Job roles/titles

### Quick Actions
- Global search
- Quick entity creation
- Keyboard shortcuts

### Favorites
- Pin frequently accessed items
- Sidebar favorites navigation

---

## 12. Enterprise Edition Plugins

### Accounting
- Chart of accounts with categories
- Transaction recording (journal entries)
- VAT/tax rows management
- Inventory management: remainders, safe remainders, adjustments
- Sync with deals and POS orders

### Content Management (CMS)
- Posts management with categories
- Pages with web builder
- Rich content editing

### Loyalty
- Score/point system
- Vouchers, coupons
- Lottery and spin-wheel campaigns
- Donation campaigns
- Assignment rules
- Pricing engine with rules

### Insurance
- Insurance types and risk types
- Contract management with templates
- PDF contract editor
- Vendor management with users
- Customer management
- Car and citizen insurance workflows

### Payments
- Invoice management
- Payment gateway settings

### Tourism
- Property Management System (PMS)
- Tour Management System (TMS)
- Preview and settings pages

---

## 13. UI/UX Patterns

### Design System
- Custom design system built on Radix UI primitives
- TailwindCSS v4 for styling
- `erxes-ui` shared component library
- `ui-modules` for cross-plugin reusable widgets
- Consistent form patterns with React Hook Form + Zod validation

### Common UI Components
- **RecordTable**: Data table with sorting, filtering, column customization
- **Sheet**: Slide-out panels for forms (add/edit)
- **CommandBar**: Contextual bulk actions
- **BlockEditor**: Rich text editing via BlockNote
- **SelectMember/SelectCompany/SelectCustomer**: Reusable entity selectors
- **MultipleSelector**: Multi-select with search
- **Upload**: File/image upload with preview
- **DatePicker**: Date selection with calendar
- **Toast**: Notification toasts
- **Dialog/Modal**: Confirmation dialogs

### Navigation
- Left sidebar with collapsible sections
- Plugin-based navigation groups (each plugin adds its menu items)
- Favorites pinning
- Theme selector (light/dark)
- Language selector (i18n)
- Organization switcher

---

## 14. Data Model Summary

### Core Entities
| Entity | Key Fields |
|--------|------------|
| Customer | firstName, lastName, primaryEmail, primaryPhone, code, avatar, state, leadStatus, tags, customFields |
| Company | primaryName, code, email, phone, website, industry, businessType, size, location, parentCompany, tags |
| Product | name, code, type, unitPrice, category, tags, UOM, barcodes, description |
| User | email, firstName, lastName, role, department, branch, position |
| Brand | name, description |
| Tag | name, type, colorCode |
| Segment | name, contentType, conditions |
| Form | title, description, fields, integrations |

### Sales Entities
| Entity | Key Fields |
|--------|------------|
| Board | name, type |
| Pipeline | name, boardId, stages, visibility |
| Stage | name, pipelineId, probability, order |
| Deal | name, stageId, amount, assignedUserIds, customerIds, companyIds, labels, products, closeDate |
| Label | name, colorCode, pipelineId |
| Checklist | title, contentType, items |

### Operations Entities
| Entity | Key Fields |
|--------|------------|
| Team | name, members, visibility |
| Project | name, teamIds, description, milestones |
| Task | name, status, priority, assigneeId, projectId, cycleId, milestoneId, estimatePoint, startDate, targetDate, tags |
| Cycle | name, teamId, startDate, endDate |
| Template | name, defaults, teamId |

### Frontline Entities
| Entity | Key Fields |
|--------|------------|
| Conversation | status, assignedUserId, channelId, integrationId, content, customer |
| Channel | name, description, integrationIds, memberIds |
| Integration | name, kind (messenger/facebook/email), brandId, config |
| Ticket | name, stageId, priority, assignedUserIds, source |
| KnowledgeBase | title, description, articles, categories |

---

## 15. Key Differences from Pipelinq

### What erxes does well:
1. **Plugin architecture**: Highly modular, each feature is a separate deployable service
2. **Module Federation**: Frontend plugins loaded dynamically, no monolithic build
3. **Multi-tenancy**: Built-in subdomain-based tenant isolation
4. **Comprehensive CRM**: Full customer lifecycle from lead to deal to support
5. **Automation engine**: Visual workflow builder with triggers/actions across modules
6. **Real-time**: GraphQL subscriptions for live updates
7. **Internationalization**: Full i18n support with Transifex
8. **AI integration**: AI agents with training capability
9. **POS system**: Point of sale built into the sales module

### Challenges observed:
1. **Deployment complexity**: 15+ microservices, Apollo Router, MongoDB replica set, Redis, RabbitMQ
2. **Docker image incompatibility**: Plugin versions not coordinated, schema drift between releases
3. **Resource heavy**: Each plugin is a separate Node.js process with its own memory footprint
4. **Learning curve**: Complex architecture requires significant ops expertise
5. **Limited self-hosting documentation**: No working docker-compose in the repository
6. **Enterprise features locked**: Accounting, CMS, loyalty behind enterprise licensing

---

## 16. Screenshots

Only one screenshot was captured before the Docker deployment failed:

1. `01-initial-loading.png` - erxes UI loading page showing "Hang in there! We'll be right back with you" message with a space shuttle illustration. This is the standard erxes loading/error page displayed when the frontend cannot connect to the GraphQL gateway.

**Note**: Full browser walkthrough was not possible due to erxes v2 Docker deployment instability. The analysis above is based on comprehensive source code review of both the v2 and erxes-next codebases.
