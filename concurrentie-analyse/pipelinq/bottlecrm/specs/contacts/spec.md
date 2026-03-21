---
competitor: bottlecrm
analyzed_date: 2026-03-14
feature: contacts
---

# Contacts Management

## Overview

BottleCRM's Contact module manages individual person records with professional information, communication preferences, and links to accounts. Contacts are the people you interact with -- they belong to organizations (Accounts) and can be associated with opportunities, cases, and tasks.

## Data Model

### Contact Entity

| Field | Type | Description |
|-------|------|-------------|
| first_name | CharField(255) | Required |
| last_name | CharField(255) | Required |
| email | EmailField | Unique per org (case-insensitive) |
| phone | CharField(25) | Validated format |
| organization | CharField(255) | Company name (text, not FK) |
| title | CharField(255) | Job title |
| department | CharField(255) | Department |
| do_not_call | BooleanField | Communication preference flag |
| linkedin_url | URLField | LinkedIn profile |
| address_line, city, state, postcode, country | Address fields | Flat address (not separate model) |
| description | TextField | Free-text notes |
| is_active | BooleanField | Soft delete |
| account | FK(Account) | Optional primary account link |

### Relationships

- **Account**: Optional FK (`account`) for primary account + M2M via Account.contacts
- **Leads**: M2M via Lead.contacts
- **Opportunities**: M2M via Opportunity.contacts
- **Cases**: M2M via Case.contacts
- **Tasks**: M2M via Task.contacts
- **Assigned To**: M2M to Profile (user assignment)
- **Teams**: M2M to Teams
- **Tags**: M2M to Tags

### Constraints

- Unique email per organization (case-insensitive, `unique_contact_email_per_org`)
- Organization-scoped (FK to Org, RLS-protected)

## API Endpoints

```
GET    /api/contacts/           -- List with filtering, pagination
POST   /api/contacts/           -- Create contact
GET    /api/contacts/<pk>/      -- Detail
PUT    /api/contacts/<pk>/      -- Update
DELETE /api/contacts/<pk>/      -- Delete
POST   /api/contacts/comment/<pk>/     -- Add comment
POST   /api/contacts/attachment/<pk>/  -- Add attachment
```

## Key Features

1. **Duplicate Detection**: Built-in `DuplicateDetector.find_duplicate_contacts()` matches on email (exact), phone (normalized last 10 digits), and name (case-insensitive first+last)
2. **Account Linking**: Contact can have a primary account (FK) and appear in multiple accounts (M2M)
3. **Communication Preferences**: `do_not_call` flag for compliance
4. **Generic Comments/Attachments**: ContentType-based, attachable to any contact
5. **Team Assignment**: Both direct user assignment and team-based assignment
6. **Tagging**: Flexible tag system with colors

## Relevance to Pipelinq

- Pipelinq could benefit from the **duplicate detection** service to prevent data quality issues
- The **do_not_call** flag pattern is useful for GDPR/compliance
- The dual account linking (FK + M2M) is worth noting -- it allows a primary account while supporting contacts that span multiple organizations
