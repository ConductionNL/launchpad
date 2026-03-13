# KeystoneJS — Competitor Analysis

## Overview

- **Website:** https://keystonejs.com/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Node.js headless CMS with code-defined schemas and GraphQL APIs

## Codebase

https://github.com/keystonejs/keystone (9.8k+ stars)

## Business Model

Fully open-source project (MIT license) with no commercial cloud offering or paid tiers. Free to use forever with no lock-in. Revenue model relies on enterprise support contracts from the maintainers (Thinkmill). No SaaS or hosted service — self-hosting only. Enterprise-level support available from the core team.

## Target Market

Node.js developers building content-driven applications with GraphQL. Teams that want a code-first CMS with strong TypeScript support. Developers who prefer GraphQL over REST APIs. Projects needing a flexible schema system that lives in code.

## Pricing

- **Self-hosted:** Completely free, MIT license, no paid tiers
- **Enterprise Support:** Available from Thinkmill (the team behind Keystone) — custom pricing
- No cloud hosting offering — you bring your own infrastructure

## Key Features

- Code-first schema definition in TypeScript/JavaScript
- Auto-generated GraphQL API with session management, access control, pagination, sorting, filtering
- Flexible schema design with rich field types
- Built-in Admin UI (React-based) auto-generated from schema
- Access control with session-based authentication
- Database support for PostgreSQL and MongoDB (via Prisma)
- Image and file management
- Hooks system for custom logic at CRUD lifecycle points
- Document fields with rich text editing (Slate-based)
- Virtual fields for computed values
- Relationship fields (one-to-many, many-to-many)

## Feature Comparison with OpenRegister

| Feature | KeystoneJS | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (code-first) | Yes |
| Auto-generated REST APIs | No (GraphQL only) | Yes |
| Full-text search | Partial (DB-level) | Yes |
| Faceted search | No | Yes |
| RBAC | Yes | Yes |
| Audit trails | No | Yes |
| Multi-tenancy | No | Yes |
| Webhooks / Events | Partial (hooks) | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- GraphQL-first approach with excellent auto-generated GraphQL API including filtering, sorting, and pagination
- Code-first TypeScript schemas provide full type safety and version-controlled configuration
- MIT license with no commercial restrictions — completely free at any scale

## Weaknesses

- GraphQL only — no REST API option, which limits integration with REST-based systems
- No cloud hosting offering — requires teams to manage their own infrastructure
- Smaller feature set compared to competitors — no audit trails, multi-tenancy, or content versioning

## Notes

Maintained by Thinkmill, an Australian consultancy. Development pace has slowed compared to 2020-2022 peak. The GraphQL-only approach is a strong differentiator but also a limitation. Good for GraphQL-native projects but not suitable for teams that need REST APIs. Uses Prisma ORM for database abstraction.
