# PocketBase — Competitor Analysis

## Overview

- **Website:** https://pocketbase.io/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Lightweight single-binary backend with real-time database and admin UI

## Codebase

https://github.com/pocketbase/pocketbase (56k+ stars)

## Business Model

Personal open-source project with no paid team or company behind it. Developed entirely on volunteer basis. No commercial cloud offering — the software is free and you only pay for hosting (a $5 VPS is sufficient for small to medium applications). Third-party managed hosting available (PocketHost at $5/instance). No enterprise license or support contracts.

## Target Market

Solo developers and small teams building side projects, MVPs, and small-to-medium applications. Developers wanting the simplest possible backend setup (single binary). Hobbyists and indie hackers who need auth, database, and real-time in one file.

## Pricing

- **Self-hosted:** Completely free, MIT license
- **No official cloud offering**
- Third-party hosting: PocketHost ~$5/instance
- A $5 VPS is sufficient for most use cases

## Key Features

- Single binary deployment — no dependencies, no Docker required
- Embedded SQLite database with schema builder
- Real-time subscriptions via SSE
- Built-in authentication with 15+ OAuth2 providers
- Admin UI for collection and record management
- REST API auto-generated from schema
- File storage (local filesystem or S3-compatible)
- Data validation and field types
- Expandable as a Go framework/library for custom business logic
- Backup and restore
- Email sending (SMTP)

## Feature Comparison with OpenRegister

| Feature | PocketBase | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (SQLite schema) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Partial (SQLite FTS) | Yes |
| Faceted search | No | Yes |
| RBAC | Partial (basic roles) | Yes |
| Audit trails | No | Yes |
| Multi-tenancy | No | Yes |
| Webhooks / Events | Partial (hooks via Go) | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (relations) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Extreme simplicity — single binary, no dependencies, runs anywhere in seconds
- Very lightweight resource usage — $5 VPS sufficient for production workloads
- MIT license, completely free, with a passionate and growing community (56k+ stars)

## Weaknesses

- SQLite-based — not suitable for high-concurrency write workloads or horizontal scaling
- No enterprise features (audit trails, advanced RBAC, multi-tenancy, SSO)
- Personal project with no company backing — risk of abandonment, no SLAs or professional support

## Notes

Extraordinarily popular for its simplicity (56k+ stars, 200+ new stars/day in 2025). Written in Go. The single-binary approach is its killer feature — developers can spin up a full backend in seconds. However, SQLite limits scalability and the lack of enterprise features make it unsuitable for production enterprise use. Ideal for prototyping and small projects.
