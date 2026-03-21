---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# File Management

## Purpose

NocoBase's file manager plugin provides file upload, storage, and retrieval with support for multiple storage backends including local filesystem, Amazon S3, Alibaba Cloud OSS, and Tencent Cloud COS.

## Architecture Overview

The file manager (`plugin-file-manager`) uses a registry pattern for storage backends:

```
Upload Request -> multer middleware -> Storage Type Handler -> Backend
                                                                |
                                            Local / S3 / Ali OSS / TX COS
```

## Data Model

### Attachments Collection
- `filename` - Original filename
- `extname` - File extension
- `size` - File size in bytes
- `mimetype` - MIME type
- `path` - Storage path
- `url` - Public URL
- `storageId` - Which storage backend
- `meta` - Additional metadata (JSON)

### Storages Collection
- `title` - Storage display name
- `name` - Storage identifier
- `type` - Backend type (local/s3/ali-oss/tx-cos)
- `baseUrl` - Public URL prefix
- `path` - Storage path prefix
- `options` - Backend-specific config (credentials, bucket, region)
- `rules` - Upload rules (size limits, allowed types)
- `default` - Whether this is the default storage
- `paranoid` - Whether to soft-delete files

## Business Logic

### Storage Backends

1. **Local** (`StorageTypeLocal`) - Files stored on local filesystem, served via static middleware
2. **S3** (`StorageTypeS3`) - Amazon S3 compatible storage (also works with MinIO, etc.)
3. **Ali OSS** (`StorageTypeAliOss`) - Alibaba Cloud Object Storage Service
4. **TX COS** (`StorageTypeTxCos`) - Tencent Cloud Object Storage

### Upload Rules
- Maximum file size
- Allowed MIME types (via `mime-match` glob patterns)
- File naming strategy

### File Lifecycle
1. Upload via multipart form data
2. File stored in configured backend
3. Attachment record created in database
4. File deletion cascades: record delete triggers backend file removal
5. Paranoid mode: files preserved on record deletion

### Attachment Interface
The `AttachmentInterface` field type enables any collection to have file upload fields, rendered as file upload components in forms.

## Requirements

### Functional
- Multi-backend file storage
- File upload with progress
- File preview (images, documents)
- Storage configuration via admin UI
- Upload rules (size, type restrictions)
- File deletion cleanup

### Non-functional
- Streaming uploads for large files
- URL encoding for special characters
- Backend-specific credential management

## Comparison Notes

### vs Nextcloud File System
- Nextcloud has a full-featured file manager as its core; NocoBase treats files as attachments
- Nextcloud supports WebDAV, sharing, versioning, trash; NocoBase has basic CRUD
- Nextcloud has collaborative editing (OnlyOffice, Collabora); NocoBase has no document editing
- NocoBase supports Chinese cloud providers (Ali OSS, TX COS); Nextcloud focuses on S3/WebDAV
- OpenRegister leverages Nextcloud's file system directly; NocoBase manages its own
