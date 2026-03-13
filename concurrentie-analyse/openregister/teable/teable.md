# Teable — Competitor Analysis

## Overview

- **Website:** https://teable.io/
- **Open Source:** Yes (AGPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** High-performance no-code database on PostgreSQL with spreadsheet UI

## Codebase

https://github.com/teableio/teable (20.8k+ stars)

## Business Model

Open-core model. Core is open source under AGPL-3.0 with a separate Enterprise Edition for advanced features. Revenue from cloud SaaS subscriptions (per-registered-seat pricing) and enterprise licenses. Self-hosted pricing is based on the number of registered users, regardless of role. External form participants do not count as seats. Teable 2.0 rebranded as "The AI Database Agent."

## Target Market

Teams looking for a high-performance Airtable alternative built on real PostgreSQL. Developers who want direct SQL access to their no-code data. Organizations needing AI-powered data management with spreadsheet simplicity.

## Pricing

- **Free:** Limited features and users
- **Plus (cloud):** $10/user/month
- **Pro (cloud):** $20/user/month
- **Enterprise:** Custom pricing, available for both cloud and self-hosted
- Self-hosted pricing is per registered seat

## Key Features

- Spreadsheet UI built on native PostgreSQL (data stored in real Postgres tables)
- Multiple views: Grid, Kanban, Gallery, Calendar, Form
- AI Database Agent for intelligent data operations (sentiment tagging, auto-replies)
- Direct SQL access to underlying data
- Real-time collaboration
- REST API with type-safe access
- Field types including attachments, links, lookups, rollups
- Automations and integrations
- Row-level permissions
- Import from Airtable, CSV

## Feature Comparison with OpenRegister

| Feature | Teable | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (PostgreSQL native) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Yes (PostgreSQL) | Yes |
| Faceted search | No | Yes |
| RBAC | Yes | Yes |
| Audit trails | Partial | Yes |
| Multi-tenancy | Yes (spaces) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | Partial (AI agent) | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (links) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Data stored in real PostgreSQL tables, enabling direct SQL queries alongside the no-code interface
- High performance for large datasets thanks to native PostgreSQL backend
- Affordable pricing ($10-20/user/month) compared to many competitors

## Weaknesses

- Relatively young project — less mature ecosystem and smaller plugin library
- No open data standard support (JSON Schema, JSON-LD) or government compliance
- AGPL-3.0 license may be restrictive for some enterprise embedding scenarios

## Notes

Fast-growing project (20k+ stars). The PostgreSQL-native approach is a differentiator — data is stored in real Postgres tables rather than a proprietary format, giving users direct SQL access. The AI Database Agent rebrand with Teable 2.0 shows a push toward AI-powered data management. TypeScript-based.
