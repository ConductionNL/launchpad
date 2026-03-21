# Directus Files and Assets

**Source:** https://docs.directus.io/guides/files/upload.html, https://docs.directus.io/guides/files/transform.html

## File Management

### Upload
- Multiple files can be uploaded simultaneously
- Any file type supported (not limited to images)
- Upload via Data Studio: drag-and-drop, file picker, or import from URL
- Upload via API: `POST /files` with `multipart/form-data` (final property must be `file`)

### File Library
- Centralized file storage with folder organization
- File details page for metadata editing

### API Upload
```js
import { createDirectus, rest, uploadFiles } from '@directus/sdk';

const formData = new FormData();
formData.append('file_1_property', 'Value');
formData.append('file', raw_file);

const result = await directus.request(uploadFiles(formData));
```

## Image Transformations

Dynamic image transformations via URL query parameters on the `/assets/{file-id}` endpoint.

### Basic Transformations

| Parameter | Description |
|-----------|-------------|
| `width` | Width in pixels |
| `height` | Height in pixels |
| `quality` | Image quality (1-100) |
| `withoutEnlargement` | Disable upscaling |
| `format` | Output format: auto, jpg, png, webp, tiff |
| `fit` | cover (default), contain, inside, outside |

### Focal Points
Transformations respect `focal_point_x` and `focal_point_y` stored in file object.

```
GET /assets/{id}?width=300&height=300&quality=50&fit=contain
```

### Advanced Transformations (Sharp API)
Full access to Sharp image processing library:
```
?transforms=[["rotate", 90], ["blur", 10], ["tint", "rgb(255, 0, 255)"]]
```

### Preset Transformations
- Restrict transformations to predefined presets
- Configure in project settings
- Key-based access for simplified requests
- Options: fit, width, height, quality, upscaling, format, additional Sharp transforms

## Storage Configuration
- Supports multiple storage adapters (local, S3, Google Cloud, Azure, etc.)
- Configurable via environment variables

## Comparison Notes (vs OpenRegister)

| Aspect | Directus | OpenRegister |
|--------|----------|-------------|
| File Storage | Built-in with multiple adapters | Nextcloud file system |
| Upload | REST API + Data Studio | Via Nextcloud + API |
| Image Transforms | On-the-fly with Sharp | Not available |
| Focal Points | Built-in per file | Not available |
| Transform Presets | Configurable in settings | Not applicable |
| CDN | Cloud tier includes CDN | Via Nextcloud/external |
| Folder Structure | Built-in file library | Nextcloud folders |
