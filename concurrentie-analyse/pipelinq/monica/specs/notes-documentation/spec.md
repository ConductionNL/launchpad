---
competitor: monica
analyzed_date: 2026-03-14
feature: notes-documentation
---

# Notes & Documentation

## Overview

Notes in Monica are per-contact text records that capture observations, memories, and details about interactions. They support emotions, full-text search, and are integrated into the contact activity feed.

## Data Model

### Note
- **Fields:** contact_id, vault_id, author_id, emotion_id, title, body
- **Relations:** contact, author (User), emotion
- **Search:** Full-text indexed on title + body via Scout
- **Feed:** Creates ContactFeedItem on create/update/delete (note_created, note_updated, note_destroyed)

### Emotion
- Account-scoped configurable emotions
- Can be attached to notes, calls, and life events

## Services

| Service | Purpose |
|---------|---------|
| CreateNote | Creates note linked to contact, generates feed item |
| UpdateNote | Updates note content, generates feed item |
| DestroyNote | Deletes note, generates feed item |

## File Management (Documents Module)

Separate from notes, contacts support file uploads:
- **UploadFile** service handles file upload to Uploadcare
- **DestroyFile** removes file association
- Polymorphic `File` model (uuid, original_filename, size, mime_type)
- Files can be attached to contacts or posts

## Photo Management

- Dedicated Photos module on contact page
- Photos stored via Uploadcare CDN
- One photo can be set as avatar (UpdatePhotoAsAvatar service)

## Pipelinq Relevance

- Notes are simple but effective -- title + body + emotion + author tracking
- The emotion attachment is unique and could inspire sentiment/status tracking in pipeline items
- Full-text search on notes is critical for knowledge retrieval
- File management via external CDN (Uploadcare) vs. Nextcloud's native file system is a key architectural difference
