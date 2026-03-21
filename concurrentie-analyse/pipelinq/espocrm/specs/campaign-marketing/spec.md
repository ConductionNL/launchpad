---
competitor: espocrm
analyzed_date: 2026-03-14
feature: campaign-marketing
---

# Campaign & Marketing

## Overview

EspoCRM includes a full campaign management system supporting email, newsletter, web, TV, radio, and mail campaign types. The system manages target lists, mass email sending, and detailed tracking statistics.

## Campaign Entity

### Core Fields
- **name** (varchar, required)
- **status** (enum): Planning, Active, Inactive, Complete
- **type** (enum): Email, Newsletter, Informational Email, Web, Television, Radio, Mail
- **startDate** / **endDate** (date, with cross-validation)
- **budget** (currency)
- **targetLists** (linkMultiple) - Included audience
- **excludingTargetLists** (linkMultiple) - Excluded audience
- **revenue** (currency, computed from linked opportunities)

### Per-Entity Email Templates
Campaigns can have different templates for different target entity types:
- contactsTemplate -> Template for contacts
- leadsTemplate -> Template for leads
- accountsTemplate -> Template for accounts
- usersTemplate -> Template for users

## Campaign Statistics

All stats are computed dynamically via `StatsLoader`:

| Metric | Description |
|--------|-------------|
| sentCount | Total emails sent |
| openedCount | Unique opens (with percentage) |
| clickedCount | URL clicks (with percentage) |
| optedInCount | Opt-ins |
| optedOutCount | Unsubscribes (with percentage) |
| bouncedCount | Total bounces (with percentage) |
| hardBouncedCount | Permanent failures |
| softBouncedCount | Temporary failures |
| leadCreatedCount | Leads generated |
| revenue | Sum of linked opportunities |

## Target Lists

### TargetList Entity
Audience segments that can contain mixed entity types:
- Links to Contacts, Leads, Accounts, Users (all M:N)
- `optedOut` column on relationships for per-list opt-out tracking
- Categories for organization (TargetListCategory)

### Target Entity
Generic marketing target (separate from Lead/Contact).

## Mass Email System

### MassEmail Entity
- Links to Campaign
- Links to InboundEmail (for reply tracking)
- Links to EmailTemplate
- Target lists selection (included + excluded)
- Scheduling and status tracking

### Execution Flow
1. Create MassEmail linked to Campaign
2. System generates EmailQueueItem per recipient
3. Scheduled job processes queue in batches
4. Per-recipient personalization via MessagePreparator
5. Tracking pixel and redirect URLs inserted automatically

### Tracking Mechanisms
- **Open tracking**: 1x1 pixel image embedded in HTML emails
- **Click tracking**: URLs replaced with redirect through CampaignTrackingUrl
- **Bounce detection**: IMAP monitoring on InboundEmail account
- **Unsubscribe**: Public REST endpoints (no auth required):
  - `POST /Campaign/unsubscribe/:id`
  - `POST /Campaign/unsubscribe/:emailAddress/:hash`

### CampaignLogRecord Entity
Every tracking event creates a log record:
- parent (polymorphic: Contact, Lead, Account, User)
- campaign link
- action type (Sent, Opened, Clicked, Opted Out, Bounced, etc.)
- timestamp

## Mail Merge

Campaigns support PDF mail merge for physical mailings:
- `POST /Campaign/:id/generateMailMerge`
- Generates personalized PDFs from templates for all targets
- Only for targets with valid addresses (`mailMergeOnlyWithAddress` flag)

## Relevance to Pipelinq

### Strengths
- Complete campaign lifecycle management
- Multi-channel campaign types (not just email)
- Detailed tracking with percentages and attribution
- Target list management with opt-out tracking
- Revenue attribution from campaigns to opportunities
- Mail merge for physical mailings

### Opportunities for Pipelinq
- **n8n-based campaigns**: Instead of built-in mass email, use n8n workflows for multi-channel campaign orchestration
- **Nextcloud ecosystem**: Leverage Nextcloud Mail, Talk, and Deck for campaign coordination
- **Simpler approach**: Most Pipelinq users need basic email sequences, not full campaign management
- **API-first tracking**: Use webhook-based event tracking via n8n instead of embedded pixels
