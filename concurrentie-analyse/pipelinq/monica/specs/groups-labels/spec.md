---
competitor: monica
analyzed_date: 2026-03-14
feature: groups-labels
---

# Groups & Labels

## Overview

Monica provides two mechanisms for organizing contacts: **Groups** (named collections with typed roles) and **Labels** (colored tags for categorization). Both are vault-scoped.

## Groups

### Data Model

**GroupType** (account-scoped)
- Fields: name, name_translation_key, type, can_be_deleted
- Defines what kind of group this is (e.g., "Club", "Association", "Team")

**GroupTypeRole** (belongs to GroupType)
- Defines roles within a group type (e.g., "President", "Member")

**Group** (vault-scoped)
- Fields: name, group_type_id
- Relations: vault, groupType, contacts (M2M)
- UUIDs, soft deletes, full-text searchable
- CardDAV sync support (vcard, distant_uuid, distant_etag, distant_uri)

### Group Services

| Service | Purpose |
|---------|---------|
| CreateGroup | Creates group in vault with type |
| UpdateGroup | Updates group name/type |
| DestroyGroup | Removes group |
| AddContactToGroup | Adds contact to group, creates feed item |
| RemoveContactFromGroup | Removes contact, creates feed item |

## Labels

### Data Model (Label)
- **Fields:** name, colour, description
- **Vault-scoped**
- **M2M with Contact** via contact_label pivot

### Label Services

| Service | Purpose |
|---------|---------|
| AssignLabel | Assigns label to contact, creates feed item |
| RemoveLabel | Removes label from contact, creates feed item |

### Label Management
- Labels are created/managed at the vault level
- Colors for visual distinction
- No hierarchy -- flat label system

## Vault Tab Visibility

Groups have a toggleable tab on the vault dashboard:
```php
$vault->show_group_tab  // boolean
```

## Pipelinq Relevance

- The **GroupType + GroupTypeRole** pattern is a flexible way to create typed collections with role semantics
- **Labels with colors** is a simple but effective categorization pattern
- Neither groups nor labels support hierarchy -- Pipelinq could offer nested grouping
- The CardDAV sync on groups is notable for external address book interop
- Groups could map to process teams; labels could map to pipeline stage indicators
