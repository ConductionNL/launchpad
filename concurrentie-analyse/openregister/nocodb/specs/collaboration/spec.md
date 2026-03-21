---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Collaboration

## Overview

NocoDB provides multi-user collaboration features including workspaces, role-based access, row-level comments, reactions, audit logging, and real-time notifications.

## Workspaces

- Top-level organizational unit
- Contains multiple bases
- User roles at workspace level
- `Workspace` and `WorkspaceUser` models

## Role System

### Roles (hierarchical)
1. **Super Admin** — First signup user, full system access
2. **Owner** — Full base control, can delete base
3. **Creator** — Create tables, views, fields, manage schema
4. **Editor** — CRUD on records, cannot modify schema
5. **Commenter** — View data, add comments only
6. **Viewer** — Read-only access

### Role Scopes
- **Organization level** — `org-level-creator`, `super` roles
- **Workspace level** — Workspace-scoped roles
- **Base level** — Per-base role assignment
- **View level** — Model role visibility controls per view

## Comments

### Row-Level Comments
- Each record has a comment thread
- Comments visible in expanded row view (right panel)
- Rich text editor: bold, italic, underline, strikethrough, link
- `Comment` model with fk_model_id, row_id, comment text

### Comment Reactions
- `CommentReaction` model
- React to comments (emoji reactions)

### Document Comments
- `DocumentComment` model for document-level (not row-level) comments
- Used in document/wiki features

## Audit System

### Audit Log
- `Audit` model tracks all operations
- Fields: user, ip, base_id, fk_model_id, op_type, op_sub_type, description
- Operations: DATA (insert, update, delete), META (column, view changes), AUTH
- Accessible via Account > Audit or API

### Telemetry
- `TelemetryService` for usage analytics
- Event tracking for feature usage

## Notifications

### In-App Notifications
- `Notification` model
- Real-time delivery via WebSocket (Socket.IO gateway)
- Notification types: mentions, comments, shares

### XcNotification
- SDK notification types for different channels
- Used by webhook and plugin systems

## Real-Time Features

### WebSocket Gateway
- `socket/` directory with Socket.IO implementation
- Real-time data sync between users
- Presence indicators

### Data Reflection
- `DataReflection` model for tracking data changes
- Used for real-time view updates

## Relevance to OpenRegister

1. **Row-level comments** are useful for data review workflows
2. **Audit logging** is essential for government data management
3. **Role system** is simpler than Nextcloud's granular permissions
4. OpenRegister inherits Nextcloud's mature collaboration features (sharing, groups, Talk)
5. **Real-time sync** via WebSocket is more responsive than polling
