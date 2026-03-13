# Squidex — Competitor Analysis

## Overview

- **Website:** https://squidex.io/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** API-first headless CMS with schema-driven content modeling and search

## Codebase

https://github.com/Squidex/squidex (2.3k+ stars)

## Business Model

100% open source (MIT license) built by independent developers. Revenue from Squidex Cloud (managed hosting) using a fair pay-per-usage model based on infrastructure costs (servers and traffic). No feature gating between self-hosted and cloud — the difference is only in managed infrastructure.

## Target Market

Developers building API-first content applications. Teams needing a headless CMS with strong content versioning and workflow capabilities. Organizations requiring multilingual content management with custom workflows.

## Pricing

- **Free (self-hosted):** Full features, MIT license
- **Cloud Free:** Limited usage tier
- **Cloud Paid:** Starting at ~$112/month (pay-per-usage)
- Additional traffic: ~0.13 EUR/GB beyond included limits
- Additional API calls: ~0.20 EUR/1,000 calls beyond included limits

## Key Features

- Schema-driven content modeling with JSON-based schemas
- REST API with OData filter support and Swagger/OpenAPI documentation
- GraphQL API for flexible querying
- Full version history with comparison and rollback
- Customizable workflow engine for content review and publishing
- Built-in full-text search
- Multilingual content with localization support
- Asset management with image transformations
- Scripting engine for custom validations and transformations
- Detailed audit logs showing who did what and when
- OpenID Connect authentication support
- Rule engine for event-driven actions

## Feature Comparison with OpenRegister

| Feature | Squidex | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | Partial (JSON-based) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Yes | Yes |
| Faceted search | Partial (OData filters) | Yes |
| RBAC | Yes | Yes |
| Audit trails | Yes | Yes |
| Multi-tenancy | Yes (apps) | Yes |
| Webhooks / Events | Yes (rules) | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (references) | Yes |
| Soft deletes | Yes (archive) | Yes |
| Time-travel queries | Yes (versioning) | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Full version history with comparison and rollback — strong content versioning capabilities
- Customizable workflow engine that can model complex multi-step review processes
- OData filter support on REST API provides powerful querying without GraphQL

## Weaknesses

- Small community (2.3k stars) — limited ecosystem, fewer integrations and plugins
- .NET/C# backend may limit contribution from the predominantly JavaScript/Python developer community
- No government API compliance, linked data support, or AI features

## Notes

Solid but niche product. Built with .NET/C# (unusual for the headless CMS space which is dominated by Node.js). The OData filter support is a differentiator. Pay-per-usage cloud pricing is transparent but can be unpredictable for high-traffic applications. MIT license is very permissive. Small but dedicated community.
