# Sanity — Competitor Analysis

## Overview

- **Website:** https://sanity.io/
- **Open Source:** Partially (Studio=MIT)
- **Self-Hosted:** Partially
- **Summary:** Real-time collaborative content platform with GROQ query language

## Codebase

https://github.com/sanity-io/sanity (Sanity Studio — MIT license, 5.2k+ stars)

## Business Model

Hybrid open-source/SaaS model. Sanity Studio (the editing interface) is open-source MIT. The backend Content Lake is proprietary cloud-only — no self-hosting for the data layer. Revenue from per-user subscriptions ($15/user/month for Growth) combined with usage-based charges for API calls, bandwidth, assets, and documents. Enterprise contracts available with annual billing.

## Target Market

Content-heavy organizations needing real-time collaborative editing. Agencies and developers building custom content workflows. Enterprises managing structured content across multiple channels. Teams that value real-time collaboration and customizable editing experiences.

## Pricing

- **Free:** $0 — limited users and usage, suitable for personal projects
- **Growth:** $15/user/month — more datasets, higher API limits, additional features
- **Enterprise:** Custom pricing — unlimited seats, SAML SSO, custom roles, 99.9% SLA, dedicated support
- Usage-based components: API requests, CDN requests, assets, bandwidth, documents
- A 50-seat Growth team with all add-ons: ~$3,247/month (~$39K/year)

## Key Features

- GROQ query language (proprietary, powerful alternative to GraphQL)
- Real-time multi-user collaborative editing with live presence
- Sanity Studio: fully customizable React-based editing environment
- Structured content with reusable content blocks
- Content Lake (managed cloud data store with global CDN)
- Change tracking and revision history
- Portable Text (structured rich text format)
- Image pipeline with on-the-fly transformations
- Webhooks and GROQ-powered listeners for real-time events
- GraphQL API alongside GROQ
- Custom input components and plugins

## Feature Comparison with OpenRegister

| Feature | Sanity | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (custom schema) | Yes |
| Auto-generated REST APIs | Partial (GROQ/GraphQL) | Yes |
| Full-text search | Yes | Yes |
| Faceted search | Partial (GROQ filters) | Yes |
| RBAC | Yes | Yes |
| Audit trails | Yes (change tracking) | Yes |
| Multi-tenancy | Yes (datasets) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | Partial (AI Assist) | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (references) | Yes |
| Soft deletes | No | Yes |
| Time-travel queries | Partial (revisions) | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- Best-in-class real-time collaborative editing — multiple users can edit simultaneously with live presence
- GROQ query language is very powerful for content querying (more flexible than REST filters)
- Sanity Studio is MIT open-source and deeply customizable with React components

## Weaknesses

- Content Lake (data backend) is proprietary cloud-only — no self-hosting for the actual data
- Per-user pricing adds up quickly — a 50-person team costs ~$39K/year before usage charges
- GROQ is a proprietary query language — creates vendor lock-in, no standard compatibility

## Notes

Strong position in the content platform market. The split model (open Studio, closed backend) is unusual. GROQ is genuinely powerful but proprietary. Real-time collaboration is the standout feature. The inability to self-host the data layer is a dealbreaker for government use cases requiring data sovereignty. Competes directly with Contentful and Hygraph.
