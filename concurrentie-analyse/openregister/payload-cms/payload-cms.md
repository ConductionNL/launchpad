# Payload CMS — Competitor Analysis

## Overview

- **Website:** https://payloadcms.com/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** TypeScript/React headless CMS with code-first schema config

## Codebase

https://github.com/payloadcms/payload (41k+ stars)

## Business Model

Fully open-source (MIT) with no per-seat or per-project software fees when self-hosted. Revenue from Payload Cloud (managed hosting) and enterprise support. Acquired by Figma, which may influence future cloud strategy. Self-hosted is completely free — you only pay for your own hosting infrastructure.

## Target Market

TypeScript/React developers building content-driven applications. Teams wanting a code-first CMS that integrates directly into their Next.js stack. Agencies and enterprises needing a developer-friendly CMS with full control over the admin panel.

## Pricing

- **Free (self-hosted):** Completely free, MIT license, no software fees
- **Cloud Standard:** $35/month — 512MB RAM, 3GB database, 30GB file storage
- **Cloud Pro:** $199/month — dedicated cluster, 30GB database, 150GB file storage
- **Enterprise:** Custom pricing

## Key Features

- Code-first configuration in TypeScript (no GUI schema builder needed)
- Runs natively on Next.js (Payload 3.0) for full-stack development
- Auto-generated REST and GraphQL APIs
- Fully customizable React admin panel
- Built-in authentication with access control
- File uploads with image resizing and optimization
- Content versioning with drafts and autosave
- Localization and internationalization
- Rich text editor with Slate or Lexical
- Hooks system for extending functionality at any lifecycle point
- Supports PostgreSQL, MongoDB, and SQLite

## Feature Comparison with OpenRegister

| Feature | Payload CMS | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (code-first TS) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Partial (basic) | Yes |
| Faceted search | No | Yes |
| RBAC | Yes | Yes |
| Audit trails | Yes (versions) | Yes |
| Multi-tenancy | Yes (plugin) | Yes |
| Webhooks / Events | Yes (hooks) | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Yes | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | Partial (versions) | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Deep Next.js integration (runs as a Next.js app) — ideal for modern React full-stack development
- Code-first TypeScript config gives developers full type safety and version-controlled schemas
- MIT license with no per-seat fees — completely free to self-host at any scale

## Weaknesses

- Requires TypeScript/React expertise — not accessible to non-developers
- No GUI-based schema builder — all configuration done in code
- No support for open data standards or government API compliance

## Notes

Rapidly growing (41k+ stars). The Figma acquisition is a significant event that may shape future direction. Payload 3.0 running natively on Next.js is a major differentiator in the CMS space. Very developer-focused — not suitable for non-technical users. The code-first approach means schemas are version-controlled in Git, which is powerful but requires development skills.
