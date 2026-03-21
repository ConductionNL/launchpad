---
competitor: monica
analyzed_date: 2026-03-14
feature: api-and-integrations
category: integration
---

# API and Integrations

## Overview

Monica has a minimal REST API and CalDAV/CardDAV integration. The API surface is intentionally small — most functionality is only accessible via the web UI. This is a significant limitation for automation and third-party integrations.

## REST API

### Authentication
- Laravel Sanctum (bearer tokens)
- Token management via Settings > API Tokens (Jetstream-style)

### Endpoints
Only two resource groups:

**Users:**
- `GET /api/user` — current authenticated user
- `GET /api/users` — list all users
- `GET /api/users/{id}` — show specific user

**Vaults:**
- `GET /api/vaults` — list vaults
- `POST /api/vaults` — create vault
- `GET /api/vaults/{id}` — show vault
- `PUT /api/vaults/{id}` — update vault
- `DELETE /api/vaults/{id}` — delete vault

### Missing from API
Everything else — contacts, notes, activities, reminders, tasks, gifts, journals, etc. — is only available through the Inertia.js web interface. This means:
- No programmatic contact management
- No automation of reminders or notifications
- No import/export via API (only vCard for contacts)
- No webhook support
- No event-driven integrations

## CalDAV/CardDAV

Built-in DAV server for syncing:
- **CardDAV:** Sync contacts with external apps (macOS Contacts, Thunderbird, etc.)
- **CalDAV:** Sync reminders/events with external calendar apps

Implementation:
- `app/Domains/Contact/Dav/` — DAV server
- `app/Domains/Contact/DavClient/` — DAV client (for syncing from external sources)
- Address book subscription model for ongoing sync

## Other Integrations

- **Uploadcare:** Cloud file/image upload service
- **Sentry:** Error tracking (`@sentry/vue`)
- **Socialite:** OAuth login providers

## Relevance to Pipelinq

Monica's integration story is its biggest weakness:
1. **Almost no API** — Only users and vaults exposed. Pipelinq's full OpenRegister API exposes everything.
2. **No webhooks** — No way to trigger external actions on events. Pipelinq has n8n workflows.
3. **No automation** — Users must do everything manually. Pipelinq's pipeline stages can trigger automated workflows.
4. **CalDAV/CardDAV** — Nice for personal use but not relevant for business pipelines.
5. **No MCP** — No machine-readable protocol for AI agents. Pipelinq has MCP standard protocol support.

This is a clear differentiation point: Pipelinq is API-first and automation-capable, while Monica is UI-only.
