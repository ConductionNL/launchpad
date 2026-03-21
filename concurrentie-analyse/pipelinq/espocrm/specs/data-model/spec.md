---
competitor: espocrm
analyzed_date: 2026-03-14
feature: data-model
---

# Data Model

## Overview

EspoCRM uses a **metadata-driven entity system** where entity definitions (fields, links, indexes) are declared in JSON files under `Resources/metadata/entityDefs/`. The ORM reads these definitions at runtime to generate SQL queries, handle relations, and enforce validation. Core platform entities live in `Espo/Entities/` while CRM-specific entities are in `Espo/Modules/Crm/Entities/`.

## Entity Hierarchy

### Core Platform Entities (~65)
- User, Team, Role, PortalRole, Portal
- Email, EmailAccount, EmailTemplate, EmailFilter, EmailFolder
- Attachment, Note, Notification
- Webhook, Job, ScheduledJob
- AuthToken, Integration, Extension
- WorkingTimeCalendar, DashboardTemplate

### CRM Module Entities (~21)
- **Sales:** Account, Contact, Lead, Opportunity, Target
- **Activities:** Meeting, Call, Task
- **Marketing:** Campaign, CampaignLogRecord, CampaignTrackingUrl, MassEmail, EmailQueueItem, TargetList
- **Support:** Case (CaseObj in PHP)
- **Knowledge:** KnowledgeBaseArticle, KnowledgeBaseCategory
- **Documents:** Document, DocumentFolder

## Key Entity Relationships

### Account-Contact-Opportunity Triangle
- Account hasMany Contacts (M:N with `role` and `isInactive` columns)
- Account hasMany Opportunities (1:N)
- Opportunity hasMany Contacts (M:N with `role` column: Decision Maker, Evaluator, Influencer)
- Contact belongsTo Account (primary account)
- Opportunity belongsTo Account + Contact (primary)

### Lead Conversion Links
- Lead belongsTo createdAccount, createdContact, createdOpportunity
- Account/Contact/Opportunity hasOne originalLead (reverse link)

### Activity Polymorphism
- Meeting/Call/Task use `parent` (linkParent) pointing to Account, Lead, Contact, Opportunity, or Case
- Meeting/Call have M:N with Contacts and Leads (with `acceptanceStatus` column)

### Campaign Links
- Campaign hasMany TargetLists (M:N, with excluding lists)
- Campaign hasMany Leads, Contacts, Accounts (targets that came from campaign)
- Campaign hasMany CampaignLogRecords (tracking events)
- Campaign hasMany MassEmails

## Field Type System

EspoCRM has ~30 built-in field types defined in `metadata/fields/`:

| Type | Description |
|------|-------------|
| varchar | Standard text (max 255) |
| text | Long text |
| int | Integer |
| float | Decimal number |
| bool | Boolean |
| enum | Single select from options list |
| multiEnum | Multi-select |
| currency | Amount + currency code (multi-currency) |
| currencyConverted | Auto-converted to base currency |
| date / datetime / datetimeOptional | Date fields |
| email | Email address (special handling, multiple per entity) |
| phone | Phone number with type (Mobile, Office, etc.) |
| url | URL with link rendering |
| link | Foreign key (belongsTo) |
| linkMultiple | M:N relationship |
| linkParent | Polymorphic link (parentType + parentId) |
| linkOne | hasOne reverse |
| address | Composite: street, city, state, country, postalCode |
| personName | Composite: salutation, firstName, lastName |
| file / image | Single attachment |
| attachmentMultiple | Multiple attachments |
| jsonArray / jsonObject | Arbitrary JSON |
| duration | Computed from start/end datetime |

## Indexes

Entity definitions include explicit index declarations for performance:
- Composite indexes on frequently filtered columns (e.g., `["assignedUserId", "stage"]`)
- Unique indexes for cursor-based pagination (e.g., `["createdAt", "id"]`)
- Soft delete support via `deleted` column in indexes

## Concurrency Control

Optimistic concurrency control is enabled per-entity via `"optimisticConcurrencyControl": true` (used on Account, Opportunity).

## Relevance to Pipelinq

EspoCRM's data model is a classic B2B CRM design. Pipelinq's differentiation points:
- **OpenRegister-backed**: Pipelinq uses schema-driven objects rather than fixed entity classes
- **Flexibility**: EspoCRM entities require PHP entity classes + JSON metadata; Pipelinq schemas are purely data-driven
- **Multi-tenancy**: EspoCRM has per-installation databases; Pipelinq inherits Nextcloud's user/group model
- **Document integration**: EspoCRM only has file attachments; Pipelinq can leverage Nextcloud's document ecosystem
