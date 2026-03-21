---
status: draft
source: competitive-analysis
competitor: pocketbase
analyzed_date: 2026-03-14
---

# File Storage

## Summary
PocketBase includes built-in file upload, storage, and serving with automatic image thumbnail generation. Files can be stored locally or in S3-compatible storage.

## Key Features
- File field type with configurable max size and MIME type restrictions
- Multiple files per field (configurable maxSelect)
- Automatic image thumbnail generation (configurable sizes)
- Local filesystem or S3-compatible storage (configurable in admin UI)
- Protected files requiring file token for access
- Concurrent thumbnail generation with semaphore limiting
- Singleflight pattern to prevent duplicate thumbnail generation
- File tokens with configurable duration (default 180s)

## Architecture
- `core/field_file.go` (820 lines) - File field type with validation
- `apis/file.go` - File download and thumbnail endpoints
- `tools/filesystem/` - Filesystem abstraction (local + S3)

## API
```
POST /api/files/token                                    # Get file access token
GET  /api/files/{collection}/{recordId}/{filename}       # Download
GET  /api/files/{collection}/{recordId}/{filename}?thumb=100x100  # Thumbnail
```

## Relevance to OpenRegister
OpenRegister handles files through Nextcloud's file storage. PocketBase's integrated approach with automatic thumbnails and S3 support is simpler for standalone use, but OpenRegister leverages Nextcloud's mature file management, sharing, and versioning.
