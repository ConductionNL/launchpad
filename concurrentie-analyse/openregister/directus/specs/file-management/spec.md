---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# File Management

## Overview

Directus provides a comprehensive file/asset management system with multi-driver storage, on-the-fly image transformations, metadata extraction, folder organization, and resumable uploads (TUS protocol).

## Storage Drivers

Directus uses a pluggable storage driver architecture (`@directus/storage`):

| Driver | Package | Use Case |
|--------|---------|----------|
| **Local** | `@directus/storage-driver-local` | Local filesystem |
| **S3** | `@directus/storage-driver-s3` | AWS S3, MinIO, DigitalOcean Spaces |
| **GCS** | `@directus/storage-driver-gcs` | Google Cloud Storage |
| **Azure** | `@directus/storage-driver-azure` | Azure Blob Storage |
| **Cloudinary** | `@directus/storage-driver-cloudinary` | Cloudinary CDN |
| **Supabase** | `@directus/storage-driver-supabase` | Supabase Storage |

Multiple storage locations can be configured simultaneously, allowing files to be stored in different backends.

## File Metadata

Files are tracked in `directus_files` with rich metadata:
- `id` - UUID
- `storage` - Which storage location
- `filename_disk` - Name on disk
- `filename_download` - Display name for downloads
- `title` - Human-readable title (auto-generated from filename)
- `type` - MIME type
- `folder` - Virtual folder reference
- `uploaded_by` / `uploaded_on` - Attribution
- `modified_by` / `modified_on` - Last modification
- `filesize` - Size in bytes
- `width` / `height` - Image dimensions
- `duration` - Media duration
- `description` - User description
- `location` / `tags` - Organizational metadata
- `metadata` - Raw EXIF/media metadata (JSON)
- `focal_point_x` / `focal_point_y` - Image focal point for smart cropping
- `tus_id` / `tus_data` - TUS resumable upload state
- `embed` - External embed URL

## Image Transformations

The `AssetsService` provides on-the-fly image transformations using Sharp:

### Transformation Parameters (via query string on `/assets/:id`)
- `width` / `height` - Resize dimensions
- `fit` - Resize behavior: `cover`, `contain`, `fill`, `inside`, `outside`
- `quality` - JPEG/WebP quality (1-100)
- `format` - Output format: `jpg`, `png`, `webp`, `avif`, `tiff`
- `withoutEnlargement` - Prevent upscaling
- `focal_point_x` / `focal_point_y` - Smart crop center point

### Transformation Presets
System presets can be defined for common transformations (e.g., thumbnail, hero image). Custom presets prevent arbitrary transformation abuse.

### Caching
Transformed images are cached to disk/storage with a hash-based filename. Cache invalidation occurs when the original file is updated.

## Folder System

Virtual folders are stored in `directus_folders`:
- Hierarchical (folders can contain sub-folders)
- Used for organization only (actual storage paths are flat)
- Default upload folder configurable in settings

## Resumable Uploads (TUS)

Directus supports the TUS protocol for resumable large file uploads:
- Handles interrupted uploads gracefully
- Supports chunked uploading
- TUS state tracked in `tus_id` and `tus_data` fields

## File Import from URL

Files can be imported directly from a URL:
- Fetches the file via HTTP
- Extracts metadata
- Stores in the configured storage location

## Access Control

File access respects the permission system:
- `directus_files` collection permissions apply
- Assets endpoint validates read access before serving files
- Share links can grant temporary access to files

## Relevance to OpenRegister

OpenRegister uses Nextcloud Files for file management, which provides:
- **Advantages**: Existing file sharing, versioning, collaborative editing, desktop sync, mobile apps
- **Disadvantages**: No on-the-fly image transformations, no multi-cloud storage abstraction, tighter coupling to filesystem

Directus's approach is better for headless CMS use cases (serving optimized images to web frontends). OpenRegister + DocuDesk covers document generation, which Directus does not offer.

The TUS protocol for resumable uploads and Sharp-based image transformations are features worth considering for OpenRegister's file handling capabilities.
