# On-the-Fly Image Transformations

## Feature Summary
Directus provides dynamic image transformation via URL query parameters, powered by the Sharp image processing library. Transformed images are cached after first generation.

## How Directus Implements This

### Basic Transformations
```
GET /assets/{file-id}?width=300&height=300&quality=50&fit=contain
```

| Parameter | Options |
|-----------|---------|
| `width` | Pixels |
| `height` | Pixels |
| `quality` | 1-100 |
| `withoutEnlargement` | true/false |
| `format` | auto, jpg, png, webp, tiff |
| `fit` | cover, contain, inside, outside |

### Focal Points
- Per-file `focal_point_x` and `focal_point_y` coordinates
- Crops around focal point during transformations
- Defaults to image center

### Advanced Transformations (Sharp API)
Full Sharp API access via `transforms` parameter:
```
?transforms=[["rotate", 90], ["blur", 10], ["tint", "rgb(255, 0, 255)"]]
```

Supports: rotate, blur, tint, negate, flip, flop, sharpen, median, gamma, normalize, and all Sharp operations.

### Preset Transformations
- Define named presets in project settings
- Access via key parameter: `?key=thumbnail`
- Restrict allowed transformations to presets only
- Configure: fit, width, height, quality, upscaling, format, additional Sharp transforms

### Caching
- Transformed images are generated on first request
- Stored and served from cache for subsequent requests
- Reduces processing overhead

## OpenRegister Current State
OpenRegister uses Nextcloud's file system for file storage. No on-the-fly image transformation is available. Files are served as-is.

## Gap Analysis

| Capability | Directus | OpenRegister |
|-----------|----------|-------------|
| Resize | URL parameters | None |
| Format Conversion | Auto (webp/avif) | None |
| Quality Control | Per-request | None |
| Focal Points | Per-file coordinates | None |
| Sharp API | Full access | None |
| Presets | Named configurations | None |
| Caching | Built-in | N/A |
| CDN Integration | Cloud tier | Via Nextcloud/external |

## Competitive Impact
**Medium** — Image transformations are critical for web applications serving responsive images. However, many deployments use external CDNs (Cloudflare, imgproxy) for this. The gap is most significant for all-in-one platform positioning.

## Recommendation
OpenRegister could:
1. Integrate with Nextcloud's image preview system (already generates thumbnails)
2. Add URL-based transformation parameters to the file serving endpoint
3. Consider an ExApp sidecar running Sharp/imgproxy for advanced transformations
4. Document integration with external image CDNs
