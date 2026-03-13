# Appwrite — Competitor Analysis

## Overview

- **Website:** https://appwrite.io/
- **Open Source:** Yes (BSD-3-Clause)
- **Self-Hosted:** Yes
- **Summary:** Self-hosted BaaS with databases, auth, storage, and functions

## Codebase

https://github.com/appwrite/appwrite (55k+ stars)

## Business Model

Open-core model with BSD-3-Clause license. Revenue from Appwrite Cloud (managed hosting) with per-project pricing model (changed from per-seat in 2025). Free tier available with limited projects. Pro and Scale plans include unlimited seats. Usage-based pricing for database operations, bandwidth, and storage beyond included limits.

## Target Market

Developers building web, mobile, and AI applications who need a self-hosted or managed backend. Teams wanting an alternative to Firebase with full self-hosting capability. Organizations that need auth, databases, storage, and functions in one platform.

## Pricing

- **Free:** $0 — 2 projects/org, 500K reads/250K writes per month, unlimited seats
- **Pro:** $25/month per project — 1.75M reads/750K writes, 2 TB bandwidth, unlimited seats
- **Scale:** Higher limits, more resources per project
- **Enterprise:** Custom pricing
- Additional reads: $0.06/100K, additional writes: $0.10/100K
- Additional bandwidth: $15/100 GB, storage: $2.80/100 GB

## Key Features

- Document-based NoSQL database with collections and attributes
- Built-in authentication with 30+ OAuth providers, email, phone, anonymous
- File storage with CDN and image previews
- Serverless functions (Node.js, Python, PHP, Ruby, Dart, etc.)
- Real-time subscriptions via WebSockets
- Messaging service (email, SMS, push notifications)
- Team management and permissions
- Database indexes and complex queries
- Multi-platform SDKs (Web, Flutter, iOS, Android, etc.)
- Built-in security (rate limiting, encryption, HTTPS)

## Feature Comparison with OpenRegister

| Feature | Appwrite | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (document-based) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Yes | Yes |
| Faceted search | No | Yes |
| RBAC | Yes (teams/roles) | Yes |
| Audit trails | Partial (logs) | Yes |
| Multi-tenancy | Yes (projects) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (relationships) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Complete BaaS platform (auth, database, storage, functions, messaging) in a single self-hosted Docker deployment
- Unlimited seats on all paid plans — per-project pricing is cost-effective for larger teams
- Multi-platform SDKs for every major platform (Web, Flutter, iOS, Android, etc.)

## Weaknesses

- Document-based database lacks the relational modeling power and SQL querying of PostgreSQL-based alternatives
- No AI/vector search capabilities, semantic search, or open data standard support
- Usage-based pricing for database operations can be unpredictable for write-heavy applications

## Notes

Large community (55k+ stars). The 2025 pricing change from per-seat to per-project was well-received. BSD-3-Clause license is very permissive. Competes directly with Firebase and Supabase. The document-based database is simpler to use but less powerful than PostgreSQL for complex queries and analytics. Strong multi-platform SDK support is a key differentiator.
