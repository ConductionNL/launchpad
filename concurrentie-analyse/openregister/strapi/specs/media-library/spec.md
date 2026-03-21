---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# Media Library

## Overview

Strapi's Media Library (`@strapi/upload`) is a built-in file management system that handles file uploads, image optimization, responsive image generation, and folder organization. It supports multiple storage providers (local, AWS S3, Cloudinary) via a provider abstraction layer and uses `sharp` for image processing.

## Core Features

### File Upload
- Multi-file upload support
- Configurable size limits per file
- MIME type validation
- Automatic filename sanitization (strips reserved characters)
- Temporary working directory per upload session
- Stream-based upload (avoids loading entire file into memory)

### Image Manipulation (sharp)
- **Thumbnail generation**: 245x156px, fit inside
- **Responsive formats**: auto-generated at configurable breakpoints
  - Large: 1000px width
  - Medium: 750px width
  - Small: 500px width
- **Image optimization**: configurable quality for JPEG, PNG, WebP, TIFF, AVIF
- **Format support**: JPEG, PNG, WebP, TIFF, SVG, GIF, AVIF
- **Metadata preservation**: dimensions, format detection

### Folder System
- Hierarchical folder organization
- Path-based folder structure stored in database
- API upload folder for programmatic uploads
- Folder CRUD with nested folder support

### AI Metadata (v5)
- AI-powered alternative text generation
- AI-powered caption generation
- Async job processing for metadata generation

## Storage Providers

### Provider Interface
```typescript
interface UploadProvider {
  upload(file: File): Promise<void>;
  uploadStream?(file: File): Promise<void>;  // Preferred, streaming
  delete(file: File): Promise<void>;
  checkFileSize?(file: File, options: { sizeLimit: number }): Promise<void>;
}
```

### Local Provider (`@strapi/provider-upload-local`)
- Stores files in `public/uploads/` directory
- Serves files via Koa static middleware
- Default provider, zero configuration

### AWS S3 Provider (`@strapi/provider-upload-aws-s3`)
- AWS SDK v3 for S3 operations
- Configurable bucket, region, ACL, prefix
- Stream upload support
- CDN-compatible URL generation

### Cloudinary Provider (`@strapi/provider-upload-cloudinary`)
- Cloudinary SDK integration
- Automatic image transformations
- CDN delivery

## File Model

```typescript
interface File {
  id: number;
  name: string;          // Display name
  alternativeText: string; // Alt text (accessibility)
  caption: string;       // Caption text
  width: number;         // Image width (px)
  height: number;        // Image height (px)
  formats: {             // Generated responsive formats
    thumbnail: FormatInfo;
    small: FormatInfo;
    medium: FormatInfo;
    large: FormatInfo;
  };
  hash: string;          // Unique hash for filename
  ext: string;           // File extension
  mime: string;          // MIME type
  size: number;          // File size (KB)
  url: string;           // Public URL
  previewUrl: string;    // Preview URL
  provider: string;      // Storage provider name
  provider_metadata: object; // Provider-specific metadata
  folderPath: string;    // Folder location
  createdAt: Date;
  updatedAt: Date;
  createdBy: User;
  updatedBy: User;
}
```

## Webhook Events

The Media Library emits webhook events:
- `media.create` - File uploaded
- `media.update` - File metadata updated
- `media.delete` - File deleted

## API Endpoints

### Content API
- `POST /api/upload` - Upload files
- `GET /api/upload/files` - List files
- `GET /api/upload/files/:id` - Get file info
- `DELETE /api/upload/files/:id` - Delete file

### Admin API
- `POST /upload` - Upload with admin context
- `POST /upload?id=x` - Replace existing file
- `GET /upload/files` - Admin file listing with filters
- `PUT /upload/files/:id` - Update file info
- Folder management endpoints

## Configuration

```typescript
// config/plugins.ts
export default {
  upload: {
    config: {
      sizeLimit: 250 * 1024 * 1024, // 250MB
      breakpoints: {
        xlarge: 1920,
        large: 1000,
        medium: 750,
        small: 500,
      },
      provider: 'local',
      providerOptions: {},
    },
  },
};
```

## Relevance to OpenRegister

**Key differences:**
- Strapi has its own file storage system; OpenRegister uses Nextcloud Files
- Strapi generates responsive images server-side; Nextcloud has preview generation
- Strapi file references are by ID; OpenRegister can reference files by Nextcloud path

**OpenRegister advantages:**
- Nextcloud Files provides sharing, versioning, WebDAV, collaborative editing
- No separate media management needed - files are already in Nextcloud
- Nextcloud's preview generator handles image thumbnails

**Features to note:**
- The provider abstraction pattern is well-designed for swappable storage backends
- AI metadata generation (alt text, captions) is an interesting v5 feature
- Responsive image breakpoint configuration is a nice developer experience
