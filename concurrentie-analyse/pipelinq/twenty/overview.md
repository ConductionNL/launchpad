# Twenty CRM - Competitive Analysis for Pipelinq

**Analyzed:** 2026-03-14
**Competitor:** Twenty (https://twenty.com)
**GitHub:** https://github.com/twentyhq/twenty (~40,400 stars, ~5,360 forks)
**License:** AGPL-3.0

## What is Twenty?

Twenty is the leading open-source CRM, positioning itself as a modern alternative to Salesforce. Built with TypeScript (React + NestJS + GraphQL + PostgreSQL), it offers both cloud hosting and self-hosting options. It emphasizes developer extensibility, clean UX (Notion/Linear-inspired), and community-driven development.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | NestJS (Node.js), TypeORM, PostgreSQL, ClickHouse (analytics) |
| API | GraphQL (primary), REST (auto-generated), MCP (planned for AI) |
| Frontend | React, Recoil state management, Vite |
| ORM | Custom "Twenty ORM" with workspace-scoped entities |
| Search | PostgreSQL tsvector full-text search |
| AI | Multi-provider SDK (OpenAI, Anthropic, Google, etc.) -- planned |
| Workflow | Custom engine with serverless code execution |
| Email | IMAP/SMTP + Google/Microsoft OAuth sync |
| Integrations | Zapier, webhooks, SDK, App platform (alpha) |
| Infra | Docker, Nx monorepo |

## Pricing

| Plan | Price | Key Features |
|------|-------|-------------|
| Free (Self-Hosted) | $0 | All core CRM, email/calendar sync, workflows, community support |
| Pro (Cloud) | ~$9/user/month | Same + hosted infra, standard support |
| Organization (Cloud) | ~$19/user/month | + SSO, row-level permissions, enhanced support |
| Organization (Self-Hosted) | Unknown | + SSO, row-level permissions on own infra |

## Feature Maturity Matrix

| Feature | Status | Pipelinq Comparison |
|---------|--------|-------------------|
| Pipeline/Kanban Management | Stable | Basic (Opportunities-only); Pipelinq can be more generic |
| Custom Data Model (18 field types) | Stable | Strong; comparable to OpenRegister schemas |
| Workflow Automation (7 triggers, 12 actions) | Stable | Good built-in; n8n has far more integrations (400+) |
| REST + GraphQL API (auto-generated) | Stable | Developer-friendly; OpenRegister has MCP advantage |
| App Marketplace & TypeScript SDK | Alpha | Forward-thinking; Nextcloud ecosystem is more mature |
| Email & Calendar Sync (Google/MS/SMTP) | Stable | Native sync; Nextcloud has full email/calendar apps |
| Dashboard & Reporting (6 widget types) | Beta | Basic charts; significant gaps (no tables/export) |
| AI Features (chatbot + agents) | Planned | Not shipped; OpenRegister MCP is production-ready |
| Permissions & RBAC (3-level cascade) | Stable | Comprehensive; Nextcloud SSO is free (no premium plan) |

## Key Strengths

1. **Developer experience:** Auto-generated APIs, GraphQL, built-in playground, TypeScript SDK
2. **Modern UI:** Clean, fast interface inspired by Notion/Linear design philosophy
3. **Self-hosting simplicity:** One-line Docker install, 2GB RAM minimum
4. **Community momentum:** 40K+ GitHub stars, active development
5. **Integrated workflows:** Built-in automation without external dependencies
6. **App platform vision:** SDK for custom objects, UI components, AI skills (alpha)
7. **Email/calendar sync:** Native integration with Google, Microsoft, generic providers
8. **Workspace-per-tenant:** PostgreSQL schema-based multi-tenancy

## Key Weaknesses

1. **Pipeline limitations:** Kanban tied to Opportunities only; no generic pipeline engine
2. **Workflow constraints:** Only 12 action types; limited ecosystem vs n8n's 400+
3. **Dashboards immature:** Beta, no tables, no export, no external sharing
4. **AI not shipped:** Chatbot and agents are planned but not available
5. **No document management:** No file storage, office suite, or document collaboration
6. **No real-time communication:** No chat, video, or messaging built in
7. **SSO requires paid plan:** Organization plan needed for SSO/SAML
8. **Small app ecosystem:** Marketplace is alpha; npm-based with no review process
9. **Email limitations:** Single recipient in workflows, no HTML signatures
10. **No multi-register isolation:** Workspace-level only, no register-level data partitioning

## Pipelinq Differentiators

### Platform Advantage (Nextcloud Ecosystem)
- Full file management, office suite, email, calendar, chat, video
- Mature app ecosystem with 1000+ apps
- Enterprise-grade SSO/LDAP included in all self-hosted plans
- Established user base of millions
- NL Design System for government/WCAG compliance

### Technical Advantage
- **MCP protocol:** AI-native data interaction (production-ready vs planned)
- **n8n integration:** 400+ node types vs 12 workflow actions
- **OpenRegister foundation:** JSON Schema standards, register-level isolation
- **Generic pipeline engine:** Pipelinq can apply pipelines to any object type
- **Data sovereignty:** Nextcloud compliance certifications, government-ready

### Gaps to Address
- Twenty's auto-generated API (REST + GraphQL) is very developer-friendly
- Twenty's TypeScript SDK and app scaffold experience is excellent
- Twenty's built-in workflow builder has a polished UI
- Twenty's kanban view with aggregations is well-executed
- Twenty's email/calendar sync is mature and well-documented

## Documentation Files

### Product Documentation (`docs/`)
- `overview.md` - Product overview, pricing, deployment, tech stack
- `data-model.md` - Objects, 18 field types, relationships, constraints
- `api-and-integrations.md` - REST, GraphQL, webhooks, SDK, third-party integrations
- `workflows-and-automation.md` - 7 triggers, 12 actions, patterns
- `views-and-pipelines.md` - Table, kanban, calendar views, dashboards
- `platform-and-extensibility.md` - App SDK, permissions, SSO, AI, email/calendar

### Feature Specs (`specs/`)
- `pipeline-management/` - Kanban pipeline analysis with Pipelinq comparison
- `custom-data-model/` - Objects, fields, relationships vs OpenRegister
- `workflow-automation/` - Workflow engine vs n8n integration
- `api-platform/` - REST + GraphQL vs OpenRegister API/MCP
- `app-marketplace/` - SDK and marketplace vs Nextcloud apps
- `email-calendar-sync/` - Email/calendar vs Nextcloud Mail/Calendar
- `dashboard-reporting/` - Dashboard and widgets analysis
- `ai-features/` - Planned AI vs OpenRegister MCP (production)
- `permissions-rbac/` - RBAC and SSO vs Nextcloud permissions
