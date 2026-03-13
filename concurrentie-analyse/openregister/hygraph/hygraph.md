# Hygraph — Competitor Analysis

## Overview

- **Website:** https://hygraph.com/
- **Open Source:** No
- **Self-Hosted:** No (cloud)
- **Summary:** GraphQL-native headless CMS with content relationships and federation

## Business Model

Pure SaaS with cloud-only deployment. Revenue from tiered subscriptions based on content entries, models, locales, and team size. Self-service plans for smaller teams, enterprise plans for larger organizations. Previously known as GraphCMS — rebranded to Hygraph. Positioned as the "GraphQL-native" headless CMS for enterprise teams.

## Target Market

Engineering teams building GraphQL-first content architectures. Enterprise teams managing complex digital ecosystems across brands, regions, and commerce platforms. Organizations needing content federation (combining content from multiple sources via a single GraphQL API). Companies invested in the GraphQL ecosystem.

## Pricing

- **Free (Developer):** $0 — personal/small-scale projects, limited entries and models
- **Professional:** $299/month — up to 50,000 entries, 75 content models, 8 locales
- **Scale:** $799/month — higher limits, more environments, advanced features
- **Enterprise:** Custom pricing — unlimited entries, custom SLA, dedicated support
- Content versioning and periodic backups included

## Key Features

- GraphQL-native API (not a REST-to-GraphQL wrapper)
- Schema builder with visual content modeling
- Content federation: combine content from remote sources (REST, GraphQL) into a single API
- Custom roles and permissions with granular access control
- Multi-environment support (dev, staging, production)
- Content staging with custom workflows
- Localization with multi-locale support
- Built-in asset management with transformations
- Webhooks for event-driven integrations
- GraphQL Management API and SDK
- Content versioning and periodic backups
- Globally distributed CDN

## Feature Comparison with OpenRegister

| Feature | Hygraph | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (GraphQL schema) | Yes |
| Auto-generated REST APIs | No (GraphQL only) | Yes |
| Full-text search | Yes | Yes |
| Faceted search | Partial (GraphQL filters) | Yes |
| RBAC | Yes | Yes |
| Audit trails | Partial (versioning) | Yes |
| Multi-tenancy | Yes (environments) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (relations/refs) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | Partial (versioning) | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Content federation is unique — combine content from multiple external sources into a single GraphQL API
- True GraphQL-native architecture (not a REST API with a GraphQL layer) for maximum GraphQL performance
- Multi-environment support with content staging for complex deployment workflows

## Weaknesses

- Cloud-only with no self-hosting — data sovereignty issues for government use
- GraphQL-only with no REST API — limits integration with REST-based systems and tools
- Expensive starting at $299/month for Professional, making it inaccessible for smaller teams

## Notes

German company (formerly GraphCMS). The content federation feature is genuinely unique — the ability to combine content from external REST/GraphQL sources into a single GraphQL endpoint is valuable for enterprise architectures. However, the cloud-only model, GraphQL-only API, and high pricing make it unsuitable for government use cases. Competes directly with Contentful and Sanity in the enterprise headless CMS space.
