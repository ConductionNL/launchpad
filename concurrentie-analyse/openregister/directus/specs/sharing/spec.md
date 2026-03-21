---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Sharing

## Overview

Directus allows users to create share links that grant temporary, scoped access to specific items. Shares enable external collaboration without requiring full user accounts, similar to Nextcloud's share links but for structured data.

## Share Model

Shares are stored in `directus_shares`:
- **Name**: Display name for the share
- **Collection**: Which collection the shared item belongs to
- **Item**: Primary key of the shared item
- **Role**: Optional role to assign to share recipients (for permission scoping)
- **Password**: Optional password protection (argon2 hashed)
- **Date Start / Date End**: Time-limited access window
- **Max Uses**: Maximum number of times the share can be used
- **Times Used**: Usage counter

## Access Flow

1. User with `share` permission creates a share link
2. Share link generates a URL with the share ID
3. Recipient opens the link (optionally enters password)
4. System creates a temporary authentication token scoped to:
   - Read access to the shared item
   - Access to related items (as defined by the share's role permissions)
5. The admin UI renders a focused view of the shared item

## Share Authentication

The `SharesService` handles authentication:
- Validates the share exists and is active
- Checks date range and usage limits
- Verifies password if required
- Issues a JWT token with the share's accountability context
- The token includes the share ID, collection, item, and role

## Permission Scoping

Shares interact with the permission system:
- The `share` action in permissions controls who can create shares
- Share recipients inherit the permissions of the share's configured role
- If no role is specified, recipients get minimal read-only access to the shared item

## Email Notification

When creating a share, users can send an email invitation:
- Uses the mail service to send a branded email
- Includes the share link and optional message
- Supports markdown in the message body

## Relevance to OpenRegister

OpenRegister benefits from Nextcloud's mature sharing system:
- File sharing links (password, expiration, download limits)
- Share with users, groups, circles, or via public link
- Federated sharing across Nextcloud instances
- Mobile and desktop app integration

However, Nextcloud sharing is file-centric. OpenRegister could benefit from:
- Data item sharing (share a specific object/record)
- Scoped API tokens for external access
- Time-limited access to specific register data
- Share with role-based permission scoping
