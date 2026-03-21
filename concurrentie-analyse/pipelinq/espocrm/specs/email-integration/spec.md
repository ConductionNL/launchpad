---
competitor: espocrm
analyzed_date: 2026-03-14
feature: email-integration
---

# Email Integration

## Overview

EspoCRM has deep email integration built into its core (not a module). It supports personal and shared IMAP/SMTP accounts, email-to-entity linking, inline composition, email templates, and mass email sending for campaigns.

## Architecture

### Core Mail Components (`Core/Mail/`)
- **EmailSender / Sender** - SMTP sending with SenderParams configuration
- **Importer** - IMAP message import and entity linking
- **Parser / ParserFactory** - Email message parsing (headers, body, attachments)
- **FiltersMatcher** - Email filter rule evaluation
- **ConfigDataProvider** - System-wide SMTP configuration
- **SmtpParams** - Per-account SMTP credentials

### Email Tools (`Tools/Email/`)
- **Service** - Core email operations
- **SendService** - Compose and send emails
- **InboxService** - Inbox management (read, important, trash)
- **ImportEmlService** - Import .eml files
- **AddressService** - Email address resolution and entity matching

## Email Entity

The core Email entity (`Entities/Email.php`) has CRM-specific extensions in `Modules/Crm/Resources/metadata/entityDefs/Email.json`:
- Links to Account (as parent)
- Activity/history panel integration
- Email-to-entity auto-linking

## Personal Email Accounts (EmailAccount)

Each user can configure personal IMAP/SMTP accounts:
- IMAP connection settings (host, port, security, credentials)
- SMTP settings (can use personal or system SMTP)
- Folder monitoring (which IMAP folders to fetch)
- Email filters for auto-routing

## Shared Inbound Email (InboundEmail)

Organization-wide email accounts (e.g., sales@company.com):
- IMAP polling on schedule (via ScheduledJob)
- Auto-distribution to teams
- Auto-reply configuration
- Case creation from incoming emails

## Email-Entity Linking

Emails are automatically linked to CRM entities via:
1. **Email address matching** - Match sender/recipient to Contact, Lead, or Account
2. **Thread tracking** - Reply chains maintain parent entity association
3. **Manual linking** - Users can manually link emails to any entity
4. **Parent polymorphism** - Email.parent can point to Account, Lead, Contact, Opportunity, or Case

## Email API Endpoints

```
POST /Email/inbox/read          - Mark as read
DELETE /Email/inbox/read        - Mark as unread
POST /Email/inbox/important     - Mark as important
DELETE /Email/inbox/important   - Remove important flag
POST /Email/inbox/inTrash       - Move to trash
DELETE /Email/inbox/inTrash     - Restore from trash
POST /Email/sendTest            - Send test email
POST /Email/folder              - Move to folder
POST /Email/importEml           - Import .eml file
GET  /Email/insertFieldData     - Get merge field data for templates
GET  /Email/notReadCounts       - Get unread counts per folder
POST /Email/users               - Share email with users
POST /Email/attachments/copy    - Copy attachments
```

## Email Templates

Templates support merge fields with Handlebars-like syntax:
- Entity fields: `{{name}}`, `{{account.name}}`
- Related records: `{{contacts.[0].name}}`
- System fields: `{{today}}`, `{{currentUser.name}}`

Template categories for organization.

## Mass Email (Campaign Integration)

Mass email is tightly integrated with the Campaign system:
- **MassEmail entity** - Defines the mass send job
- **EmailQueueItem entity** - Per-recipient queue entries
- **MessagePreparator** - Template personalization per recipient
- Batch sending via system SMTP
- Open tracking (pixel), click tracking (redirect URLs), bounce detection
- Unsubscribe handling (public endpoint, no auth required)

## Email Folders

- Personal folders per user (EmailFolder)
- Shared group folders (GroupEmailFolder)
- System folders: Inbox, Sent, Drafts, Trash, Important

## Relevance to Pipelinq

### Strengths
- Full IMAP/SMTP integration built into the platform
- Automatic email-to-entity linking based on email addresses
- Thread-aware conversation tracking
- Mass email with open/click/bounce tracking
- Personal + shared email accounts

### Opportunities for Pipelinq
- **Nextcloud Mail integration**: Instead of building IMAP/SMTP, leverage Nextcloud Mail app
- **n8n email workflows**: Use n8n for email automation instead of built-in mass email
- **No need to duplicate**: Pipelinq can reference emails via Nextcloud rather than storing copies
- **Modern email tracking**: Use webhook-based tracking via n8n rather than built-in pixel tracking
