# Strapi — Competitor Analysis

## Overview

- **Website:** https://strapi.io/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Leading open-source headless CMS with schema-driven content modeling

## Codebase

https://github.com/strapi/strapi (71.5k+ stars)

## Business Model

Open-core with MIT license for the community edition. Revenue comes from Strapi Cloud (managed hosting) and Enterprise Edition licenses (per admin-user subscription). CMS features are separated from hosting — you choose a CMS plan (Community/Growth/Enterprise) and hosting separately. Enterprise features include SSO, audit logs, and dedicated support.

## Target Market

Developers and agencies building content-driven websites and applications. Marketing teams needing a user-friendly content management interface. Enterprises requiring headless CMS with API-first content delivery. The most popular open-source headless CMS with the largest community.

## Pricing

- **Community (self-hosted):** Free, MIT license
- **Cloud Free:** $0 — limited resources, good for testing
- **Cloud Essential:** $15/month (annual billing)
- **Cloud Pro:** Higher limits, more resources
- **Cloud Scale:** Production-ready with scaling
- **Growth CMS:** Per-user licensing for advanced CMS features
- **Enterprise CMS:** Per admin-user, minimum 5 users for Silver — SSO, audit logs, SLAs, CSM

## Key Features

- Visual content-type builder for schema modeling
- Auto-generated REST and GraphQL APIs
- Customizable admin panel with WYSIWYG editors
- Role-based access control with granular permissions
- Media library with asset management
- Internationalization (i18n) for multilingual content
- Webhooks for event-driven integrations
- Plugin marketplace with 100+ community plugins
- Content versioning and draft/publish workflow
- TypeScript support (100% JavaScript/TypeScript)
- SSO, audit logs, review workflows (Enterprise)

## Feature Comparison with OpenRegister

| Feature | Strapi | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (custom schema) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Partial (basic filters) | Yes |
| Faceted search | No | Yes |
| RBAC | Yes | Yes |
| Audit trails | Yes (Enterprise) | Yes |
| Multi-tenancy | Partial (plugin) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (relations) | Yes |
| Soft deletes | No (draft/publish) | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Largest open-source headless CMS community (71.5k+ GitHub stars) with rich plugin ecosystem (100+ plugins)
- MIT license is maximally permissive — no restrictions on commercial use or embedding
- Visual content-type builder makes schema creation accessible to non-developers

## Weaknesses

- Content-focused rather than data-focused — optimized for CMS use cases, not general-purpose data management
- No support for open data standards (JSON Schema, JSON-LD, linked data) or government API compliance
- Enterprise features (audit logs, SSO) require paid license

## Notes

The dominant open-source headless CMS. 2026 roadmap focuses on quality, DX, and editing experience. Strapi Cloud is migrating to new infrastructure. The separation of CMS licensing from hosting is a relatively new pricing model. Acquired significant funding. Very large ecosystem but specifically CMS-focused rather than general data management.
