# Erxes Browser Walkthrough - Competitive Analysis

**Date:** 2026-03-14
**Version:** erxes v2.17.36
**Deployment:** Docker Compose (local, http://localhost:9014)

## Deployment Experience

### Setup Complexity
Getting erxes running locally required significant effort:
- **14 Docker containers** needed: MongoDB (replica set), Redis, RabbitMQ, core-api, gateway, 6 plugin APIs (cards, tags, notifications, logs, inbox, automations), coreui (Nginx), crons, workers
- **Multiple configuration files**: docker-compose.yml, plugins.js, permissions.json
- **Service discovery timing issues**: Gateway must wait for all plugins to register via RabbitMQ before it can compose the supergraph schema
- **Plugin federation conflicts**: Cards and Sales plugins have incompatible GraphQL types (`Deal.stage` returns `Stage` vs `SalesStage`); Forms plugin has argument mismatches with core
- **SaaS vs OS mode**: Must explicitly set `VERSION=os` to avoid multi-tenant SaaS mode which requires additional MongoDB URLs
- **Memory usage**: ~4-6 GB RAM for a minimal deployment

### Key Deployment Insight
The plugin UI bundles (`remoteEntry.js`) are NOT included in the Docker images. They must be separately downloaded and mounted into the coreui container at `/usr/share/nginx/html/js/plugins/`. Without them, plugin-specific pages (Deals, Tasks, Tickets, Inbox, Automations, Tags) render as blank pages. Only core features (Contacts, Segments, Forms, Insight, Settings) are bundled into the main React application.

## UI/UX Walkthrough

### Navigation Structure
- **Left sidebar** with icon-based navigation (collapsible)
- Primary nav items: Contacts, Segments, Forms, Insight, Sales Pipeline
- **"More" menu**: Expandable panel showing additional plugins (Tasks, Tickets, Inbox, Automations, Tags) with pin/unpin functionality
- Bottom nav: Marketplace, Settings
- **Top bar**: Global search, feedback link, user avatar/menu

### Pages Visited (38 screenshots captured)

#### 1. Welcome/Dashboard (`/welcome`) - Screenshot 35
- Onboarding wizard with "Getting Started" progress tracker (0%)
- Guided steps: System configurations, Create a brand, Set up permissions, Set organization structure, Invite team members
- Links to: Learn more, Using guide, Join our community
- "Onboarding optimized for you" - link to paid onboarding services

#### 2. Contacts: Customers (`/contacts/customer`) - Screenshots 03-06
- **List view** with table layout: Full name, Primary email, Primary phone, Score, Tags, Actions
- **Sidebar filters**: Segments, Tags, Integrations, Brand, Forms, Date, Lead Status, Lifecycle state, Type
- **Add customer dialog**: Rich form with First/Last/Middle name, Code, Owner, Email (with verification status), Phone (with verification status), Pronoun, Department, Position, Has Authority, Subscribed, Birthday, Description
- **Customer detail view**: Full profile with avatar, primary info, activity timeline, notes (rich text editor with bold/italic/lists/links/images), Channels, Skills sections
- CRUD tested successfully: Created customer "Jan de Vries" via the UI

#### 3. Contacts: Leads (`/contacts/lead`) - Screenshot 07
- Same table interface as Customers but filtered for leads
- Same sidebar filter options

#### 4. Contacts: Companies (`/contacts/company`) - Screenshots 08, 34
- Same contact framework but for company entities
- "Getting Started with Contacts" onboarding wizard with 5 steps:
  1. Import your previous contacts
  2. Collect visitor information (create Messenger)
  3. Sync email contacts
  4. Start capturing social media contacts
  5. Generate contacts through Forms
- Add company dialog: reuses the same "New customer" form (shared form component)

#### 5. Segments (`/segments`) - Screenshot 09
- Dynamic audience/contact segmentation tool
- Segment types available: contacts:customer, contacts:company, contacts:lead, cards:deal, cards:task, cards:ticket, cards:purchase
- Visual preview illustration
- "Go to create" button for each type

#### 6. Forms (`/forms`) - Screenshot 10
- Form builder for lead capture
- "New form" button with search
- Tags and date filtering in sidebar

#### 7. Insight (`/insight`) - Screenshot 12
- **Dashboard** section: Empty placeholder with + button to create
- **Report** section: Empty placeholder with + button to create
- Onboarding illustration with guidance text

#### 8. Sales Pipeline / Deals (`/deal`) - Screenshot 11
- **BLANK PAGE** - plugin UI bundle not loaded (remoteEntry.js 404)
- This is a Kanban board view when working (based on code analysis)
- Backend API is running (cards plugin is registered and healthy)

#### 9. Tasks (`/task`) - Screenshot 13
- **BLANK PAGE** - same issue, plugin-cards-ui bundle not available

#### 10. Tickets (`/ticket`) - Screenshot 14
- **BLANK PAGE** - same issue

#### 11. Inbox (`/inbox`) - Screenshot 16
- **BLANK PAGE** - plugin-inbox-ui bundle not available

#### 12. Automations (`/automations`) - Screenshot 17
- **BLANK PAGE** - plugin-automations-ui bundle not available

#### 13. Tags (`/tags`) - Screenshot 18
- **BLANK PAGE** - plugin-tags-ui bundle not available

#### 14. Marketplace (`/marketplace`) - Screenshots 19-20
- **Tab categories**: Marketing, Sales, Services, Operations, Communications, Productivity
- **Services section** with premium offerings:
  - Standard Support ($)
  - Customer Success Consulting ($)
- Plugin marketplace for extending functionality
- Monetization model visible: freemium core + paid plugins + paid services

#### 15. Settings (`/settings`) - Screenshots 21-23
Two sections:
- **General Settings**: System Configuration, Permissions, Team Members, Brands, Import & Export, Apps (legacy), Apps, Structure, Tags, Exchange Rates, System Logs, Properties, Email Delivery Logs, Configs of Products, Product and services, Email Templates
- **Plugin Settings**: Logs (from logs plugin)

#### 16. Settings: System Configuration (`/settings/general`) - Screenshot 24
Accordion-style sections:
- General settings
- Theme
- File upload
- Google Cloud Storage
- Cloudflare (R2 Bucket, Images & Stream CDN)
- AWS S3
- AWS SES
- Azure Blob Storage
- Google
- Common mail config
- Custom mail service
- Data retention
- Constants
- Connectivity Services
- MessagePro

#### 17. Settings: Team Members (`/settings/team`) - Screenshot 25
- **Organizational hierarchy**: Branch, Department, Unit panels (left sidebar)
- **Team member table**: Full name, Invitation status, Email, Employee Id, Status (active/inactive toggle), Actions
- **Invite team members** button
- Filter by segments panel
- Current user: Admin User, Verified, admin@erxes.io

#### 18. Settings: Permissions (`/settings/permissions`) - Screenshot 26
- **User Groups**: Create user group functionality
- **Permissions management**: Module-based ACL
- Filters: Choose module, Choose action, Choose users, Granted toggle
- "Fix permissions" utility button

#### 19. Settings: Brands (`/settings/brands`) - Screenshot 27
- Multi-brand support for white-labeling
- "Add New Brand" button
- Description: "Add unlimited Brands with unlimited support"

#### 20. Settings: Properties (`/settings/properties`) - Screenshot 28
- **Entity type tabs**: Tickets, Tasks, Purchases, Sales pipelines, Conversation details, Customers, Companies, Device properties, Team member, Products & services
- **Field groups**: "Basic information" with fields like Name (text), Priority (select), Label (select), Start date (input), Close date (input), Assigned to (select), Attachments (file), Description (textarea), Branches (select), Departments (select)
- **Relations** group: cross-entity linking (Tasks, Purchases, Deals)
- Toggle visibility, conditional logic per field
- "Fix properties" utility, "Add Group & Field" builder

#### 21. Settings: Email Templates (`/settings/email-templates`) - Screenshot 29
- Template management for mass emails
- "New email template" button with search

#### 22. Settings: Structure (`/settings/structure`) - Screenshot 30
- Organization structure form: Name, Description, Code, Supervisor
- Contact info: Phone number, Email, Coordinates (Longitude/Latitude)
- Social links: Website, Facebook, WhatsApp, Twitter, Youtube
- Image upload
- Tabs: Structure, Branches (0), Departments (0), Units (0), Positions (0)

#### 23. Settings: Logs (`/settings/logs`) - Screenshot 32
- Audit log with filters: Module, Action, User, Date
- Table: Date, Created by, Module, Action, Description, Changes
- Shows our customer creation: "2026-03-14 12:46:16 | admin@erxes.io | customer | CREATE"

#### 24. Settings: Product & Service (`/settings/product-service/`) - Screenshot 33
- Product catalog management
- Table columns: Code, Name, Type, Category, Unit Price, Tags, Bundle, Actions
- Category management sidebar
- Filters: Segments, Category status, Product status, Type, Brand, Tags, Bundle
- Import items + Add items buttons

#### 25. User Profile (`/profile`) - Screenshot 31
- Detailed user profile: Primary Email, Phone, Username, Short name, Location, Birthdate, Position, Positions, Score, Joined date, Description
- **Notes**: Rich text editor with attributes, formatting, image insert
- **Activity**: Timeline of user actions
- **Sidebar**: Channels, Skills (with add button), Branches, Departments, Positions
- Action dropdown menu

## CRUD Testing Results

| Operation | Entity | Result | Notes |
|-----------|--------|--------|-------|
| CREATE | Customer | SUCCESS | Created "Jan de Vries" via add dialog |
| READ | Customer list | SUCCESS | Table view with data |
| READ | Customer detail | SUCCESS | Full profile view |
| CREATE | Company | PARTIAL | Dialog opens but uses same "New customer" form |
| CREATE | Deal | FAILED | Plugin UI not loaded (remoteEntry.js 404) |
| READ | Logs | SUCCESS | Audit trail shows customer creation |
| READ | Team Members | SUCCESS | Shows admin user with status |

## Competitive Assessment vs Pipelinq

### Strengths of Erxes
1. **Comprehensive feature set**: CRM, sales pipelines, inbox, automations, forms, segments, insights -- all in one platform
2. **Plugin architecture**: Marketplace model for extending functionality; clear separation of concerns
3. **Organizational modeling**: Branch, Department, Unit, Position hierarchy with supervisor assignment
4. **Property customization**: Configurable field groups per entity type with conditional logic
5. **Audit logging**: Built-in activity logs with module/action/user filtering
6. **Multi-brand support**: White-labeling for agencies/MSPs
7. **Rich contact management**: Email/phone verification status, lead scoring, lifecycle states
8. **Onboarding experience**: Guided setup wizard with progress tracking
9. **Cloud storage flexibility**: Supports AWS S3, Azure Blob, Google Cloud, Cloudflare R2
10. **Product catalog**: Built-in product/service management with categories and pricing

### Weaknesses of Erxes
1. **Deployment complexity**: 14+ containers, numerous configuration files, service discovery timing issues -- very difficult to self-host
2. **Plugin UI loading**: Module Federation approach requires separately building/hosting plugin UI bundles; without them, major features render as blank pages
3. **Memory consumption**: 4-6 GB RAM minimum for a functional deployment
4. **GraphQL Federation fragility**: Plugin schema conflicts can prevent the entire gateway from starting
5. **Slow startup**: 60-120 seconds for all services to register; "Hang in there" loading screen on every page navigation (~6-8 seconds)
6. **Companies vs Customers confusion**: Add company dialog shows "New customer" -- shared form components lack entity-specific adaptation
7. **Missing knowledge base**: No built-in wiki/docs system visible in this deployment
8. **SaaS-first design**: Self-hosted mode (`VERSION=os`) feels like an afterthought; multi-tenant SaaS is the primary target
9. **External dependencies**: Requires MongoDB, Redis, AND RabbitMQ -- three separate stateful services
10. **Documentation gaps**: Self-hosting docs don't mention the need to download plugin UI bundles separately

### Relevance for Pipelinq
- **Pipeline views**: Erxes's Kanban board approach for deals/tasks/tickets is the direct competitor to Pipelinq's pipeline views
- **Customizable properties**: Field group management per entity is comparable to OpenRegister schema definitions
- **Automation**: Built-in workflow automation could compete with n8n integration
- **Key differentiator for Pipelinq**: Nextcloud-native deployment (single app install vs 14 containers), NL Design System compliance, simpler architecture

## Screenshots Index
All 38 screenshots saved to: `concurrentie-analyse/pipelinq/erxes/screenshots/`
