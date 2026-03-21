---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Content Versioning

## Overview

Directus provides a built-in content versioning system that allows users to create draft versions of items, collaborate on changes, and promote versions to the main content. This is separate from the revision history (audit log) and provides a Git-like branching model for content.

## Concepts

- **Main**: The current published/active version of an item
- **Version**: A named draft that contains a delta of changes from main
- **Promote**: Merge a version's changes into the main item
- **Delta**: Only changed fields are stored, not the entire item

## Data Model

Versions are stored in `directus_versions`:
```typescript
interface ContentVersion {
  id: string;
  key: string;          // URL-friendly version key (e.g., "draft-v2")
  name: string;         // Human-readable name
  collection: string;   // Which collection the version belongs to
  item: string;         // Primary key of the item
  hash: string;         // Content hash for change detection
  date_created: string;
  date_updated: string;
  user_created: string;
  user_updated: string;
  delta: object;        // JSON delta from main
}
```

## API

### Create a Version
```
POST /versions
{ "key": "draft-v2", "name": "Draft Version 2", "collection": "articles", "item": "abc-123" }
```

Requirements:
- The collection must have versioning enabled in its metadata
- The user must have read access to the item
- The key "main" is reserved

### Read with Version Applied
```
GET /items/articles/abc-123?version=draft-v2
```
Returns the main item with the version's delta merged on top.

### Raw Version Data
```
GET /items/articles/abc-123?version=draft-v2&versionRaw=true
```
Returns relational delta changes as detailed output.

### Promote a Version
```
POST /versions/:id/promote
```
Merges the version's delta into the main item, creates a revision record, and optionally deletes the promoted version.

## Enabling Versioning

Content versioning is enabled per collection:
```
PATCH /collections/articles
{ "meta": { "versioning": true } }
```

## Collaborative Editing

Recent migrations (20260128A) add collaborative editing support, enabling real-time co-editing of version content.

## Activity Tracking

Version operations are tracked in the activity log:
- Version creation, saves, and promotion

## Relevance to OpenRegister

OpenRegister does not currently have content versioning. The revision history tracks changes after the fact but does not support:
- Draft versions before publishing
- Named branches of content
- Promote/merge workflows
- Side-by-side comparison

This is a valuable feature for content management workflows where editorial review is needed before publishing. OpenRegister could implement a similar system using:
- A `versions` or `drafts` table linked to objects
- JSON delta storage (efficient for large objects)
- Promote action that merges delta into the main object
- Integration with Nextcloud's existing versioning for files
