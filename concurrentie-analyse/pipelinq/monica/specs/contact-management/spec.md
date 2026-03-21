---
competitor: monica
analyzed_date: 2026-03-14
feature: contact-management
category: core
---

# Contact Management

## Overview

Monica's core feature is a comprehensive contact profile system. Each contact is a rich entity containing personal details, relationship links, interaction history, and attached data (notes, tasks, gifts, etc.). Contacts live within Vaults (isolated workspaces).

## Data Model

**Contact entity fields:**
- First name, last name, nickname, maiden name
- Avatar (Uploadcare or generated)
- Gender (customizable)
- Pronouns (customizable)
- Religion
- Birthday and custom important dates
- Company and job title
- How you met

**Related entities per contact:**
- Addresses (multiple, with map images)
- Contact information (phone, email, social -- custom types)
- Relationships (bidirectional, typed: partner, child, friend, etc.)
- Pets (name + category)
- Notes (timestamped, favoritable)
- Calls (with reasons, dated)
- Activities (with descriptions, participants)
- Reminders (recurring, auto-generated for birthdays)
- Tasks (completable)
- Goals (with streaks)
- Gifts (ideas, purchased, given -- with occasions)
- Loans/Debts (amount, direction, status)
- Life events (categorized, timestamped)
- Documents and photos
- Quick facts
- Mood tracking events
- Labels/tags
- Groups membership

## UI Pattern

The contact detail page is a single scrollable page with configurable modules (sections). Each module can be shown/hidden via template configuration. Modules include: notes, calls, activities, reminders, tasks, gifts, debts, pets, addresses, contact info, relationships, documents, photos, goals, life events, mood tracking, groups, quick facts, job info, religion.

**Contact list:** Searchable, sortable, with avatar/initials, favorite markers, and label filters.

**Templates:** Admins can define multiple templates (e.g., "Friend", "Family", "Colleague") with different module configurations. Each contact can be assigned a template.

## Technical Implementation

- Domain: `app/Domains/Contact/` with 22 sub-domain directories
- Each sub-domain has `Services/` (business logic) and `Web/` (controllers)
- DAV integration for contacts via CardDAV
- vCard export/import support
- Feed generation per contact (activity timeline)

## Relevance to Pipelinq

Monica's contact management is its strongest feature and the most relevant comparison point for Pipelinq's pipeline item management. Key patterns to consider:
1. The modular/template-based contact page design allows users to customize what they see
2. Bidirectional relationships between contacts could inspire pipeline item linking
3. The activity feed per contact is similar to stage activity logs
4. Label-based filtering and favoriting are useful pipeline item organization patterns
