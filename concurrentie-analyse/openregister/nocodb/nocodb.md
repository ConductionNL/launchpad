# NocoDB — Competitor Analysis

## Overview

- **Website:** https://nocodb.com/
- **Open Source:** Yes (AGPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** Turns any SQL database into a spreadsheet-like interface with REST APIs

## Codebase

https://github.com/nocodb/nocodb (61k+ stars)

## Business Model

Open-core model. The core product is open source (AGPL-3.0) and free to self-host. Revenue comes from NocoDB Cloud (hosted SaaS) and enterprise features. Raised $10.5M in seed funding. The cloud offering uses a "pay for 9, get unlimited" seat pricing model where you never pay for more than 9 editor seats regardless of team size.

## Target Market

SMBs and teams looking for an Airtable alternative. Business users who want spreadsheet-like interfaces on top of existing SQL databases. Non-technical users needing no-code data management. Over 12 million Docker downloads.

## Pricing

- **Free (self-hosted):** Unlimited, full open-source features
- **Free (cloud):** Unlimited bases, 1,000 records/workspace, 3 editors, 10 commenters, 1,000 API calls/month
- **Team (cloud):** Starting at $228/year, more records and API calls
- **Business (cloud):** Advanced features, higher limits
- Annual billing saves 20% vs monthly

## Key Features

- Spreadsheet-like UI on top of any MySQL, PostgreSQL, SQL Server, or SQLite database
- Multiple views: Grid, Kanban, Gallery, Calendar, Form
- Auto-generated REST APIs with Swagger documentation
- Role-based access control and workspace management
- Webhooks and automations
- Shared views and form views for data collection
- Rich field types including attachments, lookups, rollups, formulas
- Real-time collaboration
- Import from Airtable, CSV, Excel
- App store with integrations (Slack, Discord, email, etc.)

## Feature Comparison with OpenRegister

| Feature | NocoDB | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (SQL-based) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Partial (DB-level) | Yes |
| Faceted search | No | Yes |
| RBAC | Yes | Yes |
| Audit trails | Partial (enterprise) | Yes |
| Multi-tenancy | Yes (workspaces) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (links) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Massive community (61k+ GitHub stars, 12M+ Docker downloads) and very mature ecosystem
- Connects directly to existing SQL databases without data migration — works as a layer on top
- Intuitive spreadsheet UI makes it accessible to non-technical users with minimal learning curve

## Weaknesses

- No JSON Schema support — relies on traditional SQL schema modeling rather than standard data contracts
- No built-in semantic search, vector embeddings, or AI-powered features
- No government API compliance (NLGov) or linked data support (JSON-LD), making it unsuitable for Dutch government use cases

## Notes

One of the most popular open-source no-code database tools. Strong Airtable migration path. Very active development with frequent releases. The AGPL license may be a concern for some enterprise users who want to embed it in proprietary products.
