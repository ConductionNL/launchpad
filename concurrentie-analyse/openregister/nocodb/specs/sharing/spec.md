---
status: draft
source: competitive-analysis
competitor: nocodb
analyzed_date: 2026-03-14
---

# Sharing & Collaboration

## Overview

NocoDB provides multiple sharing mechanisms: view-level public sharing, base-level sharing, member management with roles, row-level comments, and audit logging.

## Share View

Each view can be shared independently with a public URL:
- **Enable Public Viewing** — Toggle generates a shareable link
- **Password protection** — Optional password for shared views
- **Survey mode** — Form views can use survey-style (one question at a time)
- **Allowed domains** — Restrict form submissions to specific email domains
- **Shared view customization** — Independent of the original view's filter/sort

Shared view types:
- **Grid** — Read-only spreadsheet view
- **Form** — Public data collection form
- **Gallery** — Public card view
- **Calendar** — Public calendar view
- **Kanban** — Public board view

## Share Base

Entire bases can be shared:
- **Enable Public Access** — Toggle generates a base-level share link
- **Viewer/Editor roles** — Choose access level for shared base
- **Copy shared base** — Recipients can clone the base

## Member Management

### Roles
- **Owner** — Full control, can delete base
- **Creator** — Create tables, views, fields
- **Editor** — Add/edit/delete records
- **Commenter** — View data + add comments
- **Viewer** — Read-only access

### Workspace Users
- Invite by email
- Role assignment per base
- User list with role management

## Comments

Row-level commenting system:
- Rich text editor (bold, italic, underline, strikethrough, link)
- Comment reactions (CommentReaction model)
- Visible in expanded row view (right panel)
- Comments are per-record, visible across all views

## Audit Log

- Tracks all data operations (create, update, delete)
- Tracks meta operations (column changes, view changes)
- Per-base audit log
- Stored in `nc_audit` table

## Relevance to OpenRegister

1. **Per-view sharing** is more granular than OpenRegister's Nextcloud-based sharing
2. **Public forms** are built-in vs requiring separate form builder
3. **Role system** is simpler (5 roles) vs Nextcloud's permission system
4. **Comments** are built into the record view
5. OpenRegister benefits from Nextcloud's mature sharing infrastructure (groups, circles, federation)
