# EspoCRM API & Developer Documentation

**Source:** https://docs.espocrm.com/development/api/ and https://docs.espocrm.com/development/
**Fetched:** 2026-03-14

## API Overview

EspoCRM is a single-page application; the frontend communicates with the backend exclusively via REST API. All UI operations can be replicated via API calls.

- **API root path:** `api/v1/`
- **Content type:** `application/json`
- **Date/time:** All datetime values in UTC timezone

### OpenAPI Specification

As of v9.3, EspoCRM generates an OpenAPI spec dynamically (including custom entities/fields):
- **Endpoint:** `GET /api/v1/OpenApi`
- **Access:** Admin users or API users with OpenAPI scope
- **Also available:** Administration > API Users > top-right menu > OpenAPI spec

### API Reference

- CRUD operations (create, read, update, delete)
- Related records (link/unlink/list related)
- Stream (activity feed operations)
- CurrencyRate (conversion rates)
- Attachment (file upload/download)
- I18n (internationalization)
- Metadata (application metadata)
- Search parameters (filtering, sorting, pagination)

### Client Implementations

Official API client libraries in 7 languages:
- PHP
- JavaScript (Node.js)
- Python
- Rust
- Java
- Go
- Zig

### Authentication Methods

1. **API Key** (simplest) - Create API User, get API key
   - Header: `X-Api-Key: API_KEY`

2. **HMAC** (most secure) - API User with HMAC auth
   - Header: `X-Hmac-Authorization: base64(apiKey + ':' + hmacSha256(method + ' /' + uri, secretKey))`

3. **Basic Auth** (not recommended) - Username:password or username:token
   - Header: `Authorization: Basic base64(username:password)`

### Error Codes
- 400 Bad Request
- 403 Forbidden
- 404 Not Found
- 409 Conflict (locked record, duplicate)

## Developer Architecture

### Module System
- Core platform (`Espo/`)
- CRM module (`Espo/Modules/Crm/`)
- Custom directory (`custom/`) - survives upgrades
- Extension packages (installable .zip)

### Backend Development

- **Dependency Injection** - Container-based DI
- **Metadata-driven** - JSON metadata defines entities, fields, relationships, layouts, ACL, views
- **Services** - Business logic layer
- **Hooks** - Before/after save/delete hooks
- **ORM** - Custom ORM with query builder
- **Formula engine** - Server-side scripting for calculated fields and actions
- **Scheduled jobs** - Cron-based background processing
- **Webhooks** - Outbound HTTP notifications on entity events

### Frontend Development

- Custom JavaScript SPA framework (not React/Vue)
- ES modules / AMD modules
- View system with templates
- Custom field types, buttons, actions, panels
- Dynamic handler for runtime UI changes
- Ajax request helpers

### Key Metadata Types

- `scopes` - Entity scope definitions
- `entityDefs` - Entity field/relationship definitions
- `aclDefs` - Access control definitions
- `selectDefs` - Query select definitions
- `recordDefs` - Record service definitions
- `clientDefs` - Frontend view definitions
- `fields` - Field type definitions
- `dashlets` - Dashboard widget definitions

## Integration Capabilities

### Built-in
- Webhooks (outbound, entity-event-based)
- REST API (full CRUD + search + metadata)
- Email (IMAP/SMTP)
- LDAP/Active Directory/OpenLDAP
- OpenID Connect (SSO)

### Via Extensions
- Zapier (2000+ app integrations)
- Make (Integromat)
- Google Workspace (Calendar, Contacts, Gmail)
- Microsoft Outlook (Calendar, Contacts, Email)
- Zoom
- MailChimp
- Stripe
- VoIP (3CX, Twilio, Asterisk, Starface, Binotel)

## Relevance to Pipelinq

- EspoCRM has a mature REST API with OpenAPI spec support
- 7 official client libraries vs Pipelinq's Nextcloud API approach
- No native MCP (Model Context Protocol) support
- No n8n integration (requires manual API setup vs Pipelinq's native n8n ExApp)
- Custom JS frontend is harder to extend than Vue-based Nextcloud apps
