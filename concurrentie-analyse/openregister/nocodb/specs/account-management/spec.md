---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Account Management

## Overview

NocoDB provides a comprehensive account management system with setup configuration, user profiles, API token management, MCP server configuration, and an app store for plugins.

## Setup Page

### Email Configuration
- Configure SMTP settings for application emails
- Used for: alerts, notifications, password resets, invitations
- Supports standard SMTP parameters

### Storage Configuration
- Configure file storage backend
- Options: local filesystem, S3, cloud storage
- Used for: attachments, exports, backups

## Profile Management

- User email and display name
- Password change
- Account deletion

## API Tokens

### Personal API Tokens
- Created per user in Account > API Tokens
- Used for automation and external integrations
- Token format: JWT-based
- CRUD operations on tokens
- No expiration by default

### Token Usage
- Header: `xc-auth: <token>` or `xc-token: <token>`
- Full API access within user's role permissions

## MCP Server Management

### MCP Tokens
- Per-base MCP access tokens
- Created in Account > MCP Server
- Properties: name, base, created date
- Token regeneration support
- Token deletion

### Token Table
- Lists all active MCP servers
- Columns: Name, Base, Created On, Action
- Actions: regenerate, delete

## App Store (Deprecated)

### Available Plugins
- **Communication:** Slack, Microsoft Teams, Discord, Mattermost
- **Messaging:** Whatsapp Twilio, Twilio
- **Email:** SMTP configuration (moved to Setup)
- **Storage:** S3, etc. (moved to Setup)

### Deprecation Notice
- App Store will be removed
- Email & Storage plugins moved to Account/Setup
- Communication plugins moving to Integrations

## User Management

### User Administration
- Invite users by email
- Assign roles (Owner, Creator, Editor, Commenter, Viewer)
- Remove users from workspace/base
- View user activity

### OAuth Support
- `OAuthClient`, `OAuthAuthorizationCode`, `OAuthToken` models
- OAuth 2.0 authorization code flow
- Token management

## Relevance to OpenRegister

1. **API token management** UI is clean and straightforward
2. **MCP token management** is a unique feature (OpenRegister uses Basic Auth)
3. **App Store deprecation** shows the maintenance burden of plugin ecosystems
4. OpenRegister benefits from Nextcloud's built-in user management, OAuth, LDAP
5. **Per-base MCP tokens** could inspire scoped token support in OpenRegister
