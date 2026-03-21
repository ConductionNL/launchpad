# Twenty CRM - API & Integrations

**Analyzed:** 2026-03-14

## API Overview

Twenty provides two API layers:
1. **Core API** (`/rest/` or `/graphql/`) -- CRUD on records (People, Companies, Opportunities, custom objects)
2. **Metadata API** (`/rest/metadata/` or `/metadata/`) -- Workspace structure and data model configuration

Both REST and GraphQL are auto-generated from the workspace's data model, including custom objects and fields.

## Authentication

- Bearer token authentication: `Authorization: Bearer YOUR_API_KEY`
- API keys generated via Settings > APIs & Webhooks > Create Key
- Keys display only once; must be stored securely
- API keys inherit role-based permissions

## Endpoints

| Environment | Base URL |
|-------------|----------|
| Cloud | `https://api.twenty.com/` |
| Self-Hosted | `https://{your-domain}/` |

## Capabilities

| Feature | REST | GraphQL |
|---------|------|---------|
| CRUD operations | Yes | Yes |
| Batch operations | Yes (max 60 records) | Yes (max 60 records) |
| Batch upsert | No | Yes |
| Relationship queries in single call | No | Yes |

## Rate Limits

- 100 requests per minute
- 60 records maximum per batch call

## API Playground

Built-in interactive playground available at Settings > APIs & Webhooks:
- Request builder with autocomplete
- Reflects custom objects and fields
- Available for both REST and GraphQL

## Webhooks

**Event types:**
- `*.created` (person, company, note, etc.)
- `*.updated` (person, company, opportunity, etc.)
- `*.deleted` (person, company, etc.)

Currently all events are sent to webhook URLs; event filtering is planned for the future.

**Payload structure:**
```json
{
  "event": "person.created",
  "data": { "id": "...", "firstName": "...", ... },
  "timestamp": "2026-03-14T..."
}
```

**Security:**
- HMAC SHA256 signing via `X-Twenty-Webhook-Signature` and `X-Twenty-Webhook-Timestamp` headers
- Requires 2xx acknowledgment response

## Third-Party Integrations

| Platform | Type | Details |
|----------|------|---------|
| **Zapier** | Automation | Connect to 8,000+ apps |
| **Pipedream** | Automation | API integration platform |
| **Nango** | Integration | Self-hosted integration support |
| **SMTP** | Email | Generic email provider support |
| **CalDAV** | Calendar | Generic calendar provider support |
| **Google** | Email/Calendar | Gmail, Google Calendar (OAuth) |
| **Microsoft** | Email/Calendar | Outlook, Microsoft Calendar (OAuth) |

## SDK & CLI

- **twenty-sdk** npm package available
- **twenty-cli** (community) with 100% API coverage
- CLI commands: `twenty graphql schema`, `twenty app:dev`, etc.
