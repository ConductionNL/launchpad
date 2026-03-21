---
competitor: espocrm
analyzed_date: 2026-03-14
feature: API & Integration Architecture
relevance: medium
pipelinq_equivalent: OpenRegister API, MCP protocol, n8n integration
---

# API & Integration Architecture

## Overview

EspoCRM is a single-page application where all frontend operations use the REST API. This means 100% of functionality is API-accessible. As of v9.3, EspoCRM also generates dynamic OpenAPI specifications including custom entity types and fields.

## REST API

### Basics
- **Root path:** `api/v1/`
- **Format:** JSON (request and response)
- **Datetime:** All values in UTC timezone
- **Architecture:** Generic CRUD + entity-specific endpoints

### CRUD Operations
- `GET /api/v1/{EntityType}` - List records (with search/filter/sort)
- `GET /api/v1/{EntityType}/{id}` - Get single record
- `POST /api/v1/{EntityType}` - Create record
- `PUT /api/v1/{EntityType}/{id}` - Update record
- `DELETE /api/v1/{EntityType}/{id}` - Delete record

### Related Records
- `GET /api/v1/{EntityType}/{id}/{link}` - List related records
- `POST /api/v1/{EntityType}/{id}/{link}` - Link record
- `DELETE /api/v1/{EntityType}/{id}/{link}` - Unlink record

### Special Endpoints
- Stream (activity feed)
- CurrencyRate (conversion rates)
- Attachment (file upload/download)
- I18n (internationalization)
- Metadata (application metadata)
- Account-specific operations

### OpenAPI Specification (v9.3+)
- **Endpoint:** `GET /api/v1/OpenApi`
- Dynamically generated, includes custom entities and fields
- Available for admin users and API users with OpenAPI scope
- Also accessible via Administration > API Users > OpenAPI spec

## Authentication Methods

### API Key (Recommended for simple integrations)
- Create API User with specific role permissions
- Header: `X-Api-Key: {key}`

### HMAC (Most secure)
- Create API User with HMAC auth method
- Header: `X-Hmac-Authorization: base64(apiKey + ':' + hmacSha256(method + ' /' + uri, secretKey))`

### Basic Auth (Not recommended)
- Username:password or username:token base64 encoded
- Header: `Authorization: Basic {encoded}`

## Client Libraries

Official API client libraries in 7 languages:
- PHP, JavaScript (Node.js), Python, Rust, Java, Go, Zig

All clients handle authentication and URL construction automatically.

## Integration Capabilities

### Built-in
- **Webhooks** - Outbound HTTP notifications on entity events (create, update, delete)
- **REST API** - Full CRUD + search + metadata
- **Email** - IMAP/SMTP integration with auto-linking to entities
- **LDAP/Active Directory** - User authentication
- **OpenID Connect** - SSO integration
- **Web-to-Lead** - Form-based lead capture API

### Via Extensions
- Google Workspace (Calendar, Contacts, Gmail) - $190/year
- Microsoft Outlook (Calendar, Contacts, Email) - $240/year
- Zoom video conferencing - $110/year
- MailChimp email marketing - $190/year
- Stripe payments - $95/year
- VoIP (3CX, Twilio, Asterisk) - $388/year

### Via Third-Party Platforms
- Zapier (2000+ integrations)
- Make/Integromat
- Custom API development

## Strengths

- 100% API coverage (SPA architecture)
- OpenAPI spec generation for custom entities
- 7 official client libraries
- Multiple auth methods including HMAC
- Webhook system for event-driven integrations
- Mature, well-documented API

## Weaknesses

- No native MCP (Model Context Protocol) support
- No built-in n8n integration
- No GraphQL support
- No real-time API (WebSocket for UI only, not API)
- No batch/bulk API operations
- Integration extensions are paid (self-hosted)
- No API rate limiting documentation

## Comparison with Pipelinq

| Aspect | EspoCRM | Pipelinq (OpenRegister) |
|--------|---------|------------------------|
| API style | REST with OpenAPI | REST + MCP protocol |
| Auth methods | API Key, HMAC, Basic | Nextcloud auth (OAuth, tokens) |
| Client libraries | 7 languages | Nextcloud SDK |
| OpenAPI spec | Dynamic generation (v9.3+) | Manual/generated |
| Webhooks | Built-in | Via n8n |
| Integration platform | Zapier/Make (external) | n8n (native ExApp) |
| AI/LLM integration | None | MCP protocol for LLM tools |
| Real-time | WebSocket (UI only) | Nextcloud notifications |
| Extension cost | $95-$388/year each | Included |
