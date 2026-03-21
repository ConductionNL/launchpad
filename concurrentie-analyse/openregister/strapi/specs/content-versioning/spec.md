---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Content Versioning & Draft/Publish

## Overview

Strapi v5 provides content versioning through three interconnected systems: **Draft/Publish** for content lifecycle management, **History Versions** for tracking content changes over time, and **Content Releases** (EE) for scheduled batch publishing. Together they form a comprehensive content workflow system.

## Draft/Publish System

### Concept
- Content types can opt into draft/publish via `options.draftAndPublish: true`
- Each entry has a `publishedAt` timestamp field
- `publishedAt = null` means draft; `publishedAt = timestamp` means published
- Draft and published versions exist as separate database rows sharing the same `documentId`

### Status Flow
```
CREATE -> Draft (publishedAt: null)
         |
         +-> PUBLISH -> Published (publishedAt: timestamp)
         |               |
         |               +-> UNPUBLISH -> Draft (publishedAt: null)
         |               |
         |               +-> UPDATE (draft) -> Modified Draft + Published (both exist)
         |
         +-> UPDATE -> Modified Draft
```

### API Integration
- `?status=draft` - Query draft versions
- `?status=published` - Query published versions
- Without status parameter, defaults to draft for mutations, published for queries
- `POST /api/{pluralName}/:id/actions/publish` - Publish a draft
- `POST /api/{pluralName}/:id/actions/unpublish` - Unpublish

### Document Service Transforms
The `draft-and-publish.ts` module provides parameter transforms:
- `setStatusToDraft` - Forces status to draft for create/update operations
- `defaultStatus` - Defaults to draft if no status provided
- `statusToLookup` - Converts status parameter to database query filter
- `filterDataPublishedAt` - Prevents manual `publishedAt` manipulation

### Database Representation
Draft and published entries coexist:
```
| id | document_id | title  | published_at        | locale |
|----|-------------|--------|---------------------|--------|
| 1  | abc123      | Draft  | NULL                | en     |
| 2  | abc123      | Live   | 2024-01-01 00:00:00 | en     |
```

## History Versions

### Concept
The History system (`packages/core/content-manager/server/src/history/`) tracks content changes over time, stored as snapshots of entry data:

- Every create/update creates a history version
- History versions store the full entry data as JSON
- Versions are linked to the content type and document ID
- Supports locale-aware versioning

### History Version Model
```typescript
interface HistoryVersion {
  id: number;
  contentType: string;        // Content type UID
  relatedDocumentId: string;  // Document ID
  locale: string;             // Locale of this version
  data: object;               // Full snapshot of entry data
  schema: object;             // Schema at time of version
  status: string;             // draft or published
  createdAt: Date;
  createdBy: User;            // Who made the change
}
```

### History API
- `GET /content-manager/history-versions` - List versions with pagination
- Query by content type, document ID, and locale
- Ordered by creation date (newest first)
- Populated with creator information

### Version Restoration
History versions can be restored:
- Entry data is extracted from the version snapshot
- Component IDs are stripped (to create fresh components)
- Relations are resolved against current database state
- Media references are verified
- The restored data is applied as a new update (creating a new version)

### Lifecycle Integration
History versions are created via lifecycle subscribers:
- `afterCreate` - Records initial version
- `afterUpdate` - Records updated version
- Runs inside the Document Service middleware chain

## Content Releases (EE)

### Concept
Content Releases allow grouping multiple content changes into a single release that can be published atomically on a schedule.

### Services
| Service | Purpose |
|---------|---------|
| `release` | CRUD for release records |
| `release-action` | Individual actions within a release (publish/unpublish entries) |
| `scheduling` | Cron-based scheduled release execution |
| `validation` | Validate release integrity before publishing |
| `settings` | Release feature settings |

### Release Workflow
1. Create a release with a name and optional schedule date
2. Add release actions (publish or unpublish specific entries)
3. Validate all actions are still valid
4. Publish release (executes all actions atomically)
5. Or schedule release for automatic publishing

### Feature Gating
```typescript
if (strapi.ee.features.isEnabled('cms-content-releases')) {
  // Full release functionality
} else {
  // Only content types registered (to preserve data)
}
```

## Relevance to OpenRegister

**Key differences:**
- Strapi has built-in draft/publish; OpenRegister has audit logging but no draft/publish
- Strapi's history is JSON snapshots; OpenRegister could leverage Nextcloud's versioning
- Content Releases is an EE-only feature with no OpenRegister equivalent

**Features OpenRegister could adopt:**
- Draft/publish as a first-class status dimension on objects
- History version snapshots for audit and rollback
- Version restoration with relation re-resolution
- Scheduled publishing via n8n workflows (equivalent to Content Releases)
- The `documentId` pattern: stable identifier across draft/published/locale versions
