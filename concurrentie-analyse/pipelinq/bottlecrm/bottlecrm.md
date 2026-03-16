# BottleCRM — Competitor Analysis

## Overview

- **Website:** https://bottlecrm.io/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Free open-source CRM with visual drag-and-drop pipeline

## Codebase

- **Repository:** https://github.com/MicroPyramid/Django-CRM

## Business Model

Fully open source with no paid tiers or feature gates. The project is maintained by MicroPyramid, a software development company that monetizes through consulting, custom development, and support services rather than through the CRM software itself. No subscription fees, no premium editions.

## Target Market

Startups and small businesses looking for a free, modern CRM. Built with Django REST Framework and SvelteKit, it appeals to Python/Django developers who want a customizable CRM they can extend. Multi-tenant architecture targets SaaS builders who want to offer CRM as a service.

## Pricing

- **Self-Hosted:** Free (MIT license, unlimited users)
- No paid plans, no subscription fees, no feature gates
- Costs are limited to hosting and infrastructure

## Key Features

- 360-degree customer view with smart segmentation and lead scoring
- Visual drag-and-drop pipeline for deal tracking
- Task assignment, reminders, and team synchronization
- Real-time dashboards for performance tracking and ROI measurement
- Multi-tenant architecture with PostgreSQL Row-Level Security (RLS)
- Mobile app built with Flutter
- REST API for integrations
- Built with Django REST Framework + SvelteKit

## Feature Comparison with Pipelinq

| Feature | BottleCRM | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Yes | Yes |
| Request intake | No | Yes |
| Contact moments logging | Partial (activity history) | Yes |
| My Work queue | Partial (task management) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | Partial (CSV only) | Yes |
| Case management integration | No | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Partial (basic roles) | Yes |
| Audit trail | No | Yes |

## Strengths

- Completely free with no feature restrictions — MIT license allows unrestricted use and modification
- Modern tech stack (Django REST + SvelteKit + Flutter mobile) appeals to developers
- Multi-tenant SaaS-ready architecture with PostgreSQL RLS for data isolation

## Weaknesses

- Smaller community and less mature than established CRMs — limited documentation and ecosystem
- No Nextcloud integration or Dutch government ecosystem support
- Limited enterprise features — no audit trail, no advanced RBAC, no duplicate detection

## Browser Walkthrough (2026-03-14)

### Setup and Authentication

- **Docker deployment:** 6 containers (frontend, backend, db, redis, celery-worker, celery-beat)
- **Frontend:** SvelteKit + Vite dev server on port 9011
- **Backend:** Django REST Framework on port 9010
- **Auth method:** Magic link (passwordless email login). No username/password login.
- **Multi-org:** After login, user selects an organization. Supports creating new orgs.
- **JWT-based:** Auth uses access/refresh JWT tokens stored in localStorage. SvelteKit SSR also checks auth server-side.

### Pages Visited (screenshots in `screenshots/`)

1. **Login page** (01) — Clean, minimal design. Google OAuth + magic link email options.
2. **Organization select** (02) — Multi-tenant org picker after login.
3. **Create organization** (03) — Simple org creation form.
4. **Dashboard** (05) — Rich dashboard with: Today's Focus (due tasks, follow-ups), Sales Pipeline visualization (Prospecting -> Qualification -> Proposal -> Negotiation -> Closed Won/Lost), Pipeline Value/Weighted Pipeline/Won This Month/Conversion Rate KPIs, Pipeline by Stage breakdown, Hot Leads section, My Tasks with filter tabs, My Opportunities, Goal Progress, Recent Activity feed.
5. **Leads list** (06) — Table view with columns: Title, Name, Company, Email, Status, Rating, Created. Supports Table/Kanban toggle, status filters (All/Open/Lost), column visibility (7/12), filters.
6. **Leads Kanban** (10) — Kanban board with stages: Assigned, In Process, Recycled, Closed. Cards show lead title and company.
7. **Contacts list** (11, 13) — Table with columns: Contact, Company, Title, Email, Phone, Created. Column visibility (6/7).
8. **Accounts list** (14) — Table with status filters: All, Active, Closed.
9. **Deals/Opportunities** (15) — Table with Pipeline value display, status filters (All/Open/Won/Lost), column visibility (6/9).
10. **Goals** (16) — Sales Goals page with tabs: All, Active, Completed, Needs Attention. Search functionality.
11. **Tickets/Cases** (17) — Cases list with status filters: All, Open, Closed.
12. **Tasks** (18) — List/Board/Calendar view toggle, status filters (All/Active/Completed), column visibility (6/10).
13. **Help Desk** (21) — Community support page with links to GitHub, mission statement. Not an actual ticket system.
14. **Invoices** (22) — Invoice management with status filters: All, Open, Paid, Overdue. Create Invoice button.
15. **Users & Teams** (16-current, 29) — User/Team management with invite functionality (email + role selector), team members list.
16. **Organization Settings** (31) — Org preferences with Save Changes button.
17. **Swagger API** (15-swagger) — Built-in API documentation (requires auth, returned 403 without token).

