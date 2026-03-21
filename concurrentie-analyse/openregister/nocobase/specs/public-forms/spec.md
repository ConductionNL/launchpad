---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Public Forms

## Purpose

NocoBase's `plugin-public-forms` enables creating externally accessible forms that allow unauthenticated users to submit data to collections. This is useful for surveys, contact forms, registration forms, and public data collection.

## Architecture Overview

```
Public URL (no auth required)
    |
    v
Public Form Renderer (embedded schema)
    |
    v
Form Submission (POST to collection)
    |
    v
Collection Record Created
    |
    v
Optional: Workflow Trigger
```

## Data Model

### Public Forms Collection
- `title` - Form display name
- `collection` - Target collection for submissions
- `schema` - UI schema for the form layout
- `enabled` - Whether the form is active
- `password` - Optional access password
- `url` - Generated public URL

## Business Logic

### Form Creation
1. Admin creates a public form in settings
2. Selects target collection and fields to expose
3. Configures form layout using schema builder
4. Generates shareable public URL

### Form Submission
1. Anonymous user visits public URL
2. Form rendered without authentication
3. Submission creates record in target collection
4. Optional password protection for form access
5. Workflow can trigger on new submissions

### Form Embedding
Public forms can be embedded in external websites via:
- Direct URL link
- Iframe embedding (`plugin-embed` support)

## Requirements

### Functional
- Create forms accessible without login
- Select which collection fields to expose
- Optional password protection
- Shareable public URLs
- Submission creates collection records
- Workflow integration on submission

### Non-functional
- CSRF protection on submissions
- Rate limiting for abuse prevention
- Mobile-responsive form rendering

## Comparison Notes

### vs OpenRegister
- OpenRegister has public API endpoints but no visual public form builder
- NocoBase public forms create collection records; OpenRegister would create register objects
- This pattern could be valuable for citizen-facing data collection in Dutch government context
- Nextcloud has Forms app but it's survey-focused, not tied to data collections
