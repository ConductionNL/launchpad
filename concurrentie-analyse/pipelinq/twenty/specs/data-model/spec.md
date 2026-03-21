---
competitor: twenty
analyzed_date: 2026-03-14
feature: data-model
---

# Data Model & Schema Architecture

## Overview

Twenty uses a custom "Twenty ORM" built on top of TypeORM with workspace-scoped entities. The data model separates "standard objects" (built-in CRM entities) from "custom objects" (user-defined entities). All entities extend `BaseWorkspaceEntity` which provides common fields (id, createdAt, updatedAt, deletedAt).

## Standard Objects

### Company
- `name`, `domainName` (LinksMetadata), `employees` (number)
- `linkedinLink`, `xLink` (LinksMetadata)
- `annualRecurringRevenue` (CurrencyMetadata with amount + currencyCode)
- `address` (AddressMetadata - structured address with street, city, state, etc.)
- `idealCustomerProfile` (boolean flag for ICP marking)
- `position` (ordering), `searchVector` (full-text search)
- Relations: people[], accountOwner (WorkspaceMember), opportunities[], tasks/notes via targets, favorites, attachments, timelineActivities

### Person (Contact)
- `name` (FullNameMetadata - firstName + lastName)
- `emails` (EmailsMetadata - primary + additional emails)
- `phones` (PhonesMetadata - primary + additional phones)
- `jobTitle`, `city`, `avatarFile`
- `linkedinLink`, `xLink` (LinksMetadata)
- Relations: company, pointOfContactForOpportunities[], messageParticipants[], calendarEventParticipants[]

### Opportunity (Deal)
- `name`, `stage` (string, not enum - configured per workspace)
- `amount` (CurrencyMetadata), `closeDate`
- `position` (for kanban ordering)
- Relations: company, pointOfContact (Person), owner (WorkspaceMember)

### Task
- `title`, `bodyV2` (RichTextMetadata - BlockNote format)
- `dueAt`, `status` (string)
- Relations: assignee (WorkspaceMember), taskTargets[] (polymorphic linking)

### Note
- `title`, `bodyV2` (RichTextMetadata)
- Relations: noteTargets[] (polymorphic linking)

## Composite/Complex Field Types

Twenty defines 27 field metadata types:

| Type | Description |
|------|-------------|
| TEXT | Plain string |
| NUMBER / NUMERIC | Integer / Decimal |
| BOOLEAN | True/false |
| DATE / DATE_TIME | Date values |
| CURRENCY | Amount + currency code |
| EMAILS | Primary email + additionals array |
| PHONES | Primary phone + additionals array |
| LINKS | Primary link + additionals array |
| FULL_NAME | First name + last name |
| ADDRESS | Street, city, state, zip, country |
| ACTOR | Source + name (who created/modified) |
| SELECT / MULTI_SELECT | Enum with options |
| RELATION | Foreign key to another object |
| MORPH_RELATION | Polymorphic relation |
| RICH_TEXT | BlockNote document |
| RATING | Star rating |
| ARRAY | Array of values |
| RAW_JSON | Arbitrary JSON |
| FILES | File attachments |
| UUID | Unique identifier |
| POSITION | Ordering value |
| TS_VECTOR | Full-text search vector |

## Polymorphic Relations (Target Pattern)

Tasks and Notes use a "target" pattern for polymorphic linking:
- `TaskTarget` / `NoteTarget` are join entities
- They can link to Company, Person, Opportunity, or any custom object
- This avoids hard-coding which entities can have tasks/notes

## Timeline Activity

Central audit/activity log entity tracking all changes:
- `happensAt`, `name`, `properties` (JSON)
- Links to the acting `workspaceMember`
- Polymorphic targets: person, company, opportunity, note, task, workflow, dashboard, custom objects

## Search Architecture

All searchable entities include a `searchVector` field (PostgreSQL tsvector). Search fields are defined per entity type:
- Company: name, domainName
- Person: name, emails, phones, jobTitle
- Opportunity: name
- Note/Task: title, bodyV2 (rich text)

## Pipelinq Comparison

| Aspect | Twenty | Pipelinq (OpenRegister) |
|--------|--------|------------------------|
| Schema definition | TypeScript classes + metadata DB | JSON Schema in register schemas |
| Custom objects | Via metadata API (object + fields) | Via register schemas |
| Field types | 27 built-in composite types | JSON Schema types + format hints |
| Multi-tenancy | Workspace-per-schema (PostgreSQL) | Register-based isolation |
| Relations | TypeORM relations + morph relations | Schema references + object relations |
| Search | PostgreSQL tsvector | Configurable search (Solr/Elastic/DB) |
| Audit trail | TimelineActivity entity | Object versioning + audit logs |
