# Directus Overview

**Source:** https://docs.directus.io/getting-started/overview.html

## What is Directus

Directus is a backend for building projects. Connect it to your database, asset storage, and external services, and immediately receive rich developer tooling (Data Engine) and a comprehensive web application (Data Studio) to work with your data. Granular and powerful access control means users can only see, interact, and create data allowed by their role.

## Architecture

Directus has two main components:
- **Data Engine** — The backend that provides auto-generated REST + GraphQL APIs, event-driven automations, user management, authentication, real-time capabilities, and webhooks
- **Data Studio** — A comprehensive web application (admin UI) for working with data, including content editing, file management, insights dashboards, and settings

## Use Cases

1. **Backend as a Service (BaaS)** — Auto-generated APIs, automations, authentication, real-time, webhooks
2. **Headless CMS** — Content is just data from a database; deliver across websites, apps, kiosks, digital signage
3. **Internal Tool Builder** — Build custom back-office apps, admin panels, and dashboards using Directus Insights (no-code)
4. **Data Management and Analytics** — Single source of truth, no-code analytics dashboards, data synchronization via Directus Automate

## Directus Cloud

- Scalable, optimized storage and infrastructure
- Automatic updates
- Team-based project management
- Projects run in about 90 seconds
- Auto-scaling for reliability

### Cloud Tiers

| Feature | Starter | Professional | Enterprise |
|---------|---------|-------------|------------|
| Studio Users Included | 1 | 5 | Custom |
| API Requests | 50,000 | 250,000 | Custom |
| Database Entries | 5,000 | 75,000 | Custom |
| Max Studio Users | 5 | 15 | Custom |

## Pricing (as of March 2026)

### Self-Hosted
- **Free** for entities with total annual finances under $5,000,000 USD
- **Commercial license required** for entities over $5M in production use
- BSL (Business Source License) — source code becomes open-source (GPL-compatible) after 3 years

### Cloud
- **Professional:** $99/month (billed annually)
  - 5 Studio Users included (+$15/user additional)
  - 75,000 DB entries
  - 250,000 API requests/month
  - 500 GB API bandwidth/month
  - 500 MB max asset size
  - 150 GB total file storage
  - 3 region availability
  - Shared database + application servers
- **Enterprise:** Custom pricing
  - Custom users, entries, requests, storage
  - 20+ region availability
  - Dedicated database + application servers
  - Custom domain (fully)
  - Failover instances
  - Uptime SLA
  - Premium Support (+$300/month)

### Premium Support
- Customer Success Manager as single point of contact
- Hands-on advisory sessions
- 24/7 support for critical issues

## License
- BSL (Business Source License) — NOT fully open source
- Free for non-production use and entities under $5M annual finances
- Commercial license required for production use by entities over $5M
- Source code automatically becomes GPL-compatible after 3 years

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| License | BSL (restrictive for large orgs) | EUPL (true open source) |
| Architecture | Standalone Node.js app | Nextcloud app (integrated ecosystem) |
| Database | PostgreSQL, MySQL, SQLite, etc. | PostgreSQL/MySQL via Nextcloud |
| API | REST + GraphQL (auto-generated) | REST (OAS-based, auto-generated) |
| UI | Custom Data Studio | Nextcloud-integrated admin |
| Cloud | Managed cloud with tiers | Self-hosted via Nextcloud |
| Pricing | $99+/mo cloud, BSL self-hosted | Free (EUPL) |
