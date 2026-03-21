# Directus Competitive Analysis Summary

**Analysis Date:** 2026-03-14
**Competitor:** Directus (https://directus.io)
**Version Analyzed:** Directus 11.x
**License:** BSL 1.1 (Business Source License) — free for entities under $5M annual finances

## Executive Summary

Directus is a mature, feature-rich data platform that auto-generates REST and GraphQL APIs from any SQL database. It positions itself as a "Backend as a Service" and "Headless CMS." Key differentiators versus OpenRegister include its GraphQL API, real-time WebSocket subscriptions, built-in analytics dashboards (Insights), comprehensive row-level security, content versioning, on-the-fly image transformations, a visual automation engine (Flows), and an extension marketplace.

## Feature Comparison Matrix

| Feature Area | Directus | OpenRegister | Gap Level |
|-------------|----------|-------------|-----------|
| **Data Model** | Database mirroring, visual UI | JSON Schema-based | Different approach |
| **REST API** | Auto-generated from DB | Auto-generated from schemas | Comparable |
| **GraphQL API** | Native, auto-generated | Not available | **High** |
| **Filtering** | 30+ operators, geospatial | Basic OData-style | **Medium-High** |
| **Real-time** | WebSockets + GraphQL subscriptions | Not available | **High** |
| **Access Control** | Row/field-level, policies | Nextcloud groups, register-level | **High** |
| **Automation** | Built-in Flows engine | n8n integration | Comparable (different) |
| **Dashboards** | Built-in Insights module | Not available | **Medium** |
| **File Management** | Built-in with transforms | Nextcloud file system | Comparable |
| **Image Transforms** | On-the-fly Sharp-based | Not available | **Medium** |
| **Authentication** | Email, OAuth2, OpenID, LDAP, SAML | Via Nextcloud auth | Comparable |
| **Content Versioning** | Built-in per collection | Not available | **Medium** |
| **Extensions** | 10 types + marketplace | Nextcloud App Store | Different ecosystem |
| **AI Features** | Built-in assistant + MCP | Basic MCP endpoint | **Medium** |
| **SDK** | Official JavaScript SDK | No official SDK | **Medium** |
| **Collaborative Editing** | Built-in | Not available | **Medium** |
| **Translations** | Built-in relationship type | Manual schema design | **Low-Medium** |
| **Self-Hosting** | Docker-based | Nextcloud app | Comparable |
| **Cloud Hosting** | Managed cloud ($99+/mo) | Not available | N/A |

## Key Competitive Advantages of Directus

1. **GraphQL API** — Auto-generated, type-safe, with subscriptions
2. **Real-time** — WebSocket subscriptions for live updates
3. **Row-level security** — Filter-based item permissions with dynamic variables
4. **Visual Flows** — Built-in no-code automation with 14+ operation types
5. **Insights dashboards** — No-code analytics with drag-and-drop panels
6. **Image transformations** — On-the-fly with Sharp API access
7. **Extension marketplace** — 10 granular extension types with npm-based registry
8. **Content versioning** — Named versions with delta storage
9. **AI Assistant** — Built-in conversational AI in the Data Studio
10. **Official SDK** — TypeScript-first JavaScript SDK

## Key Competitive Advantages of OpenRegister

1. **True open source** — EUPL license vs BSL (no revenue restrictions)
2. **Nextcloud ecosystem** — Integrated with a mature collaboration platform
3. **Government focus** — NL Design System, WCAG compliance, Dutch gov standards
4. **JSON Schema standard** — Industry-standard schema definition
5. **n8n integration** — More flexible external automation platform
6. **MCP standard protocol** — JSON-RPC 2.0 compliant MCP endpoint
7. **No vendor lock-in** — No commercial license required at any scale
8. **Cost** — Completely free vs $99+/mo for cloud, BSL for large self-hosted

## Priority Gaps to Address

### High Priority
1. **Row-level security** — Essential for multi-tenant and government use cases
2. **Real-time capabilities** — Important for modern collaborative applications
3. **GraphQL API** — Expected by modern frontend frameworks

### Medium Priority
4. **Content versioning** — Important for editorial and compliance workflows
5. **Advanced filtering** — More operators, geospatial, nested relational filters
6. **Official SDK** — JavaScript/TypeScript SDK for easier integration
7. **Analytics dashboards** — Built-in data visualization

### Lower Priority
8. **Image transformations** — Can use external services
9. **Extension marketplace** — Nextcloud App Store serves this need
10. **AI Assistant** — Can leverage Nextcloud AI ecosystem

## Documentation Files

| File | Topic |
|------|-------|
| `01-overview.md` | Platform overview, pricing, license |
| `02-data-model.md` | Collections, fields, relationships |
| `03-api-reference.md` | REST, GraphQL, filtering, query params |
| `04-access-control.md` | Users, roles, policies, permissions |
| `05-flows-automation.md` | Triggers, operations, data chain |
| `06-extensions-marketplace.md` | Extension types, marketplace, SDK |
| `07-realtime.md` | WebSockets, subscriptions |
| `08-insights-dashboards.md` | Dashboards, panels |
| `09-files-assets.md` | File upload, image transforms |
| `10-authentication-sso.md` | SSO, OAuth2, LDAP, SAML |
| `11-ai-features.md` | AI Assistant, MCP server |

## Spec Files

| Spec | Gap Level | Description |
|------|-----------|-------------|
| `graphql-api/` | High | Auto-generated GraphQL with subscriptions |
| `realtime-websockets/` | High | WebSocket subscriptions for live updates |
| `row-level-security/` | High | Filter-based item & field permissions |
| `insights-dashboards/` | Medium | No-code analytics dashboard builder |
| `content-versioning/` | Medium | Named content versions with delta storage |
| `image-transformations/` | Medium | On-the-fly Sharp-based image processing |
| `marketplace-extensions/` | Medium | Granular extension types with npm registry |
| `ai-assistant/` | Medium | Built-in conversational AI + MCP server |