### CRUD Testing

- **Lead created successfully:** "Test Lead - Gemeente Amsterdam" with first/last name, email, phone, company, status. Toast notification confirmed creation. Lead appeared immediately in table and kanban views.
- **Contact created successfully:** "Pieter Bakker" at "Gemeente Den Haag" with email, phone, company, job title. Toast notification confirmed. Record appeared in contacts table.

### Stability Issues

- **Vite HMR reload loops:** The Vite dev server's Hot Module Replacement causes frequent page reloads, making automated testing very difficult. Pages cycle through routes unexpectedly.
- **Tailwind CSS build error:** `[plugin:@tailwindcss/vite:generate:serve] Invalid declaration: boxWith, mergeProps` in bits-ui scroll-area component causes 500 errors on some page transitions.
- **Backend DNS resolution:** Frontend SSR tries to reach `backend:8000` (Docker internal hostname) for some API calls, which fails from the browser context with `ERR_NAME_NOT_RESOLVED`. Tags and Users dropdowns fail to load.
- **Container instability:** The docker containers crashed/exited (exit code 137 = OOM killed) multiple times during the walkthrough.
- **Font loading blocking screenshots:** Playwright's `document.fonts.ready` wait hangs on many pages, likely due to Google Fonts CDN loading issues or the Vite HMR interfering.

### UI/UX Observations

- **Modern, polished design:** Clean UI with consistent orange accent color, good use of whitespace, professional iconography.
- **Responsive sidebar:** Collapsible navigation with clear section grouping (CRM, Sales, Support).
- **Good empty states:** Empty states have descriptive messages and call-to-action buttons.
- **Quick create forms:** Modal-based creation with inline field labels and icons. Forms feel well-designed.
- **Pipeline visualization:** The dashboard pipeline view is visually appealing with stage-to-stage arrows.
- **View mode toggles:** Leads support Table/Kanban, Tasks support List/Board/Calendar.
- **Column visibility controls:** Users can show/hide columns per entity type.

### Technical Architecture

- **Frontend:** SvelteKit 2.53 + Vite 7.3 + Tailwind CSS 4.1 + bits-ui 2.16 + Svelte 5.53
- **Backend:** Django REST Framework with PostgreSQL + Redis + Celery
- **Auth:** JWT with magic link (passwordless), Google OAuth support
- **Multi-tenancy:** Organization-level isolation
- **API:** REST with OpenAPI/Swagger documentation

## Notes

BottleCRM is a developer-oriented open-source CRM with a modern tech stack. Its multi-tenant architecture is interesting for SaaS builders. However, it is less feature-complete than most competitors and has a small community. The project appears to have moderate activity on GitHub. Not suitable for government use cases that require Nextcloud integration and Common Ground compliance.

The browser walkthrough revealed a visually polished but technically unstable application. The Vite dev server HMR causes navigation instability, and there are Tailwind CSS build errors that produce 500 errors. The Docker setup is fragile (containers OOM-killed). Despite these issues, the core CRM functionality (leads, contacts, deals, tasks) works, CRUD operations succeed, and the UI design is notably clean and modern. The invoicing module is a differentiator not commonly found in basic CRMs.
