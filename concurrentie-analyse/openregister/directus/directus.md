# Directus — Competitor Analysis

## Overview

- **Website:** https://directus.io/
- **Open Source:** Yes (BSL 1.1 -> MIT after 3 years)
- **Self-Hosted:** Yes
- **Summary:** Wraps any SQL database with instant REST/GraphQL APIs and admin UI

## Codebase

https://github.com/directus/directus (29k+ stars)

## Business Model

Open-core with BSL 1.1 license (converts to MIT after 3 years). Revenue from Directus Cloud (managed hosting) and Enterprise licenses. Cloud plans range from starter to enterprise. Self-hosted community edition is free with unrestricted access. Enterprise Cloud starts at $15,000/year with annual commitment. Premium support available for production license holders.

## Target Market

Developers and agencies needing a data platform that wraps existing SQL databases. Enterprises wanting a headless CMS with full API access. Teams that need both REST and GraphQL APIs auto-generated from their database schema. Organizations requiring 15+ global deployment regions.

## Pricing

- **Free (self-hosted):** Community edition, unrestricted product access
- **Starter (cloud):** $15/month
- **Professional (cloud):** $99/month
- **Business (cloud):** $499/month
- **Enterprise (cloud):** Starting at $15,000/year — custom regions, clusters, premium support

## Key Features

- Instant REST and GraphQL APIs from any SQL database (PostgreSQL, MySQL, SQLite, MS SQL, MariaDB, CockroachDB)
- No-code admin UI with customizable layouts and dashboards
- Granular role-based access control with custom permissions
- Built-in file storage with asset transformations
- Flows (visual automation builder) with triggers and operations
- Webhooks and event hooks
- Internationalization and content translations
- Schema migration and versioning
- Real-time updates via WebSockets
- Extension system for custom interfaces, layouts, modules, and endpoints
- 15+ global deployment regions (Enterprise)

## Feature Comparison with OpenRegister

| Feature | Directus | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (SQL introspection) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Yes | Yes |
| Faceted search | Partial (filters) | Yes |
| RBAC | Yes | Yes |
| Audit trails | Yes (revisions) | Yes |
| Multi-tenancy | Partial (roles/permissions) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (M2M, O2M, M2O) | Yes |
| Soft deletes | Partial (archive) | Yes |
| Time-travel queries | Partial (revisions) | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Wraps existing SQL databases without migration — introspects schema and generates APIs automatically
- Both REST and GraphQL APIs generated simultaneously, offering maximum frontend flexibility
- Mature and polished admin UI with visual flow builder, file management, and dashboard creation

## Weaknesses

- BSL 1.1 license is not truly open source — restricts production use of newer versions for competing products
- No support for JSON Schema, JSON-LD, or linked data standards
- No government-specific compliance features or Nextcloud integration

## Notes

Very well-funded and polished product. The BSL license is a significant consideration — while the code is source-available, it is not OSI-approved open source. After 3 years, each version converts to MIT. Strong developer community (29k+ stars). One of the most feature-complete headless CMS platforms available.
