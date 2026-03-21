---
competitor: twenty
analyzed_date: 2026-03-14
feature: API Platform
category: developer-platform
maturity: stable
---

# API Platform

## Summary

Twenty provides dual API access (REST + GraphQL) that auto-generates from the workspace's data model. Both custom and standard objects are fully accessible. Rate-limited at 100 req/min.

## Architecture

### Two API Layers
1. **Core API** (`/rest/`, `/graphql/`) -- Record CRUD for all objects
2. **Metadata API** (`/rest/metadata/`, `/metadata/`) -- Data model configuration

### Authentication
- Bearer token: `Authorization: Bearer YOUR_API_KEY`
- API keys generated in Settings > APIs & Webhooks
- Keys inherit assigned role permissions

### Endpoints
- Cloud: `https://api.twenty.com/`
- Self-hosted: `https://{your-domain}/`

## Capabilities

| Feature | REST | GraphQL |
|---------|------|---------|
| CRUD | Yes | Yes |
| Batch ops (max 60) | Yes | Yes |
| Batch upsert | No | Yes |
| Relationship queries | No | Single-call |
| Custom object support | Auto | Auto |

## Rate Limits
- 100 requests per minute
- 60 records per batch call

## Developer Tools
- Built-in API Playground (REST + GraphQL)
- Autocomplete reflecting custom schema
- GraphQL introspection
- twenty-sdk npm package
- CLI with schema export: `twenty graphql schema --output-file schema.json`

## Webhooks
- Events: `*.created`, `*.updated`, `*.deleted` for all objects
- HMAC SHA256 signing
- No event filtering yet (all events sent)
- Requires 2xx acknowledgment

## Relevance to Pipelinq

**Twenty's API strengths:**
- Auto-generated from data model (zero config)
- GraphQL with relationship traversal in single queries
- Built-in playground
- Consistent across standard and custom objects

**Pipelinq/OpenRegister advantages:**
- MCP protocol for AI-native interaction
- OAS (OpenAPI Spec) generation for broader tooling compatibility
- Register-based data isolation (multi-tenant)
- No rate limit concerns for internal usage
- Nextcloud authentication integration (SSO via Nextcloud)
