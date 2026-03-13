# Supabase — Competitor Analysis

## Overview

- **Website:** https://supabase.com/
- **Open Source:** Yes (Apache 2.0)
- **Self-Hosted:** Yes
- **Summary:** Open-source Firebase alternative on PostgreSQL with REST/GraphQL APIs

## Codebase

https://github.com/supabase/supabase (78k+ stars, 146 repositories in the organization)

## Business Model

Open-core model with Apache 2.0 license. Revenue primarily from Supabase Cloud (managed hosting) with hybrid pricing: base subscription fee plus usage-based charges. Well-funded startup ($116M+ raised). Free tier available. Self-hosting is free but requires managing your own infrastructure. Transparent cost structure with usage dashboards and spend caps.

## Target Market

Developers building web, mobile, and AI applications who want a Firebase alternative on PostgreSQL. Startups and scale-ups needing a managed backend platform. Full-stack developers wanting instant APIs, auth, and real-time capabilities without managing infrastructure.

## Pricing

- **Free:** $0 — 2 projects, 500 MB database, 50,000 MAUs, 1 GB storage
- **Pro:** $25/month + usage — 8 GB database, 100K MAUs, 100 GB storage
- **Team:** $599/month — Pro features with team collaboration tools
- **Enterprise:** Custom pricing — HIPAA compliance, dedicated support, SLAs
- Usage-based overages for bandwidth, storage, and compute beyond included limits
- Default spend cap prevents unexpected bills

## Key Features

- Instant REST APIs via PostgREST (auto-generated from PostgreSQL schema)
- GraphQL API via pg_graphql extension
- Built-in authentication with 30+ OAuth providers and email/phone auth
- Real-time subscriptions via WebSockets (Postgres CDC)
- Edge Functions (Deno-based serverless functions)
- File storage with CDN and image transformations
- Vector embeddings and AI integration (pgvector)
- Row-level security (RLS) for fine-grained access control
- Database branching and migrations
- Supabase Studio (web-based database management UI)

## Feature Comparison with OpenRegister

| Feature | Supabase | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (PostgreSQL DDL) | Yes |
| Auto-generated REST APIs | Yes (PostgREST) | Yes |
| Full-text search | Yes (PostgreSQL FTS) | Yes |
| Faceted search | Partial (custom queries) | Yes |
| RBAC | Yes (RLS) | Yes |
| Audit trails | Partial (custom) | Yes |
| Multi-tenancy | Partial (RLS-based) | Yes |
| Webhooks / Events | Yes (database webhooks) | Yes |
| AI / Vector embeddings | Yes (pgvector) | Yes |
| Semantic search | Yes (pgvector) | Yes |
| Object relations | Yes (foreign keys) | Yes |
| Soft deletes | No (custom) | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Comprehensive BaaS platform (auth, database, storage, functions, real-time) in one package
- Built on PostgreSQL with pgvector for AI/vector search — one of the few competitors with native AI capabilities
- Massive community (78k+ stars), excellent documentation, and very generous free tier

## Weaknesses

- Requires PostgreSQL knowledge for advanced use — not a true no-code solution
- No support for open data standards (JSON Schema, JSON-LD) or government API compliance
- Usage-based pricing can become expensive at scale (though spend caps help)

## Notes

One of the fastest-growing open-source projects. The PostgreSQL foundation means data is never locked in. Very strong developer experience with excellent documentation and client libraries for every major language/framework. The pgvector integration for AI is a genuine competitive advantage. 42:1 cost advantage over Firebase for read-heavy workloads. Not a traditional CMS or no-code platform — more of a developer-focused BaaS.
