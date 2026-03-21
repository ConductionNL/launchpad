---
competitor: krayin
analyzed_date: 2026-03-14
feature: email
priority: medium
---

# Email Client

## Overview

Krayin includes a built-in email client that supports both inbound (IMAP or webhook-based) and outbound (SMTP) email. Emails are linked to leads and persons, with support for threading (parent/child), folders, attachments, and tagging.

## Data Model

### Email (`emails` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| subject | string | Email subject |
| source | string | 'web' or 'imap' |
| name | string | Sender display name |
| user_type | string | 'admin' for outgoing |
| is_read | boolean | Read status |
| folders | JSON | Array of folder names (inbox, outbox, sent, draft, trash) |
| from | JSON | Sender address(es) |
| sender | JSON | Sender info |
| reply_to | JSON | Reply-to addresses |
| cc | JSON | CC addresses |
| bcc | JSON | BCC addresses |
| unique_id | string | Message unique ID |
| message_id | string | Email message ID |
| reference_ids | JSON | Threading reference IDs |
| reply | text | Email body (HTML) |
| person_id | FK | Linked contact person |
| parent_id | FK | Parent email (threading) |
| lead_id | FK | Linked lead |

### Attachments (`email_attachments` table)
- File attachments per email
- Download endpoint available

## Inbound Email Processing

### Two Processors
1. **SendGrid** -- `SendgridEmailProcessor`: Webhook-based inbound parsing via `/mail/inbound-parse` endpoint
2. **Webklex IMAP** -- `WebklexImapEmailProcessor`: Scheduled IMAP polling via `ProcessInboundEmails` Artisan command

### Email Parser
- `Helpers/Parser.php` -- Parses raw email content
- `Helpers/Charset.php` -- Character encoding normalization
- `Helpers/HtmlFilter.php` -- HTML sanitization
- `Helpers/Attachment.php` -- Attachment extraction

## Folder System

Emails use JSON folders array supporting:
- `inbox` -- Inbound emails
- `outbox` -- Queued for sending
- `sent` -- Successfully sent
- `draft` -- Saved drafts
- `trash` -- Deleted emails

Folder filtering via `SupportedFolderEnum`.

## Routes

```
POST   /mail/create            -- Compose/send email
PUT    /mail/edit/{id}         -- Update (e.g., move to folder)
GET    /mail/attachment-download/{id} -- Download attachment
GET    /mail/{route?}          -- List by folder
GET    /mail/{route}/{id}      -- View email thread
DELETE /mail/{id}              -- Delete
POST   /mail/mass-update       -- Mass folder move
POST   /mail/mass-destroy      -- Mass delete
POST   /mail/inbound-parse     -- SendGrid webhook (no auth)
POST   /mail/{id}/tags         -- Attach tag
DELETE /mail/{id}/tags         -- Detach tag
```

## Pipelinq Comparison Notes

- Full email client is a major feature that most pipeline tools lack
- Threading via parent_id + reference_ids is standard email behavior
- JSON arrays for from/cc/bcc is pragmatic but makes querying harder
- Dual inbound strategy (webhook + IMAP polling) provides flexibility
- Lead-email linking (`lead_id` FK) enables viewing all correspondence per deal
- No email tracking (opens/clicks)
- No email sequences/drip campaigns (that's in Marketing package)
- Pipelinq could integrate with Nextcloud Mail instead of building this
