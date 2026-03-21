---
competitor: twenty
analyzed_date: 2026-03-14
feature: Email & Calendar Sync
category: communication
maturity: stable
---

# Email & Calendar Sync

## Summary

Twenty syncs email and calendar from Google, Microsoft, and generic providers (SMTP/CalDAV). Emails and events are linked to CRM records automatically.

## Email Integration

### Supported Providers
- Google (Gmail) -- OAuth
- Microsoft (Outlook) -- OAuth
- Generic SMTP

### Sync Features
- Unlimited email accounts per user
- Folder selection (Inbox, Sent, Custom; excludes Spam/Trash)
- Three visibility levels: metadata only, metadata + subject, full content + attachments
- External emails only (internal emails kept private)
- Auto-contact creation from interactions (configurable: sent-only or all)
- Domain-based company linking
- Sync speed: ~400 messages/minute
- Update frequency: every 5 minutes

### Limitations
- No CC address option (folder selection instead)
- Single recipient for workflow emails
- No HTML signatures
- Attachments planned for H1 2026
- Only true mailboxes (no aliases/forwards)

## Calendar Integration

### Supported Providers
- Google Calendar -- OAuth
- Microsoft Calendar -- OAuth
- CalDAV

### Features
- Event visibility: full details or metadata only
- Auto-contact creation from meeting participants
- Auto-linking to existing CRM records
- Update frequency: every 5 minutes

### Limitations
- No meeting booking from Twenty
- No native scheduling (read-only sync)

## Relevance to Pipelinq

**Twenty's email/calendar strengths:**
- Native email sync built into CRM UI
- Auto-linking to contacts and companies
- Multi-provider support
- Granular visibility controls

**Pipelinq/Nextcloud advantages:**
- Nextcloud Mail is a full email client (send + receive natively)
- Nextcloud Calendar is a full calendar app with scheduling
- Nextcloud Talk for real-time communication
- All communication tools share same authentication
- No third-party OAuth required for Nextcloud-native mail
- Document sharing and collaboration alongside communication
